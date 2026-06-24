<?php
/**
 * AJAX endpoint for cart actions (add, update, remove items)
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// Get action and parameters
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : get_current_branch_id();
$tenant_id = get_current_tenant_id();

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Action is required'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();

    // Initialize cart in session if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    switch ($action) {
        case 'add':
            if ($product_id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid product ID'
                ]);
                exit;
            }

            // Get product details
            $stmt = $pdo->prepare("
                SELECT p.*, i.stock, c.name as category_name 
                FROM products p 
                LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
            ");
            $stmt->execute([$branch_id, $tenant_id, $product_id, $tenant_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Product not found'
                ]);
                exit;
            }

            // Check stock
            if ($product['stock'] < $quantity) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $product['stock']
                ]);
                exit;
            }

            // Add to cart
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => (float) $product['price'],
                    'quantity' => $quantity,
                    'sku' => $product['sku'],
                    'category' => $product['category_name'],
                    'image' => (function($path) {
                        if (!$path) return '';
                        $rel = ltrim($path, '/');
                        if (strpos($rel, 'public/') === 0) $rel = substr($rel, 7);
                        $full = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                        return file_exists($full) ? base_url($rel) . '?v=' . (@filemtime($full) ?: time()) : '';
                    })($product['image'])
                ];
            }

            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart',
                'cart' => array_values($_SESSION['cart']),
                'cart_total' => array_sum(array_map(function ($item) {
                    return $item['price'] * $item['quantity'];
                }, $_SESSION['cart']))
            ]);
            break;

        case 'update':
            if ($product_id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid product ID'
                ]);
                exit;
            }

            if (!isset($_SESSION['cart'][$product_id])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Product not in cart'
                ]);
                exit;
            }

            if ($quantity <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                // Check stock
                $stmt = $pdo->prepare("
                    SELECT stock FROM inventory 
                    WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                ");
                $stmt->execute([$product_id, $branch_id, $tenant_id]);
                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($inventory && $inventory['stock'] < $quantity) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $inventory['stock']
                    ]);
                    exit;
                }

                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Cart updated',
                'cart' => array_values($_SESSION['cart']),
                'cart_total' => array_sum(array_map(function ($item) {
                    return $item['price'] * $item['quantity'];
                }, $_SESSION['cart']))
            ]);
            break;

        case 'remove':
            if ($product_id <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid product ID'
                ]);
                exit;
            }

            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Product removed from cart',
                'cart' => array_values($_SESSION['cart']),
                'cart_total' => array_sum(array_map(function ($item) {
                    return $item['price'] * $item['quantity'];
                }, $_SESSION['cart']))
            ]);
            break;

        case 'clear':
            $_SESSION['cart'] = [];

            echo json_encode([
                'success' => true,
                'message' => 'Cart cleared',
                'cart' => [],
                'cart_total' => 0
            ]);
            break;

        case 'get':
            echo json_encode([
                'success' => true,
                'cart' => array_values($_SESSION['cart']),
                'cart_total' => array_sum(array_map(function ($item) {
                    return $item['price'] * $item['quantity'];
                }, $_SESSION['cart']))
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    error_log("Error in cart_actions: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing cart action'
    ]);
}
