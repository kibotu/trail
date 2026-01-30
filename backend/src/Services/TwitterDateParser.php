<?php

declare(strict_types=1);

namespace Trail\Services;

use DateTime;
use DateTimeZone;

/**
 * Parse Twitter date format and convert to MySQL TIMESTAMP format
 * 
 * Twitter format: "Fri Nov 28 10:54:34 +0000 2025"
 * MySQL format: "2025-11-28 10:54:34"
 */
class TwitterDateParser
{
    /**
     * Parse Twitter date format to MySQL TIMESTAMP format
     * 
     * @param string $twitterDate Date in Twitter format (e.g., "Fri Nov 28 10:54:34 +0000 2025")
     * @return string|null MySQL TIMESTAMP format or null if parsing fails
     */
    public static function parse(string $twitterDate): ?string
    {
        try {
            // Twitter date format: "Day Mon DD HH:MM:SS +ZZZZ YYYY"
            // Example: "Fri Nov 28 10:54:34 +0000 2025"
            $dateTime = DateTime::createFromFormat('D M d H:i:s O Y', $twitterDate);
            
            if ($dateTime === false) {
                error_log("TwitterDateParser: Failed to parse date: {$twitterDate}");
                return null;
            }
            
            // Convert to UTC for MySQL storage
            $dateTime->setTimezone(new DateTimeZone('UTC'));
            
            // Return in MySQL TIMESTAMP format
            return $dateTime->format('Y-m-d H:i:s');
            
        } catch (\Throwable $e) {
            error_log("TwitterDateParser: Exception parsing date '{$twitterDate}': " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate if a string matches Twitter date format
     * 
     * @param string $twitterDate Date string to validate
     * @return bool True if valid Twitter date format
     */
    public static function isValid(string $twitterDate): bool
    {
        return self::parse($twitterDate) !== null;
    }
}
