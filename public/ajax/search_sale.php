<?php
// /JDH_POS/public/ajax/search_sale.php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();

header('Content-Type: application/json');

$pdo = get_db_connection();
$invoice = $_GET['invoice'] ?? '';
$requested_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$tenant_id = get_current_tenant_id();

// Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
$branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
if ($branch_id <= 0 && $requested_branch_id > 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
    exit;
}

if (empty($invoice)) {
    echo json_encode(['success' => false, 'error' => 'Invoice number required']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT s.id, s.invoice_number, s.total, s.created_at,
               c.name as customer_name, c.phone as customer_phone
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.invoice_number LIKE ? AND s.tenant_id = ? AND s.branch_id = ?
        AND s.status = 'completed' AND s.voided = 0
        LIMIT 1
    ");
    $stmt->execute(["%$invoice%", $tenant_id, $branch_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sale) {
        echo json_encode(['success' => true, 'sale' => $sale]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Sale not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>