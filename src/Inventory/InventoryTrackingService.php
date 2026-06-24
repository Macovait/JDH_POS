<?php
/**
 * Inventory Tracking Service
 * Enterprise-grade inventory management following JDH_POS patterns
 * 
 * This service follows the same architectural patterns as the barcode label module:
 * - Service-oriented architecture with single responsibility
 * - Dependency injection
 * - Settings management integration
 * - Rate limiting for bulk operations
 * - Comprehensive error handling
 * - Security-first approach
 * - Transaction safety
 */

namespace JDH\POS\Inventory;

use \SettingsManager;
use JDH\POS\Barcode\RateLimiter;
use JDH\POS\Barcode\BarcodeErrorHandler;
use PDO;
use Exception;

class InventoryTrackingService
{
    private PDO $pdo;
    private SettingsManager $settingsManager;
    private RateLimiter $rateLimiter;
    private BarcodeErrorHandler $errorHandler;
    private int $tenantId;
    private int $branchId;
    private int $userId;

    public function __construct(
        PDO $pdo,
        SettingsManager $settingsManager,
        RateLimiter $rateLimiter,
        BarcodeErrorHandler $errorHandler,
        int $tenantId,
        int $branchId = 0,
        int $userId = 0
    ) {
        $this->pdo = $pdo;
        $this->settingsManager = $settingsManager;
        $this->rateLimiter = $rateLimiter;
        $this->errorHandler = $errorHandler;
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
        $this->userId = $userId;
        
        // Ensure inventory tracking tables exist
        $this->ensureInventoryTables();
    }

    /**
     * Get current stock levels for products
     * 
     * @param array $productIds Optional array of product IDs to filter
     * @param int $locationId Optional location/warehouse ID
     * @return array Stock levels by product
     */
    public function getStockLevels(array $productIds = [], int $locationId = null): array
    {
        try {
            $query = "
                SELECT i.product_id, p.name, p.sku,
                       i.stock AS quantity_on_hand,
                       0 AS quantity_allocated,
                       i.stock AS quantity_available,
                       i.reorder_level AS reorder_point,
                       i.maximum_stock AS max_stock,
                       NULL AS location_id,
                       NULL AS location_name
                FROM inventory i
                JOIN products p ON i.product_id = p.id AND i.tenant_id = p.tenant_id
                WHERE i.tenant_id = ?
            ";
            
            $params = [$this->tenantId];
            
            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $query .= " AND i.product_id IN ($placeholders)";
                $params = array_merge($params, $productIds);
            }
            
            if ($locationId !== null) {
                $query .= " AND i.branch_id = ?";
                $params[] = $locationId;
            }
            
            if ($this->branchId > 0) {
                $query .= " AND i.branch_id = ?";
                $params[] = $this->branchId;
            }
            
            $query .= " ORDER BY p.name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to get stock levels: " . $e->getMessage());
            throw new Exception('Unable to retrieve stock levels');
        }
    }

    /**
     * Reserve stock for a sales order
     * 
     * @param array $items Array of [product_id => quantity, ...]
     * @param int $referenceId Reference ID (sale_id, etc.)
     * @param string $referenceType Type of reference (sale, transfer, etc.)
     * @return bool Success status
     */
    public function reserveStock(array $items, int $referenceId, string $referenceType = 'sale'): bool
    {
        // Validate input
        if (empty($items)) {
            throw new Exception('No items provided for stock reservation');
        }
        
        // Check rate limiting for bulk operations
        $rlCheck = $this->rateLimiter->checkInventoryOperation('reserve', count($items));
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for stock reservation');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            foreach ($items as $productId => $quantity) {
                $productId = (int)$productId;
                $quantity = (int)$quantity;
                
                if ($quantity <= 0) {
                    continue;
                }
                
                // Check current available stock
                $stockStmt = $this->pdo->prepare("
                    SELECT stock
                    FROM inventory
                    WHERE product_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ");
                
                $stockStmt->execute([$productId, $this->tenantId, $this->branchId]);
                $stock = (int) $stockStmt->fetchColumn();
                
                if ($stock < $quantity) {
                    throw new Exception(
                        "Insufficient stock for product ID: $productId. " .
                        "Available: $stock, Requested: $quantity"
                    );
                }
                
                $this->pdo->prepare("
                    UPDATE inventory
                    SET stock = stock - ?, updated_at = NOW()
                    WHERE product_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ")->execute([
                    $quantity,
                    $productId,
                    $this->tenantId,
                    $this->branchId
                ]);
                
                $this->pdo->prepare("
                    DELETE FROM inventory_reservations
                    WHERE product_id = ?
                    AND cart_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ")->execute([
                    $productId,
                    $referenceId,
                    $this->tenantId,
                    $this->branchId
                ]);
                
                // Create stock movement record
                $this->createStockMovement(
                    $productId,
                    $quantity,
                    'allocation',
                    $referenceId,
                    $referenceType,
                    "Stock reserved for $referenceType #$referenceId"
                );
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('stock_reserved', [
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'items_count' => count($items)
            ]);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to reserve stock: " . $e->getMessage());
            throw new Exception('Unable to reserve stock');
        }
    }

    /**
     * Release stock reservation (e.g., when order is cancelled)
     * 
     * @param array $items Array of [product_id => quantity, ...]
     * @param int $referenceId Reference ID
     * @param string $referenceType Type of reference
     * @return bool Success status
     */
    public function releaseStock(array $items, int $referenceId, string $referenceType = 'sale'): bool
    {
        // Validate input
        if (empty($items)) {
            throw new Exception('No items provided for stock release');
        }
        
        // Check rate limiting
        $rlCheck = $this->rateLimiter->checkInventoryOperation('release', count($items));
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for stock release');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            foreach ($items as $productId => $quantity) {
                $productId = (int)$productId;
                $quantity = (int)$quantity;
                
                if ($quantity <= 0) {
                    continue;
                }
                
                $this->pdo->prepare("
                    DELETE FROM inventory_reservations
                    WHERE product_id = ?
                    AND cart_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ")->execute([
                    $productId,
                    $referenceId,
                    $this->tenantId,
                    $this->branchId
                ]);
                
                // Create stock movement record
                $this->createStockMovement(
                    $productId,
                    $quantity,
                    'deallocation',
                    $referenceId,
                    $referenceType,
                    "Stock released for cancelled $referenceType #$referenceId"
                );
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('stock_released', [
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'items_count' => count($items)
            ]);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to release stock: " . $e->getMessage());
            throw new Exception('Unable to release stock');
        }
    }

    /**
     * Fulfill stock (convert allocation to actual deduction)
     * 
     * @param array $items Array of [product_id => quantity, ...]
     * @param int $referenceId Reference ID (sale_id, etc.)
     * @param string $referenceType Type of reference (sale, transfer, etc.)
     * @return bool Success status
     */
    public function fulfillStock(array $items, int $referenceId, string $referenceType = 'sale'): bool
    {
        // Validate input
        if (empty($items)) {
            throw new Exception('No items provided for stock fulfillment');
        }
        
        // Check rate limiting
        $rlCheck = $this->rateLimiter->checkInventoryOperation('fulfill', count($items));
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for stock fulfillment');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            foreach ($items as $productId => $quantity) {
                $productId = (int)$productId;
                $quantity = (int)$quantity;
                
                if ($quantity <= 0) {
                    continue;
                }
                
                // Check current allocated stock
                $stockStmt = $this->pdo->prepare("
                    SELECT stock
                    FROM inventory
                    WHERE product_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ");
                
                $stockStmt->execute([$productId, $this->tenantId, $this->branchId]);
                $stock = (int) $stockStmt->fetchColumn();
                
                if ($stock < $quantity) {
                    throw new Exception(
                        "Insufficient stock for product ID: $productId. " .
                        "Available: $stock, Requested: $quantity"
                    );
                }
                
                $this->pdo->prepare("
                    UPDATE inventory
                    SET stock = stock - ?, updated_at = NOW()
                    WHERE product_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ")->execute([
                    $quantity,
                    $productId,
                    $this->tenantId,
                    $this->branchId
                ]);
                
                $this->pdo->prepare("
                    DELETE FROM inventory_reservations
                    WHERE product_id = ?
                    AND cart_id = ?
                    AND tenant_id = ?
                    AND branch_id = ?
                ")->execute([
                    $productId,
                    $referenceId,
                    $this->tenantId,
                    $this->branchId
                ]);
                
                // Create stock movement record
                $this->createStockMovement(
                    $productId,
                    $quantity,
                    'fulfillment',
                    $referenceId,
                    $referenceType,
                    "Stock fulfilled for $referenceType #$referenceId"
                );
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('stock_fulfilled', [
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'items_count' => count($items)
            ]);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to fulfill stock: " . $e->getMessage());
            throw new Exception('Unable to fulfill stock');
        }
    }

    /**
     * Adjust inventory (for counts, losses, etc.)
     * 
     * @param int $productId Product ID
     * @param int $quantity Change in quantity (positive for increase, negative for decrease)
     * @param string $reason Reason for adjustment
     * @param string $referenceType Type of reference (count, loss, damage, etc.)
     * @param int $referenceId Optional reference ID
     * @return bool Success status
     */
    public function adjustInventory(int $productId, int $quantity, string $reason, string $referenceType = 'adjustment', int $referenceId = 0): bool
    {
        // Validate input
        if ($productId <= 0) {
            throw new Exception('Invalid product ID');
        }
        
        if ($quantity == 0) {
            throw new Exception('Quantity adjustment must not be zero');
        }
        
        if (empty($reason)) {
            throw new Exception('Reason for adjustment is required');
        }
        
        // Check rate limiting
        $rlCheck = $this->rateLimiter->checkInventoryOperation('adjust', 1);
        if (!$rlCheck['allowed']) {
            throw new Exception('Rate limit exceeded for inventory adjustment');
        }
        
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Get current stock
            $stockStmt = $this->pdo->prepare("
                SELECT stock
                FROM inventory 
                WHERE product_id = ? 
                AND tenant_id = ? 
                AND branch_id = ?
            ");
            
            $stockStmt->execute([$productId, $this->tenantId, $this->branchId]);
            $stock = (int) $stockStmt->fetchColumn();
            
            if (!$stock && $quantity < 0) {
                throw new Exception("Inventory record not found for product ID: $productId");
            }
            
            $currentStock = $stock;
            $newStock = $currentStock + $quantity;
            
            if ($newStock < 0) {
                throw new Exception(
                    "Adjustment would result in negative stock. " .
                    "Current: $currentStock, Change: $quantity, Result: $newStock"
                );
            }
            
            $this->pdo->prepare("
                UPDATE inventory 
                SET stock = ?, updated_at = NOW()
                WHERE product_id = ? 
                AND tenant_id = ? 
                AND branch_id = ?
            ")->execute([
                $newStock,
                $productId,
                $this->tenantId,
                $this->branchId
            ]);
            
            // Create stock movement record
            $this->createStockMovement(
                $productId,
                abs($quantity),
                $quantity > 0 ? 'adjustment_in' : 'adjustment_out',
                $referenceId,
                $referenceType,
                $reason
            );
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log activity
            $this->logActivity('inventory_adjusted', [
                'product_id' => $productId,
                'quantity_change' => $quantity,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId
            ]);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->errorHandler->logError("Failed to adjust inventory: " . $e->getMessage());
            throw new Exception('Unable to adjust inventory');
        }
    }

    /**
     * Get low stock items
     * 
     * @param int $locationId Optional location/warehouse ID
     * @return array Low stock items
     */
    public function getLowStockItems(int $locationId = null): array
    {
        try {
            $query = "
                SELECT i.product_id, p.name, p.sku,
                       i.stock AS quantity_on_hand,
                       0 AS quantity_allocated,
                       i.stock AS quantity_available,
                       i.reorder_level AS reorder_point,
                       i.maximum_stock AS max_stock,
                       NULL AS location_id,
                       NULL AS location_name
                FROM inventory i
                JOIN products p ON i.product_id = p.id AND i.tenant_id = p.tenant_id
                WHERE i.tenant_id = ? 
                AND i.stock <= i.reorder_level
                AND i.reorder_level > 0
            ";
            
            $params = [$this->tenantId];
            
            if ($locationId !== null) {
                $query .= " AND i.branch_id = ?";
                $params[] = $locationId;
            }
            
            if ($this->branchId > 0) {
                $query .= " AND i.branch_id = ?";
                $params[] = $this->branchId;
            }
            
            $query .= " ORDER BY (i.stock / NULLIF(i.reorder_level, 0)) ASC, p.name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->errorHandler->logError("Failed to get low stock items: " . $e->getMessage());
            throw new Exception('Unable to retrieve low stock items');
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Ensure inventory tracking tables exist (create if needed)
     */
    private function ensureInventoryTables(): void
    {
        // Main inventory, stock_movements, and inventory_reservations tables are owned by migrations.
    }

    /**
     * Create stock movement record (audit trail)
     * 
     * @param int $productId Product ID
     * @param float $quantity Quantity moved (always positive)
     * @param string $movementType Type of movement
     * @param int $referenceId Reference ID
     * @param string $referenceType Type of reference
     * @param string $notes Notes or description
     */
    private function createStockMovement(int $productId, float $quantity, string $movementType, int $referenceId, string $referenceType, string $notes): void
    {
        try {
            $movementType = in_array($movementType, ['purchase','sale','adjustment','return','transfer','damage','expired'], true)
                ? $movementType
                : 'adjustment';
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_movements 
                (tenant_id, branch_id, product_id, movement_type, quantity_change,
                 quantity_before, quantity_after, notes, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->tenantId,
                $this->branchId,
                $productId,
                $movementType,
                $quantity,
                $notes,
                $this->userId
            ]);
        } catch (Exception $e) {
            // Don't let stock movement logging fail the main operation
            $this->errorHandler->logError("Failed to create stock movement record: " . $e->getMessage());
        }
    }

    /**
     * Log activity for audit trail
     * 
     * @param string $action Action performed
     * @param array $details Additional details
     */
    private function logActivity(string $action, array $details = []): void
    {
        try {
            $logMessage = sprintf(
                '[InventoryTracking] User %d (Tenant %d, Branch %d): %s - %s',
                $this->userId,
                $this->tenantId,
                $this->branchId,
                $action,
                json_encode($details)
            );
            
            error_log($logMessage);
        } catch (Exception $e) {
            // Don't let logging errors break the main functionality
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }
}