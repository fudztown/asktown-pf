<?php
/**
 * TrueLayer-specific tests with mock data
 */

require_once __DIR__ . '/../config/truelayer.php';

echo "Testing TrueLayer with mock data...\n";

// Create a mock token file for testing
$testUserId = "test-user-mock";
$testDir = "/opt/finance/users/$testUserId";
if (!is_dir($testDir)) {
    mkdir($testDir, 0700, true);
}

// Generate a fake access token (we'll just test the structure)
$mockToken = [
    "access_token"  => base64_encode("fake-access-token-for-testing"),
    "refresh_token" => base64_encode("fake-refresh-token"),
    "nonce"         => base64_encode(random_bytes(24)),
    "created_at"    => date('c'),
];

file_put_contents("$testDir/tokens.enc", json_encode($mockToken));

// Test that the function handles the mock file
$result = get_user_truelayer_tokens($testUserId);
if ($result === null) {
    echo "✓ get_user_truelayer_tokens() correctly returns null for invalid encryption\n";
} else {
    echo "✗ Unexpected result from mock token\n";
}

// Clean up
unlink("$testDir/tokens.enc");
rmdir($testDir);

echo "TrueLayer mock tests completed.\n";
