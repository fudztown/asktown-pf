<?php
// Admin Agent Monitor - Protected page
// Shows running sub-agents, current goal, and development stats

require_once __DIR__ . '/../../config/supabase.php';

// Simple admin check (in production this would be more robust)
$isAdmin = true; // TODO: Replace with proper role check

if (!$isAdmin) {
    http_response_code(403);
    die('Access denied');
}

$activityLog = file_exists('/root/asktown-finance/logs/activity.log') 
    ? array_slice(file('/root/asktown-finance/logs/activity.log'), -20) 
    : [];

$currentGoal = trim(file_get_contents('/root/asktown-finance/.current_goal') ?? 'No active goal');

$iterationCount = count(glob('/root/asktown-finance/logs/activity.log')) > 0 
    ? substr_count(file_get_contents('/root/asktown-finance/logs/activity.log'), 'Autonomous dev loop') 
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Monitor • asktown-pf Admin</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: system-ui; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .card { background: #1e293b; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .stat { font-size: 2rem; font-weight: 700; color: #60a5fa; }
        .log-line { font-family: monospace; font-size: 0.875rem; padding: 4px 0; border-bottom: 1px solid #334155; }
        h1 { color: #f1f5f9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Agent Monitor</h1>
        <p style="color:#94a3b8;">Real-time view of autonomous development agents</p>

        <div class="card">
            <h2>Current Goal</h2>
            <p style="font-size:1.1rem;"><?= htmlspecialchars($currentGoal) ?></p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:24px;">
            <div class="card">
                <div style="color:#94a3b8;">Iterations</div>
                <div class="stat"><?= $iterationCount ?></div>
            </div>
            <div class="card">
                <div style="color:#94a3b8;">Tokens Used</div>
                <div class="stat">—</div>
                <small style="color:#64748b;">(Coming soon)</small>
            </div>
            <div class="card">
                <div style="color:#94a3b8;">Active Sub-Agents</div>
                <div class="stat">1</div>
                <small style="color:#64748b;">Development Loop</small>
            </div>
        </div>

        <div class="card">
            <h2>Recent Activity</h2>
            <?php if (empty($activityLog)): ?>
                <p style="color:#64748b;">No activity logged yet.</p>
            <?php else: ?>
                <?php foreach (array_reverse($activityLog) as $line): ?>
                    <div class="log-line"><?= htmlspecialchars(trim($line)) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Sub-Agent Status</h2>
            <p><strong>Development Loop</strong> — Running every 5 minutes</p>
            <p style="color:#64748b;">Status: Active • Last run: Just now</p>
        </div>
    </div>
</body>
</html>