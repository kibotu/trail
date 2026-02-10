<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class View
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate a viewer fingerprint hash from IP, User-Agent, and optional client fingerprint.
     * 
     * This provides better deduplication than IP alone, distinguishing multiple
     * devices behind the same NAT/proxy.
     *
     * @param string $ipAddress Client IP address
     * @param string $userAgent User-Agent header
     * @param string|null $clientFingerprint Optional client-side fingerprint (e.g., from FingerprintJS)
     * @return string Binary SHA-256 hash (32 bytes)
     */
    public static function generateViewerHash(
        string $ipAddress,
        string $userAgent,
        ?string $clientFingerprint = null
    ): string {
        // Combine components into a fingerprint string
        // Order: IP | User-Agent | Client Fingerprint (if provided)
        $components = [
            $ipAddress,
            $userAgent,
        ];
        
        if ($clientFingerprint !== null && $clientFingerprint !== '') {
            $components[] = $clientFingerprint;
        }
        
        $fingerprintString = implode('|', $components);
        
        // Return raw binary hash (32 bytes for SHA-256)
        return hash('sha256', $fingerprintString, true);
    }

    /**
     * Record a view if the viewer hasn't viewed this target in the last 24 hours.
     *
     * For authenticated users, deduplication is by user_id.
     * For anonymous users, deduplication is by viewer_hash (IP + User-Agent + fingerprint).
     *
     * @param string $targetType Target type ('entry', 'comment', 'profile')
     * @param int $targetId Target ID
     * @param int|null $viewerId Authenticated user ID, or null for anonymous
     * @param string $viewerHash Binary SHA-256 hash from generateViewerHash()
     * @return bool True if a new view was recorded, false if deduplicated
     */
    public function recordView(
        string $targetType,
        int $targetId,
        ?int $viewerId,
        string $viewerHash
    ): bool {
        // Check for existing view within 24h window
        if ($viewerId !== null) {
            // Authenticated users: dedupe by user_id
            $stmt = $this->db->prepare(
                "SELECT 1 FROM trail_views
                 WHERE target_type = ? AND target_id = ?
                   AND viewer_id = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 LIMIT 1"
            );
            $stmt->execute([$targetType, $targetId, $viewerId]);
        } else {
            // Anonymous users: dedupe by viewer_hash
            $stmt = $this->db->prepare(
                "SELECT 1 FROM trail_views
                 WHERE target_type = ? AND target_id = ?
                   AND viewer_id IS NULL AND viewer_hash = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 LIMIT 1"
            );
            $stmt->execute([$targetType, $targetId, $viewerHash]);
        }

        if ($stmt->fetch()) {
            return false; // Already viewed recently
        }

        // Insert new view
        $stmt = $this->db->prepare(
            "INSERT INTO trail_views (target_type, target_id, viewer_id, viewer_hash)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$targetType, $targetId, $viewerId, $viewerHash]);

        // Atomically increment the counter cache
        $stmt = $this->db->prepare(
            "INSERT INTO trail_view_counts (target_type, target_id, view_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1"
        );
        $stmt->execute([$targetType, $targetId]);

        return true;
    }

    /**
     * Get the view count for a single target.
     * O(1) primary key lookup on trail_view_counts.
     */
    public function getViewCount(string $targetType, int $targetId): int
    {
        $stmt = $this->db->prepare(
            "SELECT view_count FROM trail_view_counts
             WHERE target_type = ? AND target_id = ?
             LIMIT 1"
        );
        $stmt->execute([$targetType, $targetId]);
        $row = $stmt->fetch();

        return $row ? (int) $row['view_count'] : 0;
    }

    /**
     * Batch fetch view counts for multiple targets of the same type.
     * Used when rendering entry/comment lists.
     *
     * @param string $targetType The target type ('entry', 'comment', 'profile')
     * @param array $targetIds Array of target IDs
     * @return array<int, int>  [target_id => view_count]
     */
    public function getViewCounts(string $targetType, array $targetIds): array
    {
        if (empty($targetIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT target_id, view_count
             FROM trail_view_counts
             WHERE target_type = ? AND target_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$targetType], $targetIds));

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[(int) $row['target_id']] = (int) $row['view_count'];
        }

        return $results;
    }

    /**
     * Get aggregated view stats for a user's profile page.
     * Single query, three subselects — same pattern as User::getProfileStats().
     */
    public function getProfileViewStats(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE((
                    SELECT SUM(vc.view_count)
                    FROM trail_view_counts vc
                    INNER JOIN trail_entries e ON vc.target_id = e.id
                    WHERE vc.target_type = 'entry' AND e.user_id = ?
                ), 0) AS total_entry_views,

                COALESCE((
                    SELECT SUM(vc.view_count)
                    FROM trail_view_counts vc
                    INNER JOIN trail_comments c ON vc.target_id = c.id
                    WHERE vc.target_type = 'comment' AND c.user_id = ?
                ), 0) AS total_comment_views,

                COALESCE((
                    SELECT view_count
                    FROM trail_view_counts
                    WHERE target_type = 'profile' AND target_id = ?
                ), 0) AS total_profile_views
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $row = $stmt->fetch();

        return [
            'total_entry_views'   => (int) ($row['total_entry_views'] ?? 0),
            'total_comment_views' => (int) ($row['total_comment_views'] ?? 0),
            'total_profile_views' => (int) ($row['total_profile_views'] ?? 0),
        ];
    }

    /**
     * Rebuild counter cache from raw views (admin/maintenance).
     * Safe to run at any time — idempotent.
     */
    public function rebuildCounterCache(): int
    {
        $this->db->exec("TRUNCATE TABLE trail_view_counts");
        
        return (int) $this->db->exec(
            "INSERT INTO trail_view_counts (target_type, target_id, view_count)
             SELECT target_type, target_id, COUNT(*) as view_count
             FROM trail_views
             GROUP BY target_type, target_id"
        );
    }

    /**
     * Delete views for a specific target (used when content is deleted).
     * Note: This is optional since we may want to keep historical view data.
     */
    public function deleteByTarget(string $targetType, int $targetId): bool
    {
        // Delete from counter cache
        $stmt = $this->db->prepare(
            "DELETE FROM trail_view_counts WHERE target_type = ? AND target_id = ?"
        );
        $stmt->execute([$targetType, $targetId]);

        // Delete raw views
        $stmt = $this->db->prepare(
            "DELETE FROM trail_views WHERE target_type = ? AND target_id = ?"
        );
        return $stmt->execute([$targetType, $targetId]);
    }
}
