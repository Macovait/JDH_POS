<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

$tenant_id = 1;
$branch_id = 1;

// Test 1: Simple products count
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE tenant_id = 1");
echo "Products count: " . $stmt->fetchColumn() . "\n";

// Test 2: Main query (simplified)
$sql = "SELECT p.id, p.name, p.sku, p.price, p.cost_price, p.image, p.category_id, p.description, p.business_type_id, COALESCE(i.stock, 0) as stock, c.name as category_name, c.color as category_color, c.icon as category_icon FROM products p LEFT JOIN categories c ON p.category_id = c.id AND c.tenant_id = ? AND c.status = 'active' LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ? WHERE p.tenant_id = ? AND p.active = 1 ORDER BY p.name ASC LIMIT 10 OFFSET 0";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tenant_id, $branch_id, $tenant_id, $tenant_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Products fetched: " . count($products) . "\n";

// Test 3: Category query
$cat_sql = "SELECT c.id, c.name, c.color, c.icon, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id AND p.tenant_id = ? AND p.active = 1 WHERE c.tenant_id = ? AND c.status = 'active' GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC";
$stmt = $pdo->prepare($cat_sql);
$stmt->execute([$tenant_id, $tenant_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Categories fetched: " . count($categories) . "\n";

echo "All tests passed!\n";
