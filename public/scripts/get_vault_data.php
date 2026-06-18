<?php
declare(strict_types=1);

// asktown-finance/public/scripts/get_vault_data.php
header('Content-Type: application/json');

$envFile = '/opt/finance/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
            [$k, $v] = explode('=', trim($line), 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$dbPass = $_ENV['DB_PASSWORD'] ?? '';
$userId = $_GET['user_id'] ?? '';

if (!preg_match('/^[a-f0-9-]{36}$/', $userId)) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    $dsn = "pgsql:host=db.mabhliiixpfbsahabzgu.supabase.co;port=5432;dbname=postgres";
    $pdo = new PDO($dsn, "postgres", $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Fetch last 10 transactions for this user
    $stmt = $pdo->prepare("SELECT date, encrypted_description, encrypted_amount FROM transactions WHERE user_id = ? ORDER BY date DESC, created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['transactions' => $transactions]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
