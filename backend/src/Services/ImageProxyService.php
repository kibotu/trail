<?php

declare(strict_types=1);

namespace Trail\Services;

use RuntimeException;

/**
 * Image Proxy Service - Proxy and cache external images to avoid CORS issues
 * 
 * External images may be blocked by browsers due to:
 * - Cross-Origin-Resource-Policy: same-origin
 * - Cross-Origin-Opener-Policy headers
 * - Other CORS restrictions
 * 
 * This service fetches and caches external images, serving them from your domain.
 */
class ImageProxyService
{
    private string $cacheDir;
    private int $cacheTtl;
    private int $maxFileSize;
    
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/svg+xml'
    ];
    
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/svg+xml' => 'svg'
    ];

    /**
     * @param string $cacheDir Directory to store cached images
     * @param int $cacheTtl Cache TTL in seconds (default: 7 days)
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     */
    public function __construct(
        string $cacheDir,
        int $cacheTtl = 604800,
        int $maxFileSize = 10485760
    ) {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->cacheTtl = $cacheTtl;
        $this->maxFileSize = $maxFileSize;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached image path, fetching from remote if needed
     * 
     * @param string $url External image URL
     * @return array{path: string, mime: string, cached: bool}|null
     */
    public function getImage(string $url): ?array
    {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        // Generate cache key from URL
        $cacheKey = $this->getCacheKey($url);
        $metaPath = $this->cacheDir . '/' . $cacheKey . '.meta';
        
        // Check if cached and valid
        if (file_exists($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true);
            if ($meta && isset($meta['path'], $meta['mime'], $meta['expires'])) {
                $imagePath = $this->cacheDir . '/' . $meta['path'];
                if (file_exists($imagePath)) {
                    // Check if still valid (not expired)
                    if ($meta['expires'] > time()) {
                        return [
                            'path' => $imagePath,
                            'mime' => $meta['mime'],
                            'cached' => true
                        ];
                    }
                }
            }
        }
        
        // Fetch from remote
        return $this->fetchAndCache($url, $cacheKey);
    }

    /**
     * Fetch image from remote URL and cache it
     */
    private function fetchAndCache(string $url, string $cacheKey): ?array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TrailBot/1.0; +https://trail.kibotu.net)',
            // Limit download size
            CURLOPT_BUFFERSIZE => 1024 * 1024,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadSize, $downloaded) {
                // Abort if file is too large
                if ($downloadSize > $this->maxFileSize || $downloaded > $this->maxFileSize) {
                    return 1; // Non-zero to abort
                }
                return 0;
            }
        ]);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error || $httpCode !== 200 || !$data) {
            error_log("ImageProxy: Failed to fetch {$url}: HTTP {$httpCode}, Error: {$error}");
            return null;
        }
        
        // Extract MIME type (may include charset)
        $mimeType = $contentType ? explode(';', $contentType)[0] : null;
        $mimeType = $mimeType ? trim($mimeType) : null;
        
        // Validate MIME type
        if (!$mimeType || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            // Try to detect from data
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->buffer($data);
            
            if (!$detectedMime || !in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
                error_log("ImageProxy: Invalid MIME type for {$url}: {$contentType}");
                return null;
            }
            $mimeType = $detectedMime;
        }
        
        // Get extension
        $ext = self::MIME_TO_EXT[$mimeType] ?? 'bin';
        $filename = $cacheKey . '.' . $ext;
        $imagePath = $this->cacheDir . '/' . $filename;
        
        // Save image
        if (file_put_contents($imagePath, $data) === false) {
            error_log("ImageProxy: Failed to save image to {$imagePath}");
            return null;
        }
        
        // Save metadata
        $meta = [
            'path' => $filename,
            'mime' => $mimeType,
            'url' => $url,
            'expires' => time() + $this->cacheTtl,
            'fetched_at' => date('Y-m-d H:i:s')
        ];
        
        $metaPath = $this->cacheDir . '/' . $cacheKey . '.meta';
        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT));
        
        return [
            'path' => $imagePath,
            'mime' => $mimeType,
            'cached' => false
        ];
    }

    /**
     * Generate cache key from URL
     */
    private function getCacheKey(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Encode URL for proxy endpoint
     */
    public static function encodeUrl(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    /**
     * Decode URL from proxy endpoint
     */
    public static function decodeUrl(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        
        if ($decoded === false || !filter_var($decoded, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        return $decoded;
    }

    /**
     * Resize a cached image to the given max width, caching the result.
     * Returns path to the resized version, or original if resize is not possible.
     * 
     * @param string $originalPath Path to the original cached image
     * @param string $mimeType MIME type of the image
     * @param int $maxWidth Target max width in pixels
     * @return array{path: string, mime: string}
     */
    public function getResized(string $originalPath, string $mimeType, int $maxWidth): array
    {
        $maxWidth = max(100, min(1920, $maxWidth));
        
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return ['path' => $originalPath, 'mime' => $mimeType];
        }
        
        $baseName = pathinfo($originalPath, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', $baseName);
        $resizedKey = $baseName . "_{$maxWidth}";
        $resizedPath = $this->cacheDir . '/' . $resizedKey . '.webp';
        
        if (file_exists($resizedPath)) {
            return ['path' => $resizedPath, 'mime' => 'image/webp'];
        }
        
        $sourceImage = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($originalPath),
            'image/png' => @imagecreatefrompng($originalPath),
            'image/webp' => @imagecreatefromwebp($originalPath),
            default => @imagecreatefromgif($originalPath),
        };
        
        if ($sourceImage === false) {
            return ['path' => $originalPath, 'mime' => $mimeType];
        }
        
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        
        if ($origWidth <= $maxWidth) {
            imagedestroy($sourceImage);
            return ['path' => $originalPath, 'mime' => $mimeType];
        }
        
        $ratio = $maxWidth / $origWidth;
        $newWidth = $maxWidth;
        $newHeight = (int) round($origHeight * $ratio);
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagewebp($newImage, $resizedPath, 80);
        
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        if (file_exists($resizedPath)) {
            return ['path' => $resizedPath, 'mime' => 'image/webp'];
        }
        
        return ['path' => $originalPath, 'mime' => $mimeType];
    }

    /**
     * Clean up expired cache files
     * 
     * @return int Number of files cleaned up
     */
    public function cleanup(): int
    {
        $deleted = 0;
        $now = time();
        
        $metaFiles = glob($this->cacheDir . '/*.meta');
        
        foreach ($metaFiles as $metaPath) {
            $meta = json_decode(file_get_contents($metaPath), true);
            
            if (!$meta || !isset($meta['expires']) || $meta['expires'] < $now) {
                // Delete meta file
                unlink($metaPath);
                $deleted++;
                
                // Delete associated image file
                if (isset($meta['path'])) {
                    $imagePath = $this->cacheDir . '/' . $meta['path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
}
