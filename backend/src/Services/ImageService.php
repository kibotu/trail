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
     * Validate uploaded image file with magic byte verification
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
        // Note: finfo_close() is deprecated in PHP 8.5+, objects are freed automatically
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Invalid image type: ' . $mimeType);
        }
        
        // Verify magic bytes match the detected MIME type (security check)
        $this->verifyImageMagicBytes($filePath, $mimeType);
        
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
     * Verify image magic bytes to prevent file type spoofing
     */
    private function verifyImageMagicBytes(string $filePath, string $mimeType): void
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Cannot read file');
        }
        
        $header = fread($handle, 12);
        fclose($handle);
        
        if ($header === false) {
            throw new InvalidArgumentException('Cannot read file header');
        }
        
        // Define magic byte signatures for each image type
        $magicBytes = [
            'image/jpeg' => [
                ['offset' => 0, 'bytes' => "\xFF\xD8\xFF"], // JPEG
            ],
            'image/png' => [
                ['offset' => 0, 'bytes' => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"], // PNG
            ],
            'image/gif' => [
                ['offset' => 0, 'bytes' => "GIF87a"], // GIF87a
                ['offset' => 0, 'bytes' => "GIF89a"], // GIF89a
            ],
            'image/webp' => [
                ['offset' => 0, 'bytes' => "RIFF"], // RIFF container
                ['offset' => 8, 'bytes' => "WEBP"], // WEBP signature
            ],
            'image/svg+xml' => [
                ['offset' => 0, 'bytes' => "<?xml"], // XML declaration
                ['offset' => 0, 'bytes' => "<svg"], // SVG tag
            ],
            'image/avif' => [
                ['offset' => 4, 'bytes' => "ftyp"], // ISO Base Media File Format
            ],
        ];
        
        if (!isset($magicBytes[$mimeType])) {
            throw new InvalidArgumentException('Unsupported MIME type for validation');
        }
        
        $valid = false;
        foreach ($magicBytes[$mimeType] as $signature) {
            $offset = $signature['offset'];
            $bytes = $signature['bytes'];
            $length = strlen($bytes);
            
            if (strlen($header) >= $offset + $length) {
                $chunk = substr($header, $offset, $length);
                if ($chunk === $bytes) {
                    $valid = true;
                    break;
                }
            }
        }
        
        if (!$valid) {
            throw new InvalidArgumentException('File magic bytes do not match declared type. Possible file type spoofing.');
        }
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
        // Note: finfo_close() is deprecated in PHP 8.5+, objects are freed automatically
        
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
        // Validate userId is positive integer
        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID');
        }
        
        $userPath = $this->uploadBasePath . '/' . $userId;
        
        // Security: Verify the resolved path is within uploadBasePath
        $realUploadBase = realpath($this->uploadBasePath);
        if ($realUploadBase === false) {
            throw new RuntimeException('Upload base path does not exist');
        }
        
        if (!is_dir($userPath)) {
            mkdir($userPath, 0755, true);
        }
        
        $realUserPath = realpath($userPath);
        if ($realUserPath === false || strpos($realUserPath, $realUploadBase) !== 0) {
            throw new RuntimeException('Path traversal attempt detected');
        }
        
        return $userPath;
    }
    
    /**
     * Get full path for specific image file with security validation
     */
    public function getImagePath(int $userId, string $filename): string
    {
        // Additional filename validation
        if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            throw new InvalidArgumentException('Invalid filename');
        }
        
        $userPath = $this->getUserImagePath($userId);
        $filePath = $userPath . '/' . $filename;
        
        // Security: Verify the resolved path is within user directory
        $realUserPath = realpath($userPath);
        if ($realUserPath === false) {
            throw new RuntimeException('User path does not exist');
        }
        
        // Check if file exists and validate its real path
        if (file_exists($filePath)) {
            $realFilePath = realpath($filePath);
            if ($realFilePath === false || strpos($realFilePath, $realUserPath) !== 0) {
                throw new RuntimeException('Path traversal attempt detected');
            }
        }
        
        return $filePath;
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
     * Sanitize original filename with enhanced security
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove any null bytes (security)
        $filename = str_replace("\0", '', $filename);
        
        // Remove path traversal attempts
        $filename = basename($filename);
        $filename = str_replace(['../', '..\\', '../', '..'], '', $filename);
        
        // Remove special characters except dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Prevent multiple dots (could hide extensions)
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        
        // Prevent leading dots (hidden files)
        $filename = ltrim($filename, '.');
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'unnamed_file';
        }
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * Secure uploaded file by removing execute permissions
     */
    public function secureUploadedFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }
        
        // Remove execute permissions (0644 = rw-r--r--)
        // Owner: read+write, Group: read, Others: read
        if (!chmod($filePath, 0644)) {
            error_log("Warning: Failed to set secure permissions on: {$filePath}");
        }
        
        // Additional security: Verify file is not a symlink
        if (is_link($filePath)) {
            unlink($filePath);
            throw new RuntimeException('Symlinks are not allowed');
        }
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
