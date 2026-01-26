<?php

declare(strict_types=1);

namespace Trail\Config;

use Symfony\Component\Yaml\Yaml;

class Config
{
    public static function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: {$path}");
        }

        $content = file_get_contents($path);
        
        // Replace environment variables
        $content = preg_replace_callback('/\$\{([A-Z_]+)\}/', function ($matches) {
            $envVar = $matches[1];
            $value = getenv($envVar);
            
            if ($value === false) {
                throw new \RuntimeException("Environment variable {$envVar} is not set");
            }
            
            return $value;
        }, $content);

        return Yaml::parse($content);
    }
}
