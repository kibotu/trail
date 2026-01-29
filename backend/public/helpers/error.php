<?php

declare(strict_types=1);

/**
 * Error Page Rendering Helper
 * 
 * Provides functions to render user-friendly error pages and log errors.
 */

/**
 * Log error to database securely.
 * 
 * @param int $statusCode HTTP status code
 * @param array $config Application configuration
 * @param int|null $userId User ID if authenticated
 */
function logErrorToDatabase(int $statusCode, array $config, ?int $userId = null): void
{
    try {
        $db = \Trail\Database\Database::getInstance($config);
        $errorLogService = new \Trail\Services\ErrorLogService($db);
        
        // Get request information
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Get client IP address (handle proxies)
        $ipAddress = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }
        
        // Log the error
        $errorLogService->logError(
            $statusCode,
            $url,
            $referer,
            $userAgent,
            $userId,
            $ipAddress
        );
    } catch (\Exception $e) {
        // Silently fail - error logging should never break the app
        error_log("Failed to log error to database: " . $e->getMessage());
    }
}

/**
 * Render an error page with the given status code.
 * 
 * @param int $statusCode HTTP status code
 * @param array $config Application configuration
 * @return string HTML content of the error page
 */
function renderErrorPage(int $statusCode, array $config): string
{
    require_once __DIR__ . '/session.php';
    
    // Get user session info
    $db = \Trail\Database\Database::getInstance($config);
    $session = getAuthenticatedUser($db);
    $isLoggedIn = $session !== null;
    $jwtToken = $session['jwt_token'] ?? null;
    $userName = $session['email'] ?? null;
    $userPhotoUrl = $session['photo_url'] ?? null;
    $userId = $session['user_id'] ?? null;
    
    // Log the error to database
    logErrorToDatabase($statusCode, $config, $userId);
    
    // Get admin nickname for changelog link
    $adminNickname = null;
    $hasChangelog = false;
    
    try {
        // Find the first admin user
        $stmt = $db->prepare("
            SELECT nickname 
            FROM trail_users 
            WHERE is_admin = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin && !empty($admin['nickname'])) {
            $adminNickname = $admin['nickname'];
            
            // Check if admin has any public posts (changelog)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM trail_entries 
                WHERE user_id = (SELECT id FROM trail_users WHERE nickname = ? LIMIT 1) 
                AND is_public = 1
            ");
            $stmt->execute([$adminNickname]);
            $result = $stmt->fetch();
            $hasChangelog = ($result && $result['count'] > 0);
        }
    } catch (Exception $e) {
        // Silently fail if there's an issue getting admin info
        error_log("Error getting admin info for error page: " . $e->getMessage());
    }
    
    // Render the error template
    $errorTemplate = __DIR__ . '/../../templates/public/error.php';
    
    if (!file_exists($errorTemplate)) {
        // Fallback if template doesn't exist
        return "<!DOCTYPE html><html><head><title>Error {$statusCode}</title></head><body><h1>Error {$statusCode}</h1><p>An error occurred.</p></body></html>";
    }
    
    ob_start();
    include $errorTemplate;
    return ob_get_clean();
}

/**
 * Send an error response with the error page.
 * 
 * @param \Psr\Http\Message\ResponseInterface $response PSR-7 response object
 * @param int $statusCode HTTP status code
 * @param array $config Application configuration
 * @return \Psr\Http\Message\ResponseInterface Modified response
 */
function sendErrorPage($response, int $statusCode, array $config)
{
    $html = renderErrorPage($statusCode, $config);
    $response->getBody()->write($html);
    
    return $response
        ->withStatus($statusCode)
        ->withHeader('Content-Type', 'text/html')
        ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->withHeader('Pragma', 'no-cache')
        ->withHeader('Expires', '0');
}
