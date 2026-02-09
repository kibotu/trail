<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\User;
use Trail\Config\Config;

class ApiTokenController
{
    /**
     * Get current user's API token
     * GET /api/token
     */
    public static function getToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        $user = $userModel->findById($userId);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'api_token' => $user['api_token'] ?? null,
            'created_at' => $user['api_token_created_at'] ?? null
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Regenerate user's API token
     * POST /api/token/regenerate
     */
    public static function regenerateToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        // Generate cryptographically secure token
        $newToken = $userModel->generateApiToken();
        
        // Update database
        $success = $userModel->updateApiToken($userId, $newToken);

        if (!$success) {
            $response->getBody()->write(json_encode(['error' => 'Failed to regenerate token']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'api_token' => $newToken,
            'created_at' => date('Y-m-d H:i:s')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
