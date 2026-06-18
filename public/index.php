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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .nav { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: white; border-bottom: 1px solid #e2e8f0; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 24px; }
        .section h2 { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
        .account-card { padding: 20px; border-radius: 12px; margin-bottom: 16px; border: 1px solid #e2e8f0; background: white; transition: transform 0.2s; }
        .account-card:hover { transform: translateY(-2px); }
        .vault-view { margin-top: 10px; }
        #pulse-console { background: #0f172a; color: #4ade80; padding: 15px; border-radius: 8px; font-size: 0.75rem; max-height: 200px; overflow-y: auto; display: none; border: 1px solid #1e293b; font-family: monospace; margin-bottom: 20px; }
    </style>
</head>
<body style="background-color: #f8fafc; font-family: -apple-system, system-ui, sans-serif; margin: 0;">
    <div class="nav">
        <div style="font-weight: 800; font-size: 1.5rem; color: #020617;">asktown-pf</div>
        <div id="user-status" style="font-size: 0.875rem; color: #64748b;">Loading user...</div>
    </div>

    <div class="container">
        <div id="main-content"></div>
    </div>

    <script>
        const userId = 'f6b1e9eb-03a4-470b-a5da-8de341f3c15a';

        function switchTab(tab, accId) {
            const views = document.querySelectorAll(`#view-summary-${accId}, #view-details-${accId}`);
            views.forEach(v => v.style.display = 'none');
            const target = document.getElementById(`view-${tab}-${accId}`);
            if (target) target.style.display = 'block';
            
            const sBtn = document.getElementById(`tab-summary-${accId}`);
            const dBtn = document.getElementById(`tab-details-${accId}`);
            if (!sBtn || !dBtn) return;
            
            if (tab === 'summary') {
                sBtn.style.background = 'white'; sBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)'; sBtn.style.fontWeight = '700';
                dBtn.style.background = 'transparent'; dBtn.style.boxShadow = 'none'; dBtn.style.fontWeight = '500';
            } else {
                dBtn.style.background = 'white'; dBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)'; dBtn.style.fontWeight = '700';
                sBtn.style.background = 'transparent'; sBtn.style.boxShadow = 'none'; sBtn.style.fontWeight = '500';
            }
        }

        async function triggerPulse() {
            const btn = document.getElementById('pulse-btn');
            const status = document.getElementById('pulse-status');
            const consoleArea = document.getElementById('pulse-console');
            
            btn.disabled = true;
            status.innerText = "Syncing...";
            consoleArea.style.display = "block";
            consoleArea.innerText = "> Initializing sync...\n";

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
                status.innerText = "Complete";
                setTimeout(() => location.reload(), 1000); 
            } catch (err) {
                consoleArea.innerText += "\n[ERR] " + err.message;
                status.innerText = "Failed";
            } finally {
                btn.disabled = false;
            }
        }

        async function init() {
            const main = document.getElementById('main-content');
            try {
                const res = await fetch(`get_user_accounts.php?user_id=${userId}&debug=1`);
                if (!res.ok) throw new Error("Backend Returned " + res.status);
                const data = await res.json();
                
                const amexDebt = 2777.09;
                const totalAssets = 894.14;
                const netPos = totalAssets - amexDebt;

                let html = `
                    <div class="section card">
                        <h2 style="margin-top:0;">Financial Insights</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <div style="padding: 15px; background: #fff1f2; border-radius: 8px; border-left: 4px solid #e11d48;">
                                <div style="color: #9f1239; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">Total Liabilities</div>
                                <div style="font-size: 1.8rem; font-weight: 800; color: #e11d48;">£${amexDebt.toLocaleString('en-GB', {minimumFractionDigits:2})}</div>
                            </div>
                            <div style="padding: 15px; background: #f0fdf4; border-radius: 8px; border-left: 4px solid #166534;">
                                <div style="color: #166534; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">Total Assets</div>
                                <div style="font-size: 1.8rem; font-weight: 800; color: #166534;">£${totalAssets.toLocaleString('en-GB', {minimumFractionDigits:2})}</div>
                            </div>
                            <div style="padding: 15px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #475569; grid-column: 1 / -1;">
                                <div style="color: #475569; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">Net Position</div>
                                <div style="font-size: 2.2rem; font-weight: 800; color: ${netPos < 0 ? '#e11d48' : '#1e293b'};">£${netPos.toLocaleString('en-GB', {minimumFractionDigits:2})}</div>
                            </div>
                        </div>
                    </div>

                    <div class="section card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <h2 style="margin:0;">Bank Connections</h2>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <button id="pulse-btn" onclick="triggerPulse()" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight:600;">🔄 Manual Refresh</button>
                                <span id="pulse-status" style="color: #94a3b8; font-size: 0.8rem;">Ready</span>
                            </div>
                        </div>
                        <pre id="pulse-console"></pre>
                        <div id="accounts-list"></div>
                    </div>
                `;
                main.innerHTML = html;

                const accList = document.getElementById('accounts-list');
                const accounts = data.data || data.accounts || [];
                const txs = data.vault_transactions || [];

                console.log("Raw API Response Data:", data); // Debugging
                console.log("Extracted Accounts List:", accounts); // Debugging

                if (accounts.length === 0) {
                    accList.innerHTML = '<p style="color:#64748b; padding:20px; text-align:center;">No bank accounts connected yet.</p>';
                } else {
                    accList.innerHTML = accounts.map(acc => {
                        const days = data.debug?.days_remaining ?? 90;
                        const col = days < 7 ? '#991b1b' : '#166534';
                        const bg = days < 7 ? '#fee2e2' : '#dcfce7';
                        const accId = acc.account_id;

                        // Filter transactions to only those belonging to this account
                        const accountTxs = txs.filter(t => t.account_id === accId || t.account_id_hashed === accId);

                        return `
                            <div class="account-card" style="border-left: 4px solid ${col}; background:white; border:1px solid #e2e8f0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                                    <div>
                                        <strong style="color:#0f172a; font-size:1.1rem;">${acc.display_name || 'Account'}</strong><br>
                                        <small style="color:#64748b; text-transform:uppercase; font-weight:700; letter-spacing:0.05em;">${acc.provider?.provider_id || 'bank'}</small>
                                    </div>
                                    <span style="background:${bg}; color:${col}; padding:4px 8px; border-radius:4px; font-size:0.7rem; font-weight:bold;">ACTIVE • ${days}D</span>
                                </div>

                                <div style="border-top:1px solid #f1f5f9; padding-top:12px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <small style="color:#64748b; font-weight:bold; letter-spacing:0.05em;">SECURE VAULT</small>
                                        <div style="background:#f1f5f9; padding:2px; border-radius:6px; display:flex; gap:4px;">
                                            <button id="tab-summary-${accId}" onclick="switchTab('summary','${accId}')" style="border:none; padding:4px 10px; font-size:0.7rem; border-radius:4px; cursor:pointer; background:white; font-weight:700; box-shadow:0 1px 2px rgba(0,0,0,0.05);">Summary</button>
                                            <button id="tab-details-${accId}" onclick="switchTab('details','${accId}')" style="border:none; padding:4px 10px; font-size:0.7rem; border-radius:4px; cursor:pointer; background:transparent; font-weight:500; color:#64748b;">Transactions</button>
                                        </div>
                                    </div>
                                    <div id="view-summary-${accId}" style="padding:15px; background:#f8fafc; border-radius:8px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <span style="font-size:0.8rem; color:#64748b;">Sync Status</span>
                                            <span style="font-size:0.8rem; font-weight:700; color:#1e293b;">${accountTxs.length} Records</span>
                                        </div>
                                    </div>
                                    <div id="view-details-${accId}" style="display:none; max-height:250px; overflow-y:auto; padding-right:5px;">
                                        ${accountTxs.length === 0 ? '<div style="text-align:center; padding:10px; color:#94a3b8;">No transactions found.</div>' : accountTxs.map(t => {
                                            const amt = parseFloat(t.amount || 0);
                                            return `
                                                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:8px; border-bottom:1px solid #f8fafc; padding-bottom:4px;">
                                                    <span style="color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:10px;">${t.description || 'No Description'}</span>
                                                    <strong style="color:${amt < 0 ? '#ef4444' : '#10b981'}; white-space:nowrap;">${amt < 0 ? '-' : '+'}£${Math.abs(amt).toFixed(2)}</strong>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }

                document.getElementById('user-status').innerText = "Personal Finance Dashboard";

            } catch (e) {
                console.error(e);
                main.innerHTML = `<div class="card" style="border-left:4px solid #ef4444;">
                    <h3 style="color:#991b1b; margin-top:0;">Failed to load dashboard</h3>
                    <p style="color:#64748b; font-size:0.9rem;">${e.message}</p>
                    <button onclick="location.reload()" style="padding:8px 16px; background:#ef4444; color:white; border:none; border-radius:6px; cursor:pointer;">Retry</button>
                </div>`;
            }
        }
        init();
    </script>
</body>
</html>