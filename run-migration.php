#!/usr/bin/env php
<?php
/**
 * Simple migration runner script
 * Usage: php run-migration.php migrations/011_add_report_and_mute_system.sql
 */

declare(strict_types=1);

require __DIR__ . '/backend/vendor/autoload.php';

use Trail\Config\Config;
use Trail\Database\Database;

if ($argc < 2) {
    echo "Usage: php run-migration.php <migration-file.sql>\n";
    echo "Example: php run-migration.php migrations/011_add_report_and_mute_system.sql\n";
    exit(1);
}

$migrationFile = $argv[1];

if (!file_exists($migrationFile)) {
    echo "Error: Migration file not found: {$migrationFile}\n";
    exit(1);
}

try {
    // Load configuration
    $config = Config::load(__DIR__ . '/backend/secrets.yml');
    
    // Get database connection
    $db = Database::getInstance($config);
    
    // Read migration file
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    echo "Running migration: {$migrationFile}\n";
    
    // Execute the migration
    // Note: We need to split by semicolons and execute each statement separately
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($stmt) => !empty($stmt) && !preg_match('/^--/', $stmt)
    );
    
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        
        try {
            $db->exec($statement);
            echo ".";
        } catch (PDOException $e) {
            // Check if it's a "table already exists" error
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "S"; // Skip - already exists
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n\nError running migration: " . $e->getMessage() . "\n";
    exit(1);
}
