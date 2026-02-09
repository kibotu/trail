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
        $token = null;
        $fromSession = false;
        $session = null;
        $db = null;

        // Try to get token from Authorization header first
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // Fallback: Try to get token from session
        if (!$token) {
            require_once __DIR__ . '/../../public/helpers/session.php';
            $db = \Trail\Database\Database::getInstance($this->config);
            $session = getAuthenticatedUser($db);
            if ($session && !empty($session['jwt_token'])) {
                $token = $session['jwt_token'];
                $fromSession = true;
            }
        }

        if (!$token) {
            return $this->unauthorized('Missing or invalid authorization');
        }

        $jwtService = new JwtService($this->config);
        $payload = $jwtService->verify($token);

        // Handle JWT refresh for session-based authentication
        if ($fromSession && $session) {
            $shouldRefresh = false;

            // Case 1: JWT is expired or invalid - must refresh
            if (!$payload) {
                $shouldRefresh = true;
            }
            // Case 2: JWT is valid but should be refreshed (sliding window)
            elseif ($jwtService->shouldRefresh($payload)) {
                $shouldRefresh = true;
            }

            // Regenerate JWT if needed
            if ($shouldRefresh) {
                $token = $jwtService->generate(
                    (int)$session['user_id'],
                    $session['email'],
                    (bool)$session['is_admin']
                );
                
                // Update session with new JWT token and extend expiration
                $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
                if ($sessionId && $db) {
                    $newExpiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));
                    $stmt = $db->prepare("UPDATE trail_sessions SET jwt_token = ?, expires_at = ? WHERE session_id = ?");
                    $stmt->execute([$token, $newExpiresAt, $sessionId]);
                }
                
                // Verify the new token
                $payload = $jwtService->verify($token);
            }
        }

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
