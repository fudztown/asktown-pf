<?php
/**
 * Simple Migration Runner for asktown-pf
 * 
 * Usage:
 *   php scripts/migrate.php [status|up]
 */

require_once __DIR__ . '/../config/supabase.php';

// Check for target environment (default to staging/test)
$isProd = in_array('--prod', $argv);

if ($isProd) {
    echo "⚠️ TARGETING PRODUCTION DATABASE ⚠️\n";
    echo "Are you sure? (type 'YES' to continue): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim($line) != 'YES') {
        echo "Aborting.\n";
        exit(1);
    }
}

// Database Connection Settings
$host = $isProd ? (getenv('PROD_DB_HOST') ?: 'db.asktown.co.uk') : (getenv('DB_HOST') ?: 'localhost');
$dbname = $isProd ? (getenv('PROD_DB_NAME') ?: 'postgres') : (getenv('DB_NAME') ?: 'asktown_test');
$user = $isProd ? (getenv('PROD_DB_USER') ?: 'postgres') : (getenv('DB_USER') ?: 'asktown_user');
$pass = $isProd ? (getenv('PROD_DB_PASS') ?: '') : (getenv('DB_PASS') ?: 'asktown_password');

try {
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Connected to " . ($isProd ? "PROD" : "STAGING") . " ($dbname)\n\n";

    // 1. Ensure migrations table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
        version VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMPTZ DEFAULT NOW()
    )");

    // 2. Scan migrations directory
    $dir = __DIR__ . '/../migrations';
    if (!is_dir($dir)) mkdir($dir);
    $files = glob("$dir/*.sql");
    sort($files);

    $applied = $pdo->query("SELECT version FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('status', $argv)) {
        echo "Migration Status:\n";
        foreach ($files as $file) {
            $version = basename($file);
            $status = in_array($version, $applied) ? "✓ Applied" : "pending";
            echo "  [$status] $version\n";
        }
        exit(0);
    }

    if (in_array('up', $argv)) {
        $count = 0;
        foreach ($files as $file) {
            $version = basename($file);
            if (!in_array($version, $applied)) {
                echo "Applying $version...\n";
                $sql = file_get_contents($file);
                
                $pdo->beginTransaction();
                try {
                    $pdo->exec($sql);
                    $stmt = $pdo->prepare("INSERT INTO _migrations (version) VALUES (?)");
                    $stmt->execute([$version]);
                    $pdo->commit();
                    echo "  ✓ Success\n";
                    $count++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "  ❌ Failed: " . $e->getMessage() . "\n";
                    exit(1);
                }
            }
        }
        echo "\nFinished. $count migration(s) applied.\n";
    } else {
        echo "Usage: php scripts/migrate.php [status|up] [--prod]\n";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
