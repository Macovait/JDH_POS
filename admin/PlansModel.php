<?php
/**
 * Plans Model - Zero-Trust Implementation
 * Handles subscription plan management with controlled SaaS admin access
 */

require_once dirname(__DIR__) . '/src/TenantContext.php';
require_once dirname(__DIR__) . '/src/BaseModel.php';

class PlansModel extends BaseModel {
    protected $table = 'pos_plans';

    public function getPlans(): array {
        // SaaS admin only - can see all plans
        if ($this->context->allowCrossTenant()) {
            $stmt = $this->pdo->prepare("SELECT * FROM pos_plans ORDER BY sort_order ASC, price ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Regular tenants cannot access plan management
        return [];
    }

    public function createPlan(array $planData): int {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Validate plan data
        $this->validatePlanData($planData);

        // Check slug uniqueness
        if ($this->checkSlugExists($planData['slug'])) {
            throw new Exception('Plan slug already exists');
        }

        return $this->insert($planData);
    }

    public function updatePlan(int $planId, array $planData): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Verify plan exists
        $currentPlan = $this->find($planId);
        if (!$currentPlan) {
            throw new Exception('Plan not found');
        }

        // Validate plan data
        $this->validatePlanData($planData, $planId);

        // Build SET clause
        $setClauses = [];
        $params = [];
        foreach ($planData as $col => $val) {
            $setClauses[] = "`$col` = ?";
            $params[] = $val;
        }
        $params[] = $planId;

        $stmt = $this->pdo->prepare("UPDATE pos_plans SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = ?");
        $stmt->execute($params);
    }

    public function deletePlan(int $planId): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Verify plan exists
        $currentPlan = $this->find($planId);
        if (!$currentPlan) {
            throw new Exception('Plan not found');
        }

        // Check if plan is in use by any company
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pos_subscriptions WHERE plan_id = ? AND status IN ('active', 'trialing')");
        $params = [$planId];
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Cannot delete plan that is currently in use by tenants');
        }

        // Deactivate the plan (pos_plans doesn't have deleted_at)
        $stmt = $this->pdo->prepare("UPDATE pos_plans SET is_active = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$planId]);
    }

    public function togglePlanStatus(int $planId): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Get current status
        $currentPlan = $this->find($planId);
        if (!$currentPlan) {
            throw new Exception('Plan not found');
        }

        $newStatus = $currentPlan['is_active'] ? 0 : 1;

        $stmt = $this->pdo->prepare("UPDATE pos_plans SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $planId]);
    }

    public function checkSlugExists(string $slug, int $excludePlanId = null): bool {
        $sql = "SELECT COUNT(*) FROM pos_plans WHERE slug = ?";
        $params = [$slug];

        if ($excludePlanId) {
            $sql .= " AND id != ?";
            $params[] = $excludePlanId;
        }

        $stmt = $this->pdo->prepare($sql);
        $this->applyTenantScope($stmt, $params);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    public function getFeatureDefinitions(): array {
        // Feature definitions - available globally
        return [
            'pos' => ['label' => 'Point of Sale', 'icon' => 'fa-cash-register'],
            'inventory' => ['label' => 'Inventory Management', 'icon' => 'fa-boxes'],
            'reports' => ['label' => 'Advanced Reports', 'icon' => 'fa-chart-line'],
            'multi_branch' => ['label' => 'Multi-Branch', 'icon' => 'fa-store'],
            'api_access' => ['label' => 'API Access', 'icon' => 'fa-code'],
            'priority_support' => ['label' => 'Priority Support', 'icon' => 'fa-headset'],
            'loyalty' => ['label' => 'Loyalty Program', 'icon' => 'fa-star'],
            'purchases' => ['label' => 'Purchase Orders', 'icon' => 'fa-truck'],
            'quotations' => ['label' => 'Quotations', 'icon' => 'fa-file-invoice'],
            'shipments' => ['label' => 'Shipments', 'icon' => 'fa-shipping-fast'],
        ];
    }

    private function validatePlanData(array &$planData, int $planId = null): void {
        // Validate required fields
        if (empty($planData['name'])) {
            throw new Exception('Plan name is required');
        }

        if (isset($planData['slug']) && empty($planData['slug'])) {
            throw new Exception('Plan slug is required');
        }

        // Validate numeric fields
        $numericFields = ['price', 'max_users', 'max_branches', 'max_products', 'max_storage_mb', 'max_api_calls', 'trial_days', 'sort_order'];
        foreach ($numericFields as $field) {
            if (isset($planData[$field])) {
                $planData[$field] = (float) $planData[$field];
                if ($planData[$field] < 0) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' cannot be negative');
                }
            }
        }

        // Validate billing_cycle
        if (isset($planData['billing_cycle'])) {
            $validCycles = ['monthly', 'quarterly', 'annual'];
            if (!in_array($planData['billing_cycle'], $validCycles, true)) {
                throw new Exception('Invalid billing cycle');
            }
        }
    }

    public function assignPlanToCompany(int $companyId, int $planId): void {
        // SaaS admin only
        if (!$this->context->allowCrossTenant()) {
            throw new Exception('Access denied: SaaS admin required');
        }

        // Verify plan exists
        $plan = $this->find($planId);
        if (!$plan) {
            throw new Exception('Plan not found');
        }

        // Verify company exists
        $stmt = $this->pdo->prepare("SELECT id FROM pos_tenants WHERE id = ?");
        $stmt->execute([$companyId]);
        if (!$stmt->fetch()) {
            throw new Exception('Company not found');
        }

        // Create or update subscription
        $stmt = $this->pdo->prepare("
            INSERT INTO pos_subscriptions (tenant_id, plan_id, status, trial_ends_at, created_by)
            VALUES (?, ?, 'trialing', DATE_ADD(NOW(), INTERVAL ? DAY), ?)
            ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), updated_by = VALUES(created_by), updated_at = NOW()
        ");
        $stmt->execute([$companyId, $planId, $plan['trial_days'], $this->context->getUserId()]);
    }
}