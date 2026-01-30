<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class CommentReport
{
    private PDO $db;
    private string $reportsTable = 'trail_comment_reports';
    private string $hiddenTable = 'trail_hidden_comments';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Report a comment
     * 
     * @param int $commentId Comment ID
     * @param int $userId User ID of reporter
     * @return bool Success status
     */
    public function reportComment(int $commentId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->reportsTable} (comment_id, reporter_user_id, created_at) 
                 VALUES (?, ?, CURRENT_TIMESTAMP)"
            );
            return $stmt->execute([$commentId, $userId]);
        } catch (\PDOException $e) {
            // Duplicate entry (already reported)
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Hide a comment for a specific user
     * 
     * @param int $commentId Comment ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function hideComment(int $commentId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->hiddenTable} (comment_id, user_id, created_at) 
                 VALUES (?, ?, CURRENT_TIMESTAMP)"
            );
            return $stmt->execute([$commentId, $userId]);
        } catch (\PDOException $e) {
            // Duplicate entry (already hidden)
            if ($e->getCode() === '23000') {
                return true; // Already hidden is okay
            }
            throw $e;
        }
    }

    /**
     * Check if a user has reported a comment
     * 
     * @param int $commentId Comment ID
     * @param int $userId User ID
     * @return bool True if already reported
     */
    public function hasUserReported(int $commentId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count 
             FROM {$this->reportsTable} 
             WHERE comment_id = ? AND reporter_user_id = ?"
        );
        $stmt->execute([$commentId, $userId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'] > 0;
    }

    /**
     * Get hidden comment IDs for a user
     * 
     * @param int $userId User ID
     * @return array Array of comment IDs
     */
    public function getHiddenCommentIds(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT comment_id 
             FROM {$this->hiddenTable} 
             WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        
        $ids = [];
        while ($row = $stmt->fetch()) {
            $ids[] = (int) $row['comment_id'];
        }
        
        return $ids;
    }

    /**
     * Get report count for a comment
     * 
     * @param int $commentId Comment ID
     * @return int Number of reports
     */
    public function getReportCount(int $commentId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count 
             FROM {$this->reportsTable} 
             WHERE comment_id = ?"
        );
        $stmt->execute([$commentId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Check if email was sent for a comment report
     * Note: For comments, we might want to notify the entry author
     * This is a placeholder for future implementation
     * 
     * @param int $commentId Comment ID
     * @return bool True if email was sent
     */
    public function wasEmailSent(int $commentId): bool
    {
        // For now, we don't send emails for comment reports
        // This can be implemented later if needed
        return false;
    }

    /**
     * Mark email as sent for a comment report
     * Note: Placeholder for future implementation
     * 
     * @param int $commentId Comment ID
     * @param int $reportCount Number of reports at time of email
     * @return bool Success status
     */
    public function markEmailSent(int $commentId, int $reportCount): bool
    {
        // Placeholder for future implementation
        return true;
    }
}
