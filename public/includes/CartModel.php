<?php
/**
 * Cart Model - Zero-Trust Implementation
 * Handles all cart-related operations with automatic tenant scoping
 */

require_once __DIR__ . '/../../src/TenantContext.php';
require_once __DIR__ . '/../../src/BaseModel.php';

class CartModel extends BaseModel {
    protected $table = 'sales';

    public function getCartItems($cartItems = []) {
        if (empty($cartItems)) return [];

        $placeholders = str_repeat('?,', count($cartItems) - 1) . '?';
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.name, p.price, i.stock
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.id IN ($placeholders) AND p.deleted_at IS NULL
        ");
        $params = array_merge([$this->context->getBranchId(), $this->context->getCompanyId()], $cartItems);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validateStock($productId, $requestedQty) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(i.stock, 0) as stock
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.id = ? AND p.deleted_at IS NULL
        ");
        $params = [$this->context->getBranchId(), $this->context->getCompanyId(), $productId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['stock'] >= $requestedQty : false;
    }

    public function getCustomerHistory($customerId) {
        $stmt = $this->pdo->prepare("
            SELECT GROUP_CONCAT(DISTINCT p.category_id) as categories,
                   GROUP_CONCAT(DISTINCT p.id) as products,
                   AVG(s.total) as avg_spend,
                   COUNT(s.id) as total_purchases
            FROM sales s
            JOIN sale_items si ON s.id = si.sale_id AND si.tenant_id = ?
            JOIN products p ON si.product_id = p.id
            WHERE s.customer_id = ? AND s.status = 'completed'
        ");
        $params = [$this->context->getCompanyId(), $customerId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getFrequentItems($productIds = []) {
        if (empty($productIds)) return [];

        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT pa.related_product_id as id,
                   p.name, p.price,
                   pa.affinity_score,
                   pa.times_bought_together,
                   'Frequently bought together' as reason
            FROM product_affinity pa
            JOIN products p ON pa.related_product_id = p.id
            JOIN inventory i ON p.id = i.product_id
            WHERE pa.product_id IN ($placeholders)
            AND pa.related_product_id NOT IN ($placeholders)
            AND i.stock > 0
            ORDER BY pa.affinity_score DESC
            LIMIT 8
        ");

        $params = array_merge($productIds, $productIds);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function processSale($saleData, $saleItems) {
        $this->pdo->beginTransaction();

        try {
            // Insert sale with tenant context
            $saleId = $this->insert($saleData);

            // Insert sale items
            $stmt = $this->pdo->prepare("INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($saleItems as $item) {
                $stmt->execute([$this->context->getCompanyId(), $saleId, $item['product_id'], $item['quantity'], $item['price'], $item['total']]);

                // Update inventory
                $this->updateInventory($item['product_id'], $this->context->getBranchId(), -$item['quantity']);
            }

            $this->pdo->commit();
            return $saleId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateInventory($productId, $branchId, $quantityChange) {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory (product_id, tenant_id, branch_id, stock, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock), updated_at = NOW()
        ");
        $stmt->execute([$productId, $this->context->getCompanyId(), $branchId, $quantityChange]);
    }

    public function getHeldSales() {
        $stmt = $this->pdo->prepare("SELECT * FROM hold_sales WHERE 1=1 ORDER BY created_at DESC");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiscounts($branchId = null) {
        $sql = "SELECT * FROM discounts WHERE active = 1 AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_until IS NULL OR valid_until >= NOW())";
        $params = [];

        if ($branchId) {
            $sql .= " AND (branch_id = ? OR branch_id IS NULL)";
            $params[] = $branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applyDiscount($cartTotal, $discountCode) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discounts
            WHERE code = ? AND active = 1
            AND (valid_from IS NULL OR valid_from <= NOW())
            AND (valid_until IS NULL OR valid_until >= NOW())
        ");
        $params = [$discountCode];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}