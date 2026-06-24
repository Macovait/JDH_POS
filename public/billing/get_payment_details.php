<?php
/**
 * Get Payment Details - AJAX endpoint
 * Returns full payment record, linked sale, sale items, and customer info.
 *
 * GET /public/billing/get_payment_details.php?payment_id=123
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php',   'src', true);

// require_login() must come before any output so it can redirect if needed
require_login();

header('Content-Type: application/json');

$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (!check_permission('billing.view') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

if ($payment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment ID']);
    exit;
}

try {
    $pdo = get_db_connection();

    // ── 1. Payment + linked sale + customer ──────────────────────────────
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.sale_id,
            p.method,
            p.amount,
            p.status,
            p.created_at,
            s.invoice_number,
            s.total         AS sale_total,
            s.subtotal      AS sale_subtotal,
            s.tax_amount    AS sale_tax,
            s.discount      AS sale_discount,
            s.notes         AS sale_notes,
            s.reference     AS sale_reference,
            s.status        AS sale_status,
            s.created_at    AS sale_date,
            c.id            AS customer_id,
            c.name          AS customer_name,
            c.email         AS customer_email,
            c.phone         AS customer_phone,
            c.address       AS customer_address
        FROM payments p
        LEFT JOIN sales     s ON p.sale_id    = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE p.id = ? AND p.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$payment_id, $tenant_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        exit;
    }

    // ── 2. Sale items ─────────────────────────────────────────────────────
    $items = [];
    if ($payment['sale_id']) {
        $stmt2 = $pdo->prepare("
            SELECT
                si.id,
                si.product_name,
                si.product_sku,
                si.quantity,
                si.price       AS unit_price,
                si.subtotal
            FROM sale_items si
            WHERE si.sale_id = ?
            ORDER BY si.id ASC
        ");
        $stmt2->execute([$payment['sale_id']]);
        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $items[] = [
                'id'           => (int)$row['id'],
                'product_name' => $row['product_name'],
                'sku'          => $row['product_sku'] ?? '',
                'quantity'     => (int)$row['quantity'],
                'unit_price'   => (float)$row['unit_price'],
                'subtotal'     => (float)$row['subtotal'],
            ];
        }
    }

    // ── 3. M-Pesa receipt (if applicable) ────────────────────────────────
    $mpesa = null;
    if (strtolower($payment['method']) === 'mpesa' && $payment['sale_id']) {
        $stmt3 = $pdo->prepare("
            SELECT
                mpesa_receipt_number,
                phone_number,
                status,
                result_description,
                transaction_date
            FROM mpesa_transactions
            WHERE order_id = ? AND tenant_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt3->execute([$payment['sale_id'], $tenant_id]);
        $mpesa = $stmt3->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── 4. Build response ─────────────────────────────────────────────────
    echo json_encode([
        'success' => true,
        'payment' => [
            'id'             => (int)$payment['id'],
            'sale_id'        => (int)$payment['sale_id'],
            'method'         => $payment['method'],
            'amount'         => (float)$payment['amount'],
            'amount_fmt'     => 'KSh ' . number_format((float)$payment['amount'], 2),
            'status'         => $payment['status'],
            'created_at'     => $payment['created_at'],
            'created_at_fmt' => date('M j, Y H:i:s', strtotime($payment['created_at'])),
        ],
        'sale' => $payment['sale_id'] ? [
            'id'             => (int)$payment['sale_id'],
            'invoice_number' => $payment['invoice_number'] ?? ('PMT-' . $payment['id']),
            'total'          => (float)$payment['sale_total'],
            'total_fmt'      => 'KSh ' . number_format((float)$payment['sale_total'], 2),
            'subtotal_fmt'   => 'KSh ' . number_format((float)$payment['sale_subtotal'], 2),
            'tax_fmt'        => 'KSh ' . number_format((float)$payment['sale_tax'], 2),
            'discount_fmt'   => 'KSh ' . number_format((float)$payment['sale_discount'], 2),
            'notes'          => $payment['sale_notes'] ?? '',
            'reference'      => $payment['sale_reference'] ?? '',
            'status'         => $payment['sale_status'],
            'date_fmt'       => date('M j, Y H:i', strtotime($payment['sale_date'])),
        ] : null,
        'customer' => $payment['customer_id'] ? [
            'id'      => (int)$payment['customer_id'],
            'name'    => $payment['customer_name'],
            'email'   => $payment['customer_email'] ?? '',
            'phone'   => $payment['customer_phone'] ?? '',
            'address' => $payment['customer_address'] ?? '',
        ] : null,
        'items' => $items,
        'mpesa' => $mpesa,
    ]);

} catch (PDOException $e) {
    error_log('get_payment_details.php PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log('get_payment_details.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unexpected error']);
}
