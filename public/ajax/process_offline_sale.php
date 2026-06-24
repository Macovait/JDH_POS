<?php
/**
 * Process Offline Sale
 * Handles sales that were queued while offline
 * Prevents duplicate sales using offline_id tracking
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Security check
require_login();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['offline_sale']) || empty($input['offline_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid offline sale data']);
        exit;
    }
    
    $offline_id = $input['offline_id'];
    $sale_data = $input['sale_data'];
    
    // Check for duplicate (already synced)
    $stmt = $pdo->prepare("
        SELECT id, receipt_number 
        FROM sales 
        WHERE offline_id = ? AND tenant_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$offline_id, $tenant_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode([
            'success' => true,
            'sale_id' => $existing['id'],
            'receipt_number' => $existing['receipt_number'],
            'message' => 'Sale already synced'
        ]);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Generate receipt number
    $receipt_number = generate_receipt_number($pdo, $tenant_id);
    
    // Insert sale
    $stmt = $pdo->prepare("
        INSERT INTO sales (
            tenant_id, user_id, branch_id, customer_id, 
            receipt_number, subtotal, tax_amount, discount_amount,
            final_amount, payment_method, payment_status, status,
            offline_id, notes, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $tenant_id,
        $user_id,
        $sale_data['branch_id'] ?? $_SESSION['branch_id'] ?? 1,
        $sale_data['customer_id'] ?? null,
        $receipt_number,
        $sale_data['subtotal'] ?? 0,
        $sale_data['tax_amount'] ?? 0,
        $sale_data['discount_amount'] ?? 0,
        $sale_data['final_amount'] ?? 0,
        $sale_data['payment_method'] ?? 'cash',
        'paid',
        'completed',
        $offline_id,
        $sale_data['notes'] ?? 'Offline sale synced'
    ]);
    
    $sale_id = $pdo->lastInsertId();
    
    // Insert sale items
    if (!empty($sale_data['items']) && is_array($sale_data['items'])) {
        $itemStmt = $pdo->prepare("
            INSERT INTO sale_items (
                sale_id, product_id, product_name, quantity,
                unit_price, total_price, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($sale_data['items'] as $item) {
            $itemStmt->execute([
                $sale_id,
                $item['product_id'] ?? null,
                $item['product_name'] ?? 'Unknown Product',
                $item['quantity'] ?? 1,
                $item['unit_price'] ?? 0,
                $item['total_price'] ?? 0
            ]);
            
            // Update inventory
            if (!empty($item['product_id'])) {
                $updateStmt = $pdo->prepare("
                    UPDATE inventory 
                    SET stock = stock - ?, updated_at = NOW()
                    WHERE product_id = ? AND branch_id = ?
                ");
                $updateStmt->execute([
                    $item['quantity'] ?? 1,
                    $item['product_id'],
                    $sale_data['branch_id'] ?? $_SESSION['branch_id'] ?? 1
                ]);
            }
        }
    }
    
    // Insert payment record
    $paymentStmt = $pdo->prepare("
        INSERT INTO payments (
            tenant_id, sale_id, amount, payment_method,
            payment_status, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $paymentStmt->execute([
        $tenant_id,
        $sale_id,
        $sale_data['final_amount'] ?? 0,
        $sale_data['payment_method'] ?? 'cash',
        'completed'
    ]);
    
    // Log the sync
    log_security_event('offline_sale_synced', [
        'sale_id' => $sale_id,
        'offline_id' => $offline_id,
        'receipt_number' => $receipt_number,
        'amount' => $sale_data['final_amount'] ?? 0,
        'user_id' => $user_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'sale_id' => $sale_id,
        'receipt_number' => $receipt_number,
        'message' => 'Offline sale synced successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Offline sale sync failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to sync offline sale',
        'message' => $e->getMessage()
    ]);
}

function generate_receipt_number($pdo, $tenant_id) {
    $prefix = 'RCP';
    $date = date('Ymd');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM sales 
        WHERE tenant_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $count = $stmt->fetchColumn();
    
    $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    return "{$prefix}-{$date}-{$sequence}";
}
?>
