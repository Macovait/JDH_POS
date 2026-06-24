<?php
/**
 * Credit Manager
 * Customer credit management with limits and approval workflow
 */

namespace Jakababa\Credit;

class CreditManager
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
     * Create credit sale
     */
    public function createCreditSale(array $data): array
    {
        $this->validateCreditSale($data);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO credit_sales 
                (tenant_id, branch_id, customer_id, user_id, amount, due_date, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([
                $this->tenantId,
                $this->branchId,
                $data['customer_id'],
                $this->userId,
                $data['amount'],
                $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                $data['notes'] ?? ''
            ]);

            $creditId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

            $this->logActivity($creditId, 'create', 'Credit sale created');

            return ['success' => true, 'credit_id' => $creditId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check credit limit
     */
    public function checkCreditLimit(int $customerId, float $amount): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.credit_limit, COALESCE(SUM(cs.amount), 0) as outstanding
            FROM customers c
            LEFT JOIN credit_sales cs ON c.id = cs.customer_id 
                AND cs.status IN ('pending', 'approved', 'partial')
            WHERE c.id = ? AND c.tenant_id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$customerId, $this->tenantId]);
        $result = $stmt->fetch();

        if (!$result) {
            return ['success' => false, 'error' => 'Customer not found'];
        }

        $available = (float)$result['credit_limit'] - (float)$result['outstanding'];
        $approved = $amount <= $available;

        return [
            'success' => true,
            'approved' => $approved,
            'available' => $available,
            'limit' => $result['credit_limit'],
            'outstanding' => $result['outstanding'],
            'requested' => $amount
        ];
    }

    /**
     * Approve credit sale
     */
    public function approveCreditSale(int $creditId, int $approverId, string $notes = ''): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE credit_sales SET status = 'approved', approved_by = ?, approved_at = NOW(), notes = CONCAT(notes, ?, ' | ')
            WHERE id = ? AND tenant_id = ? AND status = 'pending'
        ");
        $stmt->execute([$approverId, $notes, $creditId, $this->tenantId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Credit sale not found or not pending'];
        }

        $this->logActivity($creditId, 'approve', 'Credit sale approved');

        return ['success' => true, 'message' => 'Credit sale approved'];
    }

    /**
     * Record credit payment
     */
    public function recordPayment(int $creditId, float $amount, string $paymentMethod, string $reference = ''): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO credit_payments 
            (tenant_id, branch_id, credit_sale_id, amount, payment_method, reference, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $this->tenantId,
            $this->branchId,
            $creditId,
            $amount,
            $paymentMethod,
            $reference
        ]);

        $this->updateCreditStatus($creditId);

        $this->logActivity($creditId, 'payment', "Payment of {$amount} recorded");

        return ['success' => true, 'message' => 'Payment recorded'];
    }

    /**
     * Update credit status based on payments
     */
    private function updateCreditStatus(int $creditId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(cp.amount) as total_paid, cs.amount as total_owed
            FROM credit_sales cs
            LEFT JOIN credit_payments cp ON cs.id = cp.credit_sale_id
            WHERE cs.id = ?
            GROUP BY cs.id
        ");
        $stmt->execute([$creditId]);
        $result = $stmt->fetch();

        if ($result) {
            $paid = (float)($result['total_paid'] ?? 0);
            $total = (float)$result['total_owed'];

            if ($paid >= $total) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = 'partial';
            } else {
                $status = 'approved';
            }

            $stmt = $this->pdo->prepare("UPDATE credit_sales SET status = ? WHERE id = ?");
            $stmt->execute([$status, $creditId]);
        }
    }

    /**
     * Validate credit sale data
     */
    private function validateCreditSale(array $data): void
    {
        if (empty($data['customer_id'])) {
            throw new Exception('Customer ID is required');
        }

        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new Exception('Valid amount is required');
        }
    }

    /**
     * Log activity
     */
    private function logActivity(int $creditId, string $action, string $description): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, description, meta, branch_id, tenant_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                "credit.{$action}",
                $description,
                json_encode(['credit_id' => $creditId]),
                $this->branchId,
                $this->tenantId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log credit activity: " . $e->getMessage());
        }
    }

    /**
     * Get all credit sales with filters
     */
    public function getCreditSales(array $filters = []): array
    {
        $where = ["cs.tenant_id = ?", "cs.branch_id = ?"];
        $params = [$this->tenantId, $this->branchId];

        if (!empty($filters['status'])) {
            $where[] = "cs.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = "cs.customer_id = ?";
            $params[] = $filters['customer_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT cs.*, c.name as customer_name, u.name as user_name
            FROM credit_sales cs
            LEFT JOIN customers c ON cs.customer_id = c.id
            LEFT JOIN users u ON cs.user_id = u.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY cs.created_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}