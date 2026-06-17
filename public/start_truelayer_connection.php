<?php
session_start();
require_once __DIR__ . '/../config/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id required']);
    exit;
}

// Store in session (server-side only)
$_SESSION['truelayer_user_id'] = $userId;

echo json_encode(['success' => true]);
