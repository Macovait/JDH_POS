<?php

require_once __DIR__ . '/../../src/Security/CorsHandler.php';
// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * JDH POS API - Products Endpoint
 * Standardized REST API for product management
 * 
 * Endpoints:
 *   GET    /api/products.php              - List all products
 *   GET    /api/products.php?id=123      - Get single product
 *   POST   /api/products.php              - Create product
 *   PUT    /api/products.php?id=123       - Update product
 *   DELETE /api/products.php?id=123       - Delete product
 */

require_once __DIR__ . '/../../src/ApiBase.php';

class ProductsApi extends ApiBase {
    
    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->handleGet();
                break;
            case 'POST':
                $this->handlePost();
                break;
            case 'PUT':
                $this->handlePut();
                break;
            case 'DELETE':
                $this->handleDelete();
                break;
            case 'OPTIONS':
                // CORS preflight
                \Jakababa\Security\apply_cors_headers();
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
                exit;
            default:
                $this->methodNotAllowed(['GET', 'POST', 'PUT', 'DELETE']);
        }
    }
    
    /**
     * GET - List or get single product
     */
    private function handleGet(): void {
        $id = $_GET['id'] ?? null;
        
        if ($id) {
            // Get single product
            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as category_name, b.name as brand_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
            ");
            $stmt->execute([$id, $this->tenant_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $this->error('Product not found', self::HTTP_NOT_FOUND);
            }
            
            $this->success($product);
        }
        
        // List products with pagination and filters
        $pagination = $this->getPagination();
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';
        
        // Build query
        $where = ['p.tenant_id = ?', 'p.deleted_at IS NULL'];
        $params = [$this->tenant_id];
        
        if ($search) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($category) {
            $where[] = 'p.category_id = ?';
            $params[] = $category;
        }
        
        if ($status !== '') {
            $where[] = 'p.is_active = ?';
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM products p {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // Get products
        $sql = "
            SELECT p.id, p.name, p.sku, p.barcode, p.selling_price, p.cost_price,
                   p.is_active, p.created_at, p.updated_at,
                   c.name as category_name, b.name as brand_name,
                   COALESCE(i.current_stock, 0) as current_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN inventory i ON p.id = i.product_id AND i.tenant_id = p.tenant_id
            {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        $this->paginated($products, $total, $pagination['page'], $pagination['perPage']);
    }
    
    /**
     * POST - Create new product
     */
    private function handlePost(): void {
        $input = $this->getInput();
        
        // Validate input
        $this->validate($input, [
            'name' => 'required|min:2',
            'sku' => 'required|min:3',
            'selling_price' => 'required|numeric'
        ]);
        
        // Check SKU uniqueness
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE sku = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$input['sku'], $this->tenant_id]);
        if ($stmt->fetch()) {
            $this->error('SKU already exists', self::HTTP_BAD_REQUEST, ['sku' => 'SKU must be unique']);
        }
        
        // Generate barcode if not provided
        $barcode = $input['barcode'] ?? $this->generateBarcode();
        
        // Insert product
        $sql = "
            INSERT INTO products (tenant_id, name, sku, barcode, description, 
                                  category_id, brand_id, cost_price, selling_price,
                                  tax_rate, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $this->tenant_id,
            $input['name'],
            $input['sku'],
            $barcode,
            $input['description'] ?? null,
            $input['category_id'] ?? null,
            $input['brand_id'] ?? null,
            $input['cost_price'] ?? 0,
            $input['selling_price'],
            $input['tax_rate'] ?? 0,
            $input['is_active'] ?? 1
        ]);
        
        $productId = $this->pdo->lastInsertId();
        
        // Initialize inventory if stock provided
        if (!empty($input['initial_stock']) && $input['initial_stock'] > 0) {
            $this->initializeInventory($productId, (int)$input['initial_stock']);
        }
        
        // Fetch created product
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        $this->success($product, 'Product created successfully', self::HTTP_CREATED);
    }
    
    /**
     * PUT - Update product
     */
    private function handlePut(): void {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', self::HTTP_BAD_REQUEST);
        }
        
        $input = $this->getInput();
        
        // Check product exists
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, $this->tenant_id]);
        if (!$stmt->fetch()) {
            $this->error('Product not found', self::HTTP_NOT_FOUND);
        }
        
        // Check SKU uniqueness if changing
        if (!empty($input['sku'])) {
            $stmt = $this->pdo->prepare("SELECT id FROM products WHERE sku = ? AND tenant_id = ? AND id != ? AND deleted_at IS NULL");
            $stmt->execute([$input['sku'], $this->tenant_id, $id]);
            if ($stmt->fetch()) {
                $this->error('SKU already exists', self::HTTP_BAD_REQUEST);
            }
        }
        
        // Build update query
        $allowedFields = ['name', 'sku', 'barcode', 'description', 'category_id', 'brand_id', 
                         'cost_price', 'selling_price', 'tax_rate', 'is_active'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $column = $field === 'is_active' ? 'active' : $field;
                $updates[] = "{$column} = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            $this->error('No fields to update', self::HTTP_BAD_REQUEST);
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        $params[] = $this->tenant_id;
        
        $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        // Fetch updated product
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        $this->success($product, 'Product updated successfully');
    }
    
    /**
     * DELETE - Soft delete product
     */
    private function handleDelete(): void {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->error('Product ID is required', self::HTTP_BAD_REQUEST);
        }
        
        // Check product exists
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, $this->tenant_id]);
        if (!$stmt->fetch()) {
            $this->error('Product not found', self::HTTP_NOT_FOUND);
        }
        
        // Soft delete
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET deleted_at = NOW(), active = 0, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$id, $this->tenant_id]);
        
        $this->success(null, 'Product deleted successfully', self::HTTP_NO_CONTENT);
    }
    
    /**
     * Generate unique barcode
     */
    private function generateBarcode(): string {
        $prefix = 'JDH';
        $random = strtoupper(substr(uniqid(), -8));
        return $prefix . $random;
    }
    
    /**
     * Initialize inventory for new product
     */
    private function initializeInventory(int $productId, int $quantity): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory (tenant_id, product_id, branch_id, stock, minimum_stock, created_at, updated_at)
            VALUES (?, ?, ?, ?, 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE stock = stock + ?, updated_at = NOW()
        ");
        $stmt->execute([$this->tenant_id, $productId, $this->branch_id, $quantity, $quantity]);
        
        // Log stock movement
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_movements (tenant_id, branch_id, product_id, movement_type, quantity_change, quantity_before, quantity_after, notes, created_at)
            VALUES (?, ?, ?, 'adjustment', ?, 0, ?, 'Initial stock on product creation', NOW())
        ");
        $stmt->execute([$this->tenant_id, $this->branch_id, $productId, $quantity, 0, $quantity]);
    }
}

// Run the API
$api = new ProductsApi();
$api->handle();
