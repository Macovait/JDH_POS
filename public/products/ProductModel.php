<?php
/**
 * Product Form page for Jakababa POS - SaaS Version
 * Zero-Trust Implementation
 */

require_once __DIR__ . '/../../src/TenantContext.php';
require_once __DIR__ . '/../../src/BaseModel.php';

class ProductModel extends BaseModel {
    protected $table = 'products';

    public function getProductWithBranches($productId): ?array {
        $stmt = $this->pdo->prepare("SELECT p.*, GROUP_CONCAT(i.branch_id) as branch_ids FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE p.id = ? AND p.deleted_at IS NULL");
        $params = [$productId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $result = $stmt->fetch();

        if ($result) {
            $result['branch_ids'] = $result['branch_ids'] ? explode(',', $result['branch_ids']) : [];
        }

        return $result;
    }

    public function checkSkuExists($sku, $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM products WHERE sku = ? AND deleted_at IS NULL";
        $params = [$sku];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function checkBarcodeExists($barcode, $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM products WHERE barcode = ? AND deleted_at IS NULL";
        $params = [$barcode];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function getCategories(): array {
        $stmt = $this->pdo->prepare("SELECT id, name FROM categories WHERE (status = 'active' OR status IS NULL) AND deleted_at IS NULL ORDER BY name");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getBrands(): array {
        $stmt = $this->pdo->prepare("SELECT id, name FROM brands WHERE active = 1 ORDER BY name");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getBranches(): array {
        $stmt = $this->pdo->prepare("SELECT id, name FROM branches WHERE active = 1 AND deleted_at IS NULL ORDER BY name");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateInventory($productId, array $branchIds): void {
        // Remove from unselected branches
        $placeholders = str_repeat('?,', count($branchIds) - 1) . '?';
        $stmt = $this->pdo->prepare("DELETE FROM inventory WHERE product_id = ? AND tenant_id = ? AND branch_id NOT IN ($placeholders)");
        $params = array_merge([$productId, $this->context->getCompanyId()], $branchIds);
        $stmt->execute($params);

        // Add to selected branches (ignore if exists)
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO inventory (product_id, tenant_id, branch_id, stock, reorder_level, created_at, updated_at) SELECT ?, ?, id, 0, 5, NOW(), NOW() FROM branches WHERE id IN ($placeholders) AND tenant_id = ?");
        $params = array_merge([$productId, $this->context->getCompanyId()], $branchIds);
        $params[] = $this->context->getCompanyId();
        $stmt->execute($params);
    }

    public function updateProductAttributes($productId, array $attributes): void {
        $stmt = $this->pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?");
        $params = [$productId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        foreach ($attributes as $index => $attr) {
            // Support both old format (string names) and new format (array with name/values/visible/used_for_variations)
            if (is_string($attr)) {
                $attrName = trim($attr);
                $attrValue = '';
                $visible = 1;
                $usedForVariations = 0;
            } elseif (is_array($attr)) {
                $attrName = trim($attr['name'] ?? '');
                $attrValue = trim($attr['values'] ?? '');
                $visible = isset($attr['visible']) ? 1 : 0;
                $usedForVariations = isset($attr['used_for_variations']) ? 1 : 0;
            } else {
                continue;
            }

            if ($attrName) {
                $stmt = $this->pdo->prepare("INSERT INTO product_attributes (tenant_id, product_id, attribute_name, attribute_value, visible, position, used_for_variations) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$this->context->getCompanyId(), $productId, $attrName, $attrValue, $visible, (int)$index, $usedForVariations]);
            }
        }
    }

    public function updateProduct($productId, array $data): int {
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "`$key` = ?";
            $params[] = $value;
        }
        
        $params[] = $productId;
        
        $sql = "UPDATE products SET " . implode(', ', $setParts) . ", `updated_at` = NOW() WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        return $stmt->execute($params) ? $stmt->rowCount() : 0;
    }

    public function getProductAttributes($productId): array {
        $stmt = $this->pdo->prepare("SELECT pa.* FROM product_attributes pa WHERE pa.product_id = ? ORDER BY pa.position, pa.id");
        $params = [$productId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAvailableAttributes(): array {
        $stmt = $this->pdo->prepare("SELECT DISTINCT attribute_name, attribute_value FROM product_attributes WHERE product_id IS NULL ORDER BY attribute_name");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function deleteProduct($productId): bool {
        // Soft delete the product instead of hard delete
        $stmt = $this->pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
        $params = [$productId];
        $this->applyTenantScope($stmt, $params);
        return $stmt->execute($params);
    }

    public function getLinkedProducts(int $productId, string $linkType = 'upsell'): array {
        $stmt = $this->pdo->prepare("SELECT l.*, p.name, p.price, p.image, p.sku 
            FROM product_linked_products l
            JOIN products p ON p.id = l.linked_product_id
            WHERE l.product_id = ? AND l.link_type = ?");
        $params = [$productId, $linkType];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateLinkedProducts(int $productId, array $linkedIds, string $linkType): void {
        $stmt = $this->pdo->prepare("DELETE FROM product_linked_products WHERE product_id = ? AND link_type = ?");
        $params = [$productId, $linkType];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        if (!empty($linkedIds)) {
            $stmt = $this->pdo->prepare("INSERT INTO product_linked_products (tenant_id, product_id, linked_product_id, link_type) VALUES (?, ?, ?, ?)");
            foreach ($linkedIds as $linkedId) {
                $stmt->execute([$this->context->getCompanyId(), $productId, (int)$linkedId, $linkType]);
            }
        }
    }

    public function getProductVariants(int $productId): array {
        $stmt = $this->pdo->prepare("SELECT id, name, sku, barcode, price, cost_price, stock, active FROM product_variants WHERE product_id = ? ORDER BY id");
        $params = [$productId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateProductVariants(int $productId, array $variants): void {
        // Delete existing variants
        $stmt = $this->pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
        $params = [$productId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        // Insert new variants
        if (!empty($variants)) {
            $stmt = $this->pdo->prepare("INSERT INTO product_variants (tenant_id, product_id, name, sku, barcode, price, cost_price, stock, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($variants as $v) {
                $stmt->execute([
                    $this->context->getCompanyId(),
                    $productId,
                    trim($v['name'] ?? ''),
                    trim($v['sku'] ?? ''),
                    trim($v['barcode'] ?? ''),
                    floatval($v['price'] ?? 0),
                    floatval($v['cost_price'] ?? 0),
                    intval($v['stock'] ?? 0),
                    isset($v['active']) ? 1 : 0
                ]);
            }
        }
    }

    public function searchProducts(string $query, int $excludeId = 0, int $limit = 20): array {
        $sql = "SELECT id, name, sku, price, image FROM products WHERE name LIKE ? AND id != ? AND deleted_at IS NULL";
        $params = ['%' . $query . '%', $excludeId];
        $stmt = $this->pdo->prepare($sql . " ORDER BY name LIMIT " . (int)$limit);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function applyTenantScopeToQuery(PDOStatement $stmt, array &$params): void {
        $this->applyTenantScope($stmt, $params);
    }
}