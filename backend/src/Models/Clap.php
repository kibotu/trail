<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Clap
{
    private PDO $db;
    private string $table = 'trail_claps';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Add or update claps for an entry by a user
     * 
     * @param int $entryId Entry ID
     * @param int $userId User ID
     * @param int $count Total clap count (1-50 by default, configurable via maxClaps)
     * @param int $maxClaps Maximum allowed claps (default 50, can be higher for API imports)
     * @return bool Success status
     */
    public function addClap(int $entryId, int $userId, int $count, int $maxClaps = 50): bool
    {
        // Validate count range
        if ($count < 1 || $count > $maxClaps) {
            return false;
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (entry_id, user_id, clap_count, created_at, updated_at) 
             VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE 
                clap_count = VALUES(clap_count),
                updated_at = CURRENT_TIMESTAMP"
        );
        
        return $stmt->execute([$entryId, $userId, $count]);
    }

    /**
     * Get total claps for an entry
     * 
     * @param int $entryId Entry ID
     * @return int Total clap count
     */
    public function getClapsByEntry(int $entryId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(clap_count), 0) as total 
             FROM {$this->table} 
             WHERE entry_id = ?"
        );
        $stmt->execute([$entryId]);
        $result = $stmt->fetch();
        
        return (int) $result['total'];
    }

    /**
     * Get user's clap count for a specific entry
     * 
     * @param int $entryId Entry ID
     * @param int $userId User ID
     * @return int User's clap count (0 if not clapped)
     */
    public function getUserClapForEntry(int $entryId, int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT clap_count 
             FROM {$this->table} 
             WHERE entry_id = ? AND user_id = ?"
        );
        $stmt->execute([$entryId, $userId]);
        $result = $stmt->fetch();
        
        return $result ? (int) $result['clap_count'] : 0;
    }

    /**
     * Batch fetch clap counts for multiple entries
     * Returns array with entry_id as key and total claps as value
     * 
     * @param array $entryIds Array of entry IDs
     * @param int|null $userId Optional user ID to include user's clap counts
     * @return array ['entry_id' => ['total' => int, 'user_claps' => int|null]]
     */
    public function getClapCounts(array $entryIds, ?int $userId = null): array
    {
        if (empty($entryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        
        if ($userId !== null) {
            // Include user's clap counts
            $stmt = $this->db->prepare(
                "SELECT 
                    entry_id,
                    SUM(clap_count) as total,
                    SUM(CASE WHEN user_id = ? THEN clap_count ELSE 0 END) as user_claps
                 FROM {$this->table}
                 WHERE entry_id IN ($placeholders)
                 GROUP BY entry_id"
            );
            $stmt->execute(array_merge([$userId], $entryIds));
        } else {
            // Only total counts
            $stmt = $this->db->prepare(
                "SELECT 
                    entry_id,
                    SUM(clap_count) as total
                 FROM {$this->table}
                 WHERE entry_id IN ($placeholders)
                 GROUP BY entry_id"
            );
            $stmt->execute($entryIds);
        }
        
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[(int) $row['entry_id']] = [
                'total' => (int) $row['total'],
                'user_claps' => isset($row['user_claps']) ? (int) $row['user_claps'] : null
            ];
        }
        
        return $results;
    }

    /**
     * Delete all claps for an entry (used when entry is deleted)
     * Note: This is handled automatically by CASCADE, but included for completeness
     * 
     * @param int $entryId Entry ID
     * @return bool Success status
     */
    public function deleteByEntry(int $entryId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE entry_id = ?");
        return $stmt->execute([$entryId]);
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
