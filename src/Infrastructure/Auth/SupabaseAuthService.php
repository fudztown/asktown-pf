<?php
declare(strict_types=1);

namespace Asktown\Infrastructure\Auth;

use RuntimeException;

class SupabaseAuthService
{
    private string $url;
    private string $anonKey;

    public function __construct(string $url, string $anonKey)
    {
        $this->url = rtrim($url, '/');
        $this->anonKey = $anonKey;
    }

    /**
     * Verifies a JWT with Supabase and returns the user object.
     */
    public function verifyToken(string $token): array
    {
        $ch = curl_init("{$this->url}/auth/v1/user");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: {$this->anonKey}",
            "Authorization: Bearer " . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("Authentication failed with status $httpCode");
        }

        $user = json_decode($response, true);
        if (!$user || !isset($user['id'])) {
            throw new RuntimeException("Invalid user data received from Supabase");
        }

        return $user;
    }

    /**
     * Signs up a new user via Supabase REST API.
     */
    public function signUp(string $email, string $password, array $data = []): array
    {
        $ch = curl_init("{$this->url}/auth/v1/signup");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: {$this->anonKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'email' => $email,
            'password' => $password,
            'data' => $data
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
             throw new RuntimeException($result['msg'] ?? $result['message'] ?? 'Registration failed');
        }

        return $result;
    }
}
