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
$config = Config::load(__DIR__ . '/../config.yml');
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
        'description' => 'User-specific RSS feed',
        'auth' => false,
        'curl' => "curl {$baseUrl}/api/rss/123"
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
        }
        .header {
            background: #1e293b;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; color: #fff; }
        .header .subtitle { font-size: 1.125rem; opacity: 0.9; color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 3rem;
        }
        .quick-link {
            background: #1e293b;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #e2e8f0;
            transition: all 0.3s;
            border: 1px solid #334155;
        }
        .quick-link:hover {
            background: #334155;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .quick-link .icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .quick-link .label { font-weight: 600; }
        .badge {
            display: inline-block;
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        .section { margin-bottom: 3rem; }
        .section h2 {
            font-size: 1.875rem;
            margin-bottom: 1.5rem;
            color: #f1f5f9;
            border-bottom: 2px solid #334155;
            padding-bottom: 0.5rem;
        }
        .endpoint-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .endpoint-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .endpoint-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .method {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.875rem;
            font-family: "Courier New", monospace;
        }
        .method.get { background: #10b981; color: #fff; }
        .method.post { background: #3b82f6; color: #fff; }
        .method.delete { background: #ef4444; color: #fff; }
        .path {
            font-family: "Courier New", monospace;
            font-size: 1.125rem;
            color: #60a5fa;
            flex: 1;
        }
        .auth-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .description {
            color: #cbd5e1;
            margin-bottom: 1rem;
            font-size: 0.9375rem;
        }
        .curl-container {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 1rem;
            position: relative;
        }
        .curl-header {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .copy-btn {
            background: #334155;
            color: #e2e8f0;
            border: none;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }
        .copy-btn:hover {
            background: #475569;
        }
        .curl-code {
            font-family: "Courier New", monospace;
            font-size: 0.875rem;
            color: #7dd3fc;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.8;
        }
        .footer {
            margin-top: 4rem;
            padding: 2rem;
            text-align: center;
            border-top: 1px solid #334155;
            color: #94a3b8;
            font-size: 0.875rem;
        }
        @media (max-width: 768px) {
            .header h1 { font-size: 2rem; }
            .container { padding: 1rem; }
            .endpoint-header { flex-direction: column; align-items: flex-start; }
            .path { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔗 Trail API</h1>
        <p class="subtitle">Multi-user link journaling service<?php if ($isDev): ?> <span class="badge">Dev Mode</span><?php endif; ?></p>
    </div>
    
    <div class="container">
        <div class="quick-links">
            <a href="/" class="quick-link">
                <div class="icon">🏠</div>
                <div class="label">Home</div>
            </a>
            <a href="/api/rss" class="quick-link">
                <div class="icon">📡</div>
                <div class="label">RSS Feed</div>
            </a>
            <a href="/api/health" class="quick-link">
                <div class="icon">❤️</div>
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
                            <span class="auth-badge">🔒 AUTH REQUIRED</span>
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
