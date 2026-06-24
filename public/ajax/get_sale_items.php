<?php
/**
 * Get Sale Items - AJAX endpoint for fetching sale items
 * URL: http://localhost/JDH_POS/public/ajax/get_sale_items.php?sale_id=123
 */

// Suppress ALL error output before ANYTHING else for JSON API
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);

// Start fresh output buffer
while (ob_get_level()) @ob_end_clean();
ob_start();

// Register fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) @ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message']]);
        exit;
    }
});

try {
    require_once __DIR__ . '/../../src/paths.php';
    safe_require('auth.php', 'src', true);
    safe_require('db.php', 'src', true);
    safe_require('functions.php', 'src', true);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Load error: ' . $e->getMessage()]);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Permission check
if (!is_super_admin() && !check_permission('sales.returns')) {
    echo json_encode([
        'success' => false, 
        'error' => 'Permission denied. You do not have access to returns.'
    ]);
    exit;
}

// Get parameters
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$branch_id = get_current_branch_id();
$tenant_id = get_current_tenant_id();

// Validate sale ID
if ($sale_id <= 0) {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid sale ID. Please provide a valid sale ID.'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    
    // First, verify the sale exists and belongs to this tenant/branch
    $stmt = $pdo->prepare("
        SELECT id, invoice_number, total, created_at, status 
        FROM sales 
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$sale_id, $tenant_id, $branch_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        echo json_encode([
            'success' => false, 
            'error' => 'Sale not found or you do not have access to this sale.'
        ]);
        exit;
    }
    
    // Check if sale is completed (can't return from draft/voided sales)
    if ($sale['status'] !== 'completed') {
        echo json_encode([
            'success' => false, 
            'error' => 'This sale is not completed. Only completed sales can be returned.',
            'sale_status' => $sale['status']
        ]);
        exit;
    }
    
    // Fetch sale items with return information
    $stmt = $pdo->prepare("
        SELECT 
            si.id, 
            si.product_id, 
            si.product_name, 
            si.quantity, 
            si.price as unit_price, 
            si.subtotal,
            COALESCE(SUM(ri.quantity), 0) as returned_quantity,
            (si.quantity - COALESCE(SUM(ri.quantity), 0)) as available_qty,
            p.sku,
            p.image
        FROM sale_items si
        LEFT JOIN return_items ri ON ri.sale_item_id = si.id
        LEFT JOIN returns r ON ri.return_id = r.id AND r.status != 'rejected'
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        GROUP BY si.id
        ORDER BY si.id ASC
    ");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build response
    $result = [
        'success' => true,
        'sale_id' => $sale_id,
        'sale_info' => [
            'invoice_number' => $sale['invoice_number'],
            'total' => (float)$sale['total'],
            'total_formatted' => number_format((float)$sale['total'], 2),
            'date' => $sale['created_at'],
            'date_formatted' => date('d M Y H:i', strtotime($sale['created_at'])),
            'status' => $sale['status']
        ],
        'items' => [],
        'summary' => [
            'total_items' => 0,
            'total_quantity' => 0,
            'total_returned' => 0,
            'total_available' => 0,
            'total_returnable_value' => 0
        ]
    ];
    
    $total_returnable_value = 0;
    
    foreach ($items as $item) {
        $available_qty = max(0, (int)$item['available_qty']);
        $unit_price = (float)$item['unit_price'];
        $returnable_value = $available_qty * $unit_price;
        $total_returnable_value += $returnable_value;
        
        $result['items'][] = [
            'id' => (int)$item['id'],
            'product_id' => (int)$item['product_id'],
            'product_name' => htmlspecialchars($item['product_name']),
            'sku' => htmlspecialchars($item['sku'] ?? 'N/A'),
            'quantity' => (int)$item['quantity'],
            'returned_quantity' => (int)$item['returned_quantity'],
            'available_qty' => $available_qty,
            'unit_price' => $unit_price,
            'unit_price_formatted' => number_format($unit_price, 2),
            'subtotal' => (float)$item['subtotal'],
            'subtotal_formatted' => number_format((float)$item['subtotal'], 2),
            'returnable_value' => $returnable_value,
            'returnable_value_formatted' => number_format($returnable_value, 2),
            'is_fully_returned' => $available_qty <= 0,
            'has_image' => !empty($item['image']),
            'image_url' => $item['image'] ? base_url($item['image']) : null
        ];
        
        $result['summary']['total_items']++;
        $result['summary']['total_quantity'] += (int)$item['quantity'];
        $result['summary']['total_returned'] += (int)$item['returned_quantity'];
        $result['summary']['total_available'] += $available_qty;
    }
    
    $result['summary']['total_returnable_value'] = $total_returnable_value;
    $result['summary']['total_returnable_value_formatted'] = number_format($total_returnable_value, 2);
    $result['summary']['return_percentage'] = $result['summary']['total_quantity'] > 0 
        ? round(($result['summary']['total_returned'] / $result['summary']['total_quantity']) * 100, 1) 
        : 0;
    
    // Check if sale is fully returned
    $result['is_fully_returned'] = $result['summary']['total_available'] <= 0;
    $result['has_returnable_items'] = $result['summary']['total_available'] > 0;
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    error_log("get_sale_items.php PDO Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred while fetching sale items.'
    ]);
} catch (Exception $e) {
    error_log("get_sale_items.php Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'An unexpected error occurred.'
    ]);
}
?>