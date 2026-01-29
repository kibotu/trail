<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Trail\Services\JwtService;

/**
 * Token Controller
 * 
 * Provides secure token management endpoints that avoid exposing tokens in JavaScript.
 * Uses httpOnly cookies for token storage.
 */
class TokenController
{
    private array $config;
    private JwtService $jwtService;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->jwtService = new JwtService($config);
    }

    /**
     * Get current user's token info (without exposing the actual token).
     * Returns user info that can be safely used in JavaScript.
     */
    public function getTokenInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get session from cookie
        require_once __DIR__ . '/../../public/helpers/session.php';
        
        $db = \Trail\Database\Database::getInstance($this->config)->getConnection();
        $session = getAuthenticatedUser($db);

        if (!$session) {
            $response->getBody()->write(json_encode([
                'authenticated' => false
            ]));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Return user info without exposing the JWT token
        $response->getBody()->write(json_encode([
            'authenticated' => true,
            'user' => [
                'email' => $session['email'],
                'is_admin' => (bool) $session['is_admin'],
                'user_id' => $session['user_id']
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Set JWT token in httpOnly cookie.
     * This should be called during login/authentication.
     */
    public function setTokenCookie(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get session from cookie
        require_once __DIR__ . '/../../public/helpers/session.php';
        
        $db = \Trail\Database\Database::getInstance($this->config)->getConnection();
        $session = getAuthenticatedUser($db);

        if (!$session) {
            $response->getBody()->write(json_encode([
                'error' => 'Not authenticated'
            ]));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Generate JWT token if not already present
        $jwtToken = $session['jwt_token'];
        if (!$jwtToken) {
            $jwtToken = $this->jwtService->generate(
                (int) $session['user_id'],
                $session['email'],
                (bool) $session['is_admin']
            );

            // Update session with JWT token
            $stmt = $db->prepare("UPDATE trail_sessions SET jwt_token = ? WHERE session_id = ?");
            $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
            $stmt->execute([$jwtToken, $sessionId]);
        }

        // Set JWT token in httpOnly cookie
        $expiryHours = $this->config['jwt']['expiry_hours'] ?? 168; // 7 days default
        $expiresAt = time() + ($expiryHours * 3600);

        // Set the cookie
        setcookie(
            'trail_jwt',
            $jwtToken,
            [
                'expires' => $expiresAt,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Token set in secure cookie'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
