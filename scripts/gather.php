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

    echo "  [i] Successfully refreshed token. Fetching accounts...\n";

    // B. Call TrueLayer for Accounts (trying /v1/accounts AND /v1/cards)
    $endpoints = [
        'accounts' => 'https://api.truelayer.com/data/v1/accounts',
        'cards'    => 'https://api.truelayer.com/data/v1/cards'
    ];

    $allAccounts = [];

    foreach ($endpoints as $type => $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $respRaw = curl_exec($ch) ?: '{}';
        $data = json_decode($respRaw, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($data['results'])) {
            echo "  [+] Found " . count($data['results']) . " $type.\n";
            foreach ($data['results'] as $item) {
                // Normalize account_id for cards vs accounts
                $item['_type'] = $type;
                $allAccounts[] = $item;
            }
        } else {
            echo "  [i] $type endpoint returned HTTP $httpCode/status: " . ($data['error'] ?? 'No results') . "\n";
        }
    }

    if (empty($allAccounts)) {
        echo "  [!] No accounts or cards found for $userId across all endpoints.\n";
        continue;
    }

    foreach ($allAccounts as $acc) {
        $accIdRaw = $acc['account_id'] ?? $acc['card_id'] ?? null;
        if (!$accIdRaw) continue;

        $accIdHash = hash('sha256', $accIdRaw);
        $displayName = $acc['display_name'] ?? ($acc['label'] ?? ($acc['card_network'] ?? 'Bank Account'));

        // -- FETCH BALANCE (NEW) --
        $balance = "0.00";
        $balUrl = ($acc['_type'] === 'cards')
            ? "https://api.truelayer.com/data/v1/cards/$accIdRaw/balance"
            : "https://api.truelayer.com/data/v1/accounts/$accIdRaw/balance";

        $ch = curl_init($balUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $balData = json_decode(curl_exec($ch) ?: '{}', true);
        curl_close($ch);

        if (!empty($balData['results'][0])) {
            $res = $balData['results'][0];
            // For cards, 'current' is the outstanding balance (the debt)
            $balance = (string)($res['current'] ?? $res['available'] ?? '0.00');
            echo "  [i] Balance for $displayName: $balance\n";
        }

        $encName = $vault->encrypt($displayName);
        $encBal  = $vault->encrypt($balance);

        $stmt = $pdo->prepare("
            INSERT INTO bank_accounts (user_id, provider_id, account_id_hashed, encrypted_account_name, encrypted_balance, currency)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (account_id_hashed) DO UPDATE 
            SET encrypted_account_name = EXCLUDED.encrypted_account_name,
                encrypted_balance = EXCLUDED.encrypted_balance,
                last_updated = NOW()
            RETURNING id
        ");
        $stmt->execute([$userId, $acc['provider']['provider_id'] ?? 'unknown', $accIdHash, $encName, $encBal, $acc['currency']]);
        $dbAccId = $stmt->fetchColumn();

        echo "  [+] Synced (" . $acc['_type'] . "): " . $displayName . "\n";
        
        // C. Fetch Transactions (Handle cards/accounts path)
        $txUrl = ($acc['_type'] === 'cards') 
            ? "https://api.truelayer.com/data/v1/cards/$accIdRaw/transactions"
            : "https://api.truelayer.com/data/v1/accounts/$accIdRaw/transactions";
            
        $ch = curl_init($txUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $txData = json_decode(curl_exec($ch) ?: '{}', true);
        curl_close($ch);

        if (!empty($txData['results'])) {
            $txStmt = $pdo->prepare("
                INSERT INTO transactions (account_id, user_id, truelayer_id, date, encrypted_amount, encrypted_description, is_pending)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (truelayer_id) DO NOTHING
            ");
            foreach ($txData['results'] as $tx) {
                $encAmt  = $vault->encrypt((string)($tx['amount'] ?? '0'));
                $encDesc = $vault->encrypt($tx['description'] ?? $tx['merchant_name'] ?? 'No description');
                
                // Fix: Ensure a real boolean is passed to PostgreSQL for $7 (is_pending)
                $isPending = (isset($tx['transaction_status']) && $tx['transaction_status'] === 'PENDING') ? 1 : 0;
                
                $txStmt->execute([
                    $dbAccId, 
                    $userId, 
                    $tx['transaction_id'], 
                    substr($tx['timestamp'] ?? date('Y-m-d'), 0, 10), 
                    $encAmt, 
                    $encDesc, 
                    $isPending
                ]);
            }
            echo "    [>] Synced " . count($txData['results']) . " transactions.\n";
        }
    }
}

echo "\nGather Pulse Complete: " . date('Y-m-d H:i:s') . "\n";
