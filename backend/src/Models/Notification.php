<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Notification
{
    private PDO $db;
    private string $table = 'trail_notifications';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create notification
     */
    public function create(int $userId, string $type, int $actorUserId, ?int $entryId, ?int $commentId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, type, actor_user_id, entry_id, comment_id) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $type, $actorUserId, $entryId, $commentId]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Get user's notifications (paginated, with actor details)
     */
    public function getByUser(int $userId, int $limit = 20, ?string $before = null): array
    {
        $sql = "SELECT n.*, 
                u.name as actor_name, 
                u.nickname as actor_nickname, 
                u.email as actor_email,
                u.gravatar_hash as actor_gravatar_hash,
                u.photo_url as actor_photo_url,
                e.text as entry_text,
                c.text as comment_text
                FROM {$this->table} n
                JOIN trail_users u ON n.actor_user_id = u.id
                LEFT JOIN trail_entries e ON n.entry_id = e.id
                LEFT JOIN trail_comments c ON n.comment_id = c.id
                WHERE n.user_id = ?";
        
        $params = [$userId];
        
        if ($before !== null) {
            $sql .= " AND n.created_at < ?";
            $params[] = $before;
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} 
             WHERE user_id = ? AND is_read = FALSE"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET is_read = TRUE 
             WHERE id = ? AND user_id = ?"
        );
        
        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Mark all user notifications as read
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET is_read = TRUE 
             WHERE user_id = ? AND is_read = FALSE"
        );
        
        return $stmt->execute([$userId]);
    }

    /**
     * Delete notification
     */
    public function delete(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} 
             WHERE id = ? AND user_id = ?"
        );
        
        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Check if user owns notification
     */
    public function canAccess(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} 
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$notificationId, $userId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'] > 0;
    }

    /**
     * Find notification by ID
     */
    public function findById(int $notificationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT n.*, 
            u.name as actor_name, 
            u.nickname as actor_nickname, 
            u.email as actor_email,
            u.gravatar_hash as actor_gravatar_hash,
            u.photo_url as actor_photo_url,
            e.text as entry_text,
            c.text as comment_text
            FROM {$this->table} n
            JOIN trail_users u ON n.actor_user_id = u.id
            LEFT JOIN trail_entries e ON n.entry_id = e.id
            LEFT JOIN trail_comments c ON n.comment_id = c.id
            WHERE n.id = ?"
        );
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch();
        
        return $notification ?: null;
    }
}
