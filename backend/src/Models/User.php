<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class User
{
    private PDO $db;
    private string $table = 'trail_users';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function create(string $googleId, string $email, string $name, string $gravatarHash): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (google_id, email, name, gravatar_hash) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$googleId, $email, $name, $gravatarHash]);
        
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $email, string $name, string $gravatarHash): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET email = ?, name = ?, gravatar_hash = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$email, $name, $gravatarHash, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table}");
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
}
