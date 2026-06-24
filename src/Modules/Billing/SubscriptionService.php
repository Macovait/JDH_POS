<?php
/**
 * Subscription Service - Billing Lifecycle Management
 * 
 * Manages: subscriptions, invoices, payments, billing cycles
 * Integrates: Stripe, PayPal, MPesa
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

class SubscriptionService {
    
    private static ?SubscriptionService $instance = null;
    private $db;
    private int $tenantId;
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        global $db;
        $this->db = $db ?? get_db_connection();
        $this->tenantId = $_SESSION['tenant_id'] ?? $_SESSION['tenant_id'] ?? 0;
        
        // Include gateway files with namespaces
        require_once __DIR__ . '/Gateways/StripeGateway.php';
        require_once __DIR__ . '/Gateways/PayPalGateway.php';
        require_once __DIR__ . '/PaymentGatewayInterface.php';
    }
    
    /**
     * Get current subscription
     */
    public function getCurrentSubscription(): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, sp.name as plan_name, sp.price as plan_price, sp.max_users, 
                   sp.max_branches, sp.max_products, sp.max_api_calls
            FROM subscriptions s
            LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE s.tenant_id = :tenant_id
            ORDER BY s.id DESC
            LIMIT 1
        ");
        
        $stmt->execute([':tenant_id' => $this->tenantId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Check if subscription is active
     */
    public function isActive(): bool {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub) {
            return true; // No subscription = free tier access
        }
        
        return in_array($sub['status'], ['active', 'trialing']);
    }
    
    /**
     * Check if on trial
     */
    public function isOnTrial(): bool {
        $sub = $this->getCurrentSubscription();
        
        return $sub && $sub['status'] === 'trialing';
    }
    
    /**
     * Get trial days remaining
     */
    public function getTrialDaysRemaining(): int {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub || !$sub['trial_ends_at']) {
            return 0;
        }
        
        $end = strtotime($sub['trial_ends_at']);
        $now = time();
        
        if ($end < $now) {
            return 0;
        }
        
        return ceil(($end - $now) / 86400);
    }
    
    /**
     * Check if subscription is expiring soon
     */
    public function isExpiringSoon(int $days = 7): bool {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub || $sub['status'] !== 'active') {
            return $this->isOnTrial() && $this->getTrialDaysRemaining() <= $days;
        }
        
        $periodEnd = strtotime($sub['current_period_end']);
        $now = time();
        
        return ($periodEnd - $now) <= ($days * 86400);
    }
    
    /**
     * Get all available plans
     */
    public function getPlans(): array {
        return $this->db->query("
            SELECT * FROM subscription_plans 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, price ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get plan by ID
     */
    public function getPlan(int $planId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM subscription_plans WHERE id = :id AND is_active = 1
        ");
        
        $stmt->execute([':id' => $planId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Check feature access
     */
    public function hasFeature(string $featureKey): bool {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT pf.is_enabled
            FROM plan_features pf
            INNER JOIN features f ON pf.feature_id = f.id
            WHERE pf.plan_id = :plan_id AND f.feature_key = :feature_key
            AND pf.is_enabled = 1
            LIMIT 1
        ");
        
        $stmt->execute([
            ':plan_id' => $sub['plan_id'],
            ':feature_key' => $featureKey
        ]);
        
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Upgrade subscription
     */
    public function upgrade(int $planId): array {
        $plan = $this->getPlan($planId);
        
        if (!$plan) {
            return ['success' => false, 'error' => 'Invalid plan'];
        }
        
        $sub = $this->getCurrentSubscription();
        
        // Cancel current subscription
        if ($sub && $sub['status'] !== 'cancelled') {
            $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'cancelled', cancelled_at = NOW(), 
                    cancel_reason = 'Upgraded to: ' || :plan_name
                WHERE id = :id
            ")->execute([
                ':id' => $sub['id'],
                ':plan_name' => $plan['name']
            ]);
        }
        
        // Create new subscription
        $periodStart = date('Y-m-d H:i:s');
        $periodEnd = date('Y-m-d H:i:s', strtotime('+1 ' . $plan['billing_cycle']));
        
        $this->db->prepare("
            INSERT INTO subscriptions 
            (tenant_id, plan_id, status, billing_cycle, amount, currency, current_period_start, current_period_end)
            VALUES (:tenant_id, :plan_id, :status, :cycle, :amount, :currency, :period_start, :period_end)
        ")->execute([
            ':tenant_id' => $this->tenantId,
            ':plan_id' => $planId,
            ':status' => 'active',
            ':cycle' => $plan['billing_cycle'],
            ':amount' => $plan['price'],
            ':currency' => $plan['currency'] ?? 'KES',
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ]);
        
        $this->logAudit('subscription_upgraded', [
            'old_plan' => $sub['plan_id'] ?? null,
            'new_plan' => $planId,
        ]);
        
        return [
            'success' => true,
            'subscription_id' => $this->db->lastInsertId(),
            'plan' => $plan['name'],
            'amount' => $plan['price'],
            'cycle' => $plan['billing_cycle'],
        ];
    }
    
    /**
     * Cancel subscription
     */
    public function cancel(string $reason = ''): bool {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub) {
            return false;
        }
        
        $this->db->prepare("
            UPDATE subscriptions 
            SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = :reason
            WHERE id = :id
        ")->execute([
            ':id' => $sub['id'],
            ':reason' => $reason
        ]);
        
        $this->logAudit('subscription_cancelled', [
            'reason' => $reason,
        ]);
        
        return true;
    }
    
    /**
     * Process payment via gateway
     */
    public function processPayment(string $gateway, float $amount, array $metadata = []): array {
        $config = ['tenant_id' => $this->tenantId];
        
        switch ($gateway) {
            case 'stripe':
                $gatewayInstance = new StripeGateway($config);
                break;
            case 'paypal':
                $gatewayInstance = new PayPalGateway($config);
                break;
            default:
                // MPesa or other - use existing implementation
                return ['success' => false, 'error' => 'Unsupported gateway'];
        }
        
        return $gatewayInstance->initializePayment($amount, 'KES', $metadata);
    }
    
    /**
     * Record successful payment
     */
    public function recordPayment(array $paymentData): bool {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub) {
            return false;
        }
        
        // Update subscription status
        $periodStart = date('Y-m-d H:i:s');
        $periodEnd = date('Y-m-d H:i:s', strtotime('+1 ' . $sub['billing_cycle']));
        
        $this->db->prepare("
            UPDATE subscriptions 
            SET status = 'active', current_period_start = :period_start, current_period_end = :period_end
            WHERE id = :id
        ")->execute([
            ':id' => $sub['id'],
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ]);
        
        // Log payment
        $this->db->prepare("
            INSERT INTO subscription_payments 
            (subscription_id, tenant_id, amount, currency, gateway, reference, status, created_at)
            VALUES (:sub_id, :tenant_id, :amount, :currency, :gateway, :reference, 'completed', NOW())
        ")->execute([
            ':sub_id' => $sub['id'],
            ':tenant_id' => $this->tenantId,
            ':amount' => $paymentData['amount'] ?? $sub['amount'],
            ':currency' => $paymentData['currency'] ?? 'KES',
            ':gateway' => $paymentData['gateway'] ?? 'unknown',
            ':reference' => $paymentData['reference'] ?? '',
        ]);
        
        $this->logAudit('payment_received', $paymentData);
        
        return true;
    }
    
    /**
     * Create invoice
     */
    public function createInvoice(): ?array {
        $sub = $this->getCurrentSubscription();
        
        if (!$sub) {
            return null;
        }
        
        $plan = $this->getPlan($sub['plan_id']);
        
        // Generate invoice number
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM invoices 
            WHERE tenant_id = $this->tenantId 
            AND YEAR(created_at) = $year
        ");
        $count = (int) $stmt->fetchColumn() + 1;
        
        $invoiceNumber = sprintf('INV-%s-%s-%04d', $year, $month, $count);
        
        $this->db->prepare("
            INSERT INTO invoices 
            (tenant_id, subscription_id, invoice_number, amount, currency, status, due_date, created_at)
            VALUES (:tenant_id, :sub_id, :number, :amount, :currency, :status, :due_date, NOW())
        ")->execute([
            ':tenant_id' => $this->tenantId,
            ':sub_id' => $sub['id'],
            ':number' => $invoiceNumber,
            ':amount' => $sub['amount'],
            ':currency' => $sub['currency'],
            ':status' => 'pending',
            ':due_date' => $sub['current_period_end'],
        ]);
        
        return [
            'invoice_number' => $invoiceNumber,
            'amount' => $sub['amount'],
            'currency' => $sub['currency'],
            'due_date' => $sub['current_period_end'],
        ];
    }
    
    /**
     * Get invoices
     */
    public function getInvoices(int $limit = 10): array {
        return $this->db->query("
            SELECT * FROM invoices 
            WHERE tenant_id = $this->tenantId 
            ORDER BY created_at DESC 
            LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Handle failed payment
     */
    public function handleFailedPayment(string $reason = ''): void {
        $this->db->prepare("
            UPDATE subscriptions 
            SET status = 'past_due'
            WHERE tenant_id = :tenant_id AND status = 'active'
        ")->execute([':tenant_id' => $this->tenantId]);
        
        $this->logAudit('payment_failed', ['reason' => $reason]);
    }
    
    /**
     * Log audit event
     */
    private function logAudit(string $action, array $data = []): void {
        try {
            $this->db->prepare("
                INSERT INTO audit_logs 
                (tenant_id, action, entity_type, new_values, created_at)
                VALUES (:tenant_id, :action, 'subscription', :data, NOW())
            ")->execute([
                ':tenant_id' => $this->tenantId,
                ':action' => $action,
                ':data' => json_encode($data),
            ]);
        } catch (\Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}

/**
 * Helper functions
 */
if (!function_exists('getCurrentSubscription')) {
    function getCurrentSubscription(): ?array {
        return SubscriptionService::getInstance()->getCurrentSubscription();
    }
}

if (!function_exists('isSubscriptionActive')) {
    function isSubscriptionActive(): bool {
        return SubscriptionService::getInstance()->isActive();
    }
}

if (!function_exists('hasSubscriptionFeature')) {
    function hasSubscriptionFeature(string $featureKey): bool {
        return SubscriptionService::getInstance()->hasFeature($featureKey);
    }
}