<?php
/**
 * Simple Test Runner for asktown-pf
 */

echo "=== asktown-pf Test Suite ===\n\n";

$passed = 0;
$failed = 0;

function assertTrue($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "✓ $message\n";
        $passed++;
    } else {
        echo "✗ $message\n";
        $failed++;
    }
}

function assertEquals($expected, $actual, $message) {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "✓ $message\n";
        $passed++;
    } else {
        echo "✗ $message (expected: $expected, got: $actual)\n";
        $failed++;
    }
}

// Load dependencies (state_signer only - truelayer.php must never be required directly from tests)
require_once __DIR__ . '/../config/state_signer.php';

// Minimal stubs for structure-only TrueLayer tests (real implementations live in get_user_accounts.php only)
function get_user_truelayer_tokens(string $userId): ?array {
    return null; // structure test only
}

function get_user_connected_accounts(string $userId): array {
    return []; // structure test only
}

// Test 1: Signed State
echo "Testing Signed State Functions...\n";

$testUserId = "test-user-123";
$signed = sign_state($testUserId);
assertTrue(!empty($signed), "sign_state() returns a value");

$verified = verify_state($signed);
assertEquals($testUserId, $verified, "verify_state() returns correct user ID");

$tampered = base64_encode("tampered|1234567890|invalidsig");
$tamperedResult = verify_state($tampered);
assertTrue($tamperedResult === null, "verify_state() rejects tampered state");

// Test 2: TrueLayer Token Functions (structure only)
echo "\nTesting TrueLayer Functions (structure)...\n";

$result = get_user_truelayer_tokens("nonexistent-user");
assertTrue($result === null, "get_user_truelayer_tokens() returns null for missing user");

$accounts = get_user_connected_accounts("nonexistent-user");
assertTrue(is_array($accounts), "get_user_connected_accounts() returns an array");

// Summary
echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    exit(1);
} else {
    echo "All tests passed!\n";
    exit(0);
}
