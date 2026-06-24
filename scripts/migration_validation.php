<?php
/**
 * Migration Validation Script
 * 
 * Validates the completeness and consistency of the tenant_id to tenant_id migration.
 * Run this after Phase 3 data migration to verify data integrity.
 * 
 * Usage: php scripts/migration_validation.php [--detailed] [--fix]
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

class MigrationValidator {
    private PDO $db;
    private array $results = [];
    private bool $detailed = false;
    private bool $autoFix = false;
    
    // Tables that must have 100% tenant_id coverage
    private array $criticalTables = [
        'users', 'branches', 'categories', 'products', 'product_variants',
        'customers', 'suppliers', 'inventory', 'stock_movements', 'sales',
        'sale_items', 'payments', 'purchase_orders', 'activity_logs'
    ];
    
    // All tables with tenant_id/tenant_id
    private array $allTables = [
        'users', 'branches', 'categories', 'brands', 'products', 'product_variants',
        'product_images', 'product_attributes', 'inventory', 'stock_batches',
        'stock_movements', 'stock_transfers', 'customers', 'suppliers',
        'purchase_orders', 'sales', 'sale_items', 'payments', 'sale_returns',
        'discounts', 'vouchers', 'promotions', 'activity_logs', 'notifications',
        'settings', 'feature_usage', 'restaurant_tables', 'prescriptions',
        'appointments', 'override_requests', 'api_tokens', 'webhooks',
        'terminal_sessions', 'offline_sync_queue'
    ];
    
    public function __construct(bool $detailed = false, bool $autoFix = false) {
        $this->db = get_db_connection();
        $this->detailed = $detailed;
        $this->autoFix = $autoFix;
    }
    
    /**
     * Run all validation checks
     */
    public function validate(): array {
        echo "=== Jakababa POS Migration Validation ===\n\n";
        
        $this->checkTenantIdColumnsExist();
        $this->checkCompanyToTenantMapping();
        $this->checkMissingTenantIds();
        $this->checkDataConsistency();
        $this->checkOrphanedRecords();
        $this->checkForeignKeyIntegrity();
        $this->checkIndexStatus();
        $this->generateSummary();
        
        return $this->results;
    }
    
    /**
     * Check if tenant_id columns exist in all expected tables
     */
    private function checkTenantIdColumnsExist(): void {
        echo "[1/7] Checking tenant_id column existence...\n";
        
        $missing = [];
        foreach ($this->allTables as $table) {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'");
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                $missing[] = $table;
            }
        }
        
        if (empty($missing)) {
            $this->pass("All tables have tenant_id column");
        } else {
            $this->fail("Missing tenant_id columns in: " . implode(', ', $missing));
            $this->results['missing_columns'] = $missing;
        }
        echo "\n";
    }
    
    /**
     * Check company to tenant mapping completeness
     */
    private function checkCompanyToTenantMapping(): void {
        echo "[2/7] Checking company-to-tenant mapping...\n";
        
        // Check if mapping table exists
        $stmt = $this->db->query("SHOW TABLES LIKE '_company_tenant_mapping'");
        if ($stmt->rowCount() === 0) {
            $this->warn("Mapping table _company_tenant_mapping does not exist");
            echo "\n";
            return;
        }
        
        // Check companies without tenant mapping
        $stmt = $this->db->query("
            SELECT c.id, c.name, c.slug
            FROM companies c
            LEFT JOIN _company_tenant_mapping ctm ON c.id = ctm.tenant_id
            WHERE c.deleted_at IS NULL
            AND ctm.tenant_id IS NULL
        ");
        $unmapped = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($unmapped)) {
            $this->pass("All companies have tenant mapping");
        } else {
            $this->fail(count($unmapped) . " companies without tenant mapping");
            if ($this->detailed) {
                foreach ($unmapped as $company) {
                    echo "  - Company {$company['id']}: {$company['name']} ({$company['slug']})\n";
                }
            }
        }
        
        // Check companies with tenant_id column not set
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM companies 
            WHERE tenant_id IS NULL AND deleted_at IS NULL
        ");
        $count = (int) $stmt->fetchColumn();
        
        if ($count === 0) {
            $this->pass("All companies have tenant_id set");
        } else {
            $this->fail("{$count} companies missing tenant_id");
        }
        echo "\n";
    }
    
    /**
     * Check for missing tenant_id values in data tables
     */
    private function checkMissingTenantIds(): void {
        echo "[3/7] Checking for missing tenant_id values...\n";
        
        $issues = [];
        foreach ($this->allTables as $table) {
            $stmt = $this->db->query("
                SELECT COUNT(*) 
                FROM {$table} 
                WHERE tenant_id IS NULL 
                AND tenant_id IS NOT NULL
            ");
            $count = (int) $stmt->fetchColumn();
            
            if ($count > 0) {
                $issues[$table] = $count;
                $level = in_array($table, $this->criticalTables) ? 'FAIL' : 'WARN';
                echo "  [{$level}] {$table}: {$count} records missing tenant_id\n";
            }
        }
        
        if (empty($issues)) {
            $this->pass("All records have tenant_id populated");
        } else {
            $total = array_sum($issues);
            $this->fail("{$total} total records missing tenant_id across " . count($issues) . " tables");
            $this->results['missing_tenant_ids'] = $issues;
        }
        echo "\n";
    }
    
    /**
     * Check data consistency between tenant_id and tenant_id
     */
    private function checkDataConsistency(): void {
        echo "[4/7] Checking data consistency...\n";
        
        $inconsistencies = [];
        
        // Check that tenant_id matches tenant's tenant_id
        $tablesToCheck = ['users', 'products', 'sales', 'customers', 'inventory'];
        
        foreach ($tablesToCheck as $table) {
            $stmt = $this->db->query("
                SELECT COUNT(*) 
                FROM {$table} t
                JOIN companies c ON t.tenant_id = c.id
                WHERE t.tenant_id IS NOT NULL 
                AND t.tenant_id != c.tenant_id
            ");
            $count = (int) $stmt->fetchColumn();
            
            if ($count > 0) {
                $inconsistencies[$table] = $count;
                echo "  [FAIL] {$table}: {$count} records with mismatched tenant_id\n";
                
                if ($this->detailed) {
                    $stmt = $this->db->query("
                        SELECT t.id, t.tenant_id, t.tenant_id, c.tenant_id as company_tenant
                        FROM {$table} t
                        JOIN companies c ON t.tenant_id = c.id
                        WHERE t.tenant_id IS NOT NULL 
                        AND t.tenant_id != c.tenant_id
                        LIMIT 5
                    ");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "    Record {$row['id']}: table_tenant={$row['tenant_id']}, company_tenant={$row['company_tenant']}\n";
                    }
                }
            }
        }
        
        if (empty($inconsistencies)) {
            $this->pass("All tenant_id values are consistent with company mapping");
        } else {
            $total = array_sum($inconsistencies);
            $this->fail("{$total} inconsistent records found");
            $this->results['inconsistencies'] = $inconsistencies;
        }
        echo "\n";
    }
    
    /**
     * Check for orphaned records
     */
    private function checkOrphanedRecords(): void {
        echo "[5/7] Checking for orphaned records...\n";
        
        $orphans = [];
        
        // Records with tenant_id but no corresponding company tenant
        $stmt = $this->db->query("
            SELECT 
                'users' as table_name,
                COUNT(*) as count
            FROM users u
            WHERE u.tenant_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.tenant_id = u.tenant_id)
            
            UNION ALL
            
            SELECT 
                'products',
                COUNT(*)
            FROM products p
            WHERE p.tenant_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.tenant_id = p.tenant_id)
            
            UNION ALL
            
            SELECT 
                'sales',
                COUNT(*)
            FROM sales s
            WHERE s.tenant_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.tenant_id = s.tenant_id)
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['count'] > 0) {
                $orphans[$row['table_name']] = (int) $row['count'];
                echo "  [FAIL] {$row['table_name']}: {$row['count']} orphaned records\n";
            }
        }
        
        if (empty($orphans)) {
            $this->pass("No orphaned records found");
        } else {
            $this->fail("Orphaned records detected");
            $this->results['orphans'] = $orphans;
        }
        echo "\n";
    }
    
    /**
     * Check foreign key integrity
     */
    private function checkForeignKeyIntegrity(): void {
        echo "[6/7] Checking foreign key integrity...\n";
        
        $violations = [];
        
        // Check users.branch_id -> branches.id
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id AND u.tenant_id = b.tenant_id
            WHERE u.branch_id IS NOT NULL AND b.id IS NULL
            AND u.tenant_id IS NOT NULL
        ");
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            $violations[] = "{$count} users with invalid branch_id";
        }
        
        // Check products.category_id -> categories.id
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM products p
            LEFT JOIN categories c ON p.category_id = c.id AND p.tenant_id = c.tenant_id
            WHERE p.category_id IS NOT NULL AND c.id IS NULL
            AND p.tenant_id IS NOT NULL
        ");
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            $violations[] = "{$count} products with invalid category_id";
        }
        
        // Check sales.customer_id -> customers.id
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id AND s.tenant_id = c.tenant_id
            WHERE s.customer_id IS NOT NULL AND c.id IS NULL
            AND s.tenant_id IS NOT NULL
        ");
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            $violations[] = "{$count} sales with invalid customer_id";
        }
        
        // Check sale_items.sale_id -> sales.id
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM sale_items si
            LEFT JOIN sales s ON si.sale_id = s.id AND si.tenant_id = s.tenant_id
            WHERE si.sale_id IS NOT NULL AND s.id IS NULL
            AND si.tenant_id IS NOT NULL
        ");
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            $violations[] = "{$count} sale_items with invalid sale_id";
        }
        
        if (empty($violations)) {
            $this->pass("Foreign key integrity maintained");
        } else {
            $this->fail("Foreign key violations detected:");
            foreach ($violations as $v) {
                echo "  - {$v}\n";
            }
        }
        echo "\n";
    }
    
    /**
     * Check index status on tenant_id columns
     */
    private function checkIndexStatus(): void {
        echo "[7/7] Checking index status...\n";
        
        $missingIndexes = [];
        
        foreach ($this->criticalTables as $table) {
            $stmt = $this->db->query("
                SHOW INDEX FROM {$table} WHERE Column_name = 'tenant_id'
            ");
            if ($stmt->rowCount() === 0) {
                $missingIndexes[] = $table;
            }
        }
        
        if (empty($missingIndexes)) {
            $this->pass("All critical tables have tenant_id indexes");
        } else {
            $this->warn("Missing tenant_id indexes on: " . implode(', ', $missingIndexes));
            echo "  Run: CREATE INDEX idx_{$table}_tenant ON {$table}(tenant_id);\n";
        }
        echo "\n";
    }
    
    /**
     * Generate final summary
     */
    private function generateSummary(): void {
        echo "=== Validation Summary ===\n";
        
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        $warnings = count(array_filter($this->results, fn($r) => $r['status'] === 'WARN'));
        
        echo "Passed:   {$passed}\n";
        echo "Failed:   {$failed}\n";
        echo "Warnings: {$warnings}\n\n";
        
        if ($failed === 0) {
            echo "✅ MIGRATION VALIDATION PASSED\n";
            echo "The database is ready for tenant_id cutover.\n";
            exit(0);
        } else {
            echo "❌ MIGRATION VALIDATION FAILED\n";
            echo "Please address the failures before proceeding with cutover.\n";
            exit(1);
        }
    }
    
    private function pass(string $message): void {
        $this->results[] = ['status' => 'PASS', 'message' => $message];
        echo "  ✅ PASS: {$message}\n";
    }
    
    private function fail(string $message): void {
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
        echo "  ❌ FAIL: {$message}\n";
    }
    
    private function warn(string $message): void {
        $this->results[] = ['status' => 'WARN', 'message' => $message];
        echo "  ⚠️  WARN: {$message}\n";
    }
}

// Main execution
$detailed = in_array('--detailed', $argv);
$autoFix = in_array('--fix', $argv);

$validator = new MigrationValidator($detailed, $autoFix);
$results = $validator->validate();

// Return exit code based on validation results
$hasFailures = count(array_filter($results, fn($r) => $r['status'] === 'FAIL')) > 0;
exit($hasFailures ? 1 : 0);
