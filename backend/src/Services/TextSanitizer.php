<?php

declare(strict_types=1);

namespace Trail\Services;

/**
 * TextSanitizer - Sanitizes user input text to prevent XSS attacks
 * while preserving URLs and emojis.
 * 
 * This service strips all HTML tags, JavaScript, and potentially dangerous
 * content while keeping legitimate URLs and emoji characters intact.
 */
class TextSanitizer
{
    /**
     * Sanitize text input by removing scripts and HTML while preserving URLs and emojis
     * 
     * @param string $text The input text to sanitize
     * @return string The sanitized text
     */
    public static function sanitize(string $text): string
    {
        // 1. Decode HTML entities first to catch encoded attacks
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 2. Remove script tags and their content
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text);
        
        // 3. Strip all remaining HTML tags (this removes <iframe>, <object>, etc.)
        $text = strip_tags($text);
        
        // 4. Remove any remaining script-like patterns (javascript:, data:, vbscript:, etc.)
        $text = preg_replace('/javascript:/i', '', $text);
        $text = preg_replace('/vbscript:/i', '', $text);
        $text = preg_replace('/data:/i', '', $text);
        $text = preg_replace('/on\w+\s*=/i', '', $text); // Remove event handlers like onclick=
        
        // 5. Remove null bytes and control characters (except newlines and tabs)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        // 6. Normalize whitespace (convert multiple spaces to single space)
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 7. Trim whitespace from beginning and end
        $text = trim($text);
        
        // Note: We don't encode HTML entities here because:
        // - Text is stored clean in the database
        // - Encoding happens at output time (templates, RSS, JSON)
        // - This prevents double-encoding issues
        
        return $text;
    }
    
    /**
     * Validate that text doesn't contain dangerous patterns
     * 
     * @param string $text The text to validate
     * @return bool True if text is safe, false otherwise
     */
    public static function isSafe(string $text): bool
    {
        // Check for common XSS patterns
        $dangerousPatterns = [
            '/<script/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/on\w+\s*=/i', // Event handlers
            '/<\?php/i',
            '/<\?=/i',
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extract URLs from text
     * 
     * @param string $text The text to extract URLs from
     * @return array Array of URLs found in the text
     */
    public static function extractUrls(string $text): array
    {
        $pattern = '/\b(?:https?:\/\/|www\.)[^\s<>"\'\)]+/i';
        preg_match_all($pattern, $text, $matches);
        
        return $matches[0] ?? [];
    }
    
    /**
     * Validate that text contains valid UTF-8 (for emoji support)
     * 
     * @param string $text The text to validate
     * @return bool True if valid UTF-8, false otherwise
     */
    public static function isValidUtf8(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8');
    }
}
