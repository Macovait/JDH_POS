<?php
/**
 * Module Activation Engine
 * Manages feature toggling and module activation per tenant
 */

declare(strict_types=1);

namespace JDH_POS\Services;

use PDO;
use PDOException;

class ModuleService
{
    private PDO $db;
    private TenantContextService $tenantContext;
    private array $modules;

    public function __construct(PDO $db, TenantContextService $tenantContext)
    {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
        $this->initializeModules();
    }

    /**
     * Initialize available modules
     */
    private function initializeModules(): void
    {
        $this->modules = [
            'pos' => [
                'name' => 'Point of Sale',
                'description' => 'Process sales and transactions',
                'required' => true, // Core module, always enabled
                'dependencies' => [],
                'features' => ['pos_access', 'sales_history', 'sales_returns']
            ],
            'inventory' => [
                'name' => 'Inventory Management',
                'description' => 'Track products and stock levels',
                'required' => false,
                'dependencies' => ['pos'],
                'features' => ['product_management', 'inventory_tracking', 'inventory_alerts']
            ],
            'customers' => [
                'name' => 'Customer Management',
                'description' => 'Manage customer data and loyalty',
                'required' => false,
                'dependencies' => [],
                'features' => ['customer_management', 'customer_loyalty']
            ],
            'reports' => [
                'name' => 'Advanced Reports',
                'description' => 'Detailed analytics and insights',
                'required' => false,
                'dependencies' => ['pos'],
                'features' => ['reports_basic', 'reports_advanced']
            ],
            'purchases' => [
                'name' => 'Purchase Orders',
                'description' => 'Manage supplier orders and receiving',
                'required' => false,
                'dependencies' => ['inventory'],
                'features' => ['purchase_orders', 'purchase_receiving']
            ],
            'multi_branch' => [
                'name' => 'Multi-Branch',
                'description' => 'Manage multiple locations',
                'required' => false,
                'dependencies' => ['pos'],
                'features' => ['multi_branch']
            ],
            'api' => [
                'name' => 'API Access',
                'description' => 'Integrate with external systems',
                'required' => false,
                'dependencies' => [],
                'features' => ['api_access', 'webhooks']
            ],
            'loyalty' => [
                'name' => 'Loyalty Program',
                'description' => 'Customer rewards and points',
                'required' => false,
                'dependencies' => ['customers'],
                'features' => ['loyalty_points', 'loyalty_redemptions']
            ]
        ];
    }

    /**
     * Get all available modules
     */
    public function getAllModules(): array
    {
        return $this->modules;
    }

    /**
     * Check if module is enabled for current tenant
     */
    public function isModuleEnabled(string $moduleKey): bool
    {
        if (!$this->tenantContext->hasTenant()) {
            return false;
        }

        // Required modules are always enabled
        if (isset($this->modules[$moduleKey]['required']) && $this->modules[$moduleKey]['required']) {
            return true;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM pos_subscriptions ts
                JOIN pos_plan_features pf ON ts.plan_id = pf.plan_id
                JOIN pos_features f ON pf.feature_id = f.id
                WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trialing')
                  AND f.feature_key IN (
                      SELECT feature_key FROM pos_features WHERE module_name = ?
                  )
                  AND pf.is_enabled = 1
                LIMIT 1
            ");
            $stmt->execute([$this->tenantContext->getTenantId(), $moduleKey]);
            $count = $stmt->fetchColumn();

            return $count > 0;

        } catch (PDOException $e) {
            error_log("ModuleService::isModuleEnabled error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get enabled modules for current tenant
     */
    public function getEnabledModules(): array
    {
        if (!$this->tenantContext->hasTenant()) {
            return [];
        }

        $enabled = [];

        foreach ($this->modules as $key => $module) {
            if ($this->isModuleEnabled($key)) {
                $enabled[$key] = $module;
            }
        }

        return $enabled;
    }

    /**
     * Check module dependencies
     */
    public function checkDependencies(string $moduleKey): array
    {
        if (!isset($this->modules[$moduleKey])) {
            return ['valid' => false, 'missing' => []];
        }

        $module = $this->modules[$moduleKey];
        $missing = [];

        foreach ($module['dependencies'] as $dependency) {
            if (!$this->isModuleEnabled($dependency)) {
                $missing[] = $dependency;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Get module configuration for tenant
     */
    public function getModuleConfig(string $moduleKey): array
    {
        if (!$this->tenantContext->hasTenant()) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT tc.config_key, tc.config_value
                FROM tenant_configs tc
                WHERE tc.tenant_id = ? AND tc.config_key LIKE ?
            ");
            $stmt->execute([
                $this->tenantContext->getTenantId(),
                "module.{$moduleKey}.%"
            ]);

            $config = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = str_replace("module.{$moduleKey}.", '', $row['config_key']);
                $value = json_decode($row['config_value'], true) ?? $row['config_value'];
                $config[$key] = $value;
            }

            return $config;

        } catch (PDOException $e) {
            error_log("ModuleService::getModuleConfig error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set module configuration for tenant
     */
    public function setModuleConfig(string $moduleKey, array $config): bool
    {
        if (!$this->tenantContext->hasTenant()) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            foreach ($config as $key => $value) {
                $configKey = "module.{$moduleKey}.{$key}";
                $configValue = is_array($value) ? json_encode($value) : $value;

                $stmt = $this->db->prepare("
                    INSERT INTO tenant_configs (tenant_id, config_key, config_value, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        config_value = VALUES(config_value),
                        updated_at = NOW()
                ");
                $stmt->execute([
                    $this->tenantContext->getTenantId(),
                    $configKey,
                    $configValue
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ModuleService::setModuleConfig error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get navigation menu based on enabled modules
     */
    public function getNavigationMenu(): array
    {
        $enabledModules = $this->getEnabledModules();
        $menu = [];

        // Core navigation items
        $menu[] = [
            'label' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => '/admin/dashboard.php',
            'active' => true
        ];

        // POS - always available if pos module is enabled
        if (isset($enabledModules['pos'])) {
            $menu[] = [
                'label' => 'POS',
                'icon' => 'fas fa-cash-register',
                'url' => '/pos/',
                'badge' => null
            ];
        }

        // Products
        if (isset($enabledModules['inventory'])) {
            $menu[] = [
                'label' => 'Products',
                'icon' => 'fas fa-box',
                'url' => '/admin/products.php',
                'submenu' => [
                    ['label' => 'All Products', 'url' => '/admin/products.php'],
                    ['label' => 'Categories', 'url' => '/admin/categories.php'],
                    ['label' => 'Inventory', 'url' => '/admin/inventory.php']
                ]
            ];
        }

        // Sales
        if (isset($enabledModules['pos'])) {
            $menu[] = [
                'label' => 'Sales',
                'icon' => 'fas fa-shopping-cart',
                'url' => '/admin/sales.php'
            ];
        }

        // Customers
        if (isset($enabledModules['customers'])) {
            $menu[] = [
                'label' => 'Customers',
                'icon' => 'fas fa-users',
                'url' => '/admin/customers.php'
            ];
        }

        // Purchases
        if (isset($enabledModules['purchases'])) {
            $menu[] = [
                'label' => 'Purchases',
                'icon' => 'fas fa-truck',
                'url' => '/admin/purchases.php'
            ];
        }

        // Reports
        if (isset($enabledModules['reports'])) {
            $menu[] = [
                'label' => 'Reports',
                'icon' => 'fas fa-chart-bar',
                'url' => '/admin/reports.php'
            ];
        }

        // Multi-branch
        if (isset($enabledModules['multi_branch'])) {
            $menu[] = [
                'label' => 'Branches',
                'icon' => 'fas fa-store',
                'url' => '/admin/branches.php'
            ];
        }

        // Settings (always available for admins)
        $menu[] = [
            'label' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => '/admin/settings.php',
            'submenu' => [
                ['label' => 'General', 'url' => '/admin/settings.php'],
                ['label' => 'Users', 'url' => '/admin/users.php'],
                ['label' => 'API', 'url' => '/admin/api-settings.php']
            ]
        ];

        return $menu;
    }

    /**
     * Get available features for current tenant
     */
    public function getAvailableFeatures(): array
    {
        if (!$this->tenantContext->hasTenant()) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT f.feature_key, f.name, f.description, pf.is_enabled
                FROM pos_subscriptions ts
                JOIN pos_plan_features pf ON ts.plan_id = pf.plan_id
                JOIN pos_features f ON pf.feature_id = f.id
                WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trialing')
                ORDER BY f.module_name, f.name
            ");
            $stmt->execute([$this->tenantContext->getTenantId()]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("ModuleService::getAvailableFeatures error: " . $e->getMessage());
            return [];
        }
    }
}