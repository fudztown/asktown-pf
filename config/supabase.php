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
