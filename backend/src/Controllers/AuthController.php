<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\User;
use Trail\Services\GoogleAuthService;
use Trail\Services\JwtService;
use Trail\Services\GravatarService;
use Trail\Config\Config;

class AuthController
{
    public static function googleAuth(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $googleToken = $data['google_token'] ?? '';

        if (empty($googleToken)) {
            $response->getBody()->write(json_encode(['error' => 'Google token required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../config.yml');
        $googleAuth = new GoogleAuthService($config);
        $userData = $googleAuth->verifyIdToken($googleToken);

        if (!$userData) {
            $response->getBody()->write(json_encode(['error' => 'Invalid Google token']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        // Generate Gravatar hash
        $gravatarHash = GravatarService::generateHash($userData['email']);
        
        // Find or create user
        $user = $userModel->findByGoogleId($userData['google_id']);
        
        if ($user) {
            // Update existing user
            $userModel->update($user['id'], $userData['email'], $userData['name'], $gravatarHash);
            $userId = $user['id'];
            $isAdmin = (bool) $user['is_admin'];
        } else {
            // Create new user
            $userId = $userModel->create(
                $userData['google_id'],
                $userData['email'],
                $userData['name'],
                $gravatarHash
            );
            $isAdmin = false;
        }

        // Generate JWT
        $jwtService = new JwtService($config);
        $jwt = $jwtService->generate($userId, $userData['email'], $isAdmin);

        // Return response
        $responseData = [
            'jwt' => $jwt,
            'user' => [
                'id' => $userId,
                'email' => $userData['email'],
                'name' => $userData['name'],
                'gravatar_url' => GravatarService::generateUrl($gravatarHash),
                'is_admin' => $isAdmin,
            ],
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
