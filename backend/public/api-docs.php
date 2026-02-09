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
        'description' => 'Create a new entry (authenticated) - Max 280 characters. Supports custom dates, inline media upload, and initial claps.',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Check this out! https://example.com 🎉\"}' \\\n     {$baseUrl}/api/entries"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries',
        'description' => 'Create entry with custom date (Twitter format) - Import entries with original timestamps',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Imported tweet\",\"created_at\":\"Fri Nov 28 10:54:34 +0000 2025\"}' \\\n     {$baseUrl}/api/entries"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries',
        'description' => 'Create entry with inline media upload - Upload images as base64 in request body',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Photo post\",\"media\":[{\"data\":\"BASE64_IMAGE_DATA\",\"filename\":\"photo.jpg\",\"image_type\":\"post\"}],\"raw_upload\":false}' \\\n     {$baseUrl}/api/entries"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries',
        'description' => 'Create entry with initial claps - Set engagement metrics when importing content',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Popular post\",\"initial_claps\":25}' \\\n     {$baseUrl}/api/entries"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries',
        'description' => 'Create entry with all advanced features - Custom date, media, and claps combined',
        'auth' => true,
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_JWT_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Imported tweet with photo\",\"created_at\":\"Mon Jan 15 14:30:00 -0800 2024\",\"media\":[{\"data\":\"BASE64_IMAGE_DATA\",\"filename\":\"photo.jpg\",\"image_type\":\"post\"}],\"raw_upload\":true,\"initial_claps\":42}' \\\n     {$baseUrl}/api/entries"
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
        
        <div class="section">
            <h2>Advanced Entry Creation</h2>
            <p>The <code>POST /api/entries</code> endpoint supports advanced features for importing content from other platforms (e.g., Twitter/X):</p>
            
            <div class="feature-card">
                <h3><i class="fa-solid fa-clock"></i> Custom Creation Dates</h3>
                <p>Import entries with their original timestamps using Twitter date format:</p>
                <div class="code-block">
                    <code>"created_at": "Fri Nov 28 10:54:34 +0000 2025"</code>
                </div>
                <p class="note">Format: <code>Day Mon DD HH:MM:SS ±ZZZZ YYYY</code> (e.g., timezone +0000 for UTC, -0800 for PST)</p>
            </div>
            
            <div class="feature-card">
                <h3><i class="fa-solid fa-image"></i> Inline Media Upload</h3>
                <p>Upload images directly in the request (base64 encoded):</p>
                <div class="code-block">
                    <code>"media": [{<br>
                    &nbsp;&nbsp;"data": "base64_encoded_image_data...",<br>
                    &nbsp;&nbsp;"filename": "photo.jpg",<br>
                    &nbsp;&nbsp;"mime_type": "image/jpeg",<br>
                    &nbsp;&nbsp;"image_type": "post"<br>
                    }]</code>
                </div>
                <p class="note">Supports JPEG, PNG, GIF, WebP, SVG, AVIF. Max 20MB per image.</p>
            </div>
            
            <div class="feature-card">
                <h3><i class="fa-solid fa-bolt"></i> Raw Upload Mode</h3>
                <p>Skip image processing for faster imports:</p>
                <div class="code-block">
                    <code>"raw_upload": true</code>
                </div>
                <p class="note">When enabled, images are saved without resizing or WebP conversion. Use for trusted sources only.</p>
            </div>
            
            <div class="feature-card">
                <h3><i class="fa-solid fa-hands-clapping"></i> Initial Claps</h3>
                <p>Set engagement metrics when importing:</p>
                <div class="code-block">
                    <code>"initial_claps": 25</code>
                </div>
                <p class="note">Valid range: 1-50. Claps are attributed to the authenticated user (author).</p>
            </div>
            
            <div class="feature-card">
                <h3><i class="fa-solid fa-circle-info"></i> Response Format</h3>
                <p>Successful entry creation returns:</p>
                <div class="code-block">
                    <code>{<br>
                    &nbsp;&nbsp;"id": 123,<br>
                    &nbsp;&nbsp;"created_at": "2025-11-28 10:54:34",<br>
                    &nbsp;&nbsp;"images": [{<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"id": 456,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"url": "/uploads/images/1/1_1738246496_abc.webp",<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"width": 1200,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"height": 800,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;"file_size": 45678<br>
                    &nbsp;&nbsp;}],<br>
                    &nbsp;&nbsp;"clap_count": 25,<br>
                    &nbsp;&nbsp;"user_clap_count": 25<br>
                    }</code>
                </div>
            </div>
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
