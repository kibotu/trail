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

    public function create(int $userId, string $text, ?array $preview = null, ?array $imageIds = null): int
    {
        $imageIdsJson = $imageIds ? json_encode($imageIds) : null;
        
        if ($preview !== null) {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, text, preview_url, preview_title, preview_description, preview_image, preview_site_name, preview_json, preview_source, image_ids) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $text,
                $preview['url'] ?? null,
                $preview['title'] ?? null,
                $preview['description'] ?? null,
                $preview['image'] ?? null,
                $preview['site_name'] ?? null,
                $preview['json'] ?? null,
                $preview['source'] ?? null,
                $imageIdsJson
            ]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, text, image_ids) 
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$userId, $text, $imageIdsJson]);
        }
        
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url 
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
                "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id 
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
                "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id 
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

    public function getAll(int $limit = 50, ?string $before = null, ?int $offset = null, ?int $excludeUserId = null, array $excludeEntryIds = []): array
    {
        // Build WHERE clause for filters
        $whereConditions = [];
        $params = [];
        
        if ($before !== null) {
            $whereConditions[] = "e.created_at < ?";
            $params[] = $before;
        }
        
        // Exclude muted users
        if ($excludeUserId !== null) {
            $whereConditions[] = "e.user_id NOT IN (
                SELECT muted_user_id FROM trail_muted_users WHERE muter_user_id = ?
            )";
            $params[] = $excludeUserId;
        }
        
        // Exclude hidden entries
        if (!empty($excludeEntryIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeEntryIds), '?'));
            $whereConditions[] = "e.id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeEntryIds);
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        if ($offset !== null) {
            // Offset-based pagination for admin
            $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id 
                    FROM {$this->table} e 
                    JOIN trail_users u ON e.user_id = u.id 
                    $whereClause
                    ORDER BY e.created_at DESC 
                    LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        } else {
            // Cursor-based pagination
            $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id 
                    FROM {$this->table} e 
                    JOIN trail_users u ON e.user_id = u.id 
                    $whereClause
                    ORDER BY e.created_at DESC 
                    LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function update(int $id, string $text, ?array $preview = null, ?array $imageIds = null): bool
    {
        $imageIdsJson = $imageIds ? json_encode($imageIds) : null;
        
        if ($preview !== null) {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET text = ?, preview_url = ?, preview_title = ?, preview_description = ?, preview_image = ?, preview_site_name = ?, preview_json = ?, preview_source = ?, image_ids = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            
            return $stmt->execute([
                $text,
                $preview['url'] ?? null,
                $preview['title'] ?? null,
                $preview['description'] ?? null,
                $preview['image'] ?? null,
                $preview['site_name'] ?? null,
                $preview['json'] ?? null,
                $preview['source'] ?? null,
                $imageIdsJson,
                $id
            ]);
        } else {
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                 SET text = ?, preview_url = NULL, preview_title = NULL, preview_description = NULL, preview_image = NULL, preview_site_name = NULL, preview_json = NULL, preview_source = NULL, image_ids = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            
            return $stmt->execute([$text, $imageIdsJson, $id]);
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
    
    /**
     * Get entry with image URLs
     */
    public function findByIdWithImages(int $id): ?array
    {
        $entry = $this->findById($id);
        if (!$entry) {
            return null;
        }
        
        return $this->attachImageUrls($entry);
    }
    
    /**
     * Attach image URLs to entry
     */
    private function attachImageUrls(array $entry): array
    {
        try {
            // Debug: Log if image_ids exists
            if (isset($entry['image_ids'])) {
                error_log("Entry {$entry['id']} has image_ids: " . $entry['image_ids']);
            }
            
            if (!empty($entry['image_ids'])) {
                $imageIds = json_decode($entry['image_ids'], true);
                error_log("Decoded image_ids for entry {$entry['id']}: " . json_encode($imageIds));
                
                if (is_array($imageIds) && !empty($imageIds)) {
                    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
                    $stmt = $this->db->prepare("
                        SELECT id, filename, user_id, width, height, file_size
                        FROM trail_images 
                        WHERE id IN ($placeholders)
                        ORDER BY FIELD(id, $placeholders)
                    ");
                    $stmt->execute(array_merge($imageIds, $imageIds));
                    $images = $stmt->fetchAll();
                    
                    error_log("Found " . count($images) . " images for entry {$entry['id']}");
                    
                    $entry['images'] = array_map(function($img) {
                        return [
                            'id' => $img['id'],
                            'url' => '/uploads/images/' . $img['user_id'] . '/' . $img['filename'],
                            'width' => $img['width'],
                            'height' => $img['height'],
                            'file_size' => $img['file_size']
                        ];
                    }, $images);
                }
            }
        } catch (\PDOException $e) {
            // Fallback if trail_images table doesn't exist yet
            error_log("attachImageUrls error (table may not exist): " . $e->getMessage());
        }
        
        return $entry;
    }
    
    /**
     * Get all entries with image URLs attached
     */
    public function getAllWithImages(int $limit = 50, ?string $before = null, ?int $offset = null, ?int $excludeUserId = null, array $excludeEntryIds = []): array
    {
        $entries = $this->getAll($limit, $before, $offset, $excludeUserId, $excludeEntryIds);
        return array_map([$this, 'attachImageUrls'], $entries);
    }
    
    /**
     * Get user entries with image URLs attached
     */
    public function getByUserWithImages(int $userId, int $limit = 20, ?string $before = null): array
    {
        $entries = $this->getByUser($userId, $limit, $before);
        return array_map([$this, 'attachImageUrls'], $entries);
    }
}
