<?php
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';
$pdo = get_db_connection();
$cols = $pdo->query('SHOW COLUMNS FROM categories')->fetchAll();
foreach ($cols as $c) echo $c['Field'] . "\n";
