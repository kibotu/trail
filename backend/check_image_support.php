<?php
/**
 * Diagnostic script to check if image upload support is properly configured
 */

require_once __DIR__ . '/vendor/autoload.php';

use Trail\Config\Config;
use Trail\Database\Database;

try {
    $config = Config::load(__DIR__ . '/secrets.yml');
    $db = Database::getInstance($config);
    
    echo "=== Image Upload Support Diagnostic ===\n\n";
    
    // Check if trail_images table exists
    echo "1. Checking if trail_images table exists...\n";
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'trail_images'");
        $result = $stmt->fetch();
        if ($result) {
            echo "   ✅ trail_images table EXISTS\n";
            
            // Check table structure
            $stmt = $db->query("DESCRIBE trail_images");
            $columns = $stmt->fetchAll();
            echo "   Columns: " . implode(', ', array_column($columns, 'Field')) . "\n";
        } else {
            echo "   ❌ trail_images table DOES NOT EXIST\n";
            echo "   → Run migration: migrations/009_add_images_table.sql\n";
        }
    } catch (\PDOException $e) {
        echo "   ❌ Error checking table: " . $e->getMessage() . "\n";
    }
    
    echo "\n2. Checking if trail_entries.image_ids column exists...\n";
    try {
        $stmt = $db->query("SHOW COLUMNS FROM trail_entries LIKE 'image_ids'");
        $result = $stmt->fetch();
        if ($result) {
            echo "   ✅ image_ids column EXISTS in trail_entries\n";
            echo "   Type: " . $result['Type'] . "\n";
        } else {
            echo "   ❌ image_ids column DOES NOT EXIST in trail_entries\n";
            echo "   → Run migration: migrations/009_add_images_table.sql\n";
        }
    } catch (\PDOException $e) {
        echo "   ❌ Error checking column: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. Checking if trail_users has image columns...\n";
    try {
        $stmt = $db->query("SHOW COLUMNS FROM trail_users WHERE Field IN ('profile_image_id', 'header_image_id')");
        $results = $stmt->fetchAll();
        if (count($results) === 2) {
            echo "   ✅ profile_image_id and header_image_id columns EXIST\n";
        } else {
            echo "   ❌ Missing image columns in trail_users\n";
            echo "   → Run migration: migrations/009_add_images_table.sql\n";
        }
    } catch (\PDOException $e) {
        echo "   ❌ Error checking columns: " . $e->getMessage() . "\n";
    }
    
    echo "\n4. Checking for uploaded images...\n";
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM trail_images");
        $result = $stmt->fetch();
        echo "   Total images in database: " . $result['count'] . "\n";
        
        if ($result['count'] > 0) {
            $stmt = $db->query("SELECT id, user_id, filename, image_type, file_size FROM trail_images LIMIT 5");
            $images = $stmt->fetchAll();
            echo "   Sample images:\n";
            foreach ($images as $img) {
                echo "     - ID: {$img['id']}, User: {$img['user_id']}, Type: {$img['image_type']}, File: {$img['filename']}, Size: {$img['file_size']} bytes\n";
            }
        }
    } catch (\PDOException $e) {
        echo "   ❌ Error querying images: " . $e->getMessage() . "\n";
    }
    
    echo "\n5. Checking entries with images...\n";
    try {
        $stmt = $db->query("SELECT id, user_id, text, image_ids FROM trail_entries WHERE image_ids IS NOT NULL AND image_ids != '' LIMIT 5");
        $entries = $stmt->fetchAll();
        if (count($entries) > 0) {
            echo "   ✅ Found " . count($entries) . " entries with images\n";
            foreach ($entries as $entry) {
                $imageIds = json_decode($entry['image_ids'], true);
                echo "     - Entry ID: {$entry['id']}, User: {$entry['user_id']}, Image IDs: " . json_encode($imageIds) . "\n";
                echo "       Text: " . substr($entry['text'], 0, 50) . (strlen($entry['text']) > 50 ? '...' : '') . "\n";
            }
        } else {
            echo "   ℹ️  No entries with images found\n";
        }
    } catch (\PDOException $e) {
        echo "   ❌ Error querying entries: " . $e->getMessage() . "\n";
    }
    
    echo "\n6. Checking upload directories...\n";
    $uploadDir = __DIR__ . '/public/uploads/images';
    $tempDir = __DIR__ . '/storage/temp';
    
    if (is_dir($uploadDir)) {
        echo "   ✅ Upload directory exists: $uploadDir\n";
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $count = 0;
        foreach ($files as $file) {
            if ($file->isFile()) $count++;
        }
        echo "   Files in upload directory: $count\n";
    } else {
        echo "   ❌ Upload directory does not exist: $uploadDir\n";
    }
    
    if (is_dir($tempDir)) {
        echo "   ✅ Temp directory exists: $tempDir\n";
    } else {
        echo "   ℹ️  Temp directory does not exist: $tempDir (will be created on first upload)\n";
    }
    
    echo "\n=== Diagnostic Complete ===\n";
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
