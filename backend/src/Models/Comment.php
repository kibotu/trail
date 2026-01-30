<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Comment
{
    private PDO $db;
    private string $table = 'trail_comments';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $entryId, int $userId, string $text, ?array $imageIds = null): int
    {
        $imageIdsJson = $imageIds ? json_encode($imageIds) : null;
        
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (entry_id, user_id, text, image_ids) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$entryId, $userId, $text, $imageIdsJson]);
        
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id, ?int $currentUserId = null): ?array
    {
        $sql = "SELECT c.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url,
                COALESCE(clap_totals.total_claps, 0) as clap_count";
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} c 
                 JOIN trail_users u ON c.user_id = u.id 
                 LEFT JOIN (
                     SELECT comment_id, SUM(clap_count) as total_claps
                     FROM trail_comment_claps
                     GROUP BY comment_id
                 ) clap_totals ON c.id = clap_totals.comment_id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_comment_claps user_claps ON c.id = user_claps.comment_id AND user_claps.user_id = ?";
        }
        
        $sql .= " WHERE c.id = ?";
        
        $stmt = $this->db->prepare($sql);
        
        if ($currentUserId !== null) {
            $stmt->execute([$currentUserId, $id]);
        } else {
            $stmt->execute([$id]);
        }
        
        $comment = $stmt->fetch();
        
        return $comment ?: null;
    }

    public function getByEntry(int $entryId, int $limit = 50, ?string $before = null, ?int $currentUserId = null, array $excludeCommentIds = []): array
    {
        $sql = "SELECT c.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id,
                COALESCE(clap_totals.total_claps, 0) as clap_count";
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} c 
                 JOIN trail_users u ON c.user_id = u.id 
                 LEFT JOIN (
                     SELECT comment_id, SUM(clap_count) as total_claps
                     FROM trail_comment_claps
                     GROUP BY comment_id
                 ) clap_totals ON c.id = clap_totals.comment_id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_comment_claps user_claps ON c.id = user_claps.comment_id AND user_claps.user_id = ?";
        }
        
        $whereConditions = ["c.entry_id = ?"];
        $params = [];
        
        if ($currentUserId !== null) {
            $params[] = $currentUserId;
        }
        
        $params[] = $entryId;
        
        if ($before !== null) {
            $whereConditions[] = "c.created_at < ?";
            $params[] = $before;
        }
        
        // Exclude hidden comments
        if (!empty($excludeCommentIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeCommentIds), '?'));
            $whereConditions[] = "c.id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeCommentIds);
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        $sql .= " $whereClause";
        
        $sql .= " ORDER BY c.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function update(int $id, string $text, ?array $imageIds = null): bool
    {
        $imageIdsJson = $imageIds ? json_encode($imageIds) : null;
        
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET text = ?, image_ids = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$text, $imageIdsJson, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countByEntry(int $entryId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE entry_id = ?");
        $stmt->execute([$entryId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Check if a user can modify a comment (creator or admin)
     */
    public function canModify(int $commentId, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        $stmt = $this->db->prepare("SELECT user_id FROM {$this->table} WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            return false;
        }

        return (int) $comment['user_id'] === $userId;
    }
    
    /**
     * Get comment with image URLs
     */
    public function findByIdWithImages(int $id, ?int $currentUserId = null): ?array
    {
        $comment = $this->findById($id, $currentUserId);
        if (!$comment) {
            return null;
        }
        
        return $this->attachImageUrls($comment);
    }
    
    /**
     * Attach image URLs to comment
     */
    private function attachImageUrls(array $comment): array
    {
        try {
            if (!empty($comment['image_ids'])) {
                $imageIds = json_decode($comment['image_ids'], true);
                
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
                    
                    $comment['images'] = array_map(function($img) {
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
            error_log("attachImageUrls error: " . $e->getMessage());
        }
        
        return $comment;
    }
    
    /**
     * Get comments for entry with image URLs attached
     */
    public function getByEntryWithImages(int $entryId, int $limit = 50, ?string $before = null, ?int $currentUserId = null, array $excludeCommentIds = []): array
    {
        $comments = $this->getByEntry($entryId, $limit, $before, $currentUserId, $excludeCommentIds);
        return array_map([$this, 'attachImageUrls'], $comments);
    }

    /**
     * Get entry ID for a comment
     */
    public function getEntryId(int $commentId): ?int
    {
        $stmt = $this->db->prepare("SELECT entry_id FROM {$this->table} WHERE id = ?");
        $stmt->execute([$commentId]);
        $result = $stmt->fetch();
        
        return $result ? (int) $result['entry_id'] : null;
    }
}
