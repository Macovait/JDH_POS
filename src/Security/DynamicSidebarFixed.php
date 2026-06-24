<?php
/**
 * Dynamic Sidebar Generator
 * Automatically generates sidebar items based on user permissions
 * Works for any role without manual configuration
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
     * Initialize the complete menu structure with permissions
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
                    ['label' => 'Returns', 'permission' => 'sales.returns', 'url' => 'pos/returns/select_sale_for_return.php', 'icon' => 'fa-undo-alt'],
                    ['label' => 'Refunds', 'permission' => 'sales.refunds', 'url' => 'pos/refunds.php', 'icon' => 'fa-money-bill-wave'],
                    ['label' => 'Quotations', 'permission' => 'quotations.view', 'url' => 'quotations/list_quotation.php', 'icon' => 'fa-file-invoice'],
                    ['label' => 'Create Quotation', 'permission' => 'quotations.create', 'url' => 'quotations/create_quotation.php', 'icon' => 'fa-plus'],
                    ['label' => 'Discounts', 'permission' => 'sales.discounts', 'url' => 'pos/discounts.php', 'icon' => 'fa-percent'],
                    ['label' => 'Vouchers', 'permission' => 'sales.vouchers', 'url' => 'vouchers/vouchers.php', 'icon' => 'fa-ticket-alt'],
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fa-warehouse',
                'items' => [
                    ['label' => 'Products', 'permission' => 'products.view', 'url' => 'products/products.php', 'icon' => 'fa-boxes-stacked'],
                    ['label' => 'Add Product', 'permission' => 'products.create', 'url' => 'products/product_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Edit Products', 'permission' => 'products.edit', 'url' => 'products/edit_products.php', 'icon' => 'fa-edit'],
                    ['label' => 'Brands', 'permission' => 'brands.view', 'url' => 'products/brands.php', 'icon' => 'fa-tags'],
                    ['label' => 'Add Brand', 'permission' => 'brands.create', 'url' => 'products/brand_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Categories', 'permission' => 'categories.view', 'url' => 'categories/list_categories.php', 'icon' => 'fa-layer-group'],
                    ['label' => 'Add Category', 'permission' => 'categories.create', 'url' => 'categories/category_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Stock Management', 'permission' => 'inventory.manage', 'url' => 'inventory/inventory.php', 'icon' => 'fa-warehouse'],
                    ['label' => 'Stock Adjustment', 'permission' => 'inventory.adjust', 'url' => 'inventory/stock_adjustment.php', 'icon' => 'fa-sliders-h'],
                    ['label' => 'Stock Transfer', 'permission' => 'inventory.transfer', 'url' => 'inventory/stock_transfer.php', 'icon' => 'fa-exchange-alt'],
                    ['label' => 'Item Expiry', 'permission' => 'inventory.expiry', 'url' => 'inventory/item_expiry.php', 'icon' => 'fa-calendar-times'],
                    ['label' => 'Suppliers', 'permission' => 'suppliers.view', 'url' => 'inventory/suppliers.php', 'icon' => 'fa-handshake'],
                    ['label' => 'Add Supplier', 'permission' => 'suppliers.create', 'url' => 'inventory/supplier_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Shipments', 'permission' => 'shipments.view', 'url' => 'shipments/shipments.php', 'icon' => 'fa-truck-fast'],
                    ['label' => 'Receive Shipment', 'permission' => 'shipments.receive', 'url' => 'shipments/receive_shipment.php', 'icon' => 'fa-download'],
                    ['label' => 'Barcode Labels', 'permission' => 'products.view', 'url' => 'products/barcode_labels.php', 'icon' => 'fa-print'],
                    ['label' => 'Product Attributes', 'permission' => 'products.manage', 'url' => 'products/attributes.php', 'icon' => 'fa-tags'],
                    ['label' => 'Product Tags', 'permission' => 'products.manage', 'url' => 'products/product-tags.php', 'icon' => 'fa-tag'],
                ]
            ],
            [
                'label' => 'Procurement',
                'icon' => 'fa-shopping-cart',
                'items' => [
                    ['label' => 'Purchase Orders', 'permission' => 'purchases.view', 'url' => 'purchases/purchase_orders.php', 'icon' => 'fa-shopping-cart'],
                    ['label' => 'Create Purchase', 'permission' => 'purchases.create', 'url' => 'purchases/create_purchase.php', 'icon' => 'fa-plus'],
                    ['label' => 'Approve Purchases', 'permission' => 'purchases.approve', 'url' => 'purchases/approve_purchases.php', 'icon' => 'fa-check'],
                ]
            ],
            [
                'label' => 'People',
                'icon' => 'fa-users',
                'items' => [
                    ['label' => 'All Customers', 'permission' => 'customers.view', 'url' => 'customers/customers.php', 'icon' => 'fa-users'],
                    ['label' => 'Add Customer', 'permission' => 'customers.create', 'url' => 'customers/add_customer.php', 'icon' => 'fa-user-plus'],
                    ['label' => 'Edit Customers', 'permission' => 'customers.edit', 'url' => 'customers/edit_customers.php', 'icon' => 'fa-edit'],
                    ['label' => 'Bulk SMS', 'permission' => 'customers.bulk_sms', 'url' => 'customers/bulk_sms.php', 'icon' => 'fa-sms'],
                    ['label' => 'Loyalty Program', 'permission' => 'loyalty.view', 'url' => 'customers/loyalty.php', 'icon' => 'fa-star'],
                    ['label' => 'Manage Loyalty', 'permission' => 'loyalty.manage', 'url' => 'customers/manage_loyalty.php', 'icon' => 'fa-cog'],
                    ['label' => 'CRM Dashboard', 'permission' => 'crm.view', 'url' => 'crm/index.php', 'icon' => 'fa-handshake'],
                    ['label' => 'CRM Leads', 'permission' => 'crm.leads', 'url' => 'crm/leads.php', 'icon' => 'fa-user-friends'],
                    ['label' => 'CRM Deals', 'permission' => 'crm.deals', 'url' => 'crm/deals.php', 'icon' => 'fa-hand-holding-dollar'],
                    ['label' => 'HR Dashboard', 'permission' => 'hr.view', 'url' => 'hr/index.php', 'icon' => 'fa-users-cog'],
                    ['label' => 'Employees', 'permission' => 'hr.employees', 'url' => 'hr/employees.php', 'icon' => 'fa-user-tie'],
                    ['label' => 'Leave Requests', 'permission' => 'hr.leaves', 'url' => 'hr/leave_requests.php', 'icon' => 'fa-calendar-minus'],
                    ['label' => 'Payroll', 'permission' => 'hr.payroll', 'url' => 'hr/payroll.php', 'icon' => 'fa-money-check-alt'],
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fa-chart-line',
                'items' => [
                    ['label' => 'Reports Dashboard', 'permission' => 'reports.view', 'url' => 'reports/reports.php', 'icon' => 'fa-chart-line'],
                    ['label' => 'Sales Reports', 'permission' => 'reports.sales', 'url' => 'reports/sales_reports.php', 'icon' => 'fa-shopping-cart'],
                    ['label' => 'Inventory Reports', 'permission' => 'reports.inventory', 'url' => 'reports/inventory_reports.php', 'icon' => 'fa-warehouse'],
                    ['label' => 'Financial Reports', 'permission' => 'reports.financial', 'url' => 'reports/financial_reports.php', 'icon' => 'fa-dollar-sign'],
                    ['label' => 'Export Reports', 'permission' => 'reports.export', 'url' => 'reports/export_reports.php', 'icon' => 'fa-download'],
                    ['label' => 'Expenses', 'permission' => 'expenses.view', 'url' => 'expenses/expenses.php', 'icon' => 'fa-wallet'],
                    ['label' => 'Add Expense', 'permission' => 'expenses.create', 'url' => 'expenses/add_expense.php', 'icon' => 'fa-plus'],
                    ['label' => 'Approve Expenses', 'permission' => 'expenses.approve', 'url' => 'expenses/approve_expenses.php', 'icon' => 'fa-check'],
                ]
            ],
            [
                'label' => 'Billing',
                'icon' => 'fa-credit-card',
                'items' => [
                    ['label' => 'Subscription', 'permission' => 'billing.view', 'url' => 'dashboard/billing.php', 'icon' => 'fa-credit-card'],
                    ['label' => 'Manage Billing', 'permission' => 'billing.manage', 'url' => 'billing/manage_billing.php', 'icon' => 'fa-cog'],
                    ['label' => 'Payment Methods', 'permission' => 'billing.settings', 'url' => 'billing/payment_methods.php', 'icon' => 'fa-credit-card'],
                    ['label' => 'M-Pesa Settings', 'permission' => 'billing.settings', 'url' => 'billing/mpesa_settings.php', 'icon' => 'fa-mobile-alt'],
                    ['label' => 'SMS Settings', 'permission' => 'billing.settings', 'url' => 'billing/sms_settings.php', 'icon' => 'fa-paper-plane'],
                    ['label' => 'Payment History', 'permission' => 'billing.view', 'url' => 'billing/payment_history.php', 'icon' => 'fa-receipt'],
                ]
            ],
            [
                'label' => 'System',
                'icon' => 'fa-cogs',
                'items' => [
                    ['label' => 'Branches', 'permission' => 'branches.view', 'url' => 'branches/branches.php', 'icon' => 'fa-store'],
                    ['label' => 'Add Branch', 'permission' => 'branches.create', 'url' => 'branches/add_branch.php', 'icon' => 'fa-plus'],
                    ['label' => 'Manage Branches', 'permission' => 'branches.manage', 'url' => 'branches/manage_branches.php', 'icon' => 'fa-cog'],
                    ['label' => 'Users', 'permission' => 'users.view', 'url' => 'users/users.php', 'icon' => 'fa-user-gear'],
                    ['label' => 'Add User', 'permission' => 'users.create', 'url' => 'users/user_form.php', 'icon' => 'fa-user-plus'],
                    ['label' => 'Manage Users', 'permission' => 'users.manage', 'url' => 'users/manage_users.php', 'icon' => 'fa-users-cog'],
                    ['label' => 'Roles & Permissions', 'permission' => 'roles.view', 'url' => 'users/roles/roles.php', 'icon' => 'fa-user-shield'],
                    ['label' => 'Create Role', 'permission' => 'roles.create', 'url' => 'users/roles/create_role.php', 'icon' => 'fa-plus'],
                    ['label' => 'Assign Roles', 'permission' => 'roles.assign', 'url' => 'users/roles/assign_roles.php', 'icon' => 'fa-user-tag'],
                    ['label' => 'System Settings', 'permission' => 'system.settings', 'url' => 'dashboard/settings.php', 'icon' => 'fa-gear'],
                    ['label' => 'Backup & Restore', 'permission' => 'system.backup', 'url' => 'backup/backup.php', 'icon' => 'fa-database'],
                    ['label' => 'System Logs', 'permission' => 'system.logs', 'url' => 'dashboard/activity_logs.php', 'icon' => 'fa-clock-rotate-left'],
                    ['label' => 'System Maintenance', 'permission' => 'system.maintenance', 'url' => 'admin/maintenance.php', 'icon' => 'fa-tools'],
                    ['label' => 'Security Dashboard', 'permission' => 'system.logs', 'url' => 'admin/security_dashboard.php', 'icon' => 'fa-shield-alt'],
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
