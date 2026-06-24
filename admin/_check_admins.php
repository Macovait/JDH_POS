<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
if (!$pdo) { echo "No DB\n"; exit; }

// Check admins table columns
$cols = admin_table_columns('admins');
echo "Columns: " . implode(', ', $cols) . "\n\n";

// Check existing admins
$stmt = $pdo->query("SELECT * FROM admins LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "NO ADMINS FOUND - table is empty!\n";
} else {
    foreach ($rows as $r) {
        echo "ID: {$r['id']} | ";
        echo "Username: " . ($r['username'] ?? 'N/A') . " | ";
        echo "Email: " . ($r['email'] ?? 'N/A') . " | ";
        echo "Role: " . ($r['role'] ?? 'N/A') . " | ";
        echo "Status: " . ($r['status'] ?? $r['active'] ?? 'N/A') . "\n";
    }
}
