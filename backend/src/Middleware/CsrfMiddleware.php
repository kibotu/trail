<?php

declare(strict_types=1);

namespace Trail\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * CSRF Protection Middleware
 * 
 * Validates CSRF tokens for state-changing operations (POST, PUT, DELETE, PATCH).
 * Tokens are stored in the session and must be included in requests.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = 'csrf_token';
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        
        // Only validate CSRF for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return $handler->handle($request);
        }
        
        // Skip CSRF validation for certain endpoints
        $path = $request->getUri()->getPath();
        if ($this->shouldSkipCsrf($path)) {
            return $handler->handle($request);
        }
        
        // Get token from request
        $token = $this->getTokenFromRequest($request);
        
        // Get expected token from session
        $expectedToken = $_SESSION[self::SESSION_KEY] ?? null;
        
        // Validate token
        if (!$token || !$expectedToken || !hash_equals($expectedToken, $token)) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => 'CSRF token validation failed'
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }
        
        return $handler->handle($request);
    }
    
    /**
     * Generate a new CSRF token and store it in the session
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::SESSION_KEY] = $token;
        
        return $token;
    }
    
    /**
     * Get the current CSRF token from the session
     */
    public static function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION[self::SESSION_KEY] ?? null;
    }
    
    /**
     * Extract CSRF token from request
     */
    private function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        // Check header first (for AJAX requests)
        $headerToken = $request->getHeaderLine('X-CSRF-Token');
        if ($headerToken) {
            return $headerToken;
        }
        
        // Check body for form submissions
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['csrf_token'])) {
            return $body['csrf_token'];
        }
        
        // Check JSON body
        $contentType = $request->getHeaderLine('Content-Type');
        if (strpos($contentType, 'application/json') !== false) {
            $jsonBody = json_decode((string) $request->getBody(), true);
            if (is_array($jsonBody) && isset($jsonBody['csrf_token'])) {
                return $jsonBody['csrf_token'];
            }
        }
        
        return null;
    }
    
    /**
     * Determine if CSRF validation should be skipped for this path
     */
    private function shouldSkipCsrf(string $path): bool
    {
        // Skip CSRF for authentication endpoints (they use other protections)
        $skipPaths = [
            '/api/auth/google',
            '/api/auth/dev',
            '/api/auth/logout',
        ];
        
        foreach ($skipPaths as $skipPath) {
            if ($path === $skipPath || strpos($path, $skipPath) === 0) {
                return true;
            }
        }
        
        return false;
    }
}
