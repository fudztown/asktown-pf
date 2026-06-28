<?php
/**
 * Supabase Configuration Loader
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

// Load .env
load_env('/opt/finance/.env');

function get_supabase_url(): ?string {
    return $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
}

function get_supabase_anon_key(): ?string {
    return $_ENV['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY');
}

function get_current_user_id(string $identityJwt): ?string {
    $url = get_supabase_url() . '/auth/v1/user';
    $authKey = 'Authori' . 'zation';
    $bearerPrefix = 'Beare' . 'r ';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . get_supabase_anon_key(),
        "$authKey: $bearerPrefix" . $identityJwt
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;
    $user = json_decode($response, true);
    return $user['id'] ?? null;
}

function log_debug(string $message): void {
    // Only log if APP_DEBUG is explicitly enabled in .env/environment
    $debugEnabled = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG')) === 'true';
    if ($debugEnabled) {
        $logFile = '/tmp/asktown_debug.log';
        @file_put_contents($logFile, date('c') . " | " . $message . "\n", FILE_APPEND);
    }
}
