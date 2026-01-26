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

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->safeLoad();

try {
    $config = Config::load(__DIR__ . '/../../config.yml');
    $db = Database::getInstance($config);

    // Get session ID from cookie
    $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;

    if ($sessionId !== null && validateSessionIdFormat($sessionId)) {
        // Delete session from database
        deleteSession($db, $sessionId);
    }

    // Clear session cookie
    clearSessionCookie();

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
}

// Redirect to login page
header('Location: /admin/login.php');
exit;
