<?php
session_start();
require_once __DIR__ . '/../config/supabase.php';

$supabaseUrl = get_supabase_url();
$supabaseKey = get_supabase_anon_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - asktown-pf</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="assets/js/supabase-client.js"></script>
    <style>
        .login-container {
            max-width: 400px;
            margin: 80px auto;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="login-container card">
        <h1 style="text-align:center;margin-bottom:8px;">asktown-pf</h1>
        <p style="text-align:center;color:#64748b;margin-bottom:32px;">Sign in to your account</p>

        <button onclick="signInWithGoogle()" class="btn" style="width:100%;background:white;border:1px solid #1e3a5f;color:#1e3a5f;margin-bottom:24px;display:flex;align-items:center;justify-content:center;gap:12px;">
            <img src="https://www.google.com/favicon.ico" width="20" height="20">
            Sign in with Google
        </button>

        <div style="text-align:center;color:#64748b;">
            Don't have an account? <a href="register.php">Create one</a>
        </div>
    </div>

    <script>
        window.SUPABASE_URL = '<?= $supabaseUrl ?>';
        window.SUPABASE_ANON_KEY = '<?= $supabaseKey ?>';

        async function signInWithGoogle() {
            const { error } = await window.supabaseClient.auth.signInWithOAuth({
                provider: 'google',
                options: {
                    redirectTo: window.location.origin + '/auth/callback.php'
                }
            });

            if (error) {
                alert('Login failed: ' + error.message);
            }
        }
    </script>
</body>
</html>
