<?php
/**
 * API Security Middleware
 * Enforces authentication and permissions on AJAX/API endpoints
 * Returns JSON error responses for unauthorized access
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class ApiSecurity {
    
    /**
     * Map of AJAX endpoints to required permissions
     */
    private static array $apiPermissions = [
        // Product APIs
        'ajax/get_products.php' => 'products.view',
        'ajax/check_product.php' => 'products.view',
        'ajax/get_product_by_barcode.php' => 'products.view',
        'ajax/clear_product_cache.php' => 'products.manage',
        
        // Sales APIs
        'ajax/get_sale_items.php' => 'sales.view',
        'ajax/get_sale_for_return.php' => 'sales.returns',
        'ajax/process_return.php' => 'sales.returns',
        'ajax/export_sales_for_return.php' => 'sales.view',
        
        // Customer APIs
        'ajax/get_customer.php' => 'customers.view',
        'ajax/get_customer_history.php' => 'customers.view',
        'ajax/get_customer_loyalty.php' => 'loyalty.view',
        'ajax/get_customer_consent.php' => 'customers.view',
        'ajax/get_customer_insights.php' => 'customers.view',
        
        // Inventory APIs
        'ajax/get_stock.php' => 'inventory.view',
        'ajax/check_stock.php' => 'inventory.view',
        
        // Dashboard APIs
        'ajax/get_dashboard.php' => 'dashboard.view',
        'ajax/get_dashboard_data.php' => 'dashboard.view',
        'ajax/get_realtime_metrics.php' => 'dashboard.view',
        'ajax/dashboard_filters.php' => 'dashboard.view',
        
        // Report APIs
        'ajax/get_report_data.php' => 'reports.view',
        'ajax/custom_report_query.php' => 'reports.view',
        
        // Settings APIs
        'ajax/get_settings.php' => 'system.settings',
        'ajax/get_payment_methods.php' => 'system.settings',
        
        // Activity Log APIs
        'ajax/get_activity_logs.php' => 'system.logs',
        'ajax/get_log_details.php' => 'system.logs',
        'ajax/export_activity_logs.php' => 'system.logs',
        'ajax/clear_old_logs.php' => 'system.logs',
        
        // Backup APIs
        'ajax/download_backup.php' => 'system.backup',
        
        // POS APIs
        'ajax/cart_actions.php' => 'pos.access',
        'ajax/check_register.php' => 'pos.access',
        'ajax/close_register.php' => 'pos.access',
        'ajax/get_register_summary.php' => 'pos.access',
        
        // Purchase APIs
        'ajax/get_purchase_draft.php' => 'purchases.view',
        'ajax/delete_purchase_item.php' => 'purchases.edit',
        
        // SMS APIs
        'ajax/get_sms_history.php' => 'customers.bulk_sms',
        'ajax/get_sms_analytics.php' => 'customers.bulk_sms',
        
        // AI/ML APIs
        'ajax/get_smart_recommendations.php' => 'pos.access',
        'ajax/ai_recommendations.php' => 'pos.access',
        'ajax/ai_track_behavior.php' => 'pos.access',
        'ajax/get_advanced_loyalty.php' => 'loyalty.view',
        
        // Payment APIs
        'ajax/check_payment_status.php' => 'pos.access',
        'ajax/create_qr_payment.php' => 'pos.access',
    ];
    
    /**
     * Enforce API security on current request
     */
    public static function enforce(): void {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is authenticated
        if (empty($_SESSION['user_id'])) {
            self::sendUnauthorizedResponse('Authentication required');
        }
        
        // Get current endpoint
        $endpoint = self::getCurrentEndpoint();
        
        if (empty($endpoint)) {
            return;
        }
        
        // Check if endpoint requires permission
        if (isset(self::$apiPermissions[$endpoint])) {
            $requiredPermission = self::$apiPermissions[$endpoint];
            
            if (!has_permission($requiredPermission)) {
                self::sendForbiddenResponse("Permission denied: {$requiredPermission} required");
            }
        }
        
        // Validate CSRF token for state-changing requests
        if (self::isStateChangingRequest()) {
            self::validateCsrfToken();
        }
    }
    
    /**
     * Check if current request is a state-changing operation (POST, PUT, DELETE)
     */
    private static function isStateChangingRequest(): bool {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true);
    }
    
    /**
     * Validate CSRF token
     */
    private static function validateCsrfToken(): void {
        $headers = getallheaders();
        $tokenFromHeader = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
        $tokenFromPost = $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '';
        $token = $tokenFromHeader ?: $tokenFromPost;
        
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            self::sendForbiddenResponse('Invalid CSRF token');
        }
    }
    
    /**
     * Get current endpoint path relative to public directory
     */
    private static function getCurrentEndpoint(): string {
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        $publicPos = strpos($scriptPath, '/public/');
        if ($publicPos !== false) {
            $relativePath = substr($scriptPath, $publicPos + 8);
            return ltrim($relativePath, '/');
        }
        
        $uriPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
        $publicPos = strpos($uriPath, '/public/');
        if ($publicPos !== false) {
            $relativePath = substr($uriPath, $publicPos + 8);
            return ltrim($relativePath, '/');
        }
        
        return '';
    }
    
    /**
     * Send unauthorized response
     */
    private static function sendUnauthorizedResponse(string $message): void {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => true,
            'message' => $message,
            'code' => 'UNAUTHORIZED',
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ]);
        exit;
    }
    
    /**
     * Send forbidden response
     */
    private static function sendForbiddenResponse(string $message): void {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => true,
            'message' => $message,
            'code' => 'FORBIDDEN',
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ]);
        exit;
    }
    
    /**
     * Register an API endpoint with its required permission
     */
    public static function registerEndpoint(string $endpoint, string $permission): void {
        self::$apiPermissions[$endpoint] = $permission;
    }
}
?>
