<?php
/**
 * Update Payment Status - AJAX endpoint
 * Re-checks a pending payment against the payments / mpesa_transactions tables
 * and marks it paid or failed as appropriate.
 *
 * POST /public/billing/update_payment_status.php
 *   payment_id  int   (required)
 *   csrf_token  string (required)
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php',      'src', true);
safe_require('db.php',        'src', true);
safe_require('functions.php', 'src', true);

// require_login() must come before any output so it can redirect if needed
require_login();

header('Content-Type: application/json');

// ── Auth & method ─────────────────────────────────────────────────────────────
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!check_permission('billing.view') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;

if ($payment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

try {
    $pdo = get_db_connection();

    // ── 1. Load the payment ───────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT p.*, s.invoice_number
        FROM payments p
        LEFT JOIN sales s ON p.sale_id = s.id
        WHERE p.id = ? AND p.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$payment_id, $tenant_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }

    // Already resolved – nothing to do
    if (in_array($payment['status'], ['paid', 'completed', 'failed'])) {
        echo json_encode([
            'success'    => true,
            'changed'    => false,
            'new_status' => $payment['status'],
            'message'    => 'Payment is already ' . $payment['status'],
        ]);
        exit;
    }

    // ── 2. Determine new status ───────────────────────────────────────────
    $new_status  = null;
    $mpesa_receipt = null;

    if (strtolower($payment['method']) === 'mpesa' && $payment['sale_id']) {
        // Check M-Pesa transactions table
        $stmt2 = $pdo->prepare("
            SELECT status, mpesa_receipt_number, result_description
            FROM mpesa_transactions
            WHERE order_id = ? AND tenant_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt2->execute([$payment['sale_id'], $tenant_id]);
        $mpesa = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($mpesa) {
            if ($mpesa['status'] === 'completed') {
                $new_status    = 'paid';
                $mpesa_receipt = $mpesa['mpesa_receipt_number'];
            } elseif ($mpesa['status'] === 'failed' || $mpesa['status'] === 'cancelled') {
                $new_status = 'failed';
            }
        }
    } else {
        // Non-M-Pesa pending payment: check the linked sale's status
        if ($payment['sale_id']) {
            $stmt3 = $pdo->prepare("SELECT status FROM sales WHERE id = ? AND tenant_id = ?");
            $stmt3->execute([$payment['sale_id'], $tenant_id]);
            $sale = $stmt3->fetch(PDO::FETCH_ASSOC);
            if ($sale && in_array($sale['status'], ['completed', 'paid'])) {
                $new_status = 'paid';
            }
        }
    }

    // ── 3. Persist if changed ─────────────────────────────────────────────
    if ($new_status && $new_status !== $payment['status']) {
        $upd = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ? AND tenant_id = ?");
        $upd->execute([$new_status, $payment_id, $tenant_id]);

        echo json_encode([
            'success'        => true,
            'changed'        => true,
            'new_status'     => $new_status,
            'mpesa_receipt'  => $mpesa_receipt,
            'invoice_number' => $payment['invoice_number'] ?? ('PMT-' . $payment_id),
            'message'        => 'Status updated to ' . $new_status,
        ]);
    } else {
        echo json_encode([
            'success'    => true,
            'changed'    => false,
            'new_status' => $payment['status'],
            'message'    => 'No change – payment is still ' . $payment['status'],
        ]);
    }

} catch (PDOException $e) {
    error_log('update_payment_status.php PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log('update_payment_status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unexpected error']);
}
