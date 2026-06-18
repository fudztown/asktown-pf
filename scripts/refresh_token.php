<?php
declare(strict_types=1);

/**
 * Token Refresh CLI Script
 * Aligned with public/get_user_accounts.php logic
 */

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

// 1. Initial Setup
loadEnv(__DIR__ . '/../.env');

$encryptionKey = $_ENV['TOKEN_ENCRYPTION_KEY'] ?? getenv('TOKEN_ENCRYPTION_KEY');
$clientId     = $_ENV['TRUELAYER_CLIENT_ID']     ?? getenv('TRUELAYER_CLIENT_ID');
$clientSecret = $_ENV['TRUELAYER_CLIENT_SECRET']  ?? getenv('TRUELAYER_CLIENT_SECRET');

if (!$encryptionKey || !$clientId || !$clientSecret) {
    die("ERROR: Missing configuration (check .env for TOKEN_ENCRYPTION_KEY, TRUELAYER_CLIENT_ID, TRUELAYER_CLIENT_SECRET)\n");
}

$key = sodium_crypto_generichash($encryptionKey, '', 32);

// 2. Resolve User
$userId = $argv[1] ?? '';
if (!$userId) {
    die("Usage: php refresh_token.php <userId>\n");
}

$tokenFile = "/opt/finance/users/$userId/tokens.enc";
if (!file_exists($tokenFile)) {
    die("ERROR: Token file not found for user: $userId\n");
}

// 3. Decrypt Refresh Token
$data = json_decode(file_get_contents($tokenFile), true);
$nonce = base64_decode($data['nonce'] ?? '', true);
$encryptedRefresh = base64_decode($data['refresh_token'] ?? '', true);

if (!$nonce || !$encryptedRefresh) {
    die("ERROR: Invalid token file format\n");
}

$refreshToken = sodium_crypto_secretbox_open($encryptedRefresh, $nonce, $key);
if (!$refreshToken) {
    die("ERROR: Failed to decrypt refresh token. Check encryption key.\n");
}

echo "Attempting to refresh token for $userId...\n";

// 4. Call TrueLayer
$ch = curl_init('https://auth.truelayer.com/connect/token');
$authHeader = 'Authorization: Basic ' . base64_encode("$clientId:$clientSecret");

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type'    => 'refresh_token',
    'refresh_token' => $refreshToken,
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    $authHeader
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokens = json_decode($response, true);

if ($httpCode !== 200 || empty($tokens['access_token'])) {
    @file_put_contents("/tmp/truelayer_debug.log", date('c') . " | CLI_REFRESH_FAILURE: userId=$userId | http=$httpCode | response=$response\n", FILE_APPEND);
    die("ERROR: TrueLayer rejected refresh (HTTP $httpCode). See /tmp/truelayer_debug.log\n");
}

// 5. Re-encrypt and Save
$newNonce = random_bytes(24);
$newTokens = [
    'access_token'  => base64_encode(sodium_crypto_secretbox($tokens['access_token'], $newNonce, $key)),
    'refresh_token' => base64_encode(sodium_crypto_secretbox($tokens['refresh_token'] ?? $refreshToken, $newNonce, $key)),
    'nonce'         => base64_encode($newNonce),
    'created_at'    => date('c'),
    'provider'      => $data['provider'] ?? 'truelayer'
];

if (file_put_contents($tokenFile, json_encode($newTokens))) {
    chmod($tokenFile, 0600);
    echo "SUCCESS: Tokens refreshed and saved for $userId\n";
} else {
    die("ERROR: Failed to write to $tokenFile\n");
}
