<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

echo "=== CATEGORIES TABLE STRUCTURE ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM categories");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}
