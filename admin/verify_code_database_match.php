<?php
/**
 * Verify Code-Database Match
 * Scans PHP files for table references and verifies they exist in DB
 */

require_once __DIR__ . '/../src/db.php';

$pdo = get_db_connection();

// Get existing tables
$stmt = $pdo->query("SHOW TABLES");
$db_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$db_tables = array_map('strtolower', $db_tables);

// Find all PHP files
$php_files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/../', RecursiveDirectoryIterator::SKIP_DOTS)
);

$table_patterns = [
    // SQL patterns
    '/from\s+`?(\w+)`?/i',
    '/into\s+`?(\w+)`?/i',
    '/update\s+`?(\w+)`?/i',
    '/join\s+`?(\w+)`?/i',
    '/table\s*[=:]\s*[\'"](\w+)[\'"]/i',
    '/[\'"](\w+)[\'"]\s*=>/i', // array key patterns
];

$found_tables = [];
$file_count = 0;

foreach ($php_files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $file_count++;
        $content = file_get_contents($file->getPathname());
        
        // Skip this script itself
        if (strpos($content, 'verify_code_database_match') !== false) {
            continue;
        }
        
        foreach ($table_patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[1] as $table) {
                // Filter out common false positives
                if (strlen($table) < 3 || is_numeric($table[0])) continue;
                if (in_array(strtolower($table), ['and', 'or', 'not', 'null', 'true', 'false', 'where', 'select', 'insert'])) continue;
                
                $found_tables[strtolower($table)][] = $file->getPathname();
            }
        }
    }
}

// Check for matches
$matched = [];
$missing_in_db = [];
$maybe_code_tables = [];

foreach ($found_tables as $table => $files) {
    if (in_array($table, $db_tables)) {
        $matched[] = $table;
    } else {
        // Could be a table name or false positive
        if (count($files) > 1) {
            $missing_in_db[] = ['table' => $table, 'files' => array_unique(array_slice($files, 0, 3))];
        } else {
            $maybe_code_tables[] = $table;
        }
    }
}

// Check for tables in DB not referenced in code (may be unused)
$referenced_tables_lower = array_map('strtolower', $matched);
$unreferenced = [];
foreach ($db_tables as $db_table) {
    if (!in_array($db_table, $referenced_tables_lower)) {
        $unreferenced[] = $db_table;
    }
}

echo "=== CODE-DATABASE VERIFICATION ===\n\n";
echo "PHP files scanned: $file_count\n";
echo "Database tables:   " . count($db_tables) . "\n";
echo "Tables in code:    " . count($found_tables) . "\n\n";

echo "MATCHED (Table exists in DB and is referenced in code): " . count($matched) . "\n";
foreach (array_slice($matched, 0, 30) as $i => $t) {
    echo "  ✓ $t\n";
}
if (count($matched) > 30) {
    echo "  ... and " . (count($matched) - 30) . " more\n";
}

if (count($missing_in_db) > 0) {
    echo "\n\nPOTENTIALLY MISSING FROM DATABASE (referenced in multiple files):\n";
    foreach ($missing_in_db as $item) {
        echo "  ✗ {$item['table']}\n";
        foreach ($item['files'] as $f) {
            echo "     - " . str_replace(__DIR__ . '/../', '', $f) . "\n";
        }
    }
}

if (count($unreferenced) > 0) {
    echo "\n\nTABLES IN DATABASE BUT NOT REFERENCED IN CODE (may be unused):\n";
    foreach (array_slice($unreferenced, 0, 20) as $t) {
        echo "  ? $t\n";
    }
    if (count($unreferenced) > 20) {
        echo "  ... and " . (count($unreferenced) - 20) . " more\n";
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";
