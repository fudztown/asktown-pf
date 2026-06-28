<?php
declare(strict_types=1);

namespace Asktown\Tests;

use Asktown\Security\Vault;
use Asktown\Infrastructure\Auth\SupabaseAuthService;
use Exception;

// Minimal test runner for unit tests
class MiniTest
{
    private static int $passed = 0;
    private static int $failed = 0;

    public static function assertEquals($expected, $actual, $msg)
    {
        if ($expected === $actual) {
            echo "✓ $msg\n";
            self::$passed++;
        } else {
            echo "✗ $msg (expected: $expected, got: $actual)\n";
            self::$failed++;
        }
    }

    public static function summary(): int
    {
        echo "\nTests Passed: " . self::$passed . "\n";
        echo "Tests Failed: " . self::$failed . "\n";
        return self::$failed;
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

echo "Running Unit Tests...\n";

// Test Vault
try {
    $secret = "this-is-a-32-byte-long-test-key!!";
    $key = Vault::deriveKey($secret);
    $vault = new Vault($key);
    $original = "secure message";
    $encrypted = $vault->encrypt($original);
    MiniTest::assertEquals($original, $vault->decrypt($encrypted), "Vault: Encrypt/Decrypt works");
} catch (Exception $e) {
    echo "Fail: " . $e->getMessage() . "\n";
    exit(1);
}

exit(MiniTest::summary() > 0 ? 1 : 0);
