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

    public function create(string $googleId, string $email, string $name, string $gravatarHash, ?string $photoUrl = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (google_id, email, name, gravatar_hash, photo_url) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$googleId, $email, $name, $gravatarHash, $photoUrl]);
        
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $email, string $name, string $gravatarHash, ?string $photoUrl = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET email = ?, name = ?, gravatar_hash = ?, photo_url = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$email, $name, $gravatarHash, $photoUrl, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAll(int $limit = 50, ?string $before = null): array
    {
        if ($before !== null) {
            // Cursor-based pagination: get users created before the cursor timestamp
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE created_at < ? 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$before, $limit]);
        } else {
            // Initial load: get most recent users
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table}");
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    public function setAdminStatus(int $id, bool $isAdmin): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET is_admin = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$isAdmin ? 1 : 0, $id]);
    }
}
