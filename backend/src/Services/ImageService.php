<?php

declare(strict_types=1);

namespace Trail\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Image processing service with GD library
 * Handles validation, optimization, WebP conversion, and secure filename generation
 */
class ImageService
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB
    private const WEBP_QUALITY = 90;
    
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/avif'
    ];
    
    private const IMAGE_DIMENSIONS = [
        'profile' => ['width' => 512, 'height' => 512],
        'header' => ['width' => 1920, 'height' => 400],
        'post' => ['width' => 1200, 'height' => 1200]
    ];
    
    private string $uploadBasePath;
    private string $tempBasePath;
    
    public function __construct(string $uploadBasePath, string $tempBasePath)
    {
        $this->uploadBasePath = rtrim($uploadBasePath, '/');
        $this->tempBasePath = rtrim($tempBasePath, '/');
        
        // Ensure directories exist
        if (!is_dir($this->uploadBasePath)) {
            mkdir($this->uploadBasePath, 0755, true);
        }
        if (!is_dir($this->tempBasePath)) {
            mkdir($this->tempBasePath, 0755, true);
        }
    }
    
    /**
     * Validate uploaded image file
     */
    public function validateImage(string $filePath, int $maxSize = self::MAX_FILE_SIZE): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }
        
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize > $maxSize) {
            throw new InvalidArgumentException('File size exceeds maximum allowed size');
        }
        
        // Check magic bytes for real MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Invalid image type: ' . $mimeType);
        }
        
        // Get image dimensions (except for SVG)
        $width = null;
        $height = null;
        if ($mimeType !== 'image/svg+xml') {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                throw new InvalidArgumentException('Invalid image file');
            }
            [$width, $height] = $imageInfo;
        }
        
        return [
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height
        ];
    }
    
    /**
     * Optimize and convert image to WebP
     */
    public function optimizeAndConvert(
        string $sourcePath,
        string $targetPath,
        string $imageType
    ): array {
        if (!isset(self::IMAGE_DIMENSIONS[$imageType])) {
            throw new InvalidArgumentException('Invalid image type: ' . $imageType);
        }
        
        $maxWidth = self::IMAGE_DIMENSIONS[$imageType]['width'];
        $maxHeight = self::IMAGE_DIMENSIONS[$imageType]['height'];
        
        // Get source image info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $sourcePath);
        finfo_close($finfo);
        
        // Handle SVG separately (no conversion needed)
        if ($mimeType === 'image/svg+xml') {
            copy($sourcePath, $targetPath);
            return [
                'width' => null,
                'height' => null,
                'file_size' => filesize($targetPath)
            ];
        }
        
        // Load source image based on type
        $sourceImage = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => throw new RuntimeException('Unsupported image type for conversion')
        };
        
        if ($sourceImage === false) {
            throw new RuntimeException('Failed to load source image');
        }
        
        // Get original dimensions
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newWidth = (int) round($origWidth * $ratio);
        $newHeight = (int) round($origHeight * $ratio);
        
        // Create new image with calculated dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $newImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $origWidth,
            $origHeight
        );
        
        // Save as WebP
        $success = imagewebp($newImage, $targetPath, self::WEBP_QUALITY);
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        if (!$success) {
            throw new RuntimeException('Failed to save WebP image');
        }
        
        return [
            'width' => $newWidth,
            'height' => $newHeight,
            'file_size' => filesize($targetPath)
        ];
    }
    
    /**
     * Generate secure filename: {userId}_{timestamp}_{random}.webp
     */
    public function generateSecureFilename(int $userId, string $originalFilename): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        
        // Use .webp for most images, preserve .svg for SVG files
        $ext = strtolower($extension) === 'svg' ? 'svg' : 'webp';
        
        return sprintf('%d_%d_%s.%s', $userId, $timestamp, $random, $ext);
    }
    
    /**
     * Get full path for user's image directory
     */
    public function getUserImagePath(int $userId): string
    {
        $userPath = $this->uploadBasePath . '/' . $userId;
        if (!is_dir($userPath)) {
            mkdir($userPath, 0755, true);
        }
        return $userPath;
    }
    
    /**
     * Get full path for specific image file
     */
    public function getImagePath(int $userId, string $filename): string
    {
        return $this->getUserImagePath($userId) . '/' . $filename;
    }
    
    /**
     * Get temp directory for upload session
     */
    public function getTempPath(string $uploadId): string
    {
        $tempPath = $this->tempBasePath . '/' . $uploadId;
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }
        return $tempPath;
    }
    
    /**
     * Generate ETag from file contents
     */
    public function generateETag(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }
        return md5_file($filePath);
    }
    
    /**
     * Sanitize original filename
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove special characters except dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * Delete image file
     */
    public function deleteImage(int $userId, string $filename): bool
    {
        $filePath = $this->getImagePath($userId, $filename);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    /**
     * Clean up temp directory
     */
    public function cleanupTempPath(string $uploadId): bool
    {
        $tempPath = $this->tempBasePath . '/' . $uploadId;
        if (!is_dir($tempPath)) {
            return true;
        }
        
        // Remove all files in directory
        $files = glob($tempPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Remove directory
        return rmdir($tempPath);
    }
    
    /**
     * Clean up old temp directories (older than specified seconds)
     */
    public function cleanupOldTempFiles(int $olderThan = 3600): int
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
                $uploadId = basename($dir);
                if ($this->cleanupTempPath($uploadId)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
}
