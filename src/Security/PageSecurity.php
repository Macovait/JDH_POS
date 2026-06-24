<?php
/**
 * Page-Level Security Middleware
 * Enforces permission checks on individual pages
 * Redirects unauthorized users to dashboard with error message
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class PageSecurity {
    
    /**
     * Map of page paths to required permissions
     */
    private static array $pagePermissions = [
        // Dashboard
        'dashboard/home.php' => 'dashboard.view',
        'dashboard/activity_logs.php' => 'system.logs',
        'dashboard/settings.php' => 'system.settings',
        'dashboard/billing.php' => 'billing.view',
        'dashboard/profile.php' => 'profile.view',
        
// POS & Sales
         'pos/pos.php' => 'pos.access',
         'pos/all_sales.php' => 'sales.view',
         'pos/sales.php' => 'sales.view',
         'pos/add_sales.php' => 'sales.create',
         'pos/returns' => 'sales.returns',
         'pos/returns/select_sale_for_return.php' => 'sales.returns',
        'pos/returns/return_sale.php' => 'sales.returns',
        'pos/returns/list_sell_return.php' => 'sales.returns',
        'pos/returns/create_sell_return.php' => 'sales.returns',
        'pos/returns/view_sell_return.php' => 'sales.returns',
        'pos/returns/edit_sell_return.php' => 'sales.returns',
        'pos/returns/delete_sell_return.php' => 'sales.returns',
        'pos/returns/process_return.php' => 'sales.returns',
        'pos/returns/submit_return.php' => 'sales.returns',
        'pos/returns/submit_bulk_return.php' => 'sales.returns',
        'pos/returns/process_bulk_return.php' => 'sales.returns',
        'pos/returns/update_return_status.php' => 'sales.returns',
        'pos/returns/delete_return.php' => 'sales.returns',
        'pos/returns/cancel_return.php' => 'sales.returns',
        'pos/returns/export_returns.php' => 'sales.returns',
        'pos/returns/print_return.php' => 'sales.returns',
        'pos/returns/view_return.php' => 'sales.returns',
        'pos/kitchen.php' => 'pos.access',
        'pos/discounts.php' => 'sales.discounts',
        
        // Products
        'products/products.php' => 'products.view',
        'products/product_form.php' => 'products.create',
        'products/product_edit.php' => 'products.edit',
        'products/product_view.php' => 'products.view',
        'products/brands.php' => 'brands.view',
        'products/brand_form.php' => 'brands.create',
        'products/attributes.php' => 'products.manage',
        'products/product-tags.php' => 'products.manage',
        'products/barcode_labels.php' => 'products.view',
        'products/import_products.php' => 'products.create',
        'products/export_products.php' => 'products.view',
        
        // Categories
        'categories/list_categories.php' => 'categories.view',
        'categories/category_form.php' => 'categories.create',
        
        // Inventory
        'inventory/inventory.php' => 'inventory.view',
        'inventory/item_expiry.php' => 'inventory.expiry',
        'inventory/suppliers.php' => 'suppliers.view',
        'inventory/stock_adjustment.php' => 'inventory.adjust',
        'inventory/stock_transfer.php' => 'inventory.transfer',
        'pos/transfers-ui.html' => 'inventory.transfer',
        
        // Purchases
        'purchases/purchase_orders.php' => 'purchases.view',
        
        // Shipments
        'shipments/shipments.php' => 'shipments.view',
        
        // Customers
        'customers/customers.php' => 'customers.view',
        'customers/add_customer.php' => 'customers.create',
        'customers/customer_form.php' => 'customers.create',
        'customers/bulk_sms.php' => 'customers.bulk_sms',
        'customers/loyalty.php' => 'loyalty.view',
        
        // Reports
        'reports/reports.php' => 'reports.view',
        'pos/audit-ui.html' => 'system.logs',
        'pos/credit-ui.html' => 'credit.view',
        
        // Users & Roles
        'users/users.php' => 'users.view',
        'users/user_form.php' => 'users.create',
        'users/roles/roles.php' => 'roles.view',
        
        // Branches
        'branches/branches.php' => 'branches.view',
        
        // Backup
        'backup/backup.php' => 'system.backup',
        
        // Vouchers
        'vouchers/vouchers.php' => 'sales.vouchers',
        
        // Quotations
        'quotations/list_quotation.php' => 'quotations.view',
    ];
    
    /**
     * Check if current page requires permission and enforce it
     */
    public static function enforce(): void {
        $currentPage = self::getCurrentPagePath();
        
        // Skip permission checks for standalone UI files and HTML pages
        $skipPages = ['returns', 'audit-ui.html', 'credit-ui.html', 'transfers-ui.html'];
        if (in_array(basename($currentPage), $skipPages)) {
            return;
        }
        
        if (empty($currentPage)) {
            return;
        }
        
        // Check if current page has a permission requirement
        if (isset(self::$pagePermissions[$currentPage])) {
            $requiredPermission = self::$pagePermissions[$currentPage];
            
            // Check if user has this permission
            if (!has_permission($requiredPermission)) {
                self::handleUnauthorizedAccess($currentPage, $requiredPermission);
            }
        }
    }
    
    /**
     * Get current page path relative to public directory
     */
    private static function getCurrentPagePath(): string {
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Extract page from script path
        $publicPos = strpos($scriptPath, '/public/');
        if ($publicPos !== false) {
            $relativePath = substr($scriptPath, $publicPos + 8); // +8 for '/public/'
            return ltrim($relativePath, '/');
        }
        
        // Fallback to request URI
        $uriPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
        $publicPos = strpos($uriPath, '/public/');
        if ($publicPos !== false) {
            $relativePath = substr($uriPath, $publicPos + 8);
            return ltrim($relativePath, '/');
        }
        
        return '';
    }
    
    /**
     * Handle unauthorized access attempt
     */
    private static function handleUnauthorizedAccess(string $page, string $permission): void {
        // Log the unauthorized access attempt
        if (function_exists('log_security_event')) {
            log_security_event('unauthorized_access', [
                'page' => $page,
                'required_permission' => $permission,
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Set flash message
        $_SESSION['error_message'] = 'Access Denied: You do not have permission to access this page.';
        
        // Redirect to dashboard
        if (!headers_sent()) {
            header('Location: ' . base_url('dashboard/home.php?error=unauthorized'));
            exit;
        }
        
        // Fallback if headers already sent
        echo '<script>window.location.href = "' . base_url('dashboard/home.php?error=unauthorized') . '";</script>';
        exit;
    }
    
    /**
     * Register a page with its required permission
     */
    public static function registerPage(string $pagePath, string $permission): void {
        self::$pagePermissions[$pagePath] = $permission;
    }
    
    /**
     * Get all registered page permissions
     */
    public static function getPagePermissions(): array {
        return self::$pagePermissions;
    }
    
    /**
     * Check if a specific page requires permission
     */
    public static function pageRequiresPermission(string $pagePath): bool {
        return isset(self::$pagePermissions[$pagePath]);
    }
    
    /**
     * Get required permission for a page
     */
    public static function getPagePermission(string $pagePath): ?string {
        return self::$pagePermissions[$pagePath] ?? null;
    }
}
?>
