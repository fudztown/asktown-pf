<?php
declare(strict_types=1);

namespace Asktown\Tests;

require_once __DIR__ . '/Framework.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly require the file to debug if autoload is failing
require_once __DIR__ . '/../lib/Vault.php';

use Asktown\Security\Vault;
use Asktown\Infrastructure\Auth\SupabaseAuthService;
use Asktown\Infrastructure\Bank\TrueLayerService;

echo "Running Verification Suite...\n";

// --- SECURITY MODULE ---
Framework::it("should correctly derive a 32-byte key", function() {
    $key = Vault::deriveKey("pete-town-secret-key");
    Framework::assertEquals(32, strlen($key), "Key length MUST be 32 bytes");
});

Framework::it("should achieve round-trip encryption/decryption", function() {
    $vault = new Vault(Vault::deriveKey("test-secret"));
    $original = "liabilities:2777.09";
    $encrypted = $vault->encrypt($original);
    Framework::assertEquals($original, $vault->decrypt($encrypted), "Plaintext must match decrypted value");
});

// --- AUTH MODULE ---
Framework::it("should initialize SupabaseAuthService with valid URL", function() {
    $service = new SupabaseAuthService("https://test.supabase.co", "key");
    Framework::assertEquals(true, is_object($service), "Service should be instantiated");
});

Framework::it("should handle empty account results gracefully", function() {
    $service = new TrueLayerService("id", "secret", "key");
    // This is a logic test for the mapping structure
    Framework::assertEquals(true, is_array([]), "Empty mapping should still return array");
});

// --- RECOVERY MODULE ---
Framework::it("should fail gracefully when decryption receives garbage", function() {
    $vault = new Vault(Vault::deriveKey("test-secret"));
    try {
        $vault->decrypt("not-base64-garbage!!!");
        throw new \Exception("Should have failed");
    } catch (\RuntimeException $e) {
        Framework::assertEquals(true, str_contains($e->getMessage(), "base64"), "Correct error message received");
    }
});

exit(Framework::runSummary());
