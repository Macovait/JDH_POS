<?php
/**
 * Mobile Customer API
 * Handles customer mobile app operations for ordering and account management
 */

require_once __DIR__ . '/../mobile/index.php';

$company = mobile_authenticate($pdo);

switch ($action) {
    case 'menu':
        get_customer_menu($pdo, $company);
        break;

    case 'place_order':
        place_customer_order($pdo, $company);
        break;

    case 'orders':
        get_customer_orders($pdo, $company);
        break;

    case 'order_status':
        get_order_status($pdo, $company);
        break;

    case 'favorites':
        get_customer_favorites($pdo, $company);
        break;

    case 'profile':
        handle_customer_profile($pdo, $company);
        break;

    case 'loyalty':
        get_customer_loyalty($pdo, $company);
        break;

    default:
        mobile_error('Invalid customer action', 400);
}

function get_customer_menu($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 1);
    $business_type_id = (int) ($_GET['business_type_id'] ?? 2);

    // Get categories
    $stmt = $pdo->prepare("
        SELECT id, name, color, icon
        FROM categories
        WHERE tenant_id = ?
        AND status = 'active'
        AND (business_type_id = ? OR business_type_id IS NULL)
        ORDER BY name
    ");
    $stmt->execute([$company['id'], $business_type_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get products by category
    $menu_items = [];
    foreach ($categories as $category) {
        $stmt = $pdo->prepare("
            SELECT
                p.id, p.name, p.description,
                COALESCE(p.selling_price, p.price) as price,
                p.image, COALESCE(i.stock, 0) as stock,
                p.category_id, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.tenant_id = ? AND p.deleted_at IS NULL
            AND p.category_id = ?
            AND p.business_type_id = ?
            AND p.active = 1
            AND COALESCE(i.stock, 0) > 0
            ORDER BY p.name
        ");
        $stmt->execute([$branch_id, $company['id'], $company['id'], $category['id'], $business_type_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($products)) {
            $menu_items[] = [
                'category' => $category,
                'products' => $products
            ];
        }
    }

    mobile_success([
        'menu' => $menu_items,
        'branch_id' => $branch_id,
        'business_type' => $business_type_id
    ]);
}

function place_customer_order($pdo, $company) {
    $data = validate_mobile_request([
        'customer_id', 'branch_id', 'items', 'total', 'order_type'
    ]);

    $customer_id = (int) $data['customer_id'];
    $branch_id = (int) $data['branch_id'];
    $items = $data['items'];
    $total = (float) $data['total'];
    $order_type = $data['order_type']; // 'pickup', 'delivery', 'dine_in'
    $delivery_address = $data['delivery_address'] ?? '';
    $special_instructions = $data['special_instructions'] ?? '';
    $payment_method = $data['payment_method'] ?? 'cash';
    $scheduled_time = $data['scheduled_time'] ?? null;

    // Validate customer belongs to company
    $stmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = ? AND tenant_id = ? AND active = 1");
    $stmt->execute([$customer_id, $company['id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        mobile_error('Invalid customer', 400);
    }

    // Validate branch
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1");
    $stmt->execute([$branch_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid branch', 400);
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO customer_orders (
                customer_id, branch_id, order_type, total, status,
                delivery_address, special_instructions, payment_method,
                scheduled_time, created_at
            ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $customer_id, $branch_id, $order_type, $total,
            $delivery_address, $special_instructions, $payment_method, $scheduled_time
        ]);
        $order_id = $pdo->lastInsertId();

        // Add order items
        $stmt_item = $pdo->prepare("
            INSERT INTO customer_order_items (
                order_id, product_id, product_name, quantity, price, total, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($items as $item) {
            $product_id = (int) $item['product_id'];
            $quantity = (float) $item['quantity'];
            $price = (float) $item['price'];
            $item_total = $quantity * $price;

            // Validate product availability
            $stmt_check = $pdo->prepare("
                SELECT COALESCE(i.stock, 0) as stock
                FROM products p
                LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
                WHERE p.id = ? AND p.active = 1 AND COALESCE(i.stock, 0) >= ?
            ");
            $stmt_check->execute([$branch_id, $company['id'], $product_id, $quantity]);
            if (!$stmt_check->fetch()) {
                throw new Exception("Insufficient stock for product ID $product_id");
            }

            $stmt_item->execute([
                $order_id, $product_id, $item['name'], $quantity, $price, $item_total
            ]);
        }

        $pdo->commit();

        // Send notification to staff
        send_order_notification($pdo, $order_id, $company);

        mobile_success([
            'order_id' => $order_id,
            'order_number' => 'ORD' . str_pad($order_id, 6, '0', STR_PAD_LEFT),
            'status' => 'pending',
            'estimated_time' => estimate_preparation_time($order_type)
        ], 'Order placed successfully');

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Customer order error: " . $e->getMessage());
        mobile_error('Failed to place order: ' . $e->getMessage(), 500);
    }
}

function get_customer_orders($pdo, $company) {
    $customer_id = (int) ($_GET['customer_id'] ?? 0);
    $limit = (int) ($_GET['limit'] ?? 20);
    $offset = (int) ($_GET['offset'] ?? 0);

    if (!$customer_id) {
        mobile_error('Customer ID required', 400);
    }

    // Validate customer belongs to company
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customer_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid customer', 403);
    }

    $stmt = $pdo->prepare("
        SELECT
            o.id, o.order_number, o.order_type, o.total, o.status,
            o.created_at, o.scheduled_time, o.delivery_address,
            COUNT(oi.id) as item_count
        FROM customer_orders o
        LEFT JOIN customer_order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$customer_id, $limit, $offset]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order items for each order
    foreach ($orders as &$order) {
        $stmt_items = $pdo->prepare("
            SELECT product_name, quantity, price, total
            FROM customer_order_items
            WHERE order_id = ?
            ORDER BY created_at
        ");
        $stmt_items->execute([$order['id']]);
        $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }

    mobile_success(['orders' => $orders]);
}

function get_order_status($pdo, $company) {
    $order_id = (int) ($_GET['order_id'] ?? 0);
    $customer_id = (int) ($_GET['customer_id'] ?? 0);

    if (!$order_id || !$customer_id) {
        mobile_error('Order ID and Customer ID required', 400);
    }

    // Validate order belongs to customer and company
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as customer_name
        FROM customer_orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ? AND o.customer_id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$order_id, $customer_id, $company['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        mobile_error('Order not found', 404);
    }

    // Get order items
    $stmt_items = $pdo->prepare("
        SELECT product_name, quantity, price, total
        FROM customer_order_items
        WHERE order_id = ?
    ");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $order['items'] = $items;

    mobile_success(['order' => $order]);
}

function get_customer_favorites($pdo, $company) {
    $customer_id = (int) ($_GET['customer_id'] ?? 0);

    if (!$customer_id) {
        mobile_error('Customer ID required', 400);
    }

    // For now, return most ordered products as "favorites"
    // In a full implementation, you'd have a favorites table
    $stmt = $pdo->prepare("
        SELECT
            p.id, p.name, p.image, COALESCE(p.selling_price, p.price) as price,
            COUNT(oi.product_id) as order_count
        FROM customer_order_items oi
        JOIN customer_orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.customer_id = ? AND p.tenant_id = ?
        GROUP BY oi.product_id
        ORDER BY order_count DESC
        LIMIT 10
    ");
    $stmt->execute([$customer_id, $company['id']]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success(['favorites' => $favorites]);
}

function handle_customer_profile($pdo, $company) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $customer_id = (int) ($_GET['customer_id'] ?? 0);

        if (!$customer_id) {
            mobile_error('Customer ID required', 400);
        }

        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, address, loyalty_points, created_at
            FROM customers
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$customer_id, $company['id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            mobile_error('Customer not found', 404);
        }

        mobile_success(['customer' => $customer]);

    } elseif ($method === 'PUT') {
        $data = validate_mobile_request(['customer_id', 'name']);
        $customer_id = (int) $data['customer_id'];

        // Validate customer belongs to company
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $company['id']]);
        if (!$stmt->fetch()) {
            mobile_error('Invalid customer', 403);
        }

        // Update customer
        $stmt = $pdo->prepare("
            UPDATE customers
            SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['address'] ?? '',
            $customer_id,
            $company['id']
        ]);

        mobile_success(null, 'Profile updated successfully');
    }
}

function get_customer_loyalty($pdo, $company) {
    $customer_id = (int) ($_GET['customer_id'] ?? 0);

    if (!$customer_id) {
        mobile_error('Customer ID required', 400);
    }

    $stmt = $pdo->prepare("
        SELECT loyalty_points, created_at
        FROM customers
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$customer_id, $company['id']]);
    $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loyalty) {
        mobile_error('Customer not found', 404);
    }

    // Get recent loyalty transactions
    $stmt = $pdo->prepare("
        SELECT points_change, new_balance, created_at, description
        FROM loyalty_points_log
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$customer_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success([
        'loyalty_points' => (int) $loyalty['loyalty_points'],
        'member_since' => $loyalty['created_at'],
        'transactions' => $transactions
    ]);
}

function send_order_notification($pdo, $order_id, $company) {
    // In a full implementation, this would send push notifications to staff devices
    // For now, just log it
    error_log("New customer order: $order_id for company {$company['id']}");
}

function estimate_preparation_time($order_type) {
    switch ($order_type) {
        case 'pickup':
            return 15; // minutes
        case 'delivery':
            return 30; // minutes
        case 'dine_in':
            return 10; // minutes
        default:
            return 20;
    }
}
