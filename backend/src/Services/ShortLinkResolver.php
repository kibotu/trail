<?php

declare(strict_types=1);

namespace Trail\Services;

use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Models\UrlPreview;

/**
 * ShortLinkResolver - Service for detecting and resolving shortened URLs
 * 
 * Detects common URL shorteners (t.co, bit.ly, etc.) and resolves them
 * to their final destination URLs. Tracks failed attempts with timestamps
 * for intelligent retry ordering.
 */
class ShortLinkResolver
{
    /**
     * Known URL shortener domains
     * Note: youtu.be is excluded as it's a canonical YouTube URL
     */
    private static array $shortenerDomains = [
        't.co',           // Twitter/X
        'bit.ly',         // Bitly
        'tinyurl.com',    // TinyURL
        'goo.gl',         // Google (deprecated but still works)
        'ow.ly',          // Hootsuite
        'is.gd',          // is.gd
        'buff.ly',        // Buffer
        'j.mp',           // Bitly alternative
        'dlvr.it',        // dlvr.it
        'fb.me',          // Facebook
        'lnkd.in',        // LinkedIn
        'shor.by',        // Shor.by
        'rebrand.ly',     // Rebrandly
        'bl.ink',         // BL.INK
        'short.io',       // Short.io
        'cutt.ly',        // Cutt.ly
        'trib.al',        // Tribal
        'snip.ly',        // Sniply
        'clck.ru',        // Yandex
        'v.gd',           // v.gd
        'po.st',          // Post
        'soo.gd',         // soo.gd
        'tiny.cc',        // tiny.cc
        'u.to',           // u.to
        'yourls.org',     // YOURLS
        'x.co',           // GoDaddy
        'su.pr',          // StumbleUpon
        'tr.im',          // tr.im
        'cli.gs',         // cli.gs
        'twitpic.com',    // TwitPic (often shortened)
    ];

    /**
     * Run short link resolution on a batch of URLs
     * 
     * @param int|null $batchSize Number of URLs to resolve (null = use config default)
     * @return array Results with resolved, failed, skipped, errors counts
     */
    public static function run(?int $batchSize = null): array
    {
        $results = [
            'processed' => 0,
            'resolved' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            $urlPreviewModel = new UrlPreview($db);
            
            // Get batch size from config if not provided
            if ($batchSize === null) {
                $batchSize = $config['short_link_resolver']['batch_size'] ?? 1000;
            }
            
            // Get short links to resolve, sorted by priority
            $shortLinks = $urlPreviewModel->getShortLinksToResolve(self::$shortenerDomains, $batchSize);
            
            if (empty($shortLinks)) {
                return $results;
            }
            
            // Get config values
            $rateLimitMs = $config['short_link_resolver']['rate_limit_ms'] ?? 500;
            $timeout = $config['short_link_resolver']['timeout_seconds'] ?? 15;
            $connectTimeout = $config['short_link_resolver']['connect_timeout_seconds'] ?? 10;
            
            // Increase execution time limit for larger batches
            if ($batchSize > 50) {
                set_time_limit(max(60, $batchSize * 2));
            }
            
            foreach ($shortLinks as $record) {
                $id = (int) $record['id'];
                $originalUrl = $record['url'];
                $originalImage = $record['image'] ?? null;
                
                try {
                    // Resolve the URL
                    $resolveResult = self::resolveUrl($originalUrl, $timeout, $connectTimeout);
                    
                    if ($resolveResult['success']) {
                        $finalUrl = $resolveResult['final_url'];
                        
                        // Check if URL actually changed
                        if ($finalUrl === $originalUrl) {
                            // URL didn't redirect, clear any previous failure
                            $urlPreviewModel->clearResolveFailed($id);
                            $results['skipped']++;
                            error_log("ShortLinkResolver: = {$originalUrl} (no redirect)");
                        } else {
                            // Check for hash collision before updating
                            $newHash = UrlPreview::hashUrl($finalUrl);
                            $existing = $urlPreviewModel->findByUrlHash($newHash);
                            
                            if ($existing && $existing['id'] !== $id) {
                                // Target URL already exists, skip to avoid duplicate
                                $urlPreviewModel->clearResolveFailed($id);
                                $results['skipped']++;
                                error_log("ShortLinkResolver: ~ {$originalUrl} -> {$finalUrl} (already cached as ID {$existing['id']})");
                            } else {
                                // Update the URL and hash
                                $urlPreviewModel->updateUrlAndHash($id, $finalUrl);
                                
                                // Also resolve image URL if it's a short link
                                if ($originalImage && self::isShortUrl($originalImage)) {
                                    $imageResult = self::resolveUrl($originalImage, $timeout, $connectTimeout);
                                    if ($imageResult['success'] && $imageResult['final_url'] !== $originalImage) {
                                        $urlPreviewModel->updateImage($id, $imageResult['final_url']);
                                        error_log("ShortLinkResolver: Image also resolved: {$originalImage} -> {$imageResult['final_url']}");
                                    }
                                }
                                
                                $results['resolved']++;
                                error_log("ShortLinkResolver: ✓ {$originalUrl} -> {$finalUrl}");
                            }
                        }
                    } else {
                        // Resolution failed - mark with timestamp
                        $urlPreviewModel->markResolveFailed($id);
                        $results['failed']++;
                        error_log("ShortLinkResolver: ✗ {$originalUrl} - {$resolveResult['error']}");
                    }
                    
                    $results['processed']++;
                    
                } catch (\Throwable $e) {
                    $results['errors']++;
                    $results['details'][] = "Error resolving {$originalUrl}: " . $e->getMessage();
                    error_log("ShortLinkResolver: Error resolving {$originalUrl}: " . $e->getMessage());
                    
                    // Mark as failed on exception
                    try {
                        $urlPreviewModel->markResolveFailed($id);
                    } catch (\Throwable $e2) {
                        // Ignore
                    }
                }
                
                // Rate limiting
                if ($rateLimitMs > 0) {
                    usleep($rateLimitMs * 1000);
                }
            }
            
            // Log summary
            error_log(sprintf(
                "ShortLinkResolver: Completed batch - Processed: %d, Resolved: %d, Failed: %d, Skipped: %d, Errors: %d",
                $results['processed'],
                $results['resolved'],
                $results['failed'],
                $results['skipped'],
                $results['errors']
            ));
            
        } catch (\Throwable $e) {
            $results['errors']++;
            $results['details'][] = "Fatal error: " . $e->getMessage();
            error_log("ShortLinkResolver fatal error: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Check if a URL is a known short URL
     * 
     * @param string $url The URL to check
     * @return bool True if it's a known short URL domain
     */
    public static function isShortUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $host = strtolower($parsed['host']);
        
        // Remove www. prefix if present
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        
        return in_array($host, self::$shortenerDomains, true);
    }

    /**
     * Get list of shortener domains
     * 
     * @return array List of shortener domain patterns
     */
    public static function getShortenerDomains(): array
    {
        return self::$shortenerDomains;
    }

    /**
     * Resolve a URL to its final destination
     * 
     * @param string $url The URL to resolve
     * @param int $timeout Request timeout in seconds
     * @param int $connectTimeout Connection timeout in seconds
     * @return array Result with success, final_url, error
     */
    private static function resolveUrl(string $url, int $timeout = 15, int $connectTimeout = 10): array
    {
        // SSRF protection - validate URL
        if (!self::isValidUrl($url)) {
            return [
                'success' => false,
                'final_url' => null,
                'error' => 'Invalid URL or SSRF attempt blocked'
            ];
        }
        
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOBODY => true,  // HEAD request - we only need the final URL
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.app)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            // Restrict to HTTP/HTTPS only (SSRF protection)
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        
        curl_exec($ch);
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlError = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        
        curl_close($ch);
        
        // Check for curl errors
        if ($curlError !== 0) {
            return [
                'success' => false,
                'final_url' => null,
                'error' => self::classifyCurlError($curlError, $curlErrorMsg)
            ];
        }
        
        // Check HTTP status code (accept 2xx and 3xx as success since we followed redirects)
        if ($statusCode >= 200 && $statusCode < 400) {
            // Validate the final URL
            if (!self::isValidUrl($finalUrl)) {
                return [
                    'success' => false,
                    'final_url' => null,
                    'error' => 'Final URL failed validation'
                ];
            }
            
            return [
                'success' => true,
                'final_url' => $finalUrl,
                'error' => null
            ];
        }
        
        // If HEAD fails with 405, try GET
        if ($statusCode === 405) {
            return self::resolveUrlWithGet($url, $timeout, $connectTimeout);
        }
        
        return [
            'success' => false,
            'final_url' => null,
            'error' => "HTTP {$statusCode}"
        ];
    }

    /**
     * Resolve URL using GET request (fallback when HEAD is not supported)
     */
    private static function resolveUrlWithGet(string $url, int $timeout, int $connectTimeout): array
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.app)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Limit body size
            CURLOPT_BUFFERSIZE => 128,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                return ($downloaded > 8192) ? 1 : 0;
            },
        ]);
        
        curl_exec($ch);
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlError = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError !== 0 && $curlError !== CURLE_ABORTED_BY_CALLBACK) {
            return [
                'success' => false,
                'final_url' => null,
                'error' => self::classifyCurlError($curlError, $curlErrorMsg)
            ];
        }
        
        if ($statusCode >= 200 && $statusCode < 400) {
            if (!self::isValidUrl($finalUrl)) {
                return [
                    'success' => false,
                    'final_url' => null,
                    'error' => 'Final URL failed validation'
                ];
            }
            
            return [
                'success' => true,
                'final_url' => $finalUrl,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'final_url' => null,
            'error' => "HTTP {$statusCode}"
        ];
    }

    /**
     * Classify curl error into human-readable message
     */
    private static function classifyCurlError(int $curlError, string $curlErrorMsg): string
    {
        switch ($curlError) {
            case CURLE_COULDNT_RESOLVE_HOST:
                return 'DNS resolution failed';
            case CURLE_COULDNT_CONNECT:
                return 'Connection refused';
            case CURLE_OPERATION_TIMEDOUT:
                return 'Request timed out';
            case CURLE_SSL_CONNECT_ERROR:
            case CURLE_SSL_CERTPROBLEM:
            case CURLE_SSL_CIPHER:
            case CURLE_SSL_CACERT:
                return 'SSL error';
            case CURLE_TOO_MANY_REDIRECTS:
                return 'Too many redirects';
            default:
                return substr($curlErrorMsg, 0, 100) ?: 'Unknown error';
        }
    }

    /**
     * Validate URL and check for SSRF attempts
     * 
     * @param string $url The URL to validate
     * @return bool True if valid and safe
     */
    private static function isValidUrl(string $url): bool
    {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsed = parse_url($url);
        
        // Only allow http and https
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return false;
        }
        
        // Must have a host
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $host = $parsed['host'];
        
        // Block localhost and loopback
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return false;
        }
        
        // Block private IP ranges
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            // Successfully resolved to IP, check if it's private
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        
        return true;
    }
}
