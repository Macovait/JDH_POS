<?php
/**
 * REST API Router for Jakababa POS
 * Provides JSON API endpoints for ERP/accounting integrations
 * Supports: Products, Sales, Inventory, Customers, Reports
 *
 * @package Jakababa
 * @subpackage API
 * @version 3.0
 */

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: Jakababa-POS/3.0');

// CORS — validated against allowlist from API_ALLOWED_ORIGINS env
require_once __DIR__ . '/../../../src/Security/CorsHandler.php';
\Jakababa\Security\apply_cors_headers();

require_once __DIR__ . '/../../../src/paths.php';
load_core_files();

// Rate limiting — enforce per-IP throttling
require_once __DIR__ . '/../../../src/Middleware/ApiRateLimitMiddleware.php';
\Jakababa\Middleware\enforce_api_rate_limit('api_requests');

// =============================================================================
// API Authentication
// =============================================================================

class APIAuth
{
    /**
     * Authenticate API request via API key or session
     */
    public static function authenticate(): array
    {
        // Check for API key in header
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        $companyId = $_SERVER['HTTP_X_COMPANY_ID'] ?? $_GET['tenant_id'] ?? null;

        if ($apiKey) {
            return self::authenticateApiKey($apiKey, $companyId);
        }

        // Fall back to session authentication
        if (isset($_SESSION['user_id']) && isset($_SESSION['tenant_id'])) {
            return [
                'authenticated' => true,
                'user_id' => $_SESSION['user_id'],
                'tenant_id' => $_SESSION['tenant_id'],
                'branch_id' => $_SESSION['branch_id'] ?? null,
                'method' => 'session'
            ];
        }

        return ['authenticated' => false, 'error' => 'Authentication required'];
    }

    private static function authenticateApiKey(string $key, ?int $companyId): array
    {
        try {
            $hash = hash('sha256', $key);
            $apiToken = db_fetch_one(
                "SELECT at.*, u.id as user_id FROM api_tokens at 
                 JOIN users u ON u.id = at.user_id 
                 WHERE at.token_hash = ? AND at.active = 1 
                 AND (at.expires_at IS NULL OR at.expires_at > NOW())",
                [$hash]
            );

            if (!$apiToken) {
                return ['authenticated' => false, 'error' => 'Invalid API key'];
            }

            return [
                'authenticated' => true,
                'user_id' => (int) $apiToken['user_id'],
                'tenant_id' => (int) ($companyId ?? $apiToken['tenant_id']),
                'branch_id' => null,
                'method' => 'api_key',
                'scopes' => json_decode($apiToken['scopes'] ?? '[]', true) ?: []
            ];
        } catch (Exception $e) {
            return ['authenticated' => false, 'error' => 'Authentication error'];
        }
    }

    /**
     * Generate a new API key
     */
    public static function generateApiKey(int $userId, int $companyId, array $scopes = [], ?string $name = null, ?string $expiresAt = null): array
    {
        $key = 'jp_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $key);

        db_insert('api_tokens', [
            'user_id' => $userId,
            'tenant_id' => $companyId,
            'token_hash' => $hash,
            'name' => $name ?? 'API Key',
            'scopes' => json_encode($scopes),
            'active' => 1,
            'expires_at' => $expiresAt
        ]);

        return [
            'api_key' => $key,
            'message' => 'Store this key securely. It will not be shown again.'
        ];
    }
}

// =============================================================================
// API Response Helper
// =============================================================================

class APIResponse
{
    public static function success($data, int $code = 200, array $meta = []): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'meta' => array_merge($meta, [
                'timestamp' => date('c'),
                'version' => '3.0'
            ])
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $code = 400, array $details = []): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        self::success($items, 200, [
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => ceil($total / $perPage)
            ]
        ]);
    }
}

// =============================================================================
// Request Parser
// =============================================================================

class APIRequest
{
    public static function getJsonBody(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    public static function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function getIntParam(string $key, int $default = 0): int
    {
        return (int) ($_GET[$key] ?? $default);
    }

    public static function getPagination(): array
    {
        $page = max(1, self::getIntParam('page', 1));
        $perPage = min(100, max(1, self::getIntParam('per_page', 20)));
        $offset = ($page - 1) * $perPage;
        return compact('page', 'perPage', 'offset');
    }

    public static function getDateRange(): array
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        return ['from' => $from, 'to' => $to];
    }
}

// =============================================================================
// API Route Handlers
// =============================================================================

class APIHandler
{
    private array $auth;
    private int $companyId;
    private ?int $branchId;

    public function __construct(array $auth)
    {
        $this->auth = $auth;
        $this->companyId = $auth['tenant_id'];
        $this->branchId = $auth['branch_id'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Products
    // -------------------------------------------------------------------------

    public function getProducts(): void
    {
        $pagination = APIRequest::getPagination();
        $search = APIRequest::getParam('search');
        $categoryId = APIRequest::getIntParam('category_id');
        $active = APIRequest::getParam('active', '1');

        $where = ['p.tenant_id = ?'];
        $params = [$this->companyId];

        if ($search) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($categoryId) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        if ($active !== null) {
            $where[] = 'p.active = ?';
            $params[] = (int) $active;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) db_fetch_value(
            "SELECT COUNT(*) FROM products p WHERE {$whereClause} AND p.deleted_at IS NULL",
            $params
        );

        $products = db_fetch_all(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON c.id = p.category_id 
             WHERE {$whereClause} AND p.deleted_at IS NULL 
             ORDER BY p.name 
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
            $params
        );

        APIResponse::paginated($products, $total, $pagination['page'], $pagination['perPage']);
    }

    public function getProduct(int $id): void
    {
        $product = db_fetch_one(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON c.id = p.category_id 
             WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL",
            [$id, $this->companyId]
        );

        if (!$product) {
            APIResponse::error('Product not found', 404);
        }

        // Include inventory
        $inventory = db_fetch_all(
            "SELECT i.branch_id, b.name as branch_name, i.stock, i.reorder_level 
             FROM inventory i 
             JOIN branches b ON b.id = i.branch_id 
             WHERE i.product_id = ?",
            [$id]
        );

        $product['inventory'] = $inventory;
        APIResponse::success($product);
    }

    public function createProduct(): void
    {
        $data = APIRequest::getJsonBody();
        $required = ['name', 'price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                APIResponse::error("Missing required field: {$field}", 422);
            }
        }

        $productData = [
            'tenant_id' => $this->companyId,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? generate_sku($data['name']),
            'barcode' => $data['barcode'] ?? null,
            'price' => (float) $data['price'],
            'cost_price' => (float) ($data['cost_price'] ?? 0),
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'] ?? 'pcs',
            'tax_rate' => (float) ($data['tax_rate'] ?? 16),
            'active' => 1
        ];

        $id = db_insert('products', $productData);

        // Cache bust
        cache_forget(cache()->productKey($id, $this->companyId));

        APIResponse::success(['id' => $id, 'message' => 'Product created'], 201);
    }

    public function updateProduct(int $id): void
    {
        $data = APIRequest::getJsonBody();

        $exists = db_fetch_one(
            "SELECT id FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",
            [$id, $this->companyId]
        );

        if (!$exists) {
            APIResponse::error('Product not found', 404);
        }

        $allowed = ['name', 'sku', 'barcode', 'price', 'cost_price', 'category_id', 'description', 'unit', 'tax_rate', 'active'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($update)) {
            db_update('products', $update, 'id = ? AND tenant_id = ?', [$id, $this->companyId]);
            cache_forget(cache()->productKey($id, $this->companyId));
        }

        APIResponse::success(['id' => $id, 'message' => 'Product updated']);
    }

    public function deleteProduct(int $id): void
    {
        $affected = db_update(
            'products',
            ['deleted_at' => date('Y-m-d H:i:s')],
            'id = ? AND tenant_id = ?',
            [$id, $this->companyId]
        );

        if ($affected === 0) {
            APIResponse::error('Product not found', 404);
        }

        cache_forget(cache()->productKey($id, $this->companyId));
        APIResponse::success(['message' => 'Product deleted']);
    }

    // -------------------------------------------------------------------------
    // Sales
    // -------------------------------------------------------------------------

    public function getSales(): void
    {
        $pagination = APIRequest::getPagination();
        $dateRange = APIRequest::getDateRange();
        $branchId = APIRequest::getIntParam('branch_id');

        $where = ['s.tenant_id = ?', 'DATE(s.created_at) BETWEEN ? AND ?'];
        $params = [$this->companyId, $dateRange['from'], $dateRange['to']];

        if ($branchId) {
            $where[] = 's.branch_id = ?';
            $params[] = $branchId;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) db_fetch_value(
            "SELECT COUNT(*) FROM sales s WHERE {$whereClause}",
            $params
        );

        $sales = db_fetch_all(
            "SELECT s.*, b.name as branch_name, u.name as cashier_name, c.name as customer_name
             FROM sales s
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE {$whereClause}
             ORDER BY s.created_at DESC
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
            $params
        );

        APIResponse::paginated($sales, $total, $pagination['page'], $pagination['perPage']);
    }

    public function getSale(int $id): void
    {
        $sale = db_fetch_one(
            "SELECT s.*, b.name as branch_name, u.name as cashier_name, c.name as customer_name
             FROM sales s
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.id = ? AND s.tenant_id = ?",
            [$id, $this->companyId]
        );

        if (!$sale) {
            APIResponse::error('Sale not found', 404);
        }

        $items = db_fetch_all(
            "SELECT si.*, p.name as product_name, p.sku
             FROM sale_items si
             JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = ?",
            [$id]
        );

        $sale['items'] = $items;
        APIResponse::success($sale);
    }

    // -------------------------------------------------------------------------
    // Inventory
    // -------------------------------------------------------------------------

    public function getInventory(): void
    {
        $pagination = APIRequest::getPagination();
        $branchId = APIRequest::getIntParam('branch_id');
        $lowStock = APIRequest::getParam('low_stock');

        $where = ['p.tenant_id = ?'];
        $params = [$this->companyId];

        if ($branchId) {
            $where[] = 'i.branch_id = ?';
            $params[] = $branchId;
        }

        if ($lowStock === '1') {
            $where[] = 'i.stock <= i.reorder_level';
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) db_fetch_value(
            "SELECT COUNT(*) FROM inventory i 
             JOIN products p ON p.id = i.product_id 
             WHERE {$whereClause} AND p.deleted_at IS NULL",
            $params
        );

        $inventory = db_fetch_all(
            "SELECT i.*, p.name as product_name, p.sku, p.barcode, p.price, b.name as branch_name
             FROM inventory i
             JOIN products p ON p.id = i.product_id
             JOIN branches b ON b.id = i.branch_id
             WHERE {$whereClause} AND p.deleted_at IS NULL
             ORDER BY p.name
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
            $params
        );

        APIResponse::paginated($inventory, $total, $pagination['page'], $pagination['perPage']);
    }

    public function updateStock(int $productId): void
    {
        $data = APIRequest::getJsonBody();
        $branchId = $data['branch_id'] ?? $this->branchId;
        $quantity = (int) ($data['quantity'] ?? 0);
        $reason = $data['reason'] ?? 'API adjustment';

        if (!$branchId) {
            APIResponse::error('branch_id is required', 422);
        }

        if (function_exists('update_inventory')) {
            $success = update_inventory($productId, $branchId, $quantity, $reason, $this->auth['user_id']);
            if ($success) {
                APIResponse::success(['message' => 'Stock updated']);
            } else {
                APIResponse::error('Failed to update stock', 500);
            }
        } else {
            APIResponse::error('Inventory function not available', 500);
        }
    }

    // -------------------------------------------------------------------------
    // Customers
    // -------------------------------------------------------------------------

    public function getCustomers(): void
    {
        $pagination = APIRequest::getPagination();
        $search = APIRequest::getParam('search');

        $where = ['tenant_id = ?'];
        $params = [$this->companyId];

        if ($search) {
            $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) db_fetch_value(
            "SELECT COUNT(*) FROM customers WHERE {$whereClause} AND deleted_at IS NULL",
            $params
        );

        $customers = db_fetch_all(
            "SELECT * FROM customers WHERE {$whereClause} AND deleted_at IS NULL 
             ORDER BY name 
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
            $params
        );

        APIResponse::paginated($customers, $total, $pagination['page'], $pagination['perPage']);
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    public function getSalesSummary(): void
    {
        $dateRange = APIRequest::getDateRange();
        $branchId = APIRequest::getIntParam('branch_id');

        $branchFilter = $branchId ? "AND s.branch_id = {$branchId}" : '';

        $summary = db_fetch_one("
            SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(s.total), 0) as total_revenue,
                COALESCE(SUM(s.discount), 0) as total_discount,
                COALESCE(SUM(s.tax), 0) as total_tax,
                COALESCE(AVG(s.total), 0) as average_sale,
                COUNT(DISTINCT s.customer_id) as unique_customers
            FROM sales s
            WHERE s.tenant_id = ?
            AND DATE(s.created_at) BETWEEN ? AND ?
            AND s.status = 'completed'
            {$branchFilter}
        ", [$this->companyId, $dateRange['from'], $dateRange['to']]);

        // Payment breakdown
        $payments = db_fetch_all("
            SELECT payment_method, COUNT(*) as count, SUM(total) as amount
            FROM sales
            WHERE tenant_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
            AND status = 'completed'
            {$branchFilter}
            GROUP BY payment_method
        ", [$this->companyId, $dateRange['from'], $dateRange['to']]);

        // Daily breakdown
        $daily = db_fetch_all("
            SELECT DATE(created_at) as date, COUNT(*) as transactions, SUM(total) as revenue
            FROM sales
            WHERE tenant_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
            AND status = 'completed'
            {$branchFilter}
            GROUP BY DATE(created_at)
            ORDER BY date
        ", [$this->companyId, $dateRange['from'], $dateRange['to']]);

        APIResponse::success([
            'period' => $dateRange,
            'summary' => $summary,
            'payment_breakdown' => $payments,
            'daily_breakdown' => $daily
        ]);
    }

    public function getProductPerformance(): void
    {
        $dateRange = APIRequest::getDateRange();
        $limit = min(100, APIRequest::getIntParam('limit', 20));

        $products = db_fetch_all("
            SELECT 
                p.id, p.name, p.sku,
                SUM(si.quantity) as total_sold,
                SUM(si.quantity * si.price) as total_revenue,
                COUNT(DISTINCT si.sale_id) as transaction_count
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            JOIN products p ON p.id = si.product_id
            WHERE s.tenant_id = ?
            AND DATE(s.created_at) BETWEEN ? AND ?
            AND s.status = 'completed'
            GROUP BY p.id
            ORDER BY total_sold DESC
            LIMIT {$limit}
        ", [$this->companyId, $dateRange['from'], $dateRange['to']]);

        APIResponse::success([
            'period' => $dateRange,
            'products' => $products
        ]);
    }

    // -------------------------------------------------------------------------
    // Branches
    // -------------------------------------------------------------------------

    public function getBranches(): void
    {
        $branches = db_fetch_all(
            "SELECT * FROM branches WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name",
            [$this->companyId]
        );
        APIResponse::success($branches);
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    public function getCategories(): void
    {
        $categories = db_fetch_all(
            "SELECT c.*, COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL
             WHERE c.tenant_id = ? AND c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.name",
            [$this->companyId]
        );
        APIResponse::success($categories);
    }

    // -------------------------------------------------------------------------
    // Webhooks
    // -------------------------------------------------------------------------

    public function registerWebhook(): void
    {
        $data = APIRequest::getJsonBody();

        if (empty($data['url']) || empty($data['events'])) {
            APIResponse::error('url and events are required', 422);
        }

        $id = db_insert('webhooks', [
            'tenant_id' => $this->companyId,
            'url' => $data['url'],
            'events' => json_encode($data['events']),
            'secret' => bin2hex(random_bytes(16)),
            'active' => 1
        ]);

        APIResponse::success(['id' => $id, 'message' => 'Webhook registered'], 201);
    }

    public function getWebhooks(): void
    {
        $webhooks = db_fetch_all(
            "SELECT id, url, events, active, created_at FROM webhooks WHERE tenant_id = ?",
            [$this->companyId]
        );
        APIResponse::success($webhooks);
    }

    public function deleteWebhook(int $id): void
    {
        db_delete('webhooks', 'id = ? AND tenant_id = ?', [$id, $this->companyId]);
        APIResponse::success(['message' => 'Webhook deleted']);
    }

    // -------------------------------------------------------------------------
    // API Tokens
    // -------------------------------------------------------------------------

    public function createApiToken(): void
    {
        $data = APIRequest::getJsonBody();
        $result = APIAuth::generateApiKey(
            $this->auth['user_id'],
            $this->companyId,
            $data['scopes'] ?? ['read'],
            $data['name'] ?? 'API Token',
            $data['expires_at'] ?? null
        );
        APIResponse::success($result, 201);
    }
}

// =============================================================================
// Store API Handler (Public — no auth required for storefront endpoints)
// =============================================================================

class StoreAPIHandler
{
    private PDO $pdo;
    private int $tenantId;

    public function __construct()
    {
        $this->pdo = get_db_connection();
        $this->tenantId = $this->resolveTenant();
    }

    // Resolve tenant from ?tenant= param, X-Tenant-ID header, or Host header
    private function resolveTenant(): int
    {
        // 1. Explicit query param
        if (!empty($_GET['tenant'])) {
            return (int) $_GET['tenant'];
        }
        // 2. Header (for Next.js SSR calls)
        if (!empty($_SERVER['HTTP_X_TENANT_ID'])) {
            return (int) $_SERVER['HTTP_X_TENANT_ID'];
        }
        // 3. Subdomain resolution  store1.platform.co.ke → tenant
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) {
            $row = db_fetch_one(
                "SELECT tenant_id FROM pos_domains WHERE domain = ? AND active = 1 LIMIT 1",
                [$host]
            );
            if ($row) return (int) $row['tenant_id'];
        }
        return 0;
    }

    private function requireTenant(): void
    {
        if ($this->tenantId <= 0) {
            APIResponse::error('Tenant required. Pass ?tenant=ID or X-Tenant-ID header.', 400);
        }
        // Verify tenant is active
        $active = db_fetch_value(
            "SELECT id FROM pos_tenants WHERE id = ? AND status IN ('active','trial') LIMIT 1",
            [$this->tenantId]
        );
        if (!$active) {
            APIResponse::error('Store not found or inactive.', 404);
        }
    }

    /**
     * Resolve a raw image path/filename into a storefront-friendly URL.
     * If it's already a full URL or starts with '/' it is returned as-is.
     */
    private function resolveImageUrl(?string $path, string $folder = 'product_images'): ?string
    {
        if (empty($path)) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        // Already resolved (starts with /uploads/)
        if (str_starts_with($path, '/uploads/')) {
            return $path;
        }
        // Path already contains uploads/ somewhere (with or without leading slash)
        if (str_contains($path, 'uploads/')) {
            $pos = strpos($path, 'uploads/');
            return '/' . substr($path, $pos);
        }
        return '/uploads/' . $folder . '/' . $path;
    }

    // GET /store/tenant — resolve tenant from current host (used by Next.js on boot)
    public function getTenant(): void
    {
        $this->requireTenant();
        $tenant = db_fetch_one(
            "SELECT t.id, t.name, t.slug, t.status,
                    s.setting_key, s.setting_value
             FROM pos_tenants t
             LEFT JOIN storefront_settings s ON s.tenant_id = t.id
             WHERE t.id = ?",
            [$this->tenantId]
        );
        // Collapse settings
        $settings = [];
        $stmt = $this->pdo->prepare(
            "SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?"
        );
        $stmt->execute([$this->tenantId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        APIResponse::success([
            'tenant_id'   => $this->tenantId,
            'name'        => $settings['store_name'] ?? $settings['site_title'] ?? $tenant['name'] ?? 'Store',
            'slug'        => $tenant['slug'] ?? '',
            'currency'    => $settings['currency'] ?? 'KES',
            'logo'        => $this->resolveImageUrl($settings['store_logo'] ?? $settings['logo_url'] ?? null, 'store'),
            'favicon'     => $settings['store_favicon'] ?? $settings['favicon_url'] ?? null,
            'primary_color' => $settings['primary_color'] ?? '#f68b1e',
            'whatsapp'    => $settings['whatsapp_number'] ?? null,
            'online_store_enabled' => !isset($settings['online_store_enabled']) || ($settings['online_store_enabled'] !== '0' && $settings['online_store_enabled'] !== ''),
        ]);
    }

    // GET /store/settings — full storefront configuration
    public function getSettings(): void
    {
        $this->requireTenant();
        $stmt = $this->pdo->prepare(
            "SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?"
        );
        $stmt->execute([$this->tenantId]);
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        // Safe defaults
        $settings += [
            'store_name'          => 'My Store',
            'currency'            => 'KES',
            'primary_color'       => '#f68b1e',
            'online_store_enabled'=> '1',
            'whatsapp_number'     => '',
            'seo_title'           => '',
            'seo_description'     => '',
        ];
        APIResponse::success($settings);
    }

    // GET /store/banners — active storefront banners
    public function getBanners(): void
    {
        $this->requireTenant();
        $banners = db_fetch_all(
            "SELECT id, title, subtitle, image_url, link_url, sort_order
             FROM storefront_banners
             WHERE tenant_id = ? AND active = 1
             ORDER BY sort_order ASC, id ASC",
            [$this->tenantId]
        );
        foreach ($banners as &$b) {
            $b['image_url'] = $this->resolveImageUrl($b['image_url'] ?? null, 'banners');
        }
        APIResponse::success($banners);
    }

    // GET /store/blocks — active content blocks for page builder
    public function getBlocks(): void
    {
        $this->requireTenant();
        $blocks = db_fetch_all(
            "SELECT id, name, content, bg_color, text_color, padding
             FROM storefront_blocks
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY display_order ASC, id ASC",
            [$this->tenantId]
        );
        APIResponse::success($blocks);
    }

    // GET /store/categories — active categories with product counts
    public function getCategories(): void
    {
        $this->requireTenant();
        $categories = db_fetch_all(
            "SELECT c.id, c.name, c.image, c.description,
                    COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
                 AND p.tenant_id = c.tenant_id
                 AND p.active = 1
                 AND p.deleted_at IS NULL
             WHERE c.tenant_id = ?
               AND (c.status = 'active' OR c.status IS NULL)
               AND c.deleted_at IS NULL
             GROUP BY c.id
             HAVING product_count > 0
             ORDER BY c.name ASC",
            [$this->tenantId]
        );
        foreach ($categories as &$c) {
            $c['image'] = $this->resolveImageUrl($c['image'] ?? null, 'category_images');
        }
        APIResponse::success($categories);
    }

    // GET /store/products — paginated product catalog for storefront
    public function getProducts(): void
    {
        $this->requireTenant();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(60, max(1, (int) ($_GET['per_page'] ?? 24)));
        $offset  = ($page - 1) * $perPage;
        $search  = trim($_GET['q'] ?? '');
        $catId   = (int) ($_GET['category'] ?? 0);
        $minPrice= (float) ($_GET['min_price'] ?? 0);
        $maxPrice= (float) ($_GET['max_price'] ?? 0);
        $inStock  = ($_GET['in_stock'] ?? '') === '1';
        $featured = ($_GET['featured'] ?? '') === '1';
        $sort     = in_array($_GET['sort'] ?? '', ['name_asc','name_desc','price_asc','price_desc','newest','popular'])
                   ? $_GET['sort'] : 'newest';

        $where  = ['p.tenant_id = ?', 'p.active = 1', "(p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')"];
        $params = [$this->tenantId];

        if ($catId)     { $where[] = 'p.category_id = ?';  $params[] = $catId; }
        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
            $s = "%{$search}%";
            array_push($params, $s, $s, $s);
        }
        if ($minPrice > 0) { $where[] = 'COALESCE(NULLIF(p.selling_price,0),p.price) >= ?'; $params[] = $minPrice; }
        if ($maxPrice > 0) { $where[] = 'COALESCE(NULLIF(p.selling_price,0),p.price) <= ?'; $params[] = $maxPrice; }

        // Featured filter: join featured_products table
        $featuredJoin = '';
        if ($featured) {
            $where[] = "fp.section = 'featured' AND fp.is_active = 1";
            $featuredJoin = "JOIN featured_products fp ON fp.product_id = p.id AND fp.tenant_id = p.tenant_id";
        }

        $whereSql = implode(' AND ', $where);
        $sortSql  = match($sort) {
            'name_asc'   => 'p.name ASC',
            'name_desc'  => 'p.name DESC',
            'price_asc'  => 'COALESCE(NULLIF(p.selling_price,0),p.price) ASC',
            'price_desc' => 'COALESCE(NULLIF(p.selling_price,0),p.price) DESC',
            'popular'    => 'total_sold DESC',
            default      => 'p.created_at DESC',
        };

        $sql = "
            SELECT p.id, p.name, p.sku, p.price, p.selling_price, p.image,
                   p.description, p.category_id,
                   c.name AS category_name,
                   COALESCE(SUM(i.stock),0) AS stock_quantity,
                   COALESCE(sol.total_sold, 0) AS total_sold
            FROM products p
            {$featuredJoin}
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory  i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS total_sold
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id AND s.tenant_id = {$this->tenantId}
                GROUP BY product_id
            ) sol ON sol.product_id = p.id
            WHERE {$whereSql}
            GROUP BY p.id
        ";

        if ($inStock) $sql .= ' HAVING stock_quantity > 0';
        $sql .= " ORDER BY {$sortSql} LIMIT ? OFFSET ?";
        array_push($params, $perPage, $offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$p) {
            $p['effective_price'] = !empty($p['selling_price']) && (float)$p['selling_price'] > 0
                ? (float)$p['selling_price'] : (float)$p['price'];
            $p['has_discount']    = !empty($p['selling_price']) && (float)$p['selling_price'] > 0
                && (float)$p['selling_price'] < (float)$p['price'];
            $p['discount_pct']    = $p['has_discount']
                ? round((1 - $p['effective_price'] / (float)$p['price']) * 100) : 0;
            $p['in_stock']        = (int)$p['stock_quantity'] > 0;
            $p['is_low_stock']    = (int)$p['stock_quantity'] > 0 && (int)$p['stock_quantity'] <= 5;
            $p['image']           = $this->resolveImageUrl($p['image'] ?? null, 'product_images');
        }

        // Count without LIMIT
        $countParams = array_slice($params, 0, count($params) - 2);
        $countSql = "SELECT COUNT(DISTINCT p.id) FROM products p
                     LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
                     WHERE {$whereSql}";
        $total = (int) db_fetch_value($countSql, $countParams);

        APIResponse::success([
            'products' => $products,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) ceil($total / max(1, $perPage)),
        ]);
    }

    // GET /store/products/{id} — single product detail with reviews
    public function getProduct(int $id): void
    {
        $this->requireTenant();
        $stmt = $this->pdo->prepare(
            "SELECT p.*, c.name AS category_name,
                    COALESCE(SUM(i.stock),0) AS stock_quantity
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN inventory  i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
             WHERE p.id = ? AND p.tenant_id = ? AND p.active = 1
             GROUP BY p.id"
        );
        $stmt->execute([$id, $this->tenantId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            APIResponse::error('Product not found', 404);
        }
        $product['effective_price'] = !empty($product['selling_price']) && (float)$product['selling_price'] > 0
            ? (float)$product['selling_price'] : (float)$product['price'];
        $product['in_stock'] = (int)$product['stock_quantity'] > 0;
        $product['image'] = $this->resolveImageUrl($product['image'] ?? null, 'product_images');

        // Reviews
        $reviews = db_fetch_all(
            "SELECT id, customer_name, rating, review_text AS comment, created_at
             FROM product_reviews
             WHERE product_id = ? AND tenant_id = ? AND status = 'approved'
             ORDER BY created_at DESC LIMIT 20",
            [$id, $this->tenantId]
        );
        $product['reviews']      = $reviews;
        $product['review_count'] = count($reviews);
        $product['avg_rating']   = count($reviews)
            ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;

        // Related products (same category)
        $related = db_fetch_all(
            "SELECT p.id, p.name, p.price, p.selling_price, p.image,
                    COALESCE(SUM(i.stock),0) AS stock_quantity
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
             WHERE p.category_id = ? AND p.tenant_id = ? AND p.active = 1
               AND p.id != ? AND p.deleted_at IS NULL
             GROUP BY p.id
             LIMIT 8",
            [$product['category_id'], $this->tenantId, $id]
        );
        foreach ($related as &$r) {
            $r['effective_price'] = !empty($r['selling_price']) && (float)$r['selling_price'] > 0
                ? (float)$r['selling_price'] : (float)$r['price'];
            $r['has_discount'] = !empty($r['selling_price']) && (float)$r['selling_price'] > 0
                && (float)$r['selling_price'] < (float)$r['price'];
            $r['discount_pct'] = $r['has_discount']
                ? round((1 - $r['effective_price'] / (float)$r['price']) * 100) : 0;
            $r['in_stock']     = (int)$r['stock_quantity'] > 0;
            $r['image']        = $this->resolveImageUrl($r['image'] ?? null, 'product_images');
        }
        $product['related_products'] = $related;

        APIResponse::success($product);
    }

    // POST /store/orders — place a new online order (public endpoint)
    public function placeOrder(): void
    {
        $this->requireTenant();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            APIResponse::error('POST required', 405);
        }
        $data = APIRequest::getJsonBody();

        // Validate required fields
        foreach (['customer_name', 'customer_phone', 'items'] as $f) {
            if (empty($data[$f])) {
                APIResponse::error("Missing required field: {$f}", 422);
            }
        }
        if (!is_array($data['items']) || count($data['items']) === 0) {
            APIResponse::error('Cart is empty', 422);
        }

        // Validate + price items from DB (never trust client prices)
        $total   = 0.0;
        $lineItems = [];
        foreach ($data['items'] as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            if (!$pid) continue;

            $product = db_fetch_one(
                "SELECT id, name, price, selling_price FROM products
                 WHERE id = ? AND tenant_id = ? AND active = 1 AND deleted_at IS NULL",
                [$pid, $this->tenantId]
            );
            if (!$product) continue;

            $price = !empty($product['selling_price']) && (float)$product['selling_price'] > 0
                ? (float)$product['selling_price'] : (float)$product['price'];

            $lineItems[] = [
                'product_id' => $pid,
                'name'       => $product['name'],
                'quantity'   => $qty,
                'price'      => $price,
                'subtotal'   => $price * $qty,
            ];
            $total += $price * $qty;
        }
        if (empty($lineItems)) {
            APIResponse::error('No valid products in cart', 422);
        }

        // Apply coupon if provided
        $discount = 0.0;
        $couponCode = trim($data['coupon_code'] ?? '');
        if ($couponCode) {
            $coupon = db_fetch_one(
                "SELECT * FROM coupons
                 WHERE code = ? AND tenant_id = ? AND active = 1
                   AND (expires_at IS NULL OR expires_at >= CURDATE())
                   AND (usage_limit IS NULL OR used_count < usage_limit)",
                [$couponCode, $this->tenantId]
            );
            if ($coupon) {
                $discount = $coupon['discount_type'] === 'percent'
                    ? round($total * $coupon['discount_value'] / 100, 2)
                    : min((float)$coupon['discount_value'], $total);
            }
        }
        $finalTotal = max(0, $total - $discount);

        // Generate order number
        $orderNumber = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $uuid        = bin2hex(random_bytes(12));

        // Insert order
        $orderId = db_insert('online_orders', [
            'tenant_id'       => $this->tenantId,
            'uuid'            => $uuid,
            'order_number'    => $orderNumber,
            'customer_name'   => $data['customer_name'],
            'customer_phone'  => $data['customer_phone'],
            'customer_email'  => $data['customer_email'] ?? null,
            'delivery_address'=> $data['delivery_address'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'subtotal'        => $total,
            'discount'        => $discount,
            'total'           => $finalTotal,
            'payment_method'  => $data['payment_method'] ?? 'cash',
            'payment_status'  => 'pending',
            'status'          => 'pending',
            'coupon_code'     => $couponCode ?: null,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        if (!$orderId) {
            APIResponse::error('Failed to create order. Please try again.', 500);
        }

        // Insert order items
        foreach ($lineItems as $li) {
            db_insert('online_order_items', [
                'order_id'     => $orderId,
                'tenant_id'    => $this->tenantId,
                'product_id'   => $li['product_id'],
                'product_name' => $li['name'],
                'qty'          => $li['quantity'],
                'price'        => $li['price'],
            ]);
        }

        // Increment coupon usage
        if ($couponCode && !empty($coupon)) {
            db_query(
                "UPDATE coupons SET used_count = used_count + 1 WHERE code = ? AND tenant_id = ?",
                [$couponCode, $this->tenantId]
            );
        }

        // Trigger WhatsApp/SMS notification (async via job table) — best-effort
        try {
            db_insert('notification_jobs', [
                'tenant_id'  => $this->tenantId,
                'type'       => 'order_placed',
                'payload'    => json_encode([
                    'order_id'     => $orderId,
                    'order_number' => $orderNumber,
                    'customer'     => $data['customer_name'],
                    'phone'        => $data['customer_phone'],
                    'total'        => $finalTotal,
                ]),
                'status'     => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('Notification job failed: ' . $e->getMessage());
        }

        APIResponse::success([
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'uuid'         => $uuid,
            'total'        => $finalTotal,
            'discount'     => $discount,
            'status'       => 'pending',
            'track_url'    => '/store/track.php?order=' . $uuid,
            'message'      => 'Order placed successfully!',
        ], 201);
    }

    // GET /store/orders/{uuid} — track order by UUID (public)
    public function trackOrder(string $uuid): void
    {
        $this->requireTenant();
        $order = db_fetch_one(
            "SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.customer_email,
                    o.status, o.payment_status, o.payment_method, o.total, o.tax_amount,
                    o.shipping_amount, o.discount, o.delivery_address, o.shipping_address,
                    o.billing_address, o.notes, o.placed_at, o.shipped_at, o.delivered_at,
                    o.created_at, o.updated_at
             FROM online_orders o
             WHERE o.uuid = ? AND o.tenant_id = ?",
            [$uuid, $this->tenantId]
        );
        if (!$order) {
            APIResponse::error('Order not found', 404);
        }

        $items = db_fetch_all(
            "SELECT product_name, qty AS quantity, price AS unit_price, (price * qty) AS subtotal
             FROM online_order_items
             WHERE order_id = ?",
            [$order['id']]
        );
        $order['items'] = $items;
        $order['uuid']  = $uuid;

        // Build shipping timeline from status history + order timestamps
        $timeline = [];
        $history = db_fetch_all(
            "SELECT from_status, to_status, notes, changed_by_type, created_at
             FROM order_status_history
             WHERE order_id = ? AND tenant_id = ?
             ORDER BY created_at ASC",
            [$order['id'], $this->tenantId]
        );

        $statusMap = [
            'pending'    => ['label' => 'Order Received',    'icon' => 'clock',        'color' => 'yellow'],
            'confirmed'  => ['label' => 'Order Confirmed',   'icon' => 'check-circle', 'color' => 'blue'],
            'processing' => ['label' => 'Being Prepared',    'icon' => 'box',          'color' => 'blue'],
            'shipped'    => ['label' => 'Out for Delivery',  'icon' => 'truck',        'color' => 'indigo'],
            'delivered'  => ['label' => 'Delivered',         'icon' => 'check-double', 'color' => 'green'],
            'cancelled'  => ['label' => 'Cancelled',         'icon' => 'x-circle',     'color' => 'red'],
        ];

        if (!empty($order['placed_at'])) {
            $timeline[] = ['status' => 'pending', 'label' => 'Order Placed', 'timestamp' => $order['placed_at'], 'notes' => 'Order received successfully'];
        }
        if (!empty($order['shipped_at'])) {
            $timeline[] = ['status' => 'shipped', 'label' => 'Out for Delivery', 'timestamp' => $order['shipped_at'], 'notes' => 'Order has been shipped'];
        }
        if (!empty($order['delivered_at'])) {
            $timeline[] = ['status' => 'delivered', 'label' => 'Delivered', 'timestamp' => $order['delivered_at'], 'notes' => 'Order delivered'];
        }

        foreach ($history as $h) {
            $to = $h['to_status'] ?? 'pending';
            $timeline[] = [
                'status'    => $to,
                'label'     => $statusMap[$to]['label'] ?? ucfirst($to),
                'timestamp' => $h['created_at'],
                'notes'     => $h['notes'] ?? ('Status changed to ' . ucfirst($to)),
            ];
        }

        // Sort and remove duplicates by timestamp+status
        usort($timeline, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));
        $seen = [];
        $timeline = array_values(array_filter($timeline, function ($t) use (&$seen) {
            $key = $t['status'] . '|' . $t['timestamp'];
            if (isset($seen[$key])) return false;
            $seen[$key] = true;
            return true;
        }));

        $order['timeline'] = $timeline;
        $order['status_info'] = $statusMap[$order['status']] ?? $statusMap['pending'];

        APIResponse::success($order);
    }

    // POST /store/cart/validate — validate cart items (stock, prices) before checkout
    public function validateCart(): void
    {
        $this->requireTenant();
        $data  = APIRequest::getJsonBody();
        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            APIResponse::error('No items to validate', 422);
        }

        $validated = [];
        $errors    = [];
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $product = db_fetch_one(
                "SELECT p.id, p.name, p.price, p.selling_price,
                        COALESCE(SUM(i.stock),0) AS stock
                 FROM products p
                 LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
                 WHERE p.id = ? AND p.tenant_id = ? AND p.active = 1 AND p.deleted_at IS NULL
                 GROUP BY p.id",
                [$pid, $this->tenantId]
            );
            if (!$product) {
                $errors[] = ['product_id' => $pid, 'error' => 'Product no longer available'];
                continue;
            }
            $stock = (int) $product['stock'];
            if ($stock < $qty) {
                $errors[] = ['product_id' => $pid, 'name' => $product['name'],
                             'error' => "Only {$stock} left in stock", 'available' => $stock];
            }
            $price = !empty($product['selling_price']) && (float)$product['selling_price'] > 0
                ? (float)$product['selling_price'] : (float)$product['price'];
            $validated[] = ['product_id' => $pid, 'name' => $product['name'],
                            'price' => $price, 'quantity' => $qty,
                            'subtotal' => $price * $qty, 'in_stock' => $stock >= $qty];
        }
        APIResponse::success(['items' => $validated, 'errors' => $errors,
                              'valid' => empty($errors)]);
    }

    // POST /store/coupons/validate — check if a coupon is valid
    public function validateCoupon(): void
    {
        $this->requireTenant();
        $data = APIRequest::getJsonBody();
        $code = trim($data['code'] ?? '');
        if (!$code) {
            APIResponse::error('Coupon code required', 422);
        }
        $coupon = db_fetch_one(
            "SELECT id, code, discount_type, discount_value, minimum_order, description
             FROM coupons
             WHERE code = ? AND tenant_id = ? AND active = 1
               AND (expires_at IS NULL OR expires_at >= CURDATE())
               AND (usage_limit IS NULL OR used_count < usage_limit)",
            [$code, $this->tenantId]
        );
        if (!$coupon) {
            APIResponse::error('Invalid or expired coupon', 404);
        }
        APIResponse::success($coupon);
    }

    // GET /store-blocks — typed blocks for page builder (Next.js storefront)
    public function getTypedBlocks(): void
    {
        $this->requireTenant();
        $blocks = db_fetch_all(
            "SELECT id, tenant_id, name, type, props, content, bg_color, text_color, padding, section_class, display_order, is_active
             FROM storefront_blocks
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY display_order ASC, id ASC",
            [$this->tenantId]
        );

        $result = [];
        foreach ($blocks as $b) {
            $props = [];
            if (!empty($b['props'])) {
                $decoded = json_decode($b['props'], true);
                if (is_array($decoded)) {
                    $props = $decoded;
                }
            }

            $blockType = trim($b['type'] ?? '');
            $fallbackContent = '';

            // Backward compat: raw HTML blocks created via blocks.php
            if (empty($blockType) && !empty($b['content'])) {
                $blockType = 'raw-html';
                $fallbackContent = $b['content'];
            } elseif (!empty($b['content']) && empty($props)) {
                $fallbackContent = $b['content'];
            }

            $result[] = [
                'id'            => (int) $b['id'],
                'name'          => $b['name'],
                'type'          => $blockType ?: 'text-section',
                'props'         => $props ?: (object) [],
                'bg_color'      => $b['bg_color'] ?? '#ffffff',
                'text_color'    => $b['text_color'] ?? '#1f2937',
                'padding'       => $b['padding'] ?? 'py-8',
                'section_class' => $b['section_class'] ?? '',
                'display_order' => (int) $b['display_order'],
                'is_active'     => (bool) $b['is_active'],
                '_fallback_html'=> $fallbackContent,
            ];
        }

        APIResponse::success($result);
    }
}

// =============================================================================
// URI Parsing + Public Store Route Dispatch (before auth & rate limiting)
// =============================================================================

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Strip everything up to and including /api/v1 (handles XAMPP subdir and prod)
$apiMarker = '/api/v1';
$markerPos = strpos($uri, $apiMarker);
if ($markerPos !== false) {
    $uri = substr($uri, $markerPos + strlen($apiMarker)) ?: '/';
}

$storeRoutes = [
    'GET /store/tenant'            => 'getTenant',
    'GET /store/settings'          => 'getSettings',
    'GET /store/banners'           => 'getBanners',
    'GET /store/blocks'            => 'getBlocks',
    'GET /store-blocks'            => 'getTypedBlocks',
    'GET /store/categories'        => 'getCategories',
    'GET /store/products'          => 'getProducts',
    'GET /store/products/{id}'     => 'getProduct',
    'POST /store/orders'           => 'placeOrder',
    'GET /store/orders/{uuid}'     => 'trackOrder',
    'POST /store/cart/validate'    => 'validateCart',
    'POST /store/coupons/validate' => 'validateCoupon',
];

foreach ($storeRoutes as $pattern => $methodName) {
    [$routeMethod, $routePath] = explode(' ', $pattern, 2);
    if ($method !== $routeMethod) continue;
    $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath) . '$#';
    if (preg_match($regex, $uri, $matches)) {
        $storeParams = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        try {
            $storeHandler = new StoreAPIHandler();
            if (!empty($storeParams)) {
                call_user_func([$storeHandler, $methodName], ...array_values($storeParams));
            } else {
                call_user_func([$storeHandler, $methodName]);
            }
        } catch (Exception $e) {
            error_log('Store API Error: ' . $e->getMessage());
            APIResponse::error('Internal server error', 500);
        }
        exit;
    }
}

// =============================================================================
// Rate Limiting
// =============================================================================

// Get API key for rate limiting
$apiKeyForLimit = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
$rateLimitWindow = date('Y-m-d H:00:00');

// Default rate limits based on plan
$defaultRateLimit = 1000;
$subscription = db_fetch_one("
    SELECT sp.max_api_calls 
    FROM subscriptions s 
    JOIN subscription_plans sp ON s.plan_id = sp.id 
    WHERE s.tenant_id = ? AND s.status IN ('active', 'trialing') 
    LIMIT 1
", [$auth['tenant_id'] ?? 0]);

if ($subscription && !empty($subscription['max_api_calls'])) {
    $defaultRateLimit = (int) $subscription['max_api_calls'];
}

// Check current usage
$currentUsage = 0;
if ($apiKeyForLimit) {
    $keyHash = hash('sha256', $apiKeyForLimit);
    $usageRecord = db_fetch_one("
        SELECT request_count FROM api_usage_stats 
        WHERE token_hash = ? AND window_start = ?
    ", [$keyHash, $rateLimitWindow]);
    $currentUsage = (int) ($usageRecord['request_count'] ?? 0);
}

// Check limit
if ($currentUsage >= $defaultRateLimit) {
    http_response_code(429);
    header('Retry-After: 3600');
    header('X-RateLimit-Limit: ' . $defaultRateLimit);
    header('X-RateLimit-Remaining: 0');
    APIResponse::error([
        'error' => 'Rate limit exceeded',
        'limit' => $defaultRateLimit,
        'reset_at' => date('Y-m-d H:59:59')
    ], 429);
}

// Increment usage
if ($apiKeyForLimit) {
    $keyHash = hash('sha256', $apiKeyForLimit);
    db_query("
        INSERT INTO api_usage_stats (token_hash, window_start, request_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE request_count = request_count + 1
    ", [$keyHash, $rateLimitWindow]);
}

// Add rate limit headers
header('X-RateLimit-Limit: ' . $defaultRateLimit);
header('X-RateLimit-Remaining: ' . ($defaultRateLimit - $currentUsage - 1));

// =============================================================================
// Route Definition
// =============================================================================

// =============================================================================
// Authenticated Routes
// =============================================================================

// Authenticate
$auth = APIAuth::authenticate();
if (!$auth['authenticated']) {
    APIResponse::error($auth['error'] ?? 'Authentication required', 401);
}

$handler = new APIHandler($auth);

// Route matching
$routes = [
    // Products
    'GET /products' => [$handler, 'getProducts'],
    'GET /products/{id}' => [$handler, 'getProduct'],
    'POST /products' => [$handler, 'createProduct'],
    'PUT /products/{id}' => [$handler, 'updateProduct'],
    'DELETE /products/{id}' => [$handler, 'deleteProduct'],

    // Sales
    'GET /sales' => [$handler, 'getSales'],
    'GET /sales/{id}' => [$handler, 'getSale'],

    // Inventory
    'GET /inventory' => [$handler, 'getInventory'],
    'POST /inventory/{id}/adjust' => [$handler, 'updateStock'],

    // Customers
    'GET /customers' => [$handler, 'getCustomers'],

    // Reports
    'GET /reports/sales-summary' => [$handler, 'getSalesSummary'],
    'GET /reports/product-performance' => [$handler, 'getProductPerformance'],

    // Branches & Categories
    'GET /branches' => [$handler, 'getBranches'],
    'GET /categories' => [$handler, 'getCategories'],

    // Webhooks
    'GET /webhooks' => [$handler, 'getWebhooks'],
    'POST /webhooks' => [$handler, 'registerWebhook'],
    'DELETE /webhooks/{id}' => [$handler, 'deleteWebhook'],

    // API Tokens
    'POST /tokens' => [$handler, 'createApiToken'],
];

// Match route
$matched = false;
foreach ($routes as $pattern => $action) {
    $patternParts = explode(' ', $pattern, 2);
    $routeMethod = $patternParts[0];
    $routePath = $patternParts[1];

    if ($method !== $routeMethod) continue;

    // Convert {id} patterns to regex
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $matches)) {
        $matched = true;

        // Extract named parameters
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        // Call handler
        try {
            if (!empty($params)) {
                call_user_func($action, ...array_values($params));
            } else {
                call_user_func($action);
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            APIResponse::error('Internal server error', 500);
        }
        break;
    }
}

if (!$matched) {
    // 404
    if ($uri === '/' || $uri === '') {
        // API root - show available endpoints
        APIResponse::success([
            'name' => 'Jakababa POS API',
            'version' => '3.0',
            'endpoints' => [
                'products' => '/api/v1/products',
                'sales' => '/api/v1/sales',
                'inventory' => '/api/v1/inventory',
                'customers' => '/api/v1/customers',
                'reports' => '/api/v1/reports/sales-summary',
                'branches' => '/api/v1/branches',
                'categories' => '/api/v1/categories',
                'webhooks' => '/api/v1/webhooks'
            ],
            'documentation' => 'https://docs.jakababa.com/api'
        ]);
    } else {
        APIResponse::error('Endpoint not found', 404);
    }
}
