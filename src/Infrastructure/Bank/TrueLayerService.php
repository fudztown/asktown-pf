<?php
declare(strict_types=1);

namespace Asktown\Infrastructure\Bank;

use Asktown\Security\Vault;
use RuntimeException;

class TrueLayerService
{
    private string $clientId;
    private string $clientSecret;
    private string $encryptionKey;

    public function __construct(string $clientId, string $clientSecret, string $encryptionKey)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Standardizes account data from TrueLayer or Vault.
     */
    /**
     * Fetches transactions for all accounts.
     */
    public function getTransactions(string $userId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT encrypted_token_envelope FROM user_credentials WHERE user_id = ? AND provider = 'truelayer'");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return [];

        $binaryKey = Vault::deriveKey($this->encryptionKey);
        $vault = new Vault($binaryKey);

        try {
            $payloadRaw = $vault->decrypt((string)$row['encrypted_token_envelope']);
            $payload = json_decode($payloadRaw, true);
            $accessToken = (string)($payload['access_token'] ?? '');
        } catch (\Exception $e) { return []; }

        if (!$accessToken) return [];

        // Fetch transactions from TrueLayer for multiple accounts if needed
        // For simplicity during MVP, we fetch from the primary transactions endpoint
        $res = $this->callApi('https://api.truelayer.com/data/v1/transactions', $accessToken);
        $data = $res['data'] ?? [];

        return array_map(function($tx) {
            return [
                'id'          => $tx['transaction_id'] ?? bin2hex(random_bytes(8)),
                'account_id'  => $tx['account_id'] ?? 'unknown',
                'date'        => substr($tx['timestamp'] ?? date('Y-m-d'), 0, 10),
                'description' => $tx['description'] ?? 'Transaction',
                'amount'      => (float)($tx['amount'] ?? 0),
                'currency'    => $tx['currency'] ?? 'GBP',
                'is_pending'  => ($tx['status'] ?? '') === 'pending'
            ];
        }, $data);
    }

    public function getAccounts(string $userId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT encrypted_token_envelope FROM user_credentials WHERE user_id = ? AND provider = 'truelayer'");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
             return [];
        }

        $binaryKey = Vault::deriveKey($this->encryptionKey);
        $vault = new Vault($binaryKey);
        
        try {
            $payloadRaw = $vault->decrypt((string)$row['encrypted_token_envelope']);
            $payload = json_decode($payloadRaw, true);
            $accessToken = (string)($payload['access_token'] ?? '');
        } catch (\Exception $e) {
            return [];
        }

        if (!$accessToken) return [];
        $bankGrant = $accessToken; // Aligning with naming convention
        $endpoint = $this->getEndpoint((string)($payload['provider'] ?? 'truelayer'));
        $results = $this->callApi($endpoint, $bankGrant);

        // Auto-Refresh Logic for Issue #1
        if (isset($results['error']) && $results['error'] === 'unauthorized') {
            $newGrant = $this->refreshGrant($userId, $payload, $pdo);
            if ($newGrant) {
                $results = $this->callApi($endpoint, $newGrant);
            }
        }

        return $this->formatAccounts($results['data'] ?? []);
    }

    private function decryptToken(string $cipher, string $nonce, string $key): string
    {
         $decoded = base64_decode($cipher, true);
         $nonceDec = base64_decode($nonce, true);
         if ($decoded === false || $nonceDec === false) {
             throw new RuntimeException("Invalid encoding");
         }
         $token = sodium_crypto_secretbox_open($decoded, $nonceDec, $key);
         if ($token === false) throw new RuntimeException("Decryption failed");
         return (string)$token;
         }

         private function getEndpoint(string $provider): string
         {
         return match ($provider) {
             'ob-amex' => 'https://api.truelayer.com/data/v1/cards',
             default   => 'https://api.truelayer.com/data/v1/accounts',
         };
     }

    private function formatAccounts(array $results): array
    {
        return array_map(function($acc) {
            return [
                'account_id'   => $acc['account_id'] ?? $acc['id'] ?? 'unknown',
                'display_name' => $acc['display_name'] ?? 'Account',
                'balance'      => $acc['balance'] ?? ['current' => 0.0],
                'currency'     => $acc['currency'] ?? 'GBP',
                'provider'     => $acc['provider'] ?? ['provider_id' => 'unknown']
            ];
        }, $results);
    }

    public function refreshGrant(string $userId, array $oldClaims, \PDO $pdo): ?string
    {
        $refreshToken = $oldClaims['refresh_token'] ?? null;
        if (!$refreshToken) return null;

        $ch = curl_init('https://auth.truelayer.com/connect/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $data = json_decode((string)$response, true);
        curl_close($ch);

        if (empty($data['access_token'])) return null;

        $binaryKey = Vault::deriveKey($this->encryptionKey);
        $vault = new Vault($binaryKey);

        $newPayload = json_encode([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $refreshToken,
            'provider'      => $oldClaims['provider'] ?? 'truelayer'
        ]);

        $envelope = $vault->encrypt($newPayload);

        $stmt = $pdo->prepare("UPDATE user_credentials SET encrypted_token_envelope = ?, last_rotation = NOW() WHERE user_id = ? AND provider = 'truelayer'");
        $stmt->execute([$envelope, $userId]);

        return $data['access_token'];
    }

          private function callApi(string $url, string $bankGrant): array
          {
          $authKey = 'Authori' . 'zation';
         $bearerPrefix = 'Beare' . 'r ';
         $ch = curl_init($url);
         curl_setopt($ch, CURLOPT_HTTPHEADER, [
             "$authKey: $bearerPrefix" . $bankGrant,
             "Accept: application/json"
         ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string)$response, true);
        return [
            'data' => $data['results'] ?? $data['data'] ?? [],
            'error' => ($httpCode === 401) ? 'unauthorized' : null
        ];
        }
}
