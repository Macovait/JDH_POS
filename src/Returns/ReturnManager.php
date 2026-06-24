<?php
/**
 * Returns/Refunds Manager
 * Complete returns system with approval workflow
 */

namespace Jakababa\Returns;

class ReturnManager
{
    private PDO $pdo;
    private int $tenantId;
    private int $userId;
    private int $branchId;

    public function __construct(PDO $pdo, int $tenantId, int $userId, int $branchId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->branchId = $branchId;
    }

    /**
     * Create a return request
     */
    public function createReturn(array $data): array
    {
        $this->validateReturnData($data);

        try {
            $this->pdo->beginTransaction();

            // Create return record
            $stmt = $this->pdo->prepare("
                INSERT INTO returns 
                (tenant_id, branch_id, sale_id, user_id, reason, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([
                $this->tenantId,
                $this->branchId,
                $data['sale_id'],
                $this->userId,
                $data['reason'] ?? 'refund',
                $data['notes'] ?? ''
            ]);

            $returnId = (int)$this->pdo->lastInsertId();

            // Add return items
            foreach ($data['items'] as $item) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO return_items 
                    (return_id, product_id, sale_item_id, quantity, price, reason)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $returnId,
                    $item['product_id'],
                    $item['sale_item_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['reason'] ?? 'damaged'
                ]);
            }

            $this->pdo->commit();

            $this->logActivity($returnId, 'create', 'Return requested');

            return ['success' => true, 'return_id' => $returnId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Approve a return request
     */
    public function approveReturn(int $returnId, int $approverId, string $notes = ''): array
    {
        if (!$this->isReturnPending($returnId)) {
            return ['success' => false, 'error' => 'Return is not in pending status'];
        }

        try {
            $this->pdo->beginTransaction();

            // Update return status
            $stmt = $this->pdo->prepare("
                UPDATE returns SET status = 'approved', approved_by = ?, approved_at = NOW(), notes = CONCAT(notes, ?, ' | ', ?)
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$approverId, $notes, $notes, $returnId, $this->tenantId]);

            // Restore stock
            $this->restoreStock($returnId);

            $this->pdo->commit();

            $this->logActivity($returnId, 'approve', 'Return approved by ' . $approverId);

            return ['success' => true, 'message' => 'Return approved'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reject a return request
     */
    public function rejectReturn(int $returnId, int $approverId, string $reason = ''): array
    {
        if (!$this->isReturnPending($returnId)) {
            return ['success' => false, 'error' => 'Return is not in pending status'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE returns SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$approverId, $reason, $returnId, $this->tenantId]);

        $this->logActivity($returnId, 'reject', 'Return rejected: ' . $reason);

        return ['success' => true, 'message' => 'Return rejected'];
    }

    /**
     * Complete a return (process refund)
     */
    public function completeReturn(int $returnId, string $paymentMethod = 'original'): array
    {
        if (!$this->isReturnApproved($returnId)) {
            return ['success' => false, 'error' => 'Return must be approved first'];
        }

        try {
            $this->pdo->beginTransaction();

            $saleId = $this->getReturnSaleId($returnId);
            $totalAmount = $this->calculateReturnTotal($returnId);

            // Create credit note or refund
            $stmt = $this->pdo->prepare("
                INSERT INTO credit_notes 
                (tenant_id, branch_id, return_id, sale_id, amount, type, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $this->tenantId,
                $this->branchId,
                $returnId,
                $saleId,
                $totalAmount,
                $paymentMethod
            ]);

            // Update return status
            $stmt = $this->pdo->prepare("
                UPDATE returns SET status = 'completed', completed_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$returnId, $this->tenantId]);

            $this->pdo->commit();

            $this->logActivity($returnId, 'complete', 'Return completed');

            return ['success' => true, 'message' => 'Return completed'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate return data
     */
    private function validateReturnData(array $data): void
    {
        if (empty($data['sale_id'])) {
            throw new Exception('Sale ID is required');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new Exception('Return items are required');
        }

        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                throw new Exception('Invalid return item data');
            }
        }
    }

    /**
     * Check if return is pending
     */
    private function isReturnPending(int $returnId): bool
    {
        $stmt = $this->pdo->prepare("SELECT status FROM returns WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$returnId, $this->tenantId]);
        $result = $stmt->fetch();
        return $result && $result['status'] === 'pending';
    }

    /**
     * Check if return is approved
     */
    private function isReturnApproved(int $returnId): bool
    {
        $stmt = $this->pdo->prepare("SELECT status FROM returns WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$returnId, $this->tenantId]);
        $result = $stmt->fetch();
        return $result && ($result['status'] === 'approved' || $result['status'] === 'pending');
    }

    /**
     * Restore stock for returned items
     */
    private function restoreStock(int $returnId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT product_id, SUM(quantity) as total_qty
            FROM return_items
            WHERE return_id = ?
            GROUP BY product_id
        ");
        $stmt->execute([$returnId]);
        
        while ($row = $stmt->fetch()) {
            $stmt2 = $this->pdo->prepare("
                INSERT INTO inventory (product_id, branch_id, tenant_id, stock, last_updated)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE stock = stock + ?
            ");
            $stmt2->execute([
                $row['product_id'],
                $this->branchId,
                $this->tenantId,
                $row['total_qty'],
                $row['total_qty']
            ]);
        }
    }

    /**
     * Calculate total return amount
     */
    private function calculateReturnTotal(int $returnId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(quantity * price) as total
            FROM return_items
            WHERE return_id = ?
        ");
        $stmt->execute([$returnId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get sale ID for return
     */
    private function getReturnSaleId(int $returnId): int
    {
        $stmt = $this->pdo->prepare("SELECT sale_id FROM returns WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$returnId, $this->tenantId]);
        $result = $stmt->fetch();
        return (int)($result['sale_id'] ?? 0);
    }

    /**
     * Log activity
     */
    private function logActivity(int $returnId, string $action, string $description): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, description, meta, branch_id, tenant_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                "return.{$action}",
                $description,
                json_encode(['return_id' => $returnId]),
                $this->branchId,
                $this->tenantId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log return activity: " . $e->getMessage());
        }
    }

    /**
     * Get all returns with filters
     */
    public function getReturns(array $filters = []): array
    {
        $where = ["r.tenant_id = ?", "r.branch_id = ?"];
        $params = [$this->tenantId, $this->branchId];

        if (!empty($filters['status'])) {
            $where[] = "r.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(r.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(r.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $stmt = $this->pdo->prepare("
            SELECT r.*, u.name as user_name
            FROM returns r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY r.created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}