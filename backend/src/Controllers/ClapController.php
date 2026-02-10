<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Clap;
use Trail\Models\Entry;
use Trail\Config\Config;
use Trail\Services\HashIdService;

class ClapController
{
    /**
     * Add or update claps for an entry
     * POST /api/entries/{id}/claps
     * Body: {"count": 1-50}
     */
    public static function addClap(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Authentication required
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Authentication required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // Get entry ID from hash
        $hashId = $args['id'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Initialize HashIdService with salt from config
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        
        // Decode hash to get real entry ID
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Parse request body
        $data = json_decode((string) $request->getBody(), true);
        if (!$data || !isset($data['count'])) {
            $response->getBody()->write(json_encode(['error' => 'Clap count is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $count = $data['count'];

        // Validate count is numeric
        if (!is_numeric($count)) {
            $response->getBody()->write(json_encode(['error' => 'Clap count must be a number']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $count = (int) $count;

        // Check if caller is admin and has provided a custom max_claps
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $maxClaps = 50; // Default limit for regular users
        
        if ($isAdmin && isset($data['max_claps'])) {
            $customMax = (int) $data['max_claps'];
            if ($customMax >= 1 && $customMax <= 100000) {
                $maxClaps = $customMax;
            }
        }

        // Validate count range (1-maxClaps)
        if ($count < 1 || $count > $maxClaps) {
            $response->getBody()->write(json_encode([
                'error' => "Clap count must be between 1 and {$maxClaps}"
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        
        // Verify entry exists
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Prevent users from clapping their own entries
        if ((int) $entry['user_id'] === (int) $userId) {
            $response->getBody()->write(json_encode(['error' => 'You cannot clap for your own entries']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Add/update clap
        try {
            $clapModel = new Clap($db);
            $success = $clapModel->addClap($entryId, $userId, $count, $maxClaps);

            if (!$success) {
                error_log("ClapController: Failed to add clap for entry $entryId, user $userId");
                $response->getBody()->write(json_encode(['error' => 'Failed to add clap']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        } catch (\PDOException $e) {
            error_log("ClapController: Database error adding clap - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("ClapController: Unexpected error adding clap - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // Create notification for entry author (if not already exists)
        try {
            $notificationModel = new \Trail\Models\Notification($db);
            $existingNotification = $notificationModel->findByActorAndTarget(
                (int) $entry['user_id'],
                $userId,
                'clap_entry',
                $entryId,
                null
            );
            
            if (!$existingNotification) {
                $notificationModel->create(
                    (int) $entry['user_id'],
                    'clap_entry',
                    $userId,
                    $entryId,
                    null
                );
            }
        } catch (\Throwable $e) {
            // Log error but don't fail the clap operation
            error_log("ClapController: Notification creation failed: " . $e->getMessage());
        }

        // Get updated totals
        $totalClaps = $clapModel->getClapsByEntry($entryId);
        $userClaps = $clapModel->getUserClapForEntry($entryId, $userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'total_claps' => $totalClaps,
            'user_claps' => $userClaps
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get clap statistics for an entry
     * GET /api/entries/{id}/claps
     */
    public static function getClaps(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Get entry ID from hash
        $hashId = $args['id'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Initialize HashIdService with salt from config
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        
        // Decode hash to get real entry ID
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        
        // Verify entry exists
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $clapModel = new Clap($db);
            $totalClaps = $clapModel->getClapsByEntry($entryId);
            
            // Get user's claps if authenticated
            $userId = $request->getAttribute('user_id');
            $userClaps = $userId ? $clapModel->getUserClapForEntry($entryId, $userId) : null;

            $response->getBody()->write(json_encode([
                'total' => $totalClaps,
                'user_claps' => $userClaps
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("ClapController: Database error getting claps - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("ClapController: Unexpected error getting claps - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
