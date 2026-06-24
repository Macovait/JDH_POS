<?php
/**
 * Discount/Coupon Manager
 * Discount and coupon management system
 */

namespace Jakababa\Discount;

class DiscountManager
{
    private PDO $pdo;
    private int $tenantId;
    private int $branchId;
    private int $userId;

    public function __construct(PDO $pdo, int $tenantId, int $branchId, int $userId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
        $this->userId = $userId;
    }

    /**
     * Create a discount
     */
    public function createDiscount(array $data): array
    {
        $this->validateDiscount($data);

        $stmt = $this->pdo->prepare("
            INSERT INTO discounts 
            (tenant_id, branch_id, code, type, value, min_order, max_discount, starts_at, expires_at, usage_limit, used_count, active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())
        ");
        $stmt->execute([
            $this->tenantId,
            $data['branch_id'] ?? $this->branchId,
            $data['code'],
            $data['type'],
            $data['value'],
            $data['min_order'] ?? 0,
            $data['max_discount'] ?? null,
            $data['starts_at'] ?? date('Y-m-d H:i:s'),
            $data['expires_at'] ?? null,
            $data['usage_limit'] ?? null
        ]);

        $discountId = (int)$this->pdo->lastInsertId();

        $this->logActivity($discountId, 'create', 'Discount created');

        return ['success' => true, 'discount_id' => $discountId];
    }

    /**
     * Validate discount code
     */
    public function validateCode(string $code, float $orderAmount = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discounts 
            WHERE code = ? AND tenant_id = ? AND active = 1
        ");
        $stmt->execute([$code, $this->tenantId]);
        $discount = $stmt->fetch();

        if (!$discount) {
            return ['valid' => false, 'error' => 'Invalid discount code'];
        }

        // Check expiration
        if (!empty($discount['expires_at']) && strtotime($discount['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'Discount has expired'];
        }

        // Check minimum order
        if ($orderAmount < $discount['min_order']) {
            return ['valid' => false, 'error' => 'Minimum order not met'];
        }

        // Check usage limit
        if (!empty($discount['usage_limit']) && $discount['used_count'] >= $discount['usage_limit']) {
            return ['valid' => false, 'error' => 'Discount usage limit reached'];
        }

        return ['valid' => true, 'discount' => $discount];
    }

    /**
     * Apply discount to sale
     */
    public function applyToSale(int $discountId, int $saleId, float $originalAmount): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM discounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$discountId, $this->tenantId]);
        $discount = $stmt->fetch();

        if (!$discount) {
            return ['success' => false, 'error' => 'Invalid discount'];
        }

        $discountAmount = $this->calculateDiscount($discount, $originalAmount);

        // Update sale with discount
        $stmt = $this->pdo->prepare("
            UPDATE sales SET discount_id = ?, discount_amount = ? WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$discountId, $discountAmount, $saleId, $this->tenantId]);

        // Increment usage count
        $stmt = $this->pdo->prepare("UPDATE discounts SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$discountId]);

        $this->logActivity($discountId, 'apply', "Applied to sale {$saleId}");

        return [
            'success' => true,
            'discount_amount' => $discountAmount,
            'final_amount' => $originalAmount - $discountAmount
        ];
    }

    /**
     * Calculate discount amount
     */
    private function calculateDiscount(array $discount, float $amount): float
    {
        $discountAmount = 0;

        if ($discount['type'] === 'percentage') {
            $discountAmount = $amount * ($discount['value'] / 100);
        } elseif ($discount['type'] === 'fixed') {
            $discountAmount = $discount['value'];
        }

        // Apply max discount cap
        if (!empty($discount['max_discount']) && $discountAmount > $discount['max_discount']) {
            $discountAmount = $discount['max_discount'];
        }

        return min($discountAmount, $amount);
    }

    /**
     * Validate discount data
     */
    private function validateDiscount(array $data): void
    {
        if (empty($data['code'])) {
            throw new Exception('Discount code is required');
        }

        if (empty($data['type']) || !in_array($data['type'], ['percentage', 'fixed'])) {
            throw new Exception('Invalid discount type');
        }

        if (empty($data['value']) || $data['value'] < 0) {
            throw new Exception('Valid discount value is required');
        }
    }

    /**
     * Log activity
     */
    private function logActivity(int $discountId, string $action, string $description): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, description, meta, branch_id, tenant_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                "discount.{$action}",
                $description,
                json_encode(['discount_id' => $discountId]),
                $this->branchId,
                $this->tenantId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log discount activity: " . $e->getMessage());
        }
    }

    /**
     * Get all discounts
     */
    public function getDiscounts(array $filters = []): array
    {
        $where = ["tenant_id = ?"];
        $params = [$this->tenantId];

        if (isset($filters['active'])) {
            $where[] = "active = ?";
            $params[] = $filters['active'] ? 1 : 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM discounts WHERE " . implode(" AND ", $where) . "
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deactivate discount
     */
    public function deactivate(int $discountId): array
    {
        $stmt = $this->pdo->prepare("UPDATE discounts SET active = 0 WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$discountId, $this->tenantId]);

        $this->logActivity($discountId, 'deactivate', 'Discount deactivated');

        return ['success' => true, 'message' => 'Discount deactivated'];
    }
}