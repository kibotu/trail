<?php

declare(strict_types=1);

namespace Trail\Cron;

use Trail\Config\Config;
use Trail\Services\ImageService;
use Trail\Database\Database;

/**
 * Cleanup script for orphaned images that are not referenced by any entries, comments, or users
 * Run this via cron: php backend/src/Cron/CleanupOrphanImages.php
 * 
 * Performs bidirectional cleanup:
 * 1. Orphaned files: Deletes images not referenced in entries, comments, or user profiles
 * 2. Missing files: Removes database records where the file no longer exists
 * 
 * @param int $olderThanDays Only delete orphaned files older than this many days (0 = all)
 * @return array Results with deleted_files, deleted_db_records, errors counts
 */
class CleanupOrphanImages
{
    public static function run(int $olderThanDays = 7): array
    {
        $results = [
            'deleted_files' => 0,
            'deleted_db_records' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            
            $uploadBasePath = __DIR__ . '/../../public/uploads/images';
            $tempBasePath = __DIR__ . '/../../storage/temp';
            $imageService = new ImageService($uploadBasePath, $tempBasePath);
            
            // === PHASE 1: Clean up orphaned files (Database → Filesystem) ===
            self::logMessage("Starting orphaned files cleanup...");
            
            // Find orphaned images older than specified days
            $whereClause = $olderThanDays > 0 
                ? "WHERE i.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)" 
                : "WHERE 1=1";
            
            $stmt = $db->prepare("
                SELECT i.id, i.user_id, i.filename, i.created_at
                FROM trail_images i
                $whereClause
                AND i.id NOT IN (
                    SELECT profile_image_id FROM trail_users WHERE profile_image_id IS NOT NULL
                    UNION
                    SELECT header_image_id FROM trail_users WHERE header_image_id IS NOT NULL
                )
                AND i.image_type = 'post'
            ");
            
            if ($olderThanDays > 0) {
                $stmt->execute([$olderThanDays]);
            } else {
                $stmt->execute();
            }
            
            $potentialOrphans = $stmt->fetchAll();
            
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
                    continue; // Referenced in entry
                }
                
                // Check if image is referenced in any comment's image_ids JSON array
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM trail_comments 
                    WHERE image_ids IS NOT NULL 
                    AND JSON_CONTAINS(image_ids, ?)
                ");
                $stmt->execute([json_encode($imageId)]);
                $result = $stmt->fetch();
                
                if ((int) $result['count'] > 0) {
                    continue; // Referenced in comment
                }
                
                // Image is truly orphaned, delete it
                try {
                    // Delete file from filesystem
                    $imageService->deleteImage((int) $image['user_id'], $image['filename']);
                    
                    // Delete from database
                    $stmt = $db->prepare("DELETE FROM trail_images WHERE id = ?");
                    $stmt->execute([$imageId]);
                    
                    $results['deleted_files']++;
                    $results['details'][] = "Deleted orphaned file: {$image['filename']} (ID: {$imageId})";
                    self::logMessage("Deleted orphaned image: {$image['filename']} (ID: {$imageId})");
                    
                } catch (\Throwable $e) {
                    $results['errors']++;
                    $results['details'][] = "Error deleting image {$imageId}: " . $e->getMessage();
                    self::logMessage("Error deleting image {$imageId}: " . $e->getMessage());
                    error_log("CleanupOrphanImages error for image {$imageId}: " . $e->getMessage());
                }
            }
            
            // === PHASE 2: Clean up database records for missing files (Filesystem → Database) ===
            self::logMessage("Starting missing files cleanup...");
            
            $stmt = $db->query("SELECT id, user_id, filename FROM trail_images");
            $allImages = $stmt->fetchAll();
            
            foreach ($allImages as $image) {
                $imageId = (int) $image['id'];
                $userId = (int) $image['user_id'];
                $filename = $image['filename'];
                
                try {
                    $filePath = $imageService->getImagePath($userId, $filename);
                    
                    if (!file_exists($filePath)) {
                        // File doesn't exist, remove database record
                        $stmt = $db->prepare("DELETE FROM trail_images WHERE id = ?");
                        $stmt->execute([$imageId]);
                        
                        $results['deleted_db_records']++;
                        $results['details'][] = "Removed DB record for missing file: {$filename} (ID: {$imageId})";
                        self::logMessage("Removed DB record for missing file: {$filename} (ID: {$imageId})");
                    }
                } catch (\Throwable $e) {
                    $results['errors']++;
                    $results['details'][] = "Error checking file {$imageId}: " . $e->getMessage();
                    self::logMessage("Error checking file {$imageId}: " . $e->getMessage());
                    error_log("CleanupOrphanImages error checking file {$imageId}: " . $e->getMessage());
                }
            }
            
            self::logMessage("Cleanup complete: {$results['deleted_files']} files deleted, {$results['deleted_db_records']} DB records removed, {$results['errors']} errors");
            error_log("CleanupOrphanImages: {$results['deleted_files']} files deleted, {$results['deleted_db_records']} DB records removed, {$results['errors']} errors");
            
        } catch (\Throwable $e) {
            $results['errors']++;
            $results['details'][] = "Fatal error: " . $e->getMessage();
            self::logMessage("Fatal error: " . $e->getMessage());
            error_log("CleanupOrphanImages fatal error: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Log message to stdout if running in CLI mode
     */
    private static function logMessage(string $message): void
    {
        if (php_sapi_name() === 'cli') {
            echo date('Y-m-d H:i:s') . " - " . $message . "\n";
        }
    }
}

// Run if executed directly from CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Default: cleanup images older than 7 days
    $days = isset($argv[1]) ? (int) $argv[1] : 7;
    $results = CleanupOrphanImages::run($days);
    
    // Display summary
    echo "\n=== Cleanup Summary ===\n";
    echo "Orphaned files deleted: {$results['deleted_files']}\n";
    echo "DB records removed: {$results['deleted_db_records']}\n";
    echo "Errors: {$results['errors']}\n";
    
    exit($results['errors'] > 0 ? 1 : 0);
}
