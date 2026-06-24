<?php
/**
 * Billing Service - Subscription and payment management
 * 
 * Handles subscription lifecycle, payment processing,
 * and invoice generation for Shopify-level SaaS
 * 
 * @package JakababaPOS
 * @version 2.0.0
 */

namespace JakababaPOS\Services\Billing;

class BillingService
{
    private \PDO $db;
    private static ?BillingService $instance = null;
    
    const TABLE_PREFIX = 'pos_';
    
    /**
     * Subscription statuses
     */
    const STATUS_TRIALING = 'trialing';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAUSED = 'paused';
    const STATUS_EXPIRED = 'expired';
    
    /**
     * Get singleton instance
     */
    public static function getInstance(\PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get current subscription for tenant
     */
    public function getCurrentSubscription(?int $tenantId = null): ?array
    {
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT s.*, p.name as plan_name, p.price as plan_price,
                   p.max_users, p.max_branches, p.max_products, p.max_api_calls
            FROM " . self::TABLE_PREFIX . "subscriptions s
            INNER JOIN " . self::TABLE_PREFIX . "plans p ON s.plan_id = p.id
            WHERE s.tenant_id = :tenant_id
            ORDER BY s.id DESC
            LIMIT 1
        ");
        
        $stmt->execute([':tenant_id' => $tenantId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Check if subscription is active
     */
    public function isActive(?int $tenantId = null): bool
    {
        $subscription = $this->getCurrentSubscription($tenantId);
        
        return $subscription && in_array($subscription['status'], [
            self::STATUS_ACTIVE,
            self::STATUS_TRIALING
        ]);
    }
    
    /**
     * Check if on trial
     */
    public function isOnTrial(?int $tenantId = null): bool
    {
        $subscription = $this->getCurrentSubscription($tenantId);
        
        return $subscription && $subscription['status'] === self::STATUS_TRIALING;
    }
    
    /**
     * Get days remaining in trial
     */
    public function getTrialDaysRemaining(?int $tenantId = null): int
    {
        $subscription = $this->getCurrentSubscription($tenantId);
        
        if (!$subscription || !$subscription['trial_ends_at']) {
            return 0;
        }
        
        $trialEnd = strtotime($subscription['trial_ends_at']);
        $now = time();
        
        if ($trialEnd < $now) {
            return 0;
        }
        
        return ceil(($trialEnd - $now) / 86400);
    }
    
    /**
     * Check if trial about to expire (within 3 days)
     */
    public function isTrialExpiring(?int $tenantId = null): bool
    {
        return $this->getTrialDaysRemaining($tenantId) <= 3;
    }
    
    /**
     * Get all available plans
     */
    public function getPlans(?bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM " . self::TABLE_PREFIX . "plans";
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        
        $sql .= " ORDER BY sort_order ASC, price ASC";
        
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get plan by ID
     */
    public function getPlan(int $planId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::TABLE_PREFIX . "plans WHERE id = :id
        ");
        
        $stmt->execute([':id' => $planId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get plan features
     */
    public function getPlanFeatures(int $planId): array
    {
        $stmt = $this->db->prepare("
            SELECT f.feature_key, f.name, f.module_name, pf.is_enabled
            FROM " . self::TABLE_PREFIX . "plan_features pf
            INNER JOIN " . self::TABLE_PREFIX . "features f ON pf.feature_id = f.id
            WHERE pf.plan_id = :plan_id AND pf.is_enabled = 1 AND f.is_active = 1
            ORDER BY f.module_name, f.name
        ");
        
        $stmt->execute([':plan_id' => $planId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Check feature access
     */
    public function hasFeature(string $featureKey, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT pf.is_enabled
            FROM " . self::TABLE_PREFIX . "plan_features pf
            INNER JOIN " . self::TABLE_PREFIX . "features f ON pf.feature_id = f.id
            INNER JOIN " . self::TABLE_PREFIX . "subscriptions s ON s.plan_id = pf.plan_id
            WHERE s.tenant_id = :tenant_id 
            AND s.status IN ('active', 'trialing')
            AND f.feature_key = :feature_key
            AND pf.is_enabled = 1
            LIMIT 1
        ");
        
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':feature_key' => $featureKey
        ]);
        
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Upgrade subscription
     */
    public function upgrade(int $planId, ?int $tenantId = null): bool
    {
        global $db;
        
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return false;
        }
        
        // Get new plan
        $newPlan = $this->getPlan($planId);
        
        if (!$newPlan || !$newPlan['is_active']) {
            return false;
        }
        
        // Cancel current subscription
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "subscriptions 
            SET status = :status, cancelled_at = NOW(), cancel_reason = :reason
            WHERE tenant_id = :tenant_id AND status IN ('active', 'trialing')
        ")->execute([
            ':status' => self::STATUS_CANCELLED,
            ':reason' => "Upgraded to plan: " . $newPlan['name'],
            ':tenant_id' => $tenantId
        ]);
        
        // Create new subscription
        $periodStart = date('Y-m-d H:i:s');
        $periodEnd = date('Y-m-d H:i:s', strtotime('+1 ' . $newPlan['billing_cycle']));
        
        $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "subscriptions
            (tenant_id, plan_id, status, billing_cycle, amount, currency, current_period_start, current_period_end)
            VALUES (:tenant_id, :plan_id, :status, :cycle, :amount, :currency, :period_start, :period_end)
        ")->execute([
            ':tenant_id' => $tenantId,
            ':plan_id' => $planId,
            ':status' => $newPlan['price'] > 0 ? self::STATUS_ACTIVE : self::STATUS_TRIALING,
            ':cycle' => $newPlan['billing_cycle'],
            ':amount' => $newPlan['price'],
            ':currency' => $newPlan['currency'],
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ]);
        
        // Log upgrade
        $logger = \JakababaPOS\Services\Security\AuditLogger::getInstance($db);
        $logger->logSecurity('subscription_upgraded', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'plan_name' => $newPlan['name']
        ]);
        
        return true;
    }
    
    /**
     * Cancel subscription
     */
    public function cancel(string $reason, ?int $tenantId = null): bool
    {
        global $db;
        
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return false;
        }
        
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "subscriptions
            SET status = :status, cancelled_at = NOW(), cancel_reason = :reason
            WHERE tenant_id = :tenant_id AND status IN ('active', 'trialing')
        ")->execute([
            ':status' => self::STATUS_CANCELLED,
            ':reason' => $reason,
            ':tenant_id' => $tenantId
        ]);
        
        // Update tenant status
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "tenants
            SET status = 'cancelled'
            WHERE id = :tenant_id
        ")->execute([':tenant_id' => $tenantId]);
        
        // Log cancellation
        $logger = \JakababaPOS\Services\Security\AuditLogger::getInstance($db);
        $logger->logSecurity('subscription_cancelled', [
            'tenant_id' => $tenantId,
            'reason' => $reason
        ]);
        
        return true;
    }
    
    /**
     * Create invoice for subscription
     */
    public function createInvoice(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return [];
        }
        
        $subscription = $this->getCurrentSubscription($tenantId);
        
        if (!$subscription) {
            return [];
        }
        
        $invoiceNumber = $this->generateInvoiceNumber($tenantId);
        
        $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "invoices
            (tenant_id, subscription_id, invoice_number, amount, currency, status, due_date)
            VALUES (:tenant_id, :sub_id, :number, :amount, :currency, :status, :due_date)
        ")->execute([
            ':tenant_id' => $tenantId,
            ':sub_id' => $subscription['id'],
            ':number' => $invoiceNumber,
            ':amount' => $subscription['amount'],
            ':currency' => $subscription['currency'],
            ':status' => 'pending',
            ':due_date' => $subscription['current_period_end']
        ]);
        
        $invoiceId = (int) $this->db->lastInsertId();
        
        return [
            'id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'amount' => $subscription['amount'],
            'currency' => $subscription['currency'],
            'due_date' => $subscription['current_period_end']
        ];
    }
    
    /**
     * Record payment
     */
    public function recordPayment(
        int $invoiceId,
        string $method,
        string $reference,
        ?string $receipt = null
    ): bool {
        // Update invoice
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "invoices
            SET status = 'paid', paid_at = NOW(), payment_method = :method, 
                payment_reference = :ref, mpesa_receipt = :receipt
            WHERE id = :id
        ")->execute([
            ':method' => $method,
            ':ref' => $reference,
            ':receipt' => $receipt,
            ':id' => $invoiceId
        ]);
        
        // Update subscription status
        $stmt = $this->db->prepare("
            SELECT tenant_id FROM " . self::TABLE_PREFIX . "invoices WHERE id = :id
        ");
        $stmt->execute([':id' => $invoiceId]);
        $tenantId = $stmt->fetchColumn();
        
        if ($tenantId) {
            $this->db->prepare("
                UPDATE " . self::TABLE_PREFIX . "subscriptions
                SET status = 'active', 
                    current_period_start = NOW(),
                    current_period_end = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                WHERE tenant_id = :tenant_id AND status = 'past_due'
            ")->execute([':tenant_id' => $tenantId]);
        }
        
        return true;
    }
    
    /**
     * Process failed payment
     */
    public function handleFailedPayment(int $invoiceId): void
    {
        // Mark invoice as failed
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "invoices
            SET status = 'failed'
            WHERE id = :id
        ")->execute([':id' => $invoiceId]);
        
        // Get tenant
        $stmt = $this->db->prepare("
            SELECT tenant_id FROM " . self::TABLE_PREFIX . "invoices WHERE id = :id
        ");
        $stmt->execute([':id' => $invoiceId]);
        $tenantId = $stmt->fetchColumn();
        
        if ($tenantId) {
            // Mark subscription as past due
            $this->db->prepare("
                UPDATE " . self::TABLE_PREFIX . "subscriptions
                SET status = 'past_due'
                WHERE tenant_id = :tenant_id AND status = 'active'
            ")->execute([':tenant_id' => $tenantId]);
        }
    }
    
    /**
     * Get invoices for tenant
     */
    public function getInvoices(?int $tenantId = null, ?int $limit = 20): array
    {
        $tenantId = $tenantId ?? $_SESSION['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::TABLE_PREFIX . "invoices
            WHERE tenant_id = :tenant_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        
        $stmt->execute([':tenant_id' => $tenantId, ':limit' => $limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(int $tenantId): string
    {
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM " . self::TABLE_PREFIX . "invoices
            WHERE tenant_id = :tenant_id AND YEAR(created_at) = :year
        ");
        
        $stmt->execute([':tenant_id' => $tenantId, ':year' => $year]);
        $count = (int) $stmt->fetchColumn() + 1;
        
        return sprintf('INV-%s-%s-%04d', $year, $month, $count);
    }
}

/**
 * Helper functions
 */
if (!function_exists('getCurrentSubscription')) {
    function getCurrentSubscription(?int $tenantId = null): ?array
    {
        global $db;
        $service = BillingService::getInstance($db);
        return $service->getCurrentSubscription($tenantId);
    }
}

if (!function_exists('isSubscriptionActive')) {
    function isSubscriptionActive(?int $tenantId = null): bool
    {
        global $db;
        $service = BillingService::getInstance($db);
        return $service->isActive($tenantId);
    }
}

if (!function_exists('hasFeature')) {
    function hasFeature(string $featureKey, ?int $tenantId = null): bool
    {
        global $db;
        $service = BillingService::getInstance($db);
        return $service->hasFeature($featureKey, $tenantId);
    }
}