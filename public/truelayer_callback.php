<?php
/**
 * TrueLayer OAuth Callback Handler
 */
require_once __DIR__ . '/../config/supabase.php';
log_debug("CALLBACK HIT");
if (!extension_loaded('sodium')) {
    header('Location: /index.php?truelayer_error=sodium_missing');
    exit;
}

@session_start();

$stateSignerPath = __DIR__ . '/../config/state_signer.php';
if (!file_exists($stateSignerPath)) {
    header('Location: /index.php?truelayer_error=missing_state_signer');
    exit;
}

require_once $stateSignerPath;

// Load .env
$envFile = '/opt/finance/.env';
if (file_exists($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
                [$k, $v] = explode('=', trim($line), 2);
                $_ENV[trim($k)] = trim($v);
                @putenv(trim($k) . '=' . trim($v));
            }
        }
    }
}

$clientId       = $_ENV['TRUELAYER_CLIENT_ID']       ?? getenv('TRUELAYER_CLIENT_ID')       ?? null;
$clientSecret   = $_ENV['TRUELAYER_CLIENT_SECRET']   ?? getenv('TRUELAYER_CLIENT_SECRET')   ?? null;
$encryptionKey  = $_ENV['TOKEN_ENCRYPTION_KEY']      ?? getenv('TOKEN_ENCRYPTION_KEY')      ?? null;
$redirectUri    = 'https://asktown.co.uk/callback';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$code  = $_GET['code']  ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    header('Location: /index.php?truelayer_error=' . urlencode($error));
    exit;
}
if (!$code || !$state) {
    header('Location: /index.php?truelayer_error=missing_params');
    exit;
}

$userId = @verify_state($state);
if (!$userId) {
    header('Location: /index.php?truelayer_error=invalid_state');
    exit;
}
if (!$clientSecret || !$encryptionKey || !$clientId) {
    header('Location: /index.php?truelayer_error=missing_credentials');
    exit;
}

try {
    $ch = curl_init('https://auth.truelayer.com/connect/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type'    => 'authorization_code',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'code'          => $code,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    log_debug("TOKEN_EXCHANGE | HTTP=$httpCode | Response=" . substr($response ?: 'EMPTY', 0, 300));

    if ($httpCode !== 200 || !$response) {
        header('Location: /index.php?truelayer_error=token_exchange_failed');
        exit;
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['access_token'])) {
        header('Location: /index.php?truelayer_error=invalid_token_response');
        exit;
    }

    // Vault and Database Integration
    require_once __DIR__ . '/../lib/Vault.php';
    $binaryKey = \Asktown\Security\Vault::deriveKey($encryptionKey);
    $vault = new \Asktown\Security\Vault($binaryKey);

    // Prepare Payload
    $payloadJson = json_encode([
        'access_token'  => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'] ?? null,
        'provider'      => 'truelayer'
    ]);

    // Encrypt using standardized Vault envelope: base64(nonce . ciphertext)
    $envelope = $vault->encrypt($payloadJson);

    // Save to Database (user_tokens table)
    $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
    $dsn = "pgsql:host=127.0.0.1;port=54322;dbname=postgres";
    try {
        $pdo = new PDO($dsn, "postgres", "postgres", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare("
            INSERT INTO user_credentials (user_id, provider, encrypted_token_envelope, last_rotation)
            VALUES (?, 'truelayer', ?, NOW())
            ON CONFLICT (user_id, provider) DO UPDATE SET 
                encrypted_token_envelope = EXCLUDED.encrypted_token_envelope,
                last_rotation = NOW()
        ");
        $stmt->execute([$userId, $envelope]);
    } catch (Throwable $e) {
        log_debug("DATABASE ERROR during save: " . $e->getMessage());
        header('Location: /index.php?truelayer_error=token_save_failed');
        exit;
    }

    header('Location: /index.php?bank_connected=1');
    header('Location: /index.php?bank_connected=1');
    exit;

} catch (Throwable $e) {
    log_debug("EXCEPTION: " . $e->getMessage());
    header('Location: /index.php?truelayer_error=internal_error');
    exit;
}
