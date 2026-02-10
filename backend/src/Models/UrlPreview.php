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
}
