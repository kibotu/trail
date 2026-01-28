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

    public function create(int $userId, string $text, ?array $preview = null): int
    {
        if ($preview !== null) {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, text, preview_url, preview_title, preview_description, preview_image, preview_site_name) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $text,
                $preview['url'] ?? null,
                $preview['title'] ?? null,
                $preview['description'] ?? null,
                $preview['image'] ?? null,
                $preview['site_name'] ?? null,
            ]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, text) 
                 VALUES (?, ?)"
            );
            $stmt->execute([$userId, $text]);
        }
        
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

    public function getByUser(int $userId, int $limit = 20, ?string $before = null): array
    {
        if ($before !== null) {
            // Cursor-based pagination: get entries created before the cursor timestamp
            $stmt = $this->db->prepare(
                "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
                 FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 WHERE e.user_id = ? AND e.created_at < ? 
                 ORDER BY e.created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$userId, $before, $limit]);
        } else {
            // Initial load: get most recent entries
            $stmt = $this->db->prepare(
                "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
                 FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 WHERE e.user_id = ? 
                 ORDER BY e.created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        }
        
        return $stmt->fetchAll();
    }

    public function getAll(int $limit = 50, ?string $before = null, ?int $offset = null): array
    {
        if ($offset !== null) {
            // Offset-based pagination for admin
            $stmt = $this->db->prepare(
                "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
                 FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 ORDER BY e.created_at DESC 
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$limit, $offset]);
        } elseif ($before !== null) {
            // Cursor-based pagination: get entries created before the cursor timestamp
            $stmt = $this->db->prepare(
                "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
                 FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 WHERE e.created_at < ? 
                 ORDER BY e.created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$before, $limit]);
        } else {
            // Initial load: get most recent entries
            $stmt = $this->db->prepare(
                "SELECT e.*, u.name as user_name, u.email as user_email, u.gravatar_hash, u.photo_url 
                 FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 ORDER BY e.created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll();
    }

    public function update(int $id, string $text, ?array $preview = null): bool
    {
        if ($preview !== null) {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET text = ?, preview_url = ?, preview_title = ?, preview_description = ?, preview_image = ?, preview_site_name = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            
            return $stmt->execute([
                $text,
                $preview['url'] ?? null,
                $preview['title'] ?? null,
                $preview['description'] ?? null,
                $preview['image'] ?? null,
                $preview['site_name'] ?? null,
                $id
            ]);
        } else {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET text = ?, preview_url = NULL, preview_title = NULL, preview_description = NULL, preview_image = NULL, preview_site_name = NULL, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            
            return $stmt->execute([$text, $id]);
        }
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
