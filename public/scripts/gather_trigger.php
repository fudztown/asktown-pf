<?php
declare(strict_types=1);

// asktown-finance/public/scripts/gather_trigger.php
// Wrapper to run the CLI gather script from the browser for debugging.

header('Content-Type: text/plain');

$userId = $_GET['user_id'] ?? '';
if (!preg_match('/^[a-f0-9-]{36}$/', $userId)) {
    die("ERROR: Invalid user_id format.");
}

// Run the script and capture output
// We point to the actual gather script in the parent scripts folder
$cmd = "php " . escapeshellarg(__DIR__ . '/../../scripts/gather.php') . " 2>&1";

$handle = popen($cmd, 'r');
if ($handle) {
    while (!feof($handle)) {
        echo fgets($handle);
        ob_flush();
        flush();
    }
    pclose($handle);
} else {
    echo "ERROR: Could not execute gather script.";
}
