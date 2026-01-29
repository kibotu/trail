<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\HashIdService;

/**
 * Test that hash IDs remain stable regardless of entry deletion order
 * 
 * This verifies that permalinks continue to work even when entries
 * at different positions in the list are deleted.
 */
class HashIdStabilityTest extends TestCase
{
    private HashIdService $hashIdService;
    
    protected function setUp(): void
    {
        // Use a consistent salt for testing
        $this->hashIdService = new HashIdService('test_salt_for_permalink_stability');
    }
    
    /**
     * Test that the same ID always produces the same hash
     */
    public function testHashIdDeterministic(): void
    {
        $id = 42;
        
        $hash1 = $this->hashIdService->encode($id);
        $hash2 = $this->hashIdService->encode($id);
        
        $this->assertEquals($hash1, $hash2, 'Same ID should always produce same hash');
    }
    
    /**
     * Test that hash IDs are reversible
     */
    public function testHashIdReversible(): void
    {
        $originalId = 123;
        
        $hash = $this->hashIdService->encode($originalId);
        $decodedId = $this->hashIdService->decode($hash);
        
        $this->assertEquals($originalId, $decodedId, 'Decoded ID should match original');
    }
    
    /**
     * Test that deleting entries doesn't affect other entry hash IDs
     * 
     * Scenario: We have entries with IDs [1, 2, 3, 4, 5]
     * - Delete entry 3 (middle)
     * - Verify entries 1, 2, 4, 5 still have the same hash IDs
     */
    public function testHashIdStableAfterMiddleDeletion(): void
    {
        // Simulate 5 entries
        $entryIds = [1, 2, 3, 4, 5];
        
        // Generate hash IDs for all entries
        $hashIds = [];
        foreach ($entryIds as $id) {
            $hashIds[$id] = $this->hashIdService->encode($id);
        }
        
        // Simulate deletion of entry 3 (middle entry)
        // In real system, entry 3 is removed from database but IDs 1,2,4,5 remain unchanged
        $remainingIds = [1, 2, 4, 5];
        
        // Verify that remaining entries still have the same hash IDs
        foreach ($remainingIds as $id) {
            $currentHash = $this->hashIdService->encode($id);
            $this->assertEquals(
                $hashIds[$id],
                $currentHash,
                "Entry {$id} hash should remain stable after deleting entry 3"
            );
        }
    }
    
    /**
     * Test that deleting the first entry doesn't affect other entries
     */
    public function testHashIdStableAfterFirstDeletion(): void
    {
        $entryIds = [1, 2, 3, 4, 5];
        
        // Generate initial hash IDs
        $hashIds = [];
        foreach ($entryIds as $id) {
            $hashIds[$id] = $this->hashIdService->encode($id);
        }
        
        // Simulate deletion of entry 1 (first entry)
        $remainingIds = [2, 3, 4, 5];
        
        // Verify remaining entries have stable hash IDs
        foreach ($remainingIds as $id) {
            $currentHash = $this->hashIdService->encode($id);
            $this->assertEquals(
                $hashIds[$id],
                $currentHash,
                "Entry {$id} hash should remain stable after deleting entry 1"
            );
        }
    }
    
    /**
     * Test that deleting the last entry doesn't affect other entries
     */
    public function testHashIdStableAfterLastDeletion(): void
    {
        $entryIds = [1, 2, 3, 4, 5];
        
        // Generate initial hash IDs
        $hashIds = [];
        foreach ($entryIds as $id) {
            $hashIds[$id] = $this->hashIdService->encode($id);
        }
        
        // Simulate deletion of entry 5 (last entry)
        $remainingIds = [1, 2, 3, 4];
        
        // Verify remaining entries have stable hash IDs
        foreach ($remainingIds as $id) {
            $currentHash = $this->hashIdService->encode($id);
            $this->assertEquals(
                $hashIds[$id],
                $currentHash,
                "Entry {$id} hash should remain stable after deleting entry 5"
            );
        }
    }
    
    /**
     * Test that deleting multiple entries doesn't affect remaining ones
     */
    public function testHashIdStableAfterMultipleDeletions(): void
    {
        $entryIds = range(1, 10);
        
        // Generate initial hash IDs
        $hashIds = [];
        foreach ($entryIds as $id) {
            $hashIds[$id] = $this->hashIdService->encode($id);
        }
        
        // Simulate deletion of entries 2, 5, 7, 9 (scattered deletions)
        $deletedIds = [2, 5, 7, 9];
        $remainingIds = array_diff($entryIds, $deletedIds);
        
        // Verify all remaining entries have stable hash IDs
        foreach ($remainingIds as $id) {
            $currentHash = $this->hashIdService->encode($id);
            $this->assertEquals(
                $hashIds[$id],
                $currentHash,
                "Entry {$id} hash should remain stable after deleting entries " . implode(', ', $deletedIds)
            );
        }
    }
    
    /**
     * Test that hash IDs are unique for different entry IDs
     */
    public function testHashIdUniqueness(): void
    {
        $entryIds = range(1, 100);
        $hashes = [];
        
        foreach ($entryIds as $id) {
            $hash = $this->hashIdService->encode($id);
            
            $this->assertNotContains(
                $hash,
                $hashes,
                "Hash for entry {$id} should be unique"
            );
            
            $hashes[] = $hash;
        }
        
        $this->assertCount(100, array_unique($hashes), 'All hashes should be unique');
    }
    
    /**
     * Test real-world scenario: User bookmarks a permalink, then entries are deleted
     */
    public function testPermalinkWorksAfterDeletions(): void
    {
        // User creates 5 entries
        $entryIds = [1, 2, 3, 4, 5];
        
        // User bookmarks entry 3
        $bookmarkedId = 3;
        $bookmarkedHash = $this->hashIdService->encode($bookmarkedId);
        
        // Later, entries 1 and 2 are deleted (entries before the bookmarked one)
        // And entry 5 is deleted (entry after the bookmarked one)
        // Database now has entries: [3, 4]
        
        // User clicks on bookmarked permalink
        $retrievedId = $this->hashIdService->decode($bookmarkedHash);
        
        // The permalink should still resolve to the correct entry ID
        $this->assertEquals(
            $bookmarkedId,
            $retrievedId,
            'Bookmarked permalink should still work after other entries are deleted'
        );
        
        // Verify the hash is still the same
        $currentHash = $this->hashIdService->encode($bookmarkedId);
        $this->assertEquals(
            $bookmarkedHash,
            $currentHash,
            'Hash should remain unchanged'
        );
    }
    
    /**
     * Test that hash format is consistent
     */
    public function testHashFormat(): void
    {
        $id = 42;
        $hash = $this->hashIdService->encode($id);
        
        // Hash should be alphanumeric
        $this->assertMatchesRegularExpression(
            '/^[0-9a-zA-Z]+$/',
            $hash,
            'Hash should only contain alphanumeric characters'
        );
        
        // Hash should have minimum length for security
        $this->assertGreaterThanOrEqual(
            8,
            strlen($hash),
            'Hash should be at least 8 characters for security'
        );
    }
    
    /**
     * Test edge case: very large entry IDs
     */
    public function testHashIdWithLargeIds(): void
    {
        $largeIds = [1000, 10000, 100000, 1000000];
        
        foreach ($largeIds as $id) {
            $hash = $this->hashIdService->encode($id);
            $decodedId = $this->hashIdService->decode($hash);
            
            $this->assertEquals(
                $id,
                $decodedId,
                "Large ID {$id} should encode and decode correctly"
            );
        }
    }
}
