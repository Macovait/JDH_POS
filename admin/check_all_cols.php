<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();

$tables = ['sale_items', 'inventory', 'products'];
foreach ($tables as $t) {
    $has = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
    if (!$has) { echo "{$t}: MISSING\n"; continue; }
    $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}'")->fetchAll(PDO::FETCH_COLUMN);
    echo "{$t}: " . implode(', ', $cols) . "\n";
}
