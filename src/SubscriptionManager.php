<?php
/**
 * Subscription Manager
 * Handles trial/subscription checks with auto table detection.
 */

class SubscriptionManager
{
    private PDO $pdo;
    private string $subscriptionTable;
    private string $planTable;
    private string $tenantTable;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->detectTables();
    }

    /**
     * Auto-detect which subscription/plan tables exist.
     */
    private function detectTables(): void
    {
        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $tables = array_map('strtolower', $tables);

        // Subscription table preference
        if (in_array('pos_subscriptions', $tables)) {
            $this->subscriptionTable = 'pos_subscriptions';
        } elseif (in_array('subscriptions', $tables)) {
            $this->subscriptionTable = 'subscriptions';
        } elseif (in_array('tenant_subscriptions', $tables)) {
            $this->subscriptionTable = 'tenant_subscriptions';
        } else {
            $this->subscriptionTable = 'pos_subscriptions';
        }

        // Plan table preference
        if (in_array('pos_plans', $tables)) {
            $this->planTable = 'pos_plans';
        } elseif (in_array('plans', $tables)) {
            $this->planTable = 'plans';
        } elseif (in_array('subscription_plans', $tables)) {
            $this->planTable = 'subscription_plans';
        } else {
            $this->planTable = 'pos_plans';
        }

        // Tenant table preference
        if (in_array('tenants', $tables)) {
            $this->tenantTable = 'tenants';
        } elseif (in_array('pos_tenants', $tables)) {
            $this->tenantTable = 'pos_tenants';
        } elseif (in_array('companies', $tables)) {
            $this->tenantTable = 'companies';
        } else {
            $this->tenantTable = 'tenants';
        }
    }

    /**
     * Get the active subscription for a tenant.
     */
    public function getTenantSubscription(int $tenantId): ?array
    {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM `{$this->subscriptionTable}`")->fetchAll(PDO::FETCH_COLUMN);
            $hasPlanJoin = in_array('plan_id', $cols);

            $sql = "SELECT s.*";
            if ($hasPlanJoin) {
                $sql .= ", p.name as plan_name, p.slug as plan_slug, p.price as plan_price, p.currency as plan_currency";
            }
            $sql .= " FROM `{$this->subscriptionTable}` s";
            if ($hasPlanJoin) {
                $sql .= " LEFT JOIN `{$this->planTable}` p ON s.plan_id = p.id";
            }
            $sql .= " WHERE s.tenant_id = ? ORDER BY s.created_at DESC LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log('SubscriptionManager::getTenantSubscription error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if tenant's trial has expired.
     */
    public function isTrialExpired(int $tenantId): bool
    {
        $sub = $this->getTenantSubscription($tenantId);
        if (!$sub) {
            // No subscription record — treat as expired if tenant is on trial
            $tenant = $this->getTenant($tenantId);
            return isset($tenant['status']) && $tenant['status'] === 'trial';
        }

        $status = $sub['status'] ?? '';
        if ($status !== 'trialing' && $status !== 'trial') {
            return false; // Not on trial
        }

        $trialEnds = $sub['trial_ends_at'] ?? ($sub['current_period_end'] ?? null);
        if (!$trialEnds) {
            return false;
        }

        try {
            $end = new DateTime($trialEnds);
            $now = new DateTime();
            return $now > $end;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get days remaining in trial. Returns 0 or negative if expired.
     */
    public function getTrialDaysRemaining(int $tenantId): int
    {
        $sub = $this->getTenantSubscription($tenantId);
        if (!$sub) {
            return 0;
        }

        $trialEnds = $sub['trial_ends_at'] ?? ($sub['current_period_end'] ?? null);
        if (!$trialEnds) {
            return 0;
        }

        try {
            $end = new DateTime($trialEnds);
            $now = new DateTime();
            $diff = (int) $now->diff($end)->format('%r%a');
            return $diff;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Check if tenant is within the grace period after trial expiry.
     * Grace period = 24 hours after trial ends.
     */
    public function isInGracePeriod(int $tenantId): bool
    {
        $sub = $this->getTenantSubscription($tenantId);
        if (!$sub) {
            return false;
        }

        $status = $sub['status'] ?? '';
        if ($status !== 'trialing' && $status !== 'trial') {
            return false;
        }

        $trialEnds = $sub['trial_ends_at'] ?? ($sub['current_period_end'] ?? null);
        if (!$trialEnds) {
            return false;
        }

        try {
            $end = new DateTime($trialEnds);
            $now = new DateTime();
            $graceEnd = (clone $end)->modify('+24 hours');
            return $now > $end && $now <= $graceEnd;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Suspend tenant and mark subscription expired.
     */
    public function suspendExpiredTenant(int $tenantId): void
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE `{$this->tenantTable}` SET status = 'suspended', is_suspended = 1 WHERE id = ?");
            $stmt->execute([$tenantId]);
        } catch (Exception $e) {
            error_log('SubscriptionManager::suspendTenant error: ' . $e->getMessage());
        }

        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM `{$this->subscriptionTable}`")->fetchAll(PDO::FETCH_COLUMN);
            $hasStatus = in_array('status', $cols);
            $hasCancelledAt = in_array('cancelled_at', $cols);

            if ($hasStatus && $hasCancelledAt) {
                $stmt = $this->pdo->prepare("UPDATE `{$this->subscriptionTable}` SET status = 'expired', cancelled_at = NOW() WHERE tenant_id = ? AND status = 'trialing'");
                $stmt->execute([$tenantId]);
            } elseif ($hasStatus) {
                $stmt = $this->pdo->prepare("UPDATE `{$this->subscriptionTable}` SET status = 'expired' WHERE tenant_id = ? AND status = 'trialing'");
                $stmt->execute([$tenantId]);
            }
        } catch (Exception $e) {
            error_log('SubscriptionManager::expireSubscription error: ' . $e->getMessage());
        }
    }

    /**
     * Run batch expiration for all expired trials.
     */
    public function expireAllExpiredTrials(): int
    {
        $count = 0;
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM `{$this->subscriptionTable}`")->fetchAll(PDO::FETCH_COLUMN);
            $hasTrialEnds = in_array('trial_ends_at', $cols);
            $hasStatus = in_array('status', $cols);

            if ($hasTrialEnds && $hasStatus) {
                $stmt = $this->pdo->query("SELECT tenant_id FROM `{$this->subscriptionTable}` WHERE status = 'trialing' AND trial_ends_at < NOW()");
            } elseif ($hasStatus) {
                $stmt = $this->pdo->query("SELECT tenant_id FROM `{$this->subscriptionTable}` WHERE status = 'trialing' AND current_period_end < NOW()");
            } else {
                return 0;
            }

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tid) {
                $this->suspendExpiredTenant((int)$tid);
                $count++;
            }
        } catch (Exception $e) {
            error_log('SubscriptionManager::expireAllExpiredTrials error: ' . $e->getMessage());
        }
        return $count;
    }

    /**
     * Get tenant row.
     */
    public function getTenant(int $tenantId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `{$this->tenantTable}` WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get plan details.
     */
    public function getPlan(int $planId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `{$this->planTable}` WHERE id = ? LIMIT 1");
            $stmt->execute([$planId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Expose detected table names for debugging.
     */
    public function getTableNames(): array
    {
        return [
            'subscription' => $this->subscriptionTable,
            'plan' => $this->planTable,
            'tenant' => $this->tenantTable,
        ];
    }
}
