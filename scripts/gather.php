<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Vault.php';

use Asktown\Security\Vault;

// 1. Environment & Config
$envFile = '/opt/finance/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
            [$k, $v] = explode('=', trim($line), 2);
            putenv(trim($k) . '=' . trim($v));
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$encryptionKey = $_ENV['TOKEN_ENCRYPTION_KEY'] ?? getenv('TOKEN_ENCRYPTION_KEY');
$clientId      = $_ENV['TRUELAYER_CLIENT_ID']      ?? getenv('TRUELAYER_CLIENT_ID');
$clientSecret  = $_ENV['TRUELAYER_CLIENT_SECRET']  ?? getenv('TRUELAYER_CLIENT_SECRET');
$dbPass        = $_ENV['DB_PASSWORD']              ?? getenv('DB_PASSWORD');

if (!$encryptionKey || !$clientId || !$clientSecret || !$dbPass) {
    die("FATAL: Missing environment configuration.\n");
}

// 2. DB Connection (PostgreSQL)
try {
    $dsn = "pgsql:host=db.mabhliiixpfbsahabzgu.supabase.co;port=5432;dbname=postgres";
    $pdo = new PDO($dsn, "postgres", $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB CONNECTION FAILED: " . $e->getMessage() . "\n");
}

// 3. User Discovery
$userBaseDir = '/opt/finance/users';
$userIds = array_diff(scandir($userBaseDir), ['.', '..']);

$vault = new Vault(Vault::deriveKey($encryptionKey));

foreach ($userIds as $userId) {
    echo "Processing User: $userId\n";
    $tokenPath = "$userBaseDir/$userId/tokens.enc";
    if (!file_exists($tokenPath)) continue;

    $tokens = json_decode(file_get_contents($tokenPath), true);
    $nonce = base64_decode($tokens['nonce'] ?? '');
    
    // Decrypt Refresh Token
    $refreshToken = sodium_crypto_secretbox_open(
        base64_decode($tokens['refresh_token'] ?? ''),
        $nonce,
        Vault::deriveKey($encryptionKey)
    );

    if (!$refreshToken) {
        echo "  [!] Failed to decrypt refresh token for $userId. Skipping.\n";
        continue;
    }

    // A. Refresh Access Token
    $ch = curl_init('https://auth.truelayer.com/connect/token');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type'    => 'refresh_token',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $authResp = json_decode(curl_exec($ch), true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($authResp['access_token'])) {
        echo "  [!] TrueLayer Auth Failed (HTTP $httpCode) for $userId. Skipping.\n";
        continue;
    }

    $accessToken = $authResp['access_token'];

    // B. Call TrueLayer for Accounts
    $ch = curl_init('https://api.truelayer.com/data/v1/accounts');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $accountsData = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($accountsData['results'])) {
        echo "  [i] No accounts found for $userId.\n";
        continue;
    }

    foreach ($accountsData['results'] as $acc) {
        $accIdRaw = $acc['account_id'];
        $accIdHash = hash('sha256', $accIdRaw); // Blind index for dedupe
        
        $encName = $vault->encrypt($acc['display_name'] ?? 'Bank Account');
        // Note: Balance is often in a separate endpoint; using dummy for structure
        $encBal  = $vault->encrypt("0.00"); 

        $stmt = $pdo->prepare("
            INSERT INTO bank_accounts (user_id, provider_id, account_id_hashed, encrypted_account_name, encrypted_balance, currency)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (account_id_hashed) DO UPDATE 
            SET encrypted_account_name = EXCLUDED.encrypted_account_name,
                encrypted_balance = EXCLUDED.encrypted_balance,
                last_updated = NOW()
            RETURNING id
        ");
        $stmt->execute([$userId, $acc['provider']['provider_id'], $accIdHash, $encName, $encBal, $acc['currency']]);
        $dbAccId = $stmt->fetchColumn();

        echo "  [+] Account Synced: " . ($acc['display_name'] ?? $accIdRaw) . "\n";
        
        // C. Fetch & Vault Transactions (Simple 30-day example)
        $ch = curl_init("https://api.truelayer.com/data/v1/accounts/$accIdRaw/transactions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $txData = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!empty($txData['results'])) {
            $txStmt = $pdo->prepare("
                INSERT INTO transactions (account_id, user_id, truelayer_id, date, encrypted_amount, encrypted_description, is_pending)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (truelayer_id) DO NOTHING
            ");
            foreach ($txData['results'] as $tx) {
                $encAmt  = $vault->encrypt((string)$tx['amount']);
                $encDesc = $vault->encrypt($tx['description'] ?? '');
                $txStmt->execute([
                    $dbAccId, 
                    $userId, 
                    $tx['transaction_id'], 
                    substr($tx['timestamp'], 0, 10), 
                    $encAmt, 
                    $encDesc, 
                    $tx['transaction_status'] === 'PENDING'
                ]);
            }
            echo "    [>] Synced " . count($txData['results']) . " transactions.\n";
        }
    }
}

echo "\nGather Pulse Complete: " . date('Y-m-d H:i:s') . "\n";
