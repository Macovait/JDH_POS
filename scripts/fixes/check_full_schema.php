<?php
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

echo "=== All Tables ===\n";
$rows = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
sort($rows);
foreach ($rows as $t) echo "  $t\n";

echo "\n=== Categories columns ===\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) { echo "  " . $e->getMessage() . "\n"; }

echo "\n=== pos_tenants columns ===\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM pos_tenants")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) { echo "  " . $e->getMessage() . "\n"; }

echo "\n=== Inventory columns ===\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM inventory")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  " . $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) { echo "  " . $e->getMessage() . "\n"; }
