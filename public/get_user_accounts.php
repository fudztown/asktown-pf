<?php
declare(strict_types=1);

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
        @file_put_contents("/tmp/truelayer_debug.log", date('c') . " | REFRESH_FAILURE: userId=$userId | http=$httpCode | response=$response\n", FILE_APPEND);
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
    @file_put_contents("/tmp/truelayer_debug.log", date('c') . " | REFRESH: userId=$userId | status=success | new_refresh=" . (isset($tokens['refresh_token']) ? 'yes' : 'no') . "\n", FILE_APPEND);

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

function callTrueLayer(string $endpoint, string $accessToken): array {
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
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
        default   => 'https://api.truelayer.com/data/v1/cards',
    };
}

// ── Input validation ──────────────────────────────────────────────────────────

$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? '';

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

// ── Load token file ───────────────────────────────────────────────────────────

$tokenDir  = "/opt/finance/users/$userId";
$tokenFile = "$tokenDir/tokens.enc";

$debug['token_directory']     = $tokenDir;
$debug['token_file_path']     = $tokenFile;
$debug['token_file_exists']   = file_exists($tokenFile);
$debug['token_file_readable'] = is_readable($tokenFile);

if (!file_exists($tokenFile) || !is_readable($tokenFile)) {
    respond(['error' => 'token_file_not_found', 'debug' => $debug], 404);
}

$raw = file_get_contents($tokenFile);
$debug['token_file_bytes'] = strlen($raw);

$encrypted = json_decode($raw, true);

if (!is_array($encrypted)) {
    respond(['error' => 'token_file_parse_error', 'debug' => $debug], 500);
}

$debug['token_file_parsed'] = true;
$debug['token_file_keys']   = array_keys($encrypted);

foreach (['access_token', 'refresh_token', 'nonce', 'created_at'] as $field) {
    $debug["has_{$field}_field"] = isset($encrypted[$field]);
}

if (!isset($encrypted['access_token'], $encrypted['nonce'])) {
    respond(['error' => 'token_file_missing_fields', 'debug' => $debug], 500);
}

// ── Decrypt access token ──────────────────────────────────────────────────────

$debug['decryption_attempted'] = true;

$accessToken = decryptField($encrypted['access_token'], $encrypted['nonce'], $key);

$debug['decryption_success'] = ($accessToken !== false);

if ($accessToken === false) {
    respond(['error' => 'decryption_failed', 'debug' => $debug], 500);
}

$debug['access_token_length'] = strlen($accessToken);
$debug['access_token_prefix'] = substr($accessToken, 0, 20) . '...';
$debug['token_created_at']    = $encrypted['created_at'] ?? 'unknown';

// ── Determine provider & endpoint ────────────────────────────────────────────

$provider = $encrypted['provider'] ?? 'truelayer';
$endpoint = getEndpoint($provider);

$debug['provider'] = $provider;
$debug['endpoint'] = $endpoint;

// -- Call TrueLayer ------------------------------------------------------------

$result   = callTrueLayer($endpoint, $accessToken);
$httpCode = $result['httpCode'];

$debug['truelayer_http_code']       = $httpCode;
$debug['curl_errno']                = $result['errno'];
$debug['curl_error']                = $result['error'] ?: null;
$debug['truelayer_response_length'] = strlen($result['response'] ?? '');

// -- FORCE REFRESH FOR TESTING --
// User wants to refresh on every page load for now.
$forceRefresh = false; 

if ($httpCode === 401 || $forceRefresh) {
    if ($forceRefresh) {
        @file_put_contents("/tmp/truelayer_debug.log", date('c') . " | DEBUG: Forcing refresh for testing\n", FILE_APPEND);
    }
    $debug['attempting_token_refresh'] = true;

    $newAccessToken = refreshAccessToken($userId, $encrypted, $key, $debug);

    if ($newAccessToken === false) {
        respond(['error' => 'token_refresh_failed', 'debug' => $debug], 401);
    }

    $debug['token_refresh_success'] = true;

    $result   = callTrueLayer($endpoint, $newAccessToken);
    $httpCode = $result['httpCode'];

    $debug['retry_http_code'] = $httpCode;
}

// ── Parse response ────────────────────────────────────────────────────────────

$data = json_decode($result['response'], true);

$debug['truelayer_json_parsed']    = is_array($data);
$debug['truelayer_raw_response']   = $result['response'];
$debug['truelayer_top_level_keys'] = is_array($data) ? array_keys($data) : [];

if ($httpCode !== 200) {
    respond([
        'error'    => 'truelayer_api_error',
        'status'   => $httpCode,
        'response' => $data,
        'debug'    => $debug,
    ]);
}

// ── Success ───────────────────────────────────────────────────────────────────

$payload = [
    'success'  => true,
    'provider' => $provider,
    'type'     => ($provider === 'ob-amex') ? 'cards' : 'accounts',
    'data'     => $data['results'] ?? $data,
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
