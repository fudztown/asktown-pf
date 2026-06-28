<?php
declare(strict_types=1);

namespace Asktown\Infrastructure\Investments;
use Asktown\Security\Vault;
/**
 * Plaid Service - Integration for Pensions and Investments
 */
class PlaidService
{
    public function __construct(
        private string $clientId,
        private string $secret,
        private string $encryptionKey,
        private string $environment = 'sandbox' // 'sandbox', 'development', 'production'
    ) {}

    /**
     * Fetches investment accounts for a given user.
     */
    public function getAccounts(string $userId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT encrypted_token_envelope FROM user_credentials WHERE user_id = ? AND provider = 'plaid'");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) return [];

        $binaryKey = Vault::deriveKey($this->encryptionKey);
        $vault = new Vault($binaryKey);

        try {
            $payloadRaw = $vault->decrypt((string)$row['encrypted_token_envelope']);
            $payload = json_decode($payloadRaw, true);
            $plaidAccessToken = (string)($payload['access_token'] ?? '');
        } catch (\Exception $e) {
            return [];
        }

        if (!$plaidAccessToken) return [];

        // Call Plaid Investments API
        return $this->callPlaid('/investments/holdings/get', $plaidAccessToken);
    }

    /**
     * Fetches transaction/investment entries.
     */
    public function getTransactions(string $userId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT encrypted_token_envelope FROM user_credentials WHERE user_id = ? AND provider = 'plaid'");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return [];

        $binaryKey = Vault::deriveKey($this->encryptionKey);
        $vault = new Vault($binaryKey);

        try {
            $payloadRaw = $vault->decrypt((string)$row['encrypted_token_envelope']);
            $payload = json_decode($payloadRaw, true);
            $plaidAccessToken = (string)($payload['access_token'] ?? '');
        } catch (\Exception $e) { return []; }

        if (!$plaidAccessToken) return [];

        // Using /investments/transactions/get (requires product 'investments')
        $resRaw = $this->callPlaidRaw('/investments/transactions/get', $plaidAccessToken);
        $data = $resRaw['investment_transactions'] ?? [];

        return array_map(function($tx) {
            return [
                'id'          => $tx['investment_transaction_id'],
                'account_id'  => $tx['account_id'],
                'date'        => $tx['date'],
                'description' => $tx['name'] . ' (' . ($tx['type'] ?? 'N/A') . ')',
                'amount'      => (float)$tx['amount'] * -1, // Plaid usually flips investment outflows
                'currency'    => $tx['iso_currency_code'] ?? 'GBP',
                'is_pending'  => false
            ];
        }, $data);
    }

    private function callPlaid(string $path, string $accessToken): array
    {
        $baseUrl = "https://{$this->environment}.plaid.com";
        $ch = curl_init($baseUrl . $path);
        
        $payload = json_encode([
            'client_id'    => $this->clientId,
            'secret'       => $this->secret,
            'access_token' => $accessToken
        ]);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        return $data['accounts'] ?? [];
    }

    private function callPlaidRaw(string $path, string $accessToken): array
    {
        $baseUrl = "https://{$this->environment}.plaid.com";
        $ch = curl_init($baseUrl . $path);
        
        $payload = json_encode([
            'client_id'    => $this->clientId,
            'secret'       => $this->secret,
            'access_token' => $accessToken
        ]);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$response, true);
        return $data;
    }
}
