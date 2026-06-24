<?php
declare(strict_types=1);

/**
 * POS Backend - Core API Layer
 *
 * Centralizes all POS business logic with:
 * - Strict tenant isolation (tenant_id on every query)
 * - Branch filtering (branch_id for branch-scoped data)
 * - Business type driven behavior (restaurant, supermarket, pharmacy, etc.)
 * - PDO prepared statements only
 * - Clean error handling
 *
 * @package Jakababa
 * @subpackage POS Backend
 * @version 3.0
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// ================================================================
//  SESSION GUARD
// ================================================================

function pos_require_session(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $required = ['tenant_id', 'branch_id', 'user_id'];
    foreach ($required as $key) {
        if (empty($_SESSION[$key])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => "Session expired or invalid. Please log in again.",
                'code' => 'SESSION_MISSING_' . strtoupper($key)
            ]);
            exit;
        }
    }

    $business_type = $_SESSION['business_type'] ?? 'retail';

    // Validate business_type against known types
    $valid_types = array_keys(get_business_type_config() ?: []);
    if (!empty($valid_types) && !in_array($business_type, $valid_types, true)) {
        $business_type = 'retail';
    }

    // Get business_type_id from session (set from company or user selection)
    $business_type_id = null;
    $tenant_id = (int) $_SESSION['tenant_id'];
    if (!empty($_SESSION['business_type_id'])) {
        $business_type_id = (int) $_SESSION['business_type_id'];
    } elseif (!empty($_SESSION['business_type_id_' . $tenant_id])) {
        $business_type_id = (int) $_SESSION['business_type_id_' . $tenant_id];
    }

    return [
        'tenant_id'       => (int) $_SESSION['tenant_id'],
        'branch_id'        => (int) $_SESSION['branch_id'],
        'user_id'          => (int) $_SESSION['user_id'],
        'business_type'    => $business_type,
        'business_type_id' => $business_type_id,
    ];
}

// ================================================================
//  JSON RESPONSE HELPERS
// ================================================================

function pos_json_success(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function pos_json_error(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ================================================================
//  SCHEMA DETECTION (cached per request)
// ================================================================

function pos_table_has_col(\PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            $cache[$key] = $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            $cache[$key] = false;
        }
    }
    return $cache[$key];
}

function pos_table_exists(\PDO $pdo, string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            $cache[$table] = true;
        } catch (\Exception $e) {
            $cache[$table] = false;
        }
    }
    return $cache[$table];
}

// ================================================================
//  BUSINESS TYPE FEATURE CONFIG
// ================================================================

/**
 * Check if a business type has a specific feature enabled.
 *
 * @param string      $business_type Business type code
 * @param string      $feature       Feature key
 * @param mixed       $default       Default value if not set
 * @return mixed
 */
function pos_get_bt_feature(string $business_type, string $feature, $default = false)
{
    static $cache = [];
    if (!isset($cache[$business_type])) {
        $config = get_business_type_config($business_type);
        $cache[$business_type] = $config['features'] ?? [];
    }
    return $cache[$business_type][$feature] ?? $default;
}

/**
 * Get POS-specific configuration based on business type.
 * Returns a clean config array for frontend consumption.
 *
 * @param string $business_type
 * @return array
 */
function pos_get_type_config(string $business_type): array
{
    $config = get_business_type_config($business_type);
    if (!$config) {
        $config = get_business_type_config('retail') ?? [];
    }

    return [
        'type'         => $business_type,
        'name'         => $config['name'] ?? 'Retail',
        'icon'         => $config['icon'] ?? 'fa-store',
        'features'     => $config['features'] ?? [],
        'order_types'  => $config['order_types'] ?? ['walkin'],
        'order_labels' => $config['order_labels'] ?? [],
        'product_fields' => $config['product_fields'] ?? [],
        'pos_behavior' => pos_resolve_behavior($business_type),
    ];
}

/**
 * Resolve POS behavior flags based on business type.
 *
 * @param string $business_type
 * @return array
 */
function pos_resolve_behavior(string $business_type): array
{
    return match ($business_type) {
        'supermarket' => [
            'barcode_scanning' => true,
            'fast_checkout'    => true,
            'weight_based'     => pos_get_bt_feature('supermarket', 'weight_based', false),
            'show_expiry'      => pos_get_bt_feature('supermarket', 'expiry_tracking', false),
            'default_payment'  => 'cash',
        ],
        'restaurant' => [
            'table_management' => pos_get_bt_feature('restaurant', 'table_management', false),
            'kitchen_display'  => pos_get_bt_feature('restaurant', 'kitchen_display', false),
            'split_bills'      => true,
            'order_required'   => true,
            'default_payment'  => 'cash',
        ],
        'pharmacy' => [
            'show_expiry'          => true,
            'batch_tracking'       => pos_get_bt_feature('pharmacy', 'batch_tracking', false),
            'prescription_required' => pos_get_bt_feature('pharmacy', 'prescription_required', false),
            'default_payment'      => 'cash',
        ],
        'hotel' => [
            'room_service'    => pos_get_bt_feature('hotel', 'room_service', false),
            'split_bills'     => true,
            'default_payment' => 'cash',
        ],
        'salon' => [
            'appointments'    => pos_get_bt_feature('salon', 'appointments', false),
            'default_payment' => 'cash',
        ],
        'liquor_store' => [
            'age_verification' => pos_get_bt_feature('liquor_store', 'age_verification', false),
            'barcode_scanning' => true,
            'default_payment'  => 'cash',
        ],
        'butchery' => [
            'weight_based'    => true,
            'default_payment' => 'cash',
        ],
        'bakery' => [
            'show_expiry'     => pos_get_bt_feature('bakery', 'freshness_tracking', false),
            'default_payment' => 'cash',
        ],
        'electronics' => [
            'warranty_tracking' => pos_get_bt_feature('electronics', 'warranty_tracking', false),
            'serial_tracking'   => pos_get_bt_feature('electronics', 'serial_tracking', false),
            'barcode_scanning'  => true,
            'default_payment'   => 'cash',
        ],
        'hardware' => [
            'weight_based'    => pos_get_bt_feature('hardware', 'weight_based', false),
            'barcode_scanning' => true,
            'default_payment'  => 'cash',
        ],
        'stationery' => [
            'barcode_scanning' => true,
            'default_payment'  => 'cash',
        ],
        default => [
            'barcode_scanning' => pos_get_bt_feature('retail', 'barcode_scanning', true),
            'default_payment'  => 'cash',
        ],
    };
}

// ================================================================
//  PRODUCT FETCHING
// ================================================================

/**
 * Fetch products for POS display.
 * - STRICTLY filters by tenant_id (tenant isolation)
 * - Joins inventory for branch-specific stock
 * - Adapts output based on business_type
 * - Optionally filter by user_id (created_by) for user-specific products
 *
 * @return array{products: array, categories: array, low_stock_count: int}
 */
function pos_fetch_products(\PDO $pdo, int $tenant_id, int $branch_id, string $business_type, ?int $business_type_id = null, array $filters = []): array
{
    // Column detection
    $has_selling    = pos_table_has_col($pdo, 'products', 'selling_price');
    $has_barcode    = pos_table_has_col($pdo, 'products', 'barcode');
    $has_tax_rate   = pos_table_has_col($pdo, 'products', 'tax_rate');
    $has_reorder    = pos_table_has_col($pdo, 'products', 'reorder_level');
    $has_expiry     = pos_table_has_col($pdo, 'inventory', 'expiry_date');
    $has_batch      = pos_table_has_col($pdo, 'inventory', 'batch_number');
    $has_sku        = pos_table_has_col($pdo, 'products', 'sku');
    $has_unit       = pos_table_has_col($pdo, 'products', 'unit');
    $has_image      = pos_table_has_col($pdo, 'products', 'image');
    $has_desc       = pos_table_has_col($pdo, 'products', 'description');
    $has_cat_branch = pos_table_has_col($pdo, 'categories', 'branch_id');
    $has_bt_id      = pos_table_has_col($pdo, 'products', 'business_type_id');
    $has_created_by = pos_table_has_col($pdo, 'products', 'created_by');

    // Business type feature flags
    $features = pos_get_type_config($business_type)['features'];

    // Build SELECT columns
    $select = [
        'p.id', 'p.name', 'p.price', 'p.cost_price', 'p.category_id', 'p.status',
        'COALESCE(p.selling_price, p.price) AS selling_price',
        'COALESCE(i.stock, 0) AS stock',
    ];
    if ($has_sku)        $select[] = 'p.sku';
    if ($has_barcode)    $select[] = 'p.barcode';
    if ($has_tax_rate)   $select[] = 'COALESCE(p.tax_rate, 0) AS tax_rate';
    if ($has_reorder)    $select[] = 'COALESCE(i.reorder_level, p.reorder_level, 0) AS reorder_level';
    if ($has_unit)       $select[] = "COALESCE(p.unit, 'pcs') AS unit";
    if ($has_image)      $select[] = 'p.image';
    if ($has_desc)       $select[] = 'p.description';
    if ($has_expiry && ($features['expiry_tracking'] ?? false)) {
        $select[] = 'i.expiry_date';
    }
    if ($has_batch && ($features['batch_tracking'] ?? false)) {
        $select[] = 'i.batch_number';
    }

    // TENANT ISOLATION: Always filter by tenant_id, business_type_id, branch_id
    $sql = "SELECT " . implode(', ', $select) . "
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL";

    $params = [$branch_id, $tenant_id, $tenant_id];

    // Business type filter - SaaS multi-tenant filtering
    // Show products matching the current business_type_id OR untagged products
    if ($has_bt_id && $business_type_id) {
        $sql .= " AND (p.business_type_id = ? OR p.business_type_id IS NULL)";
        $params[] = $business_type_id;
    }

    // User filter - filter by created_by (only if explicitly requested)
    $has_my_products = $has_created_by && !empty($filters['my_products']);
    if ($has_my_products) {
        $sql .= " AND (p.created_by = ? OR p.created_by IS NULL)";
        $params[] = (int) ($filters['user_id'] ?? 0);
    }

    // Category filter
    $has_category = !empty($filters['category_id']);
    if ($has_category) {
        $sql .= " AND p.category_id = ?";
        $params[] = (int) $filters['category_id'];
    }

    // Search filter
    $has_search = !empty($filters['search']);
    if ($has_search) {
        $search = '%' . $filters['search'] . '%';
        if ($has_barcode && $has_sku) {
            $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        } else {
            $sql .= " AND p.name LIKE ?";
            $params[] = $search;
        }
    }

    // Stock filter - only show in-stock for POS
    if (($filters['in_stock'] ?? true) && !($filters['show_all'] ?? false)) {
        $sql .= " AND COALESCE(i.stock, 0) > 0";
    }

    $sql .= " ORDER BY p.name ASC";

    // Pagination
    $limit = min((int) ($filters['limit'] ?? 50), 200);
    $offset = max((int) ($filters['offset'] ?? 0), 0);

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Format products
    foreach ($products as &$p) {
        $p['id']           = (int) $p['id'];
        $p['price']        = (float) $p['selling_price'];
        $p['cost_price']   = (float) $p['cost_price'];
        $p['stock']        = (int) $p['stock'];
        $p['category_id']  = (int) ($p['category_id'] ?? 0);
        $p['status']       = (int) ($p['status'] ?? 1);
        unset($p['selling_price']);
    }
    unset($p);

    // Attach product tags (if tables exist)
    if (!empty($products) && pos_table_exists($pdo, 'product_tags') && pos_table_exists($pdo, 'product_tag_relations')) {
        $productIds = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $tagStmt = $pdo->prepare("SELECT r.product_id, t.name, t.color
            FROM product_tag_relations r
            JOIN product_tags t ON t.id = r.tag_id
            WHERE r.product_id IN ($placeholders) AND r.tenant_id = ? AND t.is_active = 1
            ORDER BY t.sort_order ASC, t.name ASC");
        $tagStmt->execute(array_merge($productIds, [$tenant_id]));
        $tagMap = [];
        while ($row = $tagStmt->fetch(\PDO::FETCH_ASSOC)) {
            $pid = (int) $row['product_id'];
            if (!isset($tagMap[$pid])) $tagMap[$pid] = [];
            $tagMap[$pid][] = ['name' => $row['name'], 'color' => $row['color']];
        }
        foreach ($products as &$p) {
            $p['tags'] = $tagMap[$p['id']] ?? [];
        }
        unset($p);
    }

    // Categories with product counts (TENANT ISOLATION: tenant_id filter)
    $cat_sql = "SELECT c.id, c.name, COUNT(p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
                WHERE c.tenant_id = ?";
    $cat_params = [$tenant_id, $tenant_id];
    if ($has_cat_branch) {
        $cat_sql .= " AND (c.branch_id = ? OR c.branch_id IS NULL)";
        $cat_params[] = $branch_id;
    }
    $cat_sql .= " GROUP BY c.id ORDER BY c.name ASC";

    $stmt = $pdo->prepare($cat_sql);
    $stmt->execute($cat_params);
    $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Low stock count (TENANT ISOLATION: tenant_id + branch_id)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.tenant_id = ? AND i.branch_id = ?
        AND i.stock <= COALESCE(i.reorder_level, 0)
        AND p.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $low_stock = (int) $stmt->fetchColumn();

    return [
        'products'        => $products,
        'categories'      => $categories,
        'low_stock_count' => $low_stock,
        'business_type'   => $business_type,
        'bt_features'     => $features,
        'bt_behavior'     => pos_resolve_behavior($business_type),
    ];
}

// ================================================================
//  INVOICE GENERATION
// ================================================================

function pos_generate_invoice(\PDO $pdo, int $tenant_id, int $branch_id): string
{
    // Ensure invoice_sequences table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_sequences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            branch_id INT NOT NULL,
            year INT(4) NOT NULL,
            month INT(2) NOT NULL,
            last_number INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_seq (tenant_id, branch_id, year, month)
        ) ENGINE=InnoDB");
    } catch (\Exception $e) { /* table already exists */ }

    $year  = (int) date('Y');
    $month = (int) date('m');

    $stmt = $pdo->prepare("
        INSERT INTO invoice_sequences (tenant_id, branch_id, year, month, last_number, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = NOW()
    ");
    $stmt->execute([$tenant_id, $branch_id, $year, $month]);

    $stmt = $pdo->prepare("SELECT last_number FROM invoice_sequences WHERE tenant_id = ? AND branch_id = ? AND year = ? AND month = ?");
    $stmt->execute([$tenant_id, $branch_id, $year, $month]);
    $seq = $stmt->fetch(\PDO::FETCH_ASSOC);

    return sprintf('INV-%s%s-%04d', $year, str_pad((string) $month, 2, '0', STR_PAD_LEFT), (int) ($seq['last_number'] ?? 1));
}

// ================================================================
//  DASHBOARD STATS
// ================================================================

/**
 * Get dashboard statistics for a company/branch.
 * TENANT ISOLATION: Always scoped by tenant_id + branch_id.
 */
function pos_get_dashboard_stats(\PDO $pdo, int $tenant_id, int $branch_id, string $business_type): array
{
    $has_biz = pos_table_has_col($pdo, 'sales', 'business_type');
    $bz = $has_biz ? " AND (s.business_type = ? OR s.business_type IS NULL)" : "";
    $bp = $has_biz ? [$business_type] : [];

// Today's stats (TENANT ISOLATION)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS revenue
        FROM sales s
        WHERE s.tenant_id = ? AND s.branch_id = ? AND s.status = 'completed' AND s.voided = 0
        AND DATE(s.created_at) = CURDATE()$bz
    ");
    $stmt->execute(array_merge([$tenant_id, $branch_id], $bp));
    $today = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    // This month (TENANT ISOLATION)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS revenue
        FROM sales s
        WHERE s.tenant_id = ? AND s.branch_id = ? AND s.status = 'completed' AND s.voided = 0
        AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())$bz
    ");
    $stmt->execute(array_merge([$tenant_id, $branch_id], $bp));
    $month = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Product count (TENANT ISOLATION)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $product_count = (int) $stmt->fetchColumn();

    // Low stock (TENANT ISOLATION)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.tenant_id = ? AND i.branch_id = ?
        AND i.stock <= COALESCE(i.reorder_level, 0) AND p.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $low_stock = (int) $stmt->fetchColumn();

    // Customer count (TENANT ISOLATION)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $customer_count = (int) $stmt->fetchColumn();

// Recent sales (TENANT ISOLATION)
    $stmt = $pdo->prepare("
        SELECT s.id, s.total, s.payment_method, s.created_at,
               COALESCE(c.name, 'Walk-in') AS customer
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.tenant_id = ? AND s.branch_id = ? AND s.status = 'completed' AND s.voided = 0$bz
        ORDER BY s.created_at DESC LIMIT 5
    ");
    $stmt->execute(array_merge([$tenant_id, $branch_id], $bp));
    $recent_sales = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Top products (TENANT ISOLATION)
    $stmt = $pdo->prepare("
        SELECT p.name, SUM(si.quantity) AS qty, SUM(si.quantity * si.price) AS revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id AND p.tenant_id = ?
        JOIN sales s ON si.sale_id = s.id
        WHERE s.tenant_id = ? AND s.branch_id = ? AND s.status = 'completed' AND s.voided = 0
        AND MONTH(s.created_at) = MONTH(CURDATE())$bz
        GROUP BY p.id ORDER BY qty DESC LIMIT 5
    ");
    $stmt->execute(array_merge([$tenant_id, $tenant_id, $branch_id], $bp));
    $top_products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Open register check
    $has_register = pos_table_exists($pdo, 'cash_register');
    $open_registers = 0;
    if ($has_register) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cash_register WHERE tenant_id = ? AND branch_id = ? AND status = 'open'");
        $stmt->execute([$tenant_id, $branch_id]);
        $open_registers = (int) $stmt->fetchColumn();
    }

    return [
        'today' => [
            'sales_count' => (int) $today['cnt'],
            'revenue'     => (float) $today['revenue'],
        ],
        'month' => [
            'sales_count' => (int) $month['cnt'],
            'revenue'     => (float) $month['revenue'],
        ],
        'products'       => $product_count,
        'low_stock'      => $low_stock,
        'customers'      => $customer_count,
        'open_registers' => $open_registers,
        'recent_sales'   => $recent_sales,
        'top_products'   => $top_products,
        'business_type'  => $business_type,
        'bt_behavior'    => pos_resolve_behavior($business_type),
    ];
}

// ================================================================
//  PRODUCT SEARCH (Barcode / Name)
// ================================================================

/**
 * Search product by barcode, SKU, or name.
 * TENANT ISOLATION: Always scoped by tenant_id + branch_id.
 */
function pos_search_product(\PDO $pdo, int $tenant_id, int $branch_id, string $query): ?array
{
    $has_barcode = pos_table_has_col($pdo, 'products', 'barcode');
    $has_sku     = pos_table_has_col($pdo, 'products', 'sku');
    $has_selling = pos_table_has_col($pdo, 'products', 'selling_price');

    $price_col = $has_selling ? 'COALESCE(p.selling_price, p.price)' : 'p.price';

    if ($has_barcode && $has_sku) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.sku, p.barcode, {$price_col} AS price,
                   COALESCE(i.stock, 0) AS stock, p.category_id
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
            AND (p.barcode = ? OR p.sku = ? OR p.name LIKE ?)
            LIMIT 1
        ");
        $like = '%' . $query . '%';
        $stmt->execute([$branch_id, $tenant_id, $tenant_id, $query, $query, $like]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, {$price_col} AS price,
                   COALESCE(i.stock, 0) AS stock, p.category_id
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
            AND p.name LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$branch_id, $tenant_id, $tenant_id, '%' . $query . '%']);
    }

    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['id']    = (int) $row['id'];
    $row['price'] = (float) $row['price'];
    $row['stock'] = (int) $row['stock'];
    return $row;
}

// ================================================================
//  SALE RETURNS
// ================================================================

/**
 * Process a sale return.
 * TENANT ISOLATION: Validates sale belongs to company.
 */
function pos_process_return(\PDO $pdo, int $tenant_id, int $branch_id, int $user_id, int $sale_id, array $items, string $reason = ''): array
{
    // Validate sale belongs to this company
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND tenant_id = ? AND branch_id = ? AND status = 'completed' AND voided = 0");
    $stmt->execute([$sale_id, $tenant_id, $branch_id]);
    $sale = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new \InvalidArgumentException('Sale not found or does not belong to your company/branch');
    }

    $pdo->beginTransaction();
    try {
        $total_refund = 0.0;

        foreach ($items as $item) {
            $product_id = (int) $item['product_id'];
            $quantity   = (int) $item['quantity'];

            if ($product_id <= 0 || $quantity <= 0) {
                throw new \InvalidArgumentException('Invalid product_id or quantity in return items');
            }

            // Validate item exists in sale
            $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? AND product_id = ?");
            $stmt->execute([$sale_id, $product_id]);
            $sale_item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$sale_item) {
                throw new \InvalidArgumentException("Product #$product_id not found in sale #$sale_id");
            }

            $refund_amount = (float) $sale_item['price'] * $quantity;
            $total_refund += $refund_amount;

            // Restore stock (TENANT ISOLATION)
            $stmt = $pdo->prepare("UPDATE inventory SET stock = stock + ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
            $stmt->execute([$quantity, $product_id, $branch_id, $tenant_id]);
        }

        // Record return
        $has_return_table = pos_table_exists($pdo, 'sale_returns');
        if ($has_return_table) {
            $stmt = $pdo->prepare("INSERT INTO sale_returns (sale_id, tenant_id, branch_id, user_id, total_refund, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$sale_id, $tenant_id, $branch_id, $user_id, $total_refund, $reason]);
            $return_id = (int) $pdo->lastInsertId();
        } else {
            $return_id = 0;
        }

        $pdo->commit();

        return [
            'success'      => true,
            'return_id'    => $return_id,
            'sale_id'      => $sale_id,
            'total_refund' => $total_refund,
            'items'        => count($items),
        ];

    } catch (\Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
