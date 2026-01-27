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
}
