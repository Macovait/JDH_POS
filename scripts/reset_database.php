<?php
/**
 * Database Reset Script - Complete SaaS Rebuild
 * 
 * ⚠️ WARNING: This will DELETE ALL DATA in the database!
 * 
 * Usage:
 *   php scripts/reset_database.php --preview    # Show what would be dropped
 *   php scripts/reset_database.php --execute    # Actually drop and recreate
 */

// Database configuration (update these if different)
define('DB_HOST', 'localhost');
define('DB_NAME', 'jdh_pos');
define('DB_USER', 'root');
define('DB_PASS', ''); // Add password if required

$preview = !in_array('--execute', $argv);
$dropOnly = in_array('--drop-only', $argv);

echo "=== JDH POS Database Reset ===\n\n";

if ($preview) {
    echo "⚠️  MODE: PREVIEW (no changes will be made)\n";
    echo "    Use --execute to actually reset the database\n\n";
} else {
    echo "⚠️  MODE: EXECUTE\n";
    echo "    ALL DATA WILL BE PERMANENTLY DELETED!\n\n";
    
    // Extra confirmation
    if (!in_array('--force', $argv)) {
        echo "Add --force flag to confirm deletion:\n";
        echo "  php scripts/reset_database.php --execute --force\n\n";
        exit(1);
    }
}

try {
    // First connect without database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '" . DB_NAME . "' created/verified\n";
    
    // Now connect to the database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to database: " . DB_NAME . "\n\n";
    
    // Step 1: Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($tables) . " tables:\n";
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
    echo "\n";
    
    // Step 2: Get all views
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($views) > 0) {
        echo "Found " . count($views) . " views:\n";
        foreach ($views as $view) {
            echo "  - {$view}\n";
        }
        echo "\n";
    }
    
    // Step 3: Get all procedures
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = '" . DB_NAME . "'");
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($procedures) > 0) {
        echo "Found " . count($procedures) . " procedures:\n";
        foreach ($procedures as $proc) {
            echo "  - {$proc['Name']}\n";
        }
        echo "\n";
    }
    
    // Step 4: Get all functions
    $stmt = $pdo->query("SHOW FUNCTION STATUS WHERE Db = '" . DB_NAME . "'");
    $functions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($functions) > 0) {
        echo "Found " . count($functions) . " functions:\n";
        foreach ($functions as $func) {
            echo "  - {$func['Name']}\n";
        }
        echo "\n";
    }
    
    if ($preview) {
        echo "=== PREVIEW COMPLETE ===\n";
        echo "To execute reset, run:\n";
        echo "  php scripts/reset_database.php --execute --force\n";
        exit(0);
    }
    
    // EXECUTION MODE - Actually drop everything
    echo "=== EXECUTING RESET ===\n\n";
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop views first
    foreach ($views as $view) {
        $pdo->exec("DROP VIEW IF EXISTS `{$view}`");
        echo "✓ Dropped view: {$view}\n";
    }
    
    // Drop tables
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "✓ Dropped table: {$table}\n";
    }
    
    // Drop procedures
    foreach ($procedures as $proc) {
        $pdo->exec("DROP PROCEDURE IF EXISTS `{$proc['Name']}`");
        echo "✓ Dropped procedure: {$proc['Name']}\n";
    }
    
    // Drop functions
    foreach ($functions as $func) {
        $pdo->exec("DROP FUNCTION IF EXISTS `{$func['Name']}`");
        echo "✓ Dropped function: {$func['Name']}\n";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n=== ALL OBJECTS DROPPED ===\n";
    
    if ($dropOnly) {
        echo "\nDatabase is now empty (--drop-only mode)\n";
        exit(0);
    }
    
    // Step 5: Apply clean.sql
    echo "\n=== APPLYING CLEAN SCHEMA ===\n\n";
    
    $sqlFile = __DIR__ . '/../database/migrations/clean.sql';
    if (!file_exists($sqlFile)) {
        die("ERROR: clean.sql not found at {$sqlFile}\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success = 0;
    $errors = [];
    $tablesCreated = 0;
    $viewsCreated = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement . ';');
            $success++;
            
            // Detect what was created
            if (preg_match('/CREATE TABLE.*?`(\w+)`/is', $statement, $matches)) {
                $tablesCreated++;
                echo "  ✓ Created table: {$matches[1]}\n";
            } elseif (preg_match('/CREATE VIEW.*?`(\w+)`/is', $statement, $matches)) {
                $viewsCreated++;
                echo "  ✓ Created view: {$matches[1]}\n";
            }
            
        } catch (PDOException $e) {
            // Ignore DROP IF EXISTS errors
            if (!str_contains($e->getMessage(), 'Unknown table') && 
                !str_contains($e->getMessage(), 'Can\'t DROP') &&
                !str_contains($e->getMessage(), 'doesn\'t exist')) {
                $errors[] = $e->getMessage();
                echo "  ✗ Error: " . substr($e->getMessage(), 0, 100) . "\n";
            } else {
                $success++;
            }
        }
    }
    
    echo "\n=== SCHEMA APPLIED ===\n";
    echo "Tables created: {$tablesCreated}\n";
    echo "Views created: {$viewsCreated}\n";
    echo "Statements executed: {$success}\n";
    if (count($errors) > 0) {
        echo "Errors: " . count($errors) . "\n";
    }
    
    // Verify
    $stmt = $pdo->query("SHOW TABLES");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n=== VERIFICATION ===\n";
    echo "Total tables in database: " . count($finalTables) . "\n";
    echo "Expected: 146 tables\n";
    
    if (count($finalTables) === 146) {
        echo "✓ Database reset successful!\n";
    } else {
        echo "⚠ Table count mismatch. Some tables may have failed to create.\n";
    }
    
    echo "\n=== NEXT STEPS ===\n";
    echo "1. Create your first tenant via register_company.php\n";
    echo "2. Login and start using the POS\n";
    echo "3. Add branches, products, and users\n";
    
} catch (PDOException $e) {
    die("\nERROR: " . $e->getMessage() . "\n");
}
