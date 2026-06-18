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
                        <div id="vault-controls-container" style="margin-top: 20px;"></div>
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
        const scope = encodeURIComponent('accounts balance transactions cards offline_access');
        const state = encodeURIComponent(data.signed_state);
        const url = `https://auth.truelayer.com/?response_type=code&client_id=${clientId}&redirect_uri=${redirectUri}&scope=${scope}&providers=uk-ob-all&state=${state}`;
        window.location.href = url;
    }

    async function loadAccounts(userId) {
        try {
            const response = await fetch(`get_user_accounts.php?user_id=${userId}&debug=1&t=${Date.now()}`);
            const data = await response.json();

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

            // -- Vault Trigger UI --
            const vaultControls = document.getElementById('vault-controls-container');
            if (vaultControls) {
                vaultControls.innerHTML = `
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <button id="pulse-btn" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight:600;">
                            🔄 Trigger Vault Pulse
                        </button>
                        <span id="pulse-status" style="color: #94a3b8; font-size: 0.85rem;">Vault Ready</span>
                    </div>
                    <pre id="pulse-console" style="background: #0f172a; color: #4ade80; padding: 15px; border-radius: 8px; font-size: 0.75rem; max-height: 200px; overflow-y: auto; display: none; border: 1px solid #1e293b;"></pre>
                    
                    <div id="vault-data-viewer" style="margin-top: 20px; display: none;">
                        <h4 style="font-size: 0.9rem; color: #1e293b; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                            🔐 Data in Vault (Encrypted at Rest)
                            <span style="font-size: 0.7rem; background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px;">LIVE FROM SUPABASE</span>
                        </h4>
                        <div id="vault-transactions" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                    <tr>
                                        <th style="padding: 10px; text-align: left; color: #64748b;">Date</th>
                                        <th style="padding: 10px; text-align: left; color: #64748b;">Encrypted Description</th>
                                        <th style="padding: 10px; text-align: right; color: #64748b;">Encrypted Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="vault-table-body">
                                    <tr><td colspan="3" style="padding: 20px; text-align: center; color: #94a3b8;">No data in vault yet. Run a pulse to sync.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                const loadVaultData = async () => {
                    const viewer = document.getElementById('vault-data-viewer');
                    const tbody = document.getElementById('vault-table-body');
                    try {
                        const res = await fetch(`scripts/get_vault_data.php?user_id=${userId}`);
                        if (!res.ok) throw new Error("Fetch failed");
                        const data = await res.json();
                        if (data.transactions && data.transactions.length > 0) {
                            viewer.style.display = "block";
                            tbody.innerHTML = data.transactions.map(tx => `
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px; font-weight: 500;">${tx.date}</td>
                                    <td style="padding: 10px; font-family: monospace; color: #64748b; font-size: 0.7rem; word-break: break-all;">${tx.encrypted_description}</td>
                                    <td style="padding: 10px; text-align: right; font-family: monospace; color: #64748b; font-size: 0.7rem; word-break: break-all;">${tx.encrypted_amount}</td>
                                </tr>
                            `).join('');
                        }
                    } catch (e) { console.error("Vault fetch error:", e); }
                };

                loadVaultData();

                document.getElementById('pulse-btn').onclick = async function() {
                    const btn = this;
                    const status = document.getElementById('pulse-status');
                    const consoleArea = document.getElementById('pulse-console');
                    
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    status.innerText = "Gathering data...";
                    consoleArea.style.display = "block";
                    consoleArea.innerText = "> Initializing sync...\\n";

                    try {
                        const response = await fetch(`scripts/gather_trigger.php?user_id=${userId}`);
                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();

                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) break;
                            consoleArea.innerText += decoder.decode(value);
                            consoleArea.scrollTop = consoleArea.scrollHeight;
                        }
                        status.innerText = "Sync complete.";
                        loadVaultData();
                    } catch (err) {
                        consoleArea.innerText += "\\n[ERROR] " + err.message;
                        status.innerText = "Sync failed.";
                    } finally {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                    }
                };
            }
            // -- END Vault Trigger UI --
            // -- END Vault Trigger UI --

            if (accounts.length === 0) {
                container.innerHTML = '<p style="color:#64748b;">You currently have no bank accounts connected.</p>';
            } else {
                const daysLeft = (data.debug && data.debug.days_remaining !== undefined) ? data.debug.days_remaining : 90;
                const isCritical = daysLeft < 7;
                const statusColor = isCritical ? '#991b1b' : '#166534';
                const statusBg = isCritical ? '#fee2e2' : '#dcfce7';

                let html = accounts.map(acc => {
                    return `
                        <div class="account-card" style="border-left: 4px solid ${statusColor};">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 12px;">
                                <div>
                                    <strong style="font-size:1.1rem;">${acc.display_name || acc.account_id}</strong><br>
                                    <small style="color:#64748b; text-transform: uppercase; font-weight: bold;">${acc.provider?.provider_id ?? 'unknown'}</small>
                                </div>
                                <div style="text-align:right;">
                                    <span style="display:inline-block; padding: 4px 8px; border-radius: 4px; background:${statusBg}; color:${statusColor}; font-size:0.8rem; font-weight:bold;">
                                        ACTIVE • ${daysLeft} DAYS LEFT
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                                <div style="font-size:0.85rem; color:#64748b;">
                                    🔄 Automatic background refresh active
                                </div>
                                <button onclick="alert('Disconnect functionality coming soon')" class="btn" style="background:#fee2e2; color:#991b1b; padding:6px 12px; font-size:0.8rem; border:1px solid #fecaca; cursor:pointer; min-width:100px;">
                                    Disconnect
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
                container.innerHTML = html;
            }

            // Remove the separate statusDiv logic from below since it's now integrated
            if (data.debug && data.debug.attempting_token_refresh) {
                console.log("%c🔄 Token Refresh Attempted", "color: #3b82f6; font-weight: bold;");
                if (data.debug.token_refresh_success) {
                    console.log("%c✅ Token Refresh Successful", "color: #10b981; font-weight: bold; font-size: 1.1em;");
                }
            }

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
