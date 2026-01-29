<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Report
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Report an entry
     * Returns true if this is a new report, false if already reported
     */
    public function reportEntry(int $entryId, int $reporterUserId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO trail_entry_reports (entry_id, reporter_user_id) 
                 VALUES (?, ?)"
            );
            $stmt->execute([$entryId, $reporterUserId]);
            return true;
        } catch (\PDOException $e) {
            // Duplicate entry - user already reported this
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get unique report count for an entry
     */
    public function getReportCount(int $entryId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM trail_entry_reports WHERE entry_id = ?"
        );
        $stmt->execute([$entryId]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    /**
     * Check if user has already reported an entry
     */
    public function hasUserReported(int $entryId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM trail_entry_reports 
             WHERE entry_id = ? AND reporter_user_id = ?"
        );
        $stmt->execute([$entryId, $userId]);
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Check if email was already sent for this entry
     */
    public function wasEmailSent(int $entryId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM trail_report_emails WHERE entry_id = ?"
        );
        $stmt->execute([$entryId]);
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Mark that email was sent for this entry
     */
    public function markEmailSent(int $entryId, int $reportCount): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO trail_report_emails (entry_id, report_count) 
             VALUES (?, ?)"
        );
        return $stmt->execute([$entryId, $reportCount]);
    }

    /**
     * Hide an entry for a user
     */
    public function hideEntry(int $entryId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO trail_hidden_entries (entry_id, user_id) 
                 VALUES (?, ?)"
            );
            return $stmt->execute([$entryId, $userId]);
        } catch (\PDOException $e) {
            // Already hidden - that's fine
            if ($e->getCode() === '23000') {
                return true;
            }
            throw $e;
        }
    }

    /**
     * Check if entry is hidden for user
     */
    public function isEntryHidden(int $entryId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM trail_hidden_entries 
             WHERE entry_id = ? AND user_id = ?"
        );
        $stmt->execute([$entryId, $userId]);
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Mute a user
     */
    public function muteUser(int $muterUserId, int $mutedUserId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO trail_muted_users (muter_user_id, muted_user_id) 
                 VALUES (?, ?)"
            );
            return $stmt->execute([$muterUserId, $mutedUserId]);
        } catch (\PDOException $e) {
            // Already muted - that's fine
            if ($e->getCode() === '23000') {
                return true;
            }
            throw $e;
        }
    }

    /**
     * Unmute a user
     */
    public function unmuteUser(int $muterUserId, int $mutedUserId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM trail_muted_users 
             WHERE muter_user_id = ? AND muted_user_id = ?"
        );
        return $stmt->execute([$muterUserId, $mutedUserId]);
    }

    /**
     * Check if a user is muted by another user
     */
    public function isUserMuted(int $muterUserId, int $mutedUserId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM trail_muted_users 
             WHERE muter_user_id = ? AND muted_user_id = ?"
        );
        $stmt->execute([$muterUserId, $mutedUserId]);
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    /**
     * Get list of muted user IDs for a user
     */
    public function getMutedUserIds(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT muted_user_id FROM trail_muted_users WHERE muter_user_id = ?"
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'muted_user_id');
    }

    /**
     * Get list of hidden entry IDs for a user
     */
    public function getHiddenEntryIds(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT entry_id FROM trail_hidden_entries WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'entry_id');
    }
}
