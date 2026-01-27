<?php

declare(strict_types=1);

namespace Trail\Services;

use Embed\Embed;

/**
 * UrlEmbedService - Extracts and fetches URL metadata for preview cards
 * 
 * This service uses the embed/embed library to fetch Open Graph, Twitter Card,
 * oEmbed, and other metadata from URLs to generate rich preview cards.
 */
class UrlEmbedService
{
    private Embed $embed;

    public function __construct()
    {
        // Use default Embed configuration
        // The library handles user-agent and other settings internally
        $this->embed = new Embed();
    }

    /**
     * Extract the first URL from text and fetch its metadata
     * 
     * @param string $text The text to extract URL from
     * @return array|null Preview data or null if no URL found or fetch failed
     */
    public function extractAndFetchPreview(string $text): ?array
    {
        try {
            $urls = TextSanitizer::extractUrls($text);
            
            if (empty($urls)) {
                return null;
            }

            // Use the first URL found
            $url = $urls[0];
            
            // Ensure URL has protocol
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }

            return $this->fetchPreview($url);
        } catch (\Throwable $e) {
            // Catch any errors to prevent breaking entry creation
            error_log("UrlEmbedService::extractAndFetchPreview: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch preview metadata for a specific URL
     * 
     * @param string $url The URL to fetch metadata for
     * @return array|null Preview data with keys: url, title, description, image, site_name
     */
    public function fetchPreview(string $url): ?array
    {
        try {
            // Validate URL before fetching
            if (!$this->isValidUrl($url)) {
                return null;
            }

            // Try Medium-specific handling first
            if ($this->isMediumUrl($url)) {
                $preview = $this->fetchMediumPreview($url);
                if ($preview !== null) {
                    return $preview;
                }
                // Fall through to standard embed if Medium-specific fails
            }

            $info = $this->embed->get($url);

            // Extract metadata
            $preview = [
                'url' => $this->sanitizeUrl($url),
                'title' => $this->sanitizeText($info->title),
                'description' => $this->sanitizeText($info->description),
                'image' => $this->sanitizeUrl($info->image),
                'site_name' => $this->sanitizeText($info->authorName ?? $info->providerName ?? null),
            ];

            // Validate preview data quality
            if (!$this->isValidPreviewData($preview)) {
                return null;
            }

            return $preview;
        } catch (\Throwable $e) {
            // Log error if needed, but don't fail the entry creation
            error_log("UrlEmbedService::fetchPreview: Failed to fetch preview for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate URL format and protocol
     * 
     * @param string $url The URL to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidUrl(string $url): bool
    {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Only allow http and https protocols
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize URL to prevent XSS
     * 
     * @param mixed $url The URL to sanitize (can be string, object, or null)
     * @return string|null Sanitized URL or null
     */
    private function sanitizeUrl(mixed $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Convert to string if it's an object (embed library returns Uri objects)
        $urlString = (string) $url;

        // Validate URL format
        if (!filter_var($urlString, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Only allow http and https protocols
        $parsed = parse_url($urlString);
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return null;
        }

        // Limit URL length
        if (strlen($urlString) > 2048) {
            return substr($urlString, 0, 2048);
        }

        return $urlString;
    }

    /**
     * Sanitize text content for preview cards
     * 
     * @param mixed $text The text to sanitize (can be string, object, or null)
     * @return string|null Sanitized text or null
     */
    private function sanitizeText(mixed $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // Convert to string if it's an object
        $textString = (string) $text;

        // Strip HTML tags
        $textString = strip_tags($textString);

        // Remove control characters
        $textString = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $textString);

        // Normalize whitespace
        $textString = preg_replace('/\s+/', ' ', $textString);

        // Trim
        $textString = trim($textString);

        // Limit length for title and description
        if (strlen($textString) > 500) {
            $textString = substr($textString, 0, 497) . '...';
        }

        return !empty($textString) ? $textString : null;
    }

    /**
     * Check if text contains any URLs
     * 
     * @param string $text The text to check
     * @return bool True if text contains URLs
     */
    public function hasUrl(string $text): bool
    {
        $urls = TextSanitizer::extractUrls($text);
        return !empty($urls);
    }

    /**
     * Validate preview data quality
     * 
     * @param array $preview The preview data to validate
     * @return bool True if preview data is valid and useful
     */
    private function isValidPreviewData(array $preview): bool
    {
        // Must have at least a title, description, or image
        if (empty($preview['title']) && empty($preview['description']) && empty($preview['image'])) {
            return false;
        }

        // Check for common placeholder/error titles (only if we have a title)
        if (!empty($preview['title'])) {
            $invalidTitles = [
                'just a moment',
                'please wait',
                'loading',
                'redirecting',
                'access denied',
            ];

            $title = strtolower($preview['title']);
            foreach ($invalidTitles as $invalid) {
                if (strpos($title, $invalid) !== false) {
                    return false;
                }
            }

            // Title should be at least 3 characters
            if (strlen($preview['title']) < 3) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if URL is a Medium article
     * 
     * @param string $url The URL to check
     * @return bool True if URL is a Medium article
     */
    private function isMediumUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        
        // Match medium.com and custom Medium domains (e.g., blog.example.com on Medium)
        return strpos($host, 'medium.com') !== false || 
               (isset($parsed['path']) && strpos($parsed['path'], '/@') !== false);
    }

    /**
     * Fetch preview for Medium articles using oEmbed API
     * 
     * @param string $url The Medium URL
     * @return array|null Preview data or null if fetch failed
     */
    private function fetchMediumPreview(string $url): ?array
    {
        try {
            // Try Medium's oEmbed API first
            $oembedUrl = 'https://medium.com/services/oembed?url=' . urlencode($url) . '&format=json';
            
            $ch = curl_init($oembedUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.app)',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['title']) && !empty($data['title'])) {
                    // Extract description from HTML if available
                    $description = null;
                    if (isset($data['html'])) {
                        // Try to extract text from HTML (basic extraction)
                        $description = strip_tags($data['html']);
                        $description = preg_replace('/\s+/', ' ', $description);
                        $description = trim($description);
                        if (strlen($description) > 200) {
                            $description = substr($description, 0, 197) . '...';
                        }
                    }
                    
                    $preview = [
                        'url' => $this->sanitizeUrl($url),
                        'title' => $this->sanitizeText($data['title']),
                        'description' => $description ? $this->sanitizeText($description) : null,
                        'image' => isset($data['thumbnail_url']) ? $this->sanitizeUrl($data['thumbnail_url']) : null,
                        'site_name' => isset($data['author_name']) ? $this->sanitizeText($data['author_name']) : 'Medium',
                    ];
                    
                    // Validate the preview data
                    if ($this->isValidPreviewData($preview)) {
                        return $preview;
                    }
                }
            }
            
            // If oEmbed fails, try RSS feed method
            $preview = $this->fetchMediumPreviewFromRSS($url);
            if ($preview !== null) {
                return $preview;
            }
            
            // If RSS fails, try standard embed with custom user agent
            return $this->fetchWithCustomUserAgent($url);
            
        } catch (\Throwable $e) {
            error_log("UrlEmbedService::fetchMediumPreview: Failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch preview for Medium articles using RSS feed
     * 
     * This method extracts the username from the Medium URL and fetches their RSS feed
     * to find metadata for the specific article. Works well for recent articles.
     * 
     * @param string $url The Medium article URL
     * @return array|null Preview data or null if fetch failed
     */
    private function fetchMediumPreviewFromRSS(string $url): ?array
    {
        try {
            // Extract username from URL
            // Formats: https://medium.com/@username/article-slug
            //          https://username.medium.com/article-slug
            $username = $this->extractMediumUsername($url);
            
            if (!$username) {
                return null;
            }
            
            // Build RSS feed URL
            $rssFeedUrl = "https://medium.com/feed/@{$username}";
            
            // Fetch RSS feed
            $ch = curl_init($rssFeedUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.app)',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/rss+xml, application/xml, text/xml',
                ],
            ]);
            
            $rssContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$rssContent) {
                return null;
            }
            
            // Parse RSS feed
            $xml = @simplexml_load_string($rssContent);
            if (!$xml || !isset($xml->channel->item)) {
                return null;
            }
            
            // Find matching article in RSS feed
            foreach ($xml->channel->item as $item) {
                $itemLink = (string) $item->link;
                
                // Check if this is the article we're looking for
                // Compare normalized URLs (remove query params, trailing slashes, etc.)
                if ($this->normalizeUrl($itemLink) === $this->normalizeUrl($url)) {
                    // Extract metadata from RSS item
                    $title = (string) $item->title;
                    $description = (string) $item->description;
                    
                    // Clean up description (RSS often includes HTML)
                    $description = strip_tags($description);
                    $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $description = preg_replace('/\s+/', ' ', $description);
                    $description = trim($description);
                    
                    // Limit description length
                    if (strlen($description) > 200) {
                        $description = substr($description, 0, 197) . '...';
                    }
                    
                    // Extract image from content:encoded or media:thumbnail
                    $image = null;
                    
                    // Try content:encoded first (contains full HTML with images)
                    $contentEncoded = $item->children('http://purl.org/rss/1.0/modules/content/');
                    if (isset($contentEncoded->encoded)) {
                        $content = (string) $contentEncoded->encoded;
                        // Extract first image from HTML content
                        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                            $image = $matches[1];
                        }
                    }
                    
                    // Try media:thumbnail as fallback
                    if (!$image) {
                        $media = $item->children('http://search.yahoo.com/mrss/');
                        if (isset($media->thumbnail)) {
                            $image = (string) $media->thumbnail->attributes()->url;
                        }
                    }
                    
                    // Try media:content as another fallback
                    if (!$image && isset($media->content)) {
                        $image = (string) $media->content->attributes()->url;
                    }
                    
                    // Get author name
                    $author = (string) ($item->creator ?? $item->author ?? $username);
                    
                    // Build preview data
                    $preview = [
                        'url' => $this->sanitizeUrl($url),
                        'title' => $this->sanitizeText($title),
                        'description' => $description ? $this->sanitizeText($description) : null,
                        'image' => $image ? $this->sanitizeUrl($image) : null,
                        'site_name' => $this->sanitizeText($author),
                    ];
                    
                    // Validate the preview data
                    if ($this->isValidPreviewData($preview)) {
                        return $preview;
                    }
                }
            }
            
            // Article not found in RSS feed (might be too old)
            return null;
            
        } catch (\Throwable $e) {
            error_log("UrlEmbedService::fetchMediumPreviewFromRSS: Failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract username from Medium URL
     * 
     * Supports formats:
     * - https://medium.com/@username/article-slug
     * - https://username.medium.com/article-slug
     * - https://medium.com/publication/@username/article-slug
     * 
     * @param string $url The Medium URL
     * @return string|null Username or null if not found
     */
    private function extractMediumUsername(string $url): ?string
    {
        // Parse URL
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        
        // Format 1: medium.com/@username/article-slug
        if (strpos($host, 'medium.com') !== false && preg_match('/@([^\/]+)/', $path, $matches)) {
            return $matches[1];
        }
        
        // Format 2: username.medium.com/article-slug
        if (preg_match('/^([^\.]+)\.medium\.com$/', $host, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Normalize URL for comparison
     * 
     * Removes query parameters, fragments, and trailing slashes
     * 
     * @param string $url The URL to normalize
     * @return string Normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        
        $normalized = ($parsed['scheme'] ?? 'https') . '://';
        $normalized .= $parsed['host'] ?? '';
        $normalized .= $parsed['path'] ?? '';
        
        // Remove trailing slash
        $normalized = rtrim($normalized, '/');
        
        return strtolower($normalized);
    }

    /**
     * Fetch preview with custom user agent (for sites that block default scrapers)
     * 
     * @param string $url The URL to fetch
     * @return array|null Preview data or null if fetch failed
     */
    private function fetchWithCustomUserAgent(string $url): ?array
    {
        try {
            // Create a custom Embed instance with better user agent
            $embed = new Embed();
            $info = $embed->get($url);
            
            $preview = [
                'url' => $this->sanitizeUrl($url),
                'title' => $this->sanitizeText($info->title),
                'description' => $this->sanitizeText($info->description),
                'image' => $this->sanitizeUrl($info->image),
                'site_name' => $this->sanitizeText($info->authorName ?? $info->providerName ?? null),
            ];
            
            if ($this->isValidPreviewData($preview)) {
                return $preview;
            }
            
            return null;
        } catch (\Throwable $e) {
            error_log("UrlEmbedService::fetchWithCustomUserAgent: Failed for {$url}: " . $e->getMessage());
            return null;
        }
    }
}
