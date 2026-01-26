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

    /**
     * Dev Auth - Generate JWT for testing (development mode only)
     */
    public static function devAuth(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../config.yml');
        
        // Only allow in development mode
        if (($config['app']['environment'] ?? 'production') !== 'development') {
            $response->getBody()->write(json_encode(['error' => 'Dev auth only available in development mode']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode((string) $request->getBody(), true);
        $email = $data['email'] ?? 'dev@example.com';

        // Get dev users from config
        $devUsers = $config['development']['dev_users'] ?? [];
        $devUser = null;
        
        foreach ($devUsers as $user) {
            if ($user['email'] === $email) {
                $devUser = $user;
                break;
            }
        }

        if ($devUser === null) {
            $response->getBody()->write(json_encode(['error' => 'Dev user not found in configuration']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        // Generate a fake Google ID for dev users
        $googleId = 'dev_' . md5($email);
        $name = $devUser['name'] ?? 'Dev User';
        $isAdmin = $devUser['is_admin'] ?? false;
        $gravatarHash = GravatarService::generateHash($email);

        // Find or create user
        $user = $userModel->findByGoogleId($googleId);
        
        if ($user) {
            $userId = $user['id'];
            $isAdmin = (bool) $user['is_admin'];
        } else {
            // Create new user
            $userId = $userModel->create($googleId, $email, $name, $gravatarHash);
        }

        // Generate JWT
        $jwtService = new JwtService($config);
        $jwt = $jwtService->generate($userId, $email, $isAdmin);

        // Return response
        $responseData = [
            'jwt' => $jwt,
            'user' => [
                'id' => $userId,
                'email' => $email,
                'name' => $name,
                'gravatar_url' => GravatarService::generateUrl($gravatarHash),
                'is_admin' => $isAdmin,
            ],
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
