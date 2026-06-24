<?php
/**
 * Delete Return
 * Permanently delete a return (Admin only)
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

// Only admins and super admins can delete
if (!is_super_admin() && $_SESSION['role'] !== 'Admin') {
    header('Location: list_sell_return.php?error=Permission denied');
    exit;
}

safe_require('db.php', 'src', true);

$return_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$return_id) {
    header('Location: list_sell_return.php?error=Invalid return ID');
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $current_branch_id = get_current_branch_id();
    $user_id = (int) $_SESSION['user_id'];
    $tenant_id = (int) get_current_tenant_id();
    $can_switch_branch = is_super_admin() || check_permission('branches.view');
    $selected_branch_id = $current_branch_id;

    // Get return details for logging
    $return_sql = "
        SELECT return_number, branch_id FROM returns 
        WHERE id = ? AND tenant_id = ?";
    $return_params = [$return_id, $tenant_id];
    if (!$can_switch_branch) {
        $return_sql .= " AND branch_id = ?";
        $return_params[] = $current_branch_id;
    } elseif (!empty($_GET['branch_id'])) {
        $requested_branch_id = (int) $_GET['branch_id'];
        if ($requested_branch_id > 0) {
            $selected_branch_id = $requested_branch_id;
            $return_sql .= " AND branch_id = ?";
            $return_params[] = $selected_branch_id;
        }
    }
    $stmt = $pdo->prepare($return_sql);
    $stmt->execute($return_params);
    $return = $stmt->fetch();

    if (!$return) {
        // Check if super admin (might be from other branch)
        if (is_super_admin()) {
            $stmt = $pdo->prepare("SELECT return_number, branch_id FROM returns WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$return_id, $tenant_id]);
            $return = $stmt->fetch();
        }

        if (!$return) {
            throw new Exception("Return not found");
        }
    }

    $selected_branch_id = (int) ($return['branch_id'] ?? $selected_branch_id);

    // Delete return items first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM return_items WHERE return_id = ? AND tenant_id = ?");
    $stmt->execute([$return_id, $tenant_id]);

    // Delete return
    $stmt = $pdo->prepare("DELETE FROM returns WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$return_id, $tenant_id]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, meta, branch_id, tenant_id, created_at)
        VALUES (?, 'return_delete', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        json_encode(['description' => "Deleted return #{$return['return_number']}", 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null]),
        $selected_branch_id,
        $tenant_id
    ]);

    $pdo->commit();

    header("Location: list_sell_return.php?success=deleted&branch_id=" . $selected_branch_id);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error deleting return: " . $e->getMessage());
    header("Location: list_sell_return.php?error=" . urlencode($e->getMessage()));
}
