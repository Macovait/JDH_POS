<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$pdo->prepare('UPDATE admins SET role = ? WHERE email = ?')->execute(['superadmin', 'admin@platform.com']);
echo 'Role updated to superadmin.' . PHP_EOL;
