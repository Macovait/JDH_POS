<?php
/**
 * Manager Override & Void Workflow for Jakababa POS
 * Handles authorization for sensitive operations like voids, price overrides,
 * returns above threshold, discount overrides, etc.
 *
 * @package Jakababa
 * @subpackage Overrides
 * @version 3.0
 */

if (defined('OVERRIDES_LOADED')) {
    return;
}
define('OVERRIDES_LOADED', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cache.php';

// =============================================================================
// Override Action Types
// =============================================================================

class OverrideAction
{
    const VOID_SALE = 'void_sale';
    const VOID_ITEM = 'void_item';
    const PRICE_OVERRIDE = 'price_override';
    const DISCOUNT_OVERRIDE = 'discount_override';
    const RETURN_NO_RECEIPT = 'return_no_receipt';
    const RETURN_ABOVE_LIMIT = 'return_above_limit';
    const CASH_DRAWER_OPEN = 'cash_drawer_open';
    const STOCK_ADJUSTMENT = 'stock_adjustment';
    const DISCOUNT_ABOVE_LIMIT = 'discount_above_limit';
    const NEGATIVE_SALE = 'negative_sale';
    const CREDIT_SALE = 'credit_sale';
    const REPRINT_RECEIPT = 'reprint_receipt';
    const TILL_RECONCILIATION = 'till_reconciliation';
}

// =============================================================================
// Override Request
// =============================================================================

class OverrideRequest
{
    public string $action;
    public int $requesterId;
    public ?int $saleId;
    public ?int $productId;
    public array $data;
    public string $reason;
    public ?float $amount;
    public ?float $threshold;
    public string $status; // pending, approved, rejected
    public ?int $approverId;
    public ?string $approvedAt;
    public ?string $approvalCode;

    public function __construct(array $data = [])
    {
        $this->action = $data['action'] ?? '';
        $this->requesterId = (int) ($data['requester_id'] ?? 0);
        $this->saleId = isset($data['sale_id']) ? (int) $data['sale_id'] : null;
        $this->productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $this->data = $data['data'] ?? [];
        $this->reason = $data['reason'] ?? '';
        $this->amount = isset($data['amount']) ? (float) $data['amount'] : null;
        $this->threshold = isset($data['threshold']) ? (float) $data['threshold'] : null;
        $this->status = $data['status'] ?? 'pending';
        $this->approverId = isset($data['approver_id']) ? (int) $data['approver_id'] : null;
        $this->approvedAt = $data['approved_at'] ?? null;
        $this->approvalCode = $data['approval_code'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'requester_id' => $this->requesterId,
            'sale_id' => $this->saleId,
            'product_id' => $this->productId,
            'data' => $this->data,
            'reason' => $this->reason,
            'amount' => $this->amount,
            'threshold' => $this->threshold,
            'status' => $this->status,
            'approver_id' => $this->approverId,
            'approved_at' => $this->approvedAt,
            'approval_code' => $this->approvalCode
        ];
    }
}

// =============================================================================
// Override Configuration
// =============================================================================

class OverrideConfig
{
    // Thresholds that require manager approval
    const THRESHOLDS = [
        OverrideAction::VOID_SALE => 0,             // Always requires approval
        OverrideAction::VOID_ITEM => 500,             // Items above KSh 500
        OverrideAction::PRICE_OVERRIDE => 100,         // Price change > KSh 100
        OverrideAction::DISCOUNT_OVERRIDE => 15,       // Discount > 15%
        OverrideAction::RETURN_NO_RECEIPT => 0,        // Always requires approval
        OverrideAction::RETURN_ABOVE_LIMIT => 5000,    // Returns above KSh 5000
        OverrideAction::CASH_DRAWER_OPEN => 0,         // Always (non-sale drawer open)
        OverrideAction::STOCK_ADJUSTMENT => 100,       // Adjustment > 100 units
        OverrideAction::DISCOUNT_ABOVE_LIMIT => 20,    // Discount > 20%
        OverrideAction::CREDIT_SALE => 0,             // Always requires approval
        OverrideAction::REPRINT_RECEIPT => 0,          // Always requires approval
        OverrideAction::TILL_RECONCILIATION => 0,      // Always requires approval
    ];

    // Required roles for each action
    const REQUIRED_ROLES = [
        OverrideAction::VOID_SALE => ['manager', 'supervisor', 'admin'],
        OverrideAction::VOID_ITEM => ['cashier', 'manager', 'supervisor', 'admin'],
        OverrideAction::PRICE_OVERRIDE => ['manager', 'supervisor', 'admin'],
        OverrideAction::DISCOUNT_OVERRIDE => ['cashier', 'manager', 'supervisor', 'admin'],
        OverrideAction::RETURN_NO_RECEIPT => ['manager', 'supervisor', 'admin'],
        OverrideAction::RETURN_ABOVE_LIMIT => ['manager', 'supervisor', 'admin'],
        OverrideAction::CASH_DRAWER_OPEN => ['manager', 'supervisor', 'admin'],
        OverrideAction::STOCK_ADJUSTMENT => ['manager', 'supervisor', 'admin'],
        OverrideAction::DISCOUNT_ABOVE_LIMIT => ['manager', 'supervisor', 'admin'],
        OverrideAction::CREDIT_SALE => ['manager', 'supervisor', 'admin'],
        OverrideAction::REPRINT_RECEIPT => ['cashier', 'manager', 'supervisor', 'admin'],
        OverrideAction::TILL_RECONCILIATION => ['manager', 'supervisor', 'admin'],
    ];

    // Actions that always require manager PIN
    const PIN_REQUIRED = [
        OverrideAction::VOID_SALE,
        OverrideAction::RETURN_NO_RECEIPT,
        OverrideAction::CASH_DRAWER_OPEN,
        OverrideAction::TILL_RECONCILIATION,
    ];
}

// =============================================================================
// Override Manager
// =============================================================================

class OverrideManager
{
    private ?int $companyId;
    private ?int $branchId;

    public function __construct(?int $companyId = null, ?int $branchId = null)
    {
        $this->companyId = $companyId ?? (function_exists('get_current_tenant_id') ? get_current_tenant_id() : null);
        $this->branchId = $branchId ?? (function_exists('get_current_branch_id') ? get_current_branch_id() : null);
    }

    /**
     * Check if an action requires manager override
     */
    public function requiresOverride(string $action, ?float $amount = null, ?float $value = null): bool
    {
        $threshold = OverrideConfig::THRESHOLDS[$action] ?? null;

        if ($threshold === null) {
            return false;
        }

        // Threshold of 0 means always requires override
        if ($threshold === 0) {
            return true;
        }

        // Check if amount/value exceeds threshold
        $checkValue = $amount ?? $value ?? 0;
        return $checkValue > $threshold;
    }

    /**
     * Get the threshold for an action
     */
    public function getThreshold(string $action): ?float
    {
        return OverrideConfig::THRESHOLDS[$action] ?? null;
    }

    /**
     * Get required roles for an action
     */
    public function getRequiredRoles(string $action): array
    {
        return OverrideConfig::REQUIRED_ROLES[$action] ?? ['manager', 'admin'];
    }

    /**
     * Check if PIN is required for an action
     */
    public function isPinRequired(string $action): bool
    {
        return in_array($action, OverrideConfig::PIN_REQUIRED);
    }

    /**
     * Create an override request
     */
    public function createRequest(OverrideRequest $request): array
    {
        // Validate action
        if (!isset(OverrideConfig::THRESHOLDS[$request->action])) {
            return ['success' => false, 'error' => 'Invalid override action'];
        }

        // Generate approval code
        $approvalCode = strtoupper(substr(md5(random_bytes(16)), 0, 8));

        // Store in database
        try {
            $id = db_insert('override_requests', [
                'tenant_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'action' => $request->action,
                'requester_id' => $request->requesterId,
                'sale_id' => $request->saleId,
                'product_id' => $request->productId,
                'data' => json_encode($request->data),
                'reason' => $request->reason,
                'amount' => $request->amount,
                'threshold' => $this->getThreshold($request->action),
                'status' => 'pending',
                'approval_code' => $approvalCode,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'request_id' => $id,
                'approval_code' => $approvalCode,
                'message' => 'Override request created. Waiting for manager approval.',
                'required_roles' => $this->getRequiredRoles($request->action),
                'pin_required' => $this->isPinRequired($request->action)
            ];
        } catch (Exception $e) {
            error_log("Failed to create override request: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create override request'];
        }
    }

    /**
     * Approve an override request
     */
    public function approveRequest(int $requestId, int $approverId, string $pin = '', string $notes = ''): array
    {
        try {
            $request = db_fetch_one(
                "SELECT * FROM override_requests WHERE id = ? AND tenant_id = ? AND status = 'pending'",
                [$requestId, $this->companyId]
            );

            if (!$request) {
                return ['success' => false, 'error' => 'Request not found or already processed'];
            }

            // Verify approver role
            $approver = db_fetch_one(
                "SELECT u.*, GROUP_CONCAT(r.name) as roles 
                 FROM users u 
                 LEFT JOIN user_roles ur ON ur.user_id = u.id 
                 LEFT JOIN roles r ON r.id = ur.role_id 
                 WHERE u.id = ? AND u.tenant_id = ? 
                 GROUP BY u.id",
                [$approverId, $this->companyId]
            );

            if (!$approver) {
                return ['success' => false, 'error' => 'Approver not found'];
            }

            $approverRoles = explode(',', $approver['roles'] ?? '');
            $requiredRoles = $this->getRequiredRoles($request['action']);
            $hasRole = !empty(array_intersect($approverRoles, $requiredRoles));

            if (!$hasRole) {
                return ['success' => false, 'error' => 'Insufficient role for this approval'];
            }

            // Verify PIN if required
            if ($this->isPinRequired($request['action'])) {
                if (empty($pin)) {
                    return ['success' => false, 'error' => 'PIN required for this action'];
                }

                if (!password_verify($pin, $approver['pin_hash'] ?? '')) {
                    return ['success' => false, 'error' => 'Invalid PIN'];
                }
            }

            // Approve
            db_update(
                'override_requests',
                [
                    'status' => 'approved',
                    'approver_id' => $approverId,
                    'approved_at' => date('Y-m-d H:i:s'),
                    'approval_notes' => $notes
                ],
                'id = ?',
                [$requestId]
            );

            // Log the override
            $this->logOverride($request, $approverId, 'approved');

            return [
                'success' => true,
                'message' => 'Override approved',
                'approval_code' => $request['approval_code'],
                'action' => $request['action'],
                'data' => json_decode($request['data'], true) ?: []
            ];
        } catch (Exception $e) {
            error_log("Override approval error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Approval failed'];
        }
    }

    /**
     * Reject an override request
     */
    public function rejectRequest(int $requestId, int $approverId, string $reason = ''): array
    {
        try {
            $affected = db_update(
                'override_requests',
                [
                    'status' => 'rejected',
                    'approver_id' => $approverId,
                    'approved_at' => date('Y-m-d H:i:s'),
                    'approval_notes' => $reason
                ],
                'id = ? AND tenant_id = ? AND status = ?',
                [$requestId, $this->companyId, 'pending']
            );

            if ($affected === 0) {
                return ['success' => false, 'error' => 'Request not found or already processed'];
            }

            $request = db_fetch_one("SELECT * FROM override_requests WHERE id = ?", [$requestId]);
            if ($request) {
                $this->logOverride($request, $approverId, 'rejected');
            }

            return ['success' => true, 'message' => 'Override rejected'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Rejection failed'];
        }
    }

    /**
     * Quick approve with code (for manager to give cashier a code)
     */
    public function quickApprove(string $approvalCode, int $approverId): array
    {
        try {
            $request = db_fetch_one(
                "SELECT * FROM override_requests 
                 WHERE approval_code = ? AND tenant_id = ? AND status = 'pending'",
                [$approvalCode, $this->companyId]
            );

            if (!$request) {
                return ['success' => false, 'error' => 'Invalid approval code'];
            }

            return $this->approveRequest($request['id'], $approverId);
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Quick approval failed'];
        }
    }

    /**
     * Get pending override requests
     */
    public function getPendingRequests(?int $branchId = null): array
    {
        $where = ['r.tenant_id = ?', 'r.status = ?'];
        $params = [$this->companyId, 'pending'];

        if ($branchId) {
            $where[] = 'r.branch_id = ?';
            $params[] = $branchId;
        }

        return db_fetch_all(
            "SELECT r.*, u.name as requester_name, b.name as branch_name
             FROM override_requests r
             LEFT JOIN users u ON u.id = r.requester_id
             LEFT JOIN branches b ON b.id = r.branch_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.created_at DESC",
            $params
        );
    }

    /**
     * Get override history
     */
    public function getOverrideHistory(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $where = ['r.tenant_id = ?'];
        $params = [$this->companyId];

        if ($branchId) {
            $where[] = 'r.branch_id = ?';
            $params[] = $branchId;
        }

        if ($from && $to) {
            $where[] = 'DATE(r.created_at) BETWEEN ? AND ?';
            $params[] = $from;
            $params[] = $to;
        }

        return db_fetch_all(
            "SELECT r.*, 
                    u.name as requester_name, 
                    a.name as approver_name,
                    b.name as branch_name
             FROM override_requests r
             LEFT JOIN users u ON u.id = r.requester_id
             LEFT JOIN users a ON a.id = r.approver_id
             LEFT JOIN branches b ON b.id = r.branch_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.created_at DESC
             LIMIT 100",
            $params
        );
    }

    /**
     * Validate approval code
     */
    public function validateApprovalCode(string $code): ?array
    {
        return db_fetch_one(
            "SELECT * FROM override_requests 
             WHERE approval_code = ? AND tenant_id = ? AND status = 'approved'",
            [$code, $this->companyId]
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function logOverride(array $request, int $approverId, string $action): void
    {
        try {
            if (function_exists('log_activity')) {
                log_activity("override.{$action}", "Override {$action}: {$request['action']} by user #{$request['requester_id']}, approved by #{$approverId}", $request, get_current_tenant_id());
            }
        } catch (Exception $e) {
            // Non-critical
        }
    }
}

// =============================================================================
// SQL Schema
// =============================================================================

function getOverrideSchema(): string
{
    return "
    CREATE TABLE IF NOT EXISTS override_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT,
        branch_id INT,
        action VARCHAR(50) NOT NULL,
        requester_id INT NOT NULL,
        sale_id INT NULL,
        product_id INT NULL,
        data JSON,
        reason TEXT,
        amount DECIMAL(10,2) NULL,
        threshold DECIMAL(10,2) NULL,
        status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
        approval_code VARCHAR(20) NOT NULL,
        approver_id INT NULL,
        approved_at TIMESTAMP NULL,
        approval_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_company_status (tenant_id, status),
        INDEX idx_approval_code (approval_code),
        INDEX idx_requester (requester_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
}

// =============================================================================
// Global Helper
// =============================================================================

if (!function_exists('check_override')) {
    /**
     * Quick check if an action needs override
     */
    function check_override(string $action, ?float $amount = null): bool
    {
        $manager = new OverrideManager();
        return $manager->requiresOverride($action, $amount);
    }
}
