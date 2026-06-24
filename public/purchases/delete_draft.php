<?php
/**
 * Delete Draft — handles draft sale deletion
 * Called via GET from list_draft.php JS (window.location.href)
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('sales.delete')) {
    enforce_permission('sales.delete');
}

$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Unauthorized');
}

$draft_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$draft_id) {
    header('Location: list_draft.php?msg=invalid');
    exit;
}

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$pdo = get_db_connection();

try {
    // Verify draft exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM sales WHERE id = ? AND tenant_id = ? AND status = 'draft'");
    $stmt->execute([$draft_id, $tenant_id]);
    if (!$stmt->fetch()) {
        header('Location: list_draft.php?msg=not_found');
        exit;
    }

    $pdo->beginTransaction();

    // Delete items first
    $stmt = $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$draft_id]);

    // Delete sale
    $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$draft_id, $tenant_id]);

    // Log activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, tenant_id, created_at) VALUES (?, 'draft_deleted', ?, ?, ?, NOW())");
    $stmt->execute([
        $user_id,
        "Deleted draft sale #$draft_id",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $tenant_id
    ]);

    $pdo->commit();

    header('Location: list_draft.php?msg=deleted');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete draft error: ' . $e->getMessage());
    header('Location: list_draft.php?msg=error');
    exit;
}
