<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Trail\Config\Config;
use Trail\Middleware\CorsMiddleware;
use Trail\Middleware\SecurityMiddleware;
use Trail\Middleware\RateLimitMiddleware;
use Trail\Middleware\CsrfMiddleware;
use Trail\Middleware\AuthMiddleware;
use Trail\Controllers\AuthController;
use Trail\Controllers\EntryController;
use Trail\Controllers\AdminController;
use Trail\Controllers\RssController;
use Trail\Controllers\ProfileController;
use Trail\Controllers\ImageUploadController;
use Trail\Controllers\TokenController;

require __DIR__ . '/../vendor/autoload.php';

// Load configuration (uses secrets.yml)
$config = Config::load(__DIR__ . '/../secrets.yml');

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
// Note: Rate limiting is applied per-route, not globally

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

// Profile page - Show current user's profile
$app->get('/profile', function ($request, $response) use ($config) {
    $profilePage = __DIR__ . '/../templates/public/profile.php';
    if (file_exists($profilePage)) {
        require_once __DIR__ . '/helpers/session.php';
        
        // Check if user is logged in
        $db = \Trail\Database\Database::getInstance($config);
        $session = getAuthenticatedUser($db);
        $isLoggedIn = $session !== null;
        
        if (!$isLoggedIn) {
            // Redirect to home if not logged in
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }
        
        $userName = $session['email'] ?? null;
        $userPhotoUrl = $session['photo_url'] ?? null;
        $isAdmin = $session['is_admin'] ?? false;
        $jwtToken = $session['jwt_token'] ?? null;
        
        ob_start();
        include $profilePage;
        $html = ob_get_clean();
        $response->getBody()->write($html);
        
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
    
    $response->getBody()->write('Profile page not found');
    return $response->withStatus(404);
});

// User page - Show entries by nickname
$app->get('/@{nickname}', function ($request, $response, array $args) use ($config) {
    $userPage = __DIR__ . '/../templates/public/user.php';
    if (file_exists($userPage)) {
        require_once __DIR__ . '/helpers/session.php';
        
        // Check if user is logged in
        $db = \Trail\Database\Database::getInstance($config);
        $session = getAuthenticatedUser($db);
        $isLoggedIn = $session !== null;
        $userName = $session['email'] ?? null;
        $userPhotoUrl = $session['photo_url'] ?? null;
        $isAdmin = $session['is_admin'] ?? false;
        $jwtToken = $session['jwt_token'] ?? null;
        
        // Get the nickname from the route
        $nickname = $args['nickname'] ?? null;
        
        // Build Google OAuth URL for the login button (only if not logged in)
        $googleOAuth = $config['google_oauth'] ?? null;
        $googleAuthUrl = null;
        
        if ($googleOAuth !== null && !$isLoggedIn) {
            $googleAuthUrl = buildGoogleAuthUrl($googleOAuth);
        }
        
        ob_start();
        include $userPage;
        $html = ob_get_clean();
        $response->getBody()->write($html);
        
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
    
    $response->getBody()->write('User page not found');
    return $response->withStatus(404);
});

// Status page - Show single entry
$app->get('/status/{id}', function ($request, $response, array $args) use ($config) {
    $statusPage = __DIR__ . '/../templates/public/status.php';
    if (file_exists($statusPage)) {
        require_once __DIR__ . '/helpers/session.php';
        
        // Check if user is logged in
        $db = \Trail\Database\Database::getInstance($config);
        $session = getAuthenticatedUser($db);
        $isLoggedIn = $session !== null;
        $userName = $session['email'] ?? null;
        $userPhotoUrl = $session['photo_url'] ?? null;
        $isAdmin = $session['is_admin'] ?? false;
        $jwtToken = $session['jwt_token'] ?? null;
        
        // Get the hash ID from the route (not decoded here, will be decoded by API)
        $hashId = $args['id'] ?? '';
        
        // Build Google OAuth URL for the login button (only if not logged in)
        $googleOAuth = $config['google_oauth'] ?? null;
        $googleAuthUrl = null;
        
        if ($googleOAuth !== null && !$isLoggedIn) {
            $googleAuthUrl = buildGoogleAuthUrl($googleOAuth);
        }
        
        ob_start();
        include $statusPage;
        $html = ob_get_clean();
        $response->getBody()->write($html);
        
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
    
    $response->getBody()->write('Status page not found');
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

// Authentication routes with rate limiting
$app->post('/api/auth/google', [AuthController::class, 'googleAuth'])
    ->add(new RateLimitMiddleware(5, 300)); // 5 attempts per 5 minutes
$app->post('/api/auth/dev', [AuthController::class, 'devAuth']) // Development only
    ->add(new RateLimitMiddleware(10, 60)); // 10 attempts per minute (dev only)

// Session info endpoint (returns user info without exposing JWT)
$app->get('/api/auth/session', [TokenController::class, 'getTokenInfo']);

// Logout endpoint - clears session and cookies
$app->post('/api/auth/logout', function ($request, $response) use ($config) {
    require_once __DIR__ . '/helpers/session.php';
    $db = Database::getInstance($config);
    
    // Clear session from database
    $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
    if ($sessionId) {
        deleteSession($db, $sessionId);
    }
    
    // Clear session cookie
    clearSessionCookie();
    
    // Clear JWT cookie
    setcookie('trail_jwt', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    $response->getBody()->write(json_encode(['success' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Profile routes (authenticated)
$app->get('/api/profile', [ProfileController::class, 'getProfile'])->add(new AuthMiddleware($config));
$app->put('/api/profile', [ProfileController::class, 'updateProfile'])->add(new AuthMiddleware($config));

// Public profile route
$app->get('/api/users/{nickname}', [ProfileController::class, 'getPublicProfile']);

// Authenticated entry routes (create, update, delete)
$app->post('/api/entries', [EntryController::class, 'create'])->add(new AuthMiddleware($config));
$app->put('/api/entries/{id}', [EntryController::class, 'update'])->add(new AuthMiddleware($config));
$app->delete('/api/entries/{id}', [EntryController::class, 'delete'])->add(new AuthMiddleware($config));

// Public entry routes (must be after authenticated routes to avoid conflicts)
$app->get('/api/entries', [EntryController::class, 'listPublic']);
$app->get('/api/entries/{id}', [EntryController::class, 'getById']);

// User entries by nickname
$app->get('/api/users/{nickname}/entries', [EntryController::class, 'listByNickname']);

// Image upload routes (authenticated)
$app->post('/api/images/upload/init', [ImageUploadController::class, 'initUpload'])->add(new AuthMiddleware($config));
$app->post('/api/images/upload/chunk', [ImageUploadController::class, 'uploadChunk'])->add(new AuthMiddleware($config));
$app->post('/api/images/upload/complete', [ImageUploadController::class, 'completeUpload'])->add(new AuthMiddleware($config));
$app->delete('/api/images/{id}', [ImageUploadController::class, 'deleteImage'])->add(new AuthMiddleware($config));

// Public image serving
$app->get('/api/images/{id}', [ImageUploadController::class, 'serveImage']);

// Admin routes (authenticated + admin)
$app->group('/api/admin', function ($group) {
    $group->get('/entries', [AdminController::class, 'entries']);
    $group->post('/entries/{id}', [AdminController::class, 'updateEntry']);
    $group->delete('/entries/{id}', [AdminController::class, 'deleteEntry']);
    $group->get('/users', [AdminController::class, 'users']);
    $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
    $group->post('/cache/clear', [AdminController::class, 'clearCache']);
})->add(new AuthMiddleware($config, true));

// Public RSS routes
$app->get('/api/rss', [RssController::class, 'globalFeed']);
$app->get('/api/rss/{user_id}', [RssController::class, 'userFeed']);

$app->run();
