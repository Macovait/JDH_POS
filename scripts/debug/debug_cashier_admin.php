<?php
require_once __DIR__ . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.role_id, r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id AND u.tenant_id = r.tenant_id 
    WHERE u.username = ? AND u.deleted_at IS NULL 
    LIMIT 1
');
$stmt->execute(['Wycliffe']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'User: ' . json_encode($user) . PHP_EOL;
