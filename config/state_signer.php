<?php
/**
 * Signed State Helper for OAuth CSRF Protection
 */

function load_env($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

load_env('/opt/finance/.env');

function get_state_secret(): string {
    return $_ENV['OAUTH_STATE_SECRET'] ?? getenv('OAUTH_STATE_SECRET') ?? '';
}

function sign_state(string $userId): string {
    $secret = get_state_secret();
    if (!$secret) {
        throw new Exception('OAUTH_STATE_SECRET not configured');
    }
    $timestamp = time();
    $payload = $userId . '|' . $timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);
    return base64_encode($payload . '|' . $signature);
}

function verify_state(string $signedState): ?string {
    $secret = get_state_secret();
    if (!$secret) return null;
    $decoded = base64_decode($signedState);
    if (!$decoded) return null;
    $parts = explode('|', $decoded);
    if (count($parts) !== 3) return null;
    [$userId, $timestamp, $signature] = $parts;
    $expectedSignature = hash_hmac('sha256', $userId . '|' . $timestamp, $secret);
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }
    if (time() - (int)$timestamp > 600) {
        return null;
    }
    return $userId;
}
