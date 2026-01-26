<?php

declare(strict_types=1);

namespace Trail\Services;

use Google\Client as GoogleClient;

class GoogleAuthService
{
    private GoogleClient $client;

    public function __construct(array $config)
    {
        $this->client = new GoogleClient();
        $this->client->setClientId($config['google_oauth']['client_id']);
        $this->client->setClientSecret($config['google_oauth']['client_secret']);
    }

    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);
            
            if (!$payload) {
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'] ?? '',
                'picture' => $payload['picture'] ?? '',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
