<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class SecurityMiddleware implements MiddlewareInterface
{
    private array $config;
    private array $suspiciousPatterns = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python-requests'
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip bot protection in development mode
        $isDevelopment = ($this->config['app']['environment'] ?? 'production') === 'development';
        
        if (!$this->config['security']['bot_protection']['enabled'] || $isDevelopment) {
            return $handler->handle($request);
        }

        // Allow public endpoints without restrictions
        $path = $request->getUri()->getPath();
        $publicPaths = ['/', '/api', '/api/health', '/api/entries'];
        
        // Allow RSS feeds (starts with /api/rss) and public entries
        if (in_array($path, $publicPaths) || str_starts_with($path, '/api/rss')) {
            return $handler->handle($request);
        }

        // Check User-Agent
        if ($this->config['security']['bot_protection']['require_user_agent']) {
            $userAgent = $request->getHeaderLine('User-Agent');
            
            if (empty($userAgent)) {
                $response = new Response();
                $response->getBody()->write(json_encode(['error' => 'User-Agent header required']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Check for suspicious patterns
            if ($this->config['security']['bot_protection']['block_suspicious_patterns']) {
                $userAgentLower = strtolower($userAgent);
                foreach ($this->suspiciousPatterns as $pattern) {
                    if (str_contains($userAgentLower, $pattern)) {
                        $response = new Response();
                        $response->getBody()->write(json_encode(['error' => 'Access denied']));
                        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                    }
                }
            }
        }

        return $handler->handle($request);
    }
}
