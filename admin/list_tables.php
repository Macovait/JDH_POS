<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

echo "=== PURCHASE-RELATED TABLES ===\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%purchase%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) echo "  - $t\n";

echo "\n=== ORDER-RELATED TABLES ===\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%order%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) echo "  - $t\n";

echo "\n=== STOCK-RELATED TABLES ===\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%stock%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) echo "  - $t\n";
