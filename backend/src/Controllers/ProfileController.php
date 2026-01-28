<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\User;
use Trail\Config\Config;

class ProfileController
{
    /**
     * Get current user's profile
     */
    public static function getProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        $user = $userModel->findByIdWithImages($userId);
        
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Get or generate nickname if not set
        $nickname = $user['nickname'];
        if (empty($nickname)) {
            $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
            $nickname = $userModel->getOrGenerateNickname($userId, $user['google_id'], $salt);
        }

        $profileData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'nickname' => $nickname,
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash'],
            'profile_image_id' => $user['profile_image_id'],
            'profile_image_url' => $user['profile_image_url'] ?? null,
            'header_image_id' => $user['header_image_id'],
            'header_image_url' => $user['header_image_url'] ?? null,
            'is_admin' => (bool) $user['is_admin'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];

        $response->getBody()->write(json_encode($profileData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update current user's profile (nickname and images)
     */
    public static function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode((string) $request->getBody(), true);
        $nickname = $data['nickname'] ?? null;
        $profileImageId = isset($data['profile_image_id']) ? (int) $data['profile_image_id'] : null;
        $headerImageId = isset($data['header_image_id']) ? (int) $data['header_image_id'] : null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate nickname format
        if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $nickname)) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid nickname format. Use 3-50 characters (letters, numbers, underscore, hyphen only)'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        // Check if nickname is available
        if (!$userModel->isNicknameAvailable($nickname, $userId)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is already taken']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        // Update nickname
        $success = $userModel->updateNickname($userId, $nickname);

        if (!$success) {
            $response->getBody()->write(json_encode(['error' => 'Failed to update nickname']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // Update profile image if provided
        if ($profileImageId !== null) {
            $userModel->updateProfileImage($userId, $profileImageId);
        }

        // Update header image if provided
        if ($headerImageId !== null) {
            $userModel->updateHeaderImage($userId, $headerImageId);
        }

        // Return updated profile
        $user = $userModel->findByIdWithImages($userId);
        $profileData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'nickname' => $user['nickname'],
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash'],
            'profile_image_id' => $user['profile_image_id'],
            'profile_image_url' => $user['profile_image_url'] ?? null,
            'header_image_id' => $user['header_image_id'],
            'header_image_url' => $user['header_image_url'] ?? null,
            'is_admin' => (bool) $user['is_admin'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];

        $response->getBody()->write(json_encode($profileData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get public profile by nickname
     */
    public static function getPublicProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $nickname = $args['nickname'] ?? null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        $user = $userModel->findByNickname($nickname);
        
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Return only public profile data
        $profileData = [
            'id' => $user['id'],
            'nickname' => $user['nickname'],
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash'],
            'created_at' => $user['created_at']
        ];

        $response->getBody()->write(json_encode($profileData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
