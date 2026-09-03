<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain');
echo 'id=' . session_id() . PHP_EOL;
echo 'admin_id=' . var_export($_SESSION['admin_id'] ?? null, true) . PHP_EOL;
echo 'role=' . var_export($_SESSION['admin_role'] ?? null, true) . PHP_EOL;
echo 'auth=' . var_export(admin_is_authenticated(), true) . PHP_EOL;
