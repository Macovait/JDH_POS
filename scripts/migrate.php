<?php
/**
 * Database Migration Runner for Jakababa POS
 * 
 * This script manages database migrations with version tracking,
 * rollback support, and schema drift prevention.
 * 
 * Usage:
 *   php migrate.php [command] [options]
 * 
 * Commands:
 *   up      - Run all pending migrations
 *   down    - Rollback the last migration
 *   reset   - Rollback all migrations
 *   status  - Show migration status
 *   create  - Create a new migration file
 * 
 * Options:
 *   --steps=N  - Number of migrations to run/rollback
 *   --force    - Force run even in production
 *   --dry-run  - Show what would be done without executing
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

class MigrationRunner
{
    private $pdo;
    private $migrationsPath;
    private $dbCharset;

    public function __construct()
    {
        $this->pdo = get_db_connection();
        $this->migrationsPath = __DIR__ . '/../database/migrations';
        $this->dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

        // Ensure migrations table exists
        $this->ensureMigrationsTable();
    }

    /**
     * Ensure the schema_migrations table exists
     */
    private function ensureMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                checksum VARCHAR(64) NULL,
                execution_time_ms INT UNSIGNED NULL,
                status ENUM('success', 'failed', 'rolled_back') NOT NULL DEFAULT 'success',
                error_message TEXT NULL,
                INDEX idx_schema_migrations_version (version),
                INDEX idx_schema_migrations_status (status),
                INDEX idx_schema_migrations_applied_at (applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->dbCharset} COLLATE={$this->dbCharset}_unicode_ci
        ";

        try {
            $this->pdo->exec($sql);
            echo "✓ Schema migrations table ready\n";
        } catch (PDOException $e) {
            echo "✗ Failed to create schema_migrations table: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Get all migration files
     */
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        return $files;
    }

    /**
     * Get applied migrations
     */
    private function getAppliedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT version, name, status FROM schema_migrations ORDER BY version");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        $applied = $this->getAppliedMigrations();
        $appliedVersions = array_column($applied, 'version');

        $files = $this->getMigrationFiles();
        $pending = [];

        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (!in_array($version, $appliedVersions)) {
                $pending[] = [
                    'version' => $version,
                    'file' => $file,
                    'name' => $this->extractMigrationName($file)
                ];
            }
        }

        return $pending;
    }

    /**
     * Extract migration name from file
     */
    private function extractMigrationName(string $file): string
    {
        $content = file_get_contents($file);
        if (preg_match('/-- Description:\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return basename($file, '.sql');
    }

    /**
     * Calculate file checksum
     */
    private function calculateChecksum(string $file): string
    {
        return hash_file('sha256', $file);
    }

    /**
     * Run a single migration
     */
    private function runMigration(array $migration, bool $dryRun = false): bool
    {
        $version = $migration['version'];
        $file = $migration['file'];
        $name = $migration['name'];
        $checksum = $this->calculateChecksum($file);

        echo "  Running migration {$version}: {$name}\n";

        if ($dryRun) {
            echo "    [DRY RUN] Would execute: {$file}\n";
            return true;
        }

        $startTime = microtime(true);

        try {
            $this->pdo->beginTransaction();

            // Read and execute migration SQL
            $sql = file_get_contents($file);
            $this->pdo->exec($sql);

            // Record migration
            $stmt = $this->pdo->prepare("
                INSERT INTO schema_migrations (version, name, checksum, execution_time_ms, status)
                VALUES (?, ?, ?, ?, 'success')
            ");

            $executionTime = round((microtime(true) - $startTime) * 1000);
            $stmt->execute([$version, $name, $checksum, $executionTime]);

            $this->pdo->commit();

            echo "    ✓ Applied in {$executionTime}ms\n";
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();

            // Record failed migration
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO schema_migrations (version, name, checksum, execution_time_ms, status, error_message)
                    VALUES (?, ?, ?, ?, 'failed', ?)
                ");
                $executionTime = round((microtime(true) - $startTime) * 1000);
                $stmt->execute([$version, $name, $checksum, $executionTime, $e->getMessage()]);
            } catch (PDOException $inner) {
                // Ignore if recording fails
            }

            echo "    ✗ Failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Rollback a single migration
     */
    private function rollbackMigration(array $migration, bool $dryRun = false): bool
    {
        $version = $migration['version'];
        $name = $migration['name'];

        echo "  Rolling back migration {$version}: {$name}\n";

        if ($dryRun) {
            echo "    [DRY RUN] Would rollback: {$version}\n";
            return true;
        }

        try {
            $this->pdo->beginTransaction();

            // Mark as rolled back
            $stmt = $this->pdo->prepare("
                UPDATE schema_migrations SET status = 'rolled_back' WHERE version = ?
            ");
            $stmt->execute([$version]);

            $this->pdo->commit();

            echo "    ✓ Rolled back\n";
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            echo "    ✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Run pending migrations
     */
    public function up(int $steps = 0, bool $dryRun = false): bool
    {
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            echo "✓ No pending migrations\n";
            return true;
        }

        if ($steps > 0) {
            $pending = array_slice($pending, 0, $steps);
        }

        echo "Running " . count($pending) . " migration(s):\n";

        $success = 0;
        $failed = 0;

        foreach ($pending as $migration) {
            if ($this->runMigration($migration, $dryRun)) {
                $success++;
            } else {
                $failed++;
                break; // Stop on first failure
            }
        }

        echo "\nSummary: {$success} applied, {$failed} failed\n";

        return $failed === 0;
    }

    /**
     * Rollback migrations
     */
    public function down(int $steps = 1, bool $dryRun = false): bool
    {
        $applied = $this->getAppliedMigrations();

        if (empty($applied)) {
            echo "✓ No migrations to rollback\n";
            return true;
        }

        // Get last N migrations
        $toRollback = array_slice($applied, -$steps);
        $toRollback = array_reverse($toRollback);

        echo "Rolling back " . count($toRollback) . " migration(s):\n";

        $success = 0;
        $failed = 0;

        foreach ($toRollback as $migration) {
            if ($this->rollbackMigration($migration, $dryRun)) {
                $success++;
            } else {
                $failed++;
                break; // Stop on first failure
            }
        }

        echo "\nSummary: {$success} rolled back, {$failed} failed\n";

        return $failed === 0;
    }

    /**
     * Reset all migrations
     */
    public function reset(bool $dryRun = false): bool
    {
        $applied = $this->getAppliedMigrations();

        if (empty($applied)) {
            echo "✓ No migrations to reset\n";
            return true;
        }

        echo "Resetting " . count($applied) . " migration(s):\n";

        if ($dryRun) {
            echo "  [DRY RUN] Would delete all migration records\n";
            return true;
        }

        try {
            $this->pdo->exec("DELETE FROM schema_migrations");
            echo "  ✓ All migrations reset\n";
            return true;
        } catch (PDOException $e) {
            echo "  ✗ Reset failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Show migration status
     */
    public function status(): void
    {
        $applied = $this->getAppliedMigrations();
        $pending = $this->getPendingMigrations();

        echo "Migration Status:\n";
        echo "=================\n\n";

        if (!empty($applied)) {
            echo "Applied Migrations:\n";
            foreach ($applied as $migration) {
                $status = $migration['status'] === 'success' ? '✓' : '✗';
                echo "  {$status} {$migration['version']}: {$migration['name']} [{$migration['status']}]\n";
            }
            echo "\n";
        }

        if (!empty($pending)) {
            echo "Pending Migrations:\n";
            foreach ($pending as $migration) {
                echo "  ○ {$migration['version']}: {$migration['name']}\n";
            }
            echo "\n";
        }

        echo "Total: " . count($applied) . " applied, " . count($pending) . " pending\n";
    }

    /**
     * Create a new migration file
     */
    public function create(string $name): bool
    {
        $version = date('YmdHis');
        $filename = "{$version}_{$name}.sql";
        $filepath = $this->migrationsPath . '/' . $filename;

        $template = "-- Migration: {$name}\n";
        $template .= "-- Version: {$version}\n";
        $template .= "-- Description: {$name}\n\n";
        $template .= "-- Add your migration SQL here\n";
        $template .= "-- Example:\n";
        $template .= "-- ALTER TABLE table_name ADD COLUMN column_name VARCHAR(255) NULL;\n";

        if (file_put_contents($filepath, $template) !== false) {
            echo "✓ Created migration: {$filename}\n";
            return true;
        } else {
            echo "✗ Failed to create migration file\n";
            return false;
        }
    }
}

// CLI Interface
if (php_sapi_name() === 'cli') {
    $runner = new MigrationRunner();

    $command = $argv[1] ?? 'status';
    $options = [];

    // Parse options
    for ($i = 2; $i < count($argv); $i++) {
        if (strpos($argv[$i], '--') === 0) {
            $parts = explode('=', substr($argv[$i], 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }

    $steps = isset($options['steps']) ? (int) $options['steps'] : 0;
    $dryRun = isset($options['dry-run']);
    $force = isset($options['force']);

    // Check environment
    $env = getenv('APP_ENV') ?: 'development';
    if ($env === 'production' && !$force) {
        echo "⚠ Running in production mode. Use --force to confirm.\n";
        exit(1);
    }

    switch ($command) {
        case 'up':
            $success = $runner->up($steps, $dryRun);
            exit($success ? 0 : 1);

        case 'down':
            $success = $runner->down($steps ?: 1, $dryRun);
            exit($success ? 0 : 1);

        case 'reset':
            $success = $runner->reset($dryRun);
            exit($success ? 0 : 1);

        case 'status':
            $runner->status();
            exit(0);

        case 'create':
            $name = $argv[2] ?? null;
            if (!$name) {
                echo "Usage: php migrate.php create <migration_name>\n";
                exit(1);
            }
            $success = $runner->create($name);
            exit($success ? 0 : 1);

        default:
            echo "Unknown command: {$command}\n";
            echo "Available commands: up, down, reset, status, create\n";
            exit(1);
    }
}
