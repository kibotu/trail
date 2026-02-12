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
use Trail\Controllers\ApiTokenController;
use Trail\Controllers\ReportController;
use Trail\Controllers\ClapController;
use Trail\Controllers\CommentController;
use Trail\Controllers\CommentClapController;
use Trail\Controllers\CommentReportController;
use Trail\Controllers\NotificationController;
use Trail\Controllers\ViewController;
use Trail\Controllers\TagController;

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
        $userId = $session['user_id'] ?? null;
        $userName = $session['email'] ?? null;
        $userPhotoUrl = $session['photo_url'] ?? null;
        $isAdmin = $session['is_admin'] ?? false;
        $jwtToken = $session['jwt_token'] ?? null;
        
        // Get the hash ID from the route
        $hashId = $args['id'] ?? '';
        
        // Decode hash ID and fetch entry data for meta tags
        $entry = null;
        $baseUrl = $config['app']['base_url'] ?? 'https://trail.services.kibotu.net';
        
        try {
            $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
            $hashIdService = new \Trail\Services\HashIdService($hashSalt);
            $entryId = $hashIdService->decode($hashId);
            
            if ($entryId !== null) {
                $entryModel = new \Trail\Models\Entry($db);
                $entry = $entryModel->findByIdWithImages($entryId, $userId);
                
                // Generate nickname if not set
                if ($entry && empty($entry['user_nickname']) && !empty($entry['google_id'])) {
                    $userModel = new \Trail\Models\User($db);
                    $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
                    $entry['user_nickname'] = $userModel->getOrGenerateNickname(
                        (int) $entry['user_id'],
                        $entry['google_id'],
                        $salt
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log("Status page: Failed to fetch entry for hash {$hashId}: " . $e->getMessage());
            $entry = null;
        }
        
        // If entry not found, show 404
        if ($entry === null) {
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
    $maxImagesPerEntry = \Trail\Config\Config::getMaxImagesPerEntry($config);
    $response->getBody()->write(json_encode([
        'max_text_length' => $maxTextLength,
        'max_images_per_entry' => $maxImagesPerEntry
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Authentication routes - internal use only (used by frontend, not exposed in API docs)
$rateLimitEnabled = $config['security']['rate_limit']['enabled'] ?? true;
$app->post('/api/auth/google', [AuthController::class, 'googleAuth'])
    ->add(new RateLimitMiddleware(5, 300, $rateLimitEnabled)); // 5 attempts per 5 minutes
$app->post('/api/auth/dev', [AuthController::class, 'devAuth']) // Development only
    ->add(new RateLimitMiddleware(10, 60, $rateLimitEnabled)); // 10 attempts per minute (dev only)

// Session info endpoint - internal use only
$app->get('/api/auth/session', [TokenController::class, 'getTokenInfo']);

// Logout endpoint - internal use only
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

// API Token routes (authenticated)
$app->get('/api/token', [\Trail\Controllers\ApiTokenController::class, 'getToken'])->add(new AuthMiddleware($config));
$app->post('/api/token/regenerate', [\Trail\Controllers\ApiTokenController::class, 'regenerateToken'])->add(new AuthMiddleware($config));

// Public profile route
$app->get('/api/users/{nickname}', [ProfileController::class, 'getPublicProfile']);

// Authenticated entry routes (create, update, delete)
$app->post('/api/entries', [EntryController::class, 'create'])->add(new AuthMiddleware($config));
$app->put('/api/entries/{id}', [EntryController::class, 'update'])->add(new AuthMiddleware($config));
$app->delete('/api/entries/{id}', [EntryController::class, 'delete'])->add(new AuthMiddleware($config));

// Entry routes (public read, authenticated write)
$app->get('/api/entries', [EntryController::class, 'listPublic']);
$app->get('/api/entries/{id}', [EntryController::class, 'getById']);

// User entries by nickname (public read)
$app->get('/api/users/{nickname}/entries', [EntryController::class, 'listByNickname']);

// Preview image generation endpoint (public, no auth required for crawlers)
$app->get('/api/preview-image/{id}.png', function ($request, $response, array $args) use ($config) {
    $hashId = $args['id'] ?? '';
    
    try {
        // Decode hash ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new \Trail\Services\HashIdService($hashSalt);
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            error_log("Preview image: Invalid hash ID: {$hashId}");
            $response->getBody()->write('Invalid entry ID');
            return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
        }
        
        error_log("Preview image: Decoded hash {$hashId} to entry ID {$entryId}");
        
        // Fetch entry data
        $db = \Trail\Database\Database::getInstance($config);
        $entryModel = new \Trail\Models\Entry($db);
        $entry = $entryModel->findByIdWithImages($entryId, null);
        
        if (!$entry) {
            error_log("Preview image: Entry not found for ID {$entryId} (hash: {$hashId})");
            $response->getBody()->write('Entry not found');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain');
        }
        
        error_log("Preview image: Found entry {$entryId}, generating preview");
        
        // Generate nickname if not set
        if (empty($entry['user_nickname']) && !empty($entry['google_id'])) {
            $userModel = new \Trail\Models\User($db);
            $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
            $entry['user_nickname'] = $userModel->getOrGenerateNickname(
                (int) $entry['user_id'],
                $entry['google_id'],
                $salt
            );
        }
        
        // Generate or retrieve cached preview
        // Note: Domain points to backend/public/, so paths are relative to public/
        $cacheDir = __DIR__ . '/../storage/preview-cache';
        
        // Try multiple font paths (TTF fonts, then system fonts)
        $fontPaths = [
            __DIR__ . '/assets/fonts/inter/Inter-Regular.ttf',
            __DIR__ . '/assets/fontawesome/webfonts/fa-regular-400.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
        ];
        
        $fontBoldPaths = [
            __DIR__ . '/assets/fonts/inter/Inter-Bold.ttf',
            __DIR__ . '/assets/fontawesome/webfonts/fa-solid-900.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        ];
        
        $fontPath = null;
        foreach ($fontPaths as $path) {
            if (file_exists($path) && filesize($path) > 1000) {
                $fontPath = $path;
                break;
            }
        }
        
        $fontBoldPath = null;
        foreach ($fontBoldPaths as $path) {
            if (file_exists($path) && filesize($path) > 1000) {
                $fontBoldPath = $path;
                break;
            }
        }
        
        if (!$fontPath || !$fontBoldPath) {
            error_log("Preview image: No valid fonts found");
            $response->getBody()->write('Font files not found');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }
        
        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $previewService = new \Trail\Services\PreviewImageService($cacheDir, $fontPath, $fontBoldPath);
        $imagePath = $previewService->getOrGeneratePreview($hashId, $entry);
        
        // Read image file
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            $response->getBody()->write('Failed to read preview image');
            return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
        }
        
        // Generate ETag
        $etag = md5($imageData);
        
        // Check If-None-Match header
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch === $etag) {
            return $response->withStatus(304); // Not Modified
        }
        
        // Return image with caching headers
        $response->getBody()->write($imageData);
        return $response
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Cache-Control', 'public, max-age=604800') // 7 days
            ->withHeader('ETag', $etag)
            ->withHeader('Content-Length', (string) strlen($imageData));
            
    } catch (\Throwable $e) {
        error_log("Preview image generation failed for {$hashId}: " . $e->getMessage());
        $response->getBody()->write('Preview generation failed');
        return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
    }
})->add(new RateLimitMiddleware(60, 60, $rateLimitEnabled)); // 60 req/min

// Clap routes (authenticated write, public read)
$app->post('/api/entries/{id}/claps', [ClapController::class, 'addClap'])->add(new AuthMiddleware($config));
$app->get('/api/entries/{id}/claps', [ClapController::class, 'getClaps']);

// Tag routes (public read, authenticated write)
$app->get('/api/tags', [TagController::class, 'listTags']);
$app->get('/api/tags/{slug}/entries', [TagController::class, 'getEntriesByTag']);
$app->get('/api/entries/{id}/tags', [TagController::class, 'getEntryTags']);
$app->put('/api/entries/{id}/tags', [TagController::class, 'setEntryTags'])->add(new AuthMiddleware($config));
$app->post('/api/entries/{id}/tags', [TagController::class, 'addEntryTag'])->add(new AuthMiddleware($config));
$app->delete('/api/entries/{id}/tags/{slug}', [TagController::class, 'removeEntryTag'])->add(new AuthMiddleware($config));

// Comment routes (authenticated write, public read)
$app->post('/api/entries/{id}/comments', [CommentController::class, 'create'])->add(new AuthMiddleware($config));
$app->get('/api/entries/{id}/comments', [CommentController::class, 'list']);
$app->put('/api/comments/{id}', [CommentController::class, 'update'])->add(new AuthMiddleware($config));
$app->delete('/api/comments/{id}', [CommentController::class, 'delete'])->add(new AuthMiddleware($config));

// Comment clap routes (authenticated write, public read)
$app->post('/api/comments/{id}/claps', [CommentClapController::class, 'addClap'])->add(new AuthMiddleware($config));
$app->get('/api/comments/{id}/claps', [CommentClapController::class, 'getClaps']);

// Comment report route
$app->post('/api/comments/{id}/report', [CommentReportController::class, 'reportComment'])->add(new AuthMiddleware($config));

// View tracking routes (no auth required - anonymous views tracked by IP)
$app->post('/api/entries/{id}/views', [ViewController::class, 'recordEntryView'])
    ->add(new RateLimitMiddleware(120, 60, $rateLimitEnabled)); // 120 req/min
$app->post('/api/comments/{id}/views', [ViewController::class, 'recordCommentView'])
    ->add(new RateLimitMiddleware(120, 60, $rateLimitEnabled)); // 120 req/min
$app->post('/api/users/{nickname}/views', [ViewController::class, 'recordProfileView'])
    ->add(new RateLimitMiddleware(120, 60, $rateLimitEnabled)); // 120 req/min

// Image upload routes (authenticated)
$app->post('/api/images/upload/init', [ImageUploadController::class, 'initUpload'])->add(new AuthMiddleware($config));
$app->post('/api/images/upload/chunk', [ImageUploadController::class, 'uploadChunk'])->add(new AuthMiddleware($config));
$app->post('/api/images/upload/complete', [ImageUploadController::class, 'completeUpload'])->add(new AuthMiddleware($config));
$app->delete('/api/images/{id}', [ImageUploadController::class, 'deleteImage'])->add(new AuthMiddleware($config));

// Image serving (require authentication)
$app->get('/api/images/{id}', [ImageUploadController::class, 'serveImage'])->add(new AuthMiddleware($config));

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
    $group->post('/views/prune', [AdminController::class, 'pruneViews']);
    $group->get('/duplicates', [AdminController::class, 'duplicates']);
    $group->get('/duplicates/stats', [AdminController::class, 'duplicateStats']);
    $group->post('/duplicates/resolve', [AdminController::class, 'resolveDuplicates']);
    $group->post('/duplicates/resolve-all', [AdminController::class, 'resolveAllDuplicates']);
    $group->get('/broken-links', [AdminController::class, 'brokenLinks']);
    $group->get('/broken-links/stats', [AdminController::class, 'brokenLinkStats']);
    $group->post('/broken-links/{id}/dismiss', [AdminController::class, 'dismissBrokenLink']);
    $group->post('/broken-links/{id}/undismiss', [AdminController::class, 'undismissBrokenLink']);
    $group->post('/broken-links/check', [AdminController::class, 'checkBrokenLinks']);
    $group->post('/broken-links/recheck', [AdminController::class, 'recheckBrokenLinks']);
    
    // Short link resolver routes
    $group->get('/short-links', [AdminController::class, 'shortLinks']);
    $group->get('/short-links/stats', [AdminController::class, 'shortLinkStats']);
    $group->post('/short-links/resolve', [AdminController::class, 'resolveShortLinks']);
    
    $group->put('/entries/tags', [TagController::class, 'batchSetTags']);
    
    // Tag management routes
    $group->get('/tags', [TagController::class, 'adminListTags']);
    $group->put('/tags/{id}', [TagController::class, 'adminUpdateTag']);
    $group->delete('/tags/{id}', [TagController::class, 'adminDeleteTag']);
    $group->post('/tags/{id}/merge', [TagController::class, 'adminMergeTag']);
})->add(new AuthMiddleware($config, true));

// Public RSS routes
$app->get('/api/rss', [RssController::class, 'globalFeed']);
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
