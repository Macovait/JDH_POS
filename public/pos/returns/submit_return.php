<?php
/**
 * Submit Return - Return Management System
 * POST handler for return submission
 */

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

if (!check_permission('sales.returns') && !is_super_admin()) {
    enforce_permission('sales.returns');
}

safe_require('db.php', 'src', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: select_sale_for_return.php');
    exit;
}

$errors = [];

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    $errors[] = 'Invalid CSRF token';
}

$sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
if ($sale_id <= 0) {
    $errors[] = 'Invalid sale ID';
}

$return_items = $_POST['return_items'] ?? '';
$return_items_array = json_decode($return_items, true);
if (empty($return_items_array) || !is_array($return_items_array)) {
    $errors[] = 'No items selected for return';
}

$reason = trim($_POST['reason'] ?? '');
if (empty($reason)) {
    $errors[] = 'Return reason is required';
}

$notes = trim($_POST['notes'] ?? '');
$total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

if (empty($errors)) {
    try {
        $pdo = get_db_connection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id, tenant_id, branch_id, customer_id FROM sales WHERE id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
        $stmt->execute([$sale_id, $tenant_id, $branch_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            throw new Exception('Sale not found');
        }
        
        $return_number = generateReturnNumber($pdo, $tenant_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO returns (tenant_id, branch_id, return_number, sale_id, customer_id, processed_by, amount, reason, notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $tenant_id,
            $branch_id,
            $return_number,
            $sale_id,
            $sale['customer_id'],
            $user_id,
            $total_amount,
            $reason,
            $notes
        ]);
        $return_id = $pdo->lastInsertId();
        
        foreach ($return_items_array as $sale_item_id => $quantity) {
            $sale_item_id = (int)$sale_item_id;
            $quantity = (int)$quantity;
            
            if ($quantity <= 0) continue;
            
            $stmt = $pdo->prepare("SELECT id, product_id, product_name, quantity, price as unit_price FROM sale_items WHERE id = ? AND sale_id = ?");
            $stmt->execute([$sale_item_id, $sale_id]);
            $sale_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sale_item) continue;
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as returned_qty FROM return_items WHERE sale_item_id = ? AND deleted_at IS NULL");
            $stmt->execute([$sale_item_id]);
            $already_returned = (int)$stmt->fetchColumn();
            
            $available_qty = $sale_item['quantity'] - $already_returned;
            $actual_qty = min($quantity, $available_qty);
            
            if ($actual_qty <= 0) continue;
            
            $unit_price = $sale_item['unit_price'];
            $subtotal = $actual_qty * $unit_price;
            
            $stmt = $pdo->prepare("
                INSERT INTO return_items (tenant_id, return_id, sale_item_id, product_id, product_name, quantity, unit_price, subtotal, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id,
                $return_id,
                $sale_item_id,
                $sale_item['product_id'],
                $sale_item['product_name'],
                $actual_qty,
                $unit_price,
                $subtotal,
                $reason
            ]);
        }
        
        $stmt = $pdo->prepare("UPDATE returns SET amount = (SELECT COALESCE(SUM(subtotal), 0) FROM return_items WHERE return_id = ? AND tenant_id = ?) WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$return_id, $tenant_id, $return_id, $tenant_id]);
        
        $pdo->commit();
        
        header('Location: view_return.php?id=' . $return_id . '&success=1');
        exit;
        
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Return submission error: ' . $e->getMessage());
        $errors[] = 'An error occurred while processing your return';
    }
}

if (!empty($errors)) {
    $error_msg = implode(', ', $errors);
    header('Location: process_return.php?sale_id=' . $sale_id . '&error=' . urlencode($error_msg));
    exit;
}

function generateReturnNumber($pdo, $tenant_id = null) {
    $date_part = date('Ymd');
    $tenant_filter = $tenant_id ? ' AND tenant_id = ?' : '';
    do {
        $random_part = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $return_number = 'R' . $date_part . '-' . $random_part;
        $params = [$return_number];
        if ($tenant_filter) $params[] = $tenant_id;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM returns WHERE return_number = ?{$tenant_filter}");
        $stmt->execute($params);
        $exists = (int)$stmt->fetchColumn();
    } while ($exists > 0);
    
    return $return_number;
}