<?php
require_once __DIR__ . '/../src/paths.php';
load_core_files();
$pdo = get_db_connection();
foreach ($pdo->query("SELECT id, name, status FROM pos_tenants LIMIT 5") as $r) {
    echo $r['id'] . ' | ' . $r['name'] . ' | ' . $r['status'] . PHP_EOL;
}
