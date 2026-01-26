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

    public function create(int $userId, string $url, string $message): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, url, message) 
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$userId, $url, $message]);
        
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash 
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
            "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash 
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
            "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash 
             FROM {$this->table} e 
             JOIN trail_users u ON e.user_id = u.id 
             ORDER BY e.created_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        
        return $stmt->fetchAll();
    }

    public function update(int $id, string $url, string $message): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET url = ?, message = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$url, $message, $id]);
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
}
