<?php
/**
 * Products API - dynamic feed for POS
 * Returns JSON; signals demo_mode when schema missing
 */

$root = dirname(__DIR__);
require_once $root . '/src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();
if (!check_permission('pos.view') && !check_permission('pos.checkout')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}
header('Content-Type: application/json');

$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed', 'demo_mode' => true]);
    exit;
}

function table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function has_col(PDO $pdo, string $table, string $col): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$col]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

if (!table_exists($pdo, 'products')) {
    echo json_encode(['success' => false, 'error' => 'Products table missing', 'demo_mode' => true]);
    exit;
}

$tenant_id    = (int) get_current_tenant_id();
$branch_id     = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : (int) get_current_branch_id();
$business_type = get_current_business_type($tenant_id);
$page          = max(1, (int) ($_GET['page'] ?? 1));
$limit         = min(100, max(1, (int) ($_GET['limit'] ?? 60)));
$offset        = ($page - 1) * $limit;
$search        = trim($_GET['search'] ?? '');
$category_id   = isset($_GET['category_id']) && $_GET['category_id'] !== 'all' ? (int) $_GET['category_id'] : null;

$has_company = has_col($pdo, 'products', 'tenant_id');
$has_branch  = has_col($pdo, 'products', 'branch_id');
$has_bt      = has_col($pdo, 'products', 'business_type');
$has_inventory = table_exists($pdo, 'inventory');

$where = [];
$params = [$branch_id];
if ($has_company && $tenant_id > 0) {
    $where[] = 'p.tenant_id = :tenant_id';
    $params[':tenant_id'] = $tenant_id;
}
if ($has_branch && $branch_id > 0) {
    $where[] = '(p.branch_id = :branch_id OR p.branch_id IS NULL)';
    $params[':branch_id'] = $branch_id;
}
if ($has_bt && $business_type) {
    $where[] = '(p.business_type = :bt OR p.business_type IS NULL OR p.business_type = "general")';
    $params[':bt'] = $business_type;
}
if ($search !== '') {
    $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($category_id) {
    $where[] = 'p.category_id = :category_id';
    $params[':category_id'] = $category_id;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'demo_mode' => true]);
    exit;
}

$sql = "
    SELECT 
        p.id, p.name, p.price, p.category_id,
        COALESCE(i.stock, 0) AS stock,
        p.sku, p.barcode, p.image,
        c.name AS category_name, c.icon AS category_icon, c.color AS category_color
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ? AND i.tenant_id = p.tenant_id
    $whereSql
    ORDER BY p.name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'     => true,
    'products'    => $products,
    'total'       => $total,
    'total_pages' => $limit ? max(1, (int) ceil($total / $limit)) : 1,
    'page'        => $page,
    'limit'       => $limit,
    'demo_mode'   => false
]);

