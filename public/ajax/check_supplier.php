<?php
/**
 * AJAX endpoint to check supplier information
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
$supplier_id = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

if ($supplier_id <= 0 && empty($email) && empty($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'Supplier ID, email, or phone is required'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = $_SESSION['user']['tenant_id'];

    // Build query based on provided parameters
    $query = "SELECT * FROM suppliers WHERE tenant_id = ? AND deleted_at IS NULL";
    $params = [$tenant_id];

    if ($supplier_id > 0) {
        $query .= " AND id = ?";
        $params[] = $supplier_id;
    } elseif (!empty($email)) {
        $query .= " AND email = ?";
        $params[] = $email;
    } elseif (!empty($phone)) {
        $query .= " AND phone = ?";
        $params[] = $phone;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($supplier) {
        // Get supplier's product count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as product_count 
            FROM products 
            WHERE supplier_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$supplier['id']]);
        $product_count = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get supplier's total purchases
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total_purchases
            FROM purchase_orders 
            WHERE supplier_id = ? AND status = 'received'
        ");
        $stmt->execute([$supplier['id']]);
        $purchases = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'found' => true,
            'supplier' => [
                'id' => (int) $supplier['id'],
                'name' => $supplier['name'],
                'email' => $supplier['email'] ?? '',
                'phone' => $supplier['phone'] ?? '',
                'address' => $supplier['address'] ?? '',
                'contact_person' => $supplier['contact_person'] ?? '',
                'status' => $supplier['status'] ?? 'active',
                'product_count' => (int) ($product_count['product_count'] ?? 0),
                'total_purchases' => (float) ($purchases['total_purchases'] ?? 0)
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => 'Supplier not found'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in check_supplier: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking supplier'
    ]);
}
