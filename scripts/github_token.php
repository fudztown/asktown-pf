<?php
function get_github_token(): ?string {
    $envFile = "/opt/finance/.env";
    $tokenFile = "/opt/finance/github_token.enc";

    if (!file_exists($envFile) || !file_exists($tokenFile)) {
        return null;
    }

    $encryptionKey = null;
    foreach (file($envFile) as $line) {
        if (strpos($line, "TOKEN_ENCRYPTION_KEY=") === 0) {
            $parts = explode("=", $line, 2);
            if (count($parts) == 2) {
                $encryptionKey = trim($parts[1]);
            }
            break;
        }
    }

    if (!$encryptionKey) return null;

    $data = json_decode(file_get_contents($tokenFile), true);
    if (empty($data["token"]) || empty($data["nonce"])) {
        return null;
    }

    $key = sodium_crypto_generichash($encryptionKey, "", 32);
    $nonce = base64_decode($data["nonce"]);
    $token = sodium_crypto_secretbox_open(base64_decode($data["token"]), $nonce, $key);

    return $token ?: null;
}
