<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../lib/Vault.php';

use Asktown\Security\Vault;

// 1. Loading Environment & DB info
$envPath = '/opt/finance/.env';
if (!file_exists($envPath)) {
    die("Error: .env not found at $envPath\n");
}

function getEnvMap(string $path): array {
    $map = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $map[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
    return $map;
}

$env = getEnvMap($envPath);
$encKey = $env['TOKEN_ENCRYPTION_KEY'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

if (!$encKey || !$dbPass) {
    die("Error: Missing ENCRYPTION_KEY or DB_PASSWORD in .env\n");
}

// 2. Setup PDO (Local Supabase)
$dsn = "pgsql:host=127.0.0.1;port=54322;dbname=postgres";
try {
    $pdo = new PDO($dsn, "postgres", "postgres", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

// 3. Scan /opt/finance/users for token files
$basePath = '/opt/finance/users';
if (!is_dir($basePath)) {
    die("Error: Directory $basePath does not exist.\n");
}

$users = array_diff(scandir($basePath), ['.', '..']);
$count = 0;

echo "Starting token migration...\n";

foreach ($users as $userId) {
    $tokenFile = "$basePath/$userId/tokens.enc";
    if (!file_exists($tokenFile)) continue;

    echo "Processing user: $userId\n";
    $raw = file_get_contents($tokenFile);
    $data = json_decode($raw, true);

    if (!isset($data['access_token'], $data['nonce'])) {
        echo " [SKIP] Missing access_token or nonce for $userId\n";
        continue;
    }

    // Wrap the existing structure into a single JSON for the new envelope
    // This allows us to keep access_token and refresh_token together in one encrypted block
    $payload = json_encode([
        'access_token'  => $data['access_token'],
        'refresh_token' => $data['refresh_token'] ?? null,
        'nonce'         => $data['nonce'], // Store old nonce inside to allow decryption during refactor
        'provider'      => $data['provider'] ?? 'truelayer'
    ]);

    // Use our Vault to re-encrypt the whole thing into the standard standard base64(nonce.ciphertext)
    // We derive the key exactly as get_user_accounts.php does
    $binaryKey = sodium_crypto_generichash($encKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $vault = new Vault($binaryKey);
    $envelope = $vault->encrypt($payload);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_tokens (user_id, encrypted_token_envelope, last_rotation)
            VALUES (?, ?, NOW())
            ON CONFLICT (user_id) DO UPDATE SET 
                encrypted_token_envelope = EXCLUDED.encrypted_token_envelope,
                last_rotation = NOW()
        ");
        $stmt->execute([$userId, $envelope]);
        echo " [OK] Migrated to user_tokens table\n";
        $count++;
    } catch (Exception $e) {
        echo " [ERR] DB Error: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration complete. $count tokens moved to database.\n";
