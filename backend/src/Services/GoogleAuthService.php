<?php

declare(strict_types=1);

namespace Trail\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class GoogleAuthService
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const CACHE_FILE = __DIR__ . '/../../cache/google_certs.json';
    private const CACHE_TTL = 3600; // 1 hour
    
    private string $clientId;

    public function __construct(array $config)
    {
        $this->clientId = $config['google_oauth']['client_id'];
    }

    public function verifyIdToken(string $idToken): ?array
    {
        try {
            // Decode token header to get the key ID
            $tks = explode('.', $idToken);
            if (count($tks) !== 3) {
                return null;
            }
            
            $headb64 = $tks[0];
            $header = json_decode(JWT::urlsafeB64Decode($headb64), true);
            
            if (!isset($header['kid'])) {
                return null;
            }
            
            // Get Google's public keys
            $certs = $this->getGoogleCerts();
            if (!isset($certs[$header['kid']])) {
                return null;
            }
            
            // Verify the JWT signature
            $publicKey = $certs[$header['kid']];
            $payload = (array) JWT::decode($idToken, new Key($publicKey, 'RS256'));
            
            // Verify the token claims
            if (!$this->verifyTokenClaims($payload)) {
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'] ?? '',
                'name' => $payload['name'] ?? '',
                'picture' => $payload['picture'] ?? '',
            ];
        } catch (\Exception $e) {
            error_log("Google token verification failed: " . $e->getMessage());
            return null;
        }
    }

    private function verifyTokenClaims(array $payload): bool
    {
        // Verify issuer
        if (!isset($payload['iss']) || 
            ($payload['iss'] !== 'https://accounts.google.com' && $payload['iss'] !== 'accounts.google.com')) {
            return false;
        }
        
        // Verify audience (client ID)
        if (!isset($payload['aud']) || $payload['aud'] !== $this->clientId) {
            return false;
        }
        
        // Verify expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        // Verify issued at time is not in the future
        if (isset($payload['iat']) && $payload['iat'] > time() + 300) {
            return false;
        }
        
        return true;
    }

    private function getGoogleCerts(): array
    {
        // Check cache first
        if (file_exists(self::CACHE_FILE)) {
            $cacheData = json_decode(file_get_contents(self::CACHE_FILE), true);
            if ($cacheData && isset($cacheData['timestamp']) && 
                (time() - $cacheData['timestamp']) < self::CACHE_TTL) {
                return $cacheData['keys'];
            }
        }
        
        // Fetch fresh certificates from Google
        $response = file_get_contents(self::GOOGLE_CERTS_URL);
        if (!$response) {
            throw new \Exception('Failed to fetch Google certificates');
        }
        
        $data = json_decode($response, true);
        if (!isset($data['keys'])) {
            throw new \Exception('Invalid Google certificates response');
        }
        
        // Convert JWK to PEM format
        $certs = [];
        foreach ($data['keys'] as $key) {
            if (isset($key['kid']) && isset($key['n']) && isset($key['e'])) {
                $certs[$key['kid']] = $this->jwkToPem($key['n'], $key['e']);
            }
        }
        
        // Cache the certificates
        $cacheDir = dirname(self::CACHE_FILE);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents(self::CACHE_FILE, json_encode([
            'timestamp' => time(),
            'keys' => $certs
        ]));
        
        return $certs;
    }

    private function jwkToPem(string $n, string $e): string
    {
        // Decode base64url encoded values
        $n = $this->base64UrlDecode($n);
        $e = $this->base64UrlDecode($e);
        
        // Build the RSA public key in ASN.1 DER format
        $modulus = $this->encodeLength(strlen($n)) . $n;
        $exponent = $this->encodeLength(strlen($e)) . $e;
        
        $rsaPublicKey = pack('Ca*a*a*', 0x30, $this->encodeLength(strlen($modulus) + strlen($exponent)), $modulus, $exponent);
        
        // Add RSA algorithm identifier
        $rsaOID = pack('H*', '300d06092a864886f70d0101010500');
        $publicKey = pack('Ca*a*a*', 0x30, $this->encodeLength(strlen($rsaOID) + strlen($rsaPublicKey) + 3), $rsaOID, pack('C', 0x03), $this->encodeLength(strlen($rsaPublicKey) + 1) . pack('C', 0x00) . $rsaPublicKey);
        
        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($publicKey), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";
        
        return $pem;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function encodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        
        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }
}
