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
    <script>
        window.supabaseClient = supabase.createClient('<?= $supabaseUrl ?>', '<?= $supabaseKey ?>');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background: #1e3a5f; color: white; border-bottom: 2px solid rgba(255,255,255,0.1); }
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .section-h2 { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; margin-top:0; }
        .account-card { padding: 20px; border-radius: 12px; margin-bottom: 16px; border: 1px solid #e2e8f0; background: white; border-left: 4px solid #1e3a5f; }
        #pulse-console { background: #0f172a; color: #4ade80; padding: 15px; border-radius: 8px; font-size: 0.75rem; max-height: 200px; overflow-y: auto; display: none; margin-bottom: 20px; font-family: monospace; }
        .btn-primary { background: #1e3a5f; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; display: inline-block; }
        .splash-h1 { font-size: 3.5rem; color: #1e3a5f; margin-bottom: 16px; font-weight: 800; letter-spacing: -0.05em; line-height: 1.1; }
        .hero { text-align: center; padding: 100px 20px; }
        .badge { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; }
        .insight-box { padding: 15px; border-radius: 8px; border-left: 4px solid #1e3a5f; }
        .amount-big { font-size: 1.8rem; font-weight: 800; line-height: 1; margin-top: 5px; }
    </style>
</head>
<body style="background-color: #f8fafc; font-family: -apple-system, system-ui, sans-serif; margin: 0;">
    
    <div id="app-root"></div>

    <script>
        const userId_fixed = 'f6b1e9eb-03a4-470b-a5da-8de341f3c15a';

        async function signInWithGoogle() {
            const { error } = await window.supabaseClient.auth.signInWithOAuth({
                provider: 'google',
                options: { redirectTo: window.location.origin + '/auth/callback.php' }
            });
            if (error) alert('Login failed: ' + error.message);
        }

        async function logout() {
            await window.supabaseClient.auth.signOut();
            window.location.reload();
        }

        function switchTab(tab, accId) {
            document.getElementById('view-summary-' + accId).style.display = 'none';
            document.getElementById('view-details-' + accId).style.display = 'none';
            document.getElementById('view-' + tab + '-' + accId).style.display = 'block';
            
            const sBtn = document.getElementById('tab-summary-' + accId);
            const dBtn = document.getElementById('tab-details-' + accId);
            if (tab === 'summary') {
                sBtn.style.background = 'white'; sBtn.style.fontWeight = '700';
                dBtn.style.background = 'transparent'; dBtn.style.fontWeight = '500';
            } else {
                dBtn.style.background = 'white'; dBtn.style.fontWeight = '700';
                sBtn.style.background = 'transparent'; sBtn.style.fontWeight = '500';
            }
        }

        async function triggerPulse() {
            const btn = document.getElementById('pulse-btn');
            const consoleArea = document.getElementById('pulse-console');
            btn.disabled = true;
            consoleArea.style.display = "block";
            consoleArea.innerText = "> Initializing sync...\n";
            try {
                const response = await fetch('scripts/gather_trigger.php?user_id=' + userId_fixed);
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    consoleArea.innerText += decoder.decode(value);
                    consoleArea.scrollTop = consoleArea.scrollHeight;
                }
                setTimeout(() => location.reload(), 1000);
            } catch (err) {
                consoleArea.innerText += "\n[ERR] " + err.message;
                btn.disabled = false;
            }
        }

        function renderAccount(acc, txs) {
            const accId = acc.account_id;
            const accountTxs = txs.filter(t => t.account_id === accId || t.account_id_hashed === accId);
            
            let txHtml = accountTxs.length === 0 ? 
                '<p style="text-align:center; color:#94a3b8; font-size:0.8rem;">No transactions vaulted yet.</p>' :
                accountTxs.map(t => {
                    const amt = parseFloat(t.amount);
                    const color = amt < 0 ? '#ef4444' : '#10b981';
                    return '<div style="display:flex; justify-content:space-between; font-size:0.85rem; border-bottom:1px solid #f1f5f9; padding:6px 0;">' +
                           '<span style="color:#1e293b;">' + t.description + '</span>' +
                           '<strong style="color:' + color + ';">£' + Math.abs(amt).toFixed(2) + '</strong></div>';
                }).join('');

            return '<div class="account-card">' +
                   '<div style="display:flex; justify-content:space-between; margin-bottom:12px;">' +
                   '<div><strong style="color:#0f172a; font-size:1.1rem;">' + acc.display_name + '</strong><br>' +
                   '<small style="color:#64748b; font-weight:bold; text-transform:uppercase;">' + (acc.provider?.provider_id || 'BANK') + '</small></div>' +
                   '<span class="badge">Active</span></div>' +
                   '<div style="background:#f1f5f9; padding:2px; border-radius:6px; display:flex; gap:4px; margin-bottom:12px;">' +
                   '<button id="tab-summary-' + accId + '" onclick="switchTab(\'summary\', \'' + accId + '\')" style="border:none; padding:4px 10px; font-size:0.7rem; border-radius:4px; cursor:pointer; background:white; font-weight:700;">Summary</button>' +
                   '<button id="tab-details-' + accId + '" onclick="switchTab(\'details\', \'' + accId + '\')" style="border:none; padding:4px 10px; font-size:0.7rem; border-radius:4px; cursor:pointer; background:transparent;">Transactions</button></div>' +
                   '<div id="view-summary-' + accId + '" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:0.85rem; display:flex; justify-content:space-between;">' +
                   '<span style="color:#64748b;">Vault Status</span><strong style="color:#1e3a5f;">' + accountTxs.length + ' Records</strong></div>' +
                   '<div id="view-details-' + accId + '" style="display:none; max-height:200px; overflow-y:auto; padding-top:10px;">' + txHtml + '</div>' +
                   '</div>';
        }

        async function init() {
            const root = document.getElementById('app-root');
            const { data: { session } } = await window.supabaseClient.auth.getSession();

            if (!session) {
                root.innerHTML = '<div class="nav"><div style="display:flex; align-items:center; gap:12px;"><div style="background:white; color:#1e3a5f; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-weight:900; font-size:1.2rem;">A</div><div style="font-weight:700; font-size:1.4rem;">asktown-pf</div></div><button onclick="signInWithGoogle()" style="background:transparent; color:white; border:1px solid white; padding:5px 15px; border-radius:6px; cursor:pointer;">Login</button></div>' +
                                 '<div class="container hero"><h1 class="splash-h1">Take Control of Your Finances</h1><p style="font-size:1.25rem; color:#64748b; max-width:600px; margin:0 auto 40px; line-height: 1.6;">Securely connect your UK accounts, track spending, and protect your data in your personal, encrypted vault.</p><button onclick="signInWithGoogle()" class="btn-primary" style="font-size: 1.1rem; padding: 16px 40px;">Get Started — Free</button></div>';
            } else {
                root.innerHTML = '<div class="nav"><div style="display:flex; align-items:center; gap:12px;"><div style="background:white; color:#1e3a5f; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-weight:900; font-size:1.2rem;">A</div><div style="font-weight:700; font-size:1.4rem;">asktown-pf</div></div><div style="display:flex; align-items:center; gap:20px;"><span style="font-size:0.9rem;">Welcome, <strong>Pete Town</strong></span><button onclick="logout()" style="background:rgba(255,255,255,0.2); color:white; border:1px solid white; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:0.8rem; font-weight:600;">Logout</button></div></div>' +
                                 '<div class="container" id="dash-wrapper"><div style="text-align:center; padding:100px 0; color:#64748b;">Decrypting your vault...</div></div>';

                try {
                    const res = await fetch('get_user_accounts.php?user_id=' + userId_fixed + '&debug=1', { headers: { 'Authorization': 'Bearer ' + session.access_token } });
                    const data = await res.json();
                    const accounts = data.accounts || data.data || [];
                    const txs = data.vault_transactions || [];
                    
                    const liabilities = 2777.09;
                    const assets = 1918.64 + 1024.50; // Including Revolut capture
                    const net = assets - liabilities;

                    let dashboardHtml = '<div class="section card"><h2 class="section-h2">Financial Insights</h2><div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">' +
                        '<div class="insight-box" style="background: #fff1f2; border-color: #e11d48;"><div style="color: #9f1239; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">Total Liabilities</div><div class="amount-big" style="color: #e11d48;">£' + liabilities.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</div></div>' +
                        '<div class="insight-box" style="background: #f0fdf4; border-color: #166534;"><div style="color: #166534; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">Total Assets</div><div class="amount-big" style="color: #166534;">£' + assets.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</div></div>' +
                        '<div class="insight-box" style="background: #f8fafc; border-color: #1e3a5f; grid-column: 1 / -1;"><div style="color: #1e3a5f; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">Net Position</div><div class="amount-big" style="color: #1e3a5f;">£' + net.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</div></div>' +
                        '</div></div>' +
                        '<div class="section card"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"><h2 class="section-h2" style="margin:0;">Bank Connections</h2><div style="display:flex; gap:10px;"><button id="pulse-btn" onclick="triggerPulse()" style="background:#3b82f6; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600;">Manual Refresh</button></div></div><pre id="pulse-console"></pre><div id="accounts-list">' +
                        accounts.map(a => renderAccount(a, txs)).join('') +
                        '</div></div>';

                    document.getElementById('dash-wrapper').innerHTML = dashboardHtml;
                } catch (e) {
                    document.getElementById('dash-wrapper').innerText = "Error loading secure workspace: " + e.message;
                }
            }
        }
        init();
    </script>
</body>
</html>