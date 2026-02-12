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
    private const MAX_VIDEO_SIZE = 50 * 1024 * 1024; // 50MB for videos
    private const WEBP_QUALITY = 90;
    
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/avif',
        'video/mp4',
        'video/quicktime', // MOV files
        'video/webm'
    ];
    
    private const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/webm'
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
     * Check if a MIME type is a video type
     */
    public function isVideoMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::VIDEO_MIME_TYPES, true);
    }
    
    /**
     * Validate uploaded media file (image or video) with magic byte verification
     */
    public function validateImage(string $filePath, int $maxSize = self::MAX_FILE_SIZE): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }
        
        // Check magic bytes for real MIME type first
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        // Note: finfo_close() is deprecated in PHP 8.5+, objects are freed automatically
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Invalid media type: ' . $mimeType);
        }
        
        // Use higher size limit for videos
        $effectiveMaxSize = $this->isVideoMimeType($mimeType) ? self::MAX_VIDEO_SIZE : $maxSize;
        
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize > $effectiveMaxSize) {
            $limitMB = $effectiveMaxSize / (1024 * 1024);
            throw new InvalidArgumentException("File size exceeds maximum allowed size ({$limitMB}MB)");
        }
        
        // Verify magic bytes match the detected MIME type (security check)
        $this->verifyImageMagicBytes($filePath, $mimeType);
        
        // Get dimensions based on media type
        $width = null;
        $height = null;
        
        if ($this->isVideoMimeType($mimeType)) {
            // For videos, try to get dimensions using ffprobe if available
            $dimensions = $this->getVideoDimensions($filePath);
            $width = $dimensions['width'];
            $height = $dimensions['height'];
        } elseif ($mimeType !== 'image/svg+xml') {
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
     * Get video dimensions using ffprobe if available
     * 
     * @param string $filePath Path to video file
     * @return array{width: int|null, height: int|null}
     */
    private function getVideoDimensions(string $filePath): array
    {
        $width = null;
        $height = null;
        
        // Try ffprobe first (most reliable)
        $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
        if ($ffprobe) {
            $cmd = sprintf(
                'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 %s 2>/dev/null',
                escapeshellarg($filePath)
            );
            $output = trim(shell_exec($cmd) ?? '');
            if (preg_match('/^(\d+)x(\d+)$/', $output, $matches)) {
                $width = (int) $matches[1];
                $height = (int) $matches[2];
            }
        }
        
        return ['width' => $width, 'height' => $height];
    }
    
    /**
     * Check if a GIF file is animated (has multiple frames)
     * 
     * @param string $filePath Path to the GIF file
     * @return bool True if the GIF is animated
     */
    public function isAnimatedGif(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }
        
        $frameCount = 0;
        
        // Read the entire file to check for multiple graphic control extension blocks
        // which indicate animation frames
        while (!feof($handle) && $frameCount < 2) {
            $chunk = fread($handle, 1024);
            if ($chunk === false) {
                break;
            }
            
            // Look for Graphic Control Extension (0x21 0xF9) which precedes each frame
            // Each animated frame has this extension block
            $frameCount += substr_count($chunk, "\x00\x21\xF9\x04");
            
            // Also check for NETSCAPE extension (loop indicator) - strong sign of animation
            if (strpos($chunk, "NETSCAPE") !== false) {
                fclose($handle);
                return true;
            }
        }
        
        fclose($handle);
        
        // If we found 2 or more graphic control extension blocks, it's animated
        return $frameCount >= 2;
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
            'video/mp4' => [
                ['offset' => 4, 'bytes' => "ftyp"], // ISO Base Media File Format (MP4)
            ],
            'video/quicktime' => [
                ['offset' => 4, 'bytes' => "ftyp"], // ISO Base Media File Format (MOV)
                ['offset' => 4, 'bytes' => "moov"], // QuickTime movie atom
                ['offset' => 4, 'bytes' => "mdat"], // Media data atom
                ['offset' => 4, 'bytes' => "wide"], // Wide atom
                ['offset' => 4, 'bytes' => "free"], // Free space atom
            ],
            'video/webm' => [
                ['offset' => 0, 'bytes' => "\x1A\x45\xDF\xA3"], // EBML header (WebM/Matroska)
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
     * Preserve an animated GIF by copying it without any processing.
     * This maintains the animation frames and timing.
     * 
     * @param string $sourcePath Source GIF file path
     * @param string $targetPath Target file path (should have .gif extension)
     * @param string $imageType Image type for dimension validation ('profile', 'header', 'post')
     * @return array Image metadata (width, height, file_size, mime_type)
     */
    public function preserveAnimatedGif(string $sourcePath, string $targetPath, string $imageType): array
    {
        if (!file_exists($sourcePath)) {
            throw new InvalidArgumentException('Source file does not exist');
        }
        
        $fileSize = filesize($sourcePath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('File size exceeds maximum allowed size');
        }
        
        // Get dimensions
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('Invalid GIF file');
        }
        
        [$width, $height] = $imageInfo;
        
        // Check if dimensions exceed limits for the image type
        if (isset(self::IMAGE_DIMENSIONS[$imageType])) {
            $maxWidth = self::IMAGE_DIMENSIONS[$imageType]['width'];
            $maxHeight = self::IMAGE_DIMENSIONS[$imageType]['height'];
            
            // For animated GIFs, we accept larger dimensions but log a warning
            // Resizing animated GIFs would require frame-by-frame processing
            if ($width > $maxWidth * 2 || $height > $maxHeight * 2) {
                error_log("Warning: Animated GIF dimensions ({$width}x{$height}) exceed recommended limits for {$imageType}");
            }
        }
        
        // Copy file as-is to preserve animation
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Failed to copy animated GIF file');
        }
        
        return [
            'width' => $width,
            'height' => $height,
            'file_size' => $fileSize,
            'mime_type' => 'image/gif'
        ];
    }
    
    /**
     * Check if ffmpeg is available on the system
     */
    public function isFfmpegAvailable(): bool
    {
        $ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        return !empty($ffmpeg);
    }
    
    /**
     * Process video file - convert MOV to MP4 or copy MP4 as-is
     * 
     * @param string $sourcePath Source video file path
     * @param string $targetPath Target file path (should have .mp4 extension)
     * @param string $mimeType Original MIME type
     * @return array Video metadata (width, height, file_size, mime_type)
     */
    public function processVideo(string $sourcePath, string $targetPath, string $mimeType): array
    {
        if (!file_exists($sourcePath)) {
            throw new InvalidArgumentException('Source file does not exist');
        }
        
        $fileSize = filesize($sourcePath);
        if ($fileSize === false || $fileSize > self::MAX_VIDEO_SIZE) {
            throw new InvalidArgumentException('Video file size exceeds maximum allowed size (50MB)');
        }
        
        // Validate MIME type is a video
        if (!$this->isVideoMimeType($mimeType)) {
            throw new InvalidArgumentException('Invalid video MIME type: ' . $mimeType);
        }
        
        // Determine output MIME type and whether conversion is needed
        $outputMimeType = $mimeType;
        
        // If MOV, convert to MP4 using ffmpeg
        if ($mimeType === 'video/quicktime') {
            if (!$this->isFfmpegAvailable()) {
                throw new RuntimeException('ffmpeg is required to convert MOV files but is not available');
            }
            
            // Convert MOV to MP4 with reasonable quality settings
            // -movflags +faststart: Optimize for web streaming
            // -c:v libx264: Use H.264 video codec (widely supported)
            // -c:a aac: Use AAC audio codec
            // -preset medium: Balance between encoding speed and file size
            // -crf 23: Good quality (lower = better, 18-28 is typical range)
            $cmd = sprintf(
                'ffmpeg -i %s -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k -movflags +faststart -y %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($targetPath)
            );
            
            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                error_log("ffmpeg conversion failed: " . implode("\n", $output));
                throw new RuntimeException('Failed to convert video to MP4');
            }
            
            // Get the new file size
            $fileSize = filesize($targetPath);
            if ($fileSize === false) {
                throw new RuntimeException('Failed to get converted video file size');
            }
            
            $outputMimeType = 'video/mp4';
        } else {
            // MP4 and WebM - copy as-is
            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException('Failed to copy video file');
            }
        }
        
        // Get video dimensions
        $dimensions = $this->getVideoDimensions($targetPath);
        
        return [
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'file_size' => $fileSize,
            'mime_type' => $outputMimeType
        ];
    }
    
    /**
     * Save raw image without processing (validation or conversion)
     * WARNING: This bypasses security validation. Use only for trusted sources.
     * 
     * @param string $sourcePath Source file path
     * @param string $targetPath Target file path
     * @return array Image metadata (width, height, file_size, mime_type)
     */
    public function saveRawImage(string $sourcePath, string $targetPath): array
    {
        if (!file_exists($sourcePath)) {
            throw new InvalidArgumentException('Source file does not exist');
        }
        
        $fileSize = filesize($sourcePath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('File size exceeds maximum allowed size');
        }
        
        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $sourcePath);
        
        // Get dimensions if possible (skip for non-image files)
        $width = null;
        $height = null;
        if ($mimeType !== 'image/svg+xml') {
            $imageInfo = @getimagesize($sourcePath);
            if ($imageInfo !== false) {
                [$width, $height] = $imageInfo;
            }
        }
        
        // Copy file as-is
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Failed to copy raw image file');
        }
        
        return [
            'width' => $width,
            'height' => $height,
            'file_size' => $fileSize,
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Optimize and convert image to WebP
     * 
     * Note: Animated GIFs should be handled separately using preserveAnimatedGif()
     * before calling this method. This method will convert static GIFs to WebP.
     * 
     * @param string $sourcePath Source image path
     * @param string $targetPath Target path for output image
     * @param string $imageType Image type ('profile', 'header', 'post')
     * @param bool $checkAnimatedGif If true, will throw exception for animated GIFs
     * @return array Image metadata (width, height, file_size)
     */
    public function optimizeAndConvert(
        string $sourcePath,
        string $targetPath,
        string $imageType,
        bool $checkAnimatedGif = false
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
        
        // Check for animated GIF - these should be handled by preserveAnimatedGif()
        if ($mimeType === 'image/gif' && $checkAnimatedGif && $this->isAnimatedGif($sourcePath)) {
            throw new InvalidArgumentException('Animated GIFs should be handled with preserveAnimatedGif()');
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
     * Generate secure filename: {userId}_{timestamp}_{random}.{ext}
     * 
     * @param int $userId User ID
     * @param string $originalFilename Original filename
     * @param bool $preserveGif Whether to preserve .gif extension (for animated GIFs)
     * @param string|null $videoMimeType Video MIME type if this is a video (determines extension)
     * @return string Secure filename
     */
    public function generateSecureFilename(int $userId, string $originalFilename, bool $preserveGif = false, ?string $videoMimeType = null): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        
        // Determine output extension:
        // - Video files: WebM stays .webm, others become .mp4 (MOV is converted)
        // - SVG files keep .svg
        // - Animated GIFs keep .gif when preserveGif is true
        // - Everything else becomes .webp
        if ($videoMimeType !== null) {
            // WebM videos keep their extension, MOV converts to MP4
            $ext = ($videoMimeType === 'video/webm') ? 'webm' : 'mp4';
        } elseif ($extension === 'svg') {
            $ext = 'svg';
        } elseif ($preserveGif && $extension === 'gif') {
            $ext = 'gif';
        } else {
            $ext = 'webp';
        }
        
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
