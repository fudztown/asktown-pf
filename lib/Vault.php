<?php
declare(strict_types=1);

namespace Asktown\Security;

use LogicException;
use RuntimeException;

/**
 * Vault - Standardized Authenticated Encryption for asktown-pf
 * 
 * Uses libsodium (XChaCha20-Poly1305) to provide authenticated 
 * encryption at rest for sensitive financial data.
 */
class Vault
{
    private string $key;

    /**
     * @param string $secretKey Raw binary key (32 bytes)
     */
    public function __construct(string $secretKey)
    {
        if (strlen($secretKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new LogicException('Secret key must be exactly ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes.');
        }
        $this->key = $secretKey;
    }

    /**
     * Encrypt a string and return a base64 encoded envelope.
     * Envelope format: base64(nonce . ciphertext)
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a base64 encoded envelope.
     */
    public function decrypt(string $envelope): string
    {
        $decoded = base64_decode($envelope, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 encoding in encrypted envelope.');
        }

        if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Truncated encrypted envelope.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed. Data may be tampered with or key is incorrect.');
        }

        return $plaintext;
    }

    /**
     * Helper to derive the required 32-byte key from a human-friendly string.
     */
    public static function deriveKey(string $userSecret): string
    {
        return sodium_crypto_generichash($userSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
