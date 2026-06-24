<?php
/**
 * Categories API - returns categories filtered by business_type
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

function table_exists_cat(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function has_col_cat(PDO $pdo, string $table, string $col): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$col]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

if (!table_exists_cat($pdo, 'categories')) {
    echo json_encode(['success' => false, 'error' => 'Categories table missing', 'demo_mode' => true]);
    exit;
}

$tenant_id    = (int) get_current_tenant_id();
$business_type = get_current_business_type($tenant_id);

$has_company = has_col_cat($pdo, 'categories', 'tenant_id');
$has_bt      = has_col_cat($pdo, 'categories', 'business_type');

$where = [];
$params = [];
if ($has_company && $tenant_id > 0) {
    $where[] = 'tenant_id = :tenant_id';
    $params[':tenant_id'] = $tenant_id;
}
if ($has_bt && $business_type) {
    $where[] = '(business_type = :bt OR business_type IS NULL OR business_type = "general")';
    $params[':bt'] = $business_type;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT id, name, icon, color, business_type
    FROM categories
    $whereSql
    ORDER BY sort_order ASC, name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'    => true,
    'categories' => $categories,
    'demo_mode'  => false
]);

