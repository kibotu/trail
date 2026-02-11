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
        
        if ($bgColor === false || $textColor === false || $accentColor === false || $mutedColor === false) {
            imagedestroy($img);
            throw new RuntimeException("Failed to allocate colors");
        }
        
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
        
        // Draw Trail branding
        $this->drawText($img, 'Trail', $this->fontBoldPath, 42, $accentColor, self::PADDING, $y);
        $y += 70;
        
        // Draw post content
        $text = $entry['text'] ?? '';
        if (!empty($text)) {
            // Truncate if too long
            $maxChars = 280;
            if (mb_strlen($text) > $maxChars) {
                $text = mb_substr($text, 0, $maxChars) . '...';
            }
            
            // Word wrap and draw
            $lines = $this->wordWrap($text, 32, self::WIDTH - (self::PADDING * 2));
            $maxLines = 8; // Limit to 8 lines
            $lineCount = 0;
            
            foreach ($lines as $line) {
                if ($lineCount >= $maxLines) {
                    break;
                }
                $this->drawText($img, $line, $this->fontPath, 32, $textColor, self::PADDING, $y);
                $y += (int) (32 * self::LINE_HEIGHT);
                $lineCount++;
            }
            
            $y += 30;
        }
        
        // Position footer at bottom
        $footerY = self::HEIGHT - self::PADDING - 40;
        
        // Draw author info
        $displayName = $entry['user_nickname'] ?? $entry['user_name'] ?? 'User';
        $authorText = "@{$displayName}";
        $this->drawText($img, $authorText, $this->fontBoldPath, 24, $textColor, self::PADDING, $footerY);
        
        // Draw timestamp
        $timestamp = $this->formatTimestamp($entry['created_at']);
        $this->drawText($img, $timestamp, $this->fontPath, 20, $mutedColor, self::PADDING, $footerY + 35);
        
        // Save as PNG
        $success = imagepng($img, $outputPath, 9); // Max compression
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
     * @return string Formatted date
     */
    private function formatTimestamp(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            return $date->format('M j, Y');
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
}
