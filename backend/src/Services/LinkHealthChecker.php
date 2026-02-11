<?php

declare(strict_types=1);

namespace Trail\Services;

use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Models\LinkHealth;

/**
 * LinkHealthChecker - Service for checking HTTP health of URLs
 * 
 * Performs HEAD/GET requests to check if URLs are accessible.
 * Uses a 3-strike threshold before marking links as broken.
 */
class LinkHealthChecker
{
    /**
     * Run link health check on a batch of URLs
     * 
     * @param int|null $batchSize Number of URLs to check (null = use config default)
     * @return array Results with checked, healthy, broken, errors counts
     */
    public static function run(?int $batchSize = null): array
    {
        $results = [
            'checked' => 0,
            'healthy' => 0,
            'broken' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            $linkHealthModel = new LinkHealth($db);
            
            // Get batch size from config if not provided
            if ($batchSize === null) {
                $batchSize = $config['link_health']['batch_size'] ?? 50;
            }
            
            // Get URLs to check
            $urlsToCheck = $linkHealthModel->getUrlsToCheck($batchSize);
            
            if (empty($urlsToCheck)) {
                return $results;
            }
            
            // Get config values
            $rateLimitMs = $config['link_health']['rate_limit_ms'] ?? 500;
            $failureThreshold = $config['link_health']['failure_threshold'] ?? 3;
            
            // Increase execution time limit for larger batches
            if ($batchSize > 50) {
                set_time_limit(max(60, $batchSize * 2));
            }
            
            foreach ($urlsToCheck as $index => $urlRecord) {
                $urlPreviewId = (int) $urlRecord['id'];
                $url = $urlRecord['url'];
                
                try {
                    // Check the URL
                    $checkResult = self::checkUrl($url, $config);
                    
                    // Get current health record to determine consecutive failures
                    $currentHealth = $linkHealthModel->findByUrlPreviewId($urlPreviewId);
                    $currentFailures = $currentHealth ? (int) $currentHealth['consecutive_failures'] : 0;
                    
                    if ($checkResult['is_healthy']) {
                        // URL is healthy - reset failures
                        $linkHealthModel->upsert($urlPreviewId, [
                            'http_status_code' => $checkResult['status_code'],
                            'error_type' => 'none',
                            'error_message' => null,
                            'consecutive_failures' => 0,
                            'last_healthy_at' => date('Y-m-d H:i:s'),
                            'is_broken' => false
                        ]);
                        
                        $results['healthy']++;
                        error_log("LinkHealthChecker: ✓ {$url} - HTTP {$checkResult['status_code']}");
                    } else {
                        // URL failed - increment failures
                        $newFailures = $currentFailures + 1;
                        $isBroken = $newFailures >= $failureThreshold;
                        
                        $linkHealthModel->upsert($urlPreviewId, [
                            'http_status_code' => $checkResult['status_code'] ?? 0,
                            'error_type' => $checkResult['error_type'],
                            'error_message' => $checkResult['error_message'],
                            'consecutive_failures' => $newFailures,
                            'last_healthy_at' => $currentHealth['last_healthy_at'] ?? null,
                            'is_broken' => $isBroken
                        ]);
                        
                        if ($isBroken) {
                            $results['broken']++;
                        }
                        
                        error_log("LinkHealthChecker: ✗ {$url} - {$checkResult['error_type']} ({$newFailures}/{$failureThreshold} failures)" . ($isBroken ? " [MARKED BROKEN]" : ""));
                    }
                    
                    $results['checked']++;
                    
                } catch (\Throwable $e) {
                    $results['errors']++;
                    $results['details'][] = "Error checking {$url}: " . $e->getMessage();
                    error_log("LinkHealthChecker: Error checking {$url}: " . $e->getMessage());
                }
                
                // Rate limiting - be a good citizen
                if ($rateLimitMs > 0) {
                    usleep($rateLimitMs * 1000); // Convert ms to microseconds
                }
            }
            
            // Log summary
            error_log(sprintf(
                "LinkHealthChecker: Completed batch - Checked: %d, Healthy: %d, Broken: %d, Errors: %d",
                $results['checked'],
                $results['healthy'],
                $results['broken'],
                $results['errors']
            ));
            
        } catch (\Throwable $e) {
            $results['errors']++;
            $results['details'][] = "Fatal error: " . $e->getMessage();
            error_log("LinkHealthChecker fatal error: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Recheck only broken/failing links
     * 
     * @param int|null $batchSize Number of URLs to check (null = use config default)
     * @return array Results with checked, healthy, broken, errors counts
     */
    public static function recheckBroken(?int $batchSize = null): array
    {
        $results = [
            'checked' => 0,
            'healthy' => 0,
            'broken' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            $linkHealthModel = new LinkHealth($db);
            
            // Get batch size from config if not provided
            if ($batchSize === null) {
                $batchSize = $config['link_health']['batch_size'] ?? 50;
            }
            
            // Get only broken URLs to recheck
            $urlsToCheck = $linkHealthModel->getBrokenUrlsToRecheck($batchSize);
            
            if (empty($urlsToCheck)) {
                return $results;
            }
            
            // Get config values
            $rateLimitMs = $config['link_health']['rate_limit_ms'] ?? 500;
            $failureThreshold = $config['link_health']['failure_threshold'] ?? 3;
            
            // Increase execution time limit for larger batches
            if ($batchSize > 50) {
                set_time_limit(max(60, $batchSize * 2));
            }
            
            foreach ($urlsToCheck as $index => $urlRecord) {
                $urlPreviewId = (int) $urlRecord['id'];
                $url = $urlRecord['url'];
                
                try {
                    // Check the URL
                    $checkResult = self::checkUrl($url, $config);
                    
                    // Get current health record to determine consecutive failures
                    $currentHealth = $linkHealthModel->findByUrlPreviewId($urlPreviewId);
                    $currentFailures = $currentHealth ? (int) $currentHealth['consecutive_failures'] : 0;
                    
                    if ($checkResult['is_healthy']) {
                        // URL is healthy - reset failures
                        $linkHealthModel->upsert($urlPreviewId, [
                            'http_status_code' => $checkResult['status_code'],
                            'error_type' => 'none',
                            'error_message' => null,
                            'consecutive_failures' => 0,
                            'last_healthy_at' => date('Y-m-d H:i:s'),
                            'is_broken' => false
                        ]);
                        
                        $results['healthy']++;
                        error_log("LinkHealthChecker: ✓ {$url} - HTTP {$checkResult['status_code']} [RECOVERED]");
                    } else {
                        // URL failed - increment failures
                        $newFailures = $currentFailures + 1;
                        $isBroken = $newFailures >= $failureThreshold;
                        
                        $linkHealthModel->upsert($urlPreviewId, [
                            'http_status_code' => $checkResult['status_code'] ?? 0,
                            'error_type' => $checkResult['error_type'],
                            'error_message' => $checkResult['error_message'],
                            'consecutive_failures' => $newFailures,
                            'last_healthy_at' => $currentHealth['last_healthy_at'] ?? null,
                            'is_broken' => $isBroken
                        ]);
                        
                        if ($isBroken) {
                            $results['broken']++;
                        }
                        
                        error_log("LinkHealthChecker: ✗ {$url} - {$checkResult['error_type']} ({$newFailures}/{$failureThreshold} failures)" . ($isBroken ? " [STILL BROKEN]" : ""));
                    }
                    
                    $results['checked']++;
                    
                } catch (\Throwable $e) {
                    $results['errors']++;
                    $results['details'][] = "Error checking {$url}: " . $e->getMessage();
                    error_log("LinkHealthChecker: Error checking {$url}: " . $e->getMessage());
                }
                
                // Rate limiting - be a good citizen
                if ($rateLimitMs > 0) {
                    usleep($rateLimitMs * 1000); // Convert ms to microseconds
                }
            }
            
            // Log summary
            error_log(sprintf(
                "LinkHealthChecker (recheck broken): Completed batch - Checked: %d, Healthy: %d, Broken: %d, Errors: %d",
                $results['checked'],
                $results['healthy'],
                $results['broken'],
                $results['errors']
            ));
            
        } catch (\Throwable $e) {
            $results['errors']++;
            $results['details'][] = "Fatal error: " . $e->getMessage();
            error_log("LinkHealthChecker (recheck broken) fatal error: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Check a single URL via HTTP HEAD/GET request
     * 
     * @param string $url The URL to check
     * @param array $config Configuration array
     * @return array Result with is_healthy, status_code, error_type, error_message
     */
    private static function checkUrl(string $url, array $config): array
    {
        // SSRF protection - validate URL
        if (!self::isValidUrl($url)) {
            return [
                'is_healthy' => false,
                'status_code' => 0,
                'error_type' => 'unknown',
                'error_message' => 'Invalid URL or SSRF attempt blocked'
            ];
        }
        
        // Try HEAD request first
        $result = self::performRequest($url, true, $config);
        
        // If HEAD returns 405 Method Not Allowed, retry with GET
        if (!$result['is_healthy'] && $result['status_code'] === 405) {
            $result = self::performRequest($url, false, $config);
        }
        
        return $result;
    }
    
    /**
     * Perform HTTP request (HEAD or GET)
     * 
     * @param string $url The URL to check
     * @param bool $headOnly Use HEAD request (true) or GET (false)
     * @param array $config Configuration array
     * @return array Result with is_healthy, status_code, error_type, error_message
     */
    private static function performRequest(string $url, bool $headOnly, array $config): array
    {
        $timeout = $config['link_health']['timeout_seconds'] ?? 15;
        $connectTimeout = $config['link_health']['connect_timeout_seconds'] ?? 10;
        
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
            // Restrict to HTTP/HTTPS only (SSRF protection)
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        
        if ($headOnly) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } else {
            // For GET, limit body size to avoid downloading large files
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                // Stop after 8KB
                return ($downloaded > 8192) ? 1 : 0;
            });
        }
        
        curl_exec($ch);
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        
        curl_close($ch);
        
        // Check for curl errors
        if ($curlError !== 0) {
            return self::classifyCurlError($curlError, $curlErrorMsg);
        }
        
        // Check HTTP status code
        if ($statusCode >= 200 && $statusCode < 400) {
            return [
                'is_healthy' => true,
                'status_code' => $statusCode,
                'error_type' => 'none',
                'error_message' => null
            ];
        }
        
        return [
            'is_healthy' => false,
            'status_code' => $statusCode,
            'error_type' => 'http_error',
            'error_message' => "HTTP {$statusCode}"
        ];
    }
    
    /**
     * Classify curl error into error_type enum
     * 
     * @param int $curlError The curl error number
     * @param string $curlErrorMsg The curl error message
     * @return array Result with is_healthy=false, status_code=0, error_type, error_message
     */
    private static function classifyCurlError(int $curlError, string $curlErrorMsg): array
    {
        $errorType = 'unknown';
        
        switch ($curlError) {
            case CURLE_COULDNT_RESOLVE_HOST:
                $errorType = 'dns_error';
                break;
            case CURLE_COULDNT_CONNECT:
                $errorType = 'connection_refused';
                break;
            case CURLE_OPERATION_TIMEDOUT:
                $errorType = 'timeout';
                break;
            case CURLE_SSL_CONNECT_ERROR:
            case CURLE_SSL_CERTPROBLEM:
            case CURLE_SSL_CIPHER:
            case CURLE_SSL_CACERT:
            case CURLE_SSL_CACERT_BADFILE:
            case CURLE_SSL_SHUTDOWN_FAILED:
            case CURLE_SSL_CRL_BADFILE:
            case CURLE_SSL_ISSUER_ERROR:
                $errorType = 'ssl_error';
                break;
            case CURLE_TOO_MANY_REDIRECTS:
                $errorType = 'redirect_loop';
                break;
            default:
                $errorType = 'unknown';
        }
        
        return [
            'is_healthy' => false,
            'status_code' => 0,
            'error_type' => $errorType,
            'error_message' => substr($curlErrorMsg, 0, 500)
        ];
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
