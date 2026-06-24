<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();
$r = $pdo->query("SHOW COLUMNS FROM customers WHERE Field = 'id'")->fetch(PDO::FETCH_ASSOC);
echo $r['Field'] . ' ' . $r['Type'] . ' ' . ($r['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
