<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * CSRF Protection Middleware (Double-Submit Cookie Pattern)
 *
 * Uses a non-httpOnly CSRF cookie paired with an X-CSRF-Token header.
 * The cookie is set on every response; JS reads the cookie and sends
 * the value back via header on state-changing requests.
 *
 * Requests authenticated via Bearer token (mobile/API clients) skip
 * CSRF validation because they are not vulnerable to cross-site attacks.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const TOKEN_LENGTH = 32;
    private const COOKIE_NAME = 'trail_csrf_token';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $path = $request->getUri()->getPath();

            if (!$this->shouldSkipCsrf($request, $path)) {
                $headerToken = $request->getHeaderLine('X-CSRF-Token');
                $cookieToken = $request->getCookieParams()[self::COOKIE_NAME] ?? null;

                if (
                    !$headerToken
                    || !$cookieToken
                    || !hash_equals($cookieToken, $headerToken)
                ) {
                    $response = new Response();
                    $response->getBody()->write(json_encode([
                        'error' => 'CSRF token validation failed'
                    ]));
                    return $response
                        ->withStatus(403)
                        ->withHeader('Content-Type', 'application/json');
                }
            }
        }

        $response = $handler->handle($request);

        // Always ensure the CSRF cookie exists
        $existingToken = $request->getCookieParams()[self::COOKIE_NAME] ?? null;
        if (!$existingToken) {
            $newToken = bin2hex(random_bytes(self::TOKEN_LENGTH));
            $response = $this->setCsrfCookie($response, $newToken);
        }

        return $response;
    }

    /**
     * Read the current CSRF token from the cookie (for embedding in pages).
     */
    public static function getTokenFromCookie(): ?string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }

    /**
     * Generate a fresh CSRF token and set it as a cookie via header().
     * Used during login/session creation before the middleware runs.
     */
    public static function generateAndSetCookie(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => false, // JS must be able to read this
            'samesite' => 'Strict'
        ]);
        return $token;
    }

    private function setCsrfCookie(ResponseInterface $response, string $token): ResponseInterface
    {
        $cookie = self::COOKIE_NAME . '=' . $token
            . '; Path=/; Secure; SameSite=Strict';
        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    private function shouldSkipCsrf(ServerRequestInterface $request, string $path): bool
    {
        // Bearer-token requests (mobile / API clients) are not subject to CSRF
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+.+$/i', $authHeader)) {
            return true;
        }

        $skipPaths = [
            '/api/auth/google',
            '/api/auth/dev',
            '/api/auth/logout',
            // View tracking is anonymous, no session side-effects
            '/api/entries/',  // matched more specifically below
            '/api/comments/', // matched more specifically below
            '/api/users/',    // matched more specifically below
        ];

        // Exact path matches
        if (in_array($path, ['/api/auth/google', '/api/auth/dev', '/api/auth/logout'], true)) {
            return true;
        }

        // View tracking endpoints are anonymous POST with no auth side-effects
        if (preg_match('#^/api/(entries|comments)/[^/]+/views$#', $path)) {
            return true;
        }
        if (preg_match('#^/api/users/[^/]+/views$#', $path)) {
            return true;
        }

        return false;
    }
}
