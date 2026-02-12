<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

/**
 * UrlPreview Model - Manages cached URL preview data
 * 
 * This model handles the trail_url_previews table which caches
 * link preview metadata (title, description, image, etc.) by URL.
 * This prevents redundant API calls when the same URL is posted multiple times.
 */
class UrlPreview
{
    private PDO $db;
    private string $table = 'trail_url_previews';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate SHA-256 hash of a URL for lookup
     * 
     * @param string $url The URL to hash
     * @return string 64-character hex hash
     */
    public static function hashUrl(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Find a URL preview by its hash
     * 
     * @param string $urlHash SHA-256 hash of the normalized URL
     * @return array|null Preview data or null if not found
     */
    public function findByUrlHash(string $urlHash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE url_hash = ?"
        );
        $stmt->execute([$urlHash]);
        $preview = $stmt->fetch();
        
        return $preview ?: null;
    }

    /**
     * Find a URL preview by ID
     * 
     * @param int $id Preview ID
     * @return array|null Preview data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = ?"
        );
        $stmt->execute([$id]);
        $preview = $stmt->fetch();
        
        return $preview ?: null;
    }

    /**
     * Create a new URL preview entry
     * 
     * @param string $url The normalized URL
     * @param array $data Preview data with keys: title, description, image, site_name, json, source
     * @return int The ID of the created preview
     */
    public function create(string $url, array $data): int
    {
        $urlHash = self::hashUrl($url);
        
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (url, url_hash, title, description, image, site_name, json, source, fetched_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        
        $stmt->execute([
            $url,
            $urlHash,
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['image'] ?? null,
            $data['site_name'] ?? null,
            $data['json'] ?? null,
            $data['source'] ?? null,
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing URL preview
     * 
     * @param int $id Preview ID
     * @param array $data Preview data to update
     * @return bool True on success
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET title = ?, description = ?, image = ?, site_name = ?, json = ?, source = ?, fetched_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['image'] ?? null,
            $data['site_name'] ?? null,
            $data['json'] ?? null,
            $data['source'] ?? null,
            $id
        ]);
    }

    /**
     * Delete a URL preview
     * 
     * @param int $id Preview ID
     * @return bool True on success
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Count total URL previews in cache
     * 
     * @return int Total count
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table}");
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Get previews older than a certain date (for cache cleanup)
     * 
     * @param string $olderThan Date string (e.g., '2024-01-01')
     * @param int $limit Maximum number to return
     * @return array Array of preview records
     */
    public function getStale(string $olderThan, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE fetched_at < ? 
             ORDER BY fetched_at ASC 
             LIMIT ?"
        );
        $stmt->execute([$olderThan, $limit]);
        
        return $stmt->fetchAll();
    }

    /**
     * Update URL and recalculate hash atomically
     * Used when resolving short URLs to their final destinations
     * 
     * @param int $id Preview ID
     * @param string $newUrl The resolved final URL
     * @return bool True on success
     */
    public function updateUrlAndHash(int $id, string $newUrl): bool
    {
        $newHash = self::hashUrl($newUrl);
        
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET url = ?, url_hash = ?, short_link_resolve_failed_at = NULL, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$newUrl, $newHash, $id]);
    }

    /**
     * Update image URL
     * Used when resolving short image URLs
     * 
     * @param int $id Preview ID
     * @param string $newImageUrl The resolved image URL
     * @return bool True on success
     */
    public function updateImage(int $id, string $newImageUrl): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET image = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$newImageUrl, $id]);
    }

    /**
     * Mark a short link resolution as failed
     * Sets the timestamp for retry ordering
     * 
     * @param int $id Preview ID
     * @return bool True on success
     */
    public function markResolveFailed(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET short_link_resolve_failed_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$id]);
    }

    /**
     * Clear the failed timestamp (on successful resolution or no redirect needed)
     * 
     * @param int $id Preview ID
     * @return bool True on success
     */
    public function clearResolveFailed(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET short_link_resolve_failed_at = NULL 
             WHERE id = ?"
        );
        
        return $stmt->execute([$id]);
    }

    /**
     * Get short links to resolve, sorted by priority:
     * 1. Not tried yet (failed_at IS NULL) - first
     * 2. Oldest failures (failed_at ASC) - then
     * 
     * @param array $shortenerDomains List of shortener domains to match
     * @param int $limit Maximum number to return
     * @return array Array of preview records
     */
    public function getShortLinksToResolve(array $shortenerDomains, int $limit = 50): array
    {
        if (empty($shortenerDomains)) {
            return [];
        }

        // Build LIKE conditions for each shortener domain
        $likeConditions = [];
        $params = [];
        foreach ($shortenerDomains as $domain) {
            $likeConditions[] = "url LIKE ?";
            $params[] = '%://' . $domain . '/%';
            $likeConditions[] = "url LIKE ?";
            $params[] = '%://www.' . $domain . '/%';
        }
        
        $whereClause = '(' . implode(' OR ', $likeConditions) . ')';
        $params[] = $limit;
        
        $sql = "SELECT id, url, image 
                FROM {$this->table} 
                WHERE {$whereClause}
                ORDER BY 
                    short_link_resolve_failed_at IS NULL DESC,
                    short_link_resolve_failed_at ASC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Count short links by status
     * 
     * @param array $shortenerDomains List of shortener domains to match
     * @return array Stats with total, pending (never tried), failed counts
     */
    public function getShortLinkStats(array $shortenerDomains): array
    {
        if (empty($shortenerDomains)) {
            return ['total' => 0, 'pending' => 0, 'failed' => 0];
        }

        // Build LIKE conditions for each shortener domain
        $likeConditions = [];
        $params = [];
        foreach ($shortenerDomains as $domain) {
            $likeConditions[] = "url LIKE ?";
            $params[] = '%://' . $domain . '/%';
            $likeConditions[] = "url LIKE ?";
            $params[] = '%://www.' . $domain . '/%';
        }
        
        $whereClause = '(' . implode(' OR ', $likeConditions) . ')';
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN short_link_resolve_failed_at IS NULL THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN short_link_resolve_failed_at IS NOT NULL THEN 1 ELSE 0 END) as failed,
                    MIN(short_link_resolve_failed_at) as oldest_failure
                FROM {$this->table} 
                WHERE {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return [
            'total' => (int) ($result['total'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'oldest_failure' => $result['oldest_failure'] ?? null,
        ];
    }

    /**
     * Get paginated list of short links with their status
     * 
     * @param array $shortenerDomains List of shortener domains to match
     * @param int $limit Maximum number to return
     * @param int $offset Offset for pagination
     * @return array Array of preview records with status info
     */
    public function getShortLinks(array $shortenerDomains, int $limit = 20, int $offset = 0): array
    {
        if (empty($shortenerDomains)) {
            return [];
        }

        // Build LIKE conditions for each shortener domain
        $likeConditions = [];
        $params = [];
        foreach ($shortenerDomains as $domain) {
            $likeConditions[] = "url LIKE ?";
            $params[] = '%://' . $domain . '/%';
            $likeConditions[] = "url LIKE ?";
            $params[] = '%://www.' . $domain . '/%';
        }
        
        $whereClause = '(' . implode(' OR ', $likeConditions) . ')';
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT p.id, p.url, p.title, p.image, p.short_link_resolve_failed_at,
                       (SELECT COUNT(*) FROM trail_entries e WHERE e.url_preview_id = p.id) as affected_entries
                FROM {$this->table} p
                WHERE {$whereClause}
                ORDER BY 
                    short_link_resolve_failed_at IS NULL DESC,
                    short_link_resolve_failed_at ASC,
                    p.id DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
