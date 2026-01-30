<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Rate Limiting Middleware
 * 
 * Limits the number of requests from a single IP address within a time window.
 * Uses a simple in-memory store (can be upgraded to Redis for production).
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $windowSeconds;
    private bool $enabled;
    private static array $attempts = [];
    
    /**
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @param bool $enabled Whether rate limiting is enabled
     */
    public function __construct(int $maxAttempts = 5, int $windowSeconds = 300, bool $enabled = true)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->enabled = $enabled;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip rate limiting if disabled
        if (!$this->enabled) {
            return $handler->handle($request);
        }
        
        $ip = $this->getClientIp($request);
        $key = $this->getKey($ip, $request->getUri()->getPath());
        
        // Clean up old attempts
        $this->cleanupOldAttempts($key);
        
        // Check if rate limit exceeded
        if ($this->isRateLimited($key)) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => 'Too many requests. Please try again later.',
                'retry_after' => $this->getRetryAfter($key)
            ]));
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $this->getRetryAfter($key));
        }
        
        // Record this attempt
        $this->recordAttempt($key);
        
        return $handler->handle($request);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        // Check for proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            $serverParams = $request->getServerParams();
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Generate cache key for rate limiting
     */
    private function getKey(string $ip, string $path): string
    {
        return 'rate_limit:' . md5($ip . ':' . $path);
    }
    
    /**
     * Check if rate limit is exceeded
     */
    private function isRateLimited(string $key): bool
    {
        if (!isset(self::$attempts[$key])) {
            return false;
        }
        
        return count(self::$attempts[$key]) >= $this->maxAttempts;
    }
    
    /**
     * Record an attempt
     */
    private function recordAttempt(string $key): void
    {
        if (!isset(self::$attempts[$key])) {
            self::$attempts[$key] = [];
        }
        
        self::$attempts[$key][] = time();
    }
    
    /**
     * Clean up attempts outside the time window
     */
    private function cleanupOldAttempts(string $key): void
    {
        if (!isset(self::$attempts[$key])) {
            return;
        }
        
        $cutoff = time() - $this->windowSeconds;
        self::$attempts[$key] = array_filter(
            self::$attempts[$key],
            fn($timestamp) => $timestamp > $cutoff
        );
        
        // Remove key if no attempts left
        if (empty(self::$attempts[$key])) {
            unset(self::$attempts[$key]);
        }
    }
    
    /**
     * Get seconds until rate limit resets
     */
    private function getRetryAfter(string $key): int
    {
        if (!isset(self::$attempts[$key]) || empty(self::$attempts[$key])) {
            return 0;
        }
        
        $oldestAttempt = min(self::$attempts[$key]);
        $resetTime = $oldestAttempt + $this->windowSeconds;
        
        return max(0, $resetTime - time());
    }
}
