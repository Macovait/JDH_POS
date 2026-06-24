<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();
$tables = $pdo->query("SHOW TABLES LIKE 'online_orders'")->fetchAll(PDO::FETCH_COLUMN);
if (empty($tables)) {
    echo "Table online_orders does not exist.\n";
    exit(1);
}
$cols = $pdo->query("SHOW COLUMNS FROM online_orders")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' ' . $col['Type'] . ($col['Null'] === 'NO' ? ' NOT NULL' : ' NULL') . "\n";
}
