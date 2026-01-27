<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Trail\Config\Config;
use Trail\Middleware\CorsMiddleware;
use Trail\Middleware\SecurityMiddleware;
use Trail\Middleware\RateLimitMiddleware;
use Trail\Middleware\AuthMiddleware;
use Trail\Controllers\AuthController;
use Trail\Controllers\EntryController;
use Trail\Controllers\AdminController;
use Trail\Controllers\RssController;

require __DIR__ . '/../vendor/autoload.php';

// Load configuration (uses secrets.yml)
$config = Config::load(__DIR__ . '/../config.yml');

// Create Slim app
$app = AppFactory::create();
$app->addRoutingMiddleware();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $config['app']['environment'] === 'development',
    true,
    true
);

// Add global middleware
$app->add(new CorsMiddleware());
$app->add(new SecurityMiddleware($config));
$app->add(new RateLimitMiddleware($config));

// Root endpoint - Show public entries landing page
$app->get('/', function ($request, $response) use ($config) {
    $landingPage = __DIR__ . '/../templates/public/landing.php';
    if (file_exists($landingPage)) {
        require_once __DIR__ . '/helpers/session.php';
        
        // Check if user is logged in
        $db = \Trail\Database\Database::getInstance($config);
        $session = getAuthenticatedUser($db);
        $isLoggedIn = $session !== null;
        $userName = $session['email'] ?? null;
        $userPhotoUrl = $session['photo_url'] ?? null;
        $isAdmin = $session['is_admin'] ?? false;
        $jwtToken = $session['jwt_token'] ?? null;
        
        // Build Google OAuth URL for the login button (only if not logged in)
        $googleOAuth = $config['google_oauth'] ?? null;
        $googleAuthUrl = null;
        
        if ($googleOAuth !== null && !$isLoggedIn) {
            $googleAuthUrl = buildGoogleAuthUrl($googleOAuth);
        }
        
        ob_start();
        include $landingPage;
        $html = ob_get_clean();
        $response->getBody()->write($html);
        
        // Add cache control headers to prevent caching of session-dependent content
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
    
    $response->getBody()->write('Landing page not found');
    return $response->withStatus(404);
});

// API Documentation endpoint - serve static file
$app->get('/api', function ($request, $response) {
    $docsFile = __DIR__ . '/api-docs.php';
    if (file_exists($docsFile)) {
        ob_start();
        include $docsFile;
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
    
    $response->getBody()->write('API documentation not found');
    return $response->withStatus(404);
});

// Health check endpoint
$app->get('/api/health', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Authentication routes
$app->post('/api/auth/google', [AuthController::class, 'googleAuth']);
$app->post('/api/auth/dev', [AuthController::class, 'devAuth']); // Development only

// Authenticated entry routes (create, update, delete)
$app->post('/api/entries', [EntryController::class, 'create'])->add(new AuthMiddleware($config));
$app->put('/api/entries/{id}', [EntryController::class, 'update'])->add(new AuthMiddleware($config));
$app->delete('/api/entries/{id}', [EntryController::class, 'delete'])->add(new AuthMiddleware($config));

// Public entry routes (must be after authenticated routes to avoid conflicts)
$app->get('/api/entries', [EntryController::class, 'listPublic']);

// Admin routes (authenticated + admin)
$app->group('/api/admin', function ($group) {
    $group->get('/entries', [AdminController::class, 'entries']);
    $group->post('/entries/{id}', [AdminController::class, 'updateEntry']);
    $group->delete('/entries/{id}', [AdminController::class, 'deleteEntry']);
    $group->get('/users', [AdminController::class, 'users']);
    $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
})->add(new AuthMiddleware($config, true));

// Public RSS routes
$app->get('/api/rss', [RssController::class, 'globalFeed']);
$app->get('/api/rss/{user_id}', [RssController::class, 'userFeed']);

$app->run();
