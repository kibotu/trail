<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class CommentClap
{
    private PDO $db;
    private string $table = 'trail_comment_claps';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Add or update claps for a comment by a user
     * 
     * @param int $commentId Comment ID
     * @param int $userId User ID
     * @param int $count Total clap count (1-50)
     * @return bool Success status
     */
    public function addClap(int $commentId, int $userId, int $count): bool
    {
        // Validate count range
        if ($count < 1 || $count > 50) {
            return false;
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (comment_id, user_id, clap_count, created_at, updated_at) 
             VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE 
                clap_count = VALUES(clap_count),
                updated_at = CURRENT_TIMESTAMP"
        );
        
        return $stmt->execute([$commentId, $userId, $count]);
    }

    /**
     * Get total claps for a comment
     * 
     * @param int $commentId Comment ID
     * @return int Total clap count
     */
    public function getClapsByComment(int $commentId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(clap_count), 0) as total 
             FROM {$this->table} 
             WHERE comment_id = ?"
        );
        $stmt->execute([$commentId]);
        $result = $stmt->fetch();
        
        return (int) $result['total'];
    }

    /**
     * Get user's clap count for a specific comment
     * 
     * @param int $commentId Comment ID
     * @param int $userId User ID
     * @return int User's clap count (0 if not clapped)
     */
    public function getUserClapForComment(int $commentId, int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT clap_count 
             FROM {$this->table} 
             WHERE comment_id = ? AND user_id = ?"
        );
        $stmt->execute([$commentId, $userId]);
        $result = $stmt->fetch();
        
        return $result ? (int) $result['clap_count'] : 0;
    }

    /**
     * Batch fetch clap counts for multiple comments
     * Returns array with comment_id as key and total claps as value
     * 
     * @param array $commentIds Array of comment IDs
     * @param int|null $userId Optional user ID to include user's clap counts
     * @return array ['comment_id' => ['total' => int, 'user_claps' => int|null]]
     */
    public function getClapCounts(array $commentIds, ?int $userId = null): array
    {
        if (empty($commentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        
        if ($userId !== null) {
            // Include user's clap counts
            $stmt = $this->db->prepare(
                "SELECT 
                    comment_id,
                    SUM(clap_count) as total,
                    SUM(CASE WHEN user_id = ? THEN clap_count ELSE 0 END) as user_claps
                 FROM {$this->table}
                 WHERE comment_id IN ($placeholders)
                 GROUP BY comment_id"
            );
            $stmt->execute(array_merge([$userId], $commentIds));
        } else {
            // Only total counts
            $stmt = $this->db->prepare(
                "SELECT 
                    comment_id,
                    SUM(clap_count) as total
                 FROM {$this->table}
                 WHERE comment_id IN ($placeholders)
                 GROUP BY comment_id"
            );
            $stmt->execute($commentIds);
        }
        
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[(int) $row['comment_id']] = [
                'total' => (int) $row['total'],
                'user_claps' => isset($row['user_claps']) ? (int) $row['user_claps'] : null
            ];
        }
        
        return $results;
    }

    /**
     * Delete all claps for a comment (used when comment is deleted)
     * Note: This is handled automatically by CASCADE, but included for completeness
     * 
     * @param int $commentId Comment ID
     * @return bool Success status
     */
    public function deleteByComment(int $commentId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE comment_id = ?");
        return $stmt->execute([$commentId]);
    }

    /**
     * Delete all claps by a user (used when user is deleted)
     * Note: This is handled automatically by CASCADE, but included for completeness
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function deleteByUser(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
}
