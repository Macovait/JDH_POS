<?php
/**
 * AJAX endpoint to save purchase order
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
    exit;
}

$supplier_id = isset($input['supplier_id']) ? (int) $input['supplier_id'] : 0;
$items = isset($input['items']) ? $input['items'] : [];
$notes = isset($input['notes']) ? trim($input['notes']) : '';
$reference_number = isset($input['reference_number']) ? trim($input['reference_number']) : '';
$tax_rate = isset($input['tax_rate']) ? (float) $input['tax_rate'] : 0;
$status = isset($input['status']) ? trim($input['status']) : 'draft';
$purchase_order_id = isset($input['purchase_order_id']) ? (int) $input['purchase_order_id'] : 0;

if ($supplier_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Supplier is required'
    ]);
    exit;
}

if (empty($items)) {
    echo json_encode([
        'success' => false,
        'message' => 'At least one item is required'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];
    $user_id = $_SESSION['user']['id'];
    $branch_id = get_current_branch_id();

    // Verify supplier belongs to company
    $stmt = $pdo->prepare("
        SELECT id FROM suppliers 
        WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$supplier_id, $tenant_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found'
        ]);
        exit;
    }

    // Calculate total
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    $tax_amount = $subtotal * $tax_rate / 100;
    $total_amount = $subtotal + $tax_amount;

    if ($purchase_order_id > 0) {
        // Update existing purchase order
        $stmt = $pdo->prepare("
            UPDATE purchase_orders 
            SET supplier_id = ?, notes = ?, 
                total = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ? AND status = 'draft'
        ");
        $stmt->execute([
            $supplier_id,
            $notes,
            $total_amount,
            $status,
            $purchase_order_id,
            $tenant_id
        ]);

        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Purchase order not found or cannot be modified'
            ]);
            exit;
        }

        // Delete existing items
        $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ? AND tenant_id = ?");
        $stmt->execute([$purchase_order_id, $tenant_id]);

    } else {
        // Create new purchase order
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (tenant_id, branch_id, supplier_id, created_by, 
                notes, total, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenant_id,
            $branch_id,
            $supplier_id,
            $user_id,
            $notes,
            $total_amount,
            $status
        ]);
        $purchase_order_id = $pdo->lastInsertId();
    }

    // Insert items
    $stmt = $pdo->prepare("
        INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, cost_price, received_quantity, tenant_id)
        VALUES (?, ?, ?, ?, 0, ?)
    ");

    foreach ($items as $item) {
        $product_id = (int) $item['product_id'];
        $quantity = (int) $item['quantity'];
        $unit_price = (float) $item['unit_price'];
        $total_price = $quantity * $unit_price;

        $stmt->execute([$purchase_order_id, $product_id, $quantity, $unit_price, $tenant_id]);
    }

    // Log activity
    if (function_exists('log_activity')) {
        log_activity($user_id, 'save_purchase_order', [
            'purchase_order_id' => $purchase_order_id, 'supplier_id' => $supplier_id,
            'total_amount' => $total_amount,
            'item_count' => count($items),
            'status' => $status
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order saved successfully',
        'purchase_order_id' => $purchase_order_id,
        'total_amount' => $total_amount
    ]);

} catch (Exception $e) {
    error_log("Error in save_purchase: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving purchase order'
    ]);
}
