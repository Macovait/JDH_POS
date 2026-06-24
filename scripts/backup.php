#!/usr/bin/env php
<?php
/**
 * Database Backup Script
 * Run manually or via cron: php scripts/backup.php
 */

require_once __DIR__ . '/../src/paths.php';
require_once __DIR__ . '/../src/Backup/DatabaseBackup.php';

use JDH\POS\Backup\DatabaseBackup;

echo "=== JDH POS Database Backup ===\n\n";

// Get database config
$dbConfig = [
    'host' => 'localhost',
    'database' => 'jdh_pos',
    'username' => 'root',
    'password' => ''
];

// Try to load from config if exists
$configFile = __DIR__ . '/../config/database.php';
if (file_exists($configFile)) {
    $dbConfig = require $configFile;
}

$backup = new DatabaseBackup(
    $dbConfig['host'] ?? 'localhost',
    $dbConfig['database'] ?? 'jdh_pos',
    $dbConfig['username'] ?? 'root',
    $dbConfig['password'] ?? '',
    __DIR__ . '/../storage/backups',
    30 // Keep 30 days
);

// Show current stats
echo "Current backup stats:\n";
$stats = $backup->stats();
echo "  - Total backups: {$stats['total_backups']}\n";
echo "  - Total size: {$stats['total_size_formatted']}\n";
echo "  - Retention: {$stats['retention_days']} days\n";
if ($stats['latest_backup']) {
    echo "  - Latest backup: {$stats['latest_backup']['created_at']}\n";
}
echo "\n";

// Create new backup
echo "Creating new backup...\n";
$result = $backup->create();

if ($result['success']) {
    echo "✓ Backup created successfully!\n";
    echo "  File: {$result['filename']}\n";
    echo "  Size: {$result['size_formatted']}\n";
    echo "  Path: {$result['file']}\n";
} else {
    echo "✗ Backup failed!\n";
    echo "  Error: {$result['error']}\n";
    exit(1);
}

// Clean up old backups
echo "\nCleaning up old backups...\n";
$deleted = $backup->cleanup();
echo "✓ Deleted {$deleted} old backup(s)\n";

echo "\n=== Backup Complete ===\n";
