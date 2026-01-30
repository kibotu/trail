<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\CommentClap;
use Trail\Models\Comment;
use Trail\Config\Config;

class CommentClapController
{
    /**
     * Add or update claps for a comment
     * POST /api/comments/{id}/claps
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

        $commentId = (int) $args['id'];

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

        // Validate count range (1-50)
        if ($count < 1 || $count > 50) {
            $response->getBody()->write(json_encode(['error' => 'Clap count must be between 1 and 50']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Verify comment exists
        $commentModel = new Comment($db);
        $comment = $commentModel->findById($commentId);
        
        if (!$comment) {
            $response->getBody()->write(json_encode(['error' => 'Comment not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Prevent users from clapping their own comments
        if ((int) $comment['user_id'] === (int) $userId) {
            $response->getBody()->write(json_encode(['error' => 'You cannot clap for your own comments']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Add/update clap
        try {
            $clapModel = new CommentClap($db);
            $success = $clapModel->addClap($commentId, $userId, $count);

            if (!$success) {
                error_log("CommentClapController: Failed to add clap for comment $commentId, user $userId");
                $response->getBody()->write(json_encode(['error' => 'Failed to add clap']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        } catch (\PDOException $e) {
            error_log("CommentClapController: Database error adding clap - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("CommentClapController: Unexpected error adding clap - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // Get updated totals
        $totalClaps = $clapModel->getClapsByComment($commentId);
        $userClaps = $clapModel->getUserClapForComment($commentId, $userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'total_claps' => $totalClaps,
            'user_claps' => $userClaps
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get clap statistics for a comment
     * GET /api/comments/{id}/claps
     */
    public static function getClaps(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $commentId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Verify comment exists
        $commentModel = new Comment($db);
        $comment = $commentModel->findById($commentId);
        
        if (!$comment) {
            $response->getBody()->write(json_encode(['error' => 'Comment not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $clapModel = new CommentClap($db);
            $totalClaps = $clapModel->getClapsByComment($commentId);
            
            // Get user's claps if authenticated
            $userId = $request->getAttribute('user_id');
            $userClaps = $userId ? $clapModel->getUserClapForComment($commentId, $userId) : null;

            $response->getBody()->write(json_encode([
                'total' => $totalClaps,
                'user_claps' => $userClaps
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CommentClapController: Database error getting claps - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("CommentClapController: Unexpected error getting claps - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
