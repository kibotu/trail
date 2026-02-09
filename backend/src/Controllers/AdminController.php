<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Models\User;
use Trail\Models\Comment;
use Trail\Config\Config;
use Trail\Services\TextSanitizer;
use Trail\Services\StorageService;
use Trail\Services\ErrorLogService;
use Trail\Cron\CleanupOrphanImages;

class AdminController
{
    public static function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $entryModel = new Entry($db);
        $userModel = new User($db);

        $stats = [
            'total_entries' => $entryModel->count(),
            'total_users' => $userModel->count(),
        ];

        // Load template
        $html = self::renderTemplate('dashboard', $stats);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public static function entries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Get pagination parameters
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? max(0, (int)$queryParams['page']) : 0;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20;
        $offset = $page * $limit;

        // Admin view includes clap counts (no user-specific counts needed)
        $entries = $entryModel->getAllWithImages($limit, null, $offset, null, [], null);

        // Initialize HashIdService
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new \Trail\Services\HashIdService($hashSalt);

        // Add avatar URLs and hash IDs
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
            try {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            } catch (\Throwable $e) {
                error_log("Failed to encode entry ID {$entry['id']}: " . $e->getMessage());
                $entry['hash_id'] = (string) $entry['id'];
            }
        }

        $response->getBody()->write(json_encode([
            'entries' => $entries,
            'page' => $page,
            'limit' => $limit,
            'count' => count($entries)
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function updateEntry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $entryId = (int) $args['id'];
        $data = json_decode((string) $request->getBody(), true);

        $text = $data['text'] ?? '';

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

        $success = $entryModel->update($entryId, $sanitizedText);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function deleteEntry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $entryId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $success = $entryModel->delete($entryId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function users(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        $users = $userModel->getAll(100, null);

        // Add avatar URLs with Google photo fallback to Gravatar
        foreach ($users as &$user) {
            $user['avatar_url'] = self::getAvatarUrl($user, 200);
        }

        $html = self::renderTemplate('users', ['users' => $users]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public static function deleteUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        $success = $userModel->delete($userId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function deleteUserEntries(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $deleted = $entryModel->deleteByUser($userId);

        $response->getBody()->write(json_encode(['success' => true, 'deleted' => $deleted]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function deleteUserComments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $commentModel = new Comment($db);

        $deleted = $commentModel->deleteByUser($userId);

        $response->getBody()->write(json_encode(['success' => true, 'deleted' => $deleted]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private static function renderTemplate(string $name, array $data = []): string
    {
        $templatePath = __DIR__ . '/../../templates/admin/' . $name . '.php';
        
        if (!file_exists($templatePath)) {
            return '<h1>Template not found: ' . htmlspecialchars($name) . '</h1>';
        }

        extract($data);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Get user avatar URL with Google photo fallback to Gravatar.
     * 
     * @param array $user User data with photo_url, gravatar_hash, and email
     * @param int $size Avatar size in pixels
     * @return string Avatar URL (already HTML-escaped)
     */
    private static function getAvatarUrl(array $user, int $size = 96): string
    {
        // Use Google photo if available
        if (!empty($user['photo_url'])) {
            return htmlspecialchars($user['photo_url'], ENT_QUOTES, 'UTF-8');
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
     * Clear temporary cache files
     */
    public static function clearCache(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            
            $uploadBasePath = __DIR__ . '/../../public/uploads/images';
            $tempBasePath = __DIR__ . '/../../storage/temp';
            
            $storageService = new StorageService($db, $uploadBasePath, $tempBasePath);
            $cleaned = $storageService->clearTempFiles(3600); // Clear files older than 1 hour
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'cleaned' => $cleaned,
                'message' => "Cleared {$cleaned} temporary upload directories"
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Throwable $e) {
            error_log("Cache clear error: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get error logs with pagination and filtering
     */
    public static function errorLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $errorLogService = new ErrorLogService($db);

        // Get query parameters
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? max(0, (int)$queryParams['page']) : 0;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 50;
        $offset = $page * $limit;
        $statusCode = isset($queryParams['status_code']) ? (int)$queryParams['status_code'] : null;

        // Get error logs
        $logs = $errorLogService->getErrorLogs($limit, $offset, $statusCode);
        
        // All data is already sanitized by ErrorLogService
        // Additional HTML escaping for JSON output (defense in depth)
        foreach ($logs as &$log) {
            $log['url'] = htmlspecialchars($log['url'], ENT_QUOTES, 'UTF-8');
            if ($log['referer']) {
                $log['referer'] = htmlspecialchars($log['referer'], ENT_QUOTES, 'UTF-8');
            }
            if ($log['user_agent']) {
                $log['user_agent'] = htmlspecialchars($log['user_agent'], ENT_QUOTES, 'UTF-8');
            }
        }

        $response->getBody()->write(json_encode([
            'logs' => $logs,
            'page' => $page,
            'limit' => $limit
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get error statistics
     */
    public static function errorStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $errorLogService = new ErrorLogService($db);

        $stats = $errorLogService->getErrorStatistics();

        $response->getBody()->write(json_encode([
            'statistics' => $stats
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Clean up old error logs
     */
    public static function cleanupErrorLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            $errorLogService = new ErrorLogService($db);

            // Get days parameter (default 90 days)
            $queryParams = $request->getQueryParams();
            $daysToKeep = isset($queryParams['days']) ? max(1, (int)$queryParams['days']) : 90;

            $deleted = $errorLogService->cleanupOldLogs($daysToKeep);

            $response->getBody()->write(json_encode([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Deleted {$deleted} old error logs (older than {$daysToKeep} days)"
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Throwable $e) {
            error_log("Error log cleanup error: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Prune orphaned images
     * Performs bidirectional cleanup:
     * 1. Deletes orphaned image files not referenced by entries, comments, or users
     * 2. Removes database records for images where the file no longer exists
     */
    public static function pruneImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            // Get days parameter (default 0 = all orphans)
            $queryParams = $request->getQueryParams();
            $days = isset($queryParams['days']) ? max(0, (int)$queryParams['days']) : 0;

            // Run cleanup
            $results = CleanupOrphanImages::run($days);

            // Build success message
            $message = sprintf(
                "Cleanup complete: %d orphaned file%s deleted, %d database record%s removed",
                $results['deleted_files'],
                $results['deleted_files'] === 1 ? '' : 's',
                $results['deleted_db_records'],
                $results['deleted_db_records'] === 1 ? '' : 's'
            );

            if ($results['errors'] > 0) {
                $message .= sprintf(" (%d error%s)", $results['errors'], $results['errors'] === 1 ? '' : 's');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'deleted_files' => $results['deleted_files'],
                'deleted_db_records' => $results['deleted_db_records'],
                'errors' => $results['errors'],
                'message' => $message,
                'details' => $results['details']
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Throwable $e) {
            error_log("Image prune error: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
