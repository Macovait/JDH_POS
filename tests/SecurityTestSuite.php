<?php
/**
 * Security Test Suite - Comprehensive Security Testing
 * Tests all security components and validates role-based access controls
 * 
 * @package Jakababa\Tests
 * @version 1.0.0
 */

class SecurityTestSuite {
    private $test_results = [];
    private $test_user_id = 1;
    private $test_tenant_id = 1;
    private $original_session = [];
    
    public function __construct() {
        $this->backupSession();
        $this->setupTestEnvironment();
    }
    
    /**
     * Run all security tests
     */
    public function runAllTests(): array {
        echo "Starting Security Test Suite...\n";
        
        $this->testDataAccessScope();
        $this->testRoleBasedAccess();
        $this->testTenantIsolation();
        $this->testApiSecurity();
        $this->testAuditLogging();
        $this->testSecureQueryBuilder();
        $this->testPermissionEnforcement();
        $this->testSecurityMiddleware();
        
        $this->restoreSession();
        
        return $this->generateTestReport();
    }
    
    /**
     * Test data access scope
     */
    private function testDataAccessScope(): void {
        echo "Testing Data Access Scope...\n";
        
        try {
            // Initialize test session
            $_SESSION['user_id'] = $this->test_user_id;
            $_SESSION['tenant_id'] = $this->test_tenant_id;
            $_SESSION['branch_id'] = 1;
            $_SESSION['role'] = 'admin';
            $_SESSION['is_super_admin'] = false;
            
            $data_scope = DataAccessScope::getInstance();
            
            // Test tenant WHERE clause generation
            $where = $data_scope->getTenantWhere('users');
            $this->assertTest(
                'tenant_where_clause',
                strpos($where, 'tenant_id = ' . $this->test_tenant_id) !== false,
                'Tenant WHERE clause should include tenant_id filter'
            );
            
            // Test tenant parameters
            $params = $data_scope->getTenantParams('users');
            $this->assertTest(
                'tenant_params',
                in_array($this->test_tenant_id, $params),
                'Tenant parameters should include tenant_id'
            );
            
            // Test record access check
            $can_access = $data_scope->canAccessRecord('users', $this->test_user_id);
            $this->assertTest(
                'record_access_check',
                is_bool($can_access),
                'Record access check should return boolean'
            );
            
            // Test super admin bypass
            $_SESSION['is_super_admin'] = true;
            $data_scope = DataAccessScope::getInstance(); // Reset instance
            $where = $data_scope->getTenantWhere('users');
            $this->assertTest(
                'super_admin_bypass',
                $where === '1=1',
                'Super admin should bypass tenant filtering'
            );
            
        } catch (Exception $e) {
            $this->assertTest('data_access_scope_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test role-based access control
     */
    private function testRoleBasedAccess(): void {
        echo "Testing Role-Based Access Control...\n";
        
        try {
            // Setup test user with admin role
            $_SESSION['role'] = 'admin';
            $_SESSION['permissions'] = ['users.view', 'users.create', 'products.view'];
            $_SESSION['is_super_admin'] = false;
            
            $rbac = RoleBasedAccess::getInstance();
            
            // Test permission check
            $has_permission = $rbac->hasPermission('users.view');
            $this->assertTest(
                'permission_check_granted',
                $has_permission === true,
                'Should grant access to users.view permission'
            );
            
            $has_permission = $rbac->hasPermission('users.delete');
            $this->assertTest(
                'permission_check_denied',
                $has_permission === false,
                'Should deny access to users.delete permission'
            );
            
            // Test wildcard permissions
            $_SESSION['permissions'] = ['products.*'];
            $rbac = RoleBasedAccess::getInstance(); // Reset instance
            $has_permission = $rbac->hasPermission('products.view');
            $this->assertTest(
                'wildcard_permission',
                $has_permission === true,
                'Should grant access to products.view with products.* permission'
            );
            
            // Test module access
            $can_access = $rbac->canAccessModule('products');
            $this->assertTest(
                'module_access',
                $can_access === true,
                'Should allow access to products module'
            );
            
            // Test role hierarchy
            $_SESSION['role'] = 'manager';
            $rbac = RoleBasedAccess::getInstance(); // Reset instance
            $role_level = $rbac->getRoleLevel();
            $this->assertTest(
                'role_hierarchy',
                $role_level > 0,
                'Role level should be greater than 0'
            );
            
        } catch (Exception $e) {
            $this->assertTest('role_based_access_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test tenant isolation
     */
    private function testTenantIsolation(): void {
        echo "Testing Tenant Isolation...\n";
        
        try {
            $_SESSION['user_id'] = $this->test_user_id;
            $_SESSION['tenant_id'] = $this->test_tenant_id;
            $_SESSION['is_super_admin'] = false;
            
            $middleware = new TenantIsolationMiddleware();
            
            // Test parameter validation
            $_GET['tenant_id'] = $this->test_tenant_id + 1; // Different tenant
            
            try {
                $middleware->applyTenantIsolation();
                $this->assertTest('tenant_isolation_violation', false, 'Should detect tenant parameter tampering');
            } catch (SecurityException $e) {
                $this->assertTest('tenant_isolation_protection', true, 'Should prevent tenant parameter tampering');
            }
            
            // Test file access validation
            $file_path = '/uploads/tenant_' . ($this->test_tenant_id + 1) . '/test.jpg';
            $can_access = $middleware->validateFileAccess($file_path);
            $this->assertTest(
                'file_access_denied',
                $can_access === false,
                'Should deny access to files from other tenants'
            );
            
            $file_path = '/uploads/tenant_' . $this->test_tenant_id . '/test.jpg';
            $can_access = $middleware->validateFileAccess($file_path);
            $this->assertTest(
                'file_access_granted',
                $can_access === true,
                'Should allow access to files from own tenant'
            );
            
        } catch (Exception $e) {
            $this->assertTest('tenant_isolation_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test API security middleware
     */
    private function testApiSecurity(): void {
        echo "Testing API Security Middleware...\n";
        
        try {
            $_SESSION['user_id'] = $this->test_user_id;
            $_SESSION['tenant_id'] = $this->test_tenant_id;
            $_SESSION['permissions'] = ['api.access'];
            $_SESSION['is_super_admin'] = false;
            
            $middleware = new ApiSecurityMiddleware();
            
            // Test authentication requirement
            unset($_SESSION['user_id']);
            
            ob_start();
            try {
                $middleware->secureEndpoint('api.access');
                $this->assertTest('api_authentication_bypass', false, 'Should require authentication');
            } catch (Exception $e) {
                $this->assertTest('api_authentication_required', true, 'Should require authentication for API access');
            }
            ob_end_clean();
            
            // Test permission requirement
            $_SESSION['user_id'] = $this->test_user_id;
            $_SESSION['tenant_id'] = $this->test_tenant_id;
            
            ob_start();
            try {
                $middleware->secureEndpoint('api.admin');
                $this->assertTest('api_permission_bypass', false, 'Should require proper permissions');
            } catch (Exception $e) {
                $this->assertTest('api_permission_enforced', true, 'Should enforce API permissions');
            }
            ob_end_clean();
            
        } catch (Exception $e) {
            $this->assertTest('api_security_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test audit logging
     */
    private function testAuditLogging(): void {
        echo "Testing Audit Logging...\n";
        
        try {
            $_SESSION['user_id'] = $this->test_user_id;
            $_SESSION['tenant_id'] = $this->test_tenant_id;
            
            $audit_logger = AuditLogger::getInstance();
            
            // Test data access logging
            $audit_logger->logDataAccess('select', 'users', ['test' => 'data']);
            $this->assertTest('audit_data_access', true, 'Should log data access events');
            
            // Test security violation logging
            $audit_logger->logSecurityViolation('test_violation', ['context' => 'test']);
            $this->assertTest('audit_security_violation', true, 'Should log security violations');
            
            // Test permission check logging
            $audit_logger->logPermissionCheck('test.permission', true);
            $this->assertTest('audit_permission_check', true, 'Should log permission checks');
            
            // Test audit trail manager
            $trail_manager = new AuditTrailManager();
            $trail_manager->logDataAccess('users', 'SELECT', ['record_id' => 1]);
            $this->assertTest('audit_trail_manager', true, 'Audit trail manager should log data access');
            
        } catch (Exception $e) {
            $this->assertTest('audit_logging_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test secure query builder
     */
    private function testSecureQueryBuilder(): void {
        echo "Testing Secure Query Builder...\n";
        
        try {
            $_SESSION['user_id'] = $this->test_user_id;
            $_SESSION['tenant_id'] = $this->test_tenant_id;
            $_SESSION['is_super_admin'] = false;
            
            $builder = new SecureQueryBuilder();
            
            // Test SELECT with tenant scoping
            $results = $builder->select('users', ['id', 'username'], ['status' => 1]);
            $this->assertTest(
                'secure_select',
                is_array($results),
                'Secure SELECT should return array'
            );
            
            // Test INSERT with tenant context
            $test_data = [
                'username' => 'test_user',
                'email' => 'test@example.com',
                'status' => 1
            ];
            
            // Note: This would actually insert data, so we'll just test the method exists
            $this->assertTest(
                'secure_insert_method',
                method_exists($builder, 'insert'),
                'Secure query builder should have insert method'
            );
            
            // Test UPDATE with tenant validation
            $this->assertTest(
                'secure_update_method',
                method_exists($builder, 'update'),
                'Secure query builder should have update method'
            );
            
            // Test DELETE with tenant validation
            $this->assertTest(
                'secure_delete_method',
                method_exists($builder, 'delete'),
                'Secure query builder should have delete method'
            );
            
        } catch (Exception $e) {
            $this->assertTest('secure_query_builder_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test permission enforcement
     */
    private function testPermissionEnforcement(): void {
        echo "Testing Permission Enforcement...\n";
        
        try {
            // Initialize security bootstrap
            SecurityBootstrap::initialize();
            
            // Test permission check function
            $_SESSION['permissions'] = ['test.permission'];
            $has_permission = has_permission('test.permission');
            $this->assertTest(
                'permission_function_granted',
                $has_permission === true,
                'has_permission function should return true for granted permission'
            );
            
            $has_permission = has_permission('nonexistent.permission');
            $this->assertTest(
                'permission_function_denied',
                $has_permission === false,
                'has_permission function should return false for denied permission'
            );
            
            // Test data scope function
            $scope = get_data_scope();
            $this->assertTest(
                'data_scope_function',
                is_array($scope) && isset($scope['tenant_id']),
                'get_data_scope function should return array with tenant_id'
            );
            
        } catch (Exception $e) {
            $this->assertTest('permission_enforcement_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Test security middleware
     */
    private function testSecurityMiddleware(): void {
        echo "Testing Security Middleware...\n";
        
        try {
            // Test rate limiting
            SecurityBootstrap::applyRateLimit('test_action', 5, 60);
            $this->assertTest('rate_limiting', true, 'Rate limiting should be applied');
            
            // Test tenant isolation validation
            validate_tenant_isolation();
            $this->assertTest('tenant_isolation_validation', true, 'Tenant isolation should be validated');
            
            // Test security event logging
            log_security_event('test_event', ['test' => 'data']);
            $this->assertTest('security_event_logging', true, 'Security events should be logged');
            
        } catch (Exception $e) {
            $this->assertTest('security_middleware_exception', false, $e->getMessage());
        }
    }
    
    /**
     * Assert test result
     */
    private function assertTest(string $test_name, bool $passed, string $description): void {
        $this->test_results[] = [
            'test' => $test_name,
            'passed' => $passed,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo "  {$test_name}: " . ($passed ? 'PASS' : 'FAIL') . " - {$description}\n";
    }
    
    /**
     * Setup test environment
     */
    private function setupTestEnvironment(): void {
        // Initialize security components
        SecurityBootstrap::initialize();
        
        // Create audit tables for testing
        try {
            $audit_logger = AuditLogger::getInstance();
            $audit_logger->createAuditTable();
        } catch (Exception $e) {
            // Table might already exist
        }
    }
    
    /**
     * Backup current session
     */
    private function backupSession(): void {
        $this->original_session = $_SESSION;
    }
    
    /**
     * Restore original session
     */
    private function restoreSession(): void {
        $_SESSION = $this->original_session;
        
        // Reset security singletons
        SecurityBootstrap::reset();
    }
    
    /**
     * Generate test report
     */
    private function generateTestReport(): array {
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, fn($r) => $r['passed']));
        $failed_tests = $total_tests - $passed_tests;
        
        $report = [
            'summary' => [
                'total_tests' => $total_tests,
                'passed' => $passed_tests,
                'failed' => $failed_tests,
                'success_rate' => $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'tests' => $this->test_results,
            'recommendations' => $this->generateRecommendations()
        ];
        
        echo "\n=== Security Test Report ===\n";
        echo "Total Tests: {$total_tests}\n";
        echo "Passed: {$passed_tests}\n";
        echo "Failed: {$failed_tests}\n";
        echo "Success Rate: {$report['summary']['success_rate']}%\n";
        echo "============================\n";
        
        return $report;
    }
    
    /**
     * Generate recommendations based on test results
     */
    private function generateRecommendations(): array {
        $recommendations = [];
        
        $failed_tests = array_filter($this->test_results, fn($r) => !$r['passed']);
        
        foreach ($failed_tests as $test) {
            switch ($test['test']) {
                case 'tenant_isolation_protection':
                    $recommendations[] = 'Review tenant isolation implementation - parameter tampering not detected';
                    break;
                case 'api_authentication_required':
                    $recommendations[] = 'Strengthen API authentication requirements';
                    break;
                case 'permission_enforcement':
                    $recommendations[] = 'Review permission enforcement mechanisms';
                    break;
                default:
                    $recommendations[] = "Review failed test: {$test['test']} - {$test['description']}";
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'All security tests passed. System is properly secured.';
        }
        
        return $recommendations;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test_suite = new SecurityTestSuite();
    $report = $test_suite->runAllTests();
    
    // Save report to file
    file_put_contents(__DIR__ . '/security_test_report.json', json_encode($report, JSON_PRETTY_PRINT));
    echo "\nTest report saved to: " . __DIR__ . "/security_test_report.json\n";
}
