<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Trail\Database\Database;
use Trail\Services\RateLimitService;

class RateLimitMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config['security']['rate_limit']['enabled']) {
            return $handler->handle($request);
        }

        $db = Database::getInstance($this->config);
        $rateLimitService = new RateLimitService($db, $this->config);

        // Get identifier (IP address or user ID from JWT)
        $identifier = $this->getIdentifier($request);
        $endpoint = $request->getUri()->getPath();

        if (!$rateLimitService->checkLimit($identifier, $endpoint)) {
            $retryAfter = $rateLimitService->getRetryAfter($identifier, $endpoint);
            
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => 'Too many requests',
                'retry_after' => $retryAfter
            ]));
            
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $retryAfter);
        }

        return $handler->handle($request);
    }

    private function getIdentifier(ServerRequestInterface $request): string
    {
        // Try to get user ID from JWT if present
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            // For now, use IP. In production, decode JWT to get user_id
            // This would require JwtService here
        }

        // Fall back to IP address
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
