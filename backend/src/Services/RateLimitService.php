<?php

declare(strict_types=1);

namespace Trail\Services;

use PDO;

class RateLimitService
{
    private PDO $db;
    private string $table = 'trail_rate_limits';
    private int $requestsPerMinute;
    private int $requestsPerHour;
    private bool $enabled;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->requestsPerMinute = $config['security']['rate_limit']['requests_per_minute'];
        $this->requestsPerHour = $config['security']['rate_limit']['requests_per_hour'];
        $this->enabled = $config['security']['rate_limit']['enabled'] ?? true;
    }

    public function checkLimit(string $identifier, string $endpoint): bool
    {
        // Skip rate limiting if disabled
        if (!$this->enabled) {
            return true;
        }

        // Clean up old records
        $this->cleanup();

        // Check minute limit
        $minuteCount = $this->getRequestCount($identifier, $endpoint, 60);
        if ($minuteCount >= $this->requestsPerMinute) {
            return false;
        }

        // Check hour limit
        $hourCount = $this->getRequestCount($identifier, $endpoint, 3600);
        if ($hourCount >= $this->requestsPerHour) {
            return false;
        }

        // Increment counter
        $this->incrementCounter($identifier, $endpoint);

        return true;
    }

    private function getRequestCount(string $identifier, string $endpoint, int $seconds): int
    {
        $stmt = $this->db->prepare(
            "SELECT SUM(request_count) as total 
             FROM {$this->table} 
             WHERE identifier = ? 
             AND endpoint = ? 
             AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $endpoint, $seconds]);
        $result = $stmt->fetch();

        return (int) ($result['total'] ?? 0);
    }

    private function incrementCounter(string $identifier, string $endpoint): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (identifier, endpoint, request_count, window_start) 
             VALUES (?, ?, 1, NOW()) 
             ON DUPLICATE KEY UPDATE 
             request_count = request_count + 1, 
             window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL 60 SECOND), NOW(), window_start)"
        );
        $stmt->execute([$identifier, $endpoint]);
    }

    private function cleanup(): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} 
             WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute();
    }

    public function getRetryAfter(string $identifier, string $endpoint): int
    {
        $stmt = $this->db->prepare(
            "SELECT window_start 
             FROM {$this->table} 
             WHERE identifier = ? 
             AND endpoint = ? 
             ORDER BY window_start DESC 
             LIMIT 1"
        );
        $stmt->execute([$identifier, $endpoint]);
        $result = $stmt->fetch();

        if (!$result) {
            return 60;
        }

        $windowStart = strtotime($result['window_start']);
        $retryAfter = 60 - (time() - $windowStart);

        return max(1, $retryAfter);
    }
}
