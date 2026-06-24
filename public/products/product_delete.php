<?php
/**
 * Product Delete Handler
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
    header('Location: products.php');
    exit;
}

if (!isset($_POST['id'])) {
    header('Location: products.php');
    exit;
}

$product_id = (int) $_POST['id'];
$user_id = get_current_user_id();

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id = ? AND s.tenant_id = ?");
    $stmt->execute([$product_id, $tenant_id]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = 'Cannot delete product with sales history';
        header('Location: products.php');
        exit;
    }

    // Soft delete product instead of hard delete
    $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW(), deleted_by = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $product_id, $tenant_id]);

    if ($stmt->rowCount() > 0) {
        log_activity($user_id, 'product_delete', ['product_id' => $product_id], $tenant_id);
        $_SESSION['success_message'] = 'Product deleted successfully';
    } else {
        $_SESSION['error_message'] = 'Product not found';
    }
} catch (Exception $e) {
    error_log("Delete product error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error deleting product';
}

header('Location: products.php');
exit;