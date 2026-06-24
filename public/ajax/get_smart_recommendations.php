<?php
/**
 * AI-Powered Smart Recommendations API
 * 
 * Provides intelligent product recommendations based on:
 * - Current cart contents
 * - Customer purchase history
 * - Popular combinations
 * - Seasonal trends
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('db.php', 'src', true);

require_login();

if (!check_permission('pos.access') && !check_permission('pos.sell') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$cart_products = $input['cart_products'] ?? [];
$customer_id = $input['customer_id'] ?? null;
$branch_id = $input['branch_id'] ?? 0;
$company_id = $input['company_id'] ?? 0;
$limit = min(10, intval($input['limit'] ?? 6));

$session_tenant_id = get_current_tenant_id();

if (!$company_id) {
    echo json_encode(['success' => false, 'error' => 'Company ID required']);
    exit;
}

// Validate company_id matches session tenant
if ($company_id !== $session_tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized tenant context']);
    exit;
}

// Validate branch belongs to tenant
if ($branch_id) {
    $validated_branch = resolve_branch_id($branch_id, $session_tenant_id);
    if ($validated_branch <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid branch']);
        exit;
    }
    $branch_id = $validated_branch;
}

try {
    $pdo = get_db_connection();
    $recommendations = [];

    // 1. Get frequently bought together items
    if (!empty($cart_products)) {
        $placeholders = implode(',', array_fill(0, count($cart_products), '?'));
        
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.selling_price as price,
                p.image,
                p.stock_quantity as stock,
                COUNT(*) as frequency,
                'Frequently bought together' as reason
            FROM sale_items si1
            JOIN sale_items si2 ON si1.sale_id = si2.sale_id AND si1.product_id != si2.product_id
            JOIN products p ON si2.product_id = p.id
            JOIN sales s ON si1.sale_id = s.id
            WHERE si1.product_id IN ({$placeholders})
              AND s.company_id = ?
              AND s.status = 'completed'
              AND p.deleted_at IS NULL
              AND p.active = 1
              AND p.id NOT IN ({$placeholders})
            GROUP BY p.id
            ORDER BY frequency DESC
            LIMIT ?
        ";
        
        $params = array_merge($cart_products, [$company_id], $cart_products, [$limit]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $frequent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($frequent as $item) {
            $recommendations[] = $item;
        }
    }

    // 2. Get customer's favorite categories if customer_id provided
    if ($customer_id && count($recommendations) < $limit) {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.selling_price as price,
                p.image,
                p.stock_quantity as stock,
                'Based on your preferences' as reason
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.category_id IN (
                SELECT DISTINCT p2.category_id
                FROM sales s
                JOIN sale_items si ON s.id = si.sale_id
                JOIN products p2 ON si.product_id = p2.id
                WHERE s.customer_id = ? AND s.company_id = ?
            )
            AND p.company_id = ?
            AND p.deleted_at IS NULL
            AND p.active = 1
            AND p.id NOT IN (" . implode(',', array_fill(0, count($cart_products) + count($recommendations), '?')) . ")
            ORDER BY p.popularity_score DESC, RAND()
            LIMIT ?
        ";
        
        $existing_ids = array_merge($cart_products, array_column($recommendations, 'id'));
        $params = array_merge([$customer_id, $company_id, $company_id], $existing_ids, [$limit - count($recommendations)]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $personalized = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($personalized as $item) {
            $recommendations[] = $item;
        }
    }

    // 3. Get trending/popular products to fill remaining slots
    if (count($recommendations) < $limit) {
        $exclude_ids = array_merge($cart_products, array_column($recommendations, 'id'));
        $exclude_sql = !empty($exclude_ids) ? "AND p.id NOT IN (" . implode(',', array_fill(0, count($exclude_ids), '?')) . ")" : "";
        
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.selling_price as price,
                p.image,
                COALESCE(i.quantity_on_hand, 0) as stock,
                'Popular item' as reason
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
            WHERE p.company_id = ?
            AND p.deleted_at IS NULL
            AND p.active = 1
            AND p.featured = 1
            {$exclude_sql}
            ORDER BY p.popularity_score DESC, p.created_at DESC
            LIMIT ?
        ";
        
        $params = array_merge([$branch_id, $company_id], $exclude_ids, [$limit - count($recommendations)]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($trending as $item) {
            $recommendations[] = $item;
        }
    }

    // 4. Get complementary products (upsells)
    if (!empty($cart_products) && count($recommendations) < $limit) {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.selling_price as price,
                p.image,
                COALESCE(i.quantity_on_hand, 0) as stock,
                'Upgrade option' as reason
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
            WHERE p.company_id = ?
            AND p.category_id IN (
                SELECT category_id FROM products WHERE id IN (" . implode(',', array_fill(0, count($cart_products), '?')) . ")
            )
            AND p.selling_price > (
                SELECT AVG(selling_price) FROM products WHERE id IN (" . implode(',', array_fill(0, count($cart_products), '?')) . ")
            )
            AND p.deleted_at IS NULL
            AND p.active = 1
            AND p.id NOT IN (" . implode(',', array_fill(0, count(array_merge($cart_products, array_column($recommendations, 'id'))), '?')) . ")
            ORDER BY p.selling_price ASC
            LIMIT ?
        ";
        
        $all_excluded = array_merge($cart_products, array_column($recommendations, 'id'));
        $params = array_merge([$branch_id, $company_id], $cart_products, $cart_products, $all_excluded, [$limit - count($recommendations)]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $upsells = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($upsells as $item) {
            $recommendations[] = $item;
        }
    }

    // Ensure we don't exceed limit
    $recommendations = array_slice($recommendations, 0, $limit);

    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations,
        'count' => count($recommendations),
        'generated_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log('Smart recommendations error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate recommendations',
        'recommendations' => []
    ]);
}
