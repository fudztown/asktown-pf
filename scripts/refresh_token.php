<?php
// Load .env
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env') as $line) {
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', trim($line), 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$encryptionKey = $_ENV['TOKEN_ENCRYPTION_KEY'] ?? null;
$tokenFile = '/opt/finance/tokens.enc';
$clientId = 'mypersonaldatagatherer-9a0289';
$clientSecret = '1002d6b5-189a-4bc8-8580-1b0030d95bde';

if (!$encryptionKey) die("ERROR: No encryption key\n");
if (!file_exists($tokenFile)) die("ERROR: No token file\n");

$data = json_decode(file_get_contents($tokenFile), true);
if (empty($data['refresh_token']) || empty($data['nonce'])) {
    die("ERROR: No refresh token stored\n");
}

$key = sodium_crypto_generichash($encryptionKey, '', 32);
$nonce = base64_decode($data['nonce']);

$refreshToken = sodium_crypto_secretbox_open(base64_decode($data['refresh_token']), $nonce, $key);
if (!$refreshToken) die("ERROR: Failed to decrypt refresh token\n");

echo "Refreshing access token...\n";

$ch = curl_init('https://auth.truelayer.com/connect/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'refresh_token',
    'refresh_token' => $refreshToken,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$newTokens = json_decode($response, true);

if ($httpCode !== 200 || !isset($newTokens['access_token'])) {
    die("ERROR: Refresh failed\n");
}

$newNonce = random_bytes(24);
$encrypted = [
    'access_token'  => base64_encode(sodium_crypto_secretbox($newTokens['access_token'], $newNonce, $key)),
    'refresh_token' => base64_encode(sodium_crypto_secretbox($newTokens['refresh_token'] ?? $refreshToken, $newNonce, $key)),
    'nonce'         => base64_encode($newNonce),
    'created_at'    => date('c'),
];

file_put_contents($tokenFile, json_encode($encrypted));
echo "SUCCESS: Token refreshed at " . date('c') . "\n";
