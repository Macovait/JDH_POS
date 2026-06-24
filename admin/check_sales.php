<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$cols = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_ASSOC);
echo "sales columns:\n";
foreach ($cols as $c) echo "  {$c['Field']} : {$c['Type']}\n";
