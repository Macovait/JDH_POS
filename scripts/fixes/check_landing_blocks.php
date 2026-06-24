<?php
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();
$rows = $pdo->query('SELECT section, block_key, title, subtitle, is_active FROM pos_landing_blocks ORDER BY section, sort_order')->fetchAll();
foreach ($rows as $r) {
    echo $r['section'] . ' | ' . $r['block_key'] . ' | ' . ($r['title'] ?: '-') . ' | active=' . $r['is_active'] . "\n";
}
