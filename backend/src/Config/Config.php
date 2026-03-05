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

    private const INSECURE_SALT_PATTERNS = [
        'default_entry_salt_change_me',
        'default_salt_change_me',
        'change_this_to_a_random_string_in_production',
    ];

    /**
     * Get the entry hash salt, rejecting insecure defaults in production.
     */
    public static function getEntryHashSalt(array $config): string
    {
        $salt = $config['app']['entry_hash_salt'] ?? '';
        self::assertSaltConfigured($salt, 'entry_hash_salt', $config);
        return $salt;
    }

    /**
     * Get the nickname salt, rejecting insecure defaults in production.
     */
    public static function getNicknameSalt(array $config): string
    {
        $salt = $config['app']['nickname_salt'] ?? '';
        self::assertSaltConfigured($salt, 'nickname_salt', $config);
        return $salt;
    }

    private static function assertSaltConfigured(string $salt, string $name, array $config): void
    {
        if (($config['app']['environment'] ?? 'production') === 'development') {
            return;
        }
        if ($salt === '' || in_array($salt, self::INSECURE_SALT_PATTERNS, true)) {
            throw new \RuntimeException("Security: app.{$name} must be set to a unique random value in production");
        }
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
