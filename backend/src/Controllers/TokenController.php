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
        
        $db = \Trail\Database\Database::getInstance($this->config);
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
}
