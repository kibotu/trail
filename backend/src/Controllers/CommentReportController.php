<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Comment;
use Trail\Models\CommentReport;
use Trail\Config\Config;

class CommentReportController
{
    /**
     * Report a comment
     * POST /api/comments/{id}/report
     */
    public static function reportComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Authentication required
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Authentication required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

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

        $reportModel = new CommentReport($db);

        // Check if already reported
        if ($reportModel->hasUserReported($commentId, $userId)) {
            $response->getBody()->write(json_encode(['error' => 'You have already reported this comment']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Add report
            $reportModel->reportComment($commentId, $userId);
            
            // Hide comment for user
            $reportModel->hideComment($commentId, $userId);
            
            // Get report count
            $reportCount = $reportModel->getReportCount($commentId);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Comment reported successfully',
                'report_count' => $reportCount
            ]));

            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CommentReportController: Database error reporting comment - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("CommentReportController: Unexpected error reporting comment - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
