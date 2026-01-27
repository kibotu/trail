<?php

declare(strict_types=1);

namespace Trail\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Models\User;
use Trail\Services\UrlEmbedService;

/**
 * Integration test for URL preview functionality
 * 
 * This test requires a test database connection.
 * Run with: vendor/bin/phpunit tests/Integration/UrlPreviewIntegrationTest.php
 */
class UrlPreviewIntegrationTest extends TestCase
{
    private static ?\PDO $db = null;
    private static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        // Skip if no test config available
        if (!file_exists(__DIR__ . '/../../secrets.yml')) {
            self::markTestSkipped('secrets.yml not found - skipping integration tests');
        }

        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            self::$db = Database::getInstance($config);
            
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
        $embedService = new UrlEmbedService();

        $text = "Check out https://example.com - it's a test domain";
        
        // Extract and fetch preview
        $preview = $embedService->extractAndFetchPreview($text);
        
        // Create entry with preview
        $entryId = $entryModel->create(self::$testUserId, $text, $preview);
        
        $this->assertGreaterThan(0, $entryId);
        
        // Fetch the entry back
        $entry = $entryModel->findById($entryId);
        
        $this->assertNotNull($entry);
        $this->assertEquals($text, $entry['text']);
        
        // If preview was fetched successfully, verify it's stored
        if ($preview !== null) {
            $this->assertNotNull($entry['preview_url']);
            $this->assertEquals($preview['url'], $entry['preview_url']);
            
            if (isset($preview['title'])) {
                $this->assertEquals($preview['title'], $entry['preview_title']);
            }
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
        $embedService = new UrlEmbedService();

        $text = "Just a regular post without any links";
        
        // Extract and fetch preview (should be null)
        $preview = $embedService->extractAndFetchPreview($text);
        
        $this->assertNull($preview);
        
        // Create entry without preview
        $entryId = $entryModel->create(self::$testUserId, $text, $preview);
        
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
        $embedService = new UrlEmbedService();

        // Create entry without URL
        $originalText = "Original post without URL";
        $entryId = $entryModel->create(self::$testUserId, $originalText, null);
        
        // Update with URL
        $newText = "Updated post with https://example.com";
        $preview = $embedService->extractAndFetchPreview($newText);
        
        $success = $entryModel->update($entryId, $newText, $preview);
        $this->assertTrue($success);
        
        // Fetch updated entry
        $entry = $entryModel->findById($entryId);
        
        $this->assertEquals($newText, $entry['text']);
        
        // If preview was fetched, verify it's updated
        if ($preview !== null) {
            $this->assertNotNull($entry['preview_url']);
        }
        
        // Clean up
        $entryModel->delete($entryId);
    }
}
