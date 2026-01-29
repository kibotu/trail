<?php

declare(strict_types=1);

namespace Trail\Cron;

use Trail\Config\Config;
use Trail\Services\ImageService;
use Trail\Database\Database;

/**
 * Cleanup script for orphaned images that are not referenced by any entries or users
 * Run this via cron: php backend/src/Cron/CleanupOrphanImages.php
 * 
 * IMPORTANT: This only deletes images that are:
 * 1. Not referenced in any entry's image_ids
 * 2. Not used as profile_image_id or header_image_id
 * 3. Older than specified age (default 7 days)
 */
class CleanupOrphanImages
{
    public static function run(int $olderThanDays = 7): void
    {
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            
            $uploadBasePath = __DIR__ . '/../../public/uploads/images';
            $tempBasePath = __DIR__ . '/../../storage/temp';
            $imageService = new ImageService($uploadBasePath, $tempBasePath);
            
            // Find orphaned images older than specified days
            $stmt = $db->prepare("
                SELECT i.id, i.user_id, i.filename, i.created_at
                FROM trail_images i
                WHERE i.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND i.id NOT IN (
                    SELECT profile_image_id FROM trail_users WHERE profile_image_id IS NOT NULL
                    UNION
                    SELECT header_image_id FROM trail_users WHERE header_image_id IS NOT NULL
                )
                AND i.image_type = 'post'
            ");
            $stmt->execute([$olderThanDays]);
            $potentialOrphans = $stmt->fetchAll();
            
            $deletedCount = 0;
            $errorCount = 0;
            
            foreach ($potentialOrphans as $image) {
                $imageId = (int) $image['id'];
                
                // Check if image is referenced in any entry's image_ids JSON array
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM trail_entries 
                    WHERE image_ids IS NOT NULL 
                    AND JSON_CONTAINS(image_ids, ?)
                ");
                $stmt->execute([json_encode($imageId)]);
                $result = $stmt->fetch();
                
                if ((int) $result['count'] > 0) {
                    // Image is referenced, skip it
                    continue;
                }
                
                // Image is truly orphaned, delete it
                try {
                    // Delete file from filesystem
                    $imageService->deleteImage((int) $image['user_id'], $image['filename']);
                    
                    // Delete from database
                    $stmt = $db->prepare("DELETE FROM trail_images WHERE id = ?");
                    $stmt->execute([$imageId]);
                    
                    $deletedCount++;
                    echo date('Y-m-d H:i:s') . " - Deleted orphaned image: {$image['filename']} (ID: {$imageId})\n";
                    
                } catch (\Throwable $e) {
                    $errorCount++;
                    echo date('Y-m-d H:i:s') . " - Error deleting image {$imageId}: " . $e->getMessage() . "\n";
                    error_log("CleanupOrphanImages error for image {$imageId}: " . $e->getMessage());
                }
            }
            
            echo date('Y-m-d H:i:s') . " - Cleanup complete: {$deletedCount} orphaned images deleted, {$errorCount} errors\n";
            error_log("CleanupOrphanImages: Deleted {$deletedCount} images, {$errorCount} errors");
            
        } catch (\Throwable $e) {
            echo date('Y-m-d H:i:s') . " - Fatal error: " . $e->getMessage() . "\n";
            error_log("CleanupOrphanImages fatal error: " . $e->getMessage());
        }
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Default: cleanup images older than 7 days
    $days = isset($argv[1]) ? (int) $argv[1] : 7;
    CleanupOrphanImages::run($days);
}
