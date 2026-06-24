<?php
/**
 * UI Configuration Engine
 * Renders configuration-driven user interfaces
 */

declare(strict_types=1);

namespace JDH_POS\Services;

use PDO;
use PDOException;

class UiConfigService
{
    private PDO $db;
    private TenantContextService $tenantContext;
    private ModuleService $moduleService;

    public function __construct(PDO $db, TenantContextService $tenantContext, ModuleService $moduleService)
    {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
        $this->moduleService = $moduleService;
    }

    /**
     * Get dashboard configuration for tenant
     */
    public function getDashboardConfig(): array
    {
        $config = [
            'widgets' => [],
            'layout' => 'default',
            'theme' => 'default'
        ];

        if (!$this->tenantContext->hasTenant()) {
            return $config;
        }

        // Get tenant-specific dashboard config
        $tenantConfig = $this->getTenantUiConfig('dashboard');

        if (!empty($tenantConfig)) {
            $config = array_merge($config, $tenantConfig);
        }

        // Add default widgets based on enabled modules
        $enabledModules = $this->moduleService->getEnabledModules();
        $config['widgets'] = $this->getDefaultWidgets($enabledModules);

        return $config;
    }

    /**
     * Get POS interface configuration
     */
    public function getPosConfig(): array
    {
        $config = [
            'layout' => 'standard',
            'features' => [],
            'shortcuts' => [],
            'payment_methods' => []
        ];

        if (!$this->tenantContext->hasTenant()) {
            return $config;
        }

        // Get tenant-specific POS config
        $tenantConfig = $this->getTenantUiConfig('pos');

        if (!empty($tenantConfig)) {
            $config = array_merge($config, $tenantConfig);
        }

        // Configure based on enabled modules
        $enabledModules = $this->moduleService->getEnabledModules();

        if (isset($enabledModules['inventory'])) {
            $config['features'][] = 'inventory_tracking';
        }

        if (isset($enabledModules['customers'])) {
            $config['features'][] = 'customer_management';
        }

        if (isset($enabledModules['loyalty'])) {
            $config['features'][] = 'loyalty_program';
        }

        // Get available payment methods
        $config['payment_methods'] = $this->getPaymentMethodsConfig();

        return $config;
    }

    /**
     * Get form configuration for specific entity
     */
    public function getFormConfig(string $entity): array
    {
        $defaultConfigs = [
            'product' => [
                'fields' => [
                    'name' => ['type' => 'text', 'required' => true, 'label' => 'Product Name'],
                    'sku' => ['type' => 'text', 'required' => false, 'label' => 'SKU'],
                    'price' => ['type' => 'number', 'required' => true, 'label' => 'Price'],
                    'cost_price' => ['type' => 'number', 'required' => false, 'label' => 'Cost Price'],
                    'category_id' => ['type' => 'select', 'required' => false, 'label' => 'Category'],
                    'description' => ['type' => 'textarea', 'required' => false, 'label' => 'Description']
                ],
                'layout' => 'single_column'
            ],
            'customer' => [
                'fields' => [
                    'name' => ['type' => 'text', 'required' => true, 'label' => 'Customer Name'],
                    'email' => ['type' => 'email', 'required' => false, 'label' => 'Email'],
                    'phone' => ['type' => 'tel', 'required' => false, 'label' => 'Phone'],
                    'address' => ['type' => 'textarea', 'required' => false, 'label' => 'Address']
                ],
                'layout' => 'single_column'
            ],
            'sale' => [
                'fields' => [
                    'customer_id' => ['type' => 'select', 'required' => false, 'label' => 'Customer'],
                    'items' => ['type' => 'product_selector', 'required' => true, 'label' => 'Products'],
                    'discount' => ['type' => 'number', 'required' => false, 'label' => 'Discount'],
                    'payment_method' => ['type' => 'select', 'required' => true, 'label' => 'Payment Method']
                ],
                'layout' => 'pos_style'
            ]
        ];

        $config = $defaultConfigs[$entity] ?? ['fields' => [], 'layout' => 'single_column'];

        // Override with tenant-specific configuration
        if ($this->tenantContext->hasTenant()) {
            $tenantConfig = $this->getTenantUiConfig("form.{$entity}");
            if (!empty($tenantConfig)) {
                $config = array_merge($config, $tenantConfig);
            }
        }

        return $config;
    }

    /**
     * Get report configuration
     */
    public function getReportConfig(string $reportType): array
    {
        $defaultConfigs = [
            'sales' => [
                'columns' => ['date', 'invoice', 'customer', 'total', 'payment_method', 'status'],
                'filters' => ['date_range', 'customer', 'status', 'payment_method'],
                'group_by' => ['day', 'month', 'customer'],
                'charts' => ['sales_trend', 'payment_methods', 'top_products']
            ],
            'inventory' => [
                'columns' => ['product', 'sku', 'category', 'stock', 'value', 'last_updated'],
                'filters' => ['category', 'stock_level', 'branch'],
                'alerts' => ['low_stock', 'out_of_stock', 'overstock']
            ],
            'customers' => [
                'columns' => ['name', 'email', 'phone', 'total_purchases', 'last_purchase', 'loyalty_points'],
                'filters' => ['registration_date', 'purchase_amount', 'activity'],
                'segments' => ['new', 'active', 'inactive', 'vip']
            ]
        ];

        $config = $defaultConfigs[$reportType] ?? ['columns' => [], 'filters' => []];

        // Override with tenant-specific configuration
        if ($this->tenantContext->hasTenant()) {
            $tenantConfig = $this->getTenantUiConfig("report.{$reportType}");
            if (!empty($tenantConfig)) {
                $config = array_merge($config, $tenantConfig);
            }
        }

        return $config;
    }

    /**
     * Get tenant UI configuration
     */
    private function getTenantUiConfig(string $configKey): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT config_value
                FROM tenant_configs
                WHERE tenant_id = ? AND config_key = ?
                LIMIT 1
            ");
            $stmt->execute([$this->tenantContext->getTenantId(), "ui.{$configKey}"]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['config_value']) {
                return json_decode($result['config_value'], true) ?: [];
            }

        } catch (PDOException $e) {
            error_log("UiConfigService::getTenantUiConfig error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Set tenant UI configuration
     */
    public function setTenantUiConfig(string $configKey, array $config): bool
    {
        if (!$this->tenantContext->hasTenant()) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO tenant_configs (tenant_id, config_key, config_value, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    config_value = VALUES(config_value),
                    updated_at = NOW()
            ");
            $stmt->execute([
                $this->tenantContext->getTenantId(),
                "ui.{$configKey}",
                json_encode($config)
            ]);

            return true;

        } catch (PDOException $e) {
            error_log("UiConfigService::setTenantUiConfig error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get default dashboard widgets based on enabled modules
     */
    private function getDefaultWidgets(array $enabledModules): array
    {
        $widgets = [
            [
                'type' => 'stats',
                'title' => 'Sales Today',
                'size' => 'small',
                'module' => 'pos'
            ]
        ];

        if (isset($enabledModules['inventory'])) {
            $widgets[] = [
                'type' => 'stats',
                'title' => 'Low Stock Items',
                'size' => 'small',
                'module' => 'inventory'
            ];
        }

        if (isset($enabledModules['customers'])) {
            $widgets[] = [
                'type' => 'stats',
                'title' => 'New Customers',
                'size' => 'small',
                'module' => 'customers'
            ];
        }

        if (isset($enabledModules['reports'])) {
            $widgets[] = [
                'type' => 'chart',
                'title' => 'Sales Trend',
                'size' => 'large',
                'module' => 'reports'
            ];
        }

        if (isset($enabledModules['pos'])) {
            $widgets[] = [
                'type' => 'list',
                'title' => 'Recent Sales',
                'size' => 'medium',
                'module' => 'pos'
            ];
        }

        return $widgets;
    }

    /**
     * Get payment methods configuration
     */
    private function getPaymentMethodsConfig(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT type, is_default
                FROM pos_payment_methods
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY is_default DESC, type ASC
            ");
            $stmt->execute([$this->tenantContext->getTenantId()]);

            $methods = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $methods[] = [
                    'type' => $row['type'],
                    'default' => (bool)$row['is_default'],
                    'label' => $this->getPaymentMethodLabel($row['type'])
                ];
            }

            return $methods;

        } catch (PDOException $e) {
            error_log("UiConfigService::getPaymentMethodsConfig error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment method display label
     */
    private function getPaymentMethodLabel(string $type): string
    {
        $labels = [
            'cash' => 'Cash',
            'card' => 'Credit/Debit Card',
            'mpesa' => 'M-Pesa',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque'
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Render HTML for a widget
     */
    public function renderWidget(array $widget): string
    {
        $type = $widget['type'] ?? 'stats';
        $title = $widget['title'] ?? '';
        $size = $widget['size'] ?? 'medium';

        $sizeClasses = [
            'small' => 'col-span-1',
            'medium' => 'col-span-2',
            'large' => 'col-span-3'
        ];

        $class = $sizeClasses[$size] ?? 'col-span-2';

        $html = "<div class='{$class} card-laravel p-4'>";

        if ($title) {
            $html .= "<h3 class='text-sm font-semibold text-white mb-3'>{$title}</h3>";
        }

        $html .= $this->renderWidgetContent($widget);
        $html .= "</div>";

        return $html;
    }

    /**
     * Render widget content based on type
     */
    private function renderWidgetContent(array $widget): string
    {
        $type = $widget['type'] ?? 'stats';

        switch ($type) {
            case 'stats':
                return $this->renderStatsWidget($widget);

            case 'chart':
                return $this->renderChartWidget($widget);

            case 'list':
                return $this->renderListWidget($widget);

            default:
                return '<p class="text-gray-400">Widget content not available</p>';
        }
    }

    /**
     * Render stats widget
     */
    private function renderStatsWidget(array $widget): string
    {
        $value = $widget['value'] ?? '0';
        $change = $widget['change'] ?? null;
        $changeType = $widget['change_type'] ?? 'positive';

        $html = "<div class='text-center'>";
        $html .= "<div class='text-2xl font-bold text-white mb-2'>{$value}</div>";

        if ($change !== null) {
            $colorClass = $changeType === 'positive' ? 'text-emerald-400' : 'text-red-400';
            $icon = $changeType === 'positive' ? 'fa-arrow-up' : 'fa-arrow-down';
            $html .= "<div class='{$colorClass} text-xs flex items-center justify-center gap-1'>";
            $html .= "<i class='fas {$icon}'></i> {$change}%";
            $html .= "</div>";
        }

        $html .= "</div>";
        return $html;
    }

    /**
     * Render chart widget
     */
    private function renderChartWidget(array $widget): string
    {
        $chartId = 'chart_' . uniqid();
        return "<div class='h-32'><canvas id='{$chartId}'></canvas></div>";
    }

    /**
     * Render list widget
     */
    private function renderListWidget(array $widget): string
    {
        $items = $widget['items'] ?? [];

        if (empty($items)) {
            return '<p class="text-gray-400 text-sm">No items to display</p>';
        }

        $html = "<div class='space-y-2'>";
        foreach (array_slice($items, 0, 5) as $item) {
            $html .= "<div class='flex justify-between items-center text-sm'>";
            $html .= "<span class='text-gray-300'>{$item['label']}</span>";
            $html .= "<span class='text-white font-medium'>{$item['value']}</span>";
            $html .= "</div>";
        }
        $html .= "</div>";

        return $html;
    }
}