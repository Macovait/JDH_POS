<?php
/**
 * Tenant Context Service
 * Manages tenant isolation and automatically injects tenant_id into database queries
 * Ensures complete data separation between tenants
 */

declare(strict_types=1);

namespace JDH_POS\Services;

use PDO;
use PDOException;
use Exception;

class TenantContextService
{
    private static ?int $currentTenantId = null;
    private static array $tenantConfig = [];
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Set the current tenant context
     */
    public static function setTenant(int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
        self::$tenantConfig = []; // Reset config cache
    }

    /**
     * Get the current tenant ID
     */
    public static function getTenantId(): ?int
    {
        return self::$currentTenantId;
    }

    /**
     * Clear tenant context
     */
    public static function clearTenant(): void
    {
        self::$currentTenantId = null;
        self::$tenantConfig = [];
    }

    /**
     * Check if tenant context is set
     */
    public static function hasTenant(): bool
    {
        return self::$currentTenantId !== null;
    }

    /**
     * Initialize tenant context from user session or request
     */
    public function initializeFromUser(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT tenant_id FROM users WHERE id = ? AND status = 1 LIMIT 1");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['tenant_id']) {
                self::setTenant((int)$result['tenant_id']);
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("TenantContextService::initializeFromUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize tenant context from subdomain
     */
    public function initializeFromSubdomain(string $subdomain): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM pos_tenants WHERE subdomain = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$subdomain]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                self::setTenant((int)$result['id']);
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("TenantContextService::initializeFromSubdomain error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tenant configuration
     */
    public function getTenantConfig(): array
    {
        if (!self::hasTenant()) {
            return [];
        }

        if (empty(self::$tenantConfig)) {
            try {
                $stmt = $this->db->prepare("SELECT settings, branding FROM pos_tenants WHERE id = ?");
                $stmt->execute([self::$currentTenantId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    self::$tenantConfig = [
                        'settings' => json_decode($result['settings'] ?: '{}', true) ?: [],
                        'branding' => json_decode($result['branding'] ?: '{}', true) ?: []
                    ];
                }
            } catch (PDOException $e) {
                error_log("TenantContextService::getTenantConfig error: " . $e->getMessage());
            }
        }

        return self::$tenantConfig;
    }

    /**
     * Modify SQL query to include tenant filter
     */
    public static function addTenantFilter(string $sql, array &$params, string $tableAlias = ''): string
    {
        if (!self::hasTenant()) {
            return $sql;
        }

        // Tables that require tenant isolation
        $tenantTables = [
            'users', 'branches', 'categories', 'products', 'product_variants',
            'product_images', 'inventory', 'customers', 'suppliers',
            'purchase_orders', 'purchase_order_items', 'sales', 'sale_items',
            'payments', 'discounts', 'vouchers', 'activity_logs', 'notifications',
            'expenses'
        ];

        // Check if query contains tenant tables
        $hasTenantTable = false;
        foreach ($tenantTables as $table) {
            if (strpos($sql, $table) !== false) {
                $hasTenantTable = true;
                break;
            }
        }

        if (!$hasTenantTable) {
            return $sql;
        }

        // Add tenant filter to WHERE clause
        $alias = $tableAlias ? $tableAlias . '.' : '';
        $tenantFilter = " {$alias}tenant_id = ?";

        // Insert tenant filter into SQL
        if (strpos($sql, 'WHERE') !== false) {
            $sql = preg_replace('/WHERE\s+/i', 'WHERE ' . $tenantFilter . ' AND ', $sql, 1);
        } else {
            // If no WHERE clause, add one
            $sql .= ' WHERE ' . $tenantFilter;
        }

        // Add tenant_id to params
        array_unshift($params, self::$currentTenantId);

        return $sql;
    }

    /**
     * Validate that user belongs to current tenant
     */
    public function validateUserAccess(int $userId): bool
    {
        if (!self::hasTenant()) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ? AND status = 1 LIMIT 1");
            $stmt->execute([$userId, self::$currentTenantId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("TenantContextService::validateUserAccess error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tenant limits and usage
     */
    public function getTenantLimits(): array
    {
        if (!self::hasTenant()) {
            return [];
        }

        try {
            // Get plan limits
            $stmt = $this->db->prepare("
                SELECT
                    p.max_users, p.max_branches, p.max_products, p.max_storage_mb,
                    p.max_api_calls
                FROM pos_tenants t
                JOIN pos_subscriptions s ON t.id = s.tenant_id AND s.status IN ('active', 'trialing')
                JOIN pos_plans p ON s.plan_id = p.id
                WHERE t.id = ?
                LIMIT 1
            ");
            $stmt->execute([self::$currentTenantId]);
            $planLimits = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Get current usage
            $usage = $this->db->prepare("
                SELECT
                    (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 1) as users_used,
                    (SELECT COUNT(*) FROM branches WHERE tenant_id = ?) as branches_used,
                    (SELECT COUNT(*) FROM products WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL) as products_used,
                    (SELECT COALESCE(SUM(file_size), 0) FROM uploads WHERE tenant_id = ?) as storage_used_mb
            ");
            $usage->execute([
                self::$currentTenantId,
                self::$currentTenantId,
                self::$currentTenantId,
                self::$currentTenantId
            ]);
            $currentUsage = $usage->fetch(PDO::FETCH_ASSOC) ?: [];

            return array_merge($planLimits, [
                'users_current' => (int)($currentUsage['users_used'] ?? 0),
                'branches_current' => (int)($currentUsage['branches_used'] ?? 0),
                'products_current' => (int)($currentUsage['products_used'] ?? 0),
                'storage_current_mb' => (float)($currentUsage['storage_used_mb'] ?? 0) / (1024 * 1024)
            ]);

        } catch (PDOException $e) {
            error_log("TenantContextService::getTenantLimits error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if tenant has exceeded limits
     */
    public function checkLimits(): array
    {
        $limits = $this->getTenantLimits();
        $violations = [];

        if (isset($limits['max_users']) && $limits['users_current'] >= $limits['max_users']) {
            $violations[] = 'user_limit_exceeded';
        }

        if (isset($limits['max_branches']) && $limits['branches_current'] >= $limits['max_branches']) {
            $violations[] = 'branch_limit_exceeded';
        }

        if (isset($limits['max_products']) && $limits['products_current'] >= $limits['max_products']) {
            $violations[] = 'product_limit_exceeded';
        }

        if (isset($limits['max_storage_mb']) && $limits['storage_current_mb'] >= $limits['max_storage_mb']) {
            $violations[] = 'storage_limit_exceeded';
        }

        return $violations;
    }
}