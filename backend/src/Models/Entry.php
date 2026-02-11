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

    public function create(int $userId, string $text, ?int $urlPreviewId = null, ?array $imageIds = null, ?string $createdAt = null): int
    {
        $imageIdsJson = $imageIds ? json_encode($imageIds) : null;
        
        if ($createdAt !== null) {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, text, url_preview_id, image_ids, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $text, $urlPreviewId, $imageIdsJson, $createdAt, $createdAt]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (user_id, text, url_preview_id, image_ids) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $text, $urlPreviewId, $imageIdsJson]);
        }
        
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id, ?int $currentUserId = null): ?array
    {
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url,
                p.url as preview_url, p.title as preview_title, p.description as preview_description,
                p.image as preview_image, p.site_name as preview_site_name, p.json as preview_json, p.source as preview_source,
                COALESCE(clap_totals.total_claps, 0) as clap_count,
                COALESCE(comment_counts.comment_count, 0) as comment_count,
                COALESCE(view_counts.view_count, 0) as view_count";
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                 LEFT JOIN (
                     SELECT entry_id, SUM(clap_count) as total_claps
                     FROM trail_claps
                     GROUP BY entry_id
                 ) clap_totals ON e.id = clap_totals.entry_id
                 LEFT JOIN (
                     SELECT entry_id, COUNT(*) as comment_count
                     FROM trail_comments
                     GROUP BY entry_id
                 ) comment_counts ON e.id = comment_counts.entry_id
                 LEFT JOIN trail_view_counts view_counts 
                     ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_claps user_claps ON e.id = user_claps.entry_id AND user_claps.user_id = ?";
        }
        
        $sql .= " WHERE e.id = ?";
        
        $stmt = $this->db->prepare($sql);
        
        if ($currentUserId !== null) {
            $stmt->execute([$currentUserId, $id]);
        } else {
            $stmt->execute([$id]);
        }
        
        $entry = $stmt->fetch();
        
        return $entry ?: null;
    }

    public function getByUser(int $userId, int $limit = 20, ?string $before = null, ?int $currentUserId = null): array
    {
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id,
                p.url as preview_url, p.title as preview_title, p.description as preview_description,
                p.image as preview_image, p.site_name as preview_site_name, p.json as preview_json, p.source as preview_source,
                COALESCE(clap_totals.total_claps, 0) as clap_count,
                COALESCE(comment_counts.comment_count, 0) as comment_count,
                COALESCE(view_counts.view_count, 0) as view_count";
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                 LEFT JOIN (
                     SELECT entry_id, SUM(clap_count) as total_claps
                     FROM trail_claps
                     GROUP BY entry_id
                 ) clap_totals ON e.id = clap_totals.entry_id
                 LEFT JOIN (
                     SELECT entry_id, COUNT(*) as comment_count
                     FROM trail_comments
                     GROUP BY entry_id
                 ) comment_counts ON e.id = comment_counts.entry_id
                 LEFT JOIN trail_view_counts view_counts 
                     ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_claps user_claps ON e.id = user_claps.entry_id AND user_claps.user_id = ?";
        }
        
        $params = [];
        if ($currentUserId !== null) {
            $params[] = $currentUserId;
        }
        
        if ($before !== null) {
            $sql .= " WHERE e.user_id = ? AND e.created_at < ?";
            $params[] = $userId;
            $params[] = $before;
        } else {
            $sql .= " WHERE e.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY e.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function getAll(int $limit = 50, ?string $before = null, ?int $offset = null, ?int $excludeUserId = null, array $excludeEntryIds = [], ?int $currentUserId = null, ?string $sourceFilter = null): array
    {
        // Build SELECT with clap counts, comment counts, and view counts
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id,
                p.url as preview_url, p.title as preview_title, p.description as preview_description,
                p.image as preview_image, p.site_name as preview_site_name, p.json as preview_json, p.source as preview_source,
                COALESCE(clap_totals.total_claps, 0) as clap_count,
                COALESCE(comment_counts.comment_count, 0) as comment_count,
                COALESCE(view_counts.view_count, 0) as view_count";
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                 LEFT JOIN (
                     SELECT entry_id, SUM(clap_count) as total_claps
                     FROM trail_claps
                     GROUP BY entry_id
                 ) clap_totals ON e.id = clap_totals.entry_id
                 LEFT JOIN (
                     SELECT entry_id, COUNT(*) as comment_count
                     FROM trail_comments
                     GROUP BY entry_id
                 ) comment_counts ON e.id = comment_counts.entry_id
                 LEFT JOIN trail_view_counts view_counts 
                     ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_claps user_claps ON e.id = user_claps.entry_id AND user_claps.user_id = ?";
        }
        
        // Build WHERE clause for filters
        $whereConditions = [];
        $params = [];
        
        if ($currentUserId !== null) {
            $params[] = $currentUserId;
        }
        
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
        
        // Filter by preview source
        if ($sourceFilter !== null) {
            $whereConditions[] = "p.source = ?";
            $params[] = $sourceFilter;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $sql .= " $whereClause";
        
        if ($offset !== null) {
            // Offset-based pagination for admin
            $sql .= " ORDER BY e.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        } else {
            // Cursor-based pagination
            $sql .= " ORDER BY e.created_at DESC LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function update(int $id, string $text, ?int $urlPreviewId = null, ?array $imageIds = null): bool
    {
        $imageIdsJson = $imageIds ? json_encode($imageIds) : null;
        
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET text = ?, url_preview_id = ?, image_ids = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$text, $urlPreviewId, $imageIdsJson, $id]);
    }

    public function delete(int $id): bool
    {
        // Delete view counts for this entry
        $stmt = $this->db->prepare(
            "DELETE FROM trail_view_counts WHERE target_type = 'entry' AND target_id = ?"
        );
        $stmt->execute([$id]);
        
        // Delete raw views for this entry
        $stmt = $this->db->prepare(
            "DELETE FROM trail_views WHERE target_type = 'entry' AND target_id = ?"
        );
        $stmt->execute([$id]);
        
        // Delete claps for this entry
        $stmt = $this->db->prepare("DELETE FROM trail_claps WHERE entry_id = ?");
        $stmt->execute([$id]);
        
        // Get comment IDs for this entry
        $commentStmt = $this->db->prepare("SELECT id FROM trail_comments WHERE entry_id = ?");
        $commentStmt->execute([$id]);
        $commentIds = $commentStmt->fetchAll(\PDO::FETCH_COLUMN);
        
        if (!empty($commentIds)) {
            $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
            
            // Delete view counts for comments
            $stmt = $this->db->prepare(
                "DELETE FROM trail_view_counts WHERE target_type = 'comment' AND target_id IN ($placeholders)"
            );
            $stmt->execute($commentIds);
            
            // Delete raw views for comments
            $stmt = $this->db->prepare(
                "DELETE FROM trail_views WHERE target_type = 'comment' AND target_id IN ($placeholders)"
            );
            $stmt->execute($commentIds);
        }
        
        // Delete comments for this entry
        $stmt = $this->db->prepare("DELETE FROM trail_comments WHERE entry_id = ?");
        $stmt->execute([$id]);
        
        // Finally, delete the entry
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

    /**
     * Count entries with preview URLs (links) by a specific user
     * Returns the number of entries with url_preview_id not null
     */
    public function countLinksWithPreviewByUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} 
             WHERE user_id = ? AND url_preview_id IS NOT NULL"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Delete all entries by a specific user
     * Also cleans up associated views, view counts, and claps
     * Returns the number of entries deleted
     */
    public function deleteByUser(int $userId): int
    {
        // First, get all entry IDs for this user
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        $entryIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        if (empty($entryIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        
        // Delete view counts for these entries
        $stmt = $this->db->prepare(
            "DELETE FROM trail_view_counts WHERE target_type = 'entry' AND target_id IN ($placeholders)"
        );
        $stmt->execute($entryIds);
        
        // Delete raw views for these entries
        $stmt = $this->db->prepare(
            "DELETE FROM trail_views WHERE target_type = 'entry' AND target_id IN ($placeholders)"
        );
        $stmt->execute($entryIds);
        
        // Delete claps for these entries
        $stmt = $this->db->prepare(
            "DELETE FROM trail_claps WHERE entry_id IN ($placeholders)"
        );
        $stmt->execute($entryIds);
        
        // Delete comments for these entries (and their views)
        $commentStmt = $this->db->prepare(
            "SELECT id FROM trail_comments WHERE entry_id IN ($placeholders)"
        );
        $commentStmt->execute($entryIds);
        $commentIds = $commentStmt->fetchAll(\PDO::FETCH_COLUMN);
        
        if (!empty($commentIds)) {
            $commentPlaceholders = implode(',', array_fill(0, count($commentIds), '?'));
            
            // Delete view counts for comments
            $stmt = $this->db->prepare(
                "DELETE FROM trail_view_counts WHERE target_type = 'comment' AND target_id IN ($commentPlaceholders)"
            );
            $stmt->execute($commentIds);
            
            // Delete raw views for comments
            $stmt = $this->db->prepare(
                "DELETE FROM trail_views WHERE target_type = 'comment' AND target_id IN ($commentPlaceholders)"
            );
            $stmt->execute($commentIds);
            
            // Delete the comments themselves
            $stmt = $this->db->prepare(
                "DELETE FROM trail_comments WHERE entry_id IN ($placeholders)"
            );
            $stmt->execute($entryIds);
        }
        
        // Finally, delete the entries
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return count($entryIds);
    }

    /**
     * Get the most recent entry by a specific user
     * Returns null if user has no entries
     */
    public function getLatestByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $entry = $stmt->fetch();
        
        return $entry ?: null;
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
    public function findByIdWithImages(int $id, ?int $currentUserId = null): ?array
    {
        $entry = $this->findById($id, $currentUserId);
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
    public function getAllWithImages(int $limit = 50, ?string $before = null, ?int $offset = null, ?int $excludeUserId = null, array $excludeEntryIds = [], ?int $currentUserId = null, ?string $sourceFilter = null): array
    {
        $entries = $this->getAll($limit, $before, $offset, $excludeUserId, $excludeEntryIds, $currentUserId, $sourceFilter);
        return array_map([$this, 'attachImageUrls'], $entries);
    }
    
    /**
     * Get user entries with image URLs attached
     */
    public function getByUserWithImages(int $userId, int $limit = 20, ?string $before = null, ?int $currentUserId = null): array
    {
        $entries = $this->getByUser($userId, $limit, $before, $currentUserId);
        return array_map([$this, 'attachImageUrls'], $entries);
    }

    /**
     * Search all entries with FULLTEXT or LIKE fallback
     * 
     * @param string $searchQuery Search query (already sanitized)
     * @param int $limit Maximum number of entries to return
     * @param string|null $before Cursor for pagination (created_at timestamp)
     * @param int|null $excludeUserId User ID to exclude muted users for
     * @param array $excludeEntryIds Entry IDs to exclude (hidden entries)
     * @param int|null $currentUserId Current user ID for clap counts
     * @return array Array of entries matching search query
     */
    public function searchAll(string $searchQuery, int $limit = 50, ?string $before = null, ?int $excludeUserId = null, array $excludeEntryIds = [], ?int $currentUserId = null): array
    {
        // Use FULLTEXT for queries >= 4 chars, LIKE for shorter
        $useFulltext = mb_strlen($searchQuery) >= 4;
        
        // Build SELECT with clap counts, comment counts, view counts, and relevance score
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id,
                p.url as preview_url, p.title as preview_title, p.description as preview_description,
                p.image as preview_image, p.site_name as preview_site_name, p.json as preview_json, p.source as preview_source,
                COALESCE(clap_totals.total_claps, 0) as clap_count,
                COALESCE(comment_counts.comment_count, 0) as comment_count,
                COALESCE(view_counts.view_count, 0) as view_count";
        
        // Params must be added in the exact order of ? placeholders in the SQL
        $params = [];
        
        if ($useFulltext) {
            $sql .= ", (MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) + COALESCE(MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE), 0)) as relevance";
            $params[] = $searchQuery; // Bind for SELECT relevance score (entries)
            $params[] = $searchQuery; // Bind for SELECT relevance score (previews)
        }
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                 LEFT JOIN (
                     SELECT entry_id, SUM(clap_count) as total_claps
                     FROM trail_claps
                     GROUP BY entry_id
                 ) clap_totals ON e.id = clap_totals.entry_id
                 LEFT JOIN (
                     SELECT entry_id, COUNT(*) as comment_count
                     FROM trail_comments
                     GROUP BY entry_id
                 ) comment_counts ON e.id = comment_counts.entry_id
                 LEFT JOIN trail_view_counts view_counts 
                     ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_claps user_claps ON e.id = user_claps.entry_id AND user_claps.user_id = ?";
            $params[] = $currentUserId; // Bind for JOIN
        }
        
        // Build WHERE clause
        $whereConditions = [];
        
        // Add search condition
        if ($useFulltext) {
            // FULLTEXT + LIKE hybrid for reliable matching with relevance ranking
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $whereConditions[] = "(MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $searchQuery;
            $params[] = $searchQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        } else {
            // LIKE search for short queries
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $whereConditions[] = "(e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        }
        
        // Add cursor-based pagination
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
        $sql .= " $whereClause";
        
        // Order by created_at (most recent first) - relevance is not used for sorting
        $sql .= " ORDER BY e.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Search entries by specific user with FULLTEXT or LIKE fallback
     * 
     * @param int $userId User ID to search within
     * @param string $searchQuery Search query (already sanitized)
     * @param int $limit Maximum number of entries to return
     * @param string|null $before Cursor for pagination (created_at timestamp)
     * @param int|null $currentUserId Current user ID for clap counts
     * @return array Array of entries matching search query for this user
     */
    public function searchByUser(int $userId, string $searchQuery, int $limit = 20, ?string $before = null, ?int $currentUserId = null): array
    {
        // Use FULLTEXT for queries >= 4 chars, LIKE for shorter
        $useFulltext = mb_strlen($searchQuery) >= 4;
        
        // Build SELECT with clap counts, comment counts, view counts, and relevance score
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id,
                p.url as preview_url, p.title as preview_title, p.description as preview_description,
                p.image as preview_image, p.site_name as preview_site_name, p.json as preview_json, p.source as preview_source,
                COALESCE(clap_totals.total_claps, 0) as clap_count,
                COALESCE(comment_counts.comment_count, 0) as comment_count,
                COALESCE(view_counts.view_count, 0) as view_count";
        
        // Params must be added in the exact order of ? placeholders in the SQL
        $params = [];
        
        if ($useFulltext) {
            $sql .= ", (MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) + COALESCE(MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE), 0)) as relevance";
            $params[] = $searchQuery; // Bind for SELECT relevance score (entries)
            $params[] = $searchQuery; // Bind for SELECT relevance score (previews)
        }
        
        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }
        
        $sql .= " FROM {$this->table} e 
                 JOIN trail_users u ON e.user_id = u.id 
                 LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                 LEFT JOIN (
                     SELECT entry_id, SUM(clap_count) as total_claps
                     FROM trail_claps
                     GROUP BY entry_id
                 ) clap_totals ON e.id = clap_totals.entry_id
                 LEFT JOIN (
                     SELECT entry_id, COUNT(*) as comment_count
                     FROM trail_comments
                     GROUP BY entry_id
                 ) comment_counts ON e.id = comment_counts.entry_id
                 LEFT JOIN trail_view_counts view_counts 
                     ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id";
        
        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_claps user_claps ON e.id = user_claps.entry_id AND user_claps.user_id = ?";
            $params[] = $currentUserId; // Bind for JOIN
        }
        
        // Build WHERE clause
        $whereConditions = [];
        
        // Filter by user
        $whereConditions[] = "e.user_id = ?";
        $params[] = $userId;
        
        // Add search condition
        if ($useFulltext) {
            // FULLTEXT + LIKE hybrid for reliable matching with relevance ranking
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $whereConditions[] = "(MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $searchQuery;
            $params[] = $searchQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        } else {
            // LIKE search for short queries
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $whereConditions[] = "(e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        }
        
        // Add cursor-based pagination
        if ($before !== null) {
            $whereConditions[] = "e.created_at < ?";
            $params[] = $before;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        $sql .= " $whereClause";
        
        // Order by created_at (most recent first) - relevance is not used for sorting
        $sql .= " ORDER BY e.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Search all entries with images attached
     */
    public function searchAllWithImages(string $searchQuery, int $limit = 50, ?string $before = null, ?int $excludeUserId = null, array $excludeEntryIds = [], ?int $currentUserId = null): array
    {
        $entries = $this->searchAll($searchQuery, $limit, $before, $excludeUserId, $excludeEntryIds, $currentUserId);
        return array_map([$this, 'attachImageUrls'], $entries);
    }

    /**
     * Search user entries with images attached
     */
    public function searchByUserWithImages(int $userId, string $searchQuery, int $limit = 20, ?string $before = null, ?int $currentUserId = null): array
    {
        $entries = $this->searchByUser($userId, $searchQuery, $limit, $before, $currentUserId);
        return array_map([$this, 'attachImageUrls'], $entries);
    }

    /**
     * Count all entries matching a search query
     * 
     * @param string $searchQuery Search query (already sanitized)
     * @param int|null $excludeUserId User ID to exclude muted users for
     * @param array $excludeEntryIds Entry IDs to exclude (hidden entries)
     * @return int Total count of matching entries
     */
    public function countSearchAll(string $searchQuery, ?int $excludeUserId = null, array $excludeEntryIds = []): int
    {
        // Use FULLTEXT for queries >= 4 chars, LIKE for shorter
        $useFulltext = mb_strlen($searchQuery) >= 4;
        
        $sql = "SELECT COUNT(DISTINCT e.id) as total
                FROM trail_entries e
                JOIN trail_users u ON e.user_id = u.id
                LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id";
        
        $params = [];
        $whereConditions = [];
        
        // Add search condition
        if ($useFulltext) {
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $whereConditions[] = "(MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $searchQuery;
            $params[] = $searchQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        } else {
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $whereConditions[] = "(e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        }
        
        // Exclude muted users if user is authenticated
        if ($excludeUserId !== null) {
            $whereConditions[] = "e.user_id NOT IN (SELECT muted_user_id FROM trail_mutes WHERE user_id = ?)";
            $params[] = $excludeUserId;
        }
        
        // Exclude hidden entries
        if (!empty($excludeEntryIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeEntryIds), '?'));
            $whereConditions[] = "e.id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeEntryIds);
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Count entries by specific user matching a search query
     * 
     * @param int $userId User ID to search within
     * @param string $searchQuery Search query (already sanitized)
     * @return int Total count of matching entries for this user
     */
    public function countSearchByUser(int $userId, string $searchQuery): int
    {
        // Use FULLTEXT for queries >= 4 chars, LIKE for shorter
        $useFulltext = mb_strlen($searchQuery) >= 4;
        
        $sql = "SELECT COUNT(DISTINCT e.id) as total
                FROM trail_entries e
                JOIN trail_users u ON e.user_id = u.id
                LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                WHERE e.user_id = ?";
        
        $params = [$userId];
        
        // Add search condition
        if ($useFulltext) {
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $sql .= " AND (MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $searchQuery;
            $params[] = $searchQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        } else {
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            $sql .= " AND (e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
            $params[] = $likeQuery;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Extract URLs from text using regex
     * 
     * @param string $text Entry text
     * @return array Array of unique URLs found in the text
     */
    private static function extractUrlsFromText(string $text): array
    {
        $urls = [];
        if (preg_match_all('#https?://[^\s<>"\')\]]+#i', $text, $matches)) {
            // Normalize: trim trailing punctuation that's likely not part of the URL
            foreach ($matches[0] as $url) {
                $url = rtrim($url, '.,;:!?)>');
                $urls[] = $url;
            }
        }
        return array_unique($urls);
    }

    /**
     * Get statistics about duplicate entries (same user, same content)
     * 
     * @return array Stats about text, url, and text_url duplicate groups
     */
    public function getDuplicateStats(): array
    {
        // Count text duplicate groups
        $stmt = $this->db->query(
            "SELECT COUNT(*) as group_count, COALESCE(SUM(cnt), 0) as entry_count FROM (
                SELECT COUNT(*) as cnt
                FROM {$this->table}
                WHERE text IS NOT NULL AND text != ''
                GROUP BY user_id, text
                HAVING COUNT(*) > 1
            ) as text_dupes"
        );
        $textStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Count URL preview duplicate groups
        $stmt = $this->db->query(
            "SELECT COUNT(*) as group_count, COALESCE(SUM(cnt), 0) as entry_count FROM (
                SELECT COUNT(*) as cnt
                FROM {$this->table}
                WHERE url_preview_id IS NOT NULL
                GROUP BY user_id, url_preview_id
                HAVING COUNT(*) > 1
            ) as url_dupes"
        );
        $urlStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Count text-URL duplicate groups (same URL in text, different text)
        $textUrlStats = $this->getTextUrlDuplicateStats();

        $textGroups = (int) ($textStats['group_count'] ?? 0);
        $urlGroups = (int) ($urlStats['group_count'] ?? 0);
        $textUrlGroups = $textUrlStats['group_count'];
        $textEntries = (int) ($textStats['entry_count'] ?? 0);
        $urlEntries = (int) ($urlStats['entry_count'] ?? 0);
        $textUrlEntries = $textUrlStats['entry_count'];

        return [
            'text_duplicate_groups' => $textGroups,
            'url_duplicate_groups' => $urlGroups,
            'text_url_duplicate_groups' => $textUrlGroups,
            'text_duplicate_entries' => $textEntries,
            'url_duplicate_entries' => $urlEntries,
            'text_url_duplicate_entries' => $textUrlEntries,
            'total_duplicate_groups' => $textGroups + $urlGroups + $textUrlGroups,
            'total_extra_entries' => ($textEntries - $textGroups) + ($urlEntries - $urlGroups) + ($textUrlEntries - $textUrlGroups),
        ];
    }

    /**
     * Get stats for text-URL duplicates (entries by same user containing the same URL in text)
     * This is done in PHP because URL extraction requires regex parsing
     */
    private function getTextUrlDuplicateStats(): array
    {
        // Fetch entries that contain URLs in their text
        $stmt = $this->db->query(
            "SELECT id, user_id, text FROM {$this->table}
             WHERE text IS NOT NULL AND text REGEXP 'https?://'"
        );
        $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group by (user_id, url)
        $urlMap = []; // key: "userId:url" => [entry_ids]
        foreach ($entries as $entry) {
            $urls = self::extractUrlsFromText($entry['text']);
            foreach ($urls as $url) {
                $key = $entry['user_id'] . ':' . $url;
                $urlMap[$key][] = (int) $entry['id'];
            }
        }

        // Filter to groups with >1 entry, exclude groups already covered by exact text match
        $groupCount = 0;
        $entryCount = 0;
        foreach ($urlMap as $entryIds) {
            if (count($entryIds) > 1) {
                // Only count unique entry IDs (an entry with 2 URLs shouldn't be counted twice per group)
                $uniqueIds = array_unique($entryIds);
                if (count($uniqueIds) > 1) {
                    $groupCount++;
                    $entryCount += count($uniqueIds);
                }
            }
        }

        return ['group_count' => $groupCount, 'entry_count' => $entryCount];
    }

    /**
     * Find duplicate entry groups (same user, same content)
     * 
     * @param int $limit Max number of duplicate groups to return
     * @param int $offset Offset for pagination
     * @param string $matchType Filter: 'all', 'text', 'url', 'text_url'
     * @return array ['groups' => [...], 'total_groups' => int]
     */
    public function getDuplicateGroups(int $limit = 20, int $offset = 0, string $matchType = 'all'): array
    {
        $groups = [];
        $totalGroups = 0;

        if ($matchType === 'all' || $matchType === 'text') {
            $result = $this->getTextDuplicateGroups($limit, $offset);
            $groups = array_merge($groups, $result['groups']);
            $totalGroups += $result['total'];
        }

        if ($matchType === 'all' || $matchType === 'url') {
            $remainingLimit = $limit - count($groups);
            if ($remainingLimit > 0 || $matchType === 'url') {
                $urlOffset = $matchType === 'url' ? $offset : max(0, $offset - $totalGroups);
                $urlLimit = $matchType === 'url' ? $limit : $remainingLimit;
                if ($urlLimit > 0) {
                    $result = $this->getUrlDuplicateGroups($urlLimit, max(0, $urlOffset));
                    $groups = array_merge($groups, $result['groups']);
                    $totalGroups += $result['total'];
                }
            }
        }

        if ($matchType === 'all' || $matchType === 'text_url') {
            $remainingLimit = $limit - count($groups);
            if ($remainingLimit > 0 || $matchType === 'text_url') {
                $textUrlOffset = $matchType === 'text_url' ? $offset : max(0, $offset - $totalGroups);
                $textUrlLimit = $matchType === 'text_url' ? $limit : $remainingLimit;
                if ($textUrlLimit > 0) {
                    $result = $this->getTextUrlDuplicateGroups($textUrlLimit, max(0, $textUrlOffset));
                    $groups = array_merge($groups, $result['groups']);
                    $totalGroups += $result['total'];
                }
            }
        }

        // Sort by duplicate count descending
        usort($groups, function ($a, $b) {
            return $b['dupe_count'] - $a['dupe_count'];
        });

        return [
            'groups' => array_slice($groups, 0, $limit),
            'total_groups' => $totalGroups,
        ];
    }

    /**
     * Find text-based duplicate groups
     */
    private function getTextDuplicateGroups(int $limit, int $offset): array
    {
        // Count total groups
        $countStmt = $this->db->query(
            "SELECT COUNT(*) as total FROM (
                SELECT 1 FROM {$this->table}
                WHERE text IS NOT NULL AND text != ''
                GROUP BY user_id, text
                HAVING COUNT(*) > 1
            ) as t"
        );
        $total = (int) $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        if ($total === 0) {
            return ['groups' => [], 'total' => 0];
        }

        // Get duplicate groups
        $stmt = $this->db->prepare(
            "SELECT user_id, text, COUNT(*) as dupe_count,
                    MIN(created_at) as first_posted, MAX(created_at) as last_posted,
                    GROUP_CONCAT(id ORDER BY created_at ASC) as entry_ids
             FROM {$this->table}
             WHERE text IS NOT NULL AND text != ''
             GROUP BY user_id, text
             HAVING COUNT(*) > 1
             ORDER BY dupe_count DESC, last_posted DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $rawGroups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $groups = [];
        foreach ($rawGroups as $raw) {
            $entryIds = array_map('intval', explode(',', $raw['entry_ids']));
            $entries = $this->getEntriesByIds($entryIds);

            $groups[] = [
                'match_type' => 'text',
                'user_id' => (int) $raw['user_id'],
                'matched_value' => mb_substr($raw['text'], 0, 200),
                'dupe_count' => (int) $raw['dupe_count'],
                'first_posted' => $raw['first_posted'],
                'last_posted' => $raw['last_posted'],
                'entries' => $entries,
            ];
        }

        return ['groups' => $groups, 'total' => $total];
    }

    /**
     * Find URL-based duplicate groups
     */
    private function getUrlDuplicateGroups(int $limit, int $offset): array
    {
        // Count total groups
        $countStmt = $this->db->query(
            "SELECT COUNT(*) as total FROM (
                SELECT 1 FROM {$this->table}
                WHERE url_preview_id IS NOT NULL
                GROUP BY user_id, url_preview_id
                HAVING COUNT(*) > 1
            ) as t"
        );
        $total = (int) $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        if ($total === 0) {
            return ['groups' => [], 'total' => 0];
        }

        // Get duplicate groups
        $stmt = $this->db->prepare(
            "SELECT e.user_id, e.url_preview_id, COUNT(*) as dupe_count,
                    MIN(e.created_at) as first_posted, MAX(e.created_at) as last_posted,
                    GROUP_CONCAT(e.id ORDER BY e.created_at ASC) as entry_ids,
                    p.url as preview_url, p.title as preview_title
             FROM {$this->table} e
             LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
             WHERE e.url_preview_id IS NOT NULL
             GROUP BY e.user_id, e.url_preview_id
             HAVING COUNT(*) > 1
             ORDER BY dupe_count DESC, last_posted DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $rawGroups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $groups = [];
        foreach ($rawGroups as $raw) {
            $entryIds = array_map('intval', explode(',', $raw['entry_ids']));
            $entries = $this->getEntriesByIds($entryIds);

            $matchedValue = $raw['preview_title'] ?: $raw['preview_url'] ?: 'URL #' . $raw['url_preview_id'];

            $groups[] = [
                'match_type' => 'url',
                'user_id' => (int) $raw['user_id'],
                'matched_value' => $matchedValue,
                'url_preview_id' => (int) $raw['url_preview_id'],
                'dupe_count' => (int) $raw['dupe_count'],
                'first_posted' => $raw['first_posted'],
                'last_posted' => $raw['last_posted'],
                'entries' => $entries,
            ];
        }

        return ['groups' => $groups, 'total' => $total];
    }

    /**
     * Find entries by the same user that contain the same URL in their text field
     * Uses PHP regex extraction since SQL can't easily parse URLs from freetext
     */
    private function getTextUrlDuplicateGroups(int $limit, int $offset): array
    {
        // Fetch entries that contain URLs in their text
        $stmt = $this->db->query(
            "SELECT id, user_id, text, created_at FROM {$this->table}
             WHERE text IS NOT NULL AND text REGEXP 'https?://'
             ORDER BY created_at ASC"
        );
        $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group by (user_id, url_in_text)
        $urlMap = []; // key: "userId:url" => ['entry_ids' => [...], 'url' => ..., 'user_id' => ...]
        foreach ($entries as $entry) {
            $urls = self::extractUrlsFromText($entry['text']);
            foreach ($urls as $url) {
                $key = $entry['user_id'] . ':' . $url;
                if (!isset($urlMap[$key])) {
                    $urlMap[$key] = [
                        'user_id' => (int) $entry['user_id'],
                        'url' => $url,
                        'entry_ids' => [],
                        'first_posted' => $entry['created_at'],
                        'last_posted' => $entry['created_at'],
                    ];
                }
                // Avoid adding the same entry twice (if it has the same URL repeated in text)
                if (!in_array((int) $entry['id'], $urlMap[$key]['entry_ids'], true)) {
                    $urlMap[$key]['entry_ids'][] = (int) $entry['id'];
                }
                $urlMap[$key]['last_posted'] = $entry['created_at'];
            }
        }

        // Filter to only groups with >1 unique entry
        $duplicateGroups = [];
        foreach ($urlMap as $data) {
            if (count($data['entry_ids']) > 1) {
                $duplicateGroups[] = $data;
            }
        }

        // Sort by count descending, then by last_posted descending
        usort($duplicateGroups, function ($a, $b) {
            $countDiff = count($b['entry_ids']) - count($a['entry_ids']);
            return $countDiff !== 0 ? $countDiff : strcmp($b['last_posted'], $a['last_posted']);
        });

        $total = count($duplicateGroups);

        // Apply pagination
        $paged = array_slice($duplicateGroups, $offset, $limit);

        // Hydrate with full entry details
        $groups = [];
        foreach ($paged as $data) {
            $entryDetails = $this->getEntriesByIds($data['entry_ids']);

            $groups[] = [
                'match_type' => 'text_url',
                'user_id' => $data['user_id'],
                'matched_value' => $data['url'],
                'dupe_count' => count($data['entry_ids']),
                'first_posted' => $data['first_posted'],
                'last_posted' => $data['last_posted'],
                'entries' => $entryDetails,
            ];
        }

        return ['groups' => $groups, 'total' => $total];
    }

    /**
     * Fetch full entry details for a list of entry IDs
     * Used by duplicate detection to hydrate grouped entries
     * 
     * @param array $entryIds Array of entry IDs
     * @return array Array of entry details with user info, preview data, engagement stats
     */
    private function getEntriesByIds(array $entryIds): array
    {
        if (empty($entryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));

        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, 
                       u.gravatar_hash, u.photo_url,
                       p.url as preview_url, p.title as preview_title, p.description as preview_description,
                       p.image as preview_image, p.site_name as preview_site_name, p.source as preview_source,
                       COALESCE(clap_totals.total_claps, 0) as clap_count,
                       COALESCE(comment_counts.comment_count, 0) as comment_count,
                       COALESCE(view_counts.view_count, 0) as view_count
                FROM {$this->table} e
                JOIN trail_users u ON e.user_id = u.id
                LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                LEFT JOIN (
                    SELECT entry_id, SUM(clap_count) as total_claps
                    FROM trail_claps
                    GROUP BY entry_id
                ) clap_totals ON e.id = clap_totals.entry_id
                LEFT JOIN (
                    SELECT entry_id, COUNT(*) as comment_count
                    FROM trail_comments
                    GROUP BY entry_id
                ) comment_counts ON e.id = comment_counts.entry_id
                LEFT JOIN trail_view_counts view_counts 
                    ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id
                WHERE e.id IN ($placeholders)
                ORDER BY FIELD(e.id, $placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($entryIds, $entryIds));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete duplicate entries from a group, keeping one entry
     * 
     * @param array $entryIds All entry IDs in the duplicate group
     * @param string $keep 'oldest' keeps the first entry, 'newest' keeps the last
     * @return int Number of entries deleted
     */
    public function deleteDuplicates(array $entryIds, string $keep = 'oldest'): int
    {
        if (count($entryIds) < 2) {
            return 0;
        }

        // Fetch entries to determine which to keep
        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, created_at FROM {$this->table} WHERE id IN ($placeholders) ORDER BY created_at ASC"
        );
        $stmt->execute($entryIds);
        $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($entries) < 2) {
            return 0;
        }

        // Determine which entry to keep
        $keepId = $keep === 'newest'
            ? (int) $entries[count($entries) - 1]['id']
            : (int) $entries[0]['id'];

        $deleted = 0;
        foreach ($entries as $entry) {
            if ((int) $entry['id'] !== $keepId) {
                if ($this->delete((int) $entry['id'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
