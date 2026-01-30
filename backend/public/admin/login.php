<?php

declare(strict_types=1);

/**
 * Admin Login Page
 * 
 * Public login page with Google OAuth.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;


// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isLoggedIn = false;
$userEmail = null;
$authUrl = null;
$errorMessage = null;
$isDevelopment = false;
$devUsers = [];

try {
    $config = Config::load(__DIR__ . '/../../secrets.yml');
    $db = Database::getInstance($config);

    // Check for existing valid session
    $session = getAuthenticatedUser($db);
    if ($session !== null) {
        // Already logged in, redirect to landing page
        header('Location: /');
        exit;
    }

    // Check if we're in development mode
    $isDevelopment = ($config['app']['environment'] ?? 'production') === 'development';
    
    if ($isDevelopment) {
        // Get dev users for quick login
        $devUsers = $config['development']['dev_users'] ?? [];
    }

    // Build Google OAuth URL
    $googleOAuth = $config['google_oauth'] ?? null;
    if ($googleOAuth === null && !$isDevelopment) {
        throw new Exception("Google OAuth not configured");
    }

    if ($googleOAuth !== null) {
        $authUrl = buildGoogleAuthUrl($googleOAuth);
    }

    // Check for error message
    if (isset($_GET['error'])) {
        $errorMessage = htmlspecialchars($_GET['error']);
    }

} catch (Exception $e) {
    $errorMessage = htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail - Admin Login</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-admin-login">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo"><i class="fa-solid fa-link"></i></div>
            <h1>Trail</h1>
            <p class="subtitle">Link Journal Admin</p>

            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <?= $errorMessage ?>
                </div>
            <?php endif; ?>

            <?php if ($authUrl): ?>
                <a href="<?= htmlspecialchars($authUrl) ?>" class="google-button">
                    <svg class="google-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Sign in with Google
                </a>
            <?php elseif (!$isDevelopment): ?>
                <div class="error-message">
                    Google OAuth is not configured. Please check your configuration.
                </div>
            <?php endif; ?>

            <?php if ($isDevelopment && !empty($devUsers)): ?>
                <div class="dev-section">
                    <div class="dev-badge">🔧 Development Mode</div>
                    <div class="dev-users">
                        <?php foreach ($devUsers as $devUser): ?>
                            <a href="/admin/dev-login.php?email=<?= urlencode($devUser['email']) ?>" class="dev-user-button">
                                <div class="dev-user-info">
                                    <span class="dev-user-name"><?= htmlspecialchars($devUser['name']) ?></span>
                                    <span class="dev-user-email"><?= htmlspecialchars($devUser['email']) ?></span>
                                </div>
                                <?php if ($devUser['is_admin']): ?>
                                    <span class="dev-user-badge">Admin</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Trail - Link Journal Service</p>
            <p><a href="/">← Back to Home</a></p>
        </div>
    </div>
</body>
</html>
