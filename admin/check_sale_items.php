<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items'")->fetchAll(PDO::FETCH_COLUMN);
echo "sale_items: " . implode(', ', $cols) . "\n";
