<?php
session_start();
require_once __DIR__ . '/../config/supabase.php';
$supabaseUrl = get_supabase_url();
$supabaseKey = get_supabase_anon_key();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging you in...</title>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        body { background: #1e3a5f; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif; }
        .loader { border: 4px solid rgba(255,255,255,0.1); border-left-color: #fff; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div style="text-align: center;">
        <div class="loader" style="margin: 0 auto 20px;"></div>
        <p id="status-text">Securing your session...</p>
    </div>

    <script>
        const supabase = window.supabase.createClient('<?= $supabaseUrl ?>', '<?= $supabaseKey ?>');
        
        async function handleCallback() {
            try {
                // Check if we actually have a session in the URL hash or local storage
                const { data, error } = await supabase.auth.getSession();
                
                if (error) throw error;

                if (!data.session) {
                    // If no session found (e.g., user visited directly without OAuth)
                    // Redirect them back to login after a brief pause
                    document.getElementById('status-text').innerText = "No session found. Redirecting to login...";
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 1500);
                    return;
                }

                // Success - handoff to dashboard
                window.location.href = '/index.php';
            } catch (err) {
                console.error('Auth callback error:', err);
                document.getElementById('status-text').innerText = "Authentication error. Redirecting...";
                setTimeout(() => {
                    window.location.href = '/login.php?error=' + encodeURIComponent(err.message);
                }, 2000);
            }
        }

        handleCallback();
    </script>
</body>
</html>
