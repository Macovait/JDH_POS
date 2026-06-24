<?php
/**
 * Get customer purchase history via AJAX
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
require_login();

header('Content-Type: application/json');

$customer_id = intval($_GET['customer_id'] ?? 0);
$tenant_id = get_current_tenant_id();

if (!$customer_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Verify customer belongs to this company
    $verify = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND tenant_id = ?');
    $verify->execute([$customer_id, $tenant_id]);
    if (!$verify->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    // Check if sale_items table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'sale_items'")->fetchAll();
    $hasSaleItems = count($tables) > 0;
    
    // Get customer purchase history (sales)
    if ($hasSaleItems) {
        $stmt = $pdo->prepare('
            SELECT 
                s.id,
                s.invoice_number,
                s.total as total,
                s.created_at,
                COUNT(si.id) as items
            FROM sales s
            LEFT JOIN sale_items si ON s.id = si.sale_id
            WHERE s.customer_id = ? 
            AND s.tenant_id = ?
            AND s.deleted_at IS NULL
            GROUP BY s.id
            ORDER BY s.created_at DESC
            LIMIT 20
        ');
    } else {
        // Fallback without sale_items count
        $stmt = $pdo->prepare('
            SELECT 
                s.id,
                s.invoice_number,
                s.total as total,
                s.created_at,
                0 as items
            FROM sales s
            WHERE s.customer_id = ? 
            AND s.tenant_id = ?
            AND s.deleted_at IS NULL
            ORDER BY s.created_at DESC
            LIMIT 20
        ');
    }
    $stmt->execute([$customer_id, $tenant_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    foreach ($history as &$sale) {
        $sale['items'] = intval($sale['items']);
        $sale['total'] = floatval($sale['total']);
    }
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching customer history: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching history: ' . $e->getMessage()
    ]);
}
?>
