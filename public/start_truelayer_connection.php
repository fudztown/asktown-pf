<?php
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../config/state_signer.php';

// In a real redirect, we might need the token in a cookie or session
// For this SPA, we'll look at the Bearer token or a session-stored one.
// Simplest for now: accept token as a query param for the redirect trigger
$accessToken = $_GET['t'] ?? ''; 

if (!$accessToken) {
    // If no token in URL, try headers (though direct <a href> won't have them)
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $accessToken = str_replace('Bearer ', '', $authHeader);
}

$userId = get_current_user_id($accessToken);

if (!$userId) {
    http_response_code(401);
    die("Unauthorized: Please login first.");
}

$state = sign_state($userId);
$clientId = $_ENV['TRUELAYER_CLIENT_ID'] ?? getenv('TRUELAYER_CLIENT_ID');
$redirectUri = 'https://asktown.co.uk/callback';
$scopes = 'info accounts balance cards transactions direct_debits standing_orders offline_access';
$providers = 'uk-cs-mock uk-ob-all'; // Explicitly allow all UK OB providers to prevent scope filtering

$authUrl = "https://auth.truelayer.com/?response_type=code"
    . "&client_id=" . urlencode($clientId)
    . "&redirect_uri=" . urlencode($redirectUri)
    . "&scope=" . urlencode($scopes)
    . "&providers=" . urlencode($providers)
    . "&state=" . urlencode($state)
    . "&response_mode=query";

header("Location: $authUrl");
exit;
