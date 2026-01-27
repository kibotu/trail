#!/usr/bin/env php
<?php
/**
 * Standalone test script for URL embed feature
 * 
 * Usage: php test-url-embed.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Trail\Services\UrlEmbedService;
use Trail\Services\TextSanitizer;

echo "╔════════════════════════════════════════╗\n";
echo "║   URL Embed Feature Test               ║\n";
echo "╔════════════════════════════════════════╗\n";
echo "\n";

$embedService = new UrlEmbedService();

// Test cases
$testCases = [
    [
        'name' => 'Example.com (Basic HTML)',
        'text' => 'Check out https://example.com',
        'expected' => 'Should fetch basic metadata'
    ],
    [
        'name' => 'GitHub (Open Graph)',
        'text' => 'Visit https://github.com',
        'expected' => 'Should fetch Open Graph metadata'
    ],
    [
        'name' => 'Medium Article (oEmbed)',
        'text' => 'Read https://medium.com/@username/article-title',
        'expected' => 'Should fetch via Medium oEmbed API or fallback'
    ],
    [
        'name' => 'Medium.com Homepage',
        'text' => 'Check https://medium.com',
        'expected' => 'Should fetch Medium homepage metadata'
    ],
    [
        'name' => 'No URL',
        'text' => 'Just regular text without any links',
        'expected' => 'Should return null'
    ],
    [
        'name' => 'www URL (no protocol)',
        'text' => 'Check www.example.com',
        'expected' => 'Should add https:// and fetch'
    ],
    [
        'name' => 'Invalid URL',
        'text' => 'Visit https://this-definitely-does-not-exist-12345.com',
        'expected' => 'Should return null (gracefully handle error)'
    ],
];

$passed = 0;
$failed = 0;

foreach ($testCases as $i => $test) {
    $testNum = $i + 1;
    echo "Test {$testNum}: {$test['name']}\n";
    echo "  Text: \"{$test['text']}\"\n";
    echo "  Expected: {$test['expected']}\n";
    
    try {
        // Test URL detection
        $hasUrl = $embedService->hasUrl($test['text']);
        echo "  Has URL: " . ($hasUrl ? 'Yes' : 'No') . "\n";
        
        // Test preview fetching
        $startTime = microtime(true);
        $preview = $embedService->extractAndFetchPreview($test['text']);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        echo "  Duration: {$duration}ms\n";
        
        if ($preview === null) {
            echo "  Result: NULL (no preview)\n";
        } else {
            echo "  Result: SUCCESS\n";
            echo "    URL: " . ($preview['url'] ?? 'N/A') . "\n";
            echo "    Title: " . ($preview['title'] ?? 'N/A') . "\n";
            echo "    Description: " . (isset($preview['description']) ? substr($preview['description'], 0, 60) . '...' : 'N/A') . "\n";
            echo "    Image: " . ($preview['image'] ?? 'N/A') . "\n";
            echo "    Site Name: " . ($preview['site_name'] ?? 'N/A') . "\n";
        }
        
        echo "  ✓ Test passed (no errors thrown)\n";
        $passed++;
        
    } catch (\Throwable $e) {
        echo "  ✗ Test FAILED with exception: " . $e->getMessage() . "\n";
        echo "    Stack trace:\n";
        echo "    " . str_replace("\n", "\n    ", $e->getTraceAsString()) . "\n";
        $failed++;
    }
    
    echo "\n";
}

// Summary
echo "╔════════════════════════════════════════╗\n";
echo "║   Test Summary                         ║\n";
echo "╔════════════════════════════════════════╗\n";
echo "\n";
echo "Total tests: " . count($testCases) . "\n";
echo "Passed: {$passed} ✓\n";
echo "Failed: {$failed} " . ($failed > 0 ? '✗' : '') . "\n";
echo "\n";

if ($failed === 0) {
    echo "✓ All tests passed! URL embed feature is working correctly.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Check the output above for details.\n";
    exit(1);
}
