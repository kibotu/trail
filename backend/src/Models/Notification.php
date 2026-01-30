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

    /**
     * Find notification by actor and target (for deduplication)
     */
    public function findByActorAndTarget(
        int $userId,
        int $actorUserId,
        string $type,
        ?int $entryId,
        ?int $commentId
    ): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = ? 
                AND actor_user_id = ? 
                AND type = ? 
                AND (entry_id = ? OR (entry_id IS NULL AND ? IS NULL))
                AND (comment_id = ? OR (comment_id IS NULL AND ? IS NULL))
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $actorUserId, $type, $entryId, $entryId, $commentId, $commentId]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Get user's notifications with clap grouping
     */
    public function getByUserGrouped(int $userId, int $limit = 50, ?string $before = null): array
    {
        // Fetch more than needed to account for grouping
        $notifications = $this->getByUser($userId, $limit * 2, $before);
        
        $grouped = [];
        $clapGroups = [];
        
        foreach ($notifications as $notif) {
            $type = $notif['type'];
            
            if ($type === 'clap_entry' || $type === 'clap_comment') {
                // Group key: type + target IDs
                $key = $type . '_' . ($notif['entry_id'] ?? 0) . '_' . ($notif['comment_id'] ?? 0);
                
                if (!isset($clapGroups[$key])) {
                    $clapGroups[$key] = [
                        'type' => $type,
                        'entry_id' => $notif['entry_id'],
                        'comment_id' => $notif['comment_id'],
                        'entry_text' => $notif['entry_text'],
                        'comment_text' => $notif['comment_text'],
                        'created_at' => $notif['created_at'],
                        'is_read' => $notif['is_read'],
                        'actors' => []
                    ];
                }
                
                $clapGroups[$key]['actors'][] = [
                    'id' => $notif['actor_user_id'],
                    'name' => $notif['actor_nickname'] ?? $notif['actor_name'],
                    'email' => $notif['actor_email'],
                    'gravatar_hash' => $notif['actor_gravatar_hash'],
                    'photo_url' => $notif['actor_photo_url'],
                    'avatar_url' => $this->getAvatarUrl($notif)
                ];
                
                // Latest timestamp wins
                if ($notif['created_at'] > $clapGroups[$key]['created_at']) {
                    $clapGroups[$key]['created_at'] = $notif['created_at'];
                }
                // Any unread makes group unread
                if (!$notif['is_read']) {
                    $clapGroups[$key]['is_read'] = false;
                }
            } else {
                $grouped[] = $notif; // Pass through
            }
        }
        
        // Merge clap groups into result
        $grouped = array_merge($grouped, array_values($clapGroups));
        
        // Sort by timestamp desc
        usort($grouped, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        
        return array_slice($grouped, 0, $limit);
    }

    /**
     * Get avatar URL for notification actor
     */
    private function getAvatarUrl(array $notification): string
    {
        if (!empty($notification['actor_photo_url'])) {
            return $notification['actor_photo_url'];
        }
        
        $hash = $notification['actor_gravatar_hash'] ?? md5(strtolower(trim($notification['actor_email'] ?? '')));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }
}
