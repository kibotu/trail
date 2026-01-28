<?php

declare(strict_types=1);

namespace Trail\Cron;

use Trail\Config\Config;
use Trail\Services\ImageService;
use Trail\Services\StorageService;
use Trail\Database\Database;

/**
 * Cleanup script for temporary upload files
 * Run this via cron: php backend/src/Cron/CleanupTempFiles.php
 */
class CleanupTempFiles
{
    public static function run(): void
    {
        try {
            $config = Config::load(__DIR__ . '/../../secrets.yml');
            $db = Database::getInstance($config);
            
            $uploadBasePath = __DIR__ . '/../../public/uploads/images';
            $tempBasePath = __DIR__ . '/../../storage/temp';
            
            // Cleanup temp files older than 1 hour
            $imageService = new ImageService($uploadBasePath, $tempBasePath);
            $cleaned = $imageService->cleanupOldTempFiles(3600);
            
            echo date('Y-m-d H:i:s') . " - Cleaned up {$cleaned} temporary upload directories\n";
            
            // Optional: Log to database or file
            error_log("CleanupTempFiles: Cleaned {$cleaned} directories");
            
        } catch (\Throwable $e) {
            echo date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
            error_log("CleanupTempFiles error: " . $e->getMessage());
        }
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    CleanupTempFiles::run();
}
