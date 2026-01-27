#!/usr/bin/env php
<?php
/**
 * Test script specifically for Medium oEmbed functionality
 * 
 * Usage: php test-medium-oembed.php
 */

declare(strict_types=1);

echo "╔════════════════════════════════════════╗\n";
echo "║   Medium oEmbed API Test               ║\n";
echo "╔════════════════════════════════════════╗\n";
echo "\n";

// Test Medium oEmbed API directly
function testMediumOembed(string $url): void
{
    echo "Testing URL: {$url}\n";
    echo str_repeat("-", 60) . "\n";
    
    $oembedUrl = 'https://medium.com/services/oembed?url=' . urlencode($url) . '&format=json';
    echo "oEmbed URL: {$oembedUrl}\n\n";
    
    $ch = curl_init($oembedUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.app)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Status: {$httpCode}\n";
    echo "Duration: {$duration}ms\n";
    
    if ($error) {
        echo "❌ cURL Error: {$error}\n";
        echo "\n";
        return;
    }
    
    if ($httpCode !== 200) {
        echo "❌ Failed with HTTP {$httpCode}\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
        echo "\n";
        return;
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        echo "❌ Invalid JSON response\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
        echo "\n";
        return;
    }
    
    echo "✅ Success!\n\n";
    
    echo "Metadata:\n";
    echo "  Type: " . ($data['type'] ?? 'N/A') . "\n";
    echo "  Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "  Author: " . ($data['author_name'] ?? 'N/A') . "\n";
    echo "  Author URL: " . ($data['author_url'] ?? 'N/A') . "\n";
    echo "  Provider: " . ($data['provider_name'] ?? 'N/A') . "\n";
    echo "  Thumbnail: " . ($data['thumbnail_url'] ?? 'N/A') . "\n";
    
    if (isset($data['thumbnail_width']) && isset($data['thumbnail_height'])) {
        echo "  Thumbnail Size: {$data['thumbnail_width']}x{$data['thumbnail_height']}\n";
    }
    
    if (isset($data['html'])) {
        $html = strip_tags($data['html']);
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);
        $preview = substr($html, 0, 150);
        echo "  Description (from HTML): {$preview}...\n";
    }
    
    echo "\n";
    echo "Raw JSON:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    echo "\n";
}

// Test cases
$testUrls = [
    // Medium homepage
    'https://medium.com',
    
    // Example Medium article (may or may not exist)
    'https://medium.com/@Medium/about-medium-9eac453da935',
    
    // Medium publication
    'https://medium.com/topic/technology',
];

foreach ($testUrls as $i => $url) {
    $testNum = $i + 1;
    echo "═══════════════════════════════════════════\n";
    echo "Test {$testNum} of " . count($testUrls) . "\n";
    echo "═══════════════════════════════════════════\n\n";
    
    testMediumOembed($url);
    
    if ($i < count($testUrls) - 1) {
        echo "\n";
        sleep(1); // Be nice to Medium's servers
    }
}

echo "╔════════════════════════════════════════╗\n";
echo "║   Test Complete                        ║\n";
echo "╔════════════════════════════════════════╗\n";
echo "\n";
echo "Note: Some URLs may fail if they don't exist or are paywalled.\n";
echo "Try testing with actual Medium article URLs you know exist.\n";
echo "\n";
