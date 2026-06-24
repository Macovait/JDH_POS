<?php
/**
 * AJAX endpoint to delete a purchase order item
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get parameters
$item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
$purchase_order_id = isset($_POST['purchase_order_id']) ? (int) $_POST['purchase_order_id'] : 0;

if ($item_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid item ID'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

    // Verify the item belongs to the company
    $stmt = $pdo->prepare("
        SELECT poi.*, po.tenant_id, po.status
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.purchase_order_id = po.id
        WHERE poi.id = ? AND po.tenant_id = ?
    ");
    $stmt->execute([$item_id, $tenant_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode([
            'success' => false,
            'message' => 'Purchase item not found'
        ]);
        exit;
    }

    // Check if purchase order can be modified
    if ($item['status'] !== 'draft' && $item['status'] !== 'pending') {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete items from a ' . $item['status'] . ' purchase order'
        ]);
        exit;
    }

    // Delete the item (scoped to tenant via verified purchase order)
    $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE id = ? AND purchase_order_id = ?");
    $stmt->execute([$item_id, $item['purchase_order_id']]);

    // Update purchase order total
    $stmt = $pdo->prepare("
        UPDATE purchase_orders 
        SET total_amount = (
            SELECT COALESCE(SUM(quantity * unit_price), 0) 
            FROM purchase_order_items 
            WHERE purchase_order_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$item['purchase_order_id'], $item['purchase_order_id']]);

    // Log activity
    if (function_exists('log_activity')) {
        log_activity($_SESSION['user']['id'], 'delete_purchase_item', [
            'item_id' => $item_id, 'purchase_order_id' => $item['purchase_order_id'],
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity']
        ], get_current_tenant_id());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Purchase item deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in delete_purchase_item: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting purchase item'
    ]);
}
