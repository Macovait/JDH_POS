<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain');

$pdo = admin_db();
if (!$pdo) { echo "No DB connection.\n"; exit; }

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $cols = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('billing_cycle', $cols, true)) {
        echo "[OK] {$table} has billing_cycle\n";
    } else {
        // Check for related columns that suggest it might need billing_cycle
        $hasPrice = false;
        foreach ($cols as $c) {
            if (stripos($c, 'price') !== false || stripos($c, 'amount') !== false) {
                $hasPrice = true;
            }
        }
        if ($hasPrice) {
            echo "[MAYBE MISSING] {$table} has price/amount but NO billing_cycle\n";
        }
    }
}
