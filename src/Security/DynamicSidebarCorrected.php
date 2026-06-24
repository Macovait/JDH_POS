<?php
/**
 * Dynamic Sidebar Generator - Corrected URLs
 * Automatically generates sidebar items based on user permissions
 * All URLs verified against actual file structure
 * 
 * @package Jakababa\Security
 * @version 2.0.0
 */

class DynamicSidebar {
    private static ?DynamicSidebar $instance = null;
    private array $menuStructure;
    private array $userPermissions = [];
    private bool $isSuperAdmin = false;
    
    private function __construct() {
        $this->initializeMenuStructure();
        $this->loadUserPermissions();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load user permissions from session/database
     */
    private function loadUserPermissions(): void {
        $this->isSuperAdmin = function_exists('is_super_admin') && is_super_admin();
        
        // Load permissions from session or database
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            $this->userPermissions = $_SESSION['permissions'];
        } else {
            // Load from database if not in session
            $this->userPermissions = $this->fetchUserPermissions();
            $_SESSION['permissions'] = $this->userPermissions;
        }
    }
    
    /**
     * Fetch user permissions from database
     */
    private function fetchUserPermissions(): array {
        $permissions = [];
        
        try {
            if (!function_exists('get_db_connection')) {
                return $permissions;
            }
            
            $pdo = get_db_connection();
            $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            
            if (!$user_id) {
                return $permissions;
            }
            
            $stmt = $pdo->prepare("
                SELECT p.name 
                FROM permissions p
                JOIN role_permissions rp ON rp.permission_id = p.id
                JOIN user_roles ur ON ur.role_id = rp.role_id
                WHERE ur.user_id = ? AND p.deleted_at IS NULL
            ");
            $stmt->execute([$user_id]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $permissions[] = $row['name'];
            }
            
            // Also check if user has specific permissions directly
            $stmt = $pdo->prepare("
                SELECT p.name 
                FROM permissions p
                JOIN user_permissions up ON up.permission_id = p.id
                WHERE up.user_id = ? AND p.deleted_at IS NULL
            ");
            $stmt->execute([$user_id]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!in_array($row['name'], $permissions)) {
                    $permissions[] = $row['name'];
                }
            }
        } catch (Exception $e) {
            error_log('DynamicSidebar: Failed to fetch permissions: ' . $e->getMessage());
        }
        
        return $permissions;
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
                    ['label' => 'Dashboard', 'permission' => 'dashboard.view', 'url' => 'dashboard/index.php', 'icon' => 'fa-chart-pie'],
                    ['label' => 'Analytics', 'permission' => 'dashboard.view', 'url' => 'dashboard/analytics.php', 'icon' => 'fa-chart-area'],
                    ['label' => 'Notifications', 'permission' => 'dashboard.view', 'url' => 'dashboard/notifications.php', 'icon' => 'fa-bell'],
                    ['label' => 'Activity Logs', 'permission' => 'system.logs', 'url' => 'dashboard/activity_logs.php', 'icon' => 'fa-clock-rotate-left'],
                    ['label' => 'Health Monitor', 'permission' => 'system.admin', 'url' => 'dashboard/health.php', 'icon' => 'fa-heartbeat'],
                ]
            ],
            [
                'label' => 'Sales',
                'icon' => 'fa-shopping-cart',
                'items' => [
                    ['label' => 'Point of Sale', 'permission' => 'pos.access', 'url' => 'pos/pos.php', 'icon' => 'fa-cash-register'],
                    ['label' => 'Sales Management', 'permission' => 'sales.view', 'url' => 'pos/sales.php', 'icon' => 'fa-receipt'],
                    ['label' => 'All Sales', 'permission' => 'sales.view', 'url' => 'pos/all_sales.php', 'icon' => 'fa-list'],
                    ['label' => 'Returns', 'permission' => 'sales.returns', 'url' => 'pos/returns/list_sell_return.php', 'icon' => 'fa-undo-alt'],
                    ['label' => 'Discounts', 'permission' => 'sales.discounts', 'url' => 'pos/discounts.php', 'icon' => 'fa-percent'],
                    ['label' => 'Quotations', 'permission' => 'quotations.view', 'url' => 'quotations/list_quotation.php', 'icon' => 'fa-file-invoice'],
                    ['label' => 'Vouchers', 'permission' => 'vouchers.view', 'url' => 'vouchers/vouchers.php', 'icon' => 'fa-ticket-alt'],
                ]
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fa-warehouse',
                'items' => [
                    ['label' => 'Products', 'permission' => 'products.view', 'url' => 'products/products.php', 'icon' => 'fa-boxes-stacked'],
                    ['label' => 'Add Product', 'permission' => 'products.create', 'url' => 'products/product_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Categories', 'permission' => 'categories.view', 'url' => 'categories/list_categories.php', 'icon' => 'fa-folder-tree'],
                    ['label' => 'Brands', 'permission' => 'brands.view', 'url' => 'products/brands.php', 'icon' => 'fa-tags'],
                    ['label' => 'Product Tags', 'permission' => 'products.manage', 'url' => 'products/product-tags.php', 'icon' => 'fa-tag'],
                    ['label' => 'Tag Analytics', 'permission' => 'products.manage', 'url' => 'products/tag_analytics.php', 'icon' => 'fa-chart-bar'],
                    ['label' => 'Attributes', 'permission' => 'products.manage', 'url' => 'products/attributes.php', 'icon' => 'fa-cogs'],
                    ['label' => 'Variants', 'permission' => 'products.manage', 'url' => 'inventory/variants.php', 'icon' => 'fa-clone'],
                    ['label' => 'Barcode Labels', 'permission' => 'products.manage', 'url' => 'products/barcode_labels.php', 'icon' => 'fa-barcode'],
                ]
            ],
            [
                'label' => 'Stock',
                'icon' => 'fa-boxes',
                'items' => [
                    ['label' => 'Stock Overview', 'permission' => 'inventory.view', 'url' => 'inventory/inventory.php', 'icon' => 'fa-boxes'],
                    ['label' => 'Stock Tracking', 'permission' => 'inventory.view', 'url' => 'inventory/inventory_tracking.php', 'icon' => 'fa-route'],
                    ['label' => 'Automated Inventory', 'permission' => 'inventory.manage', 'url' => 'inventory/automated_inventory.php', 'icon' => 'fa-robot'],
                    ['label' => 'Expiry Management', 'permission' => 'inventory.view', 'url' => 'inventory/item_expiry.php', 'icon' => 'fa-calendar-times'],
                    ['label' => 'Inventory Report', 'permission' => 'inventory.view', 'url' => 'inventory/inventory_report.php', 'icon' => 'fa-file-alt'],
                    ['label' => 'Stock Transfer', 'permission' => 'inventory.manage', 'url' => 'inventory/stock_transfer.php', 'icon' => 'fa-exchange-alt'],
                    ['label' => 'Suppliers', 'permission' => 'suppliers.view', 'url' => 'inventory/suppliers.php', 'icon' => 'fa-truck-loading'],
                    ['label' => 'Add Supplier', 'permission' => 'suppliers.create', 'url' => 'inventory/supplier_form.php', 'icon' => 'fa-plus'],
                ]
            ],
            [
                'label' => 'Purchases',
                'icon' => 'fa-cart-plus',
                'items' => [
                    ['label' => 'Purchase Orders', 'permission' => 'purchases.view', 'url' => 'purchases/purchase_orders.php', 'icon' => 'fa-file-invoice-dollar'],
                    ['label' => 'Draft Purchases', 'permission' => 'purchases.view', 'url' => 'purchases/list_draft.php', 'icon' => 'fa-file-alt'],
                    ['label' => 'Add Purchase', 'permission' => 'purchases.create', 'url' => 'purchases/add_draft.php', 'icon' => 'fa-plus'],
                    ['label' => 'Receive Stock', 'permission' => 'purchases.manage', 'url' => 'purchases/purchase_receive.php', 'icon' => 'fa-check-double'],
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
                    ['label' => 'Customer Groups', 'permission' => 'customers.manage', 'url' => 'customers/groups.php', 'icon' => 'fa-layer-group'],
                ]
            ],
            [
                'label' => 'CRM',
                'icon' => 'fa-handshake',
                'items' => [
                    ['label' => 'CRM Dashboard', 'permission' => 'crm.view', 'url' => 'crm/index.php', 'icon' => 'fa-handshake'],
                    ['label' => 'Leads', 'permission' => 'crm.view', 'url' => 'crm/leads.php', 'icon' => 'fa-filter'],
                    ['label' => 'Deals', 'permission' => 'crm.view', 'url' => 'crm/deals.php', 'icon' => 'fa-briefcase'],
                    ['label' => 'Pipeline', 'permission' => 'crm.view', 'url' => 'crm/pipeline.php', 'icon' => 'fa-stream'],
                ]
            ],
            [
                'label' => 'Marketing',
                'icon' => 'fa-bullhorn',
                'items' => [
                    ['label' => 'Campaigns', 'permission' => 'marketing.view', 'url' => 'marketing/campaigns.php', 'icon' => 'fa-bullhorn'],
                    ['label' => 'Campaign Builder', 'permission' => 'marketing.manage', 'url' => 'marketing/campaign_builder.php', 'icon' => 'fa-wrench'],
                    ['label' => 'Email Templates', 'permission' => 'marketing.manage', 'url' => 'marketing/email_templates.php', 'icon' => 'fa-envelope'],
                    ['label' => 'SMS Templates', 'permission' => 'marketing.manage', 'url' => 'marketing/sms_templates.php', 'icon' => 'fa-sms'],
                ]
            ],
            [
                'label' => 'Shipments',
                'icon' => 'fa-shipping-fast',
                'items' => [
                    ['label' => 'All Shipments', 'permission' => 'shipments.view', 'url' => 'shipments/shipments.php', 'icon' => 'fa-shipping-fast'],
                    ['label' => 'Create Shipment', 'permission' => 'shipments.create', 'url' => 'shipments/create_shipment.php', 'icon' => 'fa-plus'],
                    ['label' => 'Track Shipment', 'permission' => 'shipments.view', 'url' => 'shipments/track_shipment.php', 'icon' => 'fa-search-location'],
                    ['label' => 'Bulk Shipment', 'permission' => 'shipments.manage', 'url' => 'shipments/bulk_shipment.php', 'icon' => 'fa-cubes'],
                ]
            ],
            [
                'label' => 'Expenses',
                'icon' => 'fa-wallet',
                'items' => [
                    ['label' => 'All Expenses', 'permission' => 'expenses.view', 'url' => 'expenses/expenses.php', 'icon' => 'fa-wallet'],
                    ['label' => 'Add Expense', 'permission' => 'expenses.create', 'url' => 'expenses/add_expense.php', 'icon' => 'fa-plus'],
                    ['label' => 'Expense Categories', 'permission' => 'expenses.manage', 'url' => 'expenses/expense_categories.php', 'icon' => 'fa-folder-open'],
                    ['label' => 'Expense Reports', 'permission' => 'reports.view', 'url' => 'expenses/expense_reports.php', 'icon' => 'fa-chart-pie'],
                ]
            ],
            [
                'label' => 'HR',
                'icon' => 'fa-user-tie',
                'items' => [
                    ['label' => 'Employees', 'permission' => 'hr.view', 'url' => 'hr/employees.php', 'icon' => 'fa-user-tie'],
                    ['label' => 'Add Employee', 'permission' => 'hr.create', 'url' => 'hr/employee_form.php', 'icon' => 'fa-user-plus'],
                    ['label' => 'Leave Requests', 'permission' => 'hr.view', 'url' => 'hr/leave_requests.php', 'icon' => 'fa-calendar-minus'],
                    ['label' => 'Attendance', 'permission' => 'hr.view', 'url' => 'hr/attendance.php', 'icon' => 'fa-clock'],
                ]
            ],
            [
                'label' => 'Branches',
                'icon' => 'fa-code-branch',
                'items' => [
                    ['label' => 'All Branches', 'permission' => 'branches.view', 'url' => 'branches/branches.php', 'icon' => 'fa-code-branch'],
                    ['label' => 'Add Branch', 'permission' => 'branches.create', 'url' => 'branches/branch_form.php', 'icon' => 'fa-plus'],
                    ['label' => 'Branch Reports', 'permission' => 'branches.view', 'url' => 'branches/branch_reports.php', 'icon' => 'fa-chart-simple'],
                ]
            ],
            [
                'label' => 'Finance',
                'icon' => 'fa-chart-line',
                'items' => [
                    ['label' => 'Reports', 'permission' => 'reports.view', 'url' => 'reports/reports.php', 'icon' => 'fa-chart-line'],
                    ['label' => 'Chart of Accounts', 'permission' => 'accounting.view', 'url' => 'accounting/chart_of_accounts.php', 'icon' => 'fa-book'],
                    ['label' => 'Journal Entries', 'permission' => 'accounting.view', 'url' => 'accounting/journal_entries.php', 'icon' => 'fa-edit'],
                    ['label' => 'Bank Reconciliation', 'permission' => 'accounting.manage', 'url' => 'accounting/bank_reconciliation.php', 'icon' => 'fa-university'],
                ]
            ],
            [
                'label' => 'AI',
                'icon' => 'fa-robot',
                'items' => [
                    ['label' => 'AI Recommendations', 'permission' => 'ai.view', 'url' => 'ai/recommendations.php', 'icon' => 'fa-brain'],
                    ['label' => 'AI Analytics', 'permission' => 'ai.view', 'url' => 'ai/analytics.php', 'icon' => 'fa-chart-pie'],
                ]
            ],
            [
                'label' => 'Online Store',
                'icon' => 'fa-store',
                'items' => [
                    ['label' => 'Store Dashboard', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/index.php', 'icon' => 'fa-chart-line'],
                    ['label' => 'Products', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/products.php', 'icon' => 'fa-box'],
                    ['label' => 'Orders', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/orders.php', 'icon' => 'fa-shopping-bag'],
                    ['label' => 'Customers', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/customers.php', 'icon' => 'fa-users'],
                    ['label' => 'Reviews', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/reviews.php', 'icon' => 'fa-star'],
                    ['label' => 'Banners', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/banners.php', 'icon' => 'fa-image'],
                    ['label' => 'Content Blocks', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/blocks.php', 'icon' => 'fa-cubes'],
                    ['label' => 'Customize Store', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/customize.php', 'icon' => 'fa-paint-brush'],
                    ['label' => 'Store Settings', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/settings.php', 'icon' => 'fa-sliders'],
                    ['label' => 'Shipping', 'permission' => 'dashboard.view', 'url' => 'dashboard/shop/shipping.php', 'icon' => 'fa-truck'],
                ]
            ],
            [
                'label' => 'Billing',
                'icon' => 'fa-credit-card',
                'items' => [
                    ['label' => 'Subscription', 'permission' => 'billing.view', 'url' => 'dashboard/billing.php', 'icon' => 'fa-credit-card'],
                    ['label' => 'Invoices', 'permission' => 'billing.view', 'url' => 'dashboard/invoices.php', 'icon' => 'fa-file-invoice'],
                    ['label' => 'Payment History', 'permission' => 'billing.view', 'url' => 'dashboard/payments.php', 'icon' => 'fa-history'],
                ]
            ],
            [
                'label' => 'System',
                'icon' => 'fa-cogs',
                'items' => [
                    ['label' => 'Plugins', 'permission' => 'system.admin', 'url' => 'admin/plugins.php', 'icon' => 'fa-puzzle-piece'],
                    ['label' => 'Users', 'permission' => 'users.view', 'url' => 'users/users.php', 'icon' => 'fa-user-gear'],
                    ['label' => 'Add User', 'permission' => 'users.create', 'url' => 'users/user_form.php', 'icon' => 'fa-user-plus'],
                    ['label' => 'Roles & Permissions', 'permission' => 'roles.view', 'url' => 'users/roles/roles.php', 'icon' => 'fa-user-shield'],
                    ['label' => 'System Settings', 'permission' => 'system.settings', 'url' => 'dashboard/settings.php', 'icon' => 'fa-gear'],
                    ['label' => 'Security Dashboard', 'permission' => 'system.logs', 'url' => 'admin/security_dashboard.php', 'icon' => 'fa-shield-alt'],
                    ['label' => 'Profile', 'permission' => 'profile.view', 'url' => 'dashboard/profile.php', 'icon' => 'fa-user-circle'],
                    ['label' => 'Backup', 'permission' => 'system.admin', 'url' => 'admin/backup.php', 'icon' => 'fa-database'],
                    ['label' => 'System Logs', 'permission' => 'system.logs', 'url' => 'admin/system_logs.php', 'icon' => 'fa-file-alt'],
                ]
            ]
        ];
    }
    
    /**
     * Check if user has a specific permission
     */
    private function hasPermission(string $permission): bool {
        // Super admin has all permissions
        if ($this->isSuperAdmin) {
            return true;
        }
        
        // Check if user has this specific permission
        return in_array($permission, $this->userPermissions);
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
                if (!$permission || $this->hasPermission($permission)) {
                    // Convert relative URL to full URL using base_url()
                    $item['url'] = base_url($item['url']);
                    
                    // Extract page and dir for active highlighting
                    $item['page'] = basename($item['url']);
                    $item['dir'] = basename(dirname($item['url']));
                    
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
     * Get sidebar as JSON
     */
    public function generateSidebarJson(): string {
        return json_encode($this->generateSidebar(), JSON_PRETTY_PRINT);
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
            'blocked_items' => $totalItems - $allowedItems,
            'permissions_count' => count($this->userPermissions),
            'is_super_admin' => $this->isSuperAdmin
        ];
    }
    
    /**
     * Get all available permissions in the menu
     */
    public function getAvailablePermissions(): array {
        $permissions = [];
        
        foreach ($this->menuStructure as $section) {
            foreach ($section['items'] as $item) {
                if (isset($item['permission']) && !in_array($item['permission'], $permissions)) {
                    $permissions[] = $item['permission'];
                }
            }
        }
        
        return $permissions;
    }
    
    /**
     * Clear cached permissions
     */
    public function clearCache(): void {
        unset($_SESSION['permissions']);
        $this->userPermissions = [];
        $this->loadUserPermissions();
    }
}