<?php

declare(strict_types=1);

namespace Trail\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Models\User;
use Trail\Models\UrlPreview;
use Trail\Services\UrlEmbedService;

/**
 * Integration test for URL preview functionality with caching
 * 
 * This test requires a test database connection.
 * Run with: vendor/bin/phpunit tests/Integration/UrlPreviewIntegrationTest.php
 */
class UrlPreviewIntegrationTest extends TestCase
{
    private static ?\PDO $db = null;
    private static ?int $testUserId = null;
    private static ?array $config = null;

    public static function setUpBeforeClass(): void
    {
        // Skip if no test config available
        if (!file_exists(__DIR__ . '/../../secrets.yml')) {
            self::markTestSkipped('secrets.yml not found - skipping integration tests');
        }

        try {
            self::$config = Config::load(__DIR__ . '/../../secrets.yml');
            self::$db = Database::getInstance(self::$config);
            
            // Create test user
            $userModel = new User(self::$db);
            $testEmail = 'test-url-preview-' . time() . '@example.com';
            self::$testUserId = $userModel->findOrCreate(
                'test-google-id-' . time(),
                $testEmail,
                'Test User',
                false
            );
        } catch (\Exception $e) {
            self::markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$db && self::$testUserId) {
            try {
                $stmt = self::$db->prepare("DELETE FROM trail_entries WHERE user_id = ?");
                $stmt->execute([self::$testUserId]);
                
                $stmt = self::$db->prepare("DELETE FROM trail_users WHERE id = ?");
                $stmt->execute([self::$testUserId]);
                
                // Clean up orphaned URL previews (those not referenced by any entry)
                $stmt = self::$db->prepare("
                    DELETE FROM trail_url_previews 
                    WHERE id NOT IN (SELECT DISTINCT url_preview_id FROM trail_entries WHERE url_preview_id IS NOT NULL)
                ");
                $stmt->execute();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }

    public function testCreateEntryWithUrlStoresPreview(): void
    {
        if (!self::$db || !self::$testUserId) {
            $this->markTestSkipped('Database not available');
        }

        $entryModel = new Entry(self::$db);
        $embedService = new UrlEmbedService(self::$config, null, self::$db);

        $text = "Check out https://example.com - it's a test domain";
        
        // Extract and get preview ID (with caching)
        $urlPreviewId = $embedService->extractAndGetPreviewId($text);
        
        // Create entry with preview ID
        $entryId = $entryModel->create(self::$testUserId, $text, $urlPreviewId);
        
        $this->assertGreaterThan(0, $entryId);
        
        // Fetch the entry back
        $entry = $entryModel->findById($entryId);
        
        $this->assertNotNull($entry);
        $this->assertEquals($text, $entry['text']);
        
        // If preview was fetched successfully, verify it's stored
        if ($urlPreviewId !== null) {
            $this->assertNotNull($entry['preview_url']);
            $this->assertStringContainsString('example.com', $entry['preview_url']);
        }
        
        // Clean up
        $entryModel->delete($entryId);
    }

    public function testCreateEntryWithoutUrlHasNullPreview(): void
    {
        if (!self::$db || !self::$testUserId) {
            $this->markTestSkipped('Database not available');
        }

        $entryModel = new Entry(self::$db);
        $embedService = new UrlEmbedService(self::$config, null, self::$db);

        $text = "Just a regular post without any links";
        
        // Extract and get preview ID (should be null)
        $urlPreviewId = $embedService->extractAndGetPreviewId($text);
        
        $this->assertNull($urlPreviewId);
        
        // Create entry without preview
        $entryId = $entryModel->create(self::$testUserId, $text, $urlPreviewId);
        
        $this->assertGreaterThan(0, $entryId);
        
        // Fetch the entry back
        $entry = $entryModel->findById($entryId);
        
        $this->assertNotNull($entry);
        $this->assertEquals($text, $entry['text']);
        $this->assertNull($entry['preview_url']);
        $this->assertNull($entry['preview_title']);
        $this->assertNull($entry['preview_description']);
        
        // Clean up
        $entryModel->delete($entryId);
    }

    public function testUpdateEntryWithUrlUpdatesPreview(): void
    {
        if (!self::$db || !self::$testUserId) {
            $this->markTestSkipped('Database not available');
        }

        $entryModel = new Entry(self::$db);
        $embedService = new UrlEmbedService(self::$config, null, self::$db);

        // Create entry without URL
        $originalText = "Original post without URL";
        $entryId = $entryModel->create(self::$testUserId, $originalText, null);
        
        // Update with URL
        $newText = "Updated post with https://example.com";
        $urlPreviewId = $embedService->extractAndGetPreviewId($newText);
        
        $success = $entryModel->update($entryId, $newText, $urlPreviewId);
        $this->assertTrue($success);
        
        // Fetch updated entry
        $entry = $entryModel->findById($entryId);
        
        $this->assertEquals($newText, $entry['text']);
        
        // If preview was fetched, verify it's updated
        if ($urlPreviewId !== null) {
            $this->assertNotNull($entry['preview_url']);
        }
        
        // Clean up
        $entryModel->delete($entryId);
    }

    public function testSameUrlUsesCachedPreview(): void
    {
        if (!self::$db || !self::$testUserId) {
            $this->markTestSkipped('Database not available');
        }

        $entryModel = new Entry(self::$db);
        $embedService = new UrlEmbedService(self::$config, null, self::$db);
        $urlPreviewModel = new UrlPreview(self::$db);

        $testUrl = "https://example.com/test-" . time();
        $text1 = "First post with {$testUrl}";
        $text2 = "Second post with {$testUrl}";
        
        // Get initial cache count
        $initialCount = $urlPreviewModel->count();
        
        // Create first entry - should fetch and cache
        $urlPreviewId1 = $embedService->extractAndGetPreviewId($text1);
        $entryId1 = $entryModel->create(self::$testUserId, $text1, $urlPreviewId1);
        
        // Check cache increased by 1 (if preview was fetched)
        if ($urlPreviewId1 !== null) {
            $afterFirstCount = $urlPreviewModel->count();
            $this->assertEquals($initialCount + 1, $afterFirstCount);
            
            // Create second entry with same URL - should use cache
            $urlPreviewId2 = $embedService->extractAndGetPreviewId($text2);
            $entryId2 = $entryModel->create(self::$testUserId, $text2, $urlPreviewId2);
            
            // Cache count should not increase
            $afterSecondCount = $urlPreviewModel->count();
            $this->assertEquals($afterFirstCount, $afterSecondCount);
            
            // Both entries should reference the same preview
            $this->assertEquals($urlPreviewId1, $urlPreviewId2);
            
            // Clean up
            $entryModel->delete($entryId2);
        }
        
        // Clean up
        $entryModel->delete($entryId1);
    }

    public function testUrlNormalizationDeduplicates(): void
    {
        if (!self::$db || !self::$testUserId) {
            $this->markTestSkipped('Database not available');
        }

        $entryModel = new Entry(self::$db);
        $embedService = new UrlEmbedService(self::$config, null, self::$db);

        $baseUrl = "https://example.com/page";
        $text1 = "Post with {$baseUrl}";
        $text2 = "Post with {$baseUrl}?utm_source=test"; // Should normalize to same URL
        $text3 = "Post with {$baseUrl}/"; // Trailing slash should normalize
        
        // Create entries
        $urlPreviewId1 = $embedService->extractAndGetPreviewId($text1);
        $urlPreviewId2 = $embedService->extractAndGetPreviewId($text2);
        $urlPreviewId3 = $embedService->extractAndGetPreviewId($text3);
        
        // All should reference the same cached preview (if fetched)
        if ($urlPreviewId1 !== null) {
            $this->assertEquals($urlPreviewId1, $urlPreviewId2, "URL with tracking params should use same cache");
            $this->assertEquals($urlPreviewId1, $urlPreviewId3, "URL with trailing slash should use same cache");
        }
    }
}
