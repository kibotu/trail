<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $origin = $request->getHeaderLine('Origin');
        $allowedOrigin = $this->resolveOrigin($origin);

        if ($allowedOrigin === null) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '3600');

        if ($allowedOrigin !== '*') {
            $response = $response->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    private function resolveOrigin(string $origin): ?string
    {
        if ($origin === '') {
            return null;
        }

        $baseUrl = $this->config['app']['base_url'] ?? '';
        $allowedOrigins = array_filter([
            $baseUrl,
            'http://localhost:8000',
            'http://localhost:3000',
        ]);

        // Same-origin and configured origins get credentialed access
        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        // Public read-only API endpoints are available to any origin
        // (needed for embed widgets and mobile apps using Bearer tokens)
        return '*';
    }
}
