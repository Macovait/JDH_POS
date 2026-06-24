<?php
/**
 * One-time script to clear default branch address/phone values
 * Run once, then delete this file.
 */
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 2) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
require_login();

if (!is_super_admin() && !has_role(['owner', 'admin'])) {
    die('Unauthorized');
}

$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare("UPDATE branches SET address = NULL WHERE address = ? OR address = ''");
    $stmt->execute(['123 Main Street']);
    $addr_cleared = $stmt->rowCount();

    $stmt = $pdo->prepare("UPDATE branches SET phone = NULL WHERE phone = ? OR phone = ''");
    $stmt->execute(['+254700000000']);
    $phone_cleared = $stmt->rowCount();

    echo "Done. Cleared {$addr_cleared} branch address(es) and {$phone_cleared} branch phone number(s).<br>";
    echo "<a href='receipt.php?id=1'>View receipt</a> | <strong>Delete this file after use.</strong>";
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
