<?php
/**
 * Manual test for custom date entry creation
 * 
 * This demonstrates the new API features:
 * - Custom created_at in Twitter format
 * - Inline media upload with base64 encoding
 * - Raw upload option
 * - Initial claps
 * 
 * Usage:
 * 1. Get a valid JWT token from your authentication
 * 2. Update the $jwtToken variable below
 * 3. Run: php backend/tests/manual-custom-date-entry-test.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Configuration
$baseUrl = 'http://localhost:8080'; // Adjust to your local server
$jwtToken = 'YOUR_JWT_TOKEN_HERE'; // Replace with valid JWT token

// Example 1: Entry with custom date only
echo "Test 1: Entry with custom date\n";
echo "================================\n";

$response1 = makeRequest($baseUrl . '/api/entries', [
    'text' => 'This is a backdated entry from Twitter!',
    'created_at' => 'Fri Nov 28 10:54:34 +0000 2025'
], $jwtToken);

echo "Response: " . json_encode($response1, JSON_PRETTY_PRINT) . "\n\n";

// Example 2: Entry with inline media (processed)
echo "Test 2: Entry with inline media (processed)\n";
echo "==========================================\n";

// Create a small test image (1x1 red pixel PNG)
$testImageData = base64_encode(file_get_contents(__DIR__ . '/../public/assets/app-icon.webp'));

$response2 = makeRequest($baseUrl . '/api/entries', [
    'text' => 'Entry with inline image upload',
    'media' => [
        [
            'data' => $testImageData,
            'filename' => 'test-image.webp',
            'mime_type' => 'image/webp',
            'image_type' => 'post'
        ]
    ],
    'raw_upload' => false
], $jwtToken);

echo "Response: " . json_encode($response2, JSON_PRETTY_PRINT) . "\n\n";

// Example 3: Entry with custom date, media, and initial claps
echo "Test 3: Complete example (date + media + claps)\n";
echo "===============================================\n";

$response3 = makeRequest($baseUrl . '/api/entries', [
    'text' => 'Imported tweet with all features!',
    'created_at' => 'Mon Jan 15 14:30:00 +0000 2024',
    'media' => [
        [
            'data' => $testImageData,
            'filename' => 'imported-photo.webp',
            'mime_type' => 'image/webp',
            'image_type' => 'post'
        ]
    ],
    'raw_upload' => true, // Skip processing
    'initial_claps' => 10
], $jwtToken);

echo "Response: " . json_encode($response3, JSON_PRETTY_PRINT) . "\n\n";

// Example 4: Test error handling - invalid date format
echo "Test 4: Error handling - invalid date\n";
echo "====================================\n";

$response4 = makeRequest($baseUrl . '/api/entries', [
    'text' => 'This should fail',
    'created_at' => 'Invalid date format'
], $jwtToken);

echo "Response: " . json_encode($response4, JSON_PRETTY_PRINT) . "\n\n";

// Example 5: Test error handling - invalid clap count
echo "Test 5: Error handling - invalid claps (will be logged but not fail)\n";
echo "===================================================================\n";

$response5 = makeRequest($baseUrl . '/api/entries', [
    'text' => 'Testing clap validation',
    'initial_claps' => 100 // Over limit
], $jwtToken);

echo "Response: " . json_encode($response5, JSON_PRETTY_PRINT) . "\n\n";

/**
 * Helper function to make API requests
 */
function makeRequest(string $url, array $data, string $jwtToken): array
{
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $jwtToken
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    return [
        'http_code' => $httpCode,
        'data' => $decoded
    ];
}

echo "All tests completed!\n";
echo "\nNote: Make sure to:\n";
echo "1. Replace YOUR_JWT_TOKEN_HERE with a valid JWT token\n";
echo "2. Run the database migration: 023_simplify_created_at.sql\n";
echo "3. Adjust \$baseUrl if your server runs on a different port\n";
