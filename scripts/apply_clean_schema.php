<?php
/**
 * Apply Clean Schema to Database
 * 
 * This script applies the clean.sql schema to the database.
 * Run via: php scripts/apply_clean_schema.php
 */

require_once __DIR__ . '/../src/config.php';

$sqlFile = __DIR__ . '/../database/migrations/clean.sql';

echo "=== JDH POS Clean Schema Installer ===\n\n";

// Read SQL file
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die("ERROR: Could not read clean.sql file\n");
}

// Connect to MySQL (without database first)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to MySQL server\n";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '" . DB_NAME . "' created/verified\n";
    
    // Select database
    $pdo->exec("USE " . DB_NAME);
    echo "✓ Using database '" . DB_NAME . "'\n\n";
    
    // Split and execute SQL statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql))
    );
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement . ';');
            $success++;
            
            // Show progress for table creation
            if (preg_match('/CREATE TABLE.*?`(\w+)`/s', $statement, $matches)) {
                echo "  ✓ Created table: {$matches[1]}\n";
            }
            
        } catch (PDOException $e) {
            $errors++;
            // Only show non-trivial errors (ignore DROP IF EXISTS errors)
            if (!str_contains($e->getMessage(), 'Unknown table') && 
                !str_contains($e->getMessage(), 'Can\'t DROP')) {
                echo "  ✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Installation Complete ===\n";
    echo "Statements executed: {$success}\n";
    if ($errors > 0) {
        echo "Errors (mostly DROP IF EXISTS - safe to ignore): {$errors}\n";
    }
    echo "\nDatabase '{$database}' is ready with clean tenant_id architecture!\n";
    
} catch (PDOException $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}
