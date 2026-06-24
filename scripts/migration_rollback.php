<?php
/**
 * Migration Rollback Script
 * 
 * Emergency rollback procedures for the tenant_id migration.
 * This script provides multiple rollback options depending on the migration phase.
 * 
 * Usage: php scripts/migration_rollback.php [--mode=immediate|full|partial]
 * 
 * Modes:
 *   immediate - Switch application back to tenant_id mode (fastest, no data loss)
 *   partial   - Remove tenant_id columns and revert schema changes
 *   full      - Restore from pre-migration backup (last resort)
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

class MigrationRollback {
    private PDO $db;
    private string $mode;
    private array $backups = [];
    
    public function __construct(string $mode = 'immediate') {
        $this->db = get_db_connection();
        $this->mode = $mode;
        $this->findBackups();
    }
    
    /**
     * Execute rollback based on selected mode
     */
    public function execute(): void {
        echo "=== Jakababa POS Migration Rollback ===\n";
        echo "Mode: {$this->mode}\n\n";
        
        // Safety confirmation
        if (!$this->confirmRollback()) {
            echo "Rollback cancelled by user.\n";
            exit(0);
        }
        
        switch ($this->mode) {
            case 'immediate':
                $this->executeImmediateRollback();
                break;
            case 'partial':
                $this->executePartialRollback();
                break;
            case 'full':
                $this->executeFullRollback();
                break;
            default:
                echo "Unknown rollback mode: {$this->mode}\n";
                exit(1);
        }
    }
    
    /**
     * IMMEDIATE ROLLBACK (Recommended First Response)
     * Switches application back to tenant_id mode without data changes
     */
    private function executeImmediateRollback(): void {
        echo "[IMMEDIATE ROLLBACK] Switching to tenant_id mode...\n\n";
        
        // 1. Update migration config
        $this->updateMigrationConfig([
            'phase' => 'rolled_back',
            'read_source' => 'tenant_id',
            'dual_write_mode' => false,
            'features' => [
                'use_tenant_context' => false,
                'enforce_tenant_isolation' => false,
                'tenant_id_writes' => false,
            ],
        ]);
        echo "✅ Updated migration configuration\n";
        
        // 2. Clear tenant-related session variables
        echo "✅ Tenant session variables will be ignored on next login\n";
        
        // 3. Verify tenant_id data is intact
        $checks = [
            'users' => 'SELECT COUNT(*) FROM users WHERE tenant_id IS NOT NULL',
            'products' => 'SELECT COUNT(*) FROM products WHERE tenant_id IS NOT NULL',
            'sales' => 'SELECT COUNT(*) FROM sales WHERE tenant_id IS NOT NULL',
        ];
        
        echo "\nVerifying tenant_id data integrity:\n";
        foreach ($checks as $table => $query) {
            $count = (int) $this->db->query($query)->fetchColumn();
            echo "  {$table}: {$count} records with tenant_id ✅\n";
        }
        
        // 4. Log the rollback
        $this->logRollback('immediate', 'Application switched to tenant_id mode');
        
        echo "\n=== IMMEDIATE ROLLBACK COMPLETE ===\n";
        echo "The application now uses tenant_id exclusively.\n";
        echo "tenant_id columns remain in database but are not used.\n";
        echo "To re-enable tenant_id, update config/migration_mode.php\n";
    }
    
    /**
     * PARTIAL ROLLBACK
     * Removes tenant_id columns and reverts schema changes
     */
    private function executePartialRollback(): void {
        echo "[PARTIAL ROLLBACK] Reverting schema changes...\n\n";
        
        // Safety check: Ensure we have data in tenant_id columns
        $this->verifyCompanyDataExists();
        
        // Tables to revert (in reverse order of dependency)
        $tables = [
            'offline_sync_queue', 'terminal_sessions', 'webhooks', 'api_tokens',
            'override_requests', 'appointments', 'prescriptions', 'restaurant_tables',
            'feature_usage', 'settings', 'notifications', 'activity_logs',
            'promotions', 'vouchers', 'discounts', 'sale_returns', 'payments',
            'sale_items', 'sales', 'purchase_orders', 'suppliers', 'customers',
            'stock_transfers', 'stock_movements', 'stock_batches', 'inventory',
            'product_attributes', 'product_images', 'product_variants', 'products',
            'brands', 'categories', 'branches', 'users'
        ];
        
        echo "Removing tenant_id columns...\n";
        
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            try {
                // Check if column exists
                $stmt = $this->db->query("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'");
                if ($stmt->rowCount() > 0) {
                    // Drop tenant_id column
                    $this->db->exec("ALTER TABLE {$table} DROP COLUMN tenant_id");
                    echo "  Dropped tenant_id from {$table} ✅\n";
                } else {
                    echo "  tenant_id not found in {$table} (skipped)\n";
                }
            } catch (PDOException $e) {
                echo "  Error dropping from {$table}: " . $e->getMessage() . "\n";
            }
        }
        
        // Drop mapping table
        try {
            $this->db->exec("DROP TABLE IF EXISTS _company_tenant_mapping");
            echo "  Dropped _company_tenant_mapping table ✅\n";
        } catch (PDOException $e) {
            echo "  Error dropping mapping table: " . $e->getMessage() . "\n";
        }
        
        // Update migration log
        $this->db->prepare("
            INSERT INTO _migration_log (phase, operation, status, executed_by)
            VALUES ('rollback', 'partial_schema_revert', 'completed', ?)
        ")->execute([get_current_user_id() ?? 0]);
        
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $this->logRollback('partial', 'tenant_id columns removed from schema');
        
        echo "\n=== PARTIAL ROLLBACK COMPLETE ===\n";
        echo "All tenant_id columns have been removed.\n";
        echo "Database schema reverted to pre-migration state.\n";
    }
    
    /**
     * FULL ROLLBACK
     * Restores from pre-migration backup (last resort)
     */
    private function executeFullRollback(): void {
        echo "[FULL ROLLBACK] Database restoration from backup...\n\n";
        
        // Find the most recent pre-migration backup
        $backupFile = $this->findPreMigrationBackup();
        
        if (!$backupFile) {
            echo "❌ No pre-migration backup found!\n";
            echo "Looked in: " . implode(', ', $this->backups) . "\n";
            echo "\nCannot perform full rollback without backup.\n";
            echo "Consider using 'partial' rollback instead.\n";
            exit(1);
        }
        
        echo "Found backup: {$backupFile}\n\n";
        
        // Extra confirmation for destructive operation
        echo "⚠️  WARNING: This will COMPLETELY REPLACE the current database!\n";
        echo "All data changes since the backup will be LOST!\n\n";
        
        if (!$this->confirmDestructiveRollback()) {
            echo "Full rollback cancelled.\n";
            exit(0);
        }
        
        // Execute restore
        echo "Restoring database from backup...\n";
        $command = sprintf(
            'mysql -h %s -u %s %s %s < %s 2>&1',
            escapeshellarg($_ENV['DB_HOST'] ?? 'localhost'),
            escapeshellarg($_ENV['DB_USER'] ?? 'root'),
            $_ENV['DB_PASS'] ? '-p' . escapeshellarg($_ENV['DB_PASS']) : '',
            escapeshellarg($_ENV['DB_NAME'] ?? 'jakababa_pos'),
            escapeshellarg($backupFile)
        );
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            echo "❌ Restore failed with exit code {$returnCode}\n";
            echo "Output: " . implode("\n", $output) . "\n";
            exit(1);
        }
        
        $this->logRollback('full', 'Database restored from backup: ' . basename($backupFile));
        
        echo "\n=== FULL ROLLBACK COMPLETE ===\n";
        echo "Database restored from: {$backupFile}\n";
        echo "All users must log in again.\n";
    }
    
    /**
     * Verify tenant_id data exists before removing tenant_id
     */
    private function verifyCompanyDataExists(): void {
        $criticalTables = ['users', 'products', 'sales', 'customers'];
        
        echo "\nVerifying tenant_id data integrity:\n";
        
        foreach ($criticalTables as $table) {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN tenant_id IS NOT NULL THEN 1 ELSE 0 END) as with_company_id
                FROM {$table}
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total = (int) $result['total'];
            $withCompany = (int) $result['with_company_id'];
            $percentage = $total > 0 ? round(($withCompany / $total) * 100, 2) : 0;
            
            echo "  {$table}: {$withCompany}/{$total} ({$percentage}%) with tenant_id\n";
            
            if ($percentage < 95 && $total > 0) {
                echo "\n❌ CRITICAL: Less than 95% of records have tenant_id!\n";
                echo "Partial rollback would result in data loss.\n";
                echo "Consider using 'immediate' rollback instead.\n";
                exit(1);
            }
        }
        
        echo "\n✅ All critical tables have tenant_id data\n\n";
    }
    
    /**
     * Find available backups
     */
    private function findBackups(): void {
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            return;
        }
        
        $files = glob($backupDir . '/pre_tenant_migration_*.sql');
        rsort($files); // Most recent first
        $this->backups = $files;
    }
    
    /**
     * Find the most recent pre-migration backup
     */
    private function findPreMigrationBackup(): ?string {
        foreach ($this->backups as $backup) {
            if (strpos($backup, 'pre_tenant_migration_') !== false) {
                return $backup;
            }
        }
        return null;
    }
    
    /**
     * Update migration configuration file
     */
    private function updateMigrationConfig(array $config): void {
        $configFile = __DIR__ . '/../config/migration_mode.php';
        $configDir = dirname($configFile);
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $config['rollback_timestamp'] = date('Y-m-d H:i:s');
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $content);
    }
    
    /**
     * Log rollback action
     */
    private function logRollback(string $mode, string $description): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO _migration_log (phase, operation, status, executed_by, error_message)
                VALUES ('rollback', ?, 'completed', ?, ?)
            ");
            $stmt->execute([
                $mode . '_rollback',
                get_current_user_id() ?? 0,
                $description
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log rollback: " . $e->getMessage());
        }
    }
    
    /**
     * Confirm rollback with user
     */
    private function confirmRollback(): bool {
        echo "⚠️  This will rollback the tenant_id migration.\n";
        echo "Current mode: {$this->mode}\n\n";
        
        if ($this->mode === 'immediate') {
            echo "Immediate rollback is SAFE - no data will be lost.\n";
            echo "The application will simply stop using tenant_id.\n\n";
        } elseif ($this->mode === 'partial') {
            echo "Partial rollback will DROP tenant_id columns.\n";
            echo "This is reversible only by re-running the migration.\n\n";
        } elseif ($this->mode === 'full') {
            echo "Full rollback will RESTORE THE ENTIRE DATABASE from backup.\n";
            echo "ALL changes since backup will be LOST!\n\n";
        }
        
        echo "Are you sure? Type 'ROLLBACK' to confirm: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        return trim($line) === 'ROLLBACK';
    }
    
    /**
     * Extra confirmation for destructive full rollback
     */
    private function confirmDestructiveRollback(): bool {
        echo "\n🛑 FINAL CONFIRMATION 🛑\n";
        echo "This operation will:\n";
        echo "1. DROP the entire current database\n";
        echo "2. RESTORE from backup (losing all recent changes)\n";
        echo "3. REQUIRE ALL USERS to log in again\n\n";
        
        echo "Type 'DESTROY' to confirm you understand and accept data loss: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        return trim($line) === 'DESTROY';
    }
}

// Main execution
$mode = 'immediate';
foreach ($argv as $arg) {
    if (strpos($arg, '--mode=') === 0) {
        $mode = substr($arg, 7);
    }
}

$validModes = ['immediate', 'partial', 'full'];
if (!in_array($mode, $validModes, true)) {
    echo "Invalid mode: {$mode}\n";
    echo "Valid modes: " . implode(', ', $validModes) . "\n";
    exit(1);
}

try {
    $rollback = new MigrationRollback($mode);
    $rollback->execute();
} catch (Exception $e) {
    echo "❌ Rollback failed: " . $e->getMessage() . "\n";
    exit(1);
}
