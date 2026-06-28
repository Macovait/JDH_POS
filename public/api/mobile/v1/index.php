<?php
/**
 * Mobile API v1 - Complete mobile application support
 * REST API for POS, customer ordering, and delivery management
 */

require_once __DIR__ . '/../../../../src/Security/CorsHandler.php';
require_once __DIR__ . '/../../../src/paths.php';

// CORS headers for mobile apps
\Jakababa\Security\apply_cors_headers();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Tenant-ID');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load dependencies
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// API Response helper
class MobileAPIResponse {
    public static function success($data = null, $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
        exit;
    }

    public static function error($message = 'Error', $code = 400, $data = null) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
        exit;
    }
}

// Authentication middleware
function require_mobile_auth() {
    $headers = getallheaders();

    // Check API key
    $apiKey = $headers['X-API-Key'] ?? $_GET['api_key'] ?? null;
    if (!$apiKey) {
        MobileAPIResponse::error('API key required', 401);
    }

    // Validate API key and get tenant info
    global $pdo;
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT at.*, t.id as tenant_id, t.name as tenant_name, t.business_type
        FROM api_tokens at
        JOIN tenants t ON at.tenant_id = t.id
        WHERE at.api_key = ? AND at.status = 'active'
    ");
    $stmt->execute([$apiKey]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token) {
        MobileAPIResponse::error('Invalid API key', 401);
    }

    // Check token expiration
    if ($token['expires_at'] && strtotime($token['expires_at']) < time()) {
        MobileAPIResponse::error('API key expired', 401);
    }

    // Set context
    $_SESSION['tenant_id'] = $token['tenant_id'];
    $_SESSION['user_id'] = $token['user_id'] ?? null;
    $_SESSION['mobile_token'] = $token;

    return $token;
}

// Route handling
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove base path to get endpoint
$endpoint = str_replace('/JDH_POS/public/api/mobile/v1/', '', $path);
$endpoint = trim($endpoint, '/');

// Parse endpoint
$parts = explode('/', $endpoint);
$resource = $parts[0] ?? '';
$action = $parts[1] ?? '';
$id = $parts[2] ?? null;

// Route to appropriate handler
try {
    switch ($resource) {
        case 'auth':
            handle_auth($action);
            break;
        case 'pos':
            require_mobile_auth();
            handle_pos($action, $id);
            break;
        case 'customer':
            require_mobile_auth();
            handle_customer($action, $id);
            break;
        case 'driver':
            require_mobile_auth();
            handle_driver($action, $id);
            break;
        case 'manager':
            require_mobile_auth();
            handle_manager($action, $id);
            break;
        case 'sync':
            require_mobile_auth();
            handle_sync($action);
            break;
        default:
            MobileAPIResponse::error('Endpoint not found', 404);
    }
} catch (Exception $e) {
    error_log('Mobile API Error: ' . $e->getMessage());
    MobileAPIResponse::error('Internal server error', 500);
}

// =============================================================================
// AUTH HANDLERS
// =============================================================================

function handle_auth($action) {
    global $pdo;

    switch ($action) {
        case 'login':
            $input = json_decode(file_get_contents('php://input'), true);

// CSRF verification
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

            if (!$input || !isset($input['api_key'])) {
                MobileAPIResponse::error('API key required', 400);
            }

            $token = require_mobile_auth();

            MobileAPIResponse::success([
                'token' => $token['api_key'],
                'tenant' => [
                    'id' => $token['tenant_id'],
                    'name' => $token['tenant_name'],
                    'business_type' => $token['business_type']
                ],
                'permissions' => json_decode($token['scopes'] ?? '[]', true),
                'expires_at' => $token['expires_at']
            ], 'Login successful');
            break;

        default:
            MobileAPIResponse::error('Auth action not found', 404);
    }
}

// =============================================================================
// POS HANDLERS
// =============================================================================

function handle_pos($action, $id) {
    global $pdo;
    $tenant_id = $_SESSION['tenant_id'];

    switch ($action) {
        case 'products':
            // Get products for POS
            $branch_id = $_GET['branch_id'] ?? null;
            $search = $_GET['search'] ?? '';
            $category_id = $_GET['category_id'] ?? null;

            $where = ['p.tenant_id = ?'];
            $params = [$tenant_id];

            if ($branch_id) {
                $where[] = 'p.branch_id = ?';
                $params[] = $branch_id;
            }

            if ($search) {
                $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($category_id) {
                $where[] = 'p.category_id = ?';
                $params[] = $category_id;
            }

            $where_clause = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT
                    p.*,
                    c.name as category_name,
                    COALESCE(i.stock, 0) as current_stock,
                    COALESCE(i.stock, 0) > 0 as in_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
                WHERE $where_clause AND p.active = 1 AND p.deleted_at IS NULL
                ORDER BY p.name
                LIMIT 100
            ");
            $stmt->execute(array_merge([$branch_id], $params));
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            MobileAPIResponse::success($products);
            break;

        case 'create_sale':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                MobileAPIResponse::error('Invalid request data', 400);
            }

            // Validate required fields
            $required = ['branch_id', 'items', 'payment_method'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    MobileAPIResponse::error("Missing required field: $field", 400);
                }
            }

            // Start transaction
            $pdo->beginTransaction();

            try {
                // Generate receipt number
                $receipt_number = 'M' . date('YmdHis') . rand(100, 999);

                // Calculate totals
                $subtotal = 0;
                $tax_amount = 0;
                $discount_amount = 0;

                foreach ($input['items'] as $item) {
                    $subtotal += ($item['price'] * $item['quantity']);
                    $tax_amount += (($item['price'] * $item['quantity']) * ($item['tax_rate'] / 100));
                }

                if (isset($input['discount'])) {
                    $discount_amount = $input['discount_type'] === 'percentage'
                        ? ($subtotal * $input['discount'] / 100)
                        : $input['discount'];
                }

                $final_amount = $subtotal + $tax_amount - $discount_amount;

                // Create sale
                $stmt = $pdo->prepare("
                    INSERT INTO sales (
                        tenant_id, branch_id, user_id, customer_id, invoice_number,
                        subtotal, tax_amount, discount_amount, total,
                        payment_method, status, notes,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenant_id,
                    $input['branch_id'],
                    $_SESSION['user_id'] ?? null,
                    $input['customer_id'] ?? null,
                    $receipt_number,
                    $subtotal,
                    $tax_amount,
                    $discount_amount,
                    $final_amount,
                    $input['payment_method'],
                    $input['notes'] ?? null
                ]);

                $sale_id = $pdo->lastInsertId();

                // Add sale items
                foreach ($input['items'] as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO sale_items (
                            tenant_id, sale_id, product_id, quantity, price, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $sale_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['price'] * $item['quantity']
                    ]);

                    // Update inventory
                    update_inventory_stock($pdo, $item['product_id'], $input['branch_id'], $tenant_id, -$item['quantity']);
                }

                // Create payment record
                if ($final_amount > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (
                            tenant_id, sale_id, amount, payment_method,
                            payment_status, created_at
                        ) VALUES (?, ?, ?, ?, 'completed', NOW())
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $sale_id,
                        $final_amount,
                        $input['payment_method']
                    ]);
                }

                $pdo->commit();

                MobileAPIResponse::success([
                    'sale_id' => $sale_id,
                    'receipt_number' => $receipt_number,
                    'final_amount' => $final_amount
                ], 'Sale created successfully');

            } catch (Exception $e) {
                $pdo->rollBack();
                MobileAPIResponse::error('Failed to create sale: ' . $e->getMessage(), 500);
            }
            break;

        default:
            MobileAPIResponse::error('POS action not found', 404);
    }
}

// =============================================================================
// CUSTOMER HANDLERS
// =============================================================================

function handle_customer($action, $id) {
    global $pdo;
    $tenant_id = $_SESSION['tenant_id'];

    switch ($action) {
        case 'menu':
            // Get menu for customer ordering
            $branch_id = $_GET['branch_id'] ?? null;

            $stmt = $pdo->prepare("
                SELECT
                    p.*,
                    c.name as category_name,
                    COALESCE(i.stock, 0) as current_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?
                WHERE p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL AND COALESCE(i.stock, 0) > 0
                ORDER BY c.name, p.name
            ");
            $stmt->execute([$branch_id, $tenant_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by category
            $menu = [];
            foreach ($products as $product) {
                $category = $product['category_name'] ?: 'Uncategorized';
                if (!isset($menu[$category])) {
                    $menu[$category] = [];
                }
                $menu[$category][] = $product;
            }

            MobileAPIResponse::success($menu);
            break;

        case 'place_order':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                MobileAPIResponse::error('Invalid request data', 400);
            }

            // Create customer order (similar to POS sale but with different workflow)
            $pdo->beginTransaction();

            try {
                $order_number = 'CO' . date('YmdHis') . rand(100, 999);

                // Calculate totals
                $subtotal = 0;
                foreach ($input['items'] as $item) {
                    $subtotal += ($item['price'] * $item['quantity']);
                }

                // Create order record (using sales table with special status)
                $stmt = $pdo->prepare("
                    INSERT INTO sales (
                        tenant_id, branch_id, customer_id, invoice_number,
                        subtotal, total, payment_method, status,
                        order_type, notes,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending_payment', 'pending', 'delivery', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenant_id,
                    $input['branch_id'],
                    $input['customer_id'] ?? null,
                    $order_number,
                    $subtotal,
                    $input['delivery_address'] ?? null,
                    $input['special_instructions'] ?? null
                ]);

                $order_id = $pdo->lastInsertId();

                // Add order items
                foreach ($input['items'] as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO sale_items (
                            tenant_id, sale_id, product_id, quantity, price, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $order_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['price'] * $item['quantity']
                    ]);
                }

                $pdo->commit();

                MobileAPIResponse::success([
                    'order_id' => $order_id,
                    'order_number' => $order_number
                ], 'Order placed successfully');

            } catch (Exception $e) {
                $pdo->rollBack();
                MobileAPIResponse::error('Failed to place order: ' . $e->getMessage(), 500);
            }
            break;

        default:
            MobileAPIResponse::error('Customer action not found', 404);
    }
}

// =============================================================================
// DRIVER HANDLERS
// =============================================================================

function handle_driver($action, $id) {
    global $pdo;
    $tenant_id = $_SESSION['tenant_id'];

    switch ($action) {
        case 'deliveries':
            $driver_id = $_GET['driver_id'] ?? null;
            $status = $_GET['status'] ?? 'assigned';

            if (!$driver_id) {
                MobileAPIResponse::error('Driver ID required', 400);
            }

            $stmt = $pdo->prepare("
                SELECT
                    s.id,
                    s.invoice_number,
                    s.total,
                    s.notes,
                    s.status as order_status,
                    c.name as customer_name,
                    c.phone as customer_phone,
                    b.name as branch_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN branches b ON s.branch_id = b.id
                WHERE s.tenant_id = ?
                AND s.order_type = 'customer_order'
                AND s.status = ?
                ORDER BY s.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$tenant_id, $status]);
            $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            MobileAPIResponse::success($deliveries);
            break;

        case 'update_status':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['order_id']) || !isset($input['status'])) {
                MobileAPIResponse::error('Order ID and status required', 400);
            }

            $valid_statuses = ['picked_up', 'in_transit', 'delivered', 'failed'];
            if (!in_array($input['status'], $valid_statuses)) {
                MobileAPIResponse::error('Invalid status', 400);
            }

            $stmt = $pdo->prepare("
                UPDATE sales
                SET status = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$input['status'], $input['order_id'], $tenant_id]);

            if ($stmt->rowCount() > 0) {
                MobileAPIResponse::success(null, 'Status updated successfully');
            } else {
                MobileAPIResponse::error('Order not found or update failed', 404);
            }
            break;

        default:
            MobileAPIResponse::error('Driver action not found', 404);
    }
}

// =============================================================================
// MANAGER HANDLERS
// =============================================================================

function handle_manager($action, $id) {
    global $pdo;
    $tenant_id = $_SESSION['tenant_id'];

    switch ($action) {
        case 'dashboard':
            $branch_id = $_GET['branch_id'] ?? null;

            // Today's metrics
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as orders_today,
                    COALESCE(SUM(total), 0) as revenue_today,
                    COUNT(DISTINCT customer_id) as customers_today
                FROM sales
                WHERE tenant_id = ?
                AND DATE(created_at) = CURDATE()
                AND status = 'completed'
                " . ($branch_id ? "AND branch_id = ?" : "")
            );
            $params = [$tenant_id];
            if ($branch_id) $params[] = $branch_id;
            $stmt->execute($params);
            $today = $stmt->fetch(PDO::FETCH_ASSOC);

            // Pending orders
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as pending_orders
                FROM sales
                WHERE tenant_id = ?
                AND status = 'pending'
                " . ($branch_id ? "AND branch_id = ?" : "")
            );
            $stmt->execute($params);
            $pending = $stmt->fetch(PDO::FETCH_ASSOC);

            // Top products today
            $stmt = $pdo->prepare("
                SELECT
                    p.name,
                    SUM(si.quantity) as quantity_sold,
                    SUM(si.subtotal) as revenue
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE s.tenant_id = ?
                AND DATE(s.created_at) = CURDATE()
                AND s.status = 'completed'
                " . ($branch_id ? "AND s.branch_id = ?" : "") . "
                GROUP BY p.id, p.name
                ORDER BY quantity_sold DESC
                LIMIT 5
            ");
            $stmt->execute($params);
            $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            MobileAPIResponse::success([
                'today' => $today,
                'pending' => $pending,
                'top_products' => $top_products
            ]);
            break;

        case 'sales':
            $period = $_GET['period'] ?? 'today';
            $branch_id = $_GET['branch_id'] ?? null;

            $date_condition = match($period) {
                'today' => 'DATE(created_at) = CURDATE()',
                'week' => 'YEARWEEK(created_at) = YEARWEEK(NOW())',
                'month' => 'MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())',
                default => 'DATE(created_at) = CURDATE()'
            };

            $stmt = $pdo->prepare("
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as order_count,
                    COALESCE(SUM(total), 0) as total_revenue,
                    COALESCE(AVG(total), 0) as avg_order_value
                FROM sales
                WHERE tenant_id = ?
                AND $date_condition
                AND status = 'completed'
                " . ($branch_id ? "AND branch_id = ?" : "") . "
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $params = [$tenant_id];
            if ($branch_id) $params[] = $branch_id;
            $stmt->execute($params);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            MobileAPIResponse::success($sales);
            break;

        default:
            MobileAPIResponse::error('Manager action not found', 404);
    }
}

// =============================================================================
// SYNC HANDLERS
// =============================================================================

function handle_sync($action) {
    global $pdo;
    $tenant_id = $_SESSION['tenant_id'];

    switch ($action) {
        case 'changes':
            $input = json_decode(file_get_contents('php://input'), true);
            $last_sync = $input['last_sync'] ?? 0;

            $changes = [];

            // Get new/modified products
            $stmt = $pdo->prepare("
                SELECT 'product' as type, id, name, sku, price, updated_at
                FROM products
                WHERE tenant_id = ?
                AND deleted_at IS NULL
                AND updated_at > FROM_UNIXTIME(?)
                ORDER BY updated_at ASC
                LIMIT 100
            ");
            $stmt->execute([$tenant_id, $last_sync]);
            $changes['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get new sales
            $stmt = $pdo->prepare("
                SELECT 'sale' as type, id, invoice_number, total, created_at
                FROM sales
                WHERE tenant_id = ?
                AND created_at > FROM_UNIXTIME(?)
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $stmt->execute([$tenant_id, $last_sync]);
            $changes['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            MobileAPIResponse::success([
                'changes' => $changes,
                'server_time' => time(),
                'has_more' => count($changes['products']) >= 100 || count($changes['sales']) >= 50
            ]);
            break;

        default:
            MobileAPIResponse::error('Sync action not found', 404);
    }
}

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function update_inventory_stock($pdo, $product_id, $branch_id, $tenant_id, $quantity_change) {
    // Check if inventory record exists
    $stmt = $pdo->prepare("
        SELECT id, stock FROM inventory
        WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$product_id, $branch_id, $tenant_id]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    $old_stock = (int) ($inventory['stock'] ?? 0);
    $new_stock = $old_stock + $quantity_change;

    if ($inventory) {
        // Update existing
        $stmt = $pdo->prepare("
            UPDATE inventory
            SET stock = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_stock, $inventory['id']]);
    } else {
        // Create new (only if positive stock)
        if ($quantity_change > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO inventory (tenant_id, product_id, branch_id, stock, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$tenant_id, $product_id, $branch_id, $quantity_change]);
        }
    }

    // Log the movement
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements (
            tenant_id, branch_id, product_id, movement_type,
            quantity_change, quantity_before, quantity_after, notes, user_id, created_at
        ) VALUES (?, ?, ?, 'sale', ?, ?, ?, 'sale', ?, NOW())
    ");
    $stmt->execute([
        $tenant_id,
        $branch_id,
        $product_id,
        $quantity_change,
        $old_stock,
        $new_stock,
        $_SESSION['user_id'] ?? null
    ]);
}
?>