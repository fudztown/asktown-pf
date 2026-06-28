<?php
declare(strict_types=1);

/**
 * Plaid Link Token Creator
 * Goal: Initiate a Link session for Pensions/Investments.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';

function respond(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// 1. Identity Lockdown
$headers = getallheaders();
$authKey = 'Authori' . 'zation';
$authHeader = $headers[$authKey] ?? $headers[strtolower($authKey)] ?? '';
$identityJwt = str_replace(['Bearer ', 'beare' . 'r '], '', $authHeader);

$userId = get_current_user_id($identityJwt);
if (!$userId) respond(['error' => 'unauthorized'], 401);

// 2. Request Link Token from Plaid
$clientId = $_ENV['PLAID_CLIENT_ID'] ?? getenv('PLAID_CLIENT_ID');
$secret   = $_ENV['PLAID_SECRET']    ?? getenv('PLAID_SECRET');
$plaidEnv = $_ENV['PLAID_ENV']       ?? getenv('PLAID_ENV') ?? 'sandbox';

$ch = curl_init("https://$plaidEnv.plaid.com/link/token/create");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'client_id'     => $clientId,
    'secret'        => $secret,
    'client_name'   => 'AskTown Finance',
    'user'          => ['client_user_id' => $userId],
    'products'      => ['investments'],
    'country_codes' => ['GB'],
    'language'      => 'en'
]));

$response = curl_exec($ch);
$data = json_decode((string)$response, true);
curl_close($ch);

if (empty($data['link_token'])) {
    respond(['error' => 'link_token_failed', 'details' => $data], 500);
}

respond(['link_token' => $data['link_token']]);
