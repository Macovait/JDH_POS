<?php
/**
 * PROCESS SALE - AJAX endpoint for POS
 * Version: 7.0 - Enhanced security, performance, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection with token rotation
 * - Comprehensive input validation
 * - Transaction with rollback on error
 * - Stock management with audit logging
 * - Multi-payment support (split payments)
 * - Loyalty points integration
 * - Kitchen Order Ticket (KOT) support
 * - Prescription handling for pharmacy
 * - Warranty tracking
 * - Cache invalidation
 * - Activity logging
 */

// ============================================
// ERROR HANDLING
// ============================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/pos_errors.log');

// Disable default output buffering
@ini_set('output_buffering', 1);

// ============================================
// SESSION START
// ============================================
if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH', '/');
}

session_name('jakababa_saas_sid');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// AUTHENTICATION & PERMISSION CHECK
// ============================================
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('Events/HookManager.php', 'src', false);

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    sendErrorResponse('Unauthorized. Please log in.', 'unauthorized', 401);
}

if (!check_permission('sales.create') && !is_super_admin()) {
    sendErrorResponse('Permission denied. You do not have access to process sales.', 'forbidden', 403);
}

// ============================================
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// FUNCTION: Send Error Response
// ============================================
function sendErrorResponse($message, $errorCode = 'unknown_error', $statusCode = 400, $details = null) {
    $response = [
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    sendJsonResponse($response, $statusCode);
}

// ============================================
// FUNCTION: Check Table/Column Exists (Cached)
// ============================================
$tableCache = [];
$columnCache = [];

function tableExists($pdo, $table) {
    global $tableCache;
    
    if (isset($tableCache[$table])) {
        return $tableCache[$table];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table]);
        $exists = $stmt->fetch() !== false;
        $tableCache[$table] = $exists;
        return $exists;
    } catch (Exception $e) {
        return false;
    }
}

function columnExists($pdo, $table, $column) {
    global $columnCache;
    $key = $table . '.' . $column;
    
    if (isset($columnCache[$key])) {
        return $columnCache[$key];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        $exists = $stmt->fetch() !== false;
        $columnCache[$key] = $exists;
        return $exists;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// FUNCTION: Generate Invoice Number
// ============================================
function generateInvoiceNumber($pdo, $tenantId, $branchId) {
    try {
        $year = date('Y');
        $month = date('m');
        
        // Ensure sequences table exists
        if (!tableExists($pdo, 'invoice_sequences')) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `invoice_sequences` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) NOT NULL,
                    `branch_id` int(11) NOT NULL,
                    `year` int(4) NOT NULL,
                    `month` int(2) NOT NULL,
                    `last_number` int(11) NOT NULL DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_sequence` (`tenant_id`,`branch_id`,`year`,`month`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Get next sequence number
        $stmt = $pdo->prepare("
            INSERT INTO invoice_sequences (tenant_id, branch_id, year, month, last_number, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = NOW()
        ");
        $stmt->execute([$tenantId, $branchId, $year, $month]);
        
        // Get the updated value
        $stmt = $pdo->prepare("
            SELECT last_number FROM invoice_sequences 
            WHERE tenant_id = ? AND branch_id = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$tenantId, $branchId, $year, $month]);
        $seq = $stmt->fetch(PDO::FETCH_ASSOC);
        $number = str_pad($seq['last_number'], 4, '0', STR_PAD_LEFT);
        
        return "INV-{$year}{$month}-{$number}";
    } catch (Exception $e) {
        error_log("Error generating invoice number: " . $e->getMessage());
        return "INV-" . date('YmdHis') . "-" . rand(100, 999);
    }
}

// ============================================
// FUNCTION: Log Activity
// ============================================
function logActivity($action, $description, $data = [], $userId = null, $tenantId = null, $branchId = null) {
    try {
        require_once __DIR__ . '/../../src/db.php';
        $pdo = get_db_connection();
        
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $meta = json_encode(['data' => $data, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return $stmt->execute([$userId, $action, $description, $meta, $branchId, $tenantId, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================
try {
    // === 1. Validate Request Method ===
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Method not allowed. Use POST.', 'method_not_allowed', 405);
    }
    
    // === 2. Get POST Data ===
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    // === 3. Validate Session ===
    $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $tenantId = $_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0;
    $branchId = $_SESSION['branch_id'] ?? 0;
    $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    
    // Session tamper detection
    $sessionTenant = $_SESSION['tenant_id'] ?? null;
    $sessionUser = $_SESSION['user_id'] ?? null;
    
    if (($sessionTenant && (int)$sessionTenant !== $tenantId) ||
        ($sessionUser && (int)$sessionUser !== $userId)) {
        error_log("ZERO-TRUST VIOLATION: Session mismatch in process_sale.php");
        sendErrorResponse('Session validation failed', 'session_mismatch', 401);
    }
    
    if ($userId <= 0 || $tenantId <= 0) {
        sendErrorResponse('Session expired. Please log in again.', 'session_expired', 401);
    }
    
    // === 4. CSRF Protection ===
    $submittedToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!verify_csrf_token($submittedToken)) {
        error_log("CSRF_VIOLATION: Token mismatch for user {$userId}, tenant {$tenantId}");
        sendErrorResponse('Invalid security token. Please refresh the page.', 'csrf_invalid', 403);
    }
    
    // Generate new CSRF token for next request (rotation)
    $newCsrfToken = generate_csrf_token();
    
    // === 5. Database Connection ===
    require_once __DIR__ . '/../../src/db.php';
    $pdo = get_db_connection();
    
    if (!$pdo) {
        sendErrorResponse('Database connection failed', 'db_connection_error', 500);
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // === 6. Extract and Validate Input ===
    $branchIdInput = isset($input['branch_id']) ? (int) $input['branch_id'] : $branchId;
    if ($branchIdInput <= 0) {
        sendErrorResponse('Branch is required for checkout.', 'branch_required', 400);
    }
    $branchId = $branchIdInput;
    
    // Verify branch belongs to tenant
    $stmt = $pdo->prepare("SELECT id, name, is_active FROM branches WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$branchId, $tenantId]);
    $branch = $stmt->fetch();
    if (!$branch) {
        sendErrorResponse("Branch #{$branchId} does not belong to your company.", 'branch_invalid', 403);
    }
    if (!$branch['is_active']) {
        sendErrorResponse("Branch '{$branch['name']}' is currently inactive.", 'branch_inactive', 403);
    }
    
    // === 7. Validate Required Fields ===
    $items = $input['items'] ?? null;
    if (empty($items) && !empty($input['items_json'])) {
        $items = json_decode($input['items_json'], true);
    }
    
    if (!is_array($items) || empty($items)) {
        sendErrorResponse('No items in cart.', 'empty_cart', 400);
    }
    
    // Validate each item
    foreach ($items as $index => $item) {
        if (!isset($item['product_id']) || !isset($item['quantity'])) {
            sendErrorResponse("Invalid item data at position " . ($index + 1), 'invalid_item', 400);
        }
        if ((int)$item['quantity'] <= 0) {
            sendErrorResponse("Invalid quantity for item at position " . ($index + 1), 'invalid_quantity', 400);
        }
    }
    
    // === 8. Extract All Fields ===
    $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
    $customerName = trim($input['customer_name'] ?? 'Walk-in Customer');
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $subtotal = (float) ($input['subtotal'] ?? 0);
    $discount = (float) ($input['discount'] ?? 0);
    $tax = (float) ($input['tax'] ?? 0);
    $total = (float) ($input['total'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    $orderType = $input['order_type'] ?? 'dine-in';
    
    // Split payments
    $splitPayments = [];
    if ($paymentMethod === 'split' && !empty($input['split_payments'])) {
        $splitPayments = is_array($input['split_payments']) ? $input['split_payments'] : json_decode($input['split_payments'], true);
        if (!is_array($splitPayments)) $splitPayments = [];
    }
    
    // Voucher
    $voucherCode = trim($input['voucher_code'] ?? '');
    $voucherId = !empty($input['voucher_id']) ? (int) $input['voucher_id'] : null;
    
    // Discount
    $discountId = !empty($input['discount_id']) ? (int) $input['discount_id'] : null;
    $discountValue = (float) ($input['discount_value'] ?? 0);
    $discountType = $input['discount_type'] ?? 'fixed';
    
    // Loyalty
    $loyaltyPointsRedeemed = (int) ($input['loyalty_points_redeemed'] ?? 0);
    $loyaltyDiscount = (float) ($input['loyalty_discount'] ?? 0);
    
    // Business Type
    $btIdInput = isset($input['bt_id']) ? (int) $input['bt_id'] : 0;
    $btCodeInput = trim($input['business_type'] ?? '');
    
    if ($btIdInput <= 0 && empty($btCodeInput)) {
        // Get from branch
        $stmt = $pdo->prepare("SELECT bt.id, bt.code FROM branches b LEFT JOIN business_types bt ON bt.id = b.business_type_id WHERE b.id = ? AND b.business_type_id > 0 LIMIT 1");
        $stmt->execute([$branchId]);
        $btRow = $stmt->fetch();
        if ($btRow) {
            $btIdInput = (int) $btRow['id'];
            $btCodeInput = $btRow['code'];
        }
    }
    
    // Payment amounts
    $amountReceived = isset($input['amount_received']) ? (float) $input['amount_received'] : $total;
    $changeGiven = max(0, $amountReceived - $total);
    
    // Vertical features
    $appointmentId = (int) ($input['appointment_id'] ?? 0);
    $roomNumber = trim($input['room_number'] ?? '');
    $guestName = trim($input['guest_name'] ?? '');
    $serialNumber = trim($input['serial_number'] ?? '');
    $warrantyMonths = trim($input['warranty_months'] ?? '');
    $staffId = (int) ($input['staff_id'] ?? 0);
    $batchNumber = trim($input['batch_number'] ?? '');
    $weightKg = (float) ($input['weight'] ?? $input['weight_kg'] ?? 0);
    $ageVerified = (int) ($input['age_verified'] ?? 0);
    $ageVerifyId = trim($input['age_verify_id'] ?? '');
    $kitchenNotes = trim($input['kitchen_notes'] ?? '');
    $tableNumber = trim($input['table_number'] ?? '');
    $prescriptionEnabled = !empty($input['prescription_enabled']) && $input['prescription_enabled'] != '0';
    $prescriptionRef = trim($input['prescription_ref'] ?? '');
    $prescriptionDoctor = trim($input['prescription_doctor'] ?? '');
    $prescriptionNotes = trim($input['prescription_notes'] ?? '');
    
    // === 9. Validate Total ===
    $calculatedTotal = $subtotal - $discount + $tax;
    if (abs($total - $calculatedTotal) > 0.01) {
        error_log("Total mismatch: sent={$total}, calculated={$calculatedTotal}");
        // Use calculated value instead
        $total = $calculatedTotal;
    }
    
    // === 10. Start Transaction ===
    $pdo->beginTransaction();
    
    // === 11. Product Validation & Stock Check ===
    $productIds = array_column($items, 'product_id');
    $quantityMap = [];
    foreach ($items as $item) {
        $pid = (int) $item['product_id'];
        $qty = (int) $item['quantity'];
        $quantityMap[$pid] = ($quantityMap[$pid] ?? 0) + $qty;
    }
    
    $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.name, p.price, p.sku, p.manage_stock,
            COALESCE(i.stock, 999) as stock,
            COALESCE(i.reorder_level, 0) as reorder_level
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        WHERE p.id IN ({$productPlaceholders}) AND p.tenant_id = ?
        AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
    ");
    $stmt->execute(array_merge([$branchId, $tenantId], $productIds, [$tenantId]));
    
    $productsData = [];
    $stockErrors = [];
    $productPrices = [];
    $productNames = [];
    
    while ($row = $stmt->fetch()) {
        $pid = (int) $row['id'];
        $productsData[$pid] = $row;
        $productNames[$pid] = $row['name'];
        $productPrices[$pid] = (float) $row['price'];
        
        // Check stock if manage_stock is enabled
        if ($row['manage_stock'] && $row['manage_stock'] != '0') {
            $currentStock = (int) $row['stock'];
            $requestedQty = $quantityMap[$pid] ?? 0;
            
            if ($currentStock < $requestedQty) {
                $stockErrors[] = "{$row['name']} (SKU: {$row['sku']}) - Available: {$currentStock}, Requested: {$requestedQty}";
            }
        }
    }
    
    // Check for missing products
    foreach ($productIds as $pid) {
        if (!isset($productsData[$pid])) {
            $stockErrors[] = "Product #{$pid} not found in your catalog.";
        }
    }
    
    if (!empty($stockErrors)) {
        $pdo->rollBack();
        sendErrorResponse('Insufficient stock for some items', 'stock_error', 400, $stockErrors);
    }
    
    // === 12. Handle Customer ===
    $finalCustomerId = $customerId;
    
    if (!$finalCustomerId && !empty($customerName) && $customerName !== 'Walk-in Customer') {
        // Check if customer exists by name
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$customerName, $tenantId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $finalCustomerId = (int) $existing['id'];
        } else {
            // Create new customer
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, tenant_id, branch_id, loyalty_points, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$customerName, $tenantId, $branchId]);
            $finalCustomerId = (int) $pdo->lastInsertId();
            
            if ($finalCustomerId <= 0) {
                throw new Exception('Failed to create customer record');
            }
        }
    }
    
    // === 13. Generate Invoice Number ===
    $invoiceNumber = generateInvoiceNumber($pdo, $tenantId, $branchId);
    
    // === 14. Insert Sale ===
    $saleFields = ['branch_id', 'user_id', 'customer_id', 'tenant_id', 'invoice_number', 
                    'total', 'discount', 'tax', 'subtotal', 'payment_method', 'status', 
                    'created_at', 'notes', 'order_type'];
    $salePlaceholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', "'completed'", 'NOW()', '?', '?' ];
    $saleParams = [$branchId, $userId, $finalCustomerId, $tenantId, $invoiceNumber, 
                   $total, $discount, $tax, $subtotal, $paymentMethod, 
                   $notes, $orderType];
    
    // Add business type columns if they exist
    if (columnExists($pdo, 'sales', 'business_type')) {
        $saleFields[] = 'business_type';
        $salePlaceholders[] = '?';
        $saleParams[] = $btCodeInput ?: 'retail';
    }
    
    if (columnExists($pdo, 'sales', 'business_type_id')) {
        $saleFields[] = 'business_type_id';
        $salePlaceholders[] = '?';
        $saleParams[] = $btIdInput > 0 ? $btIdInput : null;
    }
    
    // Add vertical feature columns if present
    $verticalFields = [
        'appointment_id' => $appointmentId,
        'room_number' => $roomNumber,
        'guest_name' => $guestName,
        'staff_id' => $staffId,
        'batch_number' => $batchNumber,
        'weight_kg' => $weightKg,
        'age_verified' => $ageVerified,
        'age_verify_id' => $ageVerifyId,
        'amount_received' => $amountReceived,
        'change_given' => $changeGiven
    ];
    
    foreach ($verticalFields as $field => $value) {
        if (columnExists($pdo, 'sales', $field)) {
            $saleFields[] = $field;
            $salePlaceholders[] = '?';
            $saleParams[] = $value;
        }
    }
    
    // Build and execute sale insert
    $sql = "INSERT INTO sales (" . implode(', ', $saleFields) . ") VALUES (" . implode(', ', $salePlaceholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($saleParams);
    $saleId = (int) $pdo->lastInsertId();
    
    if ($saleId <= 0) {
        throw new Exception('Failed to create sale record');
    }
    
    // === 15. Insert Payments ===
    if ($paymentMethod === 'split' && !empty($splitPayments)) {
        foreach ($splitPayments as $split) {
            $method = $split['method'] ?? 'cash';
            $amount = (float) ($split['amount'] ?? 0);
            $reference = trim($split['reference'] ?? '');
            
            if ($amount > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO payments (sale_id, method, amount, reference, status, created_at, tenant_id)
                    VALUES (?, ?, ?, ?, 'paid', NOW(), ?)
                ");
                $stmt->execute([$saleId, $method, $amount, $reference, $tenantId]);
            }
        }
    } else {
        // Single payment
        $stmt = $pdo->prepare("
            INSERT INTO payments (sale_id, method, amount, status, created_at, tenant_id)
            VALUES (?, ?, ?, 'paid', NOW(), ?)
        ");
        $stmt->execute([$saleId, $paymentMethod, $total, $tenantId]);
    }
    
    // === 16. Insert Sale Items & Update Inventory ===
    $saleItemIds = [];
    foreach ($items as $item) {
        $productId = (int) $item['product_id'];
        $quantity = (int) $item['quantity'];
        $price = isset($item['price']) ? (float) $item['price'] : ($productPrices[$productId] ?? 0);
        $subtotalItem = $price * $quantity;
        $productName = $productNames[$productId] ?? "Product #{$productId}";
        
        $itemFields = ['sale_id', 'product_id', 'product_name', 'quantity', 'price', 'subtotal', 'tenant_id'];
        $itemPlaceholders = ['?', '?', '?', '?', '?', '?', '?'];
        $itemParams = [$saleId, $productId, $productName, $quantity, $price, $subtotalItem, $tenantId];
        
        // Add optional item fields
        if (!empty($item['batch_number'])) {
            $itemFields[] = 'batch_number';
            $itemPlaceholders[] = '?';
            $itemParams[] = $item['batch_number'];
        }
        
        if (!empty($item['serial_number'])) {
            $itemFields[] = 'serial_number';
            $itemPlaceholders[] = '?';
            $itemParams[] = $item['serial_number'];
        }
        
        if (!empty($item['expiry_date'])) {
            $itemFields[] = 'expiry_date';
            $itemPlaceholders[] = '?';
            $itemParams[] = $item['expiry_date'];
        }
        
        if (isset($item['weight_kg']) && $item['weight_kg'] > 0) {
            $itemFields[] = 'weight_kg';
            $itemPlaceholders[] = '?';
            $itemParams[] = $item['weight_kg'];
        }
        
        if (!empty($item['kitchen_notes'])) {
            $itemFields[] = 'kitchen_notes';
            $itemPlaceholders[] = '?';
            $itemParams[] = $item['kitchen_notes'];
        }
        
        if (isset($item['original_price']) && $item['original_price'] != $price) {
            $itemFields[] = 'original_price';
            $itemPlaceholders[] = '?';
            $itemParams[] = $item['original_price'];
        }
        
        // Insert sale item
        $sql = "INSERT INTO sale_items (" . implode(', ', $itemFields) . ") VALUES (" . implode(', ', $itemPlaceholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($itemParams);
        $saleItemId = (int) $pdo->lastInsertId();
        $saleItemIds[] = $saleItemId;
        
        // Update inventory
        $stmt = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
        $stmt->execute([$productId, $branchId, $tenantId]);
        $current = $stmt->fetch();
        $oldStock = $current ? (int) $current['stock'] : 0;
        $newStock = max(0, $oldStock - $quantity);
        
        $stmt = $pdo->prepare("
            INSERT INTO inventory (product_id, branch_id, stock, tenant_id, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE stock = VALUES(stock), updated_at = NOW()
        ");
        $stmt->execute([$productId, $branchId, $newStock, $tenantId]);
        
        // Log inventory movement
        $stmt = $pdo->prepare("
            INSERT INTO inventory_logs (product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, tenant_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $productId, $branchId, $oldStock, $newStock, -$quantity,
            "Sale - Invoice: {$invoiceNumber}", $userId, $tenantId
        ]);
        
        // Log stock movement
        if (tableExists($pdo, 'stock_movements')) {
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements (branch_id, product_id, movement_type, quantity_change, quantity_before, quantity_after, reference_table, reference_id, user_id, tenant_id, created_at)
                VALUES (?, ?, 'sale', ?, ?, ?, 'sales', ?, ?, ?, NOW())
            ");
            $stmt->execute([$branchId, $productId, -$quantity, $oldStock, $newStock, $saleId, $userId, $tenantId]);
        }
    }
    
    // === 17. Handle Voucher ===
    if ($voucherId > 0 || !empty($voucherCode)) {
        $voucherWhere = $voucherId > 0 ? "id = ?" : "code = ?";
        $voucherParam = $voucherId > 0 ? $voucherId : $voucherCode;
        
        $stmt = $pdo->prepare("
            SELECT id, usage_limit, usage_count FROM vouchers 
            WHERE {$voucherWhere} AND tenant_id = ? AND active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())
            LIMIT 1
        ");
        $stmt->execute([$voucherParam, $tenantId]);
        $voucher = $stmt->fetch();
        
        if ($voucher && ($voucher['usage_limit'] == 0 || $voucher['usage_count'] < $voucher['usage_limit'])) {
            if (tableExists($pdo, 'voucher_redemptions')) {
                $stmt = $pdo->prepare("
                    INSERT INTO voucher_redemptions (voucher_id, sale_id, tenant_id, redeemed_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$voucher['id'], $saleId, $tenantId]);
            }
            
            $stmt = $pdo->prepare("UPDATE vouchers SET usage_count = usage_count + 1 WHERE id = ?");
            $stmt->execute([$voucher['id']]);
        }
    }
    
    // === 18. Handle Discount ===
    if ($discountId > 0) {
        if (tableExists($pdo, 'discount_usage')) {
            $stmt = $pdo->prepare("
                INSERT INTO discount_usage (discount_id, sale_id, customer_id, tenant_id, used_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$discountId, $saleId, $finalCustomerId ?: null, $tenantId]);
        }
        
        $stmt = $pdo->prepare("UPDATE discounts SET usage_count = usage_count + 1 WHERE id = ?");
        $stmt->execute([$discountId]);
    }
    
    // === 19. Handle Loyalty Points ===
    $pointsEarned = $finalCustomerId ? (int) floor($total / 100) : 0;
    
    if ($finalCustomerId) {
        // Deduct redeemed points
        if ($loyaltyPointsRedeemed > 0) {
            $stmt = $pdo->prepare("
                UPDATE customers SET loyalty_points = COALESCE(loyalty_points, 0) - ? 
                WHERE id = ? AND tenant_id = ? AND COALESCE(loyalty_points, 0) >= ?
            ");
            $stmt->execute([$loyaltyPointsRedeemed, $finalCustomerId, $tenantId, $loyaltyPointsRedeemed]);
        }
        
        // Add earned points
        if ($pointsEarned > 0) {
            $stmt = $pdo->prepare("
                UPDATE customers SET 
                    loyalty_points = COALESCE(loyalty_points, 0) + ?,
                    total_spent = COALESCE(total_spent, 0) + ?,
                    last_purchase = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$pointsEarned, $total, $finalCustomerId, $tenantId]);
        }
        
        // Log loyalty points
        if ($loyaltyPointsRedeemed > 0 || $pointsEarned > 0) {
            if (tableExists($pdo, 'loyalty_points_log')) {
                if ($loyaltyPointsRedeemed > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO loyalty_points_log (customer_id, points_change, reason, created_by, tenant_id, created_at)
                        VALUES (?, ?, 'Redeemed on sale #' || ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$finalCustomerId, -$loyaltyPointsRedeemed, $saleId, $userId, $tenantId]);
                }
                
                if ($pointsEarned > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO loyalty_points_log (customer_id, points_change, reason, created_by, tenant_id, created_at)
                        VALUES (?, ?, 'Earned on sale #' || ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$finalCustomerId, $pointsEarned, $saleId, $userId, $tenantId]);
                }
            }
        }
    }
    
    // === 20. Kitchen Order Ticket (KOT) ===
    $isRestaurantLike = in_array($btCodeInput, ['restaurant', 'cafe', 'bakery'], true);
    $needsKot = $isRestaurantLike || !empty($kitchenNotes) || !empty($tableNumber);
    
    if ($needsKot && tableExists($pdo, 'kitchen_orders') && tableExists($pdo, 'kitchen_order_items')) {
        try {
            $koFields = ['branch_id', 'sale_id', 'table_number', 'order_type', 'status', 'priority', 'notes', 'created_at', 'tenant_id'];
            $koPlaceholders = ['?', '?', '?', '?', "'pending'", '0', '?', 'NOW()', '?'];
            $koParams = [$branchId, $saleId, $tableNumber ?: null, $orderType, $kitchenNotes ?: null, $tenantId];
            
            $koSql = "INSERT INTO kitchen_orders (" . implode(',', $koFields) . ") VALUES (" . implode(',', $koPlaceholders) . ")";
            $stmt = $pdo->prepare($koSql);
            $stmt->execute($koParams);
            $kitchenOrderId = (int) $pdo->lastInsertId();
            
            if ($kitchenOrderId > 0) {
                $stmt = $pdo->prepare("SELECT id, product_id, quantity, product_name FROM sale_items WHERE sale_id = ?");
                $stmt->execute([$saleId]);
                $saleItemsRows = $stmt->fetchAll();
                
                foreach ($saleItemsRows as $sir) {
                    $qty = (int) $sir['quantity'];
                    $pname = $sir['product_name'] ?? $productNames[(int)$sir['product_id']] ?? "Product #" . $sir['product_id'];
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO kitchen_order_items 
                        (tenant_id, kitchen_order_id, sale_item_id, product_name, quantity, notes, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([$tenantId, $kitchenOrderId, (int)$sir['id'], $pname, $qty, $kitchenNotes ?: null]);
                }
            }
        } catch (Exception $e) {
            error_log("KOT creation failed (non-fatal): " . $e->getMessage());
        }
    }
    
    // === 21. Handle Prescription (Pharmacy) ===
    if ($btCodeInput === 'pharmacy' && ($prescriptionEnabled || !empty($prescriptionRef))) {
        if (tableExists($pdo, 'prescriptions')) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO prescriptions
                    (tenant_id, branch_id, sale_id, customer_id, prescription_ref, doctor_name, notes, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'dispensed', NOW())
                ");
                $stmt->execute([
                    $tenantId, $branchId, $saleId, $finalCustomerId,
                    $prescriptionRef ?: null,
                    $prescriptionDoctor ?: null,
                    $prescriptionNotes ?: null
                ]);
            } catch (Exception $e) {
                error_log("Prescription creation failed (non-fatal): " . $e->getMessage());
            }
        }
    }
    
    // === 22. Handle Warranty ===
    if (!empty($serialNumber) && !empty($warrantyMonths) && !empty($saleItemIds)) {
        if (tableExists($pdo, 'warranties')) {
            try {
                $warrantyEnd = date('Y-m-d', strtotime("+{$warrantyMonths} months"));
                $stmt = $pdo->prepare("
                    INSERT INTO warranties 
                    (sale_id, sale_item_id, product_id, customer_id, serial_number, warranty_months, start_date, end_date, status, tenant_id)
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'active', ?)
                ");
                $stmt->execute([
                    $saleId, 
                    $saleItemIds[0], 
                    $productIds[0] ?? null, 
                    $finalCustomerId, 
                    $serialNumber, 
                    $warrantyMonths, 
                    $warrantyEnd, 
                    $tenantId
                ]);
            } catch (Exception $e) {
                error_log("Warranty creation failed (non-fatal): " . $e->getMessage());
            }
        }
    }
    
    // === 23. Link Appointment ===
    if ($appointmentId > 0 && tableExists($pdo, 'appointments')) {
        try {
            if (columnExists($pdo, 'appointments', 'sale_id')) {
                $stmt = $pdo->prepare("UPDATE appointments SET sale_id = ?, status = 'completed' WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$saleId, $appointmentId, $tenantId]);
            }
        } catch (Exception $e) {
            error_log("Appointment link failed (non-fatal): " . $e->getMessage());
        }
    }
    
    // === 24. Commit Transaction ===
    $pdo->commit();

    // === 25. Clear Cache ===
    try {
        $cacheDir = __DIR__ . '/../../storage/cache/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.cache');
            foreach ($files as $file) {
                $filename = basename($file);
                if (strpos($filename, md5("products_{$tenantId}_{$branchId}")) !== false ||
                    strpos($filename, md5("categories_{$tenantId}")) !== false) {
                    @unlink($file);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Cache clear error after sale: " . $e->getMessage());
    }
    
    // === 26. Log Activity ===
    logActivity(
        'sale.completed',
        "Sale #{$saleId} completed for " . number_format($total, 2),
        ['sale_id' => $saleId, 'total' => $total, 'items' => count($items)],
        $userId,
        $tenantId,
        $branchId
    );
    
    // === 27. Build Response ===
    $receiptItems = [];
    foreach ($items as $item) {
        $productId = (int) $item['product_id'];
        $name = $productNames[$productId] ?? $item['product_name'] ?? "Product #$productId";
        $price = $productPrices[$productId] ?? (float) ($item['price'] ?? 0);
        $qty = (int) $item['quantity'];
        $receiptItems[] = [
            'name' => $name,
            'quantity' => $qty,
            'price' => $price,
            'subtotal' => $price * $qty,
            'notes' => $item['kitchen_notes'] ?? ''
        ];
    }
    
    $response = [
        'success' => true,
        'sale_id' => $saleId,
        'invoice_number' => $invoiceNumber,
        'total' => $total,
        'items_count' => count($items),
        'points_earned' => $pointsEarned,
        'points_redeemed' => $loyaltyPointsRedeemed,
        'csrf_token' => $newCsrfToken,
        'receipt' => [
            'invoice_number' => $invoiceNumber,
            'date' => date('Y-m-d H:i:s'),
            'customer_name' => $customerName,
            'items' => $receiptItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'loyalty_discount' => $loyaltyDiscount,
            'tax' => $tax,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'split_payments' => $splitPayments,
            'amount_received' => $amountReceived,
            'change_given' => $changeGiven,
            'points_earned' => $pointsEarned,
            'points_redeemed' => $loyaltyPointsRedeemed
        ],
        'redirect' => false,
        'message' => 'Sale completed successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Plugin hook: allow plugins to modify response or trigger side effects
    if (function_exists('do_action')) {
        do_action('pos.after_sale', $saleId, [
            'tenant_id'      => $tenantId,
            'branch_id'      => $branchId,
            'user_id'        => $userId,
            'total'          => $total,
            'items'          => $items,
            'customer_id'    => $finalCustomerId,
            'payment_method' => $paymentMethod,
            'invoice_number' => $invoiceNumber,
            'points_earned'  => $pointsEarned,
            'discount'       => $discount,
            'tax'            => $tax,
            'response'       => &$response
        ]);
    }

    sendJsonResponse($response);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in process_sale.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    $errorMessage = 'Database error occurred. Please try again.';
    
    // Provide more specific error messages
    if (strpos($e->getMessage(), 'inventory_logs') !== false) {
        $errorMessage = 'Database error: inventory_logs table issue. Please contact support.';
    } elseif (strpos($e->getMessage(), 'stock_movements') !== false) {
        $errorMessage = 'Database error: stock_movements table issue. Please contact support.';
    } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'Duplicate entry error. Please refresh and try again.';
    } elseif (strpos($e->getMessage(), 'foreign key') !== false) {
        $errorMessage = 'Database constraint error. Please check your data.';
    }
    
    sendErrorResponse($errorMessage, 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in process_sale.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in process_sale.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}