<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items'")->fetchAll(PDO::FETCH_COLUMN);
$hasQty = in_array('quantity', $cols);
$hasPrice = in_array('price', $cols);
$hasSubtotal = in_array('subtotal', $cols);
$hasProductId = in_array('product_id', $cols);
$hasSaleId = in_array('sale_id', $cols);
echo "quantity: " . ($hasQty ? 'YES' : 'NO') . "\n";
echo "price: " . ($hasPrice ? 'YES' : 'NO') . "\n";
echo "subtotal: " . ($hasSubtotal ? 'YES' : 'NO') . "\n";
echo "product_id: " . ($hasProductId ? 'YES' : 'NO') . "\n";
echo "sale_id: " . ($hasSaleId ? 'YES' : 'NO') . "\n";
