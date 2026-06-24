<?php
/**
 * Get Customer Consent List
 * Returns list of customers with their SMS consent status
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json');

require_login();

// ZERO-TRUST: Reject tenant_id override attempts
if (!empty($_GET['tenant_id'])) {
    error_log("ZERO-TRUST VIOLATION: tenant_id override in get_customer_consent.php");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$tenant_id = get_current_tenant_id();

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $pdo = get_db_connection();
    
    // Get customers with consent status
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.phone,
            COALESCE(sc.opted_in, 1) as opted_in
        FROM customers c
        LEFT JOIN sms_customer_consent sc ON c.id = sc.customer_id AND sc.tenant_id = ?
        WHERE c.tenant_id = ? AND c.phone IS NOT NULL AND c.phone != '' AND c.status = 1
        ORDER BY c.name ASC
        LIMIT 100
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $customers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'customers' => $customers
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
