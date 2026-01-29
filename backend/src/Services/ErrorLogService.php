<?php

declare(strict_types=1);

namespace Trail\Services;

use PDO;
use PDOException;

/**
 * Error Log Service
 * 
 * Securely logs HTTP errors to the database with deduplication.
 * All data is sanitized and stored as plain text to prevent XSS attacks.
 */
class ErrorLogService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log an error occurrence.
     * 
     * If the same error (status_code + url + user_id) exists, increments the count.
     * Otherwise, creates a new error log entry.
     * 
     * All inputs are sanitized and validated to prevent injection attacks.
     * 
     * @param int $statusCode HTTP status code
     * @param string $url Request URL
     * @param string|null $referer HTTP referer
     * @param string|null $userAgent User agent string
     * @param int|null $userId User ID if authenticated
     * @param string|null $ipAddress Client IP address
     * @return bool Success status
     */
    public function logError(
        int $statusCode,
        string $url,
        ?string $referer = null,
        ?string $userAgent = null,
        ?int $userId = null,
        ?string $ipAddress = null
    ): bool {
        try {
            // Sanitize and validate inputs
            $statusCode = $this->sanitizeStatusCode($statusCode);
            $url = $this->sanitizeUrl($url);
            $referer = $referer ? $this->sanitizeUrl($referer) : null;
            $userAgent = $userAgent ? $this->sanitizeUserAgent($userAgent) : null;
            $ipAddress = $ipAddress ? $this->sanitizeIpAddress($ipAddress) : null;

            // Try to increment existing error count first
            $stmt = $this->db->prepare("
                UPDATE trail_error_logs 
                SET occurrence_count = occurrence_count + 1,
                    last_seen_at = CURRENT_TIMESTAMP
                WHERE status_code = ? 
                AND url = ? 
                AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))
            ");
            
            $stmt->execute([$statusCode, $url, $userId, $userId]);
            
            // If no rows were updated, insert a new record
            if ($stmt->rowCount() === 0) {
                $stmt = $this->db->prepare("
                    INSERT INTO trail_error_logs 
                    (status_code, url, referer, user_agent, user_id, ip_address, occurrence_count)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $statusCode,
                    $url,
                    $referer,
                    $userAgent,
                    $userId,
                    $ipAddress
                ]);
            }
            
            return true;
        } catch (PDOException $e) {
            // Log error but don't throw - error logging should never break the app
            error_log("ErrorLogService: Failed to log error - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get error logs with pagination and filtering.
     * 
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @param int|null $statusCode Filter by status code
     * @return array Array of error log records
     */
    public function getErrorLogs(int $limit = 50, int $offset = 0, ?int $statusCode = null): array
    {
        $sql = "
            SELECT 
                el.id,
                el.status_code,
                el.url,
                el.referer,
                el.user_agent,
                el.user_id,
                el.ip_address,
                el.occurrence_count,
                el.first_seen_at,
                el.last_seen_at,
                u.email as user_email,
                u.nickname as user_nickname
            FROM trail_error_logs el
            LEFT JOIN trail_users u ON el.user_id = u.id
        ";
        
        $params = [];
        
        if ($statusCode !== null) {
            $sql .= " WHERE el.status_code = ?";
            $params[] = $statusCode;
        }
        
        $sql .= " ORDER BY el.last_seen_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get error statistics grouped by status code.
     * 
     * @return array Array of status codes with counts
     */
    public function getErrorStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                status_code,
                COUNT(*) as unique_errors,
                SUM(occurrence_count) as total_occurrences,
                MAX(last_seen_at) as last_seen
            FROM trail_error_logs
            GROUP BY status_code
            ORDER BY total_occurrences DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete old error logs.
     * 
     * @param int $daysToKeep Number of days to keep logs
     * @return int Number of deleted records
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM trail_error_logs 
            WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$daysToKeep]);
        
        return $stmt->rowCount();
    }

    /**
     * Sanitize status code to ensure it's a valid HTTP status code.
     */
    private function sanitizeStatusCode(int $statusCode): int
    {
        // Ensure status code is in valid range (100-599)
        if ($statusCode < 100 || $statusCode > 599) {
            return 500; // Default to 500 for invalid codes
        }
        
        return $statusCode;
    }

    /**
     * Sanitize URL to prevent XSS and ensure it's within length limits.
     * Removes any HTML/JavaScript and truncates to safe length.
     */
    private function sanitizeUrl(string $url): string
    {
        // Remove any HTML tags
        $url = strip_tags($url);
        
        // Remove any JavaScript protocol
        $url = preg_replace('/^javascript:/i', '', $url);
        
        // Remove any data: protocol
        $url = preg_replace('/^data:/i', '', $url);
        
        // Truncate to maximum length (2048 chars)
        $url = mb_substr($url, 0, 2048);
        
        return $url;
    }

    /**
     * Sanitize user agent string.
     */
    private function sanitizeUserAgent(string $userAgent): string
    {
        // Remove any HTML tags
        $userAgent = strip_tags($userAgent);
        
        // Truncate to maximum length (512 chars)
        $userAgent = mb_substr($userAgent, 0, 512);
        
        return $userAgent;
    }

    /**
     * Sanitize and validate IP address.
     */
    private function sanitizeIpAddress(string $ipAddress): ?string
    {
        // Validate IPv4 or IPv6
        if (filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return $ipAddress;
        }
        
        return null;
    }
}
