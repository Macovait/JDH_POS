<?php
/**
 * Security Integration Script
 * Helps integrate the new security system into existing files
 * 
 * @package Jakababa\Scripts
 * @version 1.0.0
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../src/Security/SecurityBootstrap.php';

class SecurityIntegrator {
    private $base_path;
    private $processed_files = [];
    private $errors = [];
    
    public function __construct() {
        $this->base_path = __DIR__ . '/..';
    }
    
    /**
     * Run complete security integration
     */
    public function integrate(): void {
        echo "Starting Security Integration...\n";
        echo "================================\n";
        
        // Initialize security system
        SecurityBootstrap::initialize();
        
        // Process different file types
        $this->processPhpFiles();
        $this->processApiFiles();
        $this->processAjaxFiles();
        $this->createSecurityFunctions();
        $this->runSecurityTests();
        
        // Generate integration report
        $this->generateReport();
        
        echo "\nIntegration completed!\n";
    }
    
    /**
     * Process PHP files for security integration
     */
    private function processPhpFiles(): void {
        echo "\nProcessing PHP files...\n";
        
        $directories = [
            $this->base_path . '/public',
            $this->base_path . '/api'
        ];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) continue;
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $this->processPhpFile($file->getPathname());
                }
            }
        }
    }
    
    /**
     * Process individual PHP file
     */
    private function processPhpFile(string $file_path): void {
        try {
            $content = file_get_contents($file_path);
            $original_content = $content;
            
            // Skip if already integrated
            if (strpos($content, 'SecurityBootstrap::initialize()') !== false) {
                return;
            }
            
            // Skip certain files
            $skip_patterns = [
                '/Security/',
                '/tests/',
                '/vendor/',
                'bootstrap.php'
            ];
            
            foreach ($skip_patterns as $pattern) {
                if (strpos($file_path, $pattern) !== false) {
                    return;
                }
            }
            
            $modified = false;
            
            // Add security bootstrap after existing requires
            if (preg_match('/(require_once.*?functions\.php.*?;)/s', $content, $matches)) {
                $security_require = "\nrequire_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';\n\n// Initialize comprehensive security system\nSecurityBootstrap::initialize();";
                
                // Adjust path based on file location
                $relative_path = $this->getRelativePath($file_path);
                $security_require = "\nrequire_once __DIR__ . '/{$relative_path}src/Security/SecurityBootstrap.php';\n\n// Initialize comprehensive security system\nSecurityBootstrap::initialize();";
                
                $content = str_replace($matches[0], $matches[0] . $security_require, $content);
                $modified = true;
            }
            
            // Replace old permission checks
            $content = $this->replacePermissionChecks($content);
            
            // Replace old database queries
            $content = $this->replaceDatabaseQueries($content);
            
            // Add CSRF protection to forms
            $content = $this->addCsrfProtection($content);
            
            // Add tenant isolation
            $content = $this->addTenantIsolation($content);
            
            if ($modified || $content !== $original_content) {
                file_put_contents($file_path, $content);
                $this->processed_files[] = $file_path;
                echo "  ✓ Processed: " . basename($file_path) . "\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error processing {$file_path}: " . $e->getMessage();
            echo "  ✗ Error: " . basename($file_path) . " - " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Get relative path to security bootstrap
     */
    private function getRelativePath(string $file_path): string {
        $file_dir = dirname($file_path);
        $base_dir = realpath($this->base_path . '/public');
        
        if (strpos($file_dir, $base_dir) === 0) {
            $depth = substr_count(str_replace($base_dir, '', $file_dir), '/');
            return str_repeat('../', $depth);
        }
        
        return '../../';
    }
    
    /**
     * Replace old permission checks
     */
    private function replacePermissionChecks(string $content): string {
        // Replace check_permission calls
        $content = preg_replace(
            '/check_permission\([\'"]([^\'"]+)[\'"]\)/',
            'has_permission(\'$1\')',
            $content
        );
        
        // Replace old permission enforcement
        $content = preg_replace(
            '/if\s*\(\s*!check_permission\([\'"]([^\'"]+)[\'"]\)\s*\)\s*{\s*enforce_permission\([\'"]([^\'"]+)[\'"]\);\s*}/',
            'if (!has_permission(\'$1\')) { enforce_permission(\'$1\'); }',
            $content
        );
        
        return $content;
    }
    
    /**
     * Replace old database queries
     */
    private function replaceDatabaseQueries(string $content): string {
        // Replace SELECT queries
        $content = preg_replace(
            '/\$stmt\s*=\s*\$pdo->prepare\([\'"]SELECT\s+\*\s+FROM\s+(\w+)[\'"]\);\s*\$stmt->execute\(\[(.*?)\]\);/',
            '$1 = secure_db_select(\'$1\', []);',
            $content
        );
        
        // Replace INSERT queries
        $content = preg_replace(
            '/\$stmt\s*=\s*\$pdo->prepare\([\'"]INSERT\s+INTO\s+(\w+)[\'"].*?\);\s*\$stmt->execute\(\[(.*?)\]\);/',
            '$new_id = secure_db_insert(\'$1\', $data);',
            $content
        );
        
        // Replace UPDATE queries
        $content = preg_replace(
            '/\$stmt\s*=\s*\$pdo->prepare\([\'"]UPDATE\s+(\w+)[\'"].*?\);\s*\$stmt->execute\(\[(.*?)\]\);/',
            '$affected = secure_db_update(\'$1\', $data, $where);',
            $content
        );
        
        return $content;
    }
    
    /**
     * Add CSRF protection to forms
     */
    private function addCsrfProtection(string $content): string {
        // Add CSRF token to forms
        if (strpos($content, '<form') !== false && strpos($content, 'csrf_token') === false) {
            $content = preg_replace(
                '/(<form[^>]*>)/',
                '$1' . "\n        <input type=\"hidden\" name=\"csrf_token\" value=\"<?php echo htmlspecialchars(\$_SESSION['csrf_token'] ?? ''); ?>\">\n",
                $content
            );
        }
        
        return $content;
    }
    
    /**
     * Add tenant isolation
     */
    private function addTenantIsolation(string $content): string {
        // Add tenant isolation after require_login
        if (strpos($content, 'require_login()') !== false && strpos($content, 'validate_tenant_isolation()') === false) {
            $content = preg_replace(
                '/require_login\(\);/',
                "require_login();\n\n// Apply tenant isolation validation\nvalidate_tenant_isolation();",
                $content
            );
        }
        
        return $content;
    }
    
    /**
     * Process API files
     */
    private function processApiFiles(): void {
        echo "\nProcessing API files...\n";
        
        $api_dir = $this->base_path . '/api';
        if (!is_dir($api_dir)) return;
        
        $files = glob($api_dir . '/*.php');
        
        foreach ($files as $file) {
            $this->processApiFile($file);
        }
    }
    
    /**
     * Process individual API file
     */
    private function processApiFile(string $file_path): void {
        try {
            $content = file_get_contents($file_path);
            
            // Skip if already processed
            if (strpos($content, 'secure_api_endpoint') !== false) {
                return;
            }
            
            // Add API security
            $security_code = "<?php\nrequire_once __DIR__ . '/../src/Security/SecurityBootstrap.php';\n\n// Secure API endpoint\nsecure_api_endpoint('api.access');\n\n";
            
            if (strpos($content, '<?php') === 0) {
                $content = $security_code . substr($content, 5);
            } else {
                $content = $security_code . $content;
            }
            
            file_put_contents($file_path, $content);
            $this->processed_files[] = $file_path;
            echo "  ✓ Secured API: " . basename($file_path) . "\n";
            
        } catch (Exception $e) {
            $this->errors[] = "Error processing API {$file_path}: " . $e->getMessage();
        }
    }
    
    /**
     * Process AJAX files
     */
    private function processAjaxFiles(): void {
        echo "\nProcessing AJAX files...\n";
        
        $ajax_dir = $this->base_path . '/public/ajax';
        if (!is_dir($ajax_dir)) return;
        
        $files = glob($ajax_dir . '/*.php');
        
        foreach ($files as $file) {
            $this->processAjaxFile($file);
        }
    }
    
    /**
     * Process individual AJAX file
     */
    private function processAjaxFile(string $file_path): void {
        try {
            $content = file_get_contents($file_path);
            
            // Skip if already processed
            if (strpos($content, 'secure_ajax_endpoint') !== false) {
                return;
            }
            
            // Add AJAX security
            $security_code = "<?php\nrequire_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';\n\n// Secure AJAX endpoint\nsecure_ajax_endpoint('ajax.access');\n\n";
            
            if (strpos($content, '<?php') === 0) {
                $content = $security_code . substr($content, 5);
            } else {
                $content = $security_code . $content;
            }
            
            file_put_contents($file_path, $content);
            $this->processed_files[] = $file_path;
            echo "  ✓ Secured AJAX: " . basename($file_path) . "\n";
            
        } catch (Exception $e) {
            $this->errors[] = "Error processing AJAX {$file_path}: " . $e->getMessage();
        }
    }
    
    /**
     * Create security functions file
     */
    private function createSecurityFunctions(): void {
        echo "\nCreating security functions...\n";
        
        $functions_file = $this->base_path . '/src/secure_db_functions.php';
        
        if (!file_exists($functions_file)) {
            $content = '<?php
/**
 * Secure Database Functions - Tenant-Scoped Database Operations
 * All database operations automatically include tenant isolation
 */

require_once __DIR__ . "/SecurityBootstrap.php";

/**
 * Secure SELECT query
 */
function secure_db_select(string $table, array $where = [], array $columns = ["*"], string $order_by = "", int $limit = 0): array {
    $builder = new SecureQueryBuilder();
    return $builder->select($table, $columns, $where, [], $order_by, $limit);
}

/**
 * Secure INSERT query
 */
function secure_db_insert(string $table, array $data): int {
    $builder = new SecureQueryBuilder();
    return $builder->insert($table, $data);
}

/**
 * Secure UPDATE query
 */
function secure_db_update(string $table, array $data, array $where): int {
    $builder = new SecureQueryBuilder();
    return $builder->update($table, $data, $where);
}

/**
 * Secure DELETE query
 */
function secure_db_delete(string $table, array $where): int {
    $builder = new SecureQueryBuilder();
    return $builder->delete($table, $where);
}

/**
 * Secure COUNT query
 */
function secure_db_count(string $table, array $where = []): int {
    $result = secure_db_select($table, $where, ["COUNT(*) as count"]);
    return (int) ($result[0]["count"] ?? 0);
}

/**
 * Secure FIND by ID
 */
function secure_db_find(string $table, int $id): array {
    $result = secure_db_select($table, ["id" => $id]);
    return $result[0] ?? [];
}

/**
 * Secure EXISTS check
 */
function secure_db_exists(string $table, array $where): bool {
    $count = secure_db_count($table, $where);
    return $count > 0;
}

/**
 * Secure FETCH ALL
 */
function secure_db_fetch_all(string $table, array $where = [], array $columns = ["*"]): array {
    return secure_db_select($table, $where, $columns);
}
';
            
            file_put_contents($functions_file, $content);
            echo "  ✓ Created: secure_db_functions.php\n";
        }
    }
    
    /**
     * Run security tests
     */
    private function runSecurityTests(): void {
        echo "\nRunning security tests...\n";
        
        $test_file = $this->base_path . '/tests/SecurityTestSuite.php';
        
        if (file_exists($test_file)) {
            echo "  ✓ Security test suite available\n";
            echo "  Run: php tests/SecurityTestSuite.php\n";
        } else {
            echo "  ⚠ Security test suite not found\n";
        }
    }
    
    /**
     * Generate integration report
     */
    private function generateReport(): void {
        echo "\nGenerating integration report...\n";
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'processed_files' => count($this->processed_files),
            'errors' => count($this->errors),
            'files' => array_map('basename', $this->processed_files),
            'error_details' => $this->errors,
            'recommendations' => [
                'Review all processed files for proper security integration',
                'Run security tests to validate implementation',
                'Update database queries to use secure functions',
                'Test all forms and API endpoints',
                'Monitor audit logs for security events'
            ]
        ];
        
        $report_file = $this->base_path . '/security_integration_report.json';
        file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "  ✓ Report saved: security_integration_report.json\n";
        echo "\nIntegration Summary:\n";
        echo "  - Files processed: {$report['processed_files']}\n";
        echo "  - Errors: {$report['errors']}\n";
        
        if (!empty($this->errors)) {
            echo "\nErrors encountered:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }
    }
}

// Run integration if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $integrator = new SecurityIntegrator();
    $integrator->integrate();
}
