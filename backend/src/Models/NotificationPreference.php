<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class NotificationPreference
{
    private PDO $db;
    private string $table = 'trail_notification_preferences';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get user preferences (create defaults if missing)
     */
    public function get(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();
        
        // If no preferences exist, create defaults
        if (!$prefs) {
            $this->createDefaults($userId);
            $stmt->execute([$userId]);
            $prefs = $stmt->fetch();
        }
        
        return $prefs ?: [];
    }

    /**
     * Create default preferences for user
     */
    private function createDefaults(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, email_on_mention, email_on_comment, email_on_clap, email_digest_frequency) 
             VALUES (?, TRUE, FALSE, FALSE, 'instant')"
        );
        
        return $stmt->execute([$userId]);
    }

    /**
     * Update preferences
     */
    public function update(int $userId, array $preferences): bool
    {
        // Ensure preferences exist first
        $this->get($userId);
        
        $fields = [];
        $params = [];
        
        if (isset($preferences['email_on_mention'])) {
            $fields[] = "email_on_mention = ?";
            $params[] = (bool) $preferences['email_on_mention'];
        }
        
        if (isset($preferences['email_on_comment'])) {
            $fields[] = "email_on_comment = ?";
            $params[] = (bool) $preferences['email_on_comment'];
        }
        
        if (isset($preferences['email_on_clap'])) {
            $fields[] = "email_on_clap = ?";
            $params[] = (bool) $preferences['email_on_clap'];
        }
        
        if (isset($preferences['email_digest_frequency'])) {
            $fields[] = "email_digest_frequency = ?";
            $params[] = $preferences['email_digest_frequency'];
        }
        
        if (empty($fields)) {
            return true; // Nothing to update
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $userId;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Check if email should be sent for notification type
     */
    public function shouldSendEmail(int $userId, string $notificationType): bool
    {
        $prefs = $this->get($userId);
        
        if (empty($prefs)) {
            return false;
        }
        
        // Check digest frequency
        if ($prefs['email_digest_frequency'] === 'never') {
            return false;
        }
        
        // For instant notifications, check specific preferences
        if ($prefs['email_digest_frequency'] === 'instant') {
            switch ($notificationType) {
                case 'mention_entry':
                case 'mention_comment':
                    return (bool) $prefs['email_on_mention'];
                    
                case 'comment_on_entry':
                    return (bool) $prefs['email_on_comment'];
                    
                case 'clap_entry':
                case 'clap_comment':
                    return (bool) $prefs['email_on_clap'];
                    
                default:
                    return false;
            }
        }
        
        // For daily/weekly digests, return false (will be handled by digest system)
        return false;
    }
}
