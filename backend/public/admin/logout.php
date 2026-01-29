<?php

declare(strict_types=1);

/**
 * Logout Handler
 * 
 * Destroys the session and redirects to login page.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;


try {
    $config = Config::load(__DIR__ . '/../../secrets.yml');
    $db = Database::getInstance($config);

    // Get session ID from cookie
    $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;

    if ($sessionId !== null && validateSessionIdFormat($sessionId)) {
        // Delete session from database
        deleteSession($db, $sessionId);
    }

    // Clear session cookie
    clearSessionCookie();
    
    // Clear JWT cookie
    setcookie('trail_jwt', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
}

// Add cache control headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect to home page with cache-busting parameter
header('Location: /?logout=' . time());
exit;
