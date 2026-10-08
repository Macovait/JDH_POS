<?php

require_once __DIR__ . '/../../../src/Security/CorsHandler.php';
// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Products API — REST endpoints for storefront product listing
 * Supports: filtering, sorting, search, pagination
 */

require_once dirname(dirname(dirname(__DIR__))) . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json');
\Jakababa\Security\apply_cors_headers();

$pdo = get_db_connection();
$tenantId = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
if (!$tenantId) {
    echo json_encode(['success' => false, 'error' => 'Tenant required']);
    exit;
}

// Validate tenant exists to prevent enumeration/data leakage
$tenantCheck = $pdo->prepare("SELECT id FROM pos_tenants WHERE id = ? AND status = 'active' LIMIT 1");
$tenantCheck->execute([$tenantId]);
if (!$tenantCheck->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Tenant not found']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Only GET allowed']);
    exit;
}

try {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $catId = isset($_GET['category']) ? (int) $_GET['category'] : 0;
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $minPrice = isset($_GET['min_price']) ? (float) $_GET['min_price'] : 0;
    $maxPrice = isset($_GET['max_price']) ? (float) $_GET['max_price'] : 0;
    $sort = in_array($_GET['sort'] ?? '', ['name_asc','name_desc','price_asc','price_desc','newest','popular']) ? $_GET['sort'] : 'newest';
    $inStockOnly = ($_GET['in_stock'] ?? '') === '1';

    // Build WHERE
    $where = ['p.tenant_id = ?', 'p.active = 1', "(p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')"];
    $params = [$tenantId];

    if ($catId > 0) {
        $where[] = "p.category_id = ?";
        $params[] = $catId;
    }
    if ($search !== '') {
        $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
        $s = '%' . $search . '%';
        $params = array_merge($params, [$s, $s, $s]);
    }
    if ($minPrice > 0) {
        $where[] = "COALESCE(NULLIF(p.selling_price, 0), p.price) >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice > 0) {
        $where[] = "COALESCE(NULLIF(p.selling_price, 0), p.price) <= ?";
        $params[] = $maxPrice;
    }

    $whereSql = implode(' AND ', $where);

    // Sort mapping
    $sortSql = match($sort) {
        'name_asc' => 'p.name ASC',
        'name_desc' => 'p.name DESC',
        'price_asc' => 'COALESCE(NULLIF(p.selling_price, 0), p.price) ASC',
        'price_desc' => 'COALESCE(NULLIF(p.selling_price, 0), p.price) DESC',
        'popular' => 'COALESCE(vs.view_count, 0) DESC',
        default => 'p.created_at DESC'
    };

    // Get products with stock
    $sql = "
        SELECT p.id, p.name, p.sku, p.price, p.selling_price, p.image, p.description,
               p.category_id, c.name as category_name,
               COALESCE(SUM(i.stock), 0) as stock_quantity,
               COALESCE(vs.view_count, 0) as view_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id AND c.tenant_id = p.tenant_id
        LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
        LEFT JOIN product_view_stats vs ON vs.product_id = p.id AND vs.tenant_id = p.tenant_id AND vs.view_date = CURDATE()
        WHERE $whereSql
        GROUP BY p.id
    ";

    if ($inStockOnly) {
        $sql .= " HAVING stock_quantity > 0";
    }

    $sql .= " ORDER BY $sortSql LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Enrich with sale flags
    foreach ($products as &$p) {
        $p['has_sale'] = !empty($p['selling_price']) && (float) $p['selling_price'] > 0 && (float) $p['selling_price'] < (float) $p['price'];
        $p['sale_price'] = $p['has_sale'] ? (float) $p['selling_price'] : (float) $p['price'];
        $p['discount_pct'] = $p['has_sale'] ? round((1 - $p['sale_price'] / (float) $p['price']) * 100) : 0;
        $p['is_low_stock'] = (int) $p['stock_quantity'] > 0 && (int) $p['stock_quantity'] <= 5;
        $p['is_out_of_stock'] = (int) $p['stock_quantity'] <= 0;
    }

    // Total count
    $countSql = "SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id WHERE $whereSql";
    if ($inStockOnly) {
        $countSql = "SELECT COUNT(*) FROM (" . str_replace(" LIMIT ? OFFSET ?", "", $sql) . ") as t WHERE stock_quantity > 0";
        // Remove the limit params
        array_pop($params); array_pop($params);
    }
    $stmt = $pdo->prepare($countSql);
    $stmt->execute(array_slice($params, 0, count($params) - ($inStockOnly ? 0 : 2)));
    $total = (int) $stmt->fetchColumn();

    // Get categories for filter sidebar
    $catStmt = $pdo->prepare("
        SELECT id, name, (SELECT COUNT(*) FROM products WHERE category_id = c.id AND tenant_id = c.tenant_id AND active = 1) as product_count
        FROM categories c
        WHERE c.tenant_id = ? AND c.status = 'active'
        ORDER BY c.name ASC
    ");
    $catStmt->execute([$tenantId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $products,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage)
        ],
        'filters' => [
            'categories' => $categories,
            'price_range' => ['min' => $minPrice, 'max' => $maxPrice]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
