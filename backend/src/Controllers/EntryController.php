<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Config\Config;
use Trail\Services\TextSanitizer;
use Trail\Services\UrlEmbedService;
use Trail\Services\IframelyUsageTracker;

class EntryController
{
    public static function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        $text = $data['text'] ?? '';
        $imageIds = $data['image_ids'] ?? null;

        // Validation: Either text or images must be provided
        if (empty($text) && (empty($imageIds) || !is_array($imageIds) || count($imageIds) === 0)) {
            $response->getBody()->write(json_encode(['error' => 'Either text or images are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $sanitizedText = '';
        
        // Only validate and sanitize text if provided
        if (!empty($text)) {
            // Validation: Check UTF-8 encoding (for emoji support)
            if (!TextSanitizer::isValidUtf8($text)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid text encoding']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validation: Check length before sanitization
            if (mb_strlen($text) > 280) {
                $response->getBody()->write(json_encode(['error' => 'Text must be 280 characters or less']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Security: Check for dangerous patterns before sanitization
            if (!TextSanitizer::isSafe($text)) {
                $response->getBody()->write(json_encode(['error' => 'Text contains potentially dangerous content']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Security: Sanitize text to remove scripts while preserving URLs and emojis
            $sanitizedText = TextSanitizer::sanitize($text);

            // Double-check length after sanitization
            if (mb_strlen($sanitizedText) > 280) {
                $response->getBody()->write(json_encode(['error' => 'Text must be 280 characters or less']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Extract and fetch URL preview if text contains a URL
        $preview = null;
        try {
            // Create usage tracker for iframe.ly API
            $adminEmail = $config['production']['admin_email'] ?? 'cloudgazer3d@gmail.com';
            $usageTracker = new IframelyUsageTracker($db, $adminEmail);
            
            $embedService = new UrlEmbedService($config, $usageTracker);
            if ($embedService->hasUrl($sanitizedText)) {
                $preview = $embedService->extractAndFetchPreview($sanitizedText);
            }
        } catch (\Throwable $e) {
            // Log error but continue - preview is optional
            error_log("EntryController::create: Preview fetch failed: " . $e->getMessage());
            $preview = null;
        }

        $entryId = $entryModel->create($userId, $sanitizedText, $preview, $imageIds);
        $entry = $entryModel->findById($entryId);

        $response->getBody()->write(json_encode([
            'id' => $entryId,
            'created_at' => $entry['created_at'],
        ]));
        
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public static function listPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        
        $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getAllWithImages($limit, $before);
        $hasMore = count($entries) === $limit;

        // Add avatar URLs and ensure nicknames
        $userModel = new \Trail\Models\User($db);
        $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
        
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
            
            // Generate nickname if not set
            if (empty($entry['user_nickname']) && !empty($entry['google_id'])) {
                $entry['user_nickname'] = $userModel->getOrGenerateNickname(
                    (int) $entry['user_id'],
                    $entry['google_id'],
                    $salt
                );
            }
        }

        // Get the cursor for the next page (created_at of the last entry)
        $nextCursor = null;
        if ($hasMore && !empty($entries)) {
            $lastEntry = end($entries);
            $nextCursor = $lastEntry['created_at'];
        }

        $response->getBody()->write(json_encode([
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'limit' => $limit,
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        
        $limit = min(50, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getByUserWithImages($userId, $limit, $before);
        $hasMore = count($entries) === $limit;

        // Add avatar URLs with Google photo fallback to Gravatar
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
        }

        // Get the cursor for the next page (created_at of the last entry)
        $nextCursor = null;
        if ($hasMore && !empty($entries)) {
            $lastEntry = end($entries);
            $nextCursor = $lastEntry['created_at'];
        }

        $response->getBody()->write(json_encode([
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'limit' => $limit,
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $entryId = (int) $args['id'];
        $data = json_decode((string) $request->getBody(), true);

        $text = $data['text'] ?? '';
        $imageIds = $data['image_ids'] ?? null;

        // Validation: Check if text is provided
        if (empty($text)) {
            $response->getBody()->write(json_encode(['error' => 'Text is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Check UTF-8 encoding (for emoji support)
        if (!TextSanitizer::isValidUtf8($text)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid text encoding']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Check length before sanitization
        if (mb_strlen($text) > 280) {
            $response->getBody()->write(json_encode(['error' => 'Text must be 280 characters or less']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Security: Check for dangerous patterns before sanitization
        if (!TextSanitizer::isSafe($text)) {
            $response->getBody()->write(json_encode(['error' => 'Text contains potentially dangerous content']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Security: Sanitize text to remove scripts while preserving URLs and emojis
        $sanitizedText = TextSanitizer::sanitize($text);

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Authorization: Check if user can modify this entry
        if (!$entryModel->canModify($entryId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to modify this entry']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Extract and fetch URL preview if text contains a URL
        $preview = null;
        try {
            // Create usage tracker for iframe.ly API
            $adminEmail = $config['production']['admin_email'] ?? 'cloudgazer3d@gmail.com';
            $usageTracker = new IframelyUsageTracker($db, $adminEmail);
            
            $embedService = new UrlEmbedService($config, $usageTracker);
            if ($embedService->hasUrl($sanitizedText)) {
                $preview = $embedService->extractAndFetchPreview($sanitizedText);
            }
        } catch (\Throwable $e) {
            // Log error but continue - preview is optional
            error_log("EntryController::update: Preview fetch failed: " . $e->getMessage());
            $preview = null;
        }

        $success = $entryModel->update($entryId, $sanitizedText, $preview, $imageIds);

        if ($success) {
            $entry = $entryModel->findById($entryId);
            $response->getBody()->write(json_encode([
                'success' => true,
                'updated_at' => $entry['updated_at']
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to update entry']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    public static function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $entryId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Authorization: Check if user can modify this entry
        if (!$entryModel->canModify($entryId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to delete this entry']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $success = $entryModel->delete($entryId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a single entry by ID
     */
    public static function getById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $entryId = (int) $args['id'];

        if ($entryId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entry = $entryModel->findByIdWithImages($entryId);

        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Add avatar URL
        $entry['avatar_url'] = self::getAvatarUrl($entry);

        // Generate nickname if not set
        if (empty($entry['user_nickname']) && !empty($entry['google_id'])) {
            $userModel = new \Trail\Models\User($db);
            $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
            $entry['user_nickname'] = $userModel->getOrGenerateNickname(
                (int) $entry['user_id'],
                $entry['google_id'],
                $salt
            );
        }

        $response->getBody()->write(json_encode($entry));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get entries by user nickname
     */
    public static function listByNickname(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $nickname = $args['nickname'] ?? null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Find user by nickname
        $userModel = new \Trail\Models\User($db);
        $user = $userModel->findByNickname($nickname);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Get entries for this user
        $queryParams = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;

        $entryModel = new Entry($db);
        $entries = $entryModel->getByUserWithImages($user['id'], $limit, $before);
        $hasMore = count($entries) === $limit;

        // Add avatar URLs
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
        }

        // Get the cursor for the next page
        $nextCursor = null;
        if ($hasMore && !empty($entries)) {
            $lastEntry = end($entries);
            $nextCursor = $lastEntry['created_at'];
        }

        $response->getBody()->write(json_encode([
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'limit' => $limit,
            'user' => [
                'id' => $user['id'],
                'nickname' => $user['nickname'],
                'photo_url' => $user['photo_url'],
                'gravatar_hash' => $user['gravatar_hash']
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user avatar URL with Google photo fallback to Gravatar.
     * 
     * @param array $user User data with photo_url, gravatar_hash, and email
     * @param int $size Avatar size in pixels
     * @return string Avatar URL (already HTML-escaped for JSON output)
     */
    private static function getAvatarUrl(array $user, int $size = 96): string
    {
        // Use Google photo if available
        if (!empty($user['photo_url'])) {
            return $user['photo_url'];
        }

        // Fallback to Gravatar using hash if available
        if (!empty($user['gravatar_hash'])) {
            return "https://www.gravatar.com/avatar/{$user['gravatar_hash']}?s={$size}&d=mp";
        }

        // Fallback to Gravatar using email
        if (!empty($user['email']) || !empty($user['user_email'])) {
            $email = $user['email'] ?? $user['user_email'];
            $gravatarHash = md5(strtolower(trim($email)));
            return "https://www.gravatar.com/avatar/{$gravatarHash}?s={$size}&d=mp";
        }

        // Ultimate fallback
        return "https://www.gravatar.com/avatar/00000000000000000000000000000000?s={$size}&d=mp";
    }
}
