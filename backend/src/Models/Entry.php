<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Entry
{
    private PDO $db;
    private string $table = 'trail_entries';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $userId, string $text): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, text) 
             VALUES (?, ?)"
        );
        $stmt->execute([$userId, $text]);
        
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
             FROM {$this->table} e 
             JOIN trail_users u ON e.user_id = u.id 
             WHERE e.id = ?"
        );
        $stmt->execute([$id]);
        $entry = $stmt->fetch();
        
        return $entry ?: null;
    }

    public function getByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
             FROM {$this->table} e 
             JOIN trail_users u ON e.user_id = u.id 
             WHERE e.user_id = ? 
             ORDER BY e.created_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        
        return $stmt->fetchAll();
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
             FROM {$this->table} e 
             JOIN trail_users u ON e.user_id = u.id 
             ORDER BY e.created_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        
        return $stmt->fetchAll();
    }

    public function update(int $id, string $text): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET text = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$text, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table}");
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Check if a user can modify an entry (creator or admin)
     */
    public function canModify(int $entryId, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        $stmt = $this->db->prepare("SELECT user_id FROM {$this->table} WHERE id = ?");
        $stmt->execute([$entryId]);
        $entry = $stmt->fetch();

        if (!$entry) {
            return false;
        }

        return (int) $entry['user_id'] === $userId;
    }
}
