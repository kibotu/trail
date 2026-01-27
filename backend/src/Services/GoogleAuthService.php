<?php

declare(strict_types=1);

namespace Trail\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class GoogleAuthService
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const CACHE_FILE = __DIR__ . '/../../cache/google_certs.json';
    private const CACHE_TTL = 3600; // 1 hour
    
    private string $clientId;
    private ?string $lastError = null;

    public function __construct(array $config)
    {
        $this->clientId = $config['google_oauth']['client_id'];
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function verifyIdToken(string $idToken): ?array
    {
        $this->lastError = null;
        
        try {
            // Get Google's public keys in JWK format
            $jwks = $this->getGoogleJWKS();
            
            // Parse JWKs and decode/verify the token
            $keys = JWK::parseKeySet($jwks);
            $payload = (array) JWT::decode($idToken, $keys);
            
            // Verify the token claims
            $claimsResult = $this->verifyTokenClaims($payload);
            if ($claimsResult !== true) {
                $this->lastError = $claimsResult;
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'] ?? '',
                'name' => $payload['name'] ?? '',
                'picture' => $payload['picture'] ?? '',
            ];
        } catch (\Exception $e) {
            $this->lastError = "Token verification exception: " . $e->getMessage();
            return null;
        }
    }

    private function verifyTokenClaims(array $payload): string|bool
    {
        // Verify issuer
        if (!isset($payload['iss']) || 
            ($payload['iss'] !== 'https://accounts.google.com' && $payload['iss'] !== 'accounts.google.com')) {
            return "Invalid issuer: " . ($payload['iss'] ?? 'missing');
        }
        
        // Verify audience (client ID)
        if (!isset($payload['aud']) || $payload['aud'] !== $this->clientId) {
            return "Invalid audience - Expected: {$this->clientId}, Got: " . ($payload['aud'] ?? 'missing');
        }
        
        // Verify expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            $expTime = isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'missing';
            $currentTime = date('Y-m-d H:i:s', time());
            return "Token expired - exp: {$expTime}, current time: {$currentTime}";
        }
        
        // Verify issued at time is not in the future
        if (isset($payload['iat']) && $payload['iat'] > time() + 300) {
            $iatTime = date('Y-m-d H:i:s', $payload['iat']);
            $currentTime = date('Y-m-d H:i:s', time());
            return "Token issued in the future - iat: {$iatTime}, current time: {$currentTime}";
        }
        
        return true;
    }

    private function getGoogleJWKS(): array
    {
        // Check cache first
        if (file_exists(self::CACHE_FILE)) {
            $cacheData = json_decode(file_get_contents(self::CACHE_FILE), true);
            if ($cacheData && isset($cacheData['timestamp']) && 
                (time() - $cacheData['timestamp']) < self::CACHE_TTL) {
                return $cacheData['jwks'];
            }
        }
        
        // Fetch fresh certificates from Google
        $response = file_get_contents(self::GOOGLE_CERTS_URL);
        if (!$response) {
            throw new \Exception('Failed to fetch Google certificates');
        }
        
        $jwks = json_decode($response, true);
        if (!isset($jwks['keys'])) {
            throw new \Exception('Invalid Google certificates response');
        }
        
        // Cache the JWKs
        $cacheDir = dirname(self::CACHE_FILE);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents(self::CACHE_FILE, json_encode([
            'timestamp' => time(),
            'jwks' => $jwks
        ]));
        
        return $jwks;
    }
}
