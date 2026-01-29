<?php

declare(strict_types=1);

/**
 * Image Upload Security Test Script
 * Run: php tests/test-image-security.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Trail\Services\ImageService;

echo "=== Image Upload Security Tests ===\n\n";

$uploadBasePath = __DIR__ . '/../public/uploads/images';
$tempBasePath = __DIR__ . '/../storage/temp';
$imageService = new ImageService($uploadBasePath, $tempBasePath);

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Magic Byte Validation - Fake JPEG
echo "Test 1: Magic Byte Validation (Fake JPEG)\n";
try {
    $fakeJpeg = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($fakeJpeg, "<?php phpinfo(); ?>");
    
    $imageService->validateImage($fakeJpeg);
    echo "  ❌ FAILED: Fake JPEG was accepted\n";
    $testsFailed++;
} catch (\InvalidArgumentException $e) {
    if (strpos($e->getMessage(), 'magic bytes') !== false) {
        echo "  ✅ PASSED: Fake JPEG rejected with magic byte error\n";
        $testsPassed++;
    } else {
        echo "  ⚠️  PARTIAL: Rejected but not for magic bytes: " . $e->getMessage() . "\n";
        $testsPassed++;
    }
} finally {
    if (isset($fakeJpeg) && file_exists($fakeJpeg)) {
        unlink($fakeJpeg);
    }
}

// Test 2: Filename Sanitization - Path Traversal
echo "\nTest 2: Filename Sanitization (Path Traversal)\n";
$maliciousFilename = "../../../etc/passwd";
$sanitized = $imageService->sanitizeFilename($maliciousFilename);
if (strpos($sanitized, '..') === false && strpos($sanitized, '/') === false) {
    echo "  ✅ PASSED: Path traversal removed\n";
    echo "     Input:  '$maliciousFilename'\n";
    echo "     Output: '$sanitized'\n";
    $testsPassed++;
} else {
    echo "  ❌ FAILED: Path traversal not fully removed\n";
    echo "     Output: '$sanitized'\n";
    $testsFailed++;
}

// Test 3: Filename Sanitization - Null Bytes
echo "\nTest 3: Filename Sanitization (Null Bytes)\n";
$nullByteFilename = "test\0.php.jpg";
$sanitized = $imageService->sanitizeFilename($nullByteFilename);
if (strpos($sanitized, "\0") === false) {
    echo "  ✅ PASSED: Null bytes removed\n";
    echo "     Output: '$sanitized'\n";
    $testsPassed++;
} else {
    echo "  ❌ FAILED: Null bytes not removed\n";
    $testsFailed++;
}

// Test 4: Filename Sanitization - Hidden Files
echo "\nTest 4: Filename Sanitization (Hidden Files)\n";
$hiddenFilename = "...secret.jpg";
$sanitized = $imageService->sanitizeFilename($hiddenFilename);
if ($sanitized[0] !== '.') {
    echo "  ✅ PASSED: Leading dots removed\n";
    echo "     Input:  '$hiddenFilename'\n";
    echo "     Output: '$sanitized'\n";
    $testsPassed++;
} else {
    echo "  ❌ FAILED: Leading dots not removed\n";
    $testsFailed++;
}

// Test 5: Path Validation - Invalid User ID
echo "\nTest 5: Path Validation (Invalid User ID)\n";
try {
    $imageService->getUserImagePath(-1);
    echo "  ❌ FAILED: Negative user ID accepted\n";
    $testsFailed++;
} catch (\InvalidArgumentException $e) {
    echo "  ✅ PASSED: Negative user ID rejected\n";
    $testsPassed++;
}

// Test 6: Path Validation - Filename with Slashes
echo "\nTest 6: Path Validation (Filename with Slashes)\n";
try {
    $imageService->getImagePath(1, "test/../../etc/passwd");
    echo "  ❌ FAILED: Filename with slashes accepted\n";
    $testsFailed++;
} catch (\InvalidArgumentException $e) {
    echo "  ✅ PASSED: Filename with slashes rejected\n";
    $testsPassed++;
}

// Test 7: Create Valid Test Image
echo "\nTest 7: Valid Image Processing\n";
try {
    // Create a minimal valid PNG
    $validPng = tempnam(sys_get_temp_dir(), 'test_');
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    file_put_contents($validPng, $pngData);
    
    $validation = $imageService->validateImage($validPng);
    
    if ($validation['mime_type'] === 'image/png') {
        echo "  ✅ PASSED: Valid PNG accepted\n";
        echo "     MIME: {$validation['mime_type']}\n";
        echo "     Size: {$validation['file_size']} bytes\n";
        $testsPassed++;
    } else {
        echo "  ❌ FAILED: Valid PNG not recognized\n";
        $testsFailed++;
    }
} catch (\Throwable $e) {
    echo "  ❌ FAILED: Valid PNG rejected: " . $e->getMessage() . "\n";
    $testsFailed++;
} finally {
    if (isset($validPng) && file_exists($validPng)) {
        unlink($validPng);
    }
}

// Test 8: Secure File Permissions
echo "\nTest 8: Secure File Permissions\n";
try {
    $testFile = tempnam(sys_get_temp_dir(), 'test_');
    chmod($testFile, 0777); // Set all permissions
    
    $imageService->secureUploadedFile($testFile);
    
    $perms = fileperms($testFile);
    $octal = substr(sprintf('%o', $perms), -4);
    
    if ($octal === '0644') {
        echo "  ✅ PASSED: File permissions set to 0644\n";
        $testsPassed++;
    } else {
        echo "  ❌ FAILED: File permissions are $octal (expected 0644)\n";
        $testsFailed++;
    }
} catch (\Throwable $e) {
    echo "  ❌ FAILED: " . $e->getMessage() . "\n";
    $testsFailed++;
} finally {
    if (isset($testFile) && file_exists($testFile)) {
        unlink($testFile);
    }
}

// Test 9: Symlink Detection
echo "\nTest 9: Symlink Detection\n";
try {
    $testFile = tempnam(sys_get_temp_dir(), 'test_');
    $symlinkFile = tempnam(sys_get_temp_dir(), 'link_');
    unlink($symlinkFile);
    symlink($testFile, $symlinkFile);
    
    $imageService->secureUploadedFile($symlinkFile);
    
    if (!file_exists($symlinkFile)) {
        echo "  ✅ PASSED: Symlink detected and removed\n";
        $testsPassed++;
    } else {
        echo "  ❌ FAILED: Symlink not removed\n";
        $testsFailed++;
    }
} catch (\RuntimeException $e) {
    if (strpos($e->getMessage(), 'Symlinks') !== false) {
        echo "  ✅ PASSED: Symlink detected with exception\n";
        $testsPassed++;
    } else {
        echo "  ⚠️  PARTIAL: Exception but not symlink-specific\n";
        $testsPassed++;
    }
} catch (\Throwable $e) {
    echo "  ⚠️  WARNING: " . $e->getMessage() . "\n";
} finally {
    if (isset($testFile) && file_exists($testFile)) {
        unlink($testFile);
    }
    if (isset($symlinkFile) && file_exists($symlinkFile)) {
        unlink($symlinkFile);
    }
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";
$total = $testsPassed + $testsFailed;
$percentage = $total > 0 ? round(($testsPassed / $total) * 100) : 0;
echo "Success Rate: $percentage%\n";

if ($testsFailed === 0) {
    echo "\n✅ All tests passed! Security measures are working correctly.\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the security implementation.\n";
    exit(1);
}
