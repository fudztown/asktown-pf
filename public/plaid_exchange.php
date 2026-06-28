<?php
declare(strict_types=1);

/**
 * Plaid Token Exchange Endpoint
 * Goal: Swap Link public_token for Access Token and save to Vault.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../lib/Vault.php';

use Asktown\Security\Vault;

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

$userId = get_current_user_id($identityJwt);
if (!$userId) {
    respond(['error' => 'unauthorized'], 401);
}

// 2. Input Validation
$input = json_decode(file_get_contents('php://input'), true);
$publicToken = $input['public_token'] ?? null;

if (!$publicToken) {
    respond(['error' => 'missing_public_token'], 400);
}

// 3. Exchange public_token for access_token
$clientId = $_ENV['PLAID_CLIENT_ID'] ?? getenv('PLAID_CLIENT_ID');
$secret   = $_ENV['PLAID_SECRET']    ?? getenv('PLAID_SECRET');
$plaidEnv = $_ENV['PLAID_ENV']       ?? getenv('PLAID_ENV') ?? 'sandbox';

$ch = curl_init("https://$plaidEnv.plaid.com/item/public_token/exchange");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'client_id'    => $clientId,
    'secret'       => $secret,
    'public_token' => $publicToken
]));

$response = curl_exec($ch);
$data = json_decode((string)$response, true);
curl_close($ch);

if (empty($data['access_token'])) {
    respond(['error' => 'exchange_failed', 'details' => $data], 500);
}

// 4. Encrypt and Save to user_credentials
$encryptionKey = $_ENV['TOKEN_ENCRYPTION_KEY'] ?? getenv('TOKEN_ENCRYPTION_KEY');
$binaryKey = Vault::deriveKey($encryptionKey);
$vault = new Vault($binaryKey);

$payload = json_encode([
    'access_token' => $data['access_token'],
    'item_id'      => $data['item_id'],
    'provider'     => 'plaid'
]);

$envelope = $vault->encrypt($payload);

try {
    $dsn = "pgsql:host=127.0.0.1;port=54322;dbname=postgres";
    $pdo = new PDO($dsn, "postgres", "postgres", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $stmt = $pdo->prepare("
        INSERT INTO user_credentials (user_id, provider, encrypted_token_envelope, last_rotation)
        VALUES (?, 'plaid', ?, NOW())
        ON CONFLICT (user_id, provider) DO UPDATE SET 
            encrypted_token_envelope = EXCLUDED.encrypted_token_envelope,
            last_rotation = NOW()
    ");
    $stmt->execute([$userId, $envelope]);

    respond(['success' => true]);
} catch (Exception $e) {
    respond(['error' => 'vault_save_failed', 'message' => $e->getMessage()], 500);
}
