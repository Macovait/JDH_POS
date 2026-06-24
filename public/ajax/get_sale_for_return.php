<?php
/**
 * Advanced AJAX endpoint - get sale details for return processing
 * Matches actual database schema: sale_items.price, users.no tenant_id, warranties.sale_item_id
 */

// Suppress ALL error output before ANYTHING else for JSON API
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);

// Start fresh output buffer
while (ob_get_level()) @ob_end_clean();
ob_start();

// Register fatal error handler FIRST
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

// Custom error handler (after files are loaded)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "Error [$errno]: $errstr"]);
    exit;
});

if (!is_super_admin() && !check_permission('sales.returns')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();
    $requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
    $sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

    // Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
    $branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
    if ($branch_id <= 0 && $requested_branch_id > 0) {
        echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
        exit;
    }

    if ($sale_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid sale ID']);
        exit;
    }

    // Currency
    $currency = 'KSh';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ? LIMIT 1");
        $stmt->execute([$tenant_id]);
        $curr = $stmt->fetchColumn();
        if ($curr) $currency = $curr;
    } catch (Exception $e) {}

    // Get sale
    $stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.phone as customer_phone
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ? AND s.tenant_id = ? AND s.branch_id = ?");
    $stmt->execute([$sale_id, $tenant_id, $branch_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
        exit;
    }

    if (($sale['status'] ?? '') !== 'completed') {
        echo json_encode(['success' => false, 'error' => 'Only completed sales can be returned. Status: ' . ($sale['status'] ?? 'unknown')]);
        exit;
    }

    // Get items - LEFT JOIN products so deleted products don't break the query
    $stmt = $pdo->prepare("SELECT si.id, si.product_id, si.product_name, si.quantity, si.price,
            si.subtotal, si.weight, si.unit, si.batch_number, si.serial_number,
            COALESCE(SUM(ri.quantity), 0) as returned_quantity,
            (si.quantity - COALESCE(SUM(ri.quantity), 0)) as available_qty,
            p.name as product_name_full, p.sku, p.barcode, p.image
        FROM sale_items si
        LEFT JOIN return_items ri ON ri.sale_item_id = si.id AND ri.deleted_at IS NULL
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        GROUP BY si.id
        ORDER BY si.id");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => false, 'error' => 'No items found in this sale. Data may be corrupted or already fully returned.']);
        exit;
    }

    // Format items
    $processedItems = [];
    $returnedQtyMap = [];
    $totalReturnedQty = 0;
    $totalAvailableQty = 0;

    foreach ($items as $it) {
        $returnedQty = (int)($it['returned_quantity'] ?? 0);
        $availableQty = max(0, (int)($it['available_qty'] ?? 0));
        $unitPrice = (float)($it['price'] ?? 0);
        $subtotal = (float)($it['subtotal'] ?? 0);

        $returnedQtyMap[$it['id']] = $returnedQty;
        $totalReturnedQty += $returnedQty;
        $totalAvailableQty += $availableQty;

        $processedItems[] = [
            'id' => (int)$it['id'],
            'product_id' => (int)$it['product_id'],
            'product_name' => $it['product_name_full'] ?: $it['product_name'],
            'sku' => $it['sku'] ?? '',
            'barcode' => $it['barcode'] ?? '',
            'sold_quantity' => (int)$it['quantity'],
            'returned_quantity' => $returnedQty,
            'available_qty' => $availableQty,
            'is_fully_returned' => $availableQty <= 0,
            'unit_price' => $unitPrice,
            'unit_price_formatted' => $currency . ' ' . number_format($unitPrice, 0),
            'subtotal' => $subtotal,
            'subtotal_formatted' => $currency . ' ' . number_format($subtotal, 0),
            'weight' => (float)($it['weight'] ?? 0),
            'unit' => $it['unit'] ?? 'pcs',
            'batch_number' => $it['batch_number'] ?? null,
            'serial_number' => $it['serial_number'] ?? null,
            'image_url' => $it['image'] ? '/JDH_POS/public/' . $it['image'] : null
        ];
    }

    // Cashier info - users table has NO tenant_id
    $cashier_name = 'Unknown';
    if ($sale['user_id']) {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND status = 1 LIMIT 1");
        $stmt->execute([$sale['user_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) $cashier_name = $u['name'];
    }

    // Return policy settings
    $returnPolicyDays = 30;
    $restockingFee = 0;
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ('return_policy_days', 'restocking_fee_percent')");
        $stmt->execute([$tenant_id]);
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $returnPolicyDays = (int)($settings['return_policy_days'] ?? 30);
        $restockingFee = (float)($settings['restocking_fee_percent'] ?? 0);
    } catch (Exception $e) {}

    $daysSinceSale = (int)floor((time() - strtotime($sale['created_at'])) / 86400);
    $isWithinPeriod = $daysSinceSale <= $returnPolicyDays;

    echo json_encode([
        'success' => true,
        'sale' => [
            'id' => (int)$sale['id'],
            'invoice_number' => $sale['invoice_number'],
            'total' => (float)$sale['total'],
            'total_formatted' => $currency . ' ' . number_format((float)$sale['total'], 0),
            'created_at' => $sale['created_at'],
            'date_formatted' => date('M d, Y', strtotime($sale['created_at'])),
            'time_formatted' => date('h:i A', strtotime($sale['created_at'])),
            'days_since_sale' => $daysSinceSale,
            'return_policy_days' => $returnPolicyDays,
            'is_within_return_period' => $isWithinPeriod,
            'payment_method' => $sale['payment_method'] ?? 'Cash',
            'customer_name' => $sale['customer_name'] ?: 'Walk-in Customer',
            'customer_phone' => $sale['customer_phone'] ?: '',
            'cashier_name' => $cashier_name
        ],
        'items' => $processedItems,
        'returned_quantities' => $returnedQtyMap,
        'currency' => $currency,
        'can_process_return' => $totalAvailableQty > 0,
        'message' => $totalAvailableQty > 0 ? 'Select items to return' : 'All items have been returned',
        'stats' => [
            'total_items' => count($processedItems),
            'returnable_items' => $totalAvailableQty,
            'returned_items_count' => $totalReturnedQty
        ]
    ]);

} catch (PDOException $e) {
    error_log("get_sale_for_return PDO Error [" . $e->getCode() . "]: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred. Please try again.', 'code' => $e->getCode()]);
} catch (Exception $e) {
    error_log("get_sale_for_return Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred.']);
}

// Flush output buffer
if (ob_get_level()) ob_end_flush();
