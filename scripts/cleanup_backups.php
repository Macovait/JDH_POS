<?php
/**
 * Backup Files Cleanup Script
 * 
 * Removes all .backup.* files created during refactoring
 * 
 * Usage:
 *   php scripts/cleanup_backups.php --preview    # Show what would be deleted
 *   php scripts/cleanup_backups.php --delete    # Actually delete files
 */

$preview = !in_array('--delete', $argv);
$projectRoot = dirname(__DIR__);
$deleted = 0;
$errors = [];

echo "=== Backup Files Cleanup ===\n\n";

if ($preview) {
    echo "Mode: PREVIEW (use --delete to actually remove files)\n\n";
}

// Find all backup files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS)
);

$backups = [];
foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/\.backup\.\d+$/', $file->getFilename())) {
        $backups[] = $file->getPathname();
    }
}

echo "Found " . count($backups) . " backup files:\n\n";

// Show first 10
foreach (array_slice($backups, 0, 10) as $backup) {
    $relative = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $backup);
    echo "  - {$relative}\n";
}

if (count($backups) > 10) {
    echo "  ... and " . (count($backups) - 10) . " more\n";
}

if (!$preview && count($backups) > 0) {
    echo "\nDeleting files...\n";
    
    foreach ($backups as $backup) {
        if (unlink($backup)) {
            $deleted++;
            $relative = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $backup);
            echo "  ✓ Deleted: {$relative}\n";
        } else {
            $errors[] = $backup;
            echo "  ✗ Failed: {$relative}\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Deleted: {$deleted} files\n";
    if (count($errors) > 0) {
        echo "Errors: " . count($errors) . "\n";
    }
    echo "\nCleanup complete!\n";
} else {
    echo "\nTo delete these files, run: php scripts/cleanup_backups.php --delete\n";
}
