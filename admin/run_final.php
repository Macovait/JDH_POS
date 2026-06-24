<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$sql = file_get_contents(__DIR__ . '/../database/migrations/final_three_tables.sql');
$pdo->exec($sql);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "Final 3 tables created successfully.\n";
