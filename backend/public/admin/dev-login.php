<?php

declare(strict_types=1);

/**
 * Development Login Handler
 *
 * Creates a session for a configured dev user without Google OAuth.
 * Only available in development mode, from localhost, for a configured dev email.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Services\JwtService;

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $config = Config::load(__DIR__ . '/../../secrets.yml');
    $db = Database::getInstance($config);

    // Requires development mode AND the explicit dev_login.enabled flag
    $isDevelopment = ($config['app']['environment'] ?? 'production') === 'development';
    $devLoginEnabled = ($config['development']['dev_login']['enabled'] ?? false) === true;
    if (!$isDevelopment || !$devLoginEnabled) {
        throw new Exception('Dev login not enabled');
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true)) {
        throw new Exception('Dev login only available from localhost');
    }

    $email = $_GET['email'] ?? null;
    if ($email === null) {
        throw new Exception('No email specified');
    }

    // Look up the dev user in config
    $devUser = null;
    foreach ($config['development']['dev_users'] ?? [] as $u) {
        if ($u['email'] === $email) {
            $devUser = $u;
            break;
        }
    }
    if ($devUser === null) {
        throw new Exception('Dev user not found in configuration');
    }

    $name = $devUser['name'] ?? 'Dev User';
    $isAdmin = $devUser['is_admin'] ?? false;

    // Stable dev google-id so dev users persist across logins
    $googleId = 'dev_' . md5($email);
    $gravatarHash = md5(strtolower(trim($email)));

    // Find or create user
    $stmt = $db->prepare("SELECT id, is_admin, deletion_requested_at FROM trail_users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = (int) $user['id'];
        // Keep the config admin flag authoritative, but don't demote an existing admin
        $isAdmin = $isAdmin || (bool) $user['is_admin'];
    } else {
        // api_token is NOT NULL since migration 026; new users need one
        $apiToken = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO trail_users (google_id, email, name, gravatar_hash, photo_url, is_admin, api_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$googleId, $email, $name, $gravatarHash, null, $isAdmin ? 1 : 0, $apiToken]);
        $userId = (int) $db->lastInsertId();
    }

    // Generate JWT token for API access
    $jwtService = new JwtService($config);
    $jwtToken = $jwtService->generate($userId, $email, $isAdmin);

    // SECURITY: Invalidate all old sessions to prevent session fixation
    $stmt = $db->prepare("DELETE FROM trail_sessions WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Create new session with JWT token
    $sessionId = generateSessionId();
    $expiresAt = (new DateTime())->modify('+30 days');

    createSession($db, $sessionId, $userId, $email, null, $isAdmin, $expiresAt, $jwtToken);
    setSecureSessionCookie($sessionId, $expiresAt->getTimestamp());

    // Set CSRF double-submit cookie for browser sessions
    \Trail\Middleware\CsrfMiddleware::generateAndSetCookie();

    header('Location: /admin/index.php');
    exit;

} catch (Exception $e) {
    error_log('Dev login error: ' . $e->getMessage());
    header('Location: /admin/login.php?error=' . urlencode($e->getMessage()));
    exit;
}
