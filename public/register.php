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
    <title>Register - asktown-pf</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="assets/js/supabase-client.js"></script>
    <style>
        .register-container {
            max-width: 420px;
            margin: 60px auto;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="register-container card">
        <h1 style="text-align:center;margin-bottom:8px;">asktown-pf</h1>
        <p style="text-align:center;color:#64748b;margin-bottom:32px;">Create your free account</p>

        <button onclick="signInWithGoogle()" class="btn" style="width:100%;background:white;border:1px solid #1e3a5f;color:#1e3a5f;margin-bottom:24px;display:flex;align-items:center;justify-content:center;gap:12px;">
            <img src="https://www.google.com/favicon.ico" width="20" height="20">
            Sign up with Google
        </button>

        <div style="text-align:center;color:#64748b;margin:20px 0;">or</div>

        <form action="register_handler.php" method="POST">
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;">Full Name</label>
                <input type="text" name="name" required style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;">Email Address</label>
                <input type="email" name="email" required style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
            </div>
            <div style="margin-bottom:24px;">
                <label style="display:block;margin-bottom:6px;color:#64748b;">Password</label>
                <input type="password" name="password" required minlength="8" style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create Free Account</button>
        </form>

        <div style="text-align:center;margin-top:24px;color:#64748b;">
            Already have an account? <a href="login.php">Sign in</a>
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
                alert('Registration failed: ' + error.message);
            }
        }
    </script>
</body>
</html>
