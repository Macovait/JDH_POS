<?php
/**
 * CLI Migration Runner - No Web Dependencies
 */

require_once __DIR__ . '/../src/db.php';

echo "==============================================\n";
echo "JDH POS Database Migration Tool\n";
echo "==============================================\n\n";

// Get database connection
try {
    $pdo = get_db_connection();
    echo "Database connection: OK\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

$results = [
    'success' => [],
    'error' => [],
    'skipped' => []
];

// Read all comprehensive migration SQL parts
$migration_files = [
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part1.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part2.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part3.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part4.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part5.sql',
];

$sql_content = '';
foreach ($migration_files as $file) {
    if (file_exists($file)) {
        $sql_content .= file_get_contents($file) . "\n";
    } else {
        echo "WARNING: Migration file not found: $file\n";
    }
}

// Disable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
echo "Foreign key checks: DISABLED\n\n";

// Parse and execute SQL statements
$statements = array_filter(array_map('trim', preg_split('/;\s*/', $sql_content)));
$total = count($statements);
$current = 0;

echo "Processing $total SQL statements...\n\n";

foreach ($statements as $statement) {
    $current++;
    
    // Skip empty statements and comments
    if (empty($statement) || preg_match('/^--|^#|^\/\*/', $statement)) {
        continue;
    }
    
    // Extract table name from CREATE TABLE statement
    $table_name = null;
    if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $statement, $matches)) {
        $table_name = $matches[1];
    }
    
    // Show progress for table creation
    if ($table_name && $current % 10 === 0) {
        echo "[$current/$total] Processing...\r";
    }
    
    try {
        $pdo->exec($statement);
        
        if ($table_name) {
            // Verify table was created
            $check = $pdo->query("SHOW TABLES LIKE '$table_name'")->fetchColumn();
            if ($check) {
                $results['success'][] = $table_name;
                echo "[CREATED] $table_name\n";
            } else {
                $results['error'][] = ['table' => $table_name, 'error' => 'Table not found after creation'];
            }
        }
    } catch (PDOException $e) {
        // Check if error is because table already exists
        $error_msg = $e->getMessage();
        if (strpos($error_msg, 'already exists') !== false || strpos($error_msg, '1050') !== false) {
            if ($table_name) {
                $results['skipped'][] = $table_name;
                echo "[EXISTS]  $table_name\n";
            }
        } else {
            if ($table_name) {
                $results['error'][] = ['table' => $table_name, 'error' => $error_msg];
                echo "[ERROR]   $table_name: " . substr($error_msg, 0, 80) . "\n";
            } else {
                // Non-table statement error - might be OK
                if (strpos($error_msg, 'Duplicate entry') !== false || 
                    strpos($error_msg, '1062') !== false) {
                    // Insert duplicate - ignore
                } else {
                    $results['error'][] = ['table' => 'SQL', 'error' => $error_msg];
                }
            }
        }
    }
}

// Re-enable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n\n==============================================\n";
echo "Migration Complete!\n";
echo "==============================================\n";
echo "Tables Created:   " . count($results['success']) . "\n";
echo "Tables Existing:  " . count($results['skipped']) . "\n";
echo "Errors:           " . count($results['error']) . "\n";
echo "\nForeign key checks: ENABLED\n";
echo "==============================================\n";

if (count($results['success']) > 0) {
    echo "\nSuccessfully created tables:\n";
    foreach ($results['success'] as $i => $table) {
        echo ($i + 1) . ". $table\n";
    }
}

if (count($results['error']) > 0) {
    echo "\nErrors encountered:\n";
    foreach (array_slice($results['error'], 0, 10) as $err) {
        echo "- " . $err['table'] . ": " . substr($err['error'], 0, 60) . "\n";
    }
    if (count($results['error']) > 10) {
        echo "... and " . (count($results['error']) - 10) . " more errors\n";
    }
}

echo "\n";
