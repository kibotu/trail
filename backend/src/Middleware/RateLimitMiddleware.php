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
 * Uses file-based storage so counters persist across PHP processes.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $windowSeconds;
    private bool $enabled;
    private static string $storageDir = '';

    public function __construct(int $maxAttempts = 5, int $windowSeconds = 300, bool $enabled = true)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->enabled = $enabled;

        if (self::$storageDir === '') {
            self::$storageDir = sys_get_temp_dir() . '/trail_rate_limit';
            if (!is_dir(self::$storageDir)) {
                @mkdir(self::$storageDir, 0700, true);
            }
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $ip = $this->getClientIp($request);
        $key = $this->getKey($ip, $request->getUri()->getPath());
        $file = self::$storageDir . '/' . $key;

        $attempts = $this->loadAttempts($file);
        $attempts = $this->pruneOld($attempts);

        if (count($attempts) >= $this->maxAttempts) {
            $retryAfter = $this->getRetryAfter($attempts);
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter
            ]));
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $retryAfter);
        }

        $attempts[] = time();
        $this->saveAttempts($file, $attempts);

        return $handler->handle($request);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        $serverParams = $request->getServerParams();
        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    private function getKey(string $ip, string $path): string
    {
        return hash('sha256', $ip . ':' . $path);
    }

    /** @return int[] */
    private function loadAttempts(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $fh = @fopen($file, 'r');
        if (!$fh) {
            return [];
        }

        flock($fh, LOCK_SH);
        $data = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param int[] $attempts */
    private function saveAttempts(string $file, array $attempts): void
    {
        $fh = @fopen($file, 'c');
        if (!$fh) {
            return;
        }

        flock($fh, LOCK_EX);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode(array_values($attempts)));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /** @param int[] $attempts */
    private function pruneOld(array $attempts): array
    {
        $cutoff = time() - $this->windowSeconds;
        return array_values(array_filter($attempts, fn(int $t) => $t > $cutoff));
    }

    /** @param int[] $attempts */
    private function getRetryAfter(array $attempts): int
    {
        if (empty($attempts)) {
            return 0;
        }
        $oldest = min($attempts);
        return max(0, ($oldest + $this->windowSeconds) - time());
    }

    /**
     * Purge stale rate-limit files (call from a cron job).
     */
    public static function cleanup(): void
    {
        if (self::$storageDir === '' || !is_dir(self::$storageDir)) {
            return;
        }
        $cutoff = time() - 7200; // 2 hours
        foreach (glob(self::$storageDir . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
