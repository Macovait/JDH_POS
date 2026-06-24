<?php
declare(strict_types=1);

/**
 * AJAX endpoint to check product stock availability
 * TENANT ISOLATION: Enforces tenant_id + branch_id filtering
 *
 * GET /ajax/check_stock.php?product_id=123&quantity=1
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Start session with proper cookie settings for AJAX
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    
    // Calculate proper cookie path based on script location
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $cookie_path = '/';
    if (strpos($script_name, '/JDH_POS/') === 0) {
        $cookie_path = '/JDH_POS/';
    }
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie_path,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

// Validate session
$tenant_id = (int) get_current_tenant_id();
$branch_id  = (int) get_current_branch_id();
$user_id    = (int) get_current_user_id();
$business_type = (string) (get_current_business_type($tenant_id) ?? ($_SESSION['business_type'] ?? ''));
$business_type_id = (int) (get_current_business_type_id($tenant_id) ?? 0);

// Release session lock — remaining logic is read-only DB queries
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!$tenant_id || !$branch_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

// ZERO-TRUST: Reject URL overrides for tenant and user context
if (!empty($_GET['tenant_id']) || !empty($_GET['user_id'])) {
    error_log("ZERO-TRUST VIOLATION: URL parameter override attempt in check_stock.php");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Get parameters
$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$quantity   = isset($_GET['quantity']) ? max(1, (int) $_GET['quantity']) : 1;
$req_branch = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $branch_id;
$req_company = $tenant_id; // Always use session context
$req_user = $user_id;      // Always use session context
$req_business_type = trim((string) ($_GET['business_type'] ?? $business_type));
$req_business_type_id = isset($_GET['business_type_id']) ? (int) $_GET['business_type_id'] : $business_type_id;

if ($business_type !== '' && $req_business_type !== '' && $req_business_type !== $business_type) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Business type mismatch']);
    exit;
}

if ($business_type_id > 0 && $req_business_type_id > 0 && $req_business_type_id !== $business_type_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Business type mismatch']);
    exit;
}

// Security: ensure branch belongs to this company
if ($req_branch !== $branch_id) {
    $stmt = get_db_connection()->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$req_branch, $tenant_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Branch does not belong to your tenant']);
        exit;
    }
}

if ($product_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid product ID'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $has_product_business_type_id = db_has_column('products', 'business_type_id');
    $has_product_business_type = db_has_column('products', 'business_type');
    $has_deleted_at = db_has_column('products', 'deleted_at');

    $where = ["p.id = ?", "p.tenant_id = ?"];
    $params = [$product_id, $tenant_id];

    if ($has_deleted_at) {
        $where[] = "p.deleted_at IS NULL";
    }

    // Only filter by business_type_id if products exist for this type
    // Otherwise show all company products (fallback for data compatibility)
    if ($has_product_business_type_id && $business_type_id > 0) {
        $check_bt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND business_type_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1");
        $check_bt->execute([$tenant_id, $business_type_id]);
        if ($check_bt->fetchColumn() > 0) {
            $where[] = "p.business_type_id = ?";
            $params[] = $business_type_id;
        }
    } elseif ($has_product_business_type && $business_type !== '') {
        $where[] = "p.business_type = ?";
        $params[] = $business_type;
    }

    // TENANT ISOLATION: Filter by tenant_id on BOTH product and inventory
    $sql = "
        SELECT p.id, p.name, p.sku, p.price, COALESCE(p.selling_price, p.price) AS selling_price,
               COALESCE(i.stock, 0) AS stock, i.reorder_level, i.minimum_stock, i.maximum_stock
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        WHERE " . implode(' AND ', $where);

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$req_branch, $tenant_id], $params));
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            'success' => false,
            'error' => 'Product not found'
        ]);
        exit;
    }

    $stock = (int) ($product['stock'] ?? 0);
    $available = $stock >= $quantity;

    echo json_encode([
        'success' => true,
        'product_id' => (int) $product['id'],
        'product_name' => $product['name'],
        'sku' => $product['sku'] ?? '',
        'price' => (float) ($product['selling_price'] ?? $product['price']),
        'current_stock' => $stock,
        'requested_quantity' => $quantity,
        'available' => $available,
        'max_available' => $stock,
        'reorder_level' => (int) ($product['reorder_level'] ?? 0),
        'minimum_stock' => (int) ($product['minimum_stock'] ?? 0),
        'maximum_stock' => $product['maximum_stock'] !== null ? (int) $product['maximum_stock'] : null,
        'message' => $available ? 'Stock available' : "Insufficient stock. Available: $stock"
    ]);

} catch (PDOException $e) {
    error_log("check_stock error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
