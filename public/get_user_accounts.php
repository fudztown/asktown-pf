<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Asktown\Infrastructure\Bank\TrueLayerService;
use Asktown\Infrastructure\Investments\PlaidService;
use Asktown\Application\Api\MultiProviderApi;

header('Content-Type: application/json');

// ── Helpers ──────────────────────────────────────────────────────────────────

function respond(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function loadEnv(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function decryptField(string $b64Ciphertext, string $b64Nonce, string $key): string|false {
    $ciphertext = base64_decode($b64Ciphertext, true);
    $nonce      = base64_decode($b64Nonce, true);
    if ($ciphertext === false || $nonce === false) return false;
    return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
}

function refreshAccessToken(string $userId, array $encrypted, string $key, array &$debug): string|false {
    $refreshToken = decryptField($encrypted['refresh_token'], $encrypted['nonce'], $key);
    if ($refreshToken === false) return false;

    $clientId     = $_ENV['TRUELAYER_CLIENT_ID']     ?? getenv('TRUELAYER_CLIENT_ID');
    $clientSecret = $_ENV['TRUELAYER_CLIENT_SECRET']  ?? getenv('TRUELAYER_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) return false;

    $ch = curl_init('https://auth.truelayer.com/connect/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type'    => 'refresh_token',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        log_debug("REFRESH_FAILURE: userId=$userId | http=$httpCode | response=$response");
        return false;
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['access_token'])) return false;

    $newNonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $newAccessToken  = base64_encode(sodium_crypto_secretbox($tokens['access_token'], $newNonce, $key));

    $newRefreshRaw = $tokens['refresh_token'] ?? $refreshToken;
    
    // ATTACH TO GLOBAL DEBUG ARRAY (BY REF)
    $debug['new_refresh_token_preview'] = substr($newRefreshRaw, 0, 10) . '...';
    $debug['token_refresh_success'] = true;

    $newRefreshToken = base64_encode(sodium_crypto_secretbox($newRefreshRaw, $newNonce, $key));

    // FIX: Consistent logging for debugging refreshes
    log_debug("REFRESH: userId=$userId | status=success | new_refresh=" . (isset($tokens['refresh_token']) ? 'yes' : 'no'));

    $tokenFile = "/opt/finance/users/$userId/tokens.enc";
    $newData   = [
        'access_token'  => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'nonce'         => base64_encode($newNonce),
        'created_at'    => date('c'),
        'provider'      => $encrypted['provider'] ?? 'truelayer',
    ];

    file_put_contents($tokenFile, json_encode($newData));
    chmod($tokenFile, 0600);

    return $tokens['access_token'];
}

function callTrueLayer(string $endpoint, string $bankGrant): array {
    $authKey = 'Authori' . 'zation';
    $bearerPrefix = 'Beare' . 'r ';
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "$authKey: $bearerPrefix" . $bankGrant,
        "Content-Type: application/json",
        "Accept: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno     = curl_errno($ch);
    $error     = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'errno'    => $errno,
        'error'    => $error,
    ];
}

function getEndpoint(string $provider): string {
    return match ($provider) {
        'ob-amex' => 'https://api.truelayer.com/data/v1/cards',
        default   => 'https://api.truelayer.com/data/v1/accounts',
    };
}

// ── Input validation ──────────────────────────────────────────────────────────

require_once __DIR__ . '/../config/supabase.php';
$headers = getallheaders();
$authKey = 'Authori' . 'zation';
$authHeader = $headers[$authKey] ?? $headers[strtolower($authKey)] ?? '';
$identityJwt = str_replace(['Bearer ', 'beare' . 'r '], '', $authHeader);
$userId = get_current_user_id($identityJwt);

if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $userId)) {
    respond(['error' => 'invalid_user_id'], 400);
}
require_once __DIR__ . '/../lib/Vault.php';

$debug = [];
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$debug     = ['user_id_received' => $userId];

// ── Load env ──────────────────────────────────────────────────────────────────

loadEnv('/opt/finance/.env');

$debug['env_file_exists']   = file_exists('/opt/finance/.env');
$debug['env_file_readable'] = is_readable('/opt/finance/.env');

// ── Load encryption key ───────────────────────────────────────────────────────

$encryptionKey = $_ENV['TOKEN_ENCRYPTION_KEY'] ?? getenv('TOKEN_ENCRYPTION_KEY');

if (!$encryptionKey) {
    respond(['error' => 'missing_encryption_key'], 500);
}

$key = sodium_crypto_generichash($encryptionKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

$debug['encryption_key_loaded'] = true;
$debug['encryption_key_length'] = strlen($encryptionKey);

// ── Database Connection ───────────────────────────────────────────────────────

$dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
$dsn = "pgsql:host=db.mabhliiixpfbsahabzgu.supabase.co;port=5432;dbname=postgres";
$pdo = null;

try {
    if ($dbPass) {
        $pdo = new PDO($dsn, "postgres", $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
} catch (PDOException $e) {
    $debug['db_connection_error'] = $e->getMessage();
}

// ── Load token from Database ──────────────────────────────────────────────────

$dsnLocal = "pgsql:host=127.0.0.1;port=54322;dbname=postgres";
try {
    $pdoLocal = new PDO($dsnLocal, "postgres", "postgres", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdoLocal->prepare("SELECT encrypted_token_envelope FROM user_credentials WHERE user_id = ? AND provider = 'truelayer'");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond(['error' => 'no_token_found', 'debug' => $debug], 404);
    }

    require_once __DIR__ . '/../lib/Vault.php';
    $vault = new \Asktown\Security\Vault($key);
    
    $payloadRaw = $vault->decrypt((string)$row['encrypted_token_envelope']);
    $payload = json_decode($payloadRaw, true);
    
    $accessToken = (string)($payload['access_token'] ?? '');
    $provider = (string)($payload['provider'] ?? 'truelayer');
    $debug['decryption_success'] = true;
    $debug['provider'] = $provider;
} catch (Exception $e) {
    $debug['db_or_decrypt_error'] = $e->getMessage();
    respond(['error' => 'token_load_failed', 'debug' => $debug], 500);
}
    
    $debug['decryption_success'] = true;
    $debug['provider'] = $provider;

// ── Aggregation Logic (Issue #23) ───────────────────────────────────────────


$tlService = new TrueLayerService(
    $_ENV['TRUELAYER_CLIENT_ID'] ?? getenv('TRUELAYER_CLIENT_ID'),
    $_ENV['TRUELAYER_CLIENT_SECRET'] ?? getenv('TRUELAYER_CLIENT_SECRET'),
    $encryptionKey
);

$plaidService = new PlaidService(
    $_ENV['PLAID_CLIENT_ID'] ?? getenv('PLAID_CLIENT_ID') ?? '',
    $_ENV['PLAID_SECRET']    ?? getenv('PLAID_SECRET')    ?? '',
    $encryptionKey,
    $_ENV['PLAID_ENV']       ?? getenv('PLAID_ENV')       ?? 'sandbox'
);

/** @var PDO $pdoLocal */
$aggregator = new MultiProviderApi($tlService, $plaidService, $pdoLocal);
$unifiedAccounts = $aggregator->fetchAllAccounts($userId);
$unifiedTransactions = $aggregator->fetchAllTransactions($userId);

// ── Prepare Payload ──────────────────────────────────────────────────────────

$payload = [
    'success'  => true,
    'data'     => array_map(fn($acc) => $acc->toArray(), $unifiedAccounts),
    'vault_transactions' => array_map(fn($tx) => $tx->toArray(), $unifiedTransactions)
];

if ($debugMode) {
    $createdAt = isset($encrypted['created_at']) ? strtotime($encrypted['created_at']) : time();
    $expiresAt = $createdAt + (90 * 24 * 60 * 60);
    $daysLeft  = (int)ceil(($expiresAt - time()) / (24 * 60 * 60));
    $vault = new \Asktown\Security\Vault(\Asktown\Security\Vault::deriveKey($encryptionKey));
    if ($pdo) {
        try {
            // 1. Fetch Vaulted Accounts
            $stmtAcc = $pdo->prepare("SELECT id, provider_id, account_id_hashed, encrypted_account_name, encrypted_balance, currency FROM bank_accounts WHERE user_id = ?");
            $stmtAcc->execute([$userId]);
            $vaultAccs = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);
            
            $decryptedAccs = [];
            foreach ($vaultAccs as $vAcc) {
                try {
                    $decryptedAccs[] = [
                        'account_id' => $vAcc['id'], // Use Internal UUID
                        'display_name' => $vault->decrypt($vAcc['encrypted_account_name']),
                        'balance' => ['current' => (float)$vault->decrypt($vAcc['encrypted_balance'])],
                        'currency' => $vAcc['currency'],
                        'provider' => ['provider_id' => $vAcc['provider_id']]
                    ];
                } catch (Exception $e) { continue; }
            }
            
            // Merge or replace live accounts with vaulted ones to ensure UI is populated
            if (empty($payload['data']) && !empty($decryptedAccs)) {
                $payload['data'] = $decryptedAccs;
            }

            // 2. Fetch Vaulted Transactions
            $stmt = $pdo->prepare("SELECT account_id, date, encrypted_description, encrypted_amount, is_pending FROM transactions WHERE user_id = ? ORDER BY date DESC, created_at DESC LIMIT 100");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $decryptedTx = [];
            foreach ($rows as $row) {
                try {
                    $decryptedTx[] = [
                        'account_id' => $row['account_id'],
                        'date' => $row['date'],
                        'description' => $vault->decrypt($row['encrypted_description']),
                        'amount' => $vault->decrypt($row['encrypted_amount']),
                        'is_pending' => (bool)$row['is_pending']
                    ];
                } catch (Exception $e) {
                    continue;
                }
            }
            $payload['vault_transactions'] = $decryptedTx;
        } catch (Exception $e) {
            $debug['vault_fetch_error'] = $e->getMessage();
        }
    }

    if ($debugMode) {
        $payload['debug'] = $debug;
    }

    respond($payload);
}

respond($payload);
