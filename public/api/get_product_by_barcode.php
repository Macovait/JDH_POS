<?php
/**
 * Get Product by Barcode API
 * Returns product details for barcode scanning in POS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

// Release session lock immediately
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json');

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['found' => false, 'error' => 'Database connection failed']);
    exit;
}

$barcode = trim($_GET['barcode'] ?? '');
$branch_id = (int) ($_GET['branch_id'] ?? 0);

// Validate session: reject unauthenticated requests
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);
$session_tenant_id = (int) (get_current_tenant_id() ?? ($_SESSION['tenant_id'] ?? 0));

if (!$session_user_id || !$session_tenant_id) {
    echo json_encode(['found' => false, 'error' => 'Unauthorized']);
    exit;
}

// Reject cross-tenant queries
$requested_tenant_id = (int) ($_GET['company_id'] ?? $_GET['tenant_id'] ?? 0);
if ($requested_tenant_id && $requested_tenant_id !== $session_tenant_id) {
    echo json_encode(['found' => false, 'error' => 'Tenant mismatch']);
    exit;
}

$tenant_id = $requested_tenant_id ?: $session_tenant_id;

if (!$barcode || !$tenant_id) {
    echo json_encode(['found' => false, 'error' => 'Barcode and tenant_id required']);
    exit;
}

try {
    // Try barcode column first, then fall back to sku
    $hasBarcode = db_has_column('products', 'barcode');
    $hasSku     = db_has_column('products', 'sku');

    $fields = ["p.id", "p.name", "p.price", "p.category_id"];
    if (db_has_column('products', 'image'))   $fields[] = "p.image";
    if (db_has_column('products', 'sku'))     $fields[] = "p.sku";
    if (db_has_column('products', 'barcode')) $fields[] = "p.barcode";

    $sql = "SELECT " . implode(", ", $fields) . " FROM products p WHERE p.tenant_id = ? AND p.deleted_at IS NULL";
    $params = [$tenant_id];

    $conditions = [];
    if ($hasBarcode) {
        $conditions[] = "p.barcode = ?";
        $params[] = $barcode;
    }
    if ($hasSku) {
        $conditions[] = "p.sku = ?";
        $params[] = $barcode;
    }
    // Also match by product id if barcode is numeric
    if (is_numeric($barcode)) {
        $conditions[] = "p.id = ?";
        $params[] = (int) $barcode;
    }

    if (empty($conditions)) {
        echo json_encode(['found' => false, 'error' => 'No barcode/sku column available']);
        exit;
    }

    $sql .= " AND (" . implode(" OR ", $conditions) . ")";
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Resolve real stock from inventory table if available
        $stock = isset($product['stock']) ? (int) $product['stock'] : 999;
        if (db_table_exists('inventory') && $branch_id > 0) {
            try {
                $inv = $pdo->prepare("SELECT stock FROM inventory WHERE tenant_id = ? AND branch_id = ? AND product_id = ? LIMIT 1");
                $inv->execute([$tenant_id, $branch_id, (int)$product['id']]);
                $invRow = $inv->fetch(PDO::FETCH_ASSOC);
                if ($invRow) {
                    $stock = (int) $invRow['stock'];
                }
            } catch (Exception $e) {
                // ignore inventory read errors
            }
        }

        echo json_encode([
            'found' => true,
            'product' => [
                'id'     => (int) $product['id'],
                'name'   => $product['name'],
                'price'  => (float) $product['price'],
                'stock'  => $stock,
                'sku'    => $product['sku'] ?? '',
                'barcode'=> $product['barcode'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['found' => false, 'error' => 'Product not found']);
    }
} catch (Exception $e) {
    echo json_encode(['found' => false, 'error' => 'Lookup error: ' . $e->getMessage()]);
}
