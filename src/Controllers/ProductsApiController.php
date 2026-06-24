<?php
/**
 * Products API Controller
 * REST API endpoints for product management
 */

declare(strict_types=1);

namespace JDH_POS\Controllers;

use PDOException;

class ProductsApiController extends BaseApiController
{
    /**
     * Handle API request
     */
    public function handleRequest(string $method, array $pathParts = []): void
    {
        $this->requireAuth();
        $this->requireTenant();

        switch ($method) {
            case 'GET':
                if (!empty($pathParts[0])) {
                    $this->getProduct((int)$pathParts[0]);
                } else {
                    $this->getProducts();
                }
                break;

            case 'POST':
                $this->createProduct();
                break;

            case 'PUT':
                if (!empty($pathParts[0])) {
                    $this->updateProduct((int)$pathParts[0]);
                } else {
                    $this->error('Product ID required for update', 400);
                }
                break;

            case 'DELETE':
                if (!empty($pathParts[0])) {
                    $this->deleteProduct((int)$pathParts[0]);
                } else {
                    $this->error('Product ID required for deletion', 400);
                }
                break;

            default:
                $this->error('Method not allowed', 405);
        }
    }

    /**
     * Get products list with filtering and pagination
     */
    private function getProducts(): void
    {
        $this->requirePermission('products', 'view');

        $filters = $_GET;
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['per_page'] ?? 20);
        $search = $filters['search'] ?? '';
        $categoryId = $filters['category_id'] ?? null;
        $active = $filters['active'] ?? null;

        $where = ["p.tenant_id = ?", "p.deleted_at IS NULL"];
        $params = [$this->tenant->getTenantId()];

        if (!empty($search)) {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($categoryId !== null) {
            $where[] = "p.category_id = ?";
            $params[] = $categoryId;
        }

        if ($active !== null) {
            $where[] = "p.active = ?";
            $params[] = (int)$active;
        }

        $whereClause = implode(" AND ", $where);

        try {
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Get products
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name,
                       COALESCE(i.stock, 0) as current_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id AND c.tenant_id = p.tenant_id
                LEFT JOIN (
                    SELECT product_id, SUM(stock) as stock
                    FROM inventory
                    WHERE tenant_id = ?
                    GROUP BY product_id
                ) i ON p.id = i.product_id
                WHERE $whereClause
                ORDER BY p.name ASC
                LIMIT ? OFFSET ?
            ");

            $params[] = $this->tenant->getTenantId();
            $params[] = $perPage;
            $params[] = $offset;

            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->paginated($products, (int)$total, $page, $perPage, 'Products retrieved successfully');

        } catch (PDOException $e) {
            error_log("ProductsApiController::getProducts error: " . $e->getMessage());
            $this->error('Failed to retrieve products', 500);
        }
    }

    /**
     * Get single product
     */
    private function getProduct(int $productId): void
    {
        $this->requirePermission('products', 'view');

        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name,
                       COALESCE(i.total_stock, 0) as current_stock
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id AND c.tenant_id = p.tenant_id
                LEFT JOIN (
                    SELECT product_id, SUM(stock) as total_stock
                    FROM inventory
                    WHERE tenant_id = ?
                    GROUP BY product_id
                ) i ON p.id = i.product_id
                WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$this->tenant->getTenantId(), $productId, $this->tenant->getTenantId()]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $this->error('Product not found', 404);
            }

            // Get product variants
            $stmt = $this->db->prepare("
                SELECT * FROM product_variants
                WHERE product_id = ? AND tenant_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$productId, $this->tenant->getTenantId()]);
            $product['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get product images
            $stmt = $this->db->prepare("
                SELECT * FROM product_images
                WHERE product_id = ? AND tenant_id = ?
                ORDER BY sort_order ASC
            ");
            $stmt->execute([$productId, $this->tenant->getTenantId()]);
            $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->success($product, 'Product retrieved successfully');

        } catch (PDOException $e) {
            error_log("ProductsApiController::getProduct error: " . $e->getMessage());
            $this->error('Failed to retrieve product', 500);
        }
    }

    /**
     * Create new product
     */
    private function createProduct(): void
    {
        $this->requirePermission('products', 'create');

        $data = $this->getJsonInput();
        $this->validateRequired($data, ['name', 'price']);

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO products (
                    tenant_id, category_id, name, sku, description, price, cost_price,
                    active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->tenant->getTenantId(),
                $data['category_id'] ?? null,
                $data['name'],
                $data['sku'] ?? null,
                $data['description'] ?? null,
                $data['price'],
                $data['cost_price'] ?? 0,
                $data['active'] ?? 1
            ]);

            $productId = $this->db->lastInsertId();

            // Add initial inventory if specified
            if (isset($data['initial_stock']) && $data['initial_stock'] > 0) {
                $this->addInitialInventory($productId, $data['initial_stock'], $data['branch_id'] ?? 1);
            }

            $this->db->commit();

            $this->logRequest('product_created', ['product_id' => $productId, 'name' => $data['name']]);
            $this->success(['id' => $productId], 'Product created successfully', 201);

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ProductsApiController::createProduct error: " . $e->getMessage());
            $this->error('Failed to create product', 500);
        }
    }

    /**
     * Update product
     */
    private function updateProduct(int $productId): void
    {
        $this->requirePermission('products', 'edit');

        // Verify product belongs to tenant
        if (!$this->verifyProductOwnership($productId)) {
            $this->error('Product not found', 404);
        }

        $data = $this->getJsonInput();

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE products SET
                    category_id = ?,
                    name = ?,
                    sku = ?,
                    description = ?,
                    price = ?,
                    cost_price = ?,
                    active = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");

            $stmt->execute([
                $data['category_id'] ?? null,
                $data['name'] ?? null,
                $data['sku'] ?? null,
                $data['description'] ?? null,
                $data['price'] ?? null,
                $data['cost_price'] ?? null,
                $data['active'] ?? null,
                $productId,
                $this->tenant->getTenantId()
            ]);

            $this->db->commit();

            $this->logRequest('product_updated', ['product_id' => $productId]);
            $this->success([], 'Product updated successfully');

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ProductsApiController::updateProduct error: " . $e->getMessage());
            $this->error('Failed to update product', 500);
        }
    }

    /**
     * Delete product
     */
    private function deleteProduct(int $productId): void
    {
        $this->requirePermission('products', 'delete');

        // Verify product belongs to tenant
        if (!$this->verifyProductOwnership($productId)) {
            $this->error('Product not found', 404);
        }

        try {
            $this->db->beginTransaction();

            // Check if product is used in sales
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM sale_items
                WHERE product_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$productId, $this->tenant->getTenantId()]);

            if ($stmt->fetchColumn() > 0) {
                $this->db->rollBack();
                $this->error('Cannot delete product that has been sold', 400);
            }

            // Soft delete product (set active = 0)
            $stmt = $this->db->prepare("
                UPDATE products SET active = 0, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$productId, $this->tenant->getTenantId()]);

            $this->db->commit();

            $this->logRequest('product_deleted', ['product_id' => $productId]);
            $this->success([], 'Product deleted successfully');

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ProductsApiController::deleteProduct error: " . $e->getMessage());
            $this->error('Failed to delete product', 500);
        }
    }

    /**
     * Add initial inventory
     */
    private function addInitialInventory(int $productId, int $stock, int $branchId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventory (product_id, branch_id, tenant_id, stock, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)
        ");
        $stmt->execute([$productId, $branchId, $this->tenant->getTenantId(), $stock]);
    }

    /**
     * Verify product ownership
     */
    private function verifyProductOwnership(int $productId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM products
            WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$productId, $this->tenant->getTenantId()]);
        return $stmt->fetch() !== false;
    }
}