<?php

declare(strict_types=1);

/**
 * Development Login Handler
 * 
 * Bypasses Google OAuth for local development.
 * Only works when APP_ENV=development.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;

// Load environment variables (optional - Docker sets them via env_file)
$envPath = __DIR__ . '/../..';
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->safeLoad();
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    $config = Config::load(__DIR__ . '/../../config.yml');
    $db = Database::getInstance($config);

    // Only allow in development mode
    if (($config['app']['environment'] ?? 'production') !== 'development') {
        throw new Exception("Dev login is only available in development mode");
    }

    // Get requested user email
    $email = $_GET['email'] ?? null;
    if ($email === null) {
        throw new Exception("Email parameter required");
    }

    // Find user in dev_users config
    $devUsers = $config['development']['dev_users'] ?? [];
    $devUser = null;
    
    foreach ($devUsers as $user) {
        if ($user['email'] === $email) {
            $devUser = $user;
            break;
        }
    }

    if ($devUser === null) {
        throw new Exception("User not found in dev_users configuration");
    }

    // Generate a fake Google ID for dev users
    $googleId = 'dev_' . md5($email);
    $name = $devUser['name'] ?? 'Dev User';
    $isAdmin = $devUser['is_admin'] ?? false;

    // Find or create user in database
    $stmt = $db->prepare("SELECT id, is_admin FROM trail_users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $db->prepare("
            UPDATE trail_users 
            SET email = ?, name = ?, is_admin = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE google_id = ?
        ");
        $stmt->execute([$email, $name, $isAdmin ? 1 : 0, $googleId]);
        $userId = $user['id'];
    } else {
        // Create new user
        $stmt = $db->prepare("
            INSERT INTO trail_users (google_id, email, name, gravatar_hash, is_admin) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $gravatarHash = md5(strtolower(trim($email)));
        $stmt->execute([$googleId, $email, $name, $gravatarHash, $isAdmin ? 1 : 0]);
        $userId = (int) $db->lastInsertId();
    }

    // Create new session
    $sessionId = generateSessionId();
    $expiresAt = (new DateTime())->modify('+24 hours');

    createSession($db, $sessionId, $userId, $email, null, $isAdmin, $expiresAt);

    // Set secure session cookie (with secure=false for local dev)
    setcookie(
        SESSION_COOKIE_NAME,
        $sessionId,
        [
            'expires' => $expiresAt->getTimestamp(),
            'path' => '/',
            'domain' => '',
            'secure' => false,  // Allow HTTP for local dev
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    // Redirect to admin dashboard
    header('Location: /admin/');
    exit;

} catch (Exception $e) {
    error_log("Dev login error: " . $e->getMessage());
    header('Location: /admin/login.php?error=' . urlencode($e->getMessage()));
    exit;
}
