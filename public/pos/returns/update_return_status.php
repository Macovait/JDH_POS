<?php
/**
 * Update Return Status
 * Process return completion or rejection
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

// Check for return permission
if (!check_permission('sales.returns') && !is_super_admin()) {
    header('Location: list_sell_return.php?error=Permission denied');
    exit;
}

safe_require('db.php', 'src', true);

$return_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$reject_reason = isset($_GET['reason']) ? $_GET['reason'] : '';

if (!$return_id || !in_array($status, ['completed', 'rejected'])) {
    header('Location: list_sell_return.php?error=Invalid request');
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $user_id = (int) $_SESSION['user_id'];
    $current_branch_id = get_current_branch_id();
    $tenant_id = (int) get_current_tenant_id();
    $can_switch_branch = is_super_admin() || check_permission('branches.view');
    $selected_branch_id = $current_branch_id;

    // Get return details
    $return_sql = "
        SELECT r.*, ri.product_id, ri.quantity 
        FROM returns r
        LEFT JOIN return_items ri ON r.id = ri.return_id
        WHERE r.id = ? AND r.tenant_id = ?";
    $return_params = [$return_id, $tenant_id];
    if (!$can_switch_branch) {
        $return_sql .= " AND r.branch_id = ?";
        $return_params[] = $current_branch_id;
    } elseif (!empty($_GET['branch_id'])) {
        $requested_branch_id = (int) $_GET['branch_id'];
        if ($requested_branch_id > 0) {
            $selected_branch_id = $requested_branch_id;
            $return_sql .= " AND r.branch_id = ?";
            $return_params[] = $selected_branch_id;
        }
    }
    $stmt = $pdo->prepare($return_sql);
    $stmt->execute($return_params);
    $return_data = $stmt->fetchAll();

    if (empty($return_data)) {
        throw new Exception("Return not found or you don't have permission");
    }

    $return = $return_data[0];
    $selected_branch_id = (int) ($return['branch_id'] ?? $selected_branch_id);

    // If completing the return, update inventory
    if ($status === 'completed') {
        foreach ($return_data as $item) {
            if ($item['product_id']) {
                // Update inventory
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET stock = stock + ?, updated_at = NOW()
                    WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id'], $selected_branch_id, $tenant_id]);

                // Log inventory change
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_logs 
                    (product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, tenant_id, created_at) 
                    SELECT ?, ?, stock, stock + ?, ?, ?, ?, ?, NOW()
                    FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $item['product_id'],
                    $selected_branch_id,
                    $item['quantity'],
                    $item['quantity'],
                    "Return completed #" . $return['return_number'],
                    $user_id,
                    $tenant_id,
                    $item['product_id'],
                    $selected_branch_id,
                    $tenant_id
                ]);
            }
        }

        // Update return status
        $stmt = $pdo->prepare("
            UPDATE returns 
            SET status = 'completed', approved_by = ?, updated_at = NOW()
            WHERE id = ? AND branch_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$user_id, $return_id, $selected_branch_id, $tenant_id]);

        $message = "Return completed successfully";

    } else if ($status === 'rejected') {
        // Update return status to rejected
        $notes = $return['notes'] . "\nRejection reason: " . $reject_reason;
        $stmt = $pdo->prepare("
            UPDATE returns 
            SET status = 'rejected', notes = ?, updated_at = NOW()
            WHERE id = ? AND branch_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$notes, $return_id, $selected_branch_id, $tenant_id]);

        $message = "Return rejected";
    }

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, meta, branch_id, tenant_id, created_at)
        VALUES (?, 'return_status_update', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        json_encode(['description' => "Updated return #{$return['return_number']} to {$status}", 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null]),
        $selected_branch_id,
        $tenant_id
    ]);

    $pdo->commit();

    header("Location: view_return.php?id=" . $return_id . "&success=updated&branch_id=" . $selected_branch_id);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating return status: " . $e->getMessage());
    header("Location: list_sell_return.php?error=" . urlencode($e->getMessage()));
}
