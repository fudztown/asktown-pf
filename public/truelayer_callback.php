<?php
/**
 * TrueLayer OAuth Callback Handler
 */
@file_put_contents("/tmp/truelayer_debug.log", date('c') . " | CALLBACK HIT\n", FILE_APPEND);

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

    @file_put_contents("/tmp/truelayer_debug.log", date('c') . " | TOKEN_EXCHANGE | HTTP=$httpCode | Response=" . substr($response ?: 'EMPTY', 0, 300) . "\n", FILE_APPEND);

    if ($httpCode !== 200 || !$response) {
        header('Location: /index.php?truelayer_error=token_exchange_failed');
        exit;
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['access_token'])) {
        header('Location: /index.php?truelayer_error=invalid_token_response');
        exit;
    }

    // Encrypt and store
    $key   = sodium_crypto_generichash($encryptionKey, '', 32);
    $nonce = random_bytes(24);

    $encrypted = [
        'access_token'  => base64_encode(sodium_crypto_secretbox($tokens['access_token'], $nonce, $key)),
        'refresh_token' => base64_encode(sodium_crypto_secretbox($tokens['refresh_token'] ?? '', $nonce, $key)),
        'nonce'         => base64_encode($nonce),
        'created_at'    => date('c'),
        'provider'      => 'truelayer',
    ];

    $userDir = "/opt/finance/users/$userId";
    if (!is_dir($userDir)) {
        @mkdir($userDir, 0700, true);
    }

    $tokenFile = "$userDir/tokens.enc";
    @file_put_contents($tokenFile, json_encode($encrypted));
    @chmod($tokenFile, 0600);

    header('Location: /index.php?bank_connected=1');
    exit;

} catch (Throwable $e) {
    @file_put_contents("/tmp/truelayer_debug.log", date('c') . " | EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Location: /index.php?truelayer_error=internal_error');
    exit;
}
