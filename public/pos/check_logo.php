<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);

if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();

if (!$tenant_id || !$user_id) {
    die("Not logged in");
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ('company_logo', 'company_name')");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo htmlspecialchars($row['setting_key']) . ' = ' . htmlspecialchars($row['setting_value']) . '<br>';
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
