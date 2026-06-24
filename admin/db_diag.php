<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain');

$pdo = admin_db();
if (!$pdo) { echo "No DB connection.\n"; exit; }

// List tables with 'plan' in name
$tables = $pdo->query("SHOW TABLES LIKE '%plan%'")->fetchAll(PDO::FETCH_COLUMN);
echo "Plan-related tables:\n";
foreach ($tables as $t) echo "  - {$t}\n";

// For each plan table, show columns
foreach ($tables as $table) {
    echo "\n--- {$table} columns ---\n";
    $cols = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['Field']} : {$c['Type']}\n";
    }
}

// Show recent errors from any INSERT attempts
$errors = $pdo->query("SHOW ENGINE INNODB STATUS")->fetch(PDO::FETCH_ASSOC);
// This may not work, just showing table structure for now
