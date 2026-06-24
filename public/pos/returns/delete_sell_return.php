<?php
/**
 * Delete Return - Soft delete or void return
 * URL: http://localhost/JDH_POS/public/pos/delete_sell_return.php?id=123
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();
enforce_permission('sales.returns');

safe_require('db.php', 'src', true);

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$return_id) {
    header('Location: list_sell_return.php');
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Get return details before deletion
    $stmt = $pdo->prepare("
        SELECT r.*, s.branch_id 
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$return_id, $tenant_id]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        $_SESSION['error_message'] = "Return not found";
        header('Location: list_sell_return.php');
        exit;
    }
    
    // Check if return can be voided (only pending returns can be voided)
    if ($return['status'] !== 'pending') {
        $_SESSION['error_message'] = "Only pending returns can be voided. Current status: " . ucfirst($return['status']);
        header('Location: list_sell_return.php');
        exit;
    }
    
    // Soft delete - mark as voided
    $stmt = $pdo->prepare("
        UPDATE returns 
        SET status = 'voided', updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$return_id, $tenant_id]);
    
    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (branch_id, user_id, action, description, meta, tenant_id, created_at)
        VALUES (?, ?, 'return_voided', 'Voided return', ?, ?, NOW())
    ");
    $meta = json_encode([
        'return_id' => $return_id,
        'return_number' => $return['return_number']
    ]);
    $stmt->execute([$branch_id, $user_id, $meta, $tenant_id]);
    
    $_SESSION['success_message'] = "Return #{$return['return_number']} has been voided";
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to void return: " . $e->getMessage();
}

header('Location: list_sell_return.php');
exit;
?>