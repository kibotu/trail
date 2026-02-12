<?php

declare(strict_types=1);

/**
 * API Documentation Page
 * 
 * Comprehensive API documentation for technical leadership and developers
 * Restructured to prioritize critical information and integration paths
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

// Get rate limits from config
$rateLimitPerMinute = $config['security']['rate_limit']['requests_per_minute'] ?? 180;
$rateLimitPerHour = $config['security']['rate_limit']['requests_per_hour'] ?? 3000;
$maxTextLength = $config['app']['max_text_length'] ?? 140;
$maxImagesPerEntry = $config['app']['max_images_per_entry'] ?? 3;

// Define all API endpoints with enhanced metadata
$endpoints = [
    // PUBLIC ENDPOINTS (No Auth Required)
    [
        'method' => 'GET',
        'path' => '/api/health',
        'description' => 'Health check endpoint - Returns API status',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/health"
    ],
    [
        'method' => 'GET',
        'path' => '/api/config',
        'description' => 'Get public configuration values (e.g., max_text_length, max_images_per_entry)',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/config"
    ],
    [
        'method' => 'GET',
        'path' => '/api/rss',
        'description' => 'Global RSS feed of all public entries',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/rss"
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{nickname}/rss',
        'description' => 'User-specific RSS feed by nickname',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/users/alice/rss"
    ],
    
    // CORE USER ENDPOINTS (Auth Required)
    [
        'method' => 'GET',
        'path' => '/api/profile',
        'description' => 'Get own profile - Includes stats (entry_count, link_count, comment_count, last_entry_at, previous_login_at)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/profile"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/profile',
        'description' => 'Update own profile',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"nickname\":\"alice\",\"bio\":\"Hello world\"}' \\\n     {$baseUrl}/api/profile"
    ],
    [
        'method' => 'GET',
        'path' => '/api/token',
        'description' => 'Get your API token - View your persistent API token for programmatic access',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -b cookies.txt {$baseUrl}/api/token",
        'internal' => true
    ],
    [
        'method' => 'POST',
        'path' => '/api/token/regenerate',
        'description' => 'Regenerate API token - Creates a new token and invalidates the old one',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -b cookies.txt \\\n     {$baseUrl}/api/token/regenerate",
        'internal' => true
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{nickname}',
        'description' => 'Get public profile by nickname - Includes stats (entry_count, link_count, comment_count, last_entry_at, previous_login_at)',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/users/alice"
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{nickname}/entries',
        'description' => 'List entries by user nickname - Supports cursor-based pagination',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/users/alice/entries?limit=20"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries',
        'description' => "Create a new entry - Max {$maxTextLength} characters, max {$maxImagesPerEntry} images",
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Check this out! https://example.com 🎉\"}' \\\n     {$baseUrl}/api/entries"
    ],
    [
        'method' => 'GET',
        'path' => '/api/entries',
        'description' => 'List all entries with optional search - Supports cursor-based pagination and full-text search',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/entries?limit=20\n\n# With search:\ncurl {$baseUrl}/api/entries?q=example&limit=20"
    ],
    [
        'method' => 'GET',
        'path' => '/api/entries/{id}',
        'description' => 'Get a single entry by hash ID',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/entries/abc123"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/entries/{id}',
        'description' => 'Update own entry',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Updated text\"}' \\\n     {$baseUrl}/api/entries/123"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/entries/{id}',
        'description' => 'Delete own entry',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'core',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/entries/123"
    ],
    
    // ENGAGEMENT ENDPOINTS (Auth Required)
    [
        'method' => 'POST',
        'path' => '/api/entries/{id}/claps',
        'description' => 'Add clap to entry (1-50 claps)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -b cookies.txt \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"count\":5}' \\\n     {$baseUrl}/api/entries/123/claps"
    ],
    [
        'method' => 'GET',
        'path' => '/api/entries/{id}/claps',
        'description' => 'Get clap count for entry',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/entries/123/claps"
    ],
    
    // TAG ENDPOINTS
    [
        'method' => 'GET',
        'path' => '/api/tags',
        'description' => 'List all tags with entry counts - Optional ?search= query parameter for autocomplete',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/tags?search=python"
    ],
    [
        'method' => 'GET',
        'path' => '/api/tags/{slug}/entries',
        'description' => 'List entries with a specific tag - Supports cursor pagination (?limit=20&before=cursor)',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/tags/python/entries?limit=20"
    ],
    [
        'method' => 'GET',
        'path' => '/api/entries/{id}/tags',
        'description' => 'Get tags for a specific entry',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/entries/123/tags"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/entries/{id}/tags',
        'description' => 'Replace all tags for an entry (owner or admin only) - Idempotent operation',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"tags\":[\"python\",\"tutorial\",\"machine-learning\"]}' \\\n     {$baseUrl}/api/entries/123/tags"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries/{id}/tags',
        'description' => 'Add a single tag to an entry (owner or admin only)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"tag\":\"python\"}' \\\n     {$baseUrl}/api/entries/123/tags"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/entries/{id}/tags/{slug}',
        'description' => 'Remove a tag from an entry (owner or admin only)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/entries/123/tags/python"
    ],
    [
        'method' => 'POST',
        'path' => '/api/entries/{id}/comments',
        'description' => 'Create comment on entry',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -b cookies.txt \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Great post!\"}' \\\n     {$baseUrl}/api/entries/123/comments"
    ],
    [
        'method' => 'GET',
        'path' => '/api/entries/{id}/comments',
        'description' => 'List comments for entry',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/entries/123/comments"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/comments/{id}',
        'description' => 'Update own comment',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -b cookies.txt \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Updated comment\"}' \\\n     {$baseUrl}/api/comments/456"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/comments/{id}',
        'description' => 'Delete own comment',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -b cookies.txt \\\n     {$baseUrl}/api/comments/456"
    ],
    [
        'method' => 'POST',
        'path' => '/api/comments/{id}/claps',
        'description' => 'Add clap to comment',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -b cookies.txt \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"count\":3}' \\\n     {$baseUrl}/api/comments/456/claps"
    ],
    [
        'method' => 'GET',
        'path' => '/api/comments/{id}/claps',
        'description' => 'Get clap count for comment',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'public',
        'rate_limit' => 'None',
        'curl' => "curl {$baseUrl}/api/comments/456/claps"
    ],
    
    // VIEW TRACKING ENDPOINTS
    [
        'method' => 'POST',
        'path' => '/api/entries/{id}/views',
        'description' => 'Record entry view - Tracks unique views with 24h deduplication',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"fingerprint\":\"optional-client-fingerprint\"}' \\\n     {$baseUrl}/api/entries/123/views"
    ],
    [
        'method' => 'POST',
        'path' => '/api/comments/{id}/views',
        'description' => 'Record comment view - Tracks unique views with 24h deduplication',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"fingerprint\":\"optional-client-fingerprint\"}' \\\n     {$baseUrl}/api/comments/456/views"
    ],
    [
        'method' => 'POST',
        'path' => '/api/users/{nickname}/views',
        'description' => 'Record profile view - Tracks unique profile views with 24h deduplication',
        'auth' => false,
        'auth_level' => 'public',
        'group' => 'engagement',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"fingerprint\":\"optional-client-fingerprint\"}' \\\n     {$baseUrl}/api/users/alice/views"
    ],
    
    // MEDIA ENDPOINTS (Auth Required)
    [
        'method' => 'POST',
        'path' => '/api/images/upload/init',
        'description' => 'Initialize chunked media upload - Images or videos (20MB max)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'media',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"filename\":\"video.mp4\",\"total_size\":10240000}' \\\n     {$baseUrl}/api/images/upload/init"
    ],
    [
        'method' => 'POST',
        'path' => '/api/images/upload/chunk',
        'description' => 'Upload media chunk (512KB chunks)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'media',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -F \"upload_id=abc123\" \\\n     -F \"chunk_index=0\" \\\n     -F \"chunk=@chunk0.bin\" \\\n     {$baseUrl}/api/images/upload/chunk"
    ],
    [
        'method' => 'POST',
        'path' => '/api/images/upload/complete',
        'description' => 'Complete chunked upload - Validates and processes media',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'media',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"upload_id\":\"abc123\"}' \\\n     {$baseUrl}/api/images/upload/complete"
    ],
    [
        'method' => 'GET',
        'path' => '/api/images/{id}',
        'description' => 'Serve media by ID (images and videos)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'media',
        'rate_limit' => 'None',
        'curl' => "curl -b cookies.txt {$baseUrl}/api/images/789"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/images/{id}',
        'description' => 'Delete own media (image or video)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'media',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/images/789"
    ],
    
    // MODERATION ENDPOINTS (Auth Required)
    [
        'method' => 'POST',
        'path' => '/api/entries/{id}/report',
        'description' => 'Report entry for moderation',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'moderation',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"reason\":\"spam\"}' \\\n     {$baseUrl}/api/entries/123/report"
    ],
    [
        'method' => 'POST',
        'path' => '/api/comments/{id}/report',
        'description' => 'Report comment for moderation',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'moderation',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"reason\":\"harassment\"}' \\\n     {$baseUrl}/api/comments/456/report"
    ],
    [
        'method' => 'POST',
        'path' => '/api/users/{id}/mute',
        'description' => 'Mute user (hide their content)',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'moderation',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/users/789/mute"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/users/{id}/mute',
        'description' => 'Unmute user',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'moderation',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/users/789/mute"
    ],
    [
        'method' => 'GET',
        'path' => '/api/users/{id}/mute-status',
        'description' => 'Check if user is muted',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'moderation',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/users/789/mute-status"
    ],
    [
        'method' => 'GET',
        'path' => '/api/filters',
        'description' => 'Get content filters',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'moderation',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/filters"
    ],
    
    // NOTIFICATION ENDPOINTS (Auth Required)
    [
        'method' => 'GET',
        'path' => '/api/notifications',
        'description' => 'List notifications',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'notifications',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/notifications"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/notifications/{id}/read',
        'description' => 'Mark notification as read',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'notifications',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/notifications/123/read"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/notifications/read-all',
        'description' => 'Mark all notifications as read',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'notifications',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/notifications/read-all"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/notifications/{id}',
        'description' => 'Delete notification',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'notifications',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/notifications/123"
    ],
    [
        'method' => 'GET',
        'path' => '/api/notifications/preferences',
        'description' => 'Get notification preferences',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'notifications',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/notifications/preferences"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/notifications/preferences',
        'description' => 'Update notification preferences',
        'auth' => true,
        'auth_level' => 'user',
        'group' => 'notifications',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"email_mentions\":true}' \\\n     {$baseUrl}/api/notifications/preferences"
    ],
    
    // ADMIN ENDPOINTS (Admin Auth Required)
    [
        'method' => 'GET',
        'path' => '/api/admin/entries',
        'description' => 'List all entries (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/entries"
    ],
    [
        'method' => 'POST',
        'path' => '/api/admin/entries/{id}',
        'description' => 'Update any entry (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"text\":\"Updated text\"}' \\\n     {$baseUrl}/api/admin/entries/123"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/admin/entries/{id}',
        'description' => 'Delete any entry (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/entries/123"
    ],
    [
        'method' => 'PUT',
        'path' => '/api/admin/entries/tags',
        'description' => 'Batch set tags for multiple entries (admin only) - Max 100 entries per request',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X PUT \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     -H \"Content-Type: application/json\" \\\n     -d '{\"entries\":[{\"id\":\"abc123\",\"tags\":[\"python\",\"ai\"]},{\"id\":\"def456\",\"tags\":[\"rust\"]}]}' \\\n     {$baseUrl}/api/admin/entries/tags"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/users',
        'description' => 'List all users (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/users"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/admin/users/{id}',
        'description' => 'Delete user (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/users/123"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/admin/users/{id}/entries',
        'description' => 'Delete all user entries (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/users/123/entries"
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/admin/users/{id}/comments',
        'description' => 'Delete all user comments (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X DELETE \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/users/123/comments"
    ],
    [
        'method' => 'POST',
        'path' => '/api/admin/cache/clear',
        'description' => 'Clear application cache (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/cache/clear"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/error-logs',
        'description' => 'View error logs (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/error-logs"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/error-stats',
        'description' => 'Get error statistics (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/error-stats"
    ],
    [
        'method' => 'POST',
        'path' => '/api/admin/error-logs/cleanup',
        'description' => 'Cleanup old error logs (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/error-logs/cleanup"
    ],
    [
        'method' => 'POST',
        'path' => '/api/admin/images/prune',
        'description' => 'Prune orphaned images (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/images/prune"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/short-links',
        'description' => 'List short URLs (t.co, bit.ly, etc.) pending resolution (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/short-links"
    ],
    [
        'method' => 'GET',
        'path' => '/api/admin/short-links/stats',
        'description' => 'Get short link statistics - total, pending, failed counts (admin only)',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/short-links/stats"
    ],
    [
        'method' => 'POST',
        'path' => '/api/admin/short-links/resolve',
        'description' => 'Resolve short URLs to their final destinations (admin only) - Processes untried URLs first, then retries oldest failures',
        'auth' => true,
        'auth_level' => 'admin',
        'group' => 'admin',
        'rate_limit' => "{$rateLimitPerMinute}/min",
        'curl' => "curl -X POST \\\n     -H \"Authorization: Bearer YOUR_API_TOKEN\" \\\n     {$baseUrl}/api/admin/short-links/resolve"
    ]
];

// Group endpoints by category (excluding internal endpoints)
$groupedEndpoints = [];
foreach ($endpoints as $endpoint) {
    // Skip internal endpoints from public documentation
    if (!empty($endpoint['internal'])) {
        continue;
    }
    $group = $endpoint['group'] ?? 'other';
    if (!isset($groupedEndpoints[$group])) {
        $groupedEndpoints[$group] = [];
    }
    $groupedEndpoints[$group][] = $endpoint;
}

// Define group metadata (order matters for display)
$groups = [
    'public' => ['title' => 'Public Endpoints', 'description' => 'No authentication required', 'icon' => 'fa-globe'],
    'core' => ['title' => 'Core User Endpoints', 'description' => 'Profile and entry management (requires auth)', 'icon' => 'fa-user'],
    'engagement' => ['title' => 'Engagement', 'description' => 'Claps, comments, and view tracking', 'icon' => 'fa-heart'],
    'media' => ['title' => 'Media Upload', 'description' => 'Image and video upload (requires auth)', 'icon' => 'fa-photo-film'],
    'moderation' => ['title' => 'Moderation', 'description' => 'Content reporting and user muting (requires auth)', 'icon' => 'fa-shield-halved'],
    'notifications' => ['title' => 'Notifications', 'description' => 'Real-time updates (requires auth)', 'icon' => 'fa-bell'],
    'admin' => ['title' => 'Admin Endpoints', 'description' => 'Administrative functions (requires admin)', 'icon' => 'fa-crown']
];

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
        <div class="main-grid">
            <!-- Mobile TOC Toggle -->
            <button class="toc-toggle" id="toc-toggle" aria-label="Open navigation">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Mobile TOC Overlay -->
            <div class="toc-overlay" id="toc-overlay"></div>

            <!-- Table of Contents -->
            <aside class="toc" id="toc-sidebar">
                <div class="toc-header">
                    <h3 style="margin-top: 0;">Contents</h3>
                    <button class="toc-close" id="toc-close" aria-label="Close navigation">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <a href="#overview" class="toc-link">Overview</a>
                <a href="#quick-start" class="toc-link">Quick Start</a>
                <a href="#authentication" class="toc-link">Authentication</a>
                <a href="#core-concepts" class="toc-link">Core Concepts</a>
                <a href="#endpoints" class="toc-link">API Endpoints</a>
                <?php foreach ($groups as $groupKey => $groupMeta): ?>
                    <a href="#group-<?= $groupKey ?>" class="toc-link" style="padding-left: 1rem;">→ <?= $groupMeta['title'] ?></a>
                <?php endforeach; ?>
                <a href="#advanced-features" class="toc-link">Advanced Features</a>
                <a href="#performance" class="toc-link">Performance & Limits</a>
                <a href="#technical-reference" class="toc-link">Technical Reference</a>
            </aside>
            
            <!-- Main Content -->
            <main>
                <!-- Executive Overview Section -->
                <section id="overview" class="section">
                    <h2>Executive Overview</h2>
                    <p><strong>Trail</strong> is a production-ready, multi-user link journaling API service that enables users to create, share, and engage with short-form content entries (140 characters) with optional media attachments and automatic URL preview enrichment.</p>
                    
                    <h3>Key Capabilities</h3>
                    <div class="capability-list">
                        <div class="capability-item">
                            <i class="fa-solid fa-key capability-icon"></i>
                            <div>
                                <strong>Google OAuth 2.0</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Secure authentication with JWT tokens</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-link capability-icon"></i>
                            <div>
                                <strong>Link Journaling</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">140-char posts with URL previews, auto short URL resolution</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-photo-film capability-icon"></i>
                            <div>
                                <strong>Media Upload</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Images & videos (20MB max each), up to <?= $maxImagesPerEntry ?> per entry</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-magnifying-glass capability-icon"></i>
                            <div>
                                <strong>Full-Text Search</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Fast search with relevance ranking</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-heart capability-icon"></i>
                            <div>
                                <strong>Engagement</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Claps and threaded comments</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-rss capability-icon"></i>
                            <div>
                                <strong>RSS Feeds</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Global and per-user feeds</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-bell capability-icon"></i>
                            <div>
                                <strong>Notifications</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Real-time updates for mentions & claps</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-shield-halved capability-icon"></i>
                            <div>
                                <strong>Moderation</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">User muting and content reporting</div>
                            </div>
                        </div>
                        <div class="capability-item">
                            <i class="fa-solid fa-eye capability-icon"></i>
                            <div>
                                <strong>View Tracking</strong>
                                <div style="font-size: 0.875rem; color: #6b7280;">Unique view counts for entries, comments & profiles</div>
                            </div>
                        </div>
                    </div>
                    
                    <h3>System Architecture</h3>
                    <div class="mermaid">
graph LR
    Client[Client Application]
    API[Trail API]
    Auth[Auth Middleware]
    RateLimit[Rate Limiter]
    DB[(MariaDB)]
    Iframely[Iframely API]
    
    Client -->|HTTPS/JSON| API
    API --> Auth
    API --> RateLimit
    Auth -->|JWT Verify| API
    RateLimit -->|Throttle| API
    API -->|SQL| DB
    API -->|URL Preview| Iframely
                    </div>
                    
                    <h3>Quick Stats</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">30+</div>
                            <div class="stat-label">API Endpoints</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">RESTful</div>
                            <div class="stat-label">API Design</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">JSON</div>
                            <div class="stat-label">Data Format</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">HTTPS</div>
                            <div class="stat-label">Secure Transport</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">OAuth 2.0</div>
                            <div class="stat-label">Authentication</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">UTF-8</div>
                            <div class="stat-label">Character Encoding</div>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <strong>Tech Stack:</strong> PHP <?= PHP_VERSION ?> • Slim Framework • MariaDB • JWT Authentication • Iframely URL Enrichment
                    </div>
                </section>
                
                <!-- Quick Start Section -->
                <section id="quick-start" class="section">
                    <h2>Quick Start Guide</h2>
                    
                    <div class="info-box">
                        <strong>📖 Public Access:</strong> You can view entries, comments, and user profiles without authentication. Authentication is only required to create, edit, or delete content.
                    </div>
                    
                    <h3>Public API Usage (No Authentication)</h3>
                    <div class="code-example">
                        <code># List all public entries
curl <?= $baseUrl ?>/api/entries?limit=20

# Search entries
curl <?= $baseUrl ?>/api/entries?q=example&limit=20

# Get a specific entry
curl <?= $baseUrl ?>/api/entries/abc123

# View user's entries
curl <?= $baseUrl ?>/api/users/alice/entries

# Search user's entries
curl <?= $baseUrl ?>/api/users/alice/entries?q=example</code>
                    </div>
                    
                    <h3>Authenticated API Usage</h3>
                    <p>To create or modify content, follow these steps:</p>
                    
                    <h4>Step 1: Get Your API Token</h4>
                    <div class="info-box">
                        <ol style="margin: 0.5rem 0;">
                            <li>Sign in to Trail at <a href="<?= $baseUrl ?>" target="_blank"><?= $baseUrl ?></a> using Google OAuth</li>
                            <li>Navigate to your <a href="<?= $baseUrl ?>/profile" target="_blank">Profile page</a></li>
                            <li>Find the <strong>API Token</strong> section (above Muted Users)</li>
                            <li>Click the eye icon to reveal your token</li>
                            <li>Click the copy icon to copy it to your clipboard</li>
                        </ol>
                    </div>
                    
                    <h4>Step 2: Create Your First Entry</h4>
                    <div class="code-example">
                        <code># Replace YOUR_API_TOKEN with your actual token
curl -X POST <?= $baseUrl ?>/api/entries \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text":"Hello from Trail API! 🚀"}'</code>
                    </div>
                    
                    <h4>Step 3: List Your Entries</h4>
                    <div class="code-example">
                        <code># List entries you created
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     <?= $baseUrl ?>/api/profile</code>
                    </div>
                    
                    <div class="info-box success">
                        <strong>Success!</strong> You're now using the Trail API. Your API token never expires and can be regenerated anytime from your profile page.
                    </div>
                    
                    <div class="info-box">
                        <strong>💡 Pro Tips:</strong>
                        <ul style="margin-top: 0.5rem;">
                            <li>Store your API token securely (e.g., environment variables)</li>
                            <li>Never commit tokens to version control</li>
                            <li>Regenerate your token if it's compromised</li>
                            <li>Use the same token across all your applications</li>
                        </ul>
                    </div>
                </section>
                
                <!-- Authentication & Security Section -->
                <section id="authentication" class="section">
                    <h2>Authentication & Security</h2>
                    
                    <h3>How to Authenticate</h3>
                    <div class="feature-card">
                        <h4><i class="fa-solid fa-key"></i> API Token Authentication</h4>
                        <p>All API requests require authentication using your personal API token. Each user has a unique, persistent token that never expires.</p>
                        
                        <h5>Getting Your Token:</h5>
                        <ol>
                            <li>Sign in at <a href="<?= $baseUrl ?>" target="_blank"><?= $baseUrl ?></a></li>
                            <li>Go to your <a href="<?= $baseUrl ?>/profile" target="_blank">Profile page</a></li>
                            <li>Find the <strong>API Token</strong> section</li>
                            <li>Click the eye icon to reveal your token</li>
                            <li>Copy the token to use in your requests</li>
                        </ol>
                        
                        <h5>Using Your Token:</h5>
                        <div class="code-block">
                            <code># Include your token in the Authorization header
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     <?= $baseUrl ?>/api/entries

# Example: Create a new entry
curl -X POST \
     -H "Authorization: Bearer YOUR_API_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"text":"Hello from API!"}' \
     <?= $baseUrl ?>/api/entries</code>
                        </div>
                        
                        <h5>Token Properties:</h5>
                        <ul>
                            <li><strong>Format:</strong> 64-character hexadecimal string</li>
                            <li><strong>Expiration:</strong> Never expires (until you regenerate it)</li>
                            <li><strong>Regeneration:</strong> Available anytime from your profile page</li>
                            <li><strong>Security:</strong> Treat like a password - never share or commit to version control</li>
                            <li><strong>Scope:</strong> Full access to all API endpoints (same permissions as your account)</li>
                        </ul>
                    </div>
                    
                    <div class="info-box">
                        <strong>🔒 Security Best Practices:</strong>
                        <ul style="margin-top: 0.5rem;">
                            <li>Store tokens in environment variables, not in code</li>
                            <li>Never commit tokens to version control (add to .gitignore)</li>
                            <li>Regenerate your token immediately if compromised</li>
                            <li>Use HTTPS for all API requests (enforced by server)</li>
                            <li>Keep your token private - it provides full account access</li>
                        </ul>
                    </div>
                    
                    <h3>Security Model</h3>
                    <div class="feature-card">
                        <h4><i class="fa-solid fa-shield-halved"></i> Rate Limiting</h4>
                        <ul>
                            <li><strong>Authentication:</strong> 5 attempts per 5 minutes</li>
                            <li><strong>General API:</strong> <?= $rateLimitPerMinute ?> requests per minute</li>
                            <li><strong>Hourly Limit:</strong> <?= $rateLimitPerHour ?> requests per hour</li>
                            <li><strong>Response:</strong> HTTP 429 when exceeded</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h4><i class="fa-solid fa-lock"></i> Security Features</h4>
                        <ul>
                            <li><strong>CORS:</strong> Configurable cross-origin resource sharing</li>
                            <li><strong>CSRF Protection:</strong> Token-based CSRF prevention</li>
                            <li><strong>Content Sanitization:</strong> XSS prevention on all user input</li>
                            <li><strong>Bot Protection:</strong> User-agent validation and pattern detection</li>
                            <li><strong>SQL Injection:</strong> Prepared statements for all queries</li>
                        </ul>
                    </div>
                    
                    <h3>Authorization Levels</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-globe"></i></div>
                            <div class="stat-label"><strong>Public</strong></div>
                            <div class="stat-description">No authentication required</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-user"></i></div>
                            <div class="stat-label"><strong>User</strong></div>
                            <div class="stat-description">Authenticated users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-crown"></i></div>
                            <div class="stat-label"><strong>Admin</strong></div>
                            <div class="stat-description">Administrative privileges</div>
                        </div>
                    </div>
                </section>
                
                <!-- Core Concepts Section -->
                <section id="core-concepts" class="section">
                    <h2>Core Concepts</h2>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-link"></i> Entries</h3>
                        <p>Short-form posts (max <?= $maxTextLength ?> characters) that can include:</p>
                        <ul>
                            <li><strong>Text:</strong> UTF-8 text with emoji support</li>
                            <li><strong>URLs:</strong> Automatic preview enrichment via Iframely</li>
                            <li><strong>Images:</strong> Up to <?= $maxImagesPerEntry ?> images (20MB each, JPEG/PNG/GIF/WebP/SVG/AVIF)</li>
                            <li><strong>Videos:</strong> MP4, WebM, MOV (20MB each) with custom player controls</li>
                            <li><strong>Timestamps:</strong> Custom creation dates for imports</li>
                            <li><strong>Hash IDs:</strong> Secure, obfuscated entry identifiers</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-user"></i> Users</h3>
                        <p>User profiles with Google OAuth authentication:</p>
                        <ul>
                            <li><strong>Nickname:</strong> Unique @username for public profiles</li>
                            <li><strong>Avatar:</strong> Google photo or Gravatar fallback</li>
                            <li><strong>Bio:</strong> Optional profile description</li>
                            <li><strong>Privacy:</strong> Public profile pages and RSS feeds</li>
                            <li><strong>Statistics:</strong> Entry, comment counts, last activity, and login history</li>
                        </ul>
                        <p style="margin-top: 0.75rem;"><strong>Profile stats object:</strong></p>
                        <div class="code-block">
                            <code>{
  "stats": {
    "entry_count": 42,
    "link_count": 18,
    "comment_count": 7,
    "last_entry_at": "2026-02-09 14:30:00",
    "previous_login_at": "2026-02-08 09:15:22"
  }
}</code>
                        </div>
                        <ul>
                            <li><strong>entry_count:</strong> Total entries by the user</li>
                            <li><strong>link_count:</strong> Entries containing a URL preview</li>
                            <li><strong>comment_count:</strong> Total comments by the user</li>
                            <li><strong>last_entry_at:</strong> Timestamp of the most recent entry (null if none)</li>
                            <li><strong>previous_login_at:</strong> Timestamp of the login before the current session (null if first login)</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-heart"></i> Engagement</h3>
                        <p>Social interaction features:</p>
                        <ul>
                            <li><strong>Claps:</strong> 1-50 claps per user per entry/comment</li>
                            <li><strong>Comments:</strong> Threaded discussions (max <?= $maxTextLength ?> chars)</li>
                            <li><strong>Mentions:</strong> @username mentions with notifications</li>
                            <li><strong>Views:</strong> Unique view tracking for entries, comments, and profiles</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-eye"></i> View Tracking</h3>
                        <p>Analytics and view counting for content:</p>
                        <ul>
                            <li><strong>Entry views:</strong> Track unique views per entry</li>
                            <li><strong>Comment views:</strong> Track unique views per comment</li>
                            <li><strong>Profile views:</strong> Track unique profile page views</li>
                            <li><strong>Deduplication:</strong> 24-hour window for unique view counting</li>
                            <li><strong>Anonymous support:</strong> Optional fingerprint for anonymous users</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-arrows-rotate"></i> Pagination</h3>
                        <p>Cursor-based pagination for infinite scroll:</p>
                        <ul>
                            <li><strong>Parameters:</strong> <code>?limit=20&before=TIMESTAMP</code></li>
                            <li><strong>Response:</strong> <code>has_more</code> and <code>next_cursor</code> fields</li>
                            <li><strong>Default:</strong> 20 items per page</li>
                            <li><strong>Maximum:</strong> 100 items per page (public), 50 (user-specific)</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-magnifying-glass"></i> Search</h3>
                        <p>Full-text search with relevance ranking:</p>
                        <ul>
                            <li><strong>Query:</strong> <code>?q=search+term</code> parameter</li>
                            <li><strong>Full-text:</strong> 4+ characters (relevance ranked)</li>
                            <li><strong>LIKE search:</strong> 1-3 characters (pattern matching)</li>
                            <li><strong>Max length:</strong> 200 characters</li>
                            <li><strong>Public access:</strong> No authentication required</li>
                            <li><strong>Filtering:</strong> Authenticated users get personalized results (muted users hidden)</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-shield-halved"></i> Moderation</h3>
                        <p>User-controlled content filtering:</p>
                        <ul>
                            <li><strong>Muting:</strong> Hide all content from specific users</li>
                            <li><strong>Reporting:</strong> Flag entries/comments for review</li>
                            <li><strong>Hiding:</strong> Personally hide individual entries</li>
                            <li><strong>Admin tools:</strong> Full moderation capabilities</li>
                        </ul>
                    </div>
                </section>
                
                <!-- API Endpoints Section -->
                <section id="endpoints" class="section">
                    <h2>API Endpoints</h2>
                    <p>All endpoints organized by user journey and functionality. Use the search box to quickly find specific endpoints.</p>
                    
                    <input type="text" id="endpoint-search" class="search-box" placeholder="Search endpoints..." onkeyup="filterEndpoints()">
                    
                    <?php foreach ($groups as $groupKey => $groupMeta): ?>
                        <?php if (isset($groupedEndpoints[$groupKey])): ?>
                            <div class="endpoint-group" id="group-<?= $groupKey ?>">
                                <div class="group-header">
                                    <i class="fa-solid <?= $groupMeta['icon'] ?> group-icon"></i>
                                    <div>
                                        <h3 style="margin: 0;"><?= $groupMeta['title'] ?></h3>
                                        <p style="margin: 0; color: #6b7280; font-size: 0.875rem;"><?= $groupMeta['description'] ?></p>
                                    </div>
                                </div>
                                
                                <?php foreach ($groupedEndpoints[$groupKey] as $endpoint): ?>
                                    <?php $methodClass = strtolower($endpoint['method']); ?>
                                    <div class="endpoint-card" data-path="<?= htmlspecialchars($endpoint['path']) ?>" data-method="<?= htmlspecialchars($endpoint['method']) ?>" data-description="<?= htmlspecialchars($endpoint['description']) ?>">
                                        <div class="endpoint-header">
                                            <span class="method <?= $methodClass ?>"><?= $endpoint['method'] ?></span>
                                            <span class="path"><?= htmlspecialchars($endpoint['path']) ?></span>
                                            <span class="endpoint-badges">
                                                <span class="auth-level-<?= $endpoint['auth_level'] ?>"><?= strtoupper($endpoint['auth_level']) ?></span>
                                                <?php if ($endpoint['rate_limit'] !== 'None'): ?>
                                                    <span class="rate-limit-badge"><?= htmlspecialchars($endpoint['rate_limit']) ?></span>
                                                <?php endif; ?>
                                            </span>
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
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
                
                <!-- Advanced Features Section -->
                <section id="advanced-features" class="section">
                    <h2>Advanced Features</h2>
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
                        <h3><i class="fa-solid fa-photo-film"></i> Inline Media Upload</h3>
                        <p>Upload images or videos directly in the request (base64 encoded):</p>
                        <div class="code-block">
                            <code>"media": [{
  "data": "base64_encoded_media_data...",
  "filename": "photo.jpg",
  "mime_type": "image/jpeg",
  "image_type": "post"
}]</code>
                        </div>
                        <p class="note"><strong>Images:</strong> JPEG, PNG, GIF (animated supported), WebP, SVG, AVIF. Max 20MB each.<br>
                        <strong>Videos:</strong> MP4, WebM, MOV. Max 20MB each. Custom player with progress bar.<br>
                        Maximum <?= $maxImagesPerEntry ?> media items per entry.</p>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-bolt"></i> Raw Upload Mode <span class="auth-level-admin">ADMIN</span></h3>
                        <p>Skip image processing for faster imports (requires admin privileges):</p>
                        <div class="code-block">
                            <code>"raw_upload": true</code>
                        </div>
                        <p class="note">When enabled, images are saved without resizing or WebP conversion. Use for trusted sources only. <strong>Requires admin privileges.</strong></p>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-hands-clapping"></i> Initial Claps</h3>
                        <p>Set engagement metrics when importing:</p>
                        <div class="code-block">
                            <code>"initial_claps": 25</code>
                        </div>
                        <p class="note">Valid range: 1-50 (normal mode), 1-100,000 (with <code>raw_upload</code> admin mode). Claps are attributed to the authenticated user (author).</p>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-eye"></i> Initial Views <span class="auth-level-admin">ADMIN</span></h3>
                        <p>Set view counts when importing (requires <code>raw_upload</code> mode):</p>
                        <div class="code-block">
                            <code>"initial_views": 1500</code>
                        </div>
                        <p class="note">Only available with <code>raw_upload: true</code> (admin mode). Sets the initial view count for the entry.</p>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-circle-info"></i> Response Format</h3>
                        <p>Successful entry creation returns:</p>
                        <div class="code-block">
                            <code>{
  "id": 123,
  "created_at": "2025-11-28 10:54:34",
  "images": [{
    "id": 456,
    "url": "/uploads/images/1/1_1738246496_abc.webp",
    "width": 1200,
    "height": 800,
    "file_size": 45678
  }],
  "clap_count": 25,
  "user_clap_count": 25,
  "view_count": 1500
}</code>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-magnifying-glass"></i> Full-Text Search</h3>
                        <p>The <code>GET /api/entries</code> and <code>GET /api/users/{nickname}/entries</code> endpoints support full-text search:</p>
                        <div class="code-block">
                            <code># Search is public - no authentication required
curl <?= $baseUrl ?>/api/entries?q=search+term&limit=20

# Search user's entries
curl <?= $baseUrl ?>/api/users/alice/entries?q=example&limit=20</code>
                        </div>
                        <ul>
                            <li><strong>Full-text mode:</strong> 4+ characters with relevance ranking</li>
                            <li><strong>LIKE mode:</strong> 1-3 characters with pattern matching</li>
                            <li><strong>Max length:</strong> 200 characters</li>
                            <li><strong>Public access:</strong> No authentication required</li>
                            <li><strong>Personalization:</strong> Authenticated users get filtered results (muted users hidden)</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-arrows-rotate"></i> Cursor-Based Pagination</h3>
                        <p>Efficient pagination for infinite scroll implementations:</p>
                        <div class="code-block">
                            <code>// First page
GET /api/entries?limit=20

// Next page (use next_cursor from response)
GET /api/entries?limit=20&before=2025-01-15%2010:30:00</code>
                        </div>
                        <p><strong>Response format:</strong></p>
                        <div class="code-block">
                            <code>{
  "entries": [...],
  "has_more": true,
  "next_cursor": "2025-01-15 10:30:00",
  "limit": 20
}</code>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-link"></i> URL Preview Enrichment</h3>
                        <p>Automatic link unfurling using Iframely API:</p>
                        <ul>
                            <li>Detects URLs in entry text automatically</li>
                            <li>Fetches title, description, and thumbnail</li>
                            <li>Caches previews to minimize API calls</li>
                            <li>Graceful fallback if preview fails</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-compress"></i> Short URL Resolution</h3>
                        <p>Automatic resolution of shortened URLs to their final destinations:</p>
                        <ul>
                            <li><strong>Automatic on create:</strong> Short URLs (t.co, bit.ly, tinyurl.com, etc.) are resolved when posting new entries</li>
                            <li><strong>Better previews:</strong> Preview metadata is fetched from the final URL, not the shortener</li>
                            <li><strong>Graceful fallback:</strong> If resolution fails, the original short URL is preserved</li>
                            <li><strong>Admin migration tool:</strong> Bulk resolve existing short URLs via the admin dashboard</li>
                        </ul>
                        <p style="margin-top: 0.75rem;"><strong>Supported shorteners:</strong></p>
                        <div class="code-block">
                            <code>t.co, bit.ly, tinyurl.com, goo.gl, ow.ly, is.gd, buff.ly,
j.mp, dlvr.it, fb.me, lnkd.in, rebrand.ly, cutt.ly, and 20+ more</code>
                        </div>
                    </div>
                </section>
                
                <!-- Performance & Limits Section -->
                <section id="performance" class="section">
                    <h2>Performance & Limits</h2>
                    
                    <h3>Rate Limits</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">5</div>
                            <div class="stat-label">Auth attempts / 5 min</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $rateLimitPerMinute ?></div>
                            <div class="stat-label">Requests / minute</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $rateLimitPerHour ?></div>
                            <div class="stat-label">Requests / hour</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">429</div>
                            <div class="stat-label">HTTP status on exceed</div>
                        </div>
                    </div>
                    
                    <h3>Content Limits</h3>
                    <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Limit</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Entry text</td>
                                <td><?= $maxTextLength ?> characters</td>
                                <td>UTF-8, emoji supported</td>
                            </tr>
                            <tr>
                                <td>Comment text</td>
                                <td><?= $maxTextLength ?> characters</td>
                                <td>UTF-8, emoji supported</td>
                            </tr>
                            <tr>
                                <td>Images per entry</td>
                                <td><?= $maxImagesPerEntry ?> max</td>
                                <td>Per entry or comment</td>
                            </tr>
                            <tr>
                                <td>Media size</td>
                                <td>20MB max</td>
                                <td>Per image or video</td>
                            </tr>
                            <tr>
                                <td>Image formats</td>
                                <td>JPEG, PNG, GIF, WebP, SVG, AVIF</td>
                                <td>Static images converted to WebP, animated GIFs preserved</td>
                            </tr>
                            <tr>
                                <td>Video formats</td>
                                <td>MP4, WebM, MOV</td>
                                <td>Stored as-is with custom player</td>
                            </tr>
                            <tr>
                                <td>Search query</td>
                                <td>200 characters</td>
                                <td>Auto-sanitized</td>
                            </tr>
                            <tr>
                                <td>Initial claps</td>
                                <td>1-50 (normal), 1-100,000 (admin)</td>
                                <td>For imports only (higher limit with raw_upload)</td>
                            </tr>
                            <tr>
                                <td>Initial views</td>
                                <td>0+ (no limit)</td>
                                <td>Admin only (requires raw_upload)</td>
                            </tr>
                            <tr>
                                <td>Claps per action</td>
                                <td>1-50 range</td>
                                <td>Per user per entry</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-list"></i> Pagination Limits</h3>
                        <ul>
                            <li><strong>Default limit:</strong> 20 items per page</li>
                            <li><strong>Max limit (public):</strong> 100 items per page</li>
                            <li><strong>Max limit (user-specific):</strong> 50 items per page</li>
                            <li><strong>Cursor format:</strong> ISO 8601 timestamp</li>
                        </ul>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-database"></i> Caching</h3>
                        <ul>
                            <li><strong>Image ETags:</strong> Browser caching for static images</li>
                            <li><strong>No-cache headers:</strong> Session-dependent content</li>
                            <li><strong>URL previews:</strong> Cached in database to reduce API calls</li>
                        </ul>
                    </div>
                </section>
                
                <!-- Technical Reference Section -->
                <section id="technical-reference" class="section">
                    <h2>Technical Reference</h2>
                    
                    <h3>Response Formats</h3>
                    <div class="feature-card">
                        <h4>Entry Response</h4>
                        <div class="code-block">
                            <code>{
  "id": 123,
  "text": "Entry text",
  "created_at": "2025-02-09 12:00:00",
  "user_id": 456,
  "user_nickname": "alice",
  "clap_count": 10,
  "comment_count": 3,
  "view_count": 42
}</code>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h4>Comment Response</h4>
                        <div class="code-block">
                            <code>{
  "id": 789,
  "text": "Comment text",
  "created_at": "2025-02-09 12:30:00",
  "user_id": 456,
  "user_nickname": "alice",
  "clap_count": 5,
  "view_count": 18
}</code>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h4>Profile Response (GET /api/profile, GET /api/users/{nickname})</h4>
                        <div class="code-block">
                            <code>{
  "id": 456,
  "nickname": "alice",
  "name": "Alice",
  "bio": "Hello world",
  "created_at": "2025-06-15 08:00:00",
  "stats": {
    "entry_count": 42,
    "link_count": 18,
    "comment_count": 7,
    "last_entry_at": "2026-02-09 14:30:00",
    "previous_login_at": "2026-02-08 09:15:22"
  }
}</code>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h4>Error Response</h4>
                        <div class="code-block">
                            <code>{
  "error": "Error message",
  "code": "ERROR_CODE"  // Optional
}</code>
                        </div>
                    </div>
                    
                    <h3>HTTP Status Codes</h3>
                    <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Meaning</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>200</td>
                                <td>OK</td>
                                <td>Request successful</td>
                            </tr>
                            <tr>
                                <td>201</td>
                                <td>Created</td>
                                <td>Resource created successfully</td>
                            </tr>
                            <tr>
                                <td>400</td>
                                <td>Bad Request</td>
                                <td>Invalid request parameters</td>
                            </tr>
                            <tr>
                                <td>401</td>
                                <td>Unauthorized</td>
                                <td>Authentication required or token expired</td>
                            </tr>
                            <tr>
                                <td>403</td>
                                <td>Forbidden</td>
                                <td>Insufficient permissions</td>
                            </tr>
                            <tr>
                                <td>404</td>
                                <td>Not Found</td>
                                <td>Resource not found</td>
                            </tr>
                            <tr>
                                <td>429</td>
                                <td>Too Many Requests</td>
                                <td>Rate limit exceeded</td>
                            </tr>
                            <tr>
                                <td>500</td>
                                <td>Internal Server Error</td>
                                <td>Server error occurred</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    
                    <h3>Error Codes</h3>
                    <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>AUTH_REQUIRED</td>
                                <td>Authentication required for this operation</td>
                            </tr>
                            <tr>
                                <td>INVALID_TOKEN</td>
                                <td>JWT token is invalid or expired</td>
                            </tr>
                            <tr>
                                <td>RATE_LIMIT_EXCEEDED</td>
                                <td>Too many requests, please slow down</td>
                            </tr>
                            <tr>
                                <td>VALIDATION_ERROR</td>
                                <td>Request validation failed</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    
                    <h3>Date Formats</h3>
                    <div class="feature-card">
                        <h4>ISO 8601 (Default)</h4>
                        <div class="code-block">
                            <code>"2025-02-09 12:00:00"  // YYYY-MM-DD HH:MM:SS</code>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h4>Twitter Format (For Imports)</h4>
                        <div class="code-block">
                            <code>"Fri Nov 28 10:54:34 +0000 2025"  // Day Mon DD HH:MM:SS ±ZZZZ YYYY</code>
                        </div>
                        <p>Supported timezones: UTC (+0000), PST (-0800), EST (-0500), etc.</p>
                    </div>
                    
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-hashtag"></i> Hash IDs</h3>
                        <p>Entry IDs are obfuscated using hash IDs for security and aesthetics:</p>
                        <ul>
                            <li><strong>Format:</strong> Alphanumeric string (e.g., "abc123xyz")</li>
                            <li><strong>Usage:</strong> Use in URLs and API calls instead of numeric IDs</li>
                            <li><strong>Decoding:</strong> Automatically handled by API</li>
                            <li><strong>Security:</strong> Prevents enumeration attacks</li>
                        </ul>
                    </div>
                </section>
                
                        <div class="footer">
                    <p><strong>Trail API</strong> - Link Journal Service</p>
                    <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.875rem;">
                        <a href="/" style="color: var(--accent); text-decoration: none;">Home</a> • 
                        <a href="/api/rss" style="color: var(--accent); text-decoration: none;">RSS Feed</a> • 
                        <a href="/api/health" style="color: var(--accent); text-decoration: none;">Health Check</a>
                    </p>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Back to Top Button -->
    <button class="back-to-top" id="back-to-top" aria-label="Back to top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <script src="/assets/js/mermaid.min.js"></script>
    <script src="/assets/js/api-docs.js"></script>
</body>
</html>
