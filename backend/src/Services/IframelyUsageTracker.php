<?php

declare(strict_types=1);

namespace Trail\Services;

use PDO;

/**
 * IframelyUsageTracker - Tracks iframe.ly API usage and enforces monthly limits
 * 
 * This service tracks API usage per month and enforces a 2000 request limit.
 * When the limit is reached, it sends an email notification and prevents further API calls.
 */
class IframelyUsageTracker
{
    private const MONTHLY_LIMIT = 2000;
    private PDO $db;
    private string $adminEmail;

    public function __construct(PDO $db, string $adminEmail)
    {
        $this->db = $db;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Check if we can make an iframe.ly API call this month
     * 
     * @return bool True if under limit, false if limit reached
     */
    public function canUseApi(): bool
    {
        $usage = $this->getCurrentMonthUsage();
        return $usage < self::MONTHLY_LIMIT;
    }

    /**
     * Increment the API usage counter for current month
     * 
     * @return bool True if increment successful, false if limit reached
     */
    public function incrementUsage(): bool
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle first request of month
            $stmt = $this->db->prepare("
                INSERT INTO trail_iframely_usage (year, month, request_count)
                VALUES (:year, :month, 1)
                ON DUPLICATE KEY UPDATE 
                    request_count = request_count + 1,
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                'year' => $year,
                'month' => $month,
            ]);

            // Check if we just hit the limit
            $newCount = $this->getCurrentMonthUsage();
            
            if ($newCount >= self::MONTHLY_LIMIT) {
                $this->handleLimitReached($year, $month, $newCount);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            error_log("IframelyUsageTracker::incrementUsage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current month's API usage count
     * 
     * @return int Number of API calls made this month
     */
    public function getCurrentMonthUsage(): int
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        try {
            $stmt = $this->db->prepare("
                SELECT request_count 
                FROM trail_iframely_usage 
                WHERE year = :year AND month = :month
            ");
            
            $stmt->execute([
                'year' => $year,
                'month' => $month,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int) $result['request_count'] : 0;
        } catch (\Throwable $e) {
            error_log("IframelyUsageTracker::getCurrentMonthUsage: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get remaining API calls for current month
     * 
     * @return int Number of remaining API calls
     */
    public function getRemainingCalls(): int
    {
        $usage = $this->getCurrentMonthUsage();
        $remaining = self::MONTHLY_LIMIT - $usage;
        return max(0, $remaining);
    }

    /**
     * Handle when the monthly limit is reached
     * 
     * @param int $year Current year
     * @param int $month Current month
     * @param int $count Current usage count
     */
    private function handleLimitReached(int $year, int $month, int $count): void
    {
        try {
            // Update the limit_reached_at timestamp
            $stmt = $this->db->prepare("
                UPDATE trail_iframely_usage 
                SET limit_reached_at = CURRENT_TIMESTAMP
                WHERE year = :year AND month = :month AND limit_reached_at IS NULL
            ");
            
            $stmt->execute([
                'year' => $year,
                'month' => $month,
            ]);

            // Check if we need to send notification
            if ($stmt->rowCount() > 0) {
                $this->sendLimitNotification($year, $month, $count);
            }
        } catch (\Throwable $e) {
            error_log("IframelyUsageTracker::handleLimitReached: " . $e->getMessage());
        }
    }

    /**
     * Send email notification when limit is reached
     * 
     * @param int $year Current year
     * @param int $month Current month
     * @param int $count Current usage count
     */
    private function sendLimitNotification(int $year, int $month, int $count): void
    {
        try {
            $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
            $subject = "Trail: iframe.ly API Limit Reached for {$monthName} {$year}";
            
            $message = "Hello,\n\n";
            $message .= "The iframe.ly API usage limit has been reached for {$monthName} {$year}.\n\n";
            $message .= "Details:\n";
            $message .= "- Month: {$monthName} {$year}\n";
            $message .= "- Limit: " . self::MONTHLY_LIMIT . " requests\n";
            $message .= "- Current usage: {$count} requests\n\n";
            $message .= "The system has automatically switched to using the embed library fallback ";
            $message .= "for the remainder of the month.\n\n";
            $message .= "Actions you can take:\n";
            $message .= "1. Monitor usage at: https://iframely.com/dashboard\n";
            $message .= "2. Consider upgrading your iframe.ly plan if needed\n";
            $message .= "3. The fallback system will continue to provide preview cards\n\n";
            $message .= "Best regards,\n";
            $message .= "Trail Backend System";

            $headers = "From: noreply@trail.services.kibotu.net\r\n";
            $headers .= "Reply-To: noreply@trail.services.kibotu.net\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            $success = mail($this->adminEmail, $subject, $message, $headers);

            if ($success) {
                // Mark notification as sent
                $stmt = $this->db->prepare("
                    UPDATE trail_iframely_usage 
                    SET notification_sent = TRUE
                    WHERE year = :year AND month = :month
                ");
                
                $stmt->execute([
                    'year' => $year,
                    'month' => $month,
                ]);

                error_log("IframelyUsageTracker: Limit notification sent to {$this->adminEmail}");
            } else {
                error_log("IframelyUsageTracker: Failed to send limit notification email");
            }
        } catch (\Throwable $e) {
            error_log("IframelyUsageTracker::sendLimitNotification: " . $e->getMessage());
        }
    }

    /**
     * Get usage statistics for a specific month
     * 
     * @param int $year Year
     * @param int $month Month (1-12)
     * @return array|null Usage statistics or null if not found
     */
    public function getMonthUsage(int $year, int $month): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    year,
                    month,
                    request_count,
                    limit_reached_at,
                    notification_sent,
                    created_at,
                    updated_at
                FROM trail_iframely_usage 
                WHERE year = :year AND month = :month
            ");
            
            $stmt->execute([
                'year' => $year,
                'month' => $month,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("IframelyUsageTracker::getMonthUsage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the monthly limit
     * 
     * @return int Monthly API call limit
     */
    public static function getMonthlyLimit(): int
    {
        return self::MONTHLY_LIMIT;
    }
}
