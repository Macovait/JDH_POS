<?php
declare(strict_types=1);

/**
 * POS Fetch Products - AJAX Endpoint
 *
 * GET /pos/fetch_products.php
 * ?category_id=1&search=term&limit=50&offset=0&in_stock=1
 *
 * Returns JSON: {success, products, categories, low_stock_count, business_type}
 * Every product is scoped by tenant_id; stock is branch-specific.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

require_once __DIR__ . '/../../src/pos_backend.php';

try {
    $ctx = pos_require_session();
    $pdo = get_db_connection();

    $filters = [
        'category_id' => isset($_GET['category_id']) ? (int) $_GET['category_id'] : null,
        'search'      => trim($_GET['search'] ?? ''),
        'limit'       => (int) ($_GET['limit'] ?? 50),
        'offset'      => (int) ($_GET['offset'] ?? 0),
        'in_stock'    => ($_GET['in_stock'] ?? '1') === '1',
        'show_all'    => ($_GET['show_all'] ?? '0') === '1',
    ];

    $data = pos_fetch_products(
        $pdo,
        $ctx['tenant_id'],
        $ctx['branch_id'],
        $ctx['business_type'],
        $ctx['business_type_id'],
        $filters
    );

    pos_json_success($data);

} catch (\PDOException $e) {
    error_log("fetch_products error: " . $e->getMessage());
    pos_json_error('Database error loading products', 500);
} catch (\Exception $e) {
    pos_json_error($e->getMessage(), 400);
}
