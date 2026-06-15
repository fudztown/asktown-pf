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

if (!$encryptionKey) die("No encryption key\n");
if (!file_exists($tokenFile)) die("No token file\n");

$data = json_decode(file_get_contents($tokenFile), true);
if (empty($data['access_token']) || empty($data['nonce'])) die("Invalid token format\n");

$key = sodium_crypto_generichash($encryptionKey, '', 32);
$nonce = base64_decode($data['nonce']);
$accessToken = sodium_crypto_secretbox_open(base64_decode($data['access_token']), $nonce, $key);

if (!$accessToken) die("Failed to decrypt token\n");

// Get accounts
$ch = curl_init('https://api.truelayer.com/accounts');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $accessToken",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$accountsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("Failed to fetch accounts (HTTP $httpCode)\n");
}

$accounts = json_decode($accountsResponse, true);

echo "=== AMEX ACCOUNT ===\n\n";

foreach ($accounts['results'] as $account) {
    echo "Account ID: " . $account['account_id'] . "\n";
    echo "Provider: " . $account['provider']['provider_id'] . "\n";
    echo "Type: " . $account['account_type'] . "\n";
    echo "Name: " . ($account['display_name'] ?? 'N/A') . "\n\n";

    // Get balance
    $ch = curl_init('https://api.truelayer.com/accounts/' . $account['account_id'] . '/balance');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $balanceResponse = curl_exec($ch);
    curl_close($ch);

    $balance = json_decode($balanceResponse, true);
    if (isset($balance['results'][0])) {
        $b = $balance['results'][0];
        echo "Current Balance: " . $b['current'] . " " . $b['currency'] . "\n";
        if (isset($b['available'])) {
            echo "Available: " . $b['available'] . " " . $b['currency'] . "\n";
        }
    }
}
