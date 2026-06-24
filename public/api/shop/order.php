<?php
/**
 * Shop Order API — place an order from the customer-facing storefront
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF verification
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$tenant_id = (int) ($input['tenant_id'] ?? 0);
$customer_name = trim($input['customer_name'] ?? '');
$customer_phone = trim($input['customer_phone'] ?? '');
$customer_email = trim($input['customer_email'] ?? '');
$delivery_address = trim($input['delivery_address'] ?? '');
$payment_method = trim($input['payment_method'] ?? 'cash');
$items = $input['items'] ?? [];
$total_input = (float) ($input['total'] ?? 0);

if ($tenant_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid store']);
    exit;
}

// Validate tenant exists to prevent order spam against non-existent tenants
$tenantCheck = $pdo->prepare("SELECT id FROM pos_tenants WHERE id = ? LIMIT 1");
$tenantCheck->execute([$tenant_id]);
if (!$tenantCheck->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Store not found']);
    exit;
}
if (empty($customer_name) || empty($customer_phone)) {
    echo json_encode(['success' => false, 'error' => 'Name and phone are required']);
    exit;
}
if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty']);
    exit;
}

// Validate stock and calculate total
$calculated_total = 0;
$validated_items = [];
try {
    foreach ($items as $item) {
        $product_id = (int) ($item['product_id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $name = trim($item['name'] ?? '');
        if ($product_id <= 0 || $qty <= 0) continue;

        // Check current stock via inventory table
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(stock), 0) as total_stock FROM inventory WHERE product_id = ? AND tenant_id = ?");
        $stmt->execute([$product_id, $tenant_id]);
        $stock = (int) $stmt->fetchColumn();
        if ($stock < $qty) {
            echo json_encode(['success' => false, 'error' => "Insufficient stock for {$name} (available: {$stock})"]);
            exit;
        }
        $calculated_total += $price * $qty;
        $validated_items[] = ['product_id' => $product_id, 'name' => $name, 'price' => $price, 'quantity' => $qty];
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Stock check failed']);
    exit;
}

if (empty($validated_items)) {
    echo json_encode(['success' => false, 'error' => 'No valid items in cart']);
    exit;
}

// Use calculated total for security (ignore client-sent total)
$final_total = $calculated_total;

// Insert order
$has_table = false;
try {
    $pdo->query("SELECT 1 FROM online_orders LIMIT 1");
    $has_table = true;
} catch (Exception $e) {
    $has_table = false;
}

if (!$has_table) {
    echo json_encode(['success' => false, 'error' => 'Online orders are not set up yet']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Create order record
    $stmt = $pdo->prepare("
        INSERT INTO online_orders (tenant_id, customer_name, customer_phone, customer_email, delivery_address, payment_method, total, status, items_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([
        $tenant_id,
        $customer_name,
        $customer_phone,
        $customer_email,
        $delivery_address,
        $payment_method,
        $final_total,
        json_encode($validated_items)
    ]);
    $order_id = (int) $pdo->lastInsertId();

    // 2. Decrement stock via inventory table
    // Determine target branch (default branch or first available)
    $target_branch = 0;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'default_branch_id' LIMIT 1");
        $stmt->execute([$tenant_id]);
        $target_branch = (int) $stmt->fetchColumn();
    } catch (Exception $e) {}
    if ($target_branch <= 0) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
            $stmt->execute([$tenant_id]);
            $target_branch = (int) $stmt->fetchColumn();
        } catch (Exception $e) {}
    }
    if ($target_branch > 0) {
        foreach ($validated_items as $vi) {
            $stmt = $pdo->prepare("UPDATE inventory SET stock = GREATEST(stock - ?, 0) WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
            $stmt->execute([$vi['quantity'], $vi['product_id'], $target_branch, $tenant_id]);
        }
    }

    $pdo->commit();

    // 3. Send notification email to store owner (non-fatal)
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'company_email' LIMIT 1");
        $stmt->execute([$tenant_id]);
        $owner_email = $stmt->fetchColumn() ?: '';
        if ($owner_email) {
            $subject = "New Online Order #{$order_id} — {$customer_name}";
            $body = "A new online order has been placed.\n\nOrder: #{$order_id}\nCustomer: {$customer_name}\nPhone: {$customer_phone}\nEmail: {$customer_email}\nAddress: {$delivery_address}\nTotal: {$final_total}\nPayment: {$payment_method}\n\nItems:\n";
            foreach ($validated_items as $vi) {
                $body .= "- {$vi['name']} x{$vi['quantity']} @ {$vi['price']}\n";
            }
            $headers = "From: Online Store <noreply@jakababa.com>\r\n";
            @mail($owner_email, $subject, $body, $headers);
        }
    } catch (Exception $e) {
        error_log("Shop order notification error: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Order placed successfully']);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Shop order error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not place order. Please try again.']);
    exit;
}
