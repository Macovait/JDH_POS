<?php
/**
 * Database Backup CLI Script (SaaS Version)
 *
 * Usage: php scripts/backup_cli.php [--company N] [--compress] [--email] [--delete-old N] [--quiet]
 *
 * Options:
 *   --company N    Backup only company with ID N (omit for full backup)
 *   --compress     Compress the backup file (gzip)
 *   --email        Send backup via email (requires mail config)
 *   --delete-old N Delete backups older than N days
 *   --quiet        Suppress output (for cron jobs)
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------

// Define root if not already
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Load core files
require_once ROOT_PATH . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('logger.php', 'src', true);

// -----------------------------------------------------------------------------
// Command Line Options
// -----------------------------------------------------------------------------

$options = getopt('', ['company:', 'compress', 'email', 'delete-old:', 'quiet']);
$quiet = isset($options['quiet']);

// Validate that we're running from CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from command line.\n";
    exit(1);
}

// Permission check (if user session exists, but CLI likely doesn't have one)
if (isset($_SESSION['user_id'])) {
    if (!function_exists('check_permission') || !check_permission('backup.run')) {
        error_log("Unauthorized backup attempt by user: " . $_SESSION['user_id']);
        echo "Unauthorized. You don't have permission to run backups.\n";
        exit(1);
    }
}

// Get company filter
$companyId = isset($options['tenant']) ? (int) $options['tenant'] : null;

// -----------------------------------------------------------------------------
// Backup Class
// -----------------------------------------------------------------------------

class BackupManager
{
    private PDO $pdo;
    private string $backupDir;
    private string $filename;
    private bool $quiet;
    private ?int $companyId;
    private string $originalFile;

    public function __construct(?int $companyId, bool $quiet = false)
    {
        $this->pdo = get_db_connection();
        $this->companyId = $companyId;
        $this->quiet = $quiet;

        // Use tenant directory if company specified
        if ($this->companyId) {
            $this->backupDir = TENANT_PATH . DIRECTORY_SEPARATOR . $this->companyId . DIRECTORY_SEPARATOR . 'backups';
        } else {
            $this->backupDir = BACKUPS_PATH;
        }

        // Create directory if missing
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        if (!is_writable($this->backupDir)) {
            throw new RuntimeException("Backup directory is not writable: {$this->backupDir}");
        }

        $this->filename = $this->backupDir . DIRECTORY_SEPARATOR . $this->generateFilename();
    }

    /**
     * Generate a unique filename with timestamp
     */
    private function generateFilename(): string
    {
        $timestamp = date('Ymd_His');
        $prefix = $this->companyId ? "tenant_{$this->companyId}_" : "full_";
        return $prefix . "backup_{$timestamp}.sql";
    }

    /**
     * Run the backup
     */
    public function run(array $options): bool
    {
        try {
            // Step 1: Dump database
            $this->dumpDatabase();

            // Step 2: Compress if requested
            if (isset($options['compress'])) {
                $this->compress();
            }

            // Step 3: Log activity
            $this->logBackup();

            // Step 4: Send email if requested
            if (isset($options['email'])) {
                $this->sendEmail($options);
            }

            // Step 5: Delete old backups
            if (isset($options['delete-old'])) {
                $this->deleteOldBackups((int) $options['delete-old']);
            }

            // Step 6: Create latest symlink
            $this->createLatestSymlink();

            if (!$this->quiet) {
                $size = round(filesize($this->filename) / 1024 / 1024, 2);
                echo "\n✓ Backup completed successfully!\n";
                echo "  File: " . basename($this->filename) . "\n";
                echo "  Size: {$size} MB\n";
                echo "  Location: {$this->filename}\n";
            }

            return true;
        } catch (Exception $e) {
            error_log("Backup failed: " . $e->getMessage());
            if (!$this->quiet) {
                echo "Error: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }

    /**
     * Dump database using mysqldump
     */
    private function dumpDatabase(): void
    {
        if (!$this->quiet) {
            echo "Running backup...\n";
        }

        // Find mysqldump executable
        $mysqldump = $this->findMysqldump();
        if (!$mysqldump) {
            throw new RuntimeException("mysqldump not found. Please ensure MySQL client is installed.");
        }

        // Build command
        $dbConfig = require CONFIG_PATH . '/config.php';
        $dbUser = escapeshellarg($dbConfig['db_user']);
        $dbPass = escapeshellarg($dbConfig['db_pass']);
        $dbHost = escapeshellarg($dbConfig['db_host']);
        $dbName = escapeshellarg($dbConfig['db_name']);
        $filenameEscaped = escapeshellarg($this->filename);

        $cmd = "$mysqldump --routines --events --triggers --single-transaction --skip-lock-tables ";
        $cmd .= "--host=$dbHost --user=$dbUser --password=$dbPass $dbName";

        // If company specified, add a WHERE clause to exclude other companies
        if ($this->companyId) {
            // Use PHP-based filtered dump
            $this->dumpCompanyData();
            return;
        }

        // Full dump (all companies)
        $cmd .= " > $filenameEscaped 2>&1";

        $output = [];
        $return = 0;
        exec($cmd, $output, $return);

        if ($return !== 0 || !file_exists($this->filename) || filesize($this->filename) === 0) {
            throw new RuntimeException("mysqldump failed with code $return: " . implode("\n", $output));
        }

        if (!$this->quiet) {
            $size = round(filesize($this->filename) / 1024 / 1024, 2);
            echo "  Dumped {$size} MB\n";
        }
    }

    /**
     * Dump only company-specific data (simplified version)
     */
    private function dumpCompanyData(): void
    {
        $tables = $this->getTables();
        $output = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
        $output .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
        $output .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
        $output .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            $output .= $this->dumpTable($table);
        }

        $output .= "\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
        $output .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
        $output .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

        file_put_contents($this->filename, $output);
    }

    /**
     * Get list of tables to backup
     */
    private function getTables(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Dump a single table (company-filtered)
     */
    private function dumpTable(string $table): string
    {
        // Get create table statement
        $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch();
        $output = "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $row['Create Table'] . ";\n\n";

        // Determine if table has tenant_id or branch_id
        $columns = db_get_columns($table);
        $hasCompany = false;
        $hasBranch = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'tenant_id')
                $hasCompany = true;
            if ($col['Field'] === 'branch_id')
                $hasBranch = true;
        }

        if (!$hasCompany && !$hasBranch) {
            // Tables without company/branch isolation (e.g., roles, permissions)
            $output .= $this->dumpAllRows($table);
        } else {
            // Filter by company and optionally branch
            $where = [];
            if ($hasCompany && $this->companyId) {
                $where[] = "tenant_id = " . (int) $this->companyId;
            }
            $whereClause = $where ? " WHERE " . implode(' AND ', $where) : "";
            $stmt = $this->pdo->query("SELECT * FROM `$table`$whereClause");
            $rows = $stmt->fetchAll();
            $output .= $this->generateInserts($table, $rows);
        }

        return $output . "\n";
    }

    /**
     * Dump all rows of a table (no filter)
     */
    private function dumpAllRows(string $table): string
    {
        $stmt = $this->pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll();
        return $this->generateInserts($table, $rows);
    }

    /**
     * Generate INSERT statements from rows
     */
    private function generateInserts(string $table, array $rows): string
    {
        if (empty($rows)) {
            return "";
        }

        $output = "";
        $columns = array_keys($rows[0]);
        $colList = "`" . implode("`, `", $columns) . "`";

        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    $values[] = $this->pdo->quote($value);
                }
            }
            $output .= "INSERT INTO `$table` ($colList) VALUES (" . implode(", ", $values) . ");\n";
        }

        return $output . "\n";
    }

    /**
     * Find mysqldump executable path
     */
    private function findMysqldump(): ?string
    {
        $paths = [
            'mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysqldump.exe'
        ];

        foreach ($paths as $path) {
            if (strpos($path, '*') !== false) {
                $glob = glob($path);
                if (!empty($glob)) {
                    return $glob[0];
                }
                continue;
            }
            if (file_exists($path) || (PHP_OS !== 'WINNT' && is_executable($path))) {
                return $path;
            }
            if (PHP_OS !== 'WINNT') {
                $output = [];
                exec("which $path 2>/dev/null", $output, $return);
                if ($return === 0 && !empty($output)) {
                    return $output[0];
                }
            } else {
                $output = [];
                exec("where $path 2>nul", $output, $return);
                if ($return === 0 && !empty($output)) {
                    return $output[0];
                }
            }
        }
        return null;
    }

    /**
     * Compress the backup file
     */
    private function compress(): void
    {
        if (!$this->quiet) {
            echo "Compressing backup...\n";
        }

        $originalSize = filesize($this->filename);
        $gzFilename = $this->filename . '.gz';
        $gzData = gzencode(file_get_contents($this->filename), 9);

        if (file_put_contents($gzFilename, $gzData) === false) {
            throw new RuntimeException("Failed to compress backup");
        }

        unlink($this->filename);
        $this->filename = $gzFilename;

        if (!$this->quiet) {
            $compressedSize = filesize($this->filename);
            $ratio = round(($compressedSize / $originalSize) * 100, 1);
            echo "  Compressed: {$ratio}% of original\n";
        }
    }

    /**
     * Log backup activity
     */
    private function logBackup(): void
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        log_activity(
            $userId,
            'backup.created',
            [
                'filename' => basename($this->filename),
                'size' => filesize($this->filename),
                'compressed' => (str_ends_with($this->filename, '.gz')),
                'tenant_id' => $this->companyId,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            $this->companyId
        );
    }

    /**
     * Send backup via email
     */
    private function sendEmail(array $options): void
    {
        if (!$this->quiet) {
            echo "Sending backup via email...\n";
        }

        $config = require CONFIG_PATH . '/config.php';
        $backupEmail = $config['backup_email'] ?? $config['admin_email'] ?? null;
        if (!$backupEmail) {
            throw new RuntimeException("No backup email configured");
        }

        $subject = "Database Backup - " . date('Y-m-d H:i:s') . ($this->companyId ? " (Company {$this->companyId})" : "");
        $message = "Database backup has been created.\n\n";
        $message .= "File: " . basename($this->filename) . "\n";
        $message .= "Size: " . round(filesize($this->filename) / 1024 / 1024, 2) . " MB\n";
        $message .= "Date: " . date('Y-m-d H:i:s') . "\n";
        if ($this->companyId) {
            $message .= "Company: " . $this->companyId . "\n";
        }
        $message .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\n";

        $headers = "From: " . ($config['email_from'] ?? 'backup@jakababa.com') . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        if (!mail($backupEmail, $subject, $message, $headers)) {
            throw new RuntimeException("Failed to send email");
        }
    }

    /**
     * Delete old backups
     */
    private function deleteOldBackups(int $days): void
    {
        $cutoff = time() - ($days * 86400);
        $pattern = $this->backupDir . DIRECTORY_SEPARATOR . ($this->companyId ? "tenant_{$this->companyId}_backup_*" : "full_backup_*");
        $files = glob($pattern . '.sql') + glob($pattern . '.sql.gz');

        $deleted = 0;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        if (!$this->quiet && $deleted > 0) {
            echo "✓ Deleted {$deleted} old backup(s) older than {$days} days\n";
        }
    }

    /**
     * Create symlink to latest backup
     */
    private function createLatestSymlink(): void
    {
        $linkPath = $this->backupDir . DIRECTORY_SEPARATOR . 'latest_backup.sql';
        if (str_ends_with($this->filename, '.gz')) {
            $linkPath = $this->backupDir . DIRECTORY_SEPARATOR . 'latest_backup.sql.gz';
        }

        if (file_exists($linkPath)) {
            unlink($linkPath);
        }

        if (PHP_OS === 'WINNT') {
            copy($this->filename, $linkPath);
        } else {
            symlink($this->filename, $linkPath);
        }
    }
}

// -----------------------------------------------------------------------------
// Main Execution
// -----------------------------------------------------------------------------

try {
    $backup = new BackupManager($companyId, $quiet);
    $success = $backup->run($options);
    exit($success ? 0 : 1);
} catch (Exception $e) {
    error_log("Backup fatal error: " . $e->getMessage());
    if (!$quiet) {
        echo "Fatal error: " . $e->getMessage() . "\n";
    }
    exit(1);
}