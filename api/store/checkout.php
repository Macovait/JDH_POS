<?php
/**
 * Store Checkout API
 * Creates online orders from cart data.
 */
require_once __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$tenantId = (int) ($input['tenant_id'] ?? 0);
$items = $input['items'] ?? [];
$customer = $input['customer'] ?? [];
$paymentMethod = $input['payment_method'] ?? 'cod';
if (!$tenantId || empty($items)) { http_response_code(400); echo json_encode(['error'=>'Missing tenant or items']); exit; }
$pdo = get_db_connection();
$pdo->beginTransaction();
try {
    $total = 0;
    foreach ($items as $item) {
        $stmt = $pdo->prepare("SELECT price, stock_quantity FROM products WHERE id = ? AND tenant_id = ? AND status = 'active' FOR UPDATE");
        $stmt->execute([(int)$item['id'], $tenantId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product || $product['stock_quantity'] < $item['qty']) throw new Exception('Insufficient stock for product ' . $item['id']);
        $total += $product['price'] * $item['qty'];
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND tenant_id = ?")->execute([$item['qty'], $item['id'], $tenantId]);
    }
    $stmt = $pdo->prepare("INSERT INTO online_orders (tenant_id, customer_name, customer_email, customer_phone, total, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$tenantId, $customer['name'] ?? '', $customer['email'] ?? '', $customer['phone'] ?? '', $total, $paymentMethod]);
    $orderId = (int) $pdo->lastInsertId();
    $lineStmt = $pdo->prepare("INSERT INTO online_order_items (order_id, product_id, product_name, qty, price, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    foreach ($items as $item) { $lineStmt->execute([$orderId, $item['id'], $item['name'], $item['qty'], $item['price']]); }
    $pdo->commit();
    echo json_encode(['success'=>true,'order_id'=>$orderId,'total'=>$total]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
