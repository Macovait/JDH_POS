<?php
/**
 * AJAX endpoint to get purchase order draft
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get parameters
$draft_id = isset($_GET['draft_id']) ? (int) $_GET['draft_id'] : 0;

if ($draft_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid draft ID'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

    // Get purchase order draft
    $stmt = $pdo->prepare("
        SELECT po.*, s.name as supplier_name, s.email as supplier_email, s.phone as supplier_phone
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ? AND po.tenant_id = ? AND po.status = 'draft'
    ");
    $stmt->execute([$draft_id, $tenant_id]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        echo json_encode([
            'success' => false,
            'message' => 'Draft not found'
        ]);
        exit;
    }

    // Get draft items
    $stmt = $pdo->prepare("
        SELECT poi.*, p.name as product_name, p.sku, p.price as current_price
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.id ASC
    ");
    $stmt->execute([$draft_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }

    echo json_encode([
        'success' => true,
        'draft' => [
            'id' => (int) $draft['id'],
            'supplier_id' => (int) $draft['supplier_id'],
            'supplier_name' => $draft['supplier_name'],
            'supplier_email' => $draft['supplier_email'] ?? '',
            'supplier_phone' => $draft['supplier_phone'] ?? '',
            'reference_number' => $draft['reference_number'] ?? '',
            'notes' => $draft['notes'] ?? '',
            'status' => $draft['status'],
            'subtotal' => $subtotal,
            'tax_rate' => (float) ($draft['tax_rate'] ?? 0),
            'tax_amount' => $subtotal * (float) ($draft['tax_rate'] ?? 0) / 100,
            'total_amount' => $subtotal + ($subtotal * (float) ($draft['tax_rate'] ?? 0) / 100),
            'created_at' => $draft['created_at'],
            'updated_at' => $draft['updated_at']
        ],
        'items' => array_map(function ($item) {
            return [
                'id' => (int) $item['id'],
                'product_id' => (int) $item['product_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'current_price' => (float) ($item['current_price'] ?? 0),
                'total' => (int) $item['quantity'] * (float) $item['unit_price']
            ];
        }, $items)
    ]);

} catch (Exception $e) {
    error_log("Error in get_purchase_draft: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading purchase draft'
    ]);
}
