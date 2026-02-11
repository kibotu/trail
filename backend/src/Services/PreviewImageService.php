<?php

declare(strict_types=1);

namespace Trail\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Preview Image Service - Generate Open Graph preview cards
 * 
 * Creates beautiful 1200x630 PNG preview cards for social media sharing
 * using PHP GD with caching support.
 */
class PreviewImageService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const PADDING = 60;
    private const LINE_HEIGHT = 1.4;
    
    // Trail color scheme
    private const BG_COLOR = [30, 41, 59];        // #1e293b (dark slate)
    private const TEXT_COLOR = [255, 255, 255];   // #ffffff (white)
    private const ACCENT_COLOR = [59, 130, 246];  // #3b82f6 (blue)
    private const MUTED_COLOR = [148, 163, 184];  // #94a3b8 (slate-400)
    
    private string $cacheDir;
    private string $fontPath;
    private string $fontBoldPath;
    
    public function __construct(string $cacheDir, string $fontPath, string $fontBoldPath)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->fontPath = $fontPath;
        $this->fontBoldPath = $fontBoldPath;
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Validate fonts exist
        if (!file_exists($this->fontPath)) {
            throw new InvalidArgumentException("Font file not found: {$this->fontPath}");
        }
        if (!file_exists($this->fontBoldPath)) {
            throw new InvalidArgumentException("Bold font file not found: {$this->fontBoldPath}");
        }
    }
    
    /**
     * Generate or retrieve cached preview image
     * 
     * @param string $hashId Entry hash ID
     * @param array $entry Entry data with text, user info, created_at
     * @return string Path to generated PNG file
     */
    public function getOrGeneratePreview(string $hashId, array $entry): string
    {
        $cachePath = $this->getCachePath($hashId);
        
        // Check if cache exists and is fresh
        if ($this->isCacheFresh($cachePath, $entry['updated_at'] ?? $entry['created_at'])) {
            return $cachePath;
        }
        
        // Generate new preview
        $this->generatePreview($cachePath, $entry);
        
        return $cachePath;
    }
    
    /**
     * Generate preview card image
     * 
     * @param string $outputPath Path to save PNG
     * @param array $entry Entry data
     */
    private function generatePreview(string $outputPath, array $entry): void
    {
        // Check if entry has URL preview with image - create Medium-style card
        $hasUrlPreview = !empty($entry['preview_image']) && !empty($entry['preview_title']);
        
        if ($hasUrlPreview) {
            $this->generateUrlPreviewCard($outputPath, $entry);
        } else {
            $this->generateTextCard($outputPath, $entry);
        }
    }
    
    /**
     * Generate card matching the existing link-preview-card style
     * Horizontal layout: square image on left, content on right
     */
    private function generateUrlPreviewCard(string $outputPath, array $entry): void
    {
        // Create canvas
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($img === false) {
            throw new RuntimeException("Failed to create image canvas");
        }
        
        // Allocate colors matching CSS variables
        $bgColor = imagecolorallocate($img, ...self::BG_COLOR);  // var(--bg-tertiary)
        $textColor = imagecolorallocate($img, ...self::TEXT_COLOR);  // var(--text-primary)
        $textSecondary = imagecolorallocate($img, ...self::MUTED_COLOR);  // var(--text-secondary)
        $accentColor = imagecolorallocate($img, ...self::ACCENT_COLOR);  // var(--accent)
        $borderColor = imagecolorallocate($img, 51, 65, 85);  // var(--border)
        
        // Fill background
        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $bgColor);
        
        // Draw border around entire card (1px)
        imagerectangle($img, 0, 0, self::WIDTH - 1, self::HEIGHT - 1, $borderColor);
        
        // Define layout matching CSS: image on left (square), content on right
        $imageSize = 300;  // Square image (scaled up from 120px for 1200px width)
        $imageX = self::PADDING;
        $imageY = self::PADDING;
        $contentX = $imageX + $imageSize + self::PADDING;
        $contentWidth = self::WIDTH - $contentX - self::PADDING;
        
        // Load and draw preview image on the left (square)
        if (!empty($entry['preview_image'])) {
            try {
                $previewImg = $this->loadImageFromUrl($entry['preview_image']);
                if ($previewImg) {
                    // Draw square image with object-fit: cover behavior
                    $srcW = imagesx($previewImg);
                    $srcH = imagesy($previewImg);
                    
                    // Calculate crop to maintain aspect ratio (cover behavior)
                    $srcAspect = $srcW / $srcH;
                    if ($srcAspect > 1) {
                        // Wider than tall - crop width
                        $newSrcW = $srcH;
                        $srcX = ($srcW - $newSrcW) / 2;
                        $srcY = 0;
                        $newSrcH = $srcH;
                    } else {
                        // Taller than wide - crop height
                        $newSrcH = $srcW;
                        $srcY = ($srcH - $newSrcH) / 2;
                        $srcX = 0;
                        $newSrcW = $srcW;
                    }
                    
                    imagecopyresampled(
                        $img, $previewImg,
                        $imageX, $imageY,
                        (int)$srcX, (int)$srcY,
                        $imageSize, $imageSize,
                        (int)$newSrcW, (int)$newSrcH
                    );
                    
                    imagedestroy($previewImg);
                }
            } catch (\Exception $e) {
                error_log("Failed to load preview image: " . $e->getMessage());
            }
        }
        
        // Content area (right side)
        $y = $imageY + 30;
        
        // Draw preview title (font-weight: 600, 2 lines max)
        $previewTitle = $entry['preview_title'] ?? '';
        if (!empty($previewTitle)) {
            $lines = $this->wordWrap($previewTitle, 50, $contentWidth);
            $maxLines = 2;
            $lineCount = 0;
            
            foreach ($lines as $line) {
                if ($lineCount >= $maxLines) break;
                $this->drawText($img, $line, $this->fontBoldPath, 32, $textColor, $contentX, $y);
                $y += 44;
                $lineCount++;
            }
            $y += 20;
        }
        
        // Draw preview description (3 lines max, smaller, secondary color)
        $previewDesc = $entry['preview_description'] ?? '';
        if (!empty($previewDesc)) {
            $lines = $this->wordWrap($previewDesc, 60, $contentWidth);
            $maxLines = 3;
            $lineCount = 0;
            
            foreach ($lines as $line) {
                if ($lineCount >= $maxLines) break;
                $this->drawText($img, $line, $this->fontPath, 26, $textSecondary, $contentX, $y);
                $y += 38;
                $lineCount++;
            }
            $y += 20;
        }
        
        // Draw site name/URL at bottom with link icon (matching .link-preview-url)
        $siteName = $entry['preview_site_name'] ?? parse_url($entry['preview_url'] ?? '', PHP_URL_HOST) ?? '';
        if (!empty($siteName)) {
            // Draw link icon (🔗) and site name
            $urlY = self::HEIGHT - self::PADDING - 30;
            $this->drawText($img, '🔗 ' . $siteName, $this->fontPath, 24, $accentColor, $contentX, $urlY);
        }
        
        // Save as PNG
        imagepng($img, $outputPath, 9);
        imagedestroy($img);
        chmod($outputPath, 0644);
    }
    
    /**
     * Generate simple text card (no URL preview)
     */
    private function generateTextCard(string $outputPath, array $entry): void
    {
        // Create canvas
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($img === false) {
            throw new RuntimeException("Failed to create image canvas");
        }
        
        // Allocate colors
        $bgColor = imagecolorallocate($img, ...self::BG_COLOR);
        $textColor = imagecolorallocate($img, ...self::TEXT_COLOR);
        $accentColor = imagecolorallocate($img, ...self::ACCENT_COLOR);
        $mutedColor = imagecolorallocate($img, ...self::MUTED_COLOR);
        
        // Fill background
        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $bgColor);
        
        // Add subtle gradient effect (darker at bottom)
        for ($y = self::HEIGHT / 2; $y < self::HEIGHT; $y++) {
            $alpha = (int) (($y - self::HEIGHT / 2) / (self::HEIGHT / 2) * 20);
            $gradColor = imagecolorallocatealpha($img, 0, 0, 0, 127 - $alpha);
            if ($gradColor !== false) {
                imageline($img, 0, $y, self::WIDTH, $y, $gradColor);
            }
        }
        
        $y = self::PADDING;
        
        // Draw Trail branding (top left)
        $this->drawText($img, 'Trail', $this->fontBoldPath, 32, $accentColor, self::PADDING, $y);
        
        // Draw timestamp (top right)
        $timestamp = $this->formatTimestamp($entry['created_at']);
        $timestampX = self::WIDTH - self::PADDING - 250;
        $this->drawText($img, $timestamp, $this->fontPath, 22, $mutedColor, $timestampX, $y);
        
        $y += 100;
        
        // Draw post content (larger, more prominent)
        $text = $entry['text'] ?? '';
        if (!empty($text)) {
            // Truncate if too long
            $maxChars = 280;
            if (mb_strlen($text) > $maxChars) {
                $text = mb_substr($text, 0, $maxChars) . '...';
            }
            
            // Word wrap and draw with larger font
            $lines = $this->wordWrap($text, 30, self::WIDTH - (self::PADDING * 2));
            $maxLines = 8;
            $lineCount = 0;
            $fontSize = 40;
            
            foreach ($lines as $line) {
                if ($lineCount >= $maxLines) {
                    break;
                }
                $this->drawText($img, $line, $this->fontPath, $fontSize, $textColor, self::PADDING, $y);
                $y += (int) ($fontSize * self::LINE_HEIGHT);
                $lineCount++;
            }
        }
        
        // Add decorative line at bottom
        $lineY = self::HEIGHT - 20;
        $lineColor = imagecolorallocatealpha($img, self::ACCENT_COLOR[0], self::ACCENT_COLOR[1], self::ACCENT_COLOR[2], 100);
        if ($lineColor !== false) {
            imagefilledrectangle($img, self::PADDING, $lineY, self::WIDTH - self::PADDING, $lineY + 3, $lineColor);
        }
        
        // Save as PNG
        $success = imagepng($img, $outputPath, 9);
        imagedestroy($img);
        
        if (!$success) {
            throw new RuntimeException("Failed to save preview image to {$outputPath}");
        }
        
        // Set file permissions
        chmod($outputPath, 0644);
    }
    
    /**
     * Draw text on image
     * 
     * @param resource $img GD image resource
     * @param string $text Text to draw
     * @param string $fontPath Path to TTF font
     * @param int $size Font size
     * @param int $color Color resource
     * @param int $x X position
     * @param int $y Y position
     */
    private function drawText($img, string $text, string $fontPath, int $size, int $color, int $x, int $y): void
    {
        // imagettftext expects y to be the baseline, so we add the font size
        $result = imagettftext($img, $size, 0, $x, $y + $size, $color, $fontPath, $text);
        
        if ($result === false) {
            error_log("PreviewImageService: Failed to draw text: {$text}");
        }
    }
    
    /**
     * Word wrap text to fit within width
     * 
     * @param string $text Text to wrap
     * @param int $maxCharsPerLine Approximate max chars per line
     * @param int $maxWidth Max pixel width (unused for now, using char count)
     * @return array Lines of text
     */
    private function wordWrap(string $text, int $maxCharsPerLine, int $maxWidth): array
    {
        // Simple word wrap by character count
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';
        
        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            
            if (mb_strlen($testLine) <= $maxCharsPerLine) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }
        
        return $lines;
    }
    
    /**
     * Format timestamp for display
     * 
     * @param string $timestamp ISO 8601 timestamp
     * @return string Formatted date and time
     */
    private function formatTimestamp(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            // Format like: "Feb 11, 2026 at 11:44 AM"
            return $date->format('M j, Y \a\t g:i A');
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Get cache file path for hash ID
     * 
     * @param string $hashId Entry hash ID
     * @return string Full path to cache file
     */
    private function getCachePath(string $hashId): string
    {
        // Sanitize hash ID to prevent directory traversal
        $safeHashId = preg_replace('/[^a-zA-Z0-9]/', '', $hashId);
        return "{$this->cacheDir}/{$safeHashId}.png";
    }
    
    /**
     * Check if cached file is fresh
     * 
     * @param string $cachePath Path to cache file
     * @param string $entryUpdatedAt Entry's updated_at timestamp
     * @return bool True if cache is fresh
     */
    private function isCacheFresh(string $cachePath, string $entryUpdatedAt): bool
    {
        if (!file_exists($cachePath)) {
            return false;
        }
        
        try {
            $cacheTime = filemtime($cachePath);
            $entryTime = strtotime($entryUpdatedAt);
            
            if ($cacheTime === false || $entryTime === false) {
                return false;
            }
            
            // Cache is fresh if it's newer than the entry's last update
            return $cacheTime >= $entryTime;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Clear cache for specific hash ID
     * 
     * @param string $hashId Entry hash ID
     */
    public function clearCache(string $hashId): void
    {
        $cachePath = $this->getCachePath($hashId);
        if (file_exists($cachePath)) {
            @unlink($cachePath);
        }
    }
    
    /**
     * Clear all cached preview images
     */
    public function clearAllCache(): void
    {
        $files = glob("{$this->cacheDir}/*.png");
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Load image from URL
     * 
     * @param string $url Image URL
     * @return resource|false GD image resource or false on failure
     */
    private function loadImageFromUrl(string $url)
    {
        try {
            // Download image with timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'Trail/1.0 (+https://trail.services.kibotu.net)'
                ]
            ]);
            
            $imageData = @file_get_contents($url, false, $context);
            if ($imageData === false) {
                return false;
            }
            
            // Create image from string
            $img = @imagecreatefromstring($imageData);
            return $img;
        } catch (\Exception $e) {
            error_log("Failed to load image from URL {$url}: " . $e->getMessage());
            return false;
        }
    }
}
