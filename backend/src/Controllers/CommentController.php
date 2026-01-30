<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Comment;
use Trail\Models\Entry;
use Trail\Models\CommentReport;
use Trail\Config\Config;
use Trail\Services\TextSanitizer;
use Trail\Services\HashIdService;

class CommentController
{
    public static function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $entryHashId = $args['id'] ?? '';
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
        
        // Decode hash to get real entry ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        $entryId = $hashIdService->decode($entryHashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Verify entry exists
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $commentModel = new Comment($db);
        $commentId = $commentModel->create($entryId, $userId, $sanitizedText, $imageIds);
        $comment = $commentModel->findById($commentId);

        $response->getBody()->write(json_encode([
            'id' => $commentId,
            'created_at' => $comment['created_at'],
        ]));
        
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public static function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $entryHashId = $args['id'] ?? '';
        $queryParams = $request->getQueryParams();
        
        $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 50)));
        $before = $queryParams['before'] ?? null;

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Decode hash to get real entry ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        $entryId = $hashIdService->decode($entryHashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Try to detect authenticated user (optional - doesn't require auth)
        $userId = self::getOptionalUserId($request, $config);
        $excludeCommentIds = [];
        
        if ($userId) {
            // User is logged in - apply hide filters
            $reportModel = new CommentReport($db);
            $excludeCommentIds = $reportModel->getHiddenCommentIds($userId);
        }

        $commentModel = new Comment($db);
        $comments = $commentModel->getByEntryWithImages($entryId, $limit, $before, $userId, $excludeCommentIds);
        $hasMore = count($comments) === $limit;

        // Add avatar URLs and ensure nicknames
        $userModel = new \Trail\Models\User($db);
        $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
        
        foreach ($comments as &$comment) {
            $comment['avatar_url'] = self::getAvatarUrl($comment);
            
            // Generate nickname if not set
            if (empty($comment['user_nickname']) && !empty($comment['google_id'])) {
                $comment['user_nickname'] = $userModel->getOrGenerateNickname(
                    (int) $comment['user_id'],
                    $comment['google_id'],
                    $salt
                );
            }
        }

        // Get the cursor for the next page (created_at of the last comment)
        $nextCursor = null;
        if ($hasMore && !empty($comments)) {
            $lastComment = end($comments);
            $nextCursor = $lastComment['created_at'];
        }

        $response->getBody()->write(json_encode([
            'comments' => $comments,
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
        $commentId = (int) $args['id'];
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
        $commentModel = new Comment($db);

        // Authorization: Check if user can modify this comment
        if (!$commentModel->canModify($commentId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to modify this comment']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $success = $commentModel->update($commentId, $sanitizedText, $imageIds);

        if ($success) {
            $comment = $commentModel->findById($commentId);
            $response->getBody()->write(json_encode([
                'success' => true,
                'updated_at' => $comment['updated_at']
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to update comment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    public static function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $commentId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $commentModel = new Comment($db);

        // Authorization: Check if user can modify this comment
        if (!$commentModel->canModify($commentId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to delete this comment']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $success = $commentModel->delete($commentId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user avatar URL with Google photo fallback to Gravatar.
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

    /**
     * Optionally get user ID from request without requiring authentication
     */
    private static function getOptionalUserId(ServerRequestInterface $request, array $config): ?int
    {
        // Try to get JWT token from cookie
        $cookies = $request->getCookieParams();
        $token = $cookies['trail_jwt'] ?? null;

        // If no JWT cookie, try session
        if (!$token) {
            try {
                require_once __DIR__ . '/../../public/helpers/session.php';
                $session = getAuthenticatedUser($db ?? \Trail\Database\Database::getInstance($config));
                if ($session && !empty($session['jwt_token'])) {
                    $token = $session['jwt_token'];
                }
            } catch (\Throwable $e) {
                // Session check failed - user not logged in
                return null;
            }
        }

        if (!$token) {
            return null;
        }

        // Verify JWT token
        try {
            $jwtService = new \Trail\Services\JwtService($config);
            $payload = $jwtService->verify($token);
            
            if ($payload && isset($payload['user_id'])) {
                return (int) $payload['user_id'];
            }
        } catch (\Throwable $e) {
            // Token invalid or expired - treat as not logged in
            return null;
        }

        return null;
    }
}
