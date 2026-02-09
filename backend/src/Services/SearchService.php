<?php

declare(strict_types=1);

namespace Trail\Services;

/**
 * SearchService - Query sanitization and preparation for search operations
 * 
 * Provides utilities for safely handling user search queries:
 * - Sanitization to prevent SQL injection
 * - Query preparation for FULLTEXT and LIKE searches
 * - Length validation and trimming
 */
class SearchService
{
    /**
     * Maximum allowed search query length
     */
    private const MAX_QUERY_LENGTH = 100;

    /**
     * Minimum query length for FULLTEXT search (MySQL default)
     */
    private const FULLTEXT_MIN_LENGTH = 4;

    /**
     * Sanitize a search query for safe use
     * 
     * @param string $query Raw search query from user
     * @return string Sanitized query (trimmed, length-limited)
     */
    public static function sanitize(string $query): string
    {
        // Trim whitespace
        $query = trim($query);
        
        // Limit length
        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH);
        }
        
        return $query;
    }

    /**
     * Prepare query for FULLTEXT search
     * 
     * @param string $query Sanitized query
     * @return string Query prepared for FULLTEXT AGAINST()
     */
    public static function prepareForFulltext(string $query): string
    {
        // For FULLTEXT search, we pass the query directly
        // MySQL handles word parsing, stemming, and stop words
        return $query;
    }

    /**
     * Prepare query for LIKE search (fallback for short queries)
     * 
     * @param string $query Sanitized query
     * @return string Query prepared for LIKE with wildcards
     */
    public static function prepareForLike(string $query): string
    {
        // Escape special LIKE characters
        $query = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        
        // Add wildcards for partial matching
        return '%' . $query . '%';
    }

    /**
     * Check if query is too short for FULLTEXT search
     * 
     * @param string $query Sanitized query
     * @return bool True if query is shorter than FULLTEXT minimum
     */
    public static function isShortQuery(string $query): bool
    {
        return mb_strlen($query) < self::FULLTEXT_MIN_LENGTH;
    }

    /**
     * Check if query is empty after sanitization
     * 
     * @param string $query Sanitized query
     * @return bool True if query is empty
     */
    public static function isEmpty(string $query): bool
    {
        return $query === '';
    }

    /**
     * Validate that query contains only safe characters
     * Basic validation to prevent obvious SQL injection attempts
     * 
     * @param string $query Query to validate
     * @return bool True if query appears safe
     */
    public static function isSafe(string $query): bool
    {
        // Check for obvious SQL injection patterns
        $dangerousPatterns = [
            '/;\s*(DROP|DELETE|UPDATE|INSERT|ALTER|CREATE)\s+/i',
            '/UNION\s+SELECT/i',
            '/--\s*$/',
            '/\/\*.*\*\//s',
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return false;
            }
        }
        
        return true;
    }
}
