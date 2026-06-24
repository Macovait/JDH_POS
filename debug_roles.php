<?php
require_once __DIR__ . '/src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();

$cols = $pdo->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_ASSOC);
echo "=== roles columns ===\n";
foreach ($cols as $c) echo $c['Field'] . " | " . $c['Type'] . "\n";

echo "\n=== sample roles ===\n";
$rows = $pdo->query("SELECT * FROM roles LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo json_encode($r) . "\n";
