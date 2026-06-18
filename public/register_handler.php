<?php
session_start();
require_once __DIR__ . '/../config/supabase.php';

$supabaseUrl = get_supabase_url();
$supabaseKey = get_supabase_anon_key();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    $_SESSION['error'] = 'All fields are required.';
    header('Location: register.php');
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['error'] = 'Password must be at least 8 characters.';
    header('Location: register.php');
    exit;
}

// Call Supabase Auth REST API to sign up user
$ch = curl_init("$supabaseUrl/auth/v1/signup");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabaseKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'password' => $password,
    'data' => ['full_name' => $name]
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 || $httpCode === 201) {
    // Success - user created (may need email confirmation)
    $_SESSION['success'] = 'Account created successfully. Please check your email to confirm your account.';
    header('Location: login.php');
    exit;
} else {
    $errorMessage = $result['msg'] ?? $result['message'] ?? 'Registration failed. Please try again.';
    $_SESSION['error'] = $errorMessage;
    header('Location: register.php');
    exit;
}
