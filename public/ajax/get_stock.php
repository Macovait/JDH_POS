<?php
declare(strict_types=1);

/**
 * Get Stock - AJAX Endpoint
 *
 * GET /ajax/get_stock.php?product_id=123
 *
 * Returns stock info for a product at the current branch.
 * Scoped by tenant_id + branch_id.
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

    $product_id = (int) ($_GET['product_id'] ?? 0);
    if ($product_id <= 0) {
        pos_json_error('Invalid product_id');
    }

    $stock = pos_get_stock($pdo, $ctx['tenant_id'], $ctx['branch_id'], $product_id);

    if ($stock === null) {
        pos_json_error('Product not found or not available at this branch', 404);
    }

    pos_json_success(['stock' => $stock]);

} catch (\PDOException $e) {
    error_log("get_stock error: " . $e->getMessage());
    pos_json_error('Database error', 500);
} catch (\Exception $e) {
    pos_json_error($e->getMessage(), 400);
}
