<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = admin_db();
if (!$pdo) {
    echo 'DB ERROR: ' . ($GLOBALS['admin_db_error'] ?? 'unknown') . PHP_EOL;
    exit(1);
}

$hash = password_hash('password', PASSWORD_BCRYPT);
$stmt = $pdo->prepare('INSERT INTO admins (username, email, password_hash, name, role, status) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(['admin@platform.com', 'admin@platform.com', $hash, 'Super Admin', 'super_admin', 'active']);
echo 'Admin account created successfully.' . PHP_EOL;
