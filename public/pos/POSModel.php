<?php
/**
 * POS Model - Zero-Trust Implementation
 * Handles all POS operations with automatic tenant scoping
 */

require_once __DIR__ . '/../../src/TenantContext.php';
require_once __DIR__ . '/../../src/BaseModel.php';

class POSModel extends BaseModel {
    protected $table = 'products';

    public function getSettings(): array {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE 1=1");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function getBranches(): array {
        $stmt = $this->pdo->prepare("SELECT id, name, code FROM branches WHERE active = 1 AND deleted_at IS NULL ORDER BY id");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(): array {
        $stmt = $this->pdo->prepare("SELECT id, name, color, icon FROM categories WHERE (status = 'active' OR status IS NULL) AND deleted_at IS NULL ORDER BY name");
        $params = [];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProducts(int $branchId = null): array {
        $sql = "SELECT p.id, p.name, p.price, p.category_id";
        $params = [];

        // Add stock information if branch specified
        if ($branchId) {
            $sql .= ", COALESCE(i.stock, 0) AS stock";
        } else {
            $sql .= ", 999 AS stock";
        }

        $sql .= " FROM products p";

        if ($branchId) {
            $sql .= " LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " WHERE p.active = 1";

        // Apply tenant scoping (includes tenant_id and branch_id if applicable)
        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiscounts(int $branchId = null): array {
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

    public function getCurrentShift(int $branchId, int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM register_sessions WHERE branch_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $params = [$branchId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTodayStats(int $branchId): array {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash, COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card, COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa FROM sales WHERE branch_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed' AND voided = 0");
        $params = [$branchId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['total' => 0, 'sale_count' => 0, 'cash' => 0, 'card' => 0, 'mpesa' => 0];
    }

    public function getHeldSalesCount(int $branchId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM hold_sales WHERE branch_id = ?");
        $params = [$branchId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function createSale(array $saleData): int {
        // Ensure tenant context is injected
        $saleData['tenant_id'] = $this->context->getCompanyId();
        $saleData['branch_id'] = $this->context->getBranchId();
        $saleData['created_by'] = $this->context->getUserId();

        $columns = array_keys($saleData);
        $placeholders = str_repeat('?, ', count($saleData) - 1) . '?';

        $stmt = $this->pdo->prepare("INSERT INTO sales (" . implode(', ', $columns) . ") VALUES ({$placeholders})");
        $stmt->execute(array_values($saleData));

        return $this->pdo->lastInsertId();
    }

    public function updateInventory(int $productId, int $branchId, int $quantity): void {
        $stmt = $this->pdo->prepare("INSERT INTO inventory (product_id, tenant_id, branch_id, stock, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock), updated_at = NOW()");
        $stmt->execute([$productId, $this->context->getCompanyId(), $branchId, $quantity]);
    }
}