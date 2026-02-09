<?php

declare(strict_types=1);

namespace Trail\Config;

use Symfony\Component\Yaml\Yaml;

class Config
{
    public static function load(string $path): array
    {
        // Always use secrets.yml
        $secretsPath = dirname($path) . '/secrets.yml';
        
        if (!file_exists($secretsPath)) {
            throw new \RuntimeException(
                "Configuration file not found: {$secretsPath}\n" .
                "Please create secrets.yml from config.yml.example"
            );
        }

        $content = file_get_contents($secretsPath);
        return Yaml::parse($content);
    }

    /**
     * Get the maximum text length for entries and comments from config
     * 
     * @param array $config Configuration array
     * @return int Maximum text length (defaults to 140 if not set)
     */
    public static function getMaxTextLength(array $config): int
    {
        return (int) ($config['app']['max_text_length'] ?? 140);
    }

    /**
     * Get the maximum number of images per entry/comment from config
     * 
     * @param array $config Configuration array
     * @return int Maximum images per entry (defaults to 3 if not set)
     */
    public static function getMaxImagesPerEntry(array $config): int
    {
        return (int) ($config['app']['max_images_per_entry'] ?? 3);
    }
}
