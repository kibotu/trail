<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Config\Config;
use Trail\Services\TextSanitizer;

class EntryController
{
    public static function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        $text = $data['text'] ?? '';

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

        // Double-check length after sanitization
        if (mb_strlen($sanitizedText) > 280) {
            $response->getBody()->write(json_encode(['error' => 'Text must be 280 characters or less']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entryId = $entryModel->create($userId, $sanitizedText);
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

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getAll($limit, $before);
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

    public static function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        
        $limit = min(50, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getByUser($userId, $limit, $before);
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

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Authorization: Check if user can modify this entry
        if (!$entryModel->canModify($entryId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to modify this entry']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $success = $entryModel->update($entryId, $sanitizedText);

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

        $config = Config::load(__DIR__ . '/../../config.yml');
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
