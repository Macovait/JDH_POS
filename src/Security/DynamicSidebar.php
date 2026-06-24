<?php
/**
 * Dynamic Sidebar Generator - Corrected URLs
 * Automatically generates sidebar items based on user permissions
 * All URLs verified against actual file structure
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class DynamicSidebar {
    private static ?DynamicSidebar $instance = null;
    private array $menuStructure;
    
    private function __construct() {
        $this->initializeMenuStructure();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the complete menu structure with verified URLs
     */
    private function initializeMenuStructure(): void {
        $this->menuStructure = [
            [
                'label' => 'Overview',
                'icon' => 'fa-chart-pie',
                'items' => [
                    ['label' => 'Dashboard', 'permission' => 'dashboard.view', 'url' => 'dashboard/home.php', 'icon' => 'fa-chart-pie'],
                ]
            ],
            [
                'label' => 'Sales',
                'icon' => 'fa-shopping-cart',
                'items' => [
                    ['label' => 'Point of Sale', 'permission' => 'pos.access', 'url' => 'pos/pos.php', 'icon' => 'fa-cash-register'],
                    ['label' => 'All Sales', 'permission' => 'sales.view', 'url' => 'pos/all_sales.php', 'icon' => 'fa-receipt'],
                    ['label' => 'Returns', 'permission' => 'sales.returns', 'url' => 'pos/returns', 'icon' => 'fa-undo-alt'],
                    ['label' => 'Return Sale', 'permission' => 'sales.returns', 'url' => 'pos/returns/return_sale.php', 'icon' => 'fa-exchange-alt'],
                    ['label' => 'Discounts', 'permission' => 'sales.discounts', 'url' => 'pos/discounts.php', 'icon' => 'fa-percent'],
                ]
            ],
            [
                'label' => 'Online Store',
                'icon' => 'fa-store',
                'items' => [
                    ['label' => 'Store Dashboard', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/index.php', 'icon' => 'fa-chart-line'],
                    ['label' => 'Store Orders', 'permission' => 'sales.view', 'url' => 'dashboard/shop/orders.php', 'icon' => 'fa-shopping-bag'],
                    ['label' => 'Customize Store', 'permission' => 'system.settings', 'url' => 'dashboard/shop/customize.php', 'icon' => 'fa-paint-brush'],
                    ['label' => 'Store Settings', 'permission' => 'system.settings', 'url' => 'dashboard/shop/settings.php', 'icon' => 'fa-sliders-h'],
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fa-warehouse',
                'items' => [
                    ['label' => 'Products', 'permission' => 'products.view', 'url' => 'products/products.php', 'icon' => 'fa-boxes-stacked'],
                    ['label' => 'Add Product', 'permission' => 'products.create', 'url' => 'products/product_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Edit Product', 'permission' => 'products.edit', 'url' => 'products/product_edit.php', 'icon' => 'fa-edit'],
                    ['label' => 'Product View', 'permission' => 'products.view', 'url' => 'products/product_view.php', 'icon' => 'fa-eye'],
                    ['label' => 'Brands', 'permission' => 'brands.view', 'url' => 'products/brands.php', 'icon' => 'fa-tags'],
                    ['label' => 'Add Brand', 'permission' => 'brands.create', 'url' => 'products/brand_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Product Attributes', 'permission' => 'products.manage', 'url' => 'products/attributes.php', 'icon' => 'fa-cogs'],
                    ['label' => 'Product Tags', 'permission' => 'products.manage', 'url' => 'products/product-tags.php', 'icon' => 'fa-tag'],
                    ['label' => 'Barcode Labels', 'permission' => 'products.view', 'url' => 'products/barcode_labels.php', 'icon' => 'fa-print'],
                    ['label' => 'Import Products', 'permission' => 'products.create', 'url' => 'products/import_products.php', 'icon' => 'fa-upload'],
                    ['label' => 'Export Products', 'permission' => 'products.view', 'url' => 'products/export_products.php', 'icon' => 'fa-download'],
                ]
            ],
            [
                'label' => 'People',
                'icon' => 'fa-users',
                'items' => [
                    ['label' => 'All Customers', 'permission' => 'customers.view', 'url' => 'customers/customers.php', 'icon' => 'fa-users'],
                    ['label' => 'Add Customer', 'permission' => 'customers.create', 'url' => 'customers/add_customer.php', 'icon' => 'fa-user-plus'],
                    ['label' => 'Customer Form', 'permission' => 'customers.create', 'url' => 'customers/customer_form.php', 'icon' => 'fa-user-edit'],
                    ['label' => 'Bulk SMS', 'permission' => 'customers.bulk_sms', 'url' => 'customers/bulk_sms.php', 'icon' => 'fa-sms'],
                    ['label' => 'Loyalty Program', 'permission' => 'loyalty.view', 'url' => 'customers/loyalty.php', 'icon' => 'fa-star'],
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fa-chart-line',
                'items' => [
                    ['label' => 'Reports', 'permission' => 'reports.view', 'url' => 'reports/reports.php', 'icon' => 'fa-chart-line'],
                    ['label' => 'Activity Logs', 'permission' => 'system.logs', 'url' => 'dashboard/activity_logs.php', 'icon' => 'fa-clock-rotate-left'],
                ]
            ],
            [
                'label' => 'Billing',
                'icon' => 'fa-credit-card',
                'items' => [
                    ['label' => 'Subscription', 'permission' => 'billing.view', 'url' => 'dashboard/billing.php', 'icon' => 'fa-credit-card'],
                ]
            ],
            [
                'label' => 'System',
                'icon' => 'fa-cogs',
                'items' => [
                    ['label' => 'Users', 'permission' => 'users.view', 'url' => 'users/users.php', 'icon' => 'fa-user-gear'],
                    ['label' => 'Add User', 'permission' => 'users.create', 'url' => 'users/user_form.php', 'icon' => 'fa-user-plus'],
                    ['label' => 'Roles & Permissions', 'permission' => 'roles.view', 'url' => 'users/roles/roles.php', 'icon' => 'fa-user-shield'],
                    ['label' => 'System Settings', 'permission' => 'system.settings', 'url' => 'dashboard/settings.php', 'icon' => 'fa-gear'],
                    ['label' => 'Security Dashboard', 'permission' => 'system.logs', 'url' => 'admin/security_dashboard.php', 'icon' => 'fa-shield-alt'],
                    ['label' => 'Profile', 'permission' => 'profile.view', 'url' => 'dashboard/profile.php', 'icon' => 'fa-user-circle'],
                ]
            ]
        ];
    }
    
    /**
     * Generate filtered sidebar based on user permissions
     */
    public function generateSidebar(): array {
        $filteredSections = [];
        
        foreach ($this->menuStructure as $section) {
            $allowedItems = [];
            
            foreach ($section['items'] as $item) {
                $permission = $item['permission'] ?? null;
                
                // If no permission required or user has permission, include item
                if (!$permission || has_permission($permission)) {
                    // Convert relative URL to full URL using base_url()
                    $item['url'] = base_url($item['url']);
                    $allowedItems[] = $item;
                }
            }
            
            // Only include section if it has allowed items
            if (!empty($allowedItems)) {
                $filteredSections[] = [
                    'label' => $section['label'],
                    'icon' => $section['icon'],
                    'items' => $allowedItems
                ];
            }
        }
        
        return $filteredSections;
    }
    
    /**
     * Get menu statistics
     */
    public function getMenuStats(): array {
        $sections = $this->generateSidebar();
        $totalItems = 0;
        $allowedItems = 0;
        
        foreach ($this->menuStructure as $section) {
            $totalItems += count($section['items']);
        }
        
        foreach ($sections as $section) {
            $allowedItems += count($section['items']);
        }
        
        return [
            'total_sections' => count($this->menuStructure),
            'allowed_sections' => count($sections),
            'total_items' => $totalItems,
            'allowed_items' => $allowedItems,
            'blocked_items' => $totalItems - $allowedItems
        ];
    }
}
?>
