<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

/**
 * LinkHealth Model - Manages link health tracking data
 * 
 * This model handles the trail_link_health table which tracks
 * HTTP health status of URLs in trail_url_previews.
 */
class LinkHealth
{
    private PDO $db;
    private string $table = 'trail_link_health';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find link health record by URL preview ID
     * 
     * @param int $urlPreviewId The URL preview ID
     * @return array|null Health record or null if not found
     */
    public function findByUrlPreviewId(int $urlPreviewId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE url_preview_id = ?"
        );
        $stmt->execute([$urlPreviewId]);
        $record = $stmt->fetch();
        
        return $record ?: null;
    }

    /**
     * Upsert link health record
     * 
     * @param int $urlPreviewId The URL preview ID
     * @param array $data Health data with keys: http_status_code, error_type, error_message, consecutive_failures, is_broken, last_healthy_at
     * @return void
     */
    public function upsert(int $urlPreviewId, array $data): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} 
             (url_preview_id, http_status_code, error_type, error_message, consecutive_failures, last_checked_at, last_healthy_at, is_broken) 
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
             ON DUPLICATE KEY UPDATE
                http_status_code = VALUES(http_status_code),
                error_type = VALUES(error_type),
                error_message = VALUES(error_message),
                consecutive_failures = VALUES(consecutive_failures),
                last_checked_at = CURRENT_TIMESTAMP,
                last_healthy_at = VALUES(last_healthy_at),
                is_broken = VALUES(is_broken),
                updated_at = CURRENT_TIMESTAMP"
        );
        
        $stmt->execute([
            $urlPreviewId,
            $data['http_status_code'] ?? null,
            $data['error_type'] ?? 'none',
            $data['error_message'] ?? null,
            $data['consecutive_failures'] ?? 0,
            $data['last_healthy_at'] ?? null,
            $data['is_broken'] ?? false
        ]);
    }

    /**
     * Get paginated list of broken links
     * 
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @param string|null $errorType Filter by error type
     * @param bool $includeDismissed Include dismissed links
     * @return array Array of broken link records with URL preview data
     */
    public function getBrokenLinks(int $limit, int $offset, ?string $errorType = null, bool $includeDismissed = false): array
    {
        // Show links that have failed at least once (consecutive_failures >= 1)
        $whereClauses = ['lh.consecutive_failures >= 1'];
        $params = [];
        
        if (!$includeDismissed) {
            $whereClauses[] = 'lh.is_dismissed = 0';
        }
        
        if ($errorType !== null && $errorType !== '') {
            $whereClauses[] = 'lh.error_type = ?';
            $params[] = $errorType;
        }
        
        $whereClause = implode(' AND ', $whereClauses);
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare(
            "SELECT 
                lh.*,
                up.url,
                up.title,
                up.description,
                up.image,
                up.site_name,
                (SELECT COUNT(*) FROM trail_entries WHERE url_preview_id = lh.url_preview_id) as affected_entries_count
             FROM {$this->table} lh
             INNER JOIN trail_url_previews up ON lh.url_preview_id = up.id
             WHERE {$whereClause}
             ORDER BY lh.consecutive_failures DESC, lh.last_checked_at DESC
             LIMIT ? OFFSET ?"
        );
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get total count of broken links
     * 
     * @param string|null $errorType Filter by error type
     * @param bool $includeDismissed Include dismissed links
     * @return int Total count
     */
    public function getBrokenLinksCount(?string $errorType = null, bool $includeDismissed = false): int
    {
        // Count links that have failed at least once
        $whereClauses = ['consecutive_failures >= 1'];
        $params = [];
        
        if (!$includeDismissed) {
            $whereClauses[] = 'is_dismissed = 0';
        }
        
        if ($errorType !== null && $errorType !== '') {
            $whereClauses[] = 'error_type = ?';
            $params[] = $errorType;
        }
        
        $whereClause = implode(' AND ', $whereClauses);
        
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE {$whereClause}"
        );
        
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    /**
     * Get statistics about link health
     * 
     * @return array Statistics with counts by error_type, status_code, broken/healthy/unchecked
     */
    public function getStats(): array
    {
        // Total URLs in url_previews that are referenced by entries
        $stmt = $this->db->query(
            "SELECT COUNT(DISTINCT url_preview_id) as count 
             FROM trail_entries 
             WHERE url_preview_id IS NOT NULL"
        );
        $totalUrls = (int) $stmt->fetch()['count'];
        
        // Checked URLs
        $stmt = $this->db->query(
            "SELECT COUNT(*) as count FROM {$this->table}"
        );
        $checkedUrls = (int) $stmt->fetch()['count'];
        
        // Healthy URLs
        $stmt = $this->db->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE is_broken = 0"
        );
        $healthyUrls = (int) $stmt->fetch()['count'];
        
        // Broken/Failing URLs (at least 1 failure)
        $stmt = $this->db->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE consecutive_failures >= 1 AND is_dismissed = 0"
        );
        $brokenUrls = (int) $stmt->fetch()['count'];
        
        // Dismissed URLs
        $stmt = $this->db->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE is_dismissed = 1"
        );
        $dismissedUrls = (int) $stmt->fetch()['count'];
        
        // By error type
        $stmt = $this->db->query(
            "SELECT error_type, COUNT(*) as count 
             FROM {$this->table} 
             WHERE is_broken = 1 AND is_dismissed = 0 AND error_type != 'none'
             GROUP BY error_type"
        );
        $byErrorType = [];
        while ($row = $stmt->fetch()) {
            $byErrorType[$row['error_type']] = (int) $row['count'];
        }
        
        // By status code
        $stmt = $this->db->query(
            "SELECT http_status_code, COUNT(*) as count 
             FROM {$this->table} 
             WHERE is_broken = 1 AND is_dismissed = 0 AND http_status_code IS NOT NULL AND http_status_code > 0
             GROUP BY http_status_code
             ORDER BY count DESC
             LIMIT 10"
        );
        $byStatusCode = [];
        while ($row = $stmt->fetch()) {
            $byStatusCode[(string) $row['http_status_code']] = (int) $row['count'];
        }
        
        return [
            'total_urls' => $totalUrls,
            'checked' => $checkedUrls,
            'healthy' => $healthyUrls,
            'broken' => $brokenUrls,
            'dismissed' => $dismissedUrls,
            'unchecked' => max(0, $totalUrls - $checkedUrls),
            'by_error_type' => $byErrorType,
            'by_status_code' => $byStatusCode
        ];
    }

    /**
     * Dismiss a broken link
     * 
     * @param int $id Link health record ID
     * @return bool True on success
     */
    public function dismiss(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET is_dismissed = 1, dismissed_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Undismiss a broken link
     * 
     * @param int $id Link health record ID
     * @return bool True on success
     */
    public function undismiss(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET is_dismissed = 0, dismissed_at = NULL 
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Get URLs that need checking
     * 
     * Priority: unchecked first, then stale (oldest last_checked_at)
     * Only returns URLs that are actually referenced by entries
     * 
     * @param int $limit Maximum number of URLs to return
     * @return array Array of URL preview records with health data
     */
    public function getUrlsToCheck(int $limit): array
    {
        // Get ONLY unchecked URLs (URLs in entries but not in link_health)
        // Do NOT recheck already-checked URLs
        // Order by URL preview ID to ensure consistent, deterministic ordering
        $stmt = $this->db->prepare(
            "SELECT DISTINCT up.id, up.url
             FROM trail_url_previews up
             INNER JOIN trail_entries e ON e.url_preview_id = up.id
             LEFT JOIN {$this->table} lh ON lh.url_preview_id = up.id
             WHERE lh.id IS NULL
             ORDER BY up.id ASC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get only broken/failing URLs for rechecking
     * 
     * Returns URLs that have failed at least once (consecutive_failures >= 1)
     * Prioritizes by failure count (most failures first)
     * 
     * @param int $limit Maximum number of URLs to return
     * @return array Array of URL preview records with health data
     */
    public function getBrokenUrlsToRecheck(int $limit): array
    {
        // Get failing URLs, prioritized by least recently checked
        // This ensures we recheck the oldest failing links first
        $stmt = $this->db->prepare(
            "SELECT DISTINCT up.id, up.url, lh.consecutive_failures, lh.is_broken, lh.last_checked_at
             FROM trail_url_previews up
             INNER JOIN trail_entries e ON e.url_preview_id = up.id
             INNER JOIN {$this->table} lh ON lh.url_preview_id = up.id
             WHERE lh.consecutive_failures >= 1
             ORDER BY lh.last_checked_at ASC NULLS FIRST
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
    }
}
