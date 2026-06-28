<?php
declare(strict_types=1);

namespace Asktown\Tests;

/**
 * Modern Test Framework for asktown-pf
 * Minimal, zero-dependency, and integrated into the checkin gate.
 */
class Framework
{
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $errors = [];

    public static function it(string $description, callable $test): void
    {
        try {
            $test();
            echo "  ✓ $description\n";
            self::$passed++;
        } catch (\Throwable $e) {
            echo "  ✗ $description\n";
            self::$errors[] = "[$description] " . $e->getMessage();
            self::$failed++;
        }
    }

    public static function assertEquals($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException("$message | Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true));
        }
    }

    public static function runSummary(): int
    {
        echo "\n" . str_repeat("=", 30) . "\n";
        echo "TEST SUMMARY\n";
        echo "Passed: " . self::$passed . "\n";
        echo "Failed: " . self::$failed . "\n";
        
        if (self::$failed > 0) {
            echo "\nErrors:\n- " . implode("\n- ", self::$errors) . "\n";
            return 1;
        }
        
        echo "✨ All systems go!\n";
        return 0;
    }
}
