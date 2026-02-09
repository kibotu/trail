<?php

declare(strict_types=1);

namespace Trail\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private int $expiryHours;

    public function __construct(array $config)
    {
        $this->secret = $config['jwt']['secret'];
        $this->expiryHours = $config['jwt']['expiry_hours'];
    }

    public function generate(int $userId, string $email, bool $isAdmin = false): string
    {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'is_admin' => $isAdmin,
            'iat' => time(),
            'exp' => time() + ($this->expiryHours * 3600),
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if JWT should be refreshed based on age.
     * Returns true if the token is older than 18 hours.
     * 
     * @param array $payload Decoded JWT payload with 'iat' claim
     * @return bool True if token should be refreshed
     */
    public function shouldRefresh(array $payload): bool
    {
        if (!isset($payload['iat'])) {
            return false;
        }

        $issuedAt = $payload['iat'];
        $now = time();
        $age = $now - $issuedAt;

        // Refresh if token is older than 18 hours (64800 seconds)
        return $age > (18 * 3600);
    }
}
