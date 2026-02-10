<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Report;
use Trail\Models\Entry;
use Trail\Config\Config;
use Trail\Services\EmailService;
use Trail\Services\HashIdService;

class ReportController
{
    /**
     * Report an entry
     */
    public static function reportEntry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $entryId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $reportModel = new Report($db);
        $entryModel = new Entry($db);

        // Check if entry exists
        $entry = $entryModel->findById($entryId);
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Check if user already reported this entry
        if ($reportModel->hasUserReported($entryId, $userId)) {
            $response->getBody()->write(json_encode([
                'error' => 'You have already reported this entry',
                'already_reported' => true
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Add the report
        $reportModel->reportEntry($entryId, $userId);
        
        // Hide the entry for this user
        $reportModel->hideEntry($entryId, $userId);

        // Get total report count
        $reportCount = $reportModel->getReportCount($entryId);

        // Send email to admin if not already sent
        if (!$reportModel->wasEmailSent($entryId)) {
            try {
                // Add hash_id to entry for email
                $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
                $hashIdService = new HashIdService($hashSalt);
                $entry['hash_id'] = $hashIdService->encode($entryId);
                
                $adminEmail = $config['production']['admin_email'] ?? 'admin@example.com';
                $baseUrl = $config['app']['base_url'] ?? 'http://localhost';
                
                $emailService = new EmailService($adminEmail, $baseUrl);
                $emailSent = $emailService->sendReportNotification($entry, $reportCount);
                
                if ($emailSent) {
                    $reportModel->markEmailSent($entryId, $reportCount);
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the request
                error_log("ReportController: Failed to send email: " . $e->getMessage());
            }
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'report_count' => $reportCount,
            'message' => 'Entry reported successfully'
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Mute a user
     */
    public static function muteUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $mutedUserId = (int) $args['id'];

        // Prevent self-muting
        if ($userId === $mutedUserId) {
            $response->getBody()->write(json_encode(['error' => 'You cannot mute yourself']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $reportModel = new Report($db);

        // Mute the user
        $reportModel->muteUser($userId, $mutedUserId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'User muted successfully'
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Unmute a user
     */
    public static function unmuteUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $mutedUserId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $reportModel = new Report($db);

        // Unmute the user
        $reportModel->unmuteUser($userId, $mutedUserId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'User unmuted successfully'
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get muted users and hidden entries for current user
     */
    public static function getFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $reportModel = new Report($db);

        $mutedUserIds = $reportModel->getMutedUserIds($userId);
        $hiddenEntryIds = $reportModel->getHiddenEntryIds($userId);

        $response->getBody()->write(json_encode([
            'muted_users' => $mutedUserIds,
            'hidden_entries' => $hiddenEntryIds
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Check if a user is muted
     */
    public static function checkMuteStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $targetUserId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $reportModel = new Report($db);

        $isMuted = $reportModel->isUserMuted($userId, $targetUserId);

        $response->getBody()->write(json_encode([
            'is_muted' => $isMuted
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user info by ID (for muted users list)
     */
    public static function getUserInfo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $targetUserId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $userModel = new \Trail\Models\User($db);
        $user = $userModel->findById($targetUserId);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Return safe user info (no sensitive data)
        $response->getBody()->write(json_encode([
            'id' => $user['id'],
            'name' => $user['name'],
            'nickname' => $user['nickname'],
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash']
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
