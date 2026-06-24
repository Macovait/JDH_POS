<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();

$tables = ['subscriptions', 'pos_subscriptions', 'invoices', 'pos_invoices'];
foreach ($tables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
    if (!$exists) { echo "{$t}: MISSING\n"; continue; }
    $cols = $pdo->query("SHOW COLUMNS FROM {$t}")->fetchAll(PDO::FETCH_ASSOC);
    echo "{$t}:\n";
    foreach ($cols as $c) {
        echo "  {$c['Field']} = {$c['Type']}\n";
    }
}
