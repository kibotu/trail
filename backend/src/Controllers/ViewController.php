<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\View;
use Trail\Models\Entry;
use Trail\Models\Comment;
use Trail\Models\User;
use Trail\Config\Config;
use Trail\Services\HashIdService;

class ViewController
{
    /**
     * Record a view for an entry.
     * POST /api/entries/{id}/views
     * 
     * Optional body: { "fingerprint": "client-side-fingerprint" }
     */
    public static function recordEntryView(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);

        $entryId = $hashIdService->decode($args['id'] ?? '');
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);

        // Verify entry exists
        $entryModel = new Entry($db);
        if (!$entryModel->findById($entryId)) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $viewModel = new View($db);
        $viewerId = $request->getAttribute('user_id');
        $viewerHash = self::getViewerHash($request);

        try {
            $recorded = $viewModel->recordView('entry', $entryId, $viewerId, $viewerHash);
            $viewCount = $viewModel->getViewCount('entry', $entryId);

            $response->getBody()->write(json_encode([
                'recorded'   => $recorded,
                'view_count' => $viewCount,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("ViewController: Database error recording entry view - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Record a view for a comment.
     * POST /api/comments/{id}/views
     * 
     * Optional body: { "fingerprint": "client-side-fingerprint" }
     */
    public static function recordCommentView(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);

        $commentId = $hashIdService->decode($args['id'] ?? '');
        if ($commentId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid comment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);

        // Verify comment exists
        $commentModel = new Comment($db);
        if (!$commentModel->findById($commentId)) {
            $response->getBody()->write(json_encode(['error' => 'Comment not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $viewModel = new View($db);
        $viewerId = $request->getAttribute('user_id');
        $viewerHash = self::getViewerHash($request);

        try {
            $recorded = $viewModel->recordView('comment', $commentId, $viewerId, $viewerHash);
            $viewCount = $viewModel->getViewCount('comment', $commentId);

            $response->getBody()->write(json_encode([
                'recorded'   => $recorded,
                'view_count' => $viewCount,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("ViewController: Database error recording comment view - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Record a view for a user profile.
     * POST /api/users/{nickname}/views
     * 
     * Optional body: { "fingerprint": "client-side-fingerprint" }
     */
    public static function recordProfileView(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $nickname = $args['nickname'] ?? '';
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

        $viewModel = new View($db);
        $viewerId = $request->getAttribute('user_id');
        $viewerHash = self::getViewerHash($request);

        // Don't count self-views on own profile
        if ($viewerId !== null && (int) $viewerId === (int) $user['id']) {
            $viewCount = $viewModel->getViewCount('profile', (int) $user['id']);
            $response->getBody()->write(json_encode([
                'recorded'   => false,
                'view_count' => $viewCount,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            $recorded = $viewModel->recordView('profile', (int) $user['id'], $viewerId, $viewerHash);
            $viewCount = $viewModel->getViewCount('profile', (int) $user['id']);

            $response->getBody()->write(json_encode([
                'recorded'   => $recorded,
                'view_count' => $viewCount,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("ViewController: Database error recording profile view - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Generate viewer hash from request (IP + User-Agent + optional fingerprint).
     * 
     * This provides better deduplication than IP alone, allowing differentiation
     * of multiple devices behind the same NAT/proxy.
     *
     * @param ServerRequestInterface $request
     * @return string Binary SHA-256 hash (32 bytes)
     */
    private static function getViewerHash(ServerRequestInterface $request): string
    {
        $ip = self::getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent') ?: 'unknown';
        
        // Get optional client fingerprint from request body
        $clientFingerprint = null;
        $body = (string) $request->getBody();
        if (!empty($body)) {
            $data = json_decode($body, true);
            if (is_array($data) && isset($data['fingerprint']) && is_string($data['fingerprint'])) {
                // Limit fingerprint length to prevent abuse
                $clientFingerprint = substr($data['fingerprint'], 0, 256);
            }
        }
        
        return View::generateViewerHash($ip, $userAgent, $clientFingerprint);
    }

    /**
     * Extract client IP from request, respecting X-Forwarded-For behind a reverse proxy.
     */
    private static function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        // Trust X-Forwarded-For only if behind a known proxy
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwarded)) {
            // Take the leftmost (client-originating) IP
            $ips = array_map('trim', explode(',', $forwarded));
            $ip = $ips[0];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
