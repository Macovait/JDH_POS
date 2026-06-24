<?php
declare(strict_types=1);

/**
 * GDPR Data Export Endpoint
 * Returns all personal data for a tenant/user in JSON format.
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

session_start();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
$tenantId = $_SESSION['tenant_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$tenantId || !$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = get_db_connection();

// Collect all tenant data
$export = [
    'meta' => [
        'exported_at' => date('c'),
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'format_version' => '1.0'
    ],
    'company' => null,
    'users' => [],
    'customers' => [],
    'sales' => [],
    'audit_logs' => [],
    'subscriptions' => [],
    'feature_flags' => []
];

// Company info
$stmt = $pdo->prepare("SELECT * FROM companies WHERE tenant_id = ? AND branch_id = $current_branch_id LIMIT 1");
$stmt->execute([$tenantId]);
$export['company'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Users
$stmt = $pdo->prepare("SELECT id, name, email, phone, role, created_at, last_login_at FROM users WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$export['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Customers
$stmt = $pdo->prepare("SELECT id, name, email, phone, address, created_at FROM customers WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$export['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sales (anonymized if needed)
$stmt = $pdo->prepare("
    SELECT s.id, s.invoice_number, s.total, s.payment_method, s.created_at,
           si.product_name, si.quantity, si.price
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE s.tenant_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10000
");
$stmt->execute([$tenantId]);
$export['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Audit logs
$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE tenant_id = ? AND branch_id = $current_branch_id ORDER BY created_at DESC LIMIT 5000");
$stmt->execute([$tenantId]);
$export['audit_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Subscriptions
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$export['subscriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Feature flags
$stmt = $pdo->prepare("SELECT * FROM feature_flags WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$export['feature_flags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($export, JSON_PRETTY_PRINT);
