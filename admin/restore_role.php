<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = admin_db();
$pdo->prepare('UPDATE admins SET role = ? WHERE email = ?')->execute(['super_admin', 'admin@platform.com']);
echo 'Role restored to super_admin.' . PHP_EOL;
