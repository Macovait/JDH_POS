<?php
/**
 * Mobile POS API
 * Handles mobile POS operations for staff
 */

require_once __DIR__ . '/../mobile/index.php';

$auth = jwt_authenticate($pdo);
$user = $auth['user'];
$company = $auth['tenant'];

switch ($action) {
    case 'products':
        get_mobile_products($pdo, $company);
        break;

    case 'categories':
        get_mobile_categories($pdo, $company);
        break;

    case 'create_sale':
        create_mobile_sale($pdo, $company);
        break;

    case 'orders':
        get_mobile_orders($pdo, $company);
        break;

    case 'customers':
        get_mobile_customers($pdo, $company);
        break;

    case 'inventory':
        get_mobile_inventory($pdo, $company);
        break;

    case 'shift':
        handle_mobile_shift($pdo, $company);
        break;

    case 'sync':
        sync_mobile_data($pdo, $company);
        break;

    default:
        mobile_error('Invalid POS action', 400);
}

function get_mobile_products($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 1);
    $business_type_id = (int) ($_GET['business_type_id'] ?? 2); // Default supermarket
    $search = trim($_GET['search'] ?? '');
    $category_id = (int) ($_GET['category_id'] ?? 0);
    $limit = (int) ($_GET['limit'] ?? 50);
    $offset = (int) ($_GET['offset'] ?? 0);

    $sql = "
        SELECT
            p.id,
            p.name,
            COALESCE(p.selling_price, p.price) as price,
            p.cost_price,
            p.sku,
            p.barcode,
            p.image,
            p.category_id,
            c.name as category_name,
            COALESCE(i.stock, 0) as stock,
            p.active,
            p.description
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.business_type_id = ?
        AND p.active = 1
        AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
    ";

    $params = [$branch_id, $company['id'], $company['id'], $business_type_id];

    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($category_id > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }

    $sql .= " ORDER BY p.name LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $count_sql = str_replace(
        "SELECT p.id, p.name, COALESCE(p.selling_price, p.price) as price, p.cost_price, p.sku, p.barcode, p.image, p.category_id, c.name as category_name, COALESCE(i.stock, 0) as stock, p.active, p.description",
        "SELECT COUNT(*)",
        $sql
    );
    $count_sql = preg_replace('/ORDER BY.*LIMIT.*OFFSET.*/', '', $count_sql);
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
    $total_count = $count_stmt->fetchColumn();

    mobile_success([
        'products' => $products,
        'pagination' => [
            'total' => (int) $total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ]
    ]);
}

function get_mobile_categories($pdo, $company) {
    $business_type_id = (int) ($_GET['business_type_id'] ?? 2);

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

    mobile_success(['categories' => $categories]);
}

function create_mobile_sale($pdo, $company) {
    $data = validate_mobile_request([
        'items', 'total', 'payment_method', 'branch_id'
    ]);

    $branch_id = (int) $data['branch_id'];
    $user_id = (int) ($data['user_id'] ?? 1);
    $customer_id = $data['customer_id'] ? (int) $data['customer_id'] : null;
    $items = $data['items'];
    $total = (float) $data['total'];
    $discount = (float) ($data['discount'] ?? 0);
    $payment_method = $data['payment_method'];
    $split_payments = $data['split_payments'] ?? [];
    $notes = $data['notes'] ?? '';
    $device_id = $data['device_id'] ?? null;

    // Validate branch belongs to company
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1");
    $stmt->execute([$branch_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid branch', 400);
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Create sale record
        $stmt = $pdo->prepare("
            INSERT INTO sales (
                tenant_id, branch_id, user_id, customer_id, total, discount, payment_method,
                status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([
            $company['id'], $branch_id, $user_id, $customer_id, $total, $discount,
            $payment_method, $notes
        ]);
        $sale_id = $pdo->lastInsertId();

        // Add sale items
        $stmt_item = $pdo->prepare("
            INSERT INTO sale_items (
                tenant_id, sale_id, product_id, quantity, price, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $product_id = (int) $item['product_id'];
            $quantity = (float) $item['quantity'];
            $price = (float) $item['price'];
            $item_total = $quantity * $price;

            $stmt_item->execute([
                $company['id'], $sale_id, $product_id, $quantity, $price, $item_total
            ]);

            // Update inventory
            update_inventory_stock($pdo, $product_id, $branch_id, $company['id'], -$quantity);
        }

        // Handle split payments (table not available yet)
        // TODO: Implement split payments when sale_payments table is created

        // Update customer loyalty points if applicable
        if ($customer_id && $total > 0) {
            $points_earned = floor($total / 100); // 1 point per 100 currency
            if ($points_earned > 0) {
                $stmt = $pdo->prepare("
                    UPDATE customers
                    SET loyalty_points = COALESCE(loyalty_points, 0) + ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$points_earned, $customer_id, $company['id']]);
            }
        }

        $pdo->commit();

        // Log the sale
        error_log("Mobile POS sale created: ID $sale_id, Total $total, Branch $branch_id");

        mobile_success([
            'sale_id' => $sale_id,
            'receipt_number' => 'S' . str_pad($sale_id, 6, '0', STR_PAD_LEFT),
            'total' => $total,
            'points_earned' => $points_earned ?? 0
        ], 'Sale completed successfully');

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Mobile POS sale error: " . $e->getMessage());
        mobile_error('Failed to complete sale', 500);
    }
}

function get_mobile_customers($pdo, $company) {
    $search = trim($_GET['search'] ?? '');
    $limit = (int) ($_GET['limit'] ?? 20);

    $sql = "
        SELECT id, name, phone, email, address, loyalty_points
        FROM customers
        WHERE tenant_id = ?
        AND active = 1
    ";
    $params = [$company['id']];

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $sql .= " ORDER BY name LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success(['customers' => $customers]);
}

function get_mobile_orders($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 10);

    $stmt = $pdo->prepare("
        SELECT
            s.id, s.total, s.payment_method, s.created_at,
            c.name as customer_name, s.status
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.tenant_id = ?
        AND s.branch_id = ?
        ORDER BY s.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$company['id'], $branch_id, $limit]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success(['orders' => $orders]);
}

function get_mobile_inventory($pdo, $company) {
    $branch_id = (int) ($_GET['branch_id'] ?? 1);

    $stmt = $pdo->prepare("
        SELECT
            p.id, p.name, p.sku,
            COALESCE(i.stock, 0) as current_stock,
            COALESCE(i.reorder_level, 10) as reorder_level
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
        WHERE p.tenant_id = ?
        AND p.active = 1
        AND COALESCE(i.stock, 0) <= COALESCE(i.reorder_level, 10)
        AND COALESCE(i.stock, 0) > 0
        ORDER BY COALESCE(i.stock, 0) ASC
        LIMIT 5
    ");
    $stmt->execute([$branch_id, $company['id'], $company['id']]);
    $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success(['low_stock_items' => $low_stock]);
}

function handle_mobile_shift($pdo, $company) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Get current shift status
        $user_id = (int) ($_GET['user_id'] ?? 1);
        $branch_id = (int) ($_GET['branch_id'] ?? 1);

        $stmt = $pdo->prepare("
            SELECT id, start_time, end_time, status, opening_balance, closing_balance
            FROM shifts
            WHERE user_id = ? AND branch_id = ? AND DATE(start_time) = CURDATE()
            ORDER BY start_time DESC LIMIT 1
        ");
        $stmt->execute([$user_id, $branch_id]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        mobile_success(['shift' => $shift]);

    } elseif ($method === 'POST') {
        $data = validate_mobile_request(['action', 'user_id', 'branch_id']);

        $action = $data['action']; // 'start' or 'end'
        $user_id = (int) $data['user_id'];
        $branch_id = (int) $data['branch_id'];

        if ($action === 'start') {
            $opening_balance = (float) ($data['opening_balance'] ?? 0);

            $stmt = $pdo->prepare("
                INSERT INTO shifts (user_id, branch_id, start_time, opening_balance, status)
                VALUES (?, ?, NOW(), ?, 'active')
            ");
            $stmt->execute([$user_id, $branch_id, $opening_balance]);
            $shift_id = $pdo->lastInsertId();

            mobile_success(['shift_id' => $shift_id], 'Shift started successfully');

        } elseif ($action === 'end') {
            $closing_balance = (float) ($data['closing_balance'] ?? 0);
            $notes = $data['notes'] ?? '';

            $stmt = $pdo->prepare("
                UPDATE shifts
                SET end_time = NOW(), closing_balance = ?, status = 'closed', notes = ?
                WHERE user_id = ? AND branch_id = ? AND status = 'active'
                ORDER BY start_time DESC LIMIT 1
            ");
            $stmt->execute([$closing_balance, $notes, $user_id, $branch_id]);

            mobile_success(null, 'Shift ended successfully');
        } else {
            mobile_error('Invalid shift action', 400);
        }
    }
}

function sync_mobile_data($pdo, $company) {
    $last_sync = $_GET['last_sync'] ?? null;
    $branch_id = (int) ($_GET['branch_id'] ?? 1);

    $sync_data = [
        'products_updated' => [],
        'inventory_updates' => [],
        'sales_summary' => []
    ];

    // Get products updated since last sync
    if ($last_sync) {
        $stmt = $pdo->prepare("
            SELECT id, name, price, stock
            FROM products
            WHERE tenant_id = ?
            AND deleted_at IS NULL
            AND updated_at > ?
            LIMIT 100
        ");
        $stmt->execute([$company['id'], $last_sync]);
        $sync_data['products_updated'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get recent sales summary
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_sales, SUM(total) as total_revenue
        FROM sales
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $sync_data['sales_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

    mobile_success($sync_data);
}

function update_inventory_stock($pdo, $product_id, $branch_id, $tenant_id, $quantity_change) {
    // Check if inventory record exists
    $stmt = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
    $stmt->execute([$product_id, $branch_id, $tenant_id]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inventory) {
        $new_stock = $inventory['stock'] + $quantity_change;
        $stmt = $pdo->prepare("UPDATE inventory SET stock = ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
        $stmt->execute([$new_stock, $product_id, $branch_id, $tenant_id]);
    } else {
        // Create inventory record
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, tenant_id, stock, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$product_id, $branch_id, $tenant_id, $quantity_change]);
    }
}
