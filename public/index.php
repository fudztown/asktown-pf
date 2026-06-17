<?php
require_once __DIR__ . '/../config/supabase.php';

$supabaseUrl = get_supabase_url();
$supabaseKey = get_supabase_anon_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>asktown-pf</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <style>
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background: #1e3a5f;
            color: white;
        }
        .nav a {
            color: white;
            margin-left: 20px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .personalized {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .welcome {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        .section {
            margin-bottom: 40px;
        }
        .account-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div style="font-weight:700;font-size:1.4rem;">asktown-pf</div>
        <div id="nav-right"></div>
    </nav>

    <div id="main-content"></div>

    <script>
        window.SUPABASE_URL = '<?= $supabaseUrl ?>';
        window.SUPABASE_ANON_KEY = '<?= $supabaseKey ?>';
    </script>
    <script src="assets/js/supabase-client.js"></script>
<script>
    async function init() {
        const { data: { session } } = await window.supabaseClient.auth.getSession();
        const navRight = document.getElementById('nav-right');
        const mainContent = document.getElementById('main-content');

        if (session && session.user) {
            const userId = session.user.id;

            navRight.innerHTML = `
                <div class="user-info">
                    <span>${session.user.email}</span>
                    <a href="#" onclick="logout()" style="color:white;">Logout</a>
                </div>
            `;

            mainContent.innerHTML = `
                <div class="personalized">
                    <div class="section">
                        <h1 class="welcome">Welcome back, ${session.user.user_metadata?.full_name || 'there'}!</h1>
                        <p style="color:#64748b;">Here's a quick overview of your finances.</p>
                    </div>

                    <div class="section card">
                        <h2>Connected Accounts</h2>
                        <div id="accounts-container"><p style="color:#64748b;">Loading accounts...</p></div>
                        <a href="#" onclick="startBankConnection('${userId}')" class="btn btn-primary" style="margin-top:16px;">Connect a Bank</a>
                    </div>

                    <div class="section card">
                        <h2>Financial Insights</h2>
                        <p style="color:#64748b;">Your monthly summary will appear here once you connect accounts.</p>
                    </div>

                    <div class="section card">
                        <h2>Account Settings</h2>
                        <p style="color:#64748b;">Manage your connected banks and preferences.</p>
                        <a href="#" class="btn" style="margin-top:16px;background:#64748b;color:white;">Manage Accounts</a>
                    </div>
                </div>
            `;

            // Now call loadAccounts AFTER mainContent is rendered so #accounts-container exists
            await loadAccounts(userId);

        } else {
            navRight.innerHTML = `
                <a href="#" onclick="signInWithGoogle()">Login</a>
                <a href="register.php" class="btn btn-accent">Get Started</a>
            `;

            mainContent.innerHTML = `
                <section style="text-align:center;padding:100px 20px;">
                    <h1 style="font-size:3rem;margin-bottom:16px;">Take Control of Your Finances</h1>
                    <p style="font-size:1.25rem;color:#64748b;max-width:600px;margin:0 auto 40px;">
                        Securely connect your accounts, track your spending, and get insights — all in one place.
                    </p>
                    <a href="register.php" class="btn btn-primary">Create Free Account</a>
                </section>

                <section id="features" style="max-width:1100px;margin:0 auto;padding:60px 20px;">
                    <h2 style="text-align:center;font-size:2.25rem;margin-bottom:40px;">Everything you need to manage your money</h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
                        <div class="card">
                            <h3>Bank Connections</h3>
                            <p style="color:#64748b;">Securely link your UK bank accounts using Open Banking.</p>
                        </div>
                        <div class="card">
                            <h3>Real-time Dashboard</h3>
                            <p style="color:#64748b;">Monitor balances, spending, and trends in one place.</p>
                        </div>
                        <div class="card">
                            <h3>Insights & Reports</h3>
                            <p style="color:#64748b;">Get clear insights into your financial health.</p>
                        </div>
                    </div>
                </section>

                <section id="pricing" style="background:#f8fafc;padding:60px 20px;text-align:center;">
                    <h2 style="font-size:2.25rem;margin-bottom:16px;">Simple Pricing</h2>
                    <p style="color:#64748b;max-width:500px;margin:0 auto 40px;">Start free. Upgrade when you need more power.</p>
                    
                    <div style="display:flex;justify-content:center;gap:24px;flex-wrap:wrap;">
                        <div class="card" style="width:300px;">
                            <h3>Free</h3>
                            <div style="font-size:2.5rem;margin:16px 0;color:#1e3a5f;">£0</div>
                            <ul style="text-align:left;line-height:1.8;color:#64748b;">
                                <li>Connect bank accounts</li>
                                <li>Basic dashboard</li>
                                <li>Monthly insights</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary" style="margin-top:24px;display:inline-block;">Get Started</a>
                        </div>
                        
                        <div class="card" style="width:300px;border:2px solid #1e3a5f;">
                            <h3>Pro <span style="font-size:0.9rem;color:#10b981;">(Coming Soon)</span></h3>
                            <div style="font-size:2.5rem;margin:16px 0;color:#1e3a5f;">£9/mo</div>
                            <ul style="text-align:left;line-height:1.8;color:#64748b;">
                                <li>Everything in Free</li>
                                <li>Personal Hermes Agent</li>
                                <li>Weekly reports & alerts</li>
                                <li>Priority support</li>
                            </ul>
                            <a href="#" class="btn" style="margin-top:24px;display:inline-block;background:#64748b;color:white;pointer-events:none;">Coming Soon</a>
                        </div>
                    </div>
                </section>
            `;
        }
    }

    async function logout() {
        await window.supabaseClient.auth.signOut();
        window.location.href = 'index.php';
    }

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

    async function startBankConnection(userId) {
        const response = await fetch('get_signed_state.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const data = await response.json();
        if (!data.signed_state) {
            alert('Failed to start bank connection');
            return;
        }
        const clientId = 'mypersonaldatagatherer-9a0289';
        const redirectUri = encodeURIComponent('https://asktown.co.uk/callback');
        const scope = encodeURIComponent('accounts balance transactions cards');
        const state = encodeURIComponent(data.signed_state);
        const url = `https://auth.truelayer.com/?response_type=code&client_id=${clientId}&redirect_uri=${redirectUri}&scope=${scope}&providers=uk-ob-all&state=${state}`;
        window.location.href = url;
    }

    async function loadAccounts(userId) {
        try {
            const response = await fetch(`get_user_accounts.php?user_id=${userId}&debug=1`);
            const data = await response.json();

            console.group("TrueLayer Debug Info");
            console.log("Full JSON Response:", data);
            console.log("PHP Debug Logs:", data.debug);

            if (data.error) {
                console.error("Backend Error:", data.error);
                console.error("Error Detail:", data.message || "No message");
            }

            if (data.accounts) {
                console.log("Accounts Found:", data.accounts.length);
                console.table(data.accounts);
            }
            console.groupEnd();

            const container = document.getElementById('accounts-container');
            if (!container) {
                console.error('No #accounts-container element found in the page');
                return;
            }

            // Support both old (data.accounts) and new (data.data) response formats
            const accounts = data.accounts || data.data || [];

            if (accounts.length === 0) {
                container.innerHTML = '<p style="color:#64748b;">You currently have no bank accounts connected.</p>';
                return;
            }

            container.innerHTML = accounts.map(acc => {
                let expiryHtml = '';
                if (acc.expiry && acc.expiry.refresh_expiry) {
                    const date = new Date(acc.expiry.refresh_expiry);
                    expiryHtml = `<small style="color:#64748b;">Refresh expires: ${date.toLocaleDateString()}</small>`;
                }
                return `
                    <div class="account-card">
                        <strong>${acc.display_name || acc.account_id}</strong><br>
                        <small style="color:#64748b;">${acc.provider?.provider_id ?? 'unknown'}</small><br>
                        ${expiryHtml}
                    </div>
                `;
            }).join('');

        } catch (err) {
            console.error('Fetch or parse error:', err);
            const container = document.getElementById('accounts-container');
            if (container) container.innerHTML = '<p style="color:#f87171;">Error loading accounts.</p>';
        }
    }

    init();
</script>
</body>
</html>
