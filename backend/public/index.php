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
use Trail\Controllers\ReportController;
use Trail\Controllers\ClapController;
use Trail\Controllers\CommentController;
use Trail\Controllers\CommentClapController;
use Trail\Controllers\CommentReportController;
use Trail\Controllers\NotificationController;

require __DIR__ . '/../vendor/autoload.php';

// Load configuration (uses secrets.yml)
$config = Config::load(__DIR__ . '/../secrets.yml');

// Create Slim app
$app = AppFactory::create();
$app->addRoutingMiddleware();

// Add error middleware with custom error handler
$errorMiddleware = $app->addErrorMiddleware(
    $config['app']['environment'] === 'development',
    true,
    true
);

// Custom error handler for production
if ($config['app']['environment'] !== 'development') {
    $errorHandler = $errorMiddleware->getDefaultErrorHandler();
    $errorHandler->registerErrorRenderer('text/html', function ($exception, $displayErrorDetails) use ($config) {
        require_once __DIR__ . '/helpers/error.php';
        
        // Determine status code from exception
        $statusCode = 500;
        if (method_exists($exception, 'getCode') && $exception->getCode() >= 400 && $exception->getCode() < 600) {
            $statusCode = $exception->getCode();
        }
        
        return renderErrorPage($statusCode, $config);
    });
}

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
        $userId = $session['user_id'] ?? null;
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
    
    require_once __DIR__ . '/helpers/error.php';
    return sendErrorPage($response, 404, $config);
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
    
    require_once __DIR__ . '/helpers/error.php';
    return sendErrorPage($response, 404, $config);
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
        $userId = $session['user_id'] ?? null;
        $userName = $session['email'] ?? null;
        $userPhotoUrl = $session['photo_url'] ?? null;
        $isAdmin = $session['is_admin'] ?? false;
        $jwtToken = $session['jwt_token'] ?? null;
        
        // Get the nickname from the route
        $nickname = $args['nickname'] ?? null;
        
        // Check if user exists
        $userModel = new \Trail\Models\User($db);
        $user = $userModel->findByNickname($nickname);
        
        if ($user === null) {
            // User not found - show 404 error page
            require_once __DIR__ . '/helpers/error.php';
            return sendErrorPage($response, 404, $config);
        }
        
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
    
    require_once __DIR__ . '/helpers/error.php';
    return sendErrorPage($response, 404, $config);
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
    
    require_once __DIR__ . '/helpers/error.php';
    return sendErrorPage($response, 404, $config);
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
    
    require_once __DIR__ . '/helpers/error.php';
    return sendErrorPage($response, 404, $config);
});

// Health check endpoint
$app->get('/api/health', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Config endpoint - exposes public configuration values
$app->get('/api/config', function ($request, $response) use ($config) {
    $maxTextLength = \Trail\Config\Config::getMaxTextLength($config);
    $response->getBody()->write(json_encode([
        'max_text_length' => $maxTextLength
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Authentication routes with rate limiting
$rateLimitEnabled = $config['security']['rate_limit']['enabled'] ?? true;
$app->post('/api/auth/google', [AuthController::class, 'googleAuth'])
    ->add(new RateLimitMiddleware(5, 300, $rateLimitEnabled)); // 5 attempts per 5 minutes
$app->post('/api/auth/dev', [AuthController::class, 'devAuth']) // Development only
    ->add(new RateLimitMiddleware(10, 60, $rateLimitEnabled)); // 10 attempts per minute (dev only)

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

// Clap routes
$app->post('/api/entries/{id}/claps', [ClapController::class, 'addClap'])->add(new AuthMiddleware($config));
$app->get('/api/entries/{id}/claps', [ClapController::class, 'getClaps']);

// Comment routes
$app->post('/api/entries/{id}/comments', [CommentController::class, 'create'])->add(new AuthMiddleware($config));
$app->get('/api/entries/{id}/comments', [CommentController::class, 'list']);
$app->put('/api/comments/{id}', [CommentController::class, 'update'])->add(new AuthMiddleware($config));
$app->delete('/api/comments/{id}', [CommentController::class, 'delete'])->add(new AuthMiddleware($config));

// Comment clap routes
$app->post('/api/comments/{id}/claps', [CommentClapController::class, 'addClap'])->add(new AuthMiddleware($config));
$app->get('/api/comments/{id}/claps', [CommentClapController::class, 'getClaps']);

// Comment report route
$app->post('/api/comments/{id}/report', [CommentReportController::class, 'reportComment'])->add(new AuthMiddleware($config));

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
    $group->delete('/users/{id}/entries', [AdminController::class, 'deleteUserEntries']);
    $group->delete('/users/{id}/comments', [AdminController::class, 'deleteUserComments']);
    $group->post('/cache/clear', [AdminController::class, 'clearCache']);
    $group->get('/error-logs', [AdminController::class, 'errorLogs']);
    $group->get('/error-stats', [AdminController::class, 'errorStats']);
    $group->post('/error-logs/cleanup', [AdminController::class, 'cleanupErrorLogs']);
    $group->post('/images/prune', [AdminController::class, 'pruneImages']);
})->add(new AuthMiddleware($config, true));

// Public RSS routes
$app->get('/api/rss', [RssController::class, 'globalFeed']);
$app->get('/api/rss/{user_id}', [RssController::class, 'userFeed']);
$app->get('/api/users/{nickname}/rss', [RssController::class, 'userFeedByNickname']);

// Report and moderation routes (authenticated)
$app->post('/api/entries/{id}/report', [ReportController::class, 'reportEntry'])->add(new AuthMiddleware($config));
$app->post('/api/users/{id}/mute', [ReportController::class, 'muteUser'])->add(new AuthMiddleware($config));
$app->delete('/api/users/{id}/mute', [ReportController::class, 'unmuteUser'])->add(new AuthMiddleware($config));
$app->get('/api/users/{id}/mute-status', [ReportController::class, 'checkMuteStatus'])->add(new AuthMiddleware($config));
$app->get('/api/users/{id}/info', [ReportController::class, 'getUserInfo'])->add(new AuthMiddleware($config));
$app->get('/api/filters', [ReportController::class, 'getFilters'])->add(new AuthMiddleware($config));

// Notification Web Pages (require auth)
$app->get('/notifications', [NotificationController::class, 'page'])->add(new AuthMiddleware($config));
$app->get('/notifications/preferences', [NotificationController::class, 'preferencesPage'])->add(new AuthMiddleware($config));

// Notification API Endpoints (require auth)
$app->get('/api/notifications', [NotificationController::class, 'list'])->add(new AuthMiddleware($config));
$app->put('/api/notifications/{id}/read', [NotificationController::class, 'markRead'])->add(new AuthMiddleware($config));
$app->put('/api/notifications/read-all', [NotificationController::class, 'markAllRead'])->add(new AuthMiddleware($config));
$app->delete('/api/notifications/{id}', [NotificationController::class, 'delete'])->add(new AuthMiddleware($config));
$app->get('/api/notifications/preferences', [NotificationController::class, 'getPreferences'])->add(new AuthMiddleware($config));
$app->put('/api/notifications/preferences', [NotificationController::class, 'updatePreferences'])->add(new AuthMiddleware($config));

// Catch-all 404 handler for unmatched routes
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) use ($config) {
    require_once __DIR__ . '/helpers/error.php';
    return sendErrorPage($response, 404, $config);
});

$app->run();
