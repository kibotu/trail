<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Trail\Services\JwtService;

class AuthMiddleware implements MiddlewareInterface
{
    private array $config;
    private bool $requireAdmin;

    public function __construct(array $config, bool $requireAdmin = false)
    {
        $this->config = $config;
        $this->requireAdmin = $requireAdmin;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Missing or invalid authorization header');
        }

        $token = $matches[1];
        $jwtService = new JwtService($this->config);
        $payload = $jwtService->verify($token);

        if (!$payload) {
            return $this->unauthorized('Invalid or expired token');
        }

        if ($this->requireAdmin && !($payload['is_admin'] ?? false)) {
            return $this->forbidden('Admin access required');
        }

        // Add user info to request attributes
        $request = $request
            ->withAttribute('user_id', $payload['user_id'])
            ->withAttribute('email', $payload['email'])
            ->withAttribute('is_admin', $payload['is_admin'] ?? false);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    private function forbidden(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }
}
