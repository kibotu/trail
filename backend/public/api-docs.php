<?php

declare(strict_types=1);

/**
 * API Documentation Page
 * 
 * Displays all available API endpoints with curl examples
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Trail\Config\Config;

// Load configuration (uses secrets.yml)
$config = Config::load(__DIR__ . '/../secrets.yml');
$isDev = ($config['app']['environment'] ?? 'production') === 'development';

// Get base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;

// Define all API endpoints
$endpoints = [
    [
        'method' => 'GET',
        'path' => '/api',
        'description' => 'API documentation (this page)',
        'auth' => false,
        'curl' => "curl {$baseUrl}/api"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/entries',
        'description' => 'List all entries (admin only)',
        'auth' => true,
        'curl' => "curl -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     {$baseUrl}/api/admin/entries"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/admin/entries/{id}',
        'description' => 'Delete an entry (admin only)',
        'auth' => true,
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     {$baseUrl}/api/admin/entries/123"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/users',
        'description' => 'List all users (admin only)',
        'auth' => true,
        'curl' => "curl -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     {$baseUrl}/api/admin/users"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/admin/users/{id}',
        'description' => 'Delete a user (admin only)',
        'auth' => true,
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     {$baseUrl}/api/admin/users/123"
    ],
    [
        'method' => 'POST',
        'path' => '/api/admin/entries/{id}',
        'description' => 'Update an entry (admin only)',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Updated text with https://example.com\"}' \\\n     {$baseUrl}/api/admin/entries/123"
    ],
    [
        'method' => 'POST',
        'path' => '/api/auth/google',
        'description' => 'Authenticate with Google OAuth token',
        'auth' => false,
        'curl' => "curl -X POST \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"id_token\":\"GOOGLE_ID_TOKEN\"}' \\\n     {$baseUrl}/api/auth/google"
    ],
    [
        'method' => 'GET',
        'path' => '/api/entries',
        'description' => 'List user entries (authenticated) - Supports cursor-based pagination with ?limit=20&before=TIMESTAMP',
        'auth' => true,
        'curl' => "curl -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     {$baseUrl}/api/entries?limit=20\n\n# Next page:\ncurl -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     {$baseUrl}/api/entries?limit=20&before=2024-01-01%2012:00:00"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries',
        'description' => 'Create a new entry (authenticated) - Max 280 characters',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Check this out! https://example.com 🎉\"}' \\\n     {$baseUrl}/api/entries"
    ],
    [
        'method' => 'GET',
        'path' => '/api/health',
        'description' => 'Health check endpoint',
        'auth' => false,
        'curl' => "curl {$baseUrl}/api/health"
    ],
    [
        'method' => 'GET',
        'path' => '/api/rss',
        'description' => 'Global RSS feed of all public entries',
        'auth' => false,
        'curl' => "curl {$baseUrl}/api/rss"
    ],
    [
        'method' => 'GET',
        'path' => '/api/rss/{user_id}',
        'description' => 'User-specific RSS feed by user ID',
        'auth' => false,
        'curl' => "curl {$baseUrl}/api/rss/123"
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{nickname}/rss',
        'description' => 'User-specific RSS feed by nickname',
        'auth' => false,
        'curl' => "curl {$baseUrl}/api/users/@alice/rss"
    ]
];

// Sort endpoints by path
usort($endpoints, function($a, $b) {
    return strcmp($a['path'], $b['path']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail API Documentation</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-api-docs">
    <div class="header">
        <h1><i class="fa-solid fa-link"></i> Trail API</h1>
        <p class="subtitle">Multi-user link journaling service<?php if ($isDev): ?> <span class="badge">Dev Mode</span><?php endif; ?></p>
    </div>
    
    <div class="container">
        <div class="quick-links">
            <a href="/" class="quick-link">
                <div class="icon"><i class="fa-solid fa-house"></i></div>
                <div class="label">Home</div>
            </a>
            <a href="/api/rss" class="quick-link">
                <div class="icon"><i class="fa-solid fa-rss"></i></div>
                <div class="label">RSS Feed</div>
            </a>
            <a href="/api/health" class="quick-link">
                <div class="icon"><i class="fa-solid fa-heart"></i></div>
                <div class="label">Health Check</div>
            </a>
        </div>
        
        <div class="section">
            <h2>API Endpoints</h2>
            
            <?php foreach ($endpoints as $endpoint): ?>
                <?php $methodClass = strtolower($endpoint['method']); ?>
                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <span class="method <?= $methodClass ?>"><?= $endpoint['method'] ?></span>
                        <span class="path"><?= htmlspecialchars($endpoint['path']) ?></span>
                        <?php if ($endpoint['auth']): ?>
                            <span class="auth-badge"><i class="fa-solid fa-lock"></i> AUTH REQUIRED</span>
                        <?php endif; ?>
                    </div>
                    <div class="description"><?= htmlspecialchars($endpoint['description']) ?></div>
                    <div class="curl-container">
                        <div class="curl-header">
                            <button class="copy-btn" onclick="copyToClipboard(this)">Copy</button>
                        </div>
                        <div class="curl-code"><?= htmlspecialchars($endpoint['curl']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer">
            <p><strong>Trail API</strong> - Link Journal Service</p>
            <p>PHP <?= PHP_VERSION ?> • Slim Framework • MariaDB</p>
        </div>
    </div>
    
    <script>
        function copyToClipboard(button) {
            const codeElement = button.closest('.curl-container').querySelector('.curl-code');
            const text = codeElement.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.style.background = '#10b981';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
    </script>
</body>
</html>
