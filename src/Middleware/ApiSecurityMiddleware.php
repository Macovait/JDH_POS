<?php
/**
 * API Security Middleware - Comprehensive Protection for API Endpoints
 * Enforces authentication, authorization, and data scoping for all API calls
 * 
 * @package Jakababa\Middleware
 * @version 1.0.0
 */

class ApiSecurityMiddleware {
    private $role_based_access;
    private $data_scope;
    private $audit_logger;
    
    public function __construct() {
        $this->role_based_access = RoleBasedAccess::getInstance();
        $this->data_scope = DataAccessScope::getInstance();
        $this->audit_logger = AuditLogger::getInstance();
    }
    
    /**
     * Secure API endpoint with authentication and authorization
     */
    public function secureEndpoint(string $required_permission = null, array $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE']): void {
        // Start secure session
        if (function_exists('start_session_secure')) {
            start_session_secure();
        }
        
        // Validate request method
        $this->validateRequestMethod($allowed_methods);
        
        // Validate CSRF for state-changing methods
        $this->validateCsrfToken();
        
        // Check authentication
        $this->requireAuthentication();
        
        // Check authorization if permission required
        if ($required_permission) {
            $this->requirePermission($required_permission);
        }
        
        // Apply tenant isolation
        $this->enforceTenantIsolation();
        
        // Log API access
        $this->logApiAccess($required_permission);
    }
    
    /**
     * Secure AJAX endpoint
     */
    public function secureAjaxEndpoint(string $required_permission = null): void {
        // Verify AJAX request
        if (!$this->isAjaxRequest()) {
            $this->sendJsonError('Invalid request type', 400);
        }
        
        // Apply full security
        $this->secureEndpoint($required_permission);
    }
    
    /**
     * Validate request method
     */
    private function validateRequestMethod(array $allowed_methods): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($method, $allowed_methods)) {
            $this->audit_logger->logSecurityViolation('Invalid HTTP method', [
                'method' => $method,
                'allowed_methods' => $allowed_methods
            ]);
            
            $this->sendJsonError('Method not allowed', 405);
        }
    }
    
    /**
     * Validate CSRF token for state-changing requests
     */
    private function validateCsrfToken(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!$token || !function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
                $this->audit_logger->logSecurityViolation('Invalid CSRF token', [
                    'method' => $method,
                    'uri' => $_SERVER['REQUEST_URI'] ?? ''
                ]);
                
                $this->sendJsonError('Invalid security token', 403);
            }
        }
    }
    
    /**
     * Require user authentication
     */
    private function requireAuthentication(): void {
        $user_id = $_SESSION['user_id'] ?? null;
        $tenant_id = $_SESSION['tenant_id'] ?? null;
        
        if (!$user_id || !$tenant_id) {
            $this->audit_logger->logSecurityViolation('Unauthenticated API access', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $this->sendJsonError('Authentication required', 401);
        }
        
        // Validate session integrity
        $this->validateSessionIntegrity();
    }
    
    /**
     * Validate session integrity
     */
    private function validateSessionIntegrity(): void {
        // Check session fingerprint
        if (isset($_SESSION['fingerprint'])) {
            $current_fingerprint = $this->generateSessionFingerprint();
            
            if (!hash_equals($_SESSION['fingerprint'], $current_fingerprint)) {
                $this->audit_logger->logSecurityViolation('Session fingerprint mismatch', [
                    'user_id' => $_SESSION['user_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                
                session_destroy();
                $this->sendJsonError('Session invalid', 401);
            }
        }
        
        // Check session timeout
        $last_activity = $_SESSION['last_activity'] ?? 0;
        $timeout = 3600; // 1 hour
        
        if (time() - $last_activity > $timeout) {
            $this->audit_logger->logSecurityViolation('Session timeout', [
                'user_id' => $_SESSION['user_id'],
                'last_activity' => $last_activity
            ]);
            
            session_destroy();
            $this->sendJsonError('Session expired', 401);
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Generate session fingerprint
     */
    private function generateSessionFingerprint(): string {
        return hash('sha256', 
            ($_SERVER['REMOTE_ADDR'] ?? '') .
            ($_SERVER['HTTP_USER_AGENT'] ?? '') .
            session_id()
        );
    }
    
    /**
     * Require specific permission
     */
    private function requirePermission(string $permission): void {
        $has_permission = $this->role_based_access->hasPermission($permission);
        
        // Log permission check
        $this->audit_logger->logPermissionCheck($permission, $has_permission, [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]);
        
        if (!$has_permission) {
            $this->audit_logger->logSecurityViolation('Permission denied', [
                'permission' => $permission,
                'user_id' => $_SESSION['user_id'],
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            $this->sendJsonError('Permission denied', 403);
        }
    }
    
    /**
     * Enforce tenant isolation
     */
    private function enforceTenantIsolation(): void {
        // Check for tenant ID tampering
        $request_tenant_id = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? null;
        $session_tenant_id = $_SESSION['tenant_id'] ?? null;
        
        if ($request_tenant_id && $request_tenant_id != $session_tenant_id) {
            $this->audit_logger->logSecurityViolation('Tenant ID tampering', [
                'request_tenant_id' => $request_tenant_id,
                'session_tenant_id' => $session_tenant_id,
                'user_id' => $_SESSION['user_id']
            ]);
            
            $this->sendJsonError('Access denied', 403);
        }
        
        // Check for branch ID tampering
        $request_branch_id = $_GET['branch_id'] ?? $_POST['branch_id'] ?? null;
        $session_branch_id = $_SESSION['branch_id'] ?? null;
        
        if ($request_branch_id && $request_branch_id != $session_branch_id) {
            // Allow branch switching only with proper permission
            if (!$this->role_based_access->hasPermission('branches.switch')) {
                $this->audit_logger->logSecurityViolation('Branch ID tampering', [
                    'request_branch_id' => $request_branch_id,
                    'session_branch_id' => $session_branch_id,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $this->sendJsonError('Access denied', 403);
            }
        }
    }
    
    /**
     * Log API access
     */
    private function logApiAccess(string $required_permission = null): void {
        $this->audit_logger->logDataAccess('api_access', $_SERVER['REQUEST_URI'] ?? '', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'required_permission' => $required_permission,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? ''
        ]);
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Send JSON error response
     */
    private function sendJsonError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
    
    /**
     * Apply rate limiting
     */
    public function applyRateLimit(string $action, int $max_requests = 60, int $window_seconds = 60): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 0;
        
        $key = "rate_limit_{$action}_{$user_id}_{$ip}";
        $now = time();
        
        // Initialize rate limit data
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }
        
        $rate_data = $_SESSION[$key];
        
        // Reset window if expired
        if (($now - $rate_data['window_start']) > $window_seconds) {
            $_SESSION[$key] = [
                'count' => 1,
                'window_start' => $now
            ];
            return;
        }
        
        // Check if limit exceeded
        if ($rate_data['count'] >= $max_requests) {
            $this->audit_logger->logSecurityViolation('Rate limit exceeded', [
                'action' => $action,
                'ip' => $ip,
                'user_id' => $user_id,
                'count' => $rate_data['count']
            ]);
            
            $this->sendJsonError('Rate limit exceeded', 429);
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
    }
    
    /**
     * Validate API key for external API access
     */
    public function validateApiKey(): void {
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        
        if (!$api_key) {
            $this->sendJsonError('API key required', 401);
        }
        
        // Validate API key against database
        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("
                SELECT api_keys.*, users.tenant_id, users.role 
                FROM api_keys 
                JOIN users ON api_keys.user_id = users.id 
                WHERE api_keys.key = ? AND api_keys.status = 'active' 
                AND users.status = 'active' AND users.deleted_at IS NULL
                LIMIT 1
            ");
            
            $stmt->execute([$api_key]);
            $api_key_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$api_key_data) {
                $this->audit_logger->logSecurityViolation('Invalid API key', [
                    'api_key' => substr($api_key, 0, 8) . '...',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                
                $this->sendJsonError('Invalid API key', 401);
            }
            
            // Set context from API key
            $_SESSION['user_id'] = $api_key_data['user_id'];
            $_SESSION['tenant_id'] = $api_key_data['tenant_id'];
            $_SESSION['role'] = $api_key_data['role'];
            $_SESSION['api_key_auth'] = true;
            
        } catch (Exception $e) {
            error_log("API key validation failed: " . $e->getMessage());
            $this->sendJsonError('Authentication failed', 500);
        }
    }
    
    /**
     * Get database connection
     */
    private function getDbConnection() {
        if (function_exists('get_db_connection')) {
            return get_db_connection();
        }
        
        throw new RuntimeException('Database connection not available');
    }
}
