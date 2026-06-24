<?php
/**
 * InventoryService — Stock Management & POS Sync
 * Calculates available stock, handles reservations, syncs with POS
 */

namespace Services\Shop;

class InventoryService {
    private PDO $pdo;
    private int $tenantId;
    private int $reservationMinutes = 30;

    public function __construct(PDO $pdo, int $tenantId) {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }

    /**
     * Get available stock for online shoppers
     * Formula: inventory.stock - active reservations
     */
    public function getAvailableStock(int $productId, int $branchId = 1): int {
        // Get raw POS stock
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(stock), 0)
            FROM inventory
            WHERE product_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$productId, $this->tenantId, $branchId]);
        $rawStock = (int) $stmt->fetchColumn();

        // Subtract active reservations
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0)
            FROM inventory_reservations
            WHERE product_id = ? AND tenant_id = ? AND branch_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$productId, $this->tenantId, $branchId]);
        $reserved = (int) $stmt->fetchColumn();

        return max(0, $rawStock - $reserved);
    }

    /**
     * Reserve stock for a cart
     */
    public function reserveStock(int $productId, int $quantity, int $cartId, bool $refresh = false): void {
        if ($refresh) {
            // Remove old reservation for this cart/product
            $this->pdo->prepare("
                DELETE FROM inventory_reservations
                WHERE product_id = ? AND cart_id = ? AND tenant_id = ?
            ")->execute([$productId, $cartId, $this->tenantId]);
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->reservationMinutes} minutes"));

        $stmt = $this->pdo->prepare("
            INSERT INTO inventory_reservations
                (tenant_id, product_id, branch_id, cart_id, quantity, expires_at)
            VALUES (?, ?, 1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                quantity = VALUES(quantity),
                expires_at = VALUES(expires_at)
        ");
        $stmt->execute([$this->tenantId, $productId, $cartId, $quantity, $expiresAt]);
    }

    /**
     * Release reservation
     */
    public function releaseReservation(int $productId, int $cartId): void {
        $this->pdo->prepare("
            DELETE FROM inventory_reservations
            WHERE product_id = ? AND cart_id = ? AND tenant_id = ?
        ")->execute([$productId, $cartId, $this->tenantId]);
    }

    /**
     * Convert reservation to actual sale (deduct from POS inventory)
     */
    public function commitReservation(int $cartId, int $orderId): void {
        $stmt = $this->pdo->prepare("
            SELECT product_id, branch_id, quantity
            FROM inventory_reservations
            WHERE cart_id = ? AND tenant_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$cartId, $this->tenantId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $res) {
            // Deduct from POS inventory
            $this->pdo->prepare("
                UPDATE inventory
                SET stock = GREATEST(stock - ?, 0), updated_at = NOW()
                WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
            ")->execute([$res['quantity'], $res['product_id'], $res['branch_id'], $this->tenantId]);

            // Log sync event
            $this->logSyncEvent(
                (int) $res['product_id'],
                (int) $res['branch_id'],
                'sale',
                null,
                null,
                -((int) $res['quantity']),
                'online',
                (string) $orderId
            );
        }

        // Delete reservations
        $this->pdo->prepare("
            DELETE FROM inventory_reservations WHERE cart_id = ? AND tenant_id = ?
        ")->execute([$cartId, $this->tenantId]);
    }

    /**
     * Release all expired reservations (run by cron)
     */
    public function releaseExpiredReservations(): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as released
            FROM inventory_reservations
            WHERE tenant_id = ? AND expires_at <= NOW()
        ");
        $stmt->execute([$this->tenantId]);
        $count = (int) $stmt->fetchColumn();

        $this->pdo->prepare("
            DELETE FROM inventory_reservations
            WHERE tenant_id = ? AND expires_at <= NOW()
        ")->execute([$this->tenantId]);

        return $count;
    }

    /**
     * Sync POS stock change to online (called by webhook or worker)
     */
    public function syncStockFromPOS(int $productId, int $branchId, int $oldStock, int $newStock, string $reason, ?string $sourceId = null): void {
        $delta = $newStock - $oldStock;

        // Log the sync event
        $this->logSyncEvent($productId, $branchId, $reason, $oldStock, $newStock, $delta, 'pos', $sourceId);

        // Invalidate any caches (future: Redis)
        // Cache::invalidate("tenant:{$this->tenantId}:product:{$productId}:stock");
    }

    /**
     * Get low stock alerts for shop admin
     */
    public function getLowStockProducts(int $threshold = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.name, p.image, p.reorder_level,
                   COALESCE(SUM(i.stock), 0) as total_stock
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
            WHERE p.tenant_id = ? AND p.active = 1
              AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
            GROUP BY p.id
            HAVING total_stock <= ? OR total_stock <= p.reorder_level
            ORDER BY total_stock ASC
            LIMIT 50
        ");
        $stmt->execute([$this->tenantId, $threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Batch get available stock for multiple products
     */
    public function getAvailableStockBatch(array $productIds): array {
        if (empty($productIds)) return [];

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $params = array_merge([$this->tenantId, $this->tenantId], $productIds);

        $stmt = $this->pdo->prepare("
            SELECT p.id,
                COALESCE(SUM(DISTINCT i.stock), 0) as raw_stock,
                COALESCE(SUM(DISTINCT r.quantity), 0) as reserved
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id AND i.tenant_id = p.tenant_id
            LEFT JOIN inventory_reservations r ON r.product_id = p.id AND r.tenant_id = p.tenant_id AND r.expires_at > NOW()
            WHERE p.tenant_id = ? AND p.id IN ({$placeholders})
            GROUP BY p.id
        ");
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['id']] = max(0, (int) $row['raw_stock'] - (int) $row['reserved']);
        }
        return $result;
    }

    // ─── Private ───

    private function logSyncEvent(int $productId, int $branchId, string $eventType, ?int $oldValue, ?int $newValue, int $delta, string $source, ?string $sourceId): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_sync_log
                (tenant_id, product_id, branch_id, event_type, old_value, new_value, delta, source, source_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->tenantId, $productId, $branchId, $eventType,
            $oldValue, $newValue, $delta, $source, $sourceId
        ]);
    }
}
