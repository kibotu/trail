<?php

declare(strict_types=1);

namespace Trail\Services;

use PDO;

/**
 * Storage metrics service for calculating image and cache sizes
 */
class StorageService
{
    private PDO $db;
    private string $uploadBasePath;
    private string $tempBasePath;
    
    public function __construct(PDO $db, string $uploadBasePath, string $tempBasePath)
    {
        $this->db = $db;
        $this->uploadBasePath = rtrim($uploadBasePath, '/');
        $this->tempBasePath = rtrim($tempBasePath, '/');
    }
    
    /**
     * Get total image storage size from database
     */
    public function getTotalImageSize(): int
    {
        try {
            $stmt = $this->db->query("SELECT COALESCE(SUM(file_size), 0) as total_size FROM trail_images");
            $result = $stmt->fetch();
            return (int) $result['total_size'];
        } catch (\PDOException $e) {
            error_log("getTotalImageSize error (table may not exist): " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get total number of images
     */
    public function getTotalImageCount(): int
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM trail_images");
            $result = $stmt->fetch();
            return (int) $result['count'];
        } catch (\PDOException $e) {
            error_log("getTotalImageCount error (table may not exist): " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get user's total image storage size
     */
    public function getUserImageSize(int $userId): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(file_size), 0) as total_size 
                FROM trail_images 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return (int) $result['total_size'];
        } catch (\PDOException $e) {
            error_log("getUserImageSize error (table may not exist): " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get user's image statistics by type
     */
    public function getUserImageStats(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as image_count,
                    COALESCE(SUM(file_size), 0) as total_size,
                    SUM(CASE WHEN image_type = 'profile' THEN 1 ELSE 0 END) as profile_count,
                    SUM(CASE WHEN image_type = 'header' THEN 1 ELSE 0 END) as header_count,
                    SUM(CASE WHEN image_type = 'post' THEN 1 ELSE 0 END) as post_count
                FROM trail_images
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            return [
                'image_count' => (int) $result['image_count'],
                'total_size' => (int) $result['total_size'],
                'profile_count' => (int) $result['profile_count'],
                'header_count' => (int) $result['header_count'],
                'post_count' => (int) $result['post_count']
            ];
        } catch (\PDOException $e) {
            error_log("getUserImageStats error (table may not exist): " . $e->getMessage());
            return [
                'image_count' => 0,
                'total_size' => 0,
                'profile_count' => 0,
                'header_count' => 0,
                'post_count' => 0
            ];
        }
    }
    
    /**
     * Get all users' image statistics
     */
    public function getAllUsersImageStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    user_id,
                    COUNT(*) as image_count,
                    COALESCE(SUM(file_size), 0) as total_size,
                    SUM(CASE WHEN image_type = 'profile' THEN 1 ELSE 0 END) as profile_count,
                    SUM(CASE WHEN image_type = 'header' THEN 1 ELSE 0 END) as header_count,
                    SUM(CASE WHEN image_type = 'post' THEN 1 ELSE 0 END) as post_count
                FROM trail_images
                GROUP BY user_id
            ");
            
            $stats = [];
            while ($row = $stmt->fetch()) {
                $stats[(int) $row['user_id']] = [
                    'image_count' => (int) $row['image_count'],
                    'total_size' => (int) $row['total_size'],
                    'profile_count' => (int) $row['profile_count'],
                    'header_count' => (int) $row['header_count'],
                    'post_count' => (int) $row['post_count']
                ];
            }
            
            return $stats;
        } catch (\PDOException $e) {
            error_log("getAllUsersImageStats error (table may not exist): " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate directory size recursively
     */
    private function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Get total uploaded images size on disk (all users)
     */
    public function getTotalUploadedImagesSize(): int
    {
        return $this->getDirectorySize($this->uploadBasePath);
    }
    
    /**
     * Get temp directory size (for cleanup monitoring)
     */
    public function getTempDirectorySize(): int
    {
        return $this->getDirectorySize($this->tempBasePath);
    }
    
    /**
     * Get user's actual directory size on filesystem
     */
    public function getUserDirectorySize(int $userId): int
    {
        $userPath = $this->uploadBasePath . '/' . $userId;
        return $this->getDirectorySize($userPath);
    }
    
    /**
     * Clear temp files older than specified seconds
     */
    public function clearTempFiles(int $olderThan = 3600): int
    {
        $cleaned = 0;
        $now = time();
        
        if (!is_dir($this->tempBasePath)) {
            return 0;
        }
        
        $dirs = glob($this->tempBasePath . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $mtime = filemtime($dir);
            if ($mtime !== false && ($now - $mtime) > $olderThan) {
                if ($this->removeDirectory($dir)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Remove directory and all its contents
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Format bytes to human-readable format
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = 1024;
        $exp = (int) floor(log($bytes) / log($base));
        $exp = min($exp, count($units) - 1);
        
        $value = $bytes / pow($base, $exp);
        
        return sprintf('%.' . $precision . 'f %s', $value, $units[$exp]);
    }
    
    /**
     * Get storage statistics summary
     */
    public function getStorageSummary(): array
    {
        $totalImageSizeDb = $this->getTotalImageSize();
        $totalImageSizeDisk = $this->getTotalUploadedImagesSize();
        
        return [
            'total_images' => $this->getTotalImageCount(),
            'total_image_size' => $totalImageSizeDb,
            'total_image_size_formatted' => self::formatBytes($totalImageSizeDb),
            'total_disk_size' => $totalImageSizeDisk,
            'total_disk_size_formatted' => self::formatBytes($totalImageSizeDisk),
            'temp_size' => $this->getTempDirectorySize(),
            'temp_size_formatted' => self::formatBytes($this->getTempDirectorySize())
        ];
    }
}
