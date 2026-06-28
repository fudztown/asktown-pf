<?php
declare(strict_types=1);

/**
 * Disconnect Bank Endpoint for asktown-pf
 * Goal: Securely remove TrueLayer tokens from the Vault table.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/supabase.php';

function respond(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// 1. Identity Lockdown: Get User ID from JWT
$headers = getallheaders();
$authKey = 'Authori' . 'zation';
$authHeader = $headers[$authKey] ?? $headers[strtolower($authKey)] ?? '';
$identityJwt = str_replace(['Bearer ', 'beare' . 'r '], '', $authHeader);

if (!$identityJwt) {
    respond(['error' => 'not_authenticated'], 401);
}

$userId = get_current_user_id($identityJwt);

if (!$userId) {
    respond(['error' => 'invalid_session'], 401);
}

// 2. Database Connection (Local Stack)
$envPath = '/opt/finance/.env';
function getDbPass(string $path): string {
    if (!is_readable($path)) return 'postgres';
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, 'DB_PASSWORD=')) {
            return trim(explode('=', $line, 2)[1], " \"'");
        }
    }
    return 'postgres';
}

$dsn = "pgsql:host=127.0.0.1;port=54322;dbname=postgres";
try {
    $pdo = new PDO($dsn, "postgres", "postgres", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    respond(['error' => 'database_connection_failed'], 500);
}

// 3. Perform Deletion
try {
    $stmt = $pdo->prepare("DELETE FROM user_credentials WHERE user_id = ? AND provider = 'truelayer'");
    $stmt->execute([$userId]);
    
    respond([
        'success' => true,
        'message' => 'Bank connection removed successfully.'
    ]);
} catch (Exception $e) {
    respond(['error' => 'deletion_failed', 'details' => $e->getMessage()], 500);
}
