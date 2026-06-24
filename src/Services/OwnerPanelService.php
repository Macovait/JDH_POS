<?php
/**
 * JDH POS - Owner Panel Service
 * 
 * Core service class for the Super Admin Owner Panel.
 * Handles all SaaS platform management, multi-tenant operations,
 * and ensures strict data isolation between companies.
 * 
 * @package JDH_POS\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace JDH_POS\Services;

use PDO;
use PDOException;
use Exception;

class OwnerPanelService
{
    private PDO $db;
    private int $adminId;
    private array $permissions;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     * @param int $adminId Current admin ID
     */
    public function __construct(PDO $db, int $adminId = 0)
    {
        $this->db = $db;
        $this->adminId = $adminId;
        $this->permissions = $this->loadAdminPermissions();
    }
    
    /**
     * Load admin permissions from their role
     */
    private function loadAdminPermissions(): array
    {
        if (!$this->adminId) {
            return [];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT ar.permissions 
                FROM admins a
                LEFT JOIN admin_roles ar ON a.owner_role_id = ar.id
                WHERE a.id = ? AND ar.active = 1
                LIMIT 1
            ");
            $stmt->execute([$this->adminId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['permissions']) {
                return json_decode($result['permissions'], true) ?: [];
            }
        } catch (PDOException $e) {
            error_log("OwnerPanelService::loadAdminPermissions error: " . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Check if admin has specific permission
     * Super admins always have all permissions
     */
    public function hasPermission(string $module, string $action): bool
    {
        // Super admins have all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        if (isset($this->permissions['all']) && $this->permissions['all'] === true) {
            return true;
        }
        
        if (isset($this->permissions['owner_panel'][$module])) {
            return in_array($action, $this->permissions['owner_panel'][$module]);
        }
        
        return false;
    }
    
    /**
     * Check if current admin is a super admin
     */
    private function isSuperAdmin(): bool
    {
        if (!$this->adminId) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT is_super_admin FROM admins WHERE id = ? LIMIT 1");
            $stmt->execute([$this->adminId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && !empty($result['is_super_admin']);
        } catch (PDOException $e) {
            error_log("OwnerPanelService::isSuperAdmin error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get dashboard statistics - AGGREGATED DATA ONLY
     * No individual company sensitive data exposed
     */
    public function getDashboardStats(): array
    {
        try {
            // Tenant statistics
            $companyStats = $this->db->query("
                SELECT
                    COUNT(*) as total_companies,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_companies,
                    SUM(CASE WHEN status = 'trial' THEN 1 ELSE 0 END) as trial_companies,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_companies,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month
                FROM pos_tenants
            ")->fetch(PDO::FETCH_ASSOC);
            
            // Subscription statistics
            $subscriptionStats = $this->db->query("
                SELECT 
                    COUNT(*) as total_subscriptions,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
                    SUM(CASE WHEN status = 'trialing' THEN 1 ELSE 0 END) as trial_subscriptions,
                    SUM(CASE WHEN status = 'past_due' THEN 1 ELSE 0 END) as past_due_subscriptions,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_subscriptions
                FROM company_subscriptions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ")->fetch(PDO::FETCH_ASSOC);
            
            // Monthly Recurring Revenue (MRR)
            $mrr = $this->db->query("
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN cs.billing_cycle = 'monthly' THEN cs.amount
                        WHEN cs.billing_cycle = 'yearly' THEN cs.amount / 12
                        ELSE cs.amount
                    END
                ), 0) as mrr
                FROM company_subscriptions cs
                WHERE cs.status = 'active'
            ")->fetchColumn();
            
            // Revenue statistics
            $revenueStats = $this->db->query("
                SELECT 
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN amount ELSE 0 END), 0) as today_revenue,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN amount ELSE 0 END), 0) as week_revenue,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END), 0) as month_revenue,
                    COUNT(DISTINCT CASE WHEN status = 'completed' THEN tenant_id END) as paying_companies
                FROM platform_payments
                WHERE status = 'completed'
            ")->fetch(PDO::FETCH_ASSOC);
            
            // Platform usage - AGGREGATED ONLY
            $usageStats = $this->db->query("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE status = 1) as total_active_users,
                    (SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL) as total_branches,
                    (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND tenant_id = t.id) as total_products,
                    (SELECT COUNT(*) FROM sales WHERE status = 'completed' AND voided = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_transactions
            ")->fetch(PDO::FETCH_ASSOC);
            
            // Churn metrics
            $churnStats = $this->db->query("
                SELECT 
                    COUNT(DISTINCT CASE WHEN status = 'cancelled' AND cancelled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN tenant_id END) as churned_this_month,
                    COUNT(DISTINCT CASE WHEN status = 'active' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN tenant_id END) as active_last_month
                FROM company_subscriptions
            ")->fetch(PDO::FETCH_ASSOC);
            
            $churnRate = $churnStats['active_last_month'] > 0 
                ? round(($churnStats['churned_this_month'] / $churnStats['active_last_month']) * 100, 2)
                : 0;
            
            return [
                'companies' => [
                    'total' => (int) $companyStats['total_companies'],
                    'active' => (int) $companyStats['active_companies'],
                    'trial' => (int) $companyStats['trial_companies'],
                    'suspended' => (int) $companyStats['suspended_companies'],
                    'new_this_month' => (int) $companyStats['new_this_month'],
                ],
                'subscriptions' => [
                    'total' => (int) $subscriptionStats['total_subscriptions'],
                    'active' => (int) $subscriptionStats['active_subscriptions'],
                    'trial' => (int) $subscriptionStats['trial_subscriptions'],
                    'past_due' => (int) $subscriptionStats['past_due_subscriptions'],
                    'expired' => (int) $subscriptionStats['expired_subscriptions'],
                ],
                'revenue' => [
                    'mrr' => (float) $mrr,
                    'today' => (float) $revenueStats['today_revenue'],
                    'this_week' => (float) $revenueStats['week_revenue'],
                    'this_month' => (float) $revenueStats['month_revenue'],
                    'paying_companies' => (int) $revenueStats['paying_companies'],
                ],
                'usage' => [
                    'total_active_users' => (int) $usageStats['total_active_users'],
                    'total_branches' => (int) $usageStats['total_branches'],
                    'total_products' => (int) $usageStats['total_products'],
                    'monthly_transactions' => (int) $usageStats['monthly_transactions'],
                ],
                'churn' => [
                    'churned_this_month' => (int) $churnStats['churned_this_month'],
                    'churn_rate_percent' => $churnRate,
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getDashboardStats error: " . $e->getMessage());
            return $this->getEmptyStats();
        }
    }
    
    /**
     * Get empty stats structure
     */
    private function getEmptyStats(): array
    {
        return [
            'companies' => ['total' => 0, 'active' => 0, 'trial' => 0, 'suspended' => 0, 'new_this_month' => 0],
            'subscriptions' => ['total' => 0, 'active' => 0, 'trial' => 0, 'past_due' => 0, 'expired' => 0],
            'revenue' => ['mrr' => 0, 'today' => 0, 'this_week' => 0, 'this_month' => 0, 'paying_companies' => 0],
            'usage' => ['total_active_users' => 0, 'total_branches' => 0, 'total_products' => 0, 'monthly_transactions' => 0],
            'churn' => ['churned_this_month' => 0, 'churn_rate_percent' => 0],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get revenue chart data for dashboard
     */
    public function getRevenueChartData(int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    SUM(amount) as revenue,
                    COUNT(*) as transaction_count,
                    payment_method
                FROM platform_payments
                WHERE status = 'completed'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), payment_method
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for chart.js
            $dates = [];
            $revenues = [];
            $mpesaRevenues = [];
            $cardRevenues = [];
            
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dates[] = date('M d', strtotime($date));
                $revenues[$date] = 0;
                $mpesaRevenues[$date] = 0;
                $cardRevenues[$date] = 0;
            }
            
            foreach ($results as $row) {
                $date = $row['date'];
                if (isset($revenues[$date])) {
                    $revenues[$date] += (float) $row['revenue'];
                    if ($row['payment_method'] === 'mpesa') {
                        $mpesaRevenues[$date] += (float) $row['revenue'];
                    } elseif ($row['payment_method'] === 'card') {
                        $cardRevenues[$date] += (float) $row['revenue'];
                    }
                }
            }
            
            return [
                'labels' => $dates,
                'revenue' => array_values($revenues),
                'mpesa' => array_values($mpesaRevenues),
                'card' => array_values($cardRevenues),
            ];
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getRevenueChartData error: " . $e->getMessage());
            return ['labels' => [], 'revenue' => [], 'mpesa' => [], 'card' => []];
        }
    }
    
    /**
     * Get companies list with filtering
     */
    public function getCompanies(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            $where = ["1=1"];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = "t.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['plan_id'])) {
                $where[] = "ts.plan_id = ?";
                $params[] = $filters['plan_id'];
            }

            if (!empty($filters['search'])) {
                $where[] = "(t.name LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.email')) LIKE ?)";
                $search = "%{$filters['search']}%";
                $params[] = $search;
                $params[] = $search;
            }

            if (!empty($filters['date_from'])) {
                $where[] = "t.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "t.created_at <= ?";
                $params[] = $filters['date_to'];
            }

            $whereClause = implode(" AND ", $where);

            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT t.id)
                FROM pos_tenants t
                LEFT JOIN pos_subscriptions ts ON t.id = ts.tenant_id AND ts.status IN ('active', 'trialing')
                WHERE $whereClause
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Get tenants
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare("
                SELECT
                    t.id,
                    t.name,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.email')) as email,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.phone')) as phone,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.business_type')) as business_type,
                    t.status,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.timezone')) as timezone,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.currency')) as currency,
                    t.created_at,
                    ts.status as subscription_status,
                    ts.current_period_end as subscription_expires,
                    ts.billing_cycle,
                    pp.id as plan_id,
                    pp.name as plan_name,
                    pp.price_monthly as plan_price,
                    (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND status = 1) as user_count,
                    (SELECT COUNT(*) FROM branches WHERE tenant_id = t.id) as branch_count
                FROM pos_tenants t
                LEFT JOIN pos_subscriptions ts ON t.id = ts.tenant_id AND ts.status IN ('active', 'trialing')
                LEFT JOIN pos_plans pp ON ts.plan_id = pp.id
                WHERE $whereClause
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'companies' => $companies,
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ];

        } catch (PDOException $e) {
            error_log("OwnerPanelService::getCompanies error: " . $e->getMessage());
            return ['companies' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }
    
    /**
     * Get company summary (non-sensitive data only)
     */
    public function getCompanySummary(int $companyId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    t.id,
                    t.name,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.email')) as email,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.phone')) as phone,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.address')) as address,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.website')) as website,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.business_type')) as business_type,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.industry')) as industry,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.timezone')) as timezone,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.currency')) as currency,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.tax_rate')) as tax_rate,
                    t.status,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.max_users')) as max_users,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.max_branches')) as max_branches,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.max_products')) as max_products,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.storage_limit_mb')) as storage_limit_mb,
                    JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.storage_used_mb')) as storage_used_mb,
                    t.created_at,
                    t.updated_at,
                    ts.status as subscription_status,
                    ts.billing_cycle,
                    ts.amount as subscription_amount,
                    ts.current_period_start,
                    ts.current_period_end,
                    pp.id as plan_id,
                    pp.name as plan_name,
                    pp.slug as plan_slug,
                    (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND status = 1) as user_count,
                    (SELECT COUNT(*) FROM branches WHERE tenant_id = t.id) as branch_count,
                    (SELECT COUNT(*) FROM products WHERE tenant_id = t.id AND active = 1 AND deleted_at IS NULL) as product_count
                FROM pos_tenants t
                LEFT JOIN pos_subscriptions ts ON t.id = ts.tenant_id AND ts.status IN ('active', 'trialing')
                LEFT JOIN pos_plans pp ON ts.plan_id = pp.id
                WHERE t.id = ?
                LIMIT 1
            ");
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                return null;
            }

            // Get plan features
            $company['features'] = $this->getCompanyFeatures($companyId);

            // Get recent subscription history
            $stmt = $this->db->prepare("
                SELECT id, status, amount, billing_cycle, created_at, current_period_end
                FROM pos_subscriptions
                WHERE tenant_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$companyId]);
            $company['subscription_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $company;

        } catch (PDOException $e) {
            error_log("OwnerPanelService::getCompanySummary error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update company status
     */
    public function updateCompanyStatus(int $companyId, string $status, string $reason = ''): bool
    {
        try {
            $this->db->beginTransaction();

            // Update tenant status
            $stmt = $this->db->prepare("
                UPDATE pos_tenants
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $companyId]);

            // Log the action
            $this->logAuditAction('tenant_status_change', 'tenant', $companyId, [
                'old_status' => $this->getCompanyStatus($companyId),
                'new_status' => $status,
                'reason' => $reason
            ]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("OwnerPanelService::updateCompanyStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upgrade/Downgrade company subscription
     */
    public function changeCompanyPlan(int $companyId, int $newPlanId, string $billingCycle = 'monthly'): bool
    {
        try {
            $this->db->beginTransaction();

            // Get new plan details
            $stmt = $this->db->prepare("SELECT * FROM pos_plans WHERE id = ? AND is_active = 1");
            $stmt->execute([$newPlanId]);
            $newPlan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$newPlan) {
                throw new Exception("Invalid plan selected");
            }

            // Get current subscription
            $stmt = $this->db->prepare("
                SELECT * FROM pos_subscriptions
                WHERE tenant_id = ? AND status IN ('active', 'trialing')
                LIMIT 1
            ");
            $stmt->execute([$companyId]);
            $currentSub = $stmt->fetch(PDO::FETCH_ASSOC);

            // Cancel current subscription
            if ($currentSub) {
                $stmt = $this->db->prepare("
                    UPDATE pos_subscriptions
                    SET status = 'cancelled',
                        cancelled_at = NOW(),
                        cancel_reason = 'Upgraded to plan: {$newPlan['name']}'
                    WHERE id = ?
                ");
                $stmt->execute([$currentSub['id']]);
            }

            // Create new subscription
            $amount = $billingCycle === 'yearly' ? $newPlan['price_yearly'] : $newPlan['price_monthly'];
            $periodEnd = date('Y-m-d H:i:s', strtotime($billingCycle === 'yearly' ? '+1 year' : '+1 month'));

            $stmt = $this->db->prepare("
                INSERT INTO pos_subscriptions
                (tenant_id, plan_id, status, billing_cycle, amount, currency,
                 current_period_start, current_period_end, created_at)
                VALUES (?, ?, 'active', ?, ?, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([
                $companyId, $newPlanId, $billingCycle, $amount,
                $newPlan['currency'], $periodEnd
            ]);

            // Update tenant settings with new limits
            $currentSettings = $this->getTenantSettings($companyId);
            $updatedSettings = array_merge($currentSettings, [
                'max_users' => $newPlan['max_users'],
                'max_branches' => $newPlan['max_branches'],
                'max_products' => $newPlan['max_products'],
                'storage_limit_mb' => $newPlan['max_storage_mb']
            ]);

            $stmt = $this->db->prepare("
                UPDATE pos_tenants
                SET settings = ?, plan_id = ?, status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([json_encode($updatedSettings), $newPlanId, $companyId]);

            // Log the action
            $this->logAuditAction('subscription_change', 'subscription', $companyId, [
                'old_plan' => $currentSub['plan_id'] ?? null,
                'new_plan' => $newPlanId,
                'billing_cycle' => $billingCycle,
                'amount' => $amount
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("OwnerPanelService::changeCompanyPlan error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all subscription plans
     */
    public function getSubscriptionPlans(bool $includeInactive = false): array
    {
        try {
            $sql = "SELECT * FROM pos_plans";
            if (!$includeInactive) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY price ASC";

            $stmt = $this->db->query($sql);
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get features for each plan
            foreach ($plans as &$plan) {
                $stmt = $this->db->prepare("
                    SELECT pf.feature_id, f.name, f.module_name, pf.is_enabled, pf.limit_value
                    FROM pos_plan_features pf
                    JOIN pos_features f ON pf.feature_id = f.id
                    WHERE pf.plan_id = ?
                    ORDER BY f.module_name, f.name
                ");
                $stmt->execute([$plan['id']]);
                $plan['features'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $plans;

        } catch (PDOException $e) {
            error_log("OwnerPanelService::getSubscriptionPlans error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create or update subscription plan
     */
    public function saveSubscriptionPlan(array $data): bool
    {
        try {
            $this->db->beginTransaction();
            
            if (!empty($data['id'])) {
                // Update existing plan
                $stmt = $this->db->prepare("
                    UPDATE subscription_plans 
                    SET name = ?, slug = ?, description = ?, price_monthly = ?, price_yearly = ?,
                        max_users = ?, max_branches = ?, max_products = ?, max_storage_mb = ?,
                        is_active = ?, is_popular = ?, trial_days = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['name'], $data['slug'], $data['description'],
                    $data['price_monthly'], $data['price_yearly'],
                    $data['max_users'], $data['max_branches'], $data['max_products'],
                    $data['max_storage_mb'], $data['is_active'] ?? 1,
                    $data['is_popular'] ?? 0, $data['trial_days'] ?? 14,
                    $data['id']
                ]);
                $planId = $data['id'];
            } else {
                // Create new plan
                $stmt = $this->db->prepare("
                    INSERT INTO subscription_plans 
                    (name, slug, description, price_monthly, price_yearly, max_users, max_branches,
                     max_products, max_storage_mb, is_active, is_popular, trial_days, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $data['name'], $data['slug'], $data['description'],
                    $data['price_monthly'], $data['price_yearly'],
                    $data['max_users'], $data['max_branches'], $data['max_products'],
                    $data['max_storage_mb'], $data['is_active'] ?? 1,
                    $data['is_popular'] ?? 0, $data['trial_days'] ?? 14
                ]);
                $planId = $this->db->lastInsertId();
            }
            
            // Update plan features if provided
            if (!empty($data['features'])) {
                // Clear existing features
                $stmt = $this->db->prepare("DELETE FROM plan_features WHERE plan_id = ?");
                $stmt->execute([$planId]);
                
                // Insert new features
                $stmt = $this->db->prepare("
                    INSERT INTO plan_features (plan_id, feature_key, is_enabled, limit_value)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($data['features'] as $feature) {
                    $stmt->execute([
                        $planId,
                        $feature['feature_key'],
                        $feature['is_enabled'] ? 1 : 0,
                        $feature['limit_value'] ?? null
                    ]);
                }
            }
            
            $this->logAuditAction(
                !empty($data['id']) ? 'plan_update' : 'plan_create',
                'plan',
                $planId,
                $data
            );
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("OwnerPanelService::saveSubscriptionPlan error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get company features
     */
    public function getCompanyFeatures(int $companyId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT f.name, f.module_name, f.description, pf.is_enabled
                FROM pos_subscriptions ts
                JOIN pos_plan_features pf ON ts.plan_id = pf.plan_id
                JOIN pos_features f ON pf.feature_id = f.id
                WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trialing')
                ORDER BY f.module_name, f.name
            ");
            $stmt->execute([$companyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("OwnerPanelService::getCompanyFeatures error: " . $e->getMessage());
            return [];
        }
    }

    private function getTenantSettings(int $tenantId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT settings FROM pos_tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['settings']) {
                return json_decode($result['settings'], true) ?: [];
            }

            return [];
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getTenantSettings error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get platform payments
     */
    public function getPlatformPayments(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['status'])) {
                $where[] = "pp.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['payment_method'])) {
                $where[] = "pp.payment_method = ?";
                $params[] = $filters['payment_method'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "pp.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "pp.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = implode(" AND ", $where);
            
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM platform_payments pp WHERE $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get payments
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare("
                SELECT 
                    pp.*,
                    c.name as company_name,
                    sp.name as plan_name
                FROM platform_payments pp
                LEFT JOIN companies c ON pp.tenant_id = c.id
                LEFT JOIN company_subscriptions cs ON pp.subscription_id = cs.id
                LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
                WHERE $whereClause
                ORDER BY pp.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'payments' => $payments,
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ];
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getPlatformPayments error: " . $e->getMessage());
            return ['payments' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }
    
    /**
     * Get revenue summary for date range
     */
    public function getRevenueSummary(string $periodType = 'monthly', int $periods = 12): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM platform_revenue_summary
                WHERE period_type = ?
                ORDER BY period_start DESC
                LIMIT ?
            ");
            $stmt->execute([$periodType, $periods]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getRevenueSummary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system analytics
     */
    public function getSystemAnalytics(string $dateFrom, string $dateTo): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM system_analytics
                WHERE metric_date BETWEEN ? AND ?
                ORDER BY metric_date ASC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getSystemAnalytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create support access session
     */
    public function createSupportAccess(int $companyId, string $reason, string $accessType = 'read_only', int $durationMinutes = 60): ?array
    {
        try {
            // Check if admin already has active access
            $stmt = $this->db->prepare("
                SELECT id FROM support_access_logs 
                WHERE admin_id = ? AND tenant_id = ? AND status = 'active' AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$this->adminId, $companyId]);
            if ($stmt->fetch()) {
                throw new Exception("You already have an active support session for this company");
            }
            
            // Generate access token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationMinutes} minutes"));
            
            $stmt = $this->db->prepare("
                INSERT INTO support_access_logs 
                (admin_id, tenant_id, access_token, reason, access_type, expires_at, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->adminId,
                $companyId,
                $token,
                $reason,
                $accessType,
                $expiresAt,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            $accessId = $this->db->lastInsertId();
            
            // Log the action
            $this->logAuditAction('support_access_created', 'support_access', $accessId, [
                'tenant_id' => $companyId,
                'reason' => $reason,
                'access_type' => $accessType,
                'duration_minutes' => $durationMinutes
            ]);
            
            return [
                'id' => $accessId,
                'token' => $token,
                'expires_at' => $expiresAt,
                'tenant_id' => $companyId
            ];
            
        } catch (Exception $e) {
            error_log("OwnerPanelService::createSupportAccess error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Revoke support access
     */
    public function revokeSupportAccess(int $accessId, string $reason = ''): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE support_access_logs 
                SET status = 'revoked', ended_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$accessId]);
            
            $this->logAuditAction('support_access_revoked', 'support_access', $accessId, [
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::revokeSupportAccess error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active support access sessions
     */
    public function getActiveSupportAccess(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    sal.*,
                    a.name as admin_name,
                    a.email as admin_email,
                    c.name as company_name
                FROM support_access_logs sal
                JOIN admins a ON sal.admin_id = a.id
                JOIN companies c ON sal.tenant_id = c.id
                WHERE sal.status = 'active' AND sal.expires_at > NOW()
                ORDER BY sal.started_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getActiveSupportAccess error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get support access history
     */
    public function getSupportAccessHistory(int $page = 1, int $perPage = 20): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare("
                SELECT 
                    sal.*,
                    a.name as admin_name,
                    a.email as admin_email,
                    c.name as company_name,
                    (SELECT COUNT(*) FROM support_access_actions WHERE access_log_id = sal.id) as action_count
                FROM support_access_logs sal
                JOIN admins a ON sal.admin_id = a.id
                JOIN companies c ON sal.tenant_id = c.id
                ORDER BY sal.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$perPage, $offset]);
            
            $countStmt = $this->db->query("SELECT COUNT(*) FROM support_access_logs");
            $total = $countStmt->fetchColumn();
            
            return [
                'sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ];
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getSupportAccessHistory error: " . $e->getMessage());
            return ['sessions' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }
    
    /**
     * Get admin roles
     */
    public function getAdminRoles(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT * FROM admin_roles 
                WHERE deleted_at IS NULL 
                ORDER BY is_system DESC, name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getAdminRoles error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit logs
     */
    public function getAuditLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['admin_id'])) {
                $where[] = "admin_id = ?";
                $params[] = $filters['admin_id'];
            }
            
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['entity_type'])) {
                $where[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = implode(" AND ", $where);
            
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM owner_audit_logs WHERE $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get logs
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare("
                SELECT 
                    oal.*,
                    a.name as admin_name,
                    a.email as admin_email
                FROM owner_audit_logs oal
                LEFT JOIN admins a ON oal.admin_id = a.id
                WHERE $whereClause
                ORDER BY oal.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            
            return [
                'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ];
            
        } catch (PDOException $e) {
            error_log("OwnerPanelService::getAuditLogs error: " . $e->getMessage());
            return ['logs' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }
    
    /**
     * Log support access action
     */
    public function logSupportAction(int $accessId, string $action, string $details = ''): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO support_access_actions 
                (access_id, admin_id, action, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $accessId,
                $this->adminId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("OwnerPanelService::logSupportAction error: " . $e->getMessage());
        }
    }
    
    /**
     * Create audit log entry (public API)
     */
    public function createAuditLog(string $action, string $entityType, ?int $entityId, array $data = []): void
    {
        $this->logAuditAction($action, $entityType, $entityId, $data);
    }
    
    /**
     * Log audit action (internal)
     */
    private function logAuditAction(string $action, string $entityType, ?int $entityId, array $data = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO owner_audit_logs 
                (admin_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->adminId,
                $action,
                $entityType,
                $entityId,
                $data['description'] ?? $action,
                isset($data['old_values']) ? json_encode($data['old_values']) : null,
                isset($data['new_values']) ? json_encode($data['new_values']) : json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("OwnerPanelService::logAuditAction error: " . $e->getMessage());
        }
    }
    
    /**
     * Get company status
     */
    private function getCompanyStatus(int $companyId): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT status FROM pos_tenants WHERE id = ?");
            $stmt->execute([$companyId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return null;
        }
    }
}
