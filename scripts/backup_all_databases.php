<?php
/**
 * Full Database Backup to Migration Files
 * Generates timestamped SQL migration files from all tables in the database.
 */

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

loadEnv(__DIR__ . '/../.env');

$host   = $_ENV['DB_HOST']   ?? 'localhost';
$dbname = $_ENV['DB_NAME']   ?? 'jdh_pos';
$user   = $_ENV['DB_USER']   ?? 'root';
$pass   = $_ENV['DB_PASS']   ?? '';
$port   = $_ENV['DB_PORT']   ?? 3306;
$charset= $_ENV['DB_CHARSET']?? 'utf8mb4';

// Ensure backup directory exists
$backupDir = __DIR__ . '/../database/migrations/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Ymd_His');

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET NAMES {$charset}, time_zone = '+03:00'");

    echo "=== Database Migration Backup ===\n";
    echo "Database: {$dbname}\n";
    echo "Timestamp: {$timestamp}\n\n";

    // 1. Schema backup (all CREATE TABLE statements)
    $schemaFile = "{$backupDir}/{$timestamp}_full_schema.sql";
    $schemaSql = "-- ========================================================\n";
    $schemaSql .= "-- Full Schema Backup for {$dbname}\n";
    $schemaSql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $schemaSql .= "-- ========================================================\n\n";
    $schemaSql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // 2. Data backup (all INSERT statements)
    $dataFile = "{$backupDir}/{$timestamp}_full_data.sql";
    $dataSql = "-- ========================================================\n";
    $dataSql .= "-- Full Data Backup for {$dbname}\n";
    $dataSql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $dataSql .= "-- ========================================================\n\n";
    $dataSql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
        ->fetchAll(PDO::FETCH_COLUMN);

    $totalTables = count($tables);
    echo "Found {$totalTables} tables.\n\n";

    // ============================================
    // BACKUP SCHEMA
    // ============================================
    echo "--- Backing up SCHEMA ---\n";
    foreach ($tables as $table) {
        echo "  Schema: {$table}\n";

        // DROP TABLE IF EXISTS
        $schemaSql .= "DROP TABLE IF EXISTS `{$table}`;\n";

        // CREATE TABLE
        $createResult = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createResult->fetch(PDO::FETCH_ASSOC);
        $createStatement = $createRow['Create Table'] ?? $createRow["Create Table"] ?? '';
        $schemaSql .= $createStatement . ";\n\n";
    }

    // ============================================
    // BACKUP DATA (per table migration files)
    // ============================================
    echo "\n--- Backing up DATA ---\n";

    foreach ($tables as $table) {
        echo "  Data: {$table}\n";

        // Count rows
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $rowCount = (int) $countStmt->fetchColumn();

        if ($rowCount === 0) {
            echo "    -> Skipped (0 rows)\n";
            continue;
        }

        echo "    -> {$rowCount} rows\n";

        // Start table data in main data file
        $dataSql .= "-- ----------------------------\n";
        $dataSql .= "-- Data for table `{$table}`\n";
        $dataSql .= "-- ----------------------------\n";

        // Per-table migration file
        $tableFile = "{$backupDir}/{$timestamp}_data_{$table}.sql";
        $tableSql = "-- ========================================================\n";
        $tableSql .= "-- Data Migration: {$table}\n";
        $tableSql .= "-- Database: {$dbname}\n";
        $tableSql .= "-- Row Count: {$rowCount}\n";
        $tableSql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $tableSql .= "-- ========================================================\n\n";
        $tableSql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        $tableSql .= "TRUNCATE TABLE `{$table}`;\n\n";

        // Get columns
        $columnsResult = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = $columnsResult->fetchAll(PDO::FETCH_COLUMN);
        $columnList = implode(', ', array_map(function($c) { return '`' . $c . '`'; }, $columns));

        // Fetch and build INSERTs in chunks
        $offset = 0;
        $chunkSize = 500;
        $tableInserts = [];

        while ($offset < $rowCount) {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` LIMIT ? OFFSET ?");
            $stmt->execute([$chunkSize, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) break;

            $insertValues = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote($value);
                    }
                }
                $insertValues[] = '(' . implode(', ', $values) . ')';
            }

            $insertChunk = "INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $insertValues) . ";\n";
            $tableInserts[] = $insertChunk;
            $offset += $chunkSize;
        }

        $tableInsertSql = implode("\n", $tableInserts);
        $dataSql .= $tableInsertSql . "\n";
        $tableSql .= $tableInsertSql . "\n";
        $tableSql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Write per-table file
        file_put_contents($tableFile, $tableSql);
        echo "    -> Saved: backups/{$timestamp}_data_{$table}.sql\n";
    }

    // Finalize main files
    $schemaSql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $dataSql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Write main schema file
    file_put_contents($schemaFile, $schemaSql);
    echo "\n[SAVED] Schema: backups/{$timestamp}_full_schema.sql\n";

    // Write main data file
    file_put_contents($dataFile, $dataSql);
    echo "[SAVED] Data: backups/{$timestamp}_full_data.sql\n";

    // ============================================
    // SINGLE COMBINED MIGRATION FILE
    // ============================================
    $combinedFile = "{$backupDir}/{$timestamp}_full_backup.sql";
    $combinedSql = "-- ========================================================\n";
    $combinedSql .= "-- Complete Database Backup Migration\n";
    $combinedSql .= "-- Database: {$dbname}\n";
    $combinedSql .= "-- Tables: {$totalTables}\n";
    $combinedSql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $combinedSql .= "-- ========================================================\n\n";
    $combinedSql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    $combinedSql .= $schemaSql . "\n";
    $combinedSql .= $dataSql . "\n";
    $combinedSql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($combinedFile, $combinedSql);
    echo "[SAVED] Combined: backups/{$timestamp}_full_backup.sql\n";

    // ============================================
    // SUMMARY MIGRATION INDEX FILE
    // ============================================
    $indexFile = "{$backupDir}/{$timestamp}_migration_index.php";
    $indexContent = "<?php\n";
    $indexContent .= "// ========================================================\n";
    $indexContent .= "// Migration Index for {$dbname}\n";
    $indexContent .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
    $indexContent .= "// ========================================================\n\n";
    $indexContent .= "return [\n";
    $indexContent .= "    'database' => '{$dbname}',\n";
    $indexContent .= "    'timestamp' => '{$timestamp}',\n";
    $indexContent .= "    'generated_at' => '" . date('Y-m-d H:i:s') . "',\n";
    $indexContent .= "    'total_tables' => {$totalTables},\n";
    $indexContent .= "    'tables' => [\n";
    foreach ($tables as $table) {
        $indexContent .= "        '{$table}',\n";
    }
    $indexContent .= "    ],\n";
    $indexContent .= "    'files' => [\n";
    $indexContent .= "        'schema' => '{$timestamp}_full_schema.sql',\n";
    $indexContent .= "        'data' => '{$timestamp}_full_data.sql',\n";
    $indexContent .= "        'combined' => '{$timestamp}_full_backup.sql',\n";
    $indexContent .= "    ],\n";
    $indexContent .= "];\n";

    file_put_contents($indexFile, $indexContent);
    echo "[SAVED] Index: backups/{$timestamp}_migration_index.php\n";

    echo "\n=== Backup Complete ===\n";
    echo "Location: database/migrations/backups/\n";
    echo "Total tables backed up: {$totalTables}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
