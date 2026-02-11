<?php
// Test script to check preview data availability
require_once __DIR__ . '/../vendor/autoload.php';

use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Services\HashIdService;

$config = Config::load(__DIR__ . '/../secrets.yml');
$db = Database::getInstance($config);

// Get a few recent entries with URL previews
$sql = "SELECT e.id, e.text, e.created_at,
        u.nickname as user_nickname, u.name as user_name,
        p.url as preview_url, p.title as preview_title, 
        p.description as preview_description,
        p.image as preview_image, p.site_name as preview_site_name
        FROM trail_entries e
        JOIN trail_users u ON e.user_id = u.id
        LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
        WHERE e.url_preview_id IS NOT NULL
        ORDER BY e.created_at DESC
        LIMIT 5";

$stmt = $db->query($sql);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
$hashIdService = new HashIdService($hashSalt);

echo "<h1>Entries with URL Previews</h1>";
echo "<style>table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style>";
echo "<table>";
echo "<tr><th>Hash ID</th><th>Text</th><th>Preview Title</th><th>Preview Image</th><th>Site</th><th>Test</th></tr>";

foreach ($entries as $entry) {
    $hashId = $hashIdService->encode((int)$entry['id']);
    $text = mb_substr($entry['text'] ?? '', 0, 50);
    $title = mb_substr($entry['preview_title'] ?? 'N/A', 0, 50);
    $hasImage = !empty($entry['preview_image']) ? '✓' : '✗';
    $site = $entry['preview_site_name'] ?? 'N/A';
    
    echo "<tr>";
    echo "<td><a href='/status/{$hashId}'>{$hashId}</a></td>";
    echo "<td>" . htmlspecialchars($text) . "</td>";
    echo "<td>" . htmlspecialchars($title) . "</td>";
    echo "<td>{$hasImage}</td>";
    echo "<td>" . htmlspecialchars($site) . "</td>";
    echo "<td><a href='/api/preview-image/{$hashId}.png' target='_blank'>View Card</a></td>";
    echo "</tr>";
}

echo "</table>";
