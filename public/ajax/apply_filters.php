<?php
/**
 * AJAX endpoint to apply filters to various data tables
 * Supports filtering for products, sales, customers, etc.
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get filter parameters
$filter_type = isset($_POST['filter_type']) ? trim($_POST['filter_type']) : '';
$filters = isset($_POST['filters']) ? json_decode($_POST['filters'], true) : [];
$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : get_current_branch_id();
$tenant_id = get_current_tenant_id();

if (empty($filter_type)) {
    echo json_encode([
        'success' => false,
        'message' => 'Filter type is required'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $results = [];

    switch ($filter_type) {
        case 'products':
            $query = "SELECT p.*, i.stock, c.name as category_name
                     FROM products p
                     LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE p.tenant_id = ? AND p.deleted_at IS NULL";
            $params = [$branch_id, $tenant_id];

            if (!empty($filters['category_id'])) {
                $query .= " AND p.category_id = ?";
                $params[] = (int) $filters['category_id'];
            }

            if (!empty($filters['search'])) {
                $query .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }

            if (!empty($filters['min_price'])) {
                $query .= " AND p.price >= ?";
                $params[] = (float) $filters['min_price'];
            }

            if (!empty($filters['max_price'])) {
                $query .= " AND p.price <= ?";
                $params[] = (float) $filters['max_price'];
            }

            if (!empty($filters['in_stock'])) {
                $query .= " AND i.stock > 0";
            }

            $query .= " ORDER BY p.name ASC";

            if (!empty($filters['limit'])) {
                $query .= " LIMIT ?";
                $params[] = (int) $filters['limit'];
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'sales':
            $query = "SELECT s.*, u.name as cashier_name, b.name as branch_name 
                     FROM sales s 
                     LEFT JOIN users u ON s.user_id = u.id 
                     LEFT JOIN branches b ON s.branch_id = b.id 
                     WHERE s.tenant_id = ?";
            $params = [$_SESSION['user']['tenant_id']];

            if (!empty($filters['start_date'])) {
                $query .= " AND DATE(s.created_at) >= ?";
                $params[] = $filters['start_date'];
            }

            if (!empty($filters['end_date'])) {
                $query .= " AND DATE(s.created_at) <= ?";
                $params[] = $filters['end_date'];
            }

            if (!empty($filters['payment_method'])) {
                $query .= " AND s.payment_method = ?";
                $params[] = $filters['payment_method'];
            }

            if (!empty($filters['branch_id'])) {
                $query .= " AND s.branch_id = ?";
                $params[] = (int) $filters['branch_id'];
            }

            $query .= " ORDER BY s.created_at DESC";

            if (!empty($filters['limit'])) {
                $query .= " LIMIT ?";
                $params[] = (int) $filters['limit'];
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'customers':
            $query = "SELECT * FROM customers WHERE tenant_id = ? AND deleted_at IS NULL";
            $params = [$_SESSION['user']['tenant_id']];

            if (!empty($filters['search'])) {
                $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }

            if (!empty($filters['loyalty_tier'])) {
                $query .= " AND loyalty_tier = ?";
                $params[] = $filters['loyalty_tier'];
            }

            $query .= " ORDER BY name ASC";

            if (!empty($filters['limit'])) {
                $query .= " LIMIT ?";
                $params[] = (int) $filters['limit'];
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid filter type'
            ]);
            exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
        'count' => count($results)
    ]);

} catch (Exception $e) {
    error_log("Error in apply_filters: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error applying filters'
    ]);
}
