#!/usr/bin/env php
<?php
/**
 * Test script specifically for Medium RSS feed functionality
 * 
 * Usage: php test-medium-rss.php [username]
 */

declare(strict_types=1);

echo "╔════════════════════════════════════════╗\n";
echo "║   Medium RSS Feed Test                 ║\n";
echo "╔════════════════════════════════════════╗\n";
echo "\n";

// Get username from command line or use default
$username = $argv[1] ?? 'Medium';

echo "Testing RSS feed for: @{$username}\n";
echo str_repeat("-", 60) . "\n\n";

// Build RSS feed URL
$rssFeedUrl = "https://medium.com/feed/@{$username}";
echo "RSS Feed URL: {$rssFeedUrl}\n\n";

// Fetch RSS feed
$ch = curl_init($rssFeedUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.app)',
    CURLOPT_HTTPHEADER => [
        'Accept: application/rss+xml, application/xml, text/xml',
    ],
]);

$startTime = microtime(true);
$rssContent = curl_exec($ch);
$duration = round((microtime(true) - $startTime) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "Duration: {$duration}ms\n";

if ($error) {
    echo "❌ cURL Error: {$error}\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ Failed with HTTP {$httpCode}\n";
    echo "Response: " . substr($rssContent, 0, 200) . "...\n";
    exit(1);
}

echo "✅ RSS feed fetched successfully!\n\n";

// Parse RSS feed
$xml = @simplexml_load_string($rssContent);

if (!$xml) {
    echo "❌ Failed to parse XML\n";
    exit(1);
}

echo "RSS Feed Information:\n";
echo "  Title: " . (string) $xml->channel->title . "\n";
echo "  Description: " . (string) $xml->channel->description . "\n";
echo "  Link: " . (string) $xml->channel->link . "\n";
echo "\n";

// Count items
$itemCount = count($xml->channel->item);
echo "Found {$itemCount} articles in feed\n\n";

if ($itemCount === 0) {
    echo "⚠️ No articles found in RSS feed\n";
    exit(0);
}

// Display first 5 articles
echo "Recent Articles:\n";
echo str_repeat("=", 60) . "\n\n";

$displayCount = min(5, $itemCount);
for ($i = 0; $i < $displayCount; $i++) {
    $item = $xml->channel->item[$i];
    
    echo "Article " . ($i + 1) . ":\n";
    echo "  Title: " . (string) $item->title . "\n";
    echo "  Link: " . (string) $item->link . "\n";
    echo "  Published: " . (string) $item->pubDate . "\n";
    
    // Extract description
    $description = (string) $item->description;
    $description = strip_tags($description);
    $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $description = preg_replace('/\s+/', ' ', $description);
    $description = trim($description);
    $preview = substr($description, 0, 100);
    echo "  Description: {$preview}...\n";
    
    // Check for author
    $creator = $item->children('http://purl.org/dc/elements/1.1/');
    if (isset($creator->creator)) {
        echo "  Author: " . (string) $creator->creator . "\n";
    }
    
    // Check for images
    $hasImage = false;
    
    // Check content:encoded
    $contentEncoded = $item->children('http://purl.org/rss/1.0/modules/content/');
    if (isset($contentEncoded->encoded)) {
        $content = (string) $contentEncoded->encoded;
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            echo "  Image (from content): " . substr($matches[1], 0, 60) . "...\n";
            $hasImage = true;
        }
    }
    
    // Check media:thumbnail
    if (!$hasImage) {
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->thumbnail)) {
            $imageUrl = (string) $media->thumbnail->attributes()->url;
            echo "  Image (from thumbnail): " . substr($imageUrl, 0, 60) . "...\n";
            $hasImage = true;
        }
    }
    
    // Check media:content
    if (!$hasImage && isset($media->content)) {
        $imageUrl = (string) $media->content->attributes()->url;
        echo "  Image (from content): " . substr($imageUrl, 0, 60) . "...\n";
        $hasImage = true;
    }
    
    if (!$hasImage) {
        echo "  Image: Not found\n";
    }
    
    echo "\n";
}

// Test URL matching
echo str_repeat("=", 60) . "\n";
echo "Testing URL Matching:\n\n";

// Get first article URL
$firstArticleUrl = (string) $xml->channel->item[0]->link;
echo "First article URL: {$firstArticleUrl}\n";

// Test normalization
function normalizeUrl(string $url): string
{
    $parsed = parse_url($url);
    
    $normalized = ($parsed['scheme'] ?? 'https') . '://';
    $normalized .= $parsed['host'] ?? '';
    $normalized .= $parsed['path'] ?? '';
    
    // Remove trailing slash
    $normalized = rtrim($normalized, '/');
    
    return strtolower($normalized);
}

$normalized = normalizeUrl($firstArticleUrl);
echo "Normalized URL: {$normalized}\n\n";

// Test variations
$testUrls = [
    $firstArticleUrl,
    $firstArticleUrl . '/',
    $firstArticleUrl . '?source=rss',
    rtrim($firstArticleUrl, '/'),
];

echo "Testing URL variations (should all match):\n";
foreach ($testUrls as $testUrl) {
    $testNormalized = normalizeUrl($testUrl);
    $match = ($testNormalized === $normalized) ? '✅' : '❌';
    echo "  {$match} {$testUrl}\n";
    echo "      → {$testNormalized}\n";
}

echo "\n";
echo "╔════════════════════════════════════════╗\n";
echo "║   Test Complete                        ║\n";
echo "╔════════════════════════════════════════╗\n";
echo "\n";

echo "Summary:\n";
echo "  RSS Feed: ✅ Working\n";
echo "  Articles Found: {$itemCount}\n";
echo "  Metadata: ✅ Available\n";
echo "  Images: " . ($hasImage ? '✅' : '⚠️') . " " . ($hasImage ? 'Found' : 'Check content:encoded') . "\n";
echo "\n";

echo "To test with a specific article:\n";
echo "  1. Copy an article URL from above\n";
echo "  2. Use it in your application\n";
echo "  3. The RSS feed method will find and extract metadata\n";
echo "\n";

echo "Note: RSS feeds typically show only the latest ~10 articles.\n";
echo "      Older articles will fall back to other methods.\n";
echo "\n";
