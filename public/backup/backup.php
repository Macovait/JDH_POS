<?php
/**
 * Database Backup - Jakababa POS
 * Full CRUD with upload, export, Laravel-style layout
 */

$page_title = 'Backups';
ob_start();

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('backup.run') && !is_super_admin()) {
    enforce_permission('backup.run');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

$user_id   = (int) ($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';
$is_admin  = is_super_admin() || $user_role === 'Admin';

$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) { mkdir($backup_dir, 0755, true); }

$success_message = '';
$error_message   = '';

// ------------------------------------------------------------------------------
// POST Actions (CRUD + Upload + Export)
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        // ----- CREATE -----
        case 'create_backup':
            try {
                $timestamp = date('Y-m-d_H-i-s');
                $filename  = "backup_{$timestamp}.sql";
                $filepath  = $backup_dir . $filename;

                $tables = [];
                $stmt = $pdo->query("SHOW TABLES");
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }

                $output = "-- JDH POS Database Backup\n";
                $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                $output .= "-- Database: " . ($pdo->query('SELECT DATABASE()')->fetchColumn()) . "\n\n";
                $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

                foreach ($tables as $table) {
                    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                    $row  = $stmt->fetch(PDO::FETCH_NUM);
                    $output .= "\n-- Table structure for `{$table}`\n";
                    $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    $output .= $row[1] . ";\n\n";

                    $stmt = $pdo->query("SELECT * FROM `{$table}`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($rows)) {
                        $output .= "-- Dumping data for `{$table}`\n";
                        $columns = array_keys($rows[0]);
                        foreach ($rows as $rowData) {
                            $values = array_map(function ($value) use ($pdo) {
                                return $value === null ? 'NULL' : $pdo->quote($value);
                            }, $rowData);
                            $output .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $output .= "\n";
                    }
                }

                $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
                file_put_contents($filepath, $output);

                $success_message = 'Backup created successfully: ' . $filename;
                if (function_exists('log_activity')) {
                    log_activity($user_id, 'backup.created', ['file' => $filename, 'size' => filesize($filepath)], null, get_current_tenant_id());
                }
            } catch (Exception $e) {
                $error_message = 'Backup failed: ' . $e->getMessage();
                error_log('Backup creation error: ' . $e->getMessage());
            }
            break;

        // ----- UPDATE (Rename) -----
        case 'rename_backup':
            if ($is_admin) {
                $old_name = basename($_POST['old_name'] ?? '');
                $new_name = basename(trim($_POST['new_name'] ?? ''));
                $old_path = $backup_dir . $old_name;
                $new_path = $backup_dir . $new_name;

                if (empty($old_name) || empty($new_name)) {
                    $error_message = 'Both old and new names are required';
                } elseif (!file_exists($old_path)) {
                    $error_message = 'Backup file not found';
                } elseif (file_exists($new_path)) {
                    $error_message = 'A file with that name already exists';
                } elseif (!preg_match('/\.(sql|sql\.gz)$/i', $new_name)) {
                    $error_message = 'Filename must end with .sql or .sql.gz';
                } elseif (rename($old_path, $new_path)) {
                    $success_message = 'Backup renamed successfully';
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'backup.renamed', ['old' => $old_name, 'new' => $new_name], null, get_current_tenant_id());
                    }
                } else {
                    $error_message = 'Failed to rename backup file';
                }
            }
            break;

        // ----- DELETE -----
        case 'delete_backup':
            if ($is_admin) {
                $backup_file = basename($_POST['backup_file'] ?? '');
                $file_path = $backup_dir . $backup_file;
                if (!empty($backup_file) && file_exists($file_path) && is_file($file_path)) {
                    if (unlink($file_path)) {
                        $success_message = 'Backup file deleted successfully';
                        if (function_exists('log_activity')) log_activity($user_id, 'backup.deleted', ['file' => $backup_file], null, get_current_tenant_id());
                    } else { $error_message = 'Failed to delete backup file'; }
                } else { $error_message = 'Backup file not found'; }
            }
            break;

        // ----- DOWNLOAD (Read) -----
        case 'download_backup':
            $backup_file = basename($_POST['backup_file'] ?? '');
            $file_path = $backup_dir . $backup_file;
            if (!empty($backup_file) && file_exists($file_path) && is_file($file_path)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $backup_file . '"');
                header('Content-Length: ' . filesize($file_path));
                if (function_exists('log_activity')) log_activity($user_id, 'backup.downloaded', ['file' => $backup_file], null, get_current_tenant_id());
                readfile($file_path);
                exit;
            } else { $error_message = 'Backup file not found'; }
            break;

        // ----- UPLOAD -----
        case 'upload_backup':
            if (!empty($_FILES['backup_file']['tmp_name']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                $upload_name = basename($_FILES['backup_file']['name']);
                $tmp_path    = $_FILES['backup_file']['tmp_name'];
                $dest_path   = $backup_dir . $upload_name;

                $allowed_ext = ['sql', 'gz'];
                $ext = strtolower(pathinfo($upload_name, PATHINFO_EXTENSION));
                $is_sql_gz = (substr($upload_name, -7) === '.sql.gz');

                if (!$is_sql_gz && !in_array($ext, $allowed_ext, true)) {
                    $error_message = 'Only .sql and .sql.gz files are allowed';
                } elseif (file_exists($dest_path)) {
                    $error_message = 'A backup with that name already exists';
                } elseif (move_uploaded_file($tmp_path, $dest_path)) {
                    $success_message = 'Backup uploaded successfully: ' . $upload_name;
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'backup.uploaded', ['file' => $upload_name, 'size' => filesize($dest_path)], null, get_current_tenant_id());
                    }
                } else {
                    $error_message = 'Failed to upload backup file';
                }
            } else {
                $error_message = 'No file selected or upload error occurred';
            }
            break;

        // ----- EXPORT LIST TO CSV -----
        case 'export_csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="backups_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Filename', 'Size (Bytes)', 'Size (Human)', 'Created At', 'Age (Days)']);

            $files = scandir($backup_dir);
            foreach ($files as $file) {
                $isSql = (pathinfo($file, PATHINFO_EXTENSION) === 'sql');
                $isSqlGz = (substr($file, -7) === '.sql.gz');
                if (($isSql || $isSqlGz) && is_file($backup_dir . $file)) {
                    $size = filesize($backup_dir . $file);
                    $time = filemtime($backup_dir . $file);
                    $age_days = floor((time() - $time) / 86400);
                    fputcsv($out, [
                        $file,
                        $size,
                        format_bytes($size),
                        date('Y-m-d H:i:s', $time),
                        $age_days
                    ]);
                }
            }
            fclose($out);
            if (function_exists('log_activity')) {
                log_activity($user_id, 'backup.exported', ['format' => 'csv'], null, get_current_tenant_id());
            }
            exit;

        // ----- CLEANUP -----
        case 'cleanup_old':
            if ($is_admin) {
                $days = intval($_POST['days'] ?? 30);
                $cutoff = time() - ($days * 24 * 60 * 60);
                $deleted_count = 0;
                if (is_dir($backup_dir)) {
                    $files = scandir($backup_dir);
                    foreach ($files as $file) {
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $isSql = ($ext === 'sql');
                        $isSqlGz = (substr($file, -7) === '.sql.gz');
                        if ($isSql || $isSqlGz) {
                            $file_path = $backup_dir . $file;
                            if (filemtime($file_path) < $cutoff && unlink($file_path)) $deleted_count++;
                        }
                    }
                }
                $success_message = "Deleted $deleted_count backup files older than $days days";
                if (function_exists('log_activity')) log_activity($user_id, 'backup.cleanup', ['days' => $days, 'deleted' => $deleted_count], get_current_tenant_id());
            }
            break;
    }
}

// ------------------------------------------------------------------------------
// Load Backup List
// ------------------------------------------------------------------------------
$backup_files = [];
$total_size = 0;
$newest_backup = null;
$oldest_backup = null;

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $isSql = ($ext === 'sql');
        $isSqlGz = (substr($file, -7) === '.sql.gz');
        if (($isSql || $isSqlGz) && is_file($backup_dir . $file)) {
            $file_path = $backup_dir . $file;
            $file_size = filesize($file_path);
            $file_time = filemtime($file_path);
            $backup_files[] = [
                'name'     => $file,
                'size'     => $file_size,
                'modified' => $file_time,
                'path'     => $file_path,
            ];
            $total_size += $file_size;
            if ($newest_backup === null || $file_time > $newest_backup['modified']) {
                $newest_backup = ['name' => $file, 'modified' => $file_time];
            }
            if ($oldest_backup === null || $file_time < $oldest_backup['modified']) {
                $oldest_backup = ['name' => $file, 'modified' => $file_time];
            }
        }
    }
    usort($backup_files, fn($a, $b) => $b['modified'] - $a['modified']);
}

// ------------------------------------------------------------------------------
// Database Stats
// ------------------------------------------------------------------------------
$db_name = '';
$db_size = 0;
$table_count = 0;
try {
    $stmt = $pdo->query('SELECT DATABASE() as db_name');
    $db_name = $stmt->fetch()['db_name'] ?: 'jakababa_pos';
    $stmt = $pdo->query('SHOW TABLES');
    $table_count = $stmt->rowCount();
    $stmt = $pdo->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()");
    $db_size = $stmt->fetch()['size'] ?? 0;
} catch (PDOException $e) { error_log("Error getting database info: " . $e->getMessage()); }

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<style>
    .inline-flex items-center justify-center { display: inline-flex; align-items: center; justify-content: center; }
    .cli-command { background: #1E1E1E; color: #D4D4D4; font-family: 'Courier New', monospace; padding: 1rem; border-radius: 0.75rem; border: 1px solid #374151; overflow-x: auto; }
    .cli-command .prompt { color: #10B981; }
    .cli-command .command { color: #FBBF24; }
    .mb-4 { background: rgba(239, 68, 68, 0.1); border-left: 4px solid #EF4444; }
    .success-box { background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10B981; }
    .info-box { background: rgba(251, 191, 36, 0.1); border-left: 4px solid #FBBF24; }
</style>

<div class="fade-in">

    <!-- Laravel-style CRUD Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 mb-6">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">System</div>
            <h1 class="text-lg font-bold text-white">Backups</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <form method="POST" style="display:inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors"><i class="fas fa-file-csv"></i> Export CSV</button>
            </form>
            <button onclick="showUploadModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors"><i class="fas fa-cloud-upload-alt"></i> Upload</button>
            <form method="POST" style="display:inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors"><i class="fas fa-plus"></i> Create Backup</button>
            </form>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 rounded-lg bg-green-500/10 border border-green-500/20 text-green-400">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="mb-6 p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Database</div>
            <div class="text-lg font-bold text-white text-xl font-mono"><?php echo htmlspecialchars($db_name); ?></div>
            <div class="text-xs text-[#6B7280] mt-1"><?php echo number_format($table_count); ?> tables</div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">DB Size</div>
            <div class="text-lg font-bold text-white text-xl text-[#FBBF24]"><?php echo format_bytes($db_size); ?></div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Backups</div>
            <div class="text-lg font-bold text-white text-xl text-[#3B82F6]"><?php echo count($backup_files); ?></div>
            <div class="text-xs text-[#6B7280] mt-1"><?php echo format_bytes($total_size); ?> total</div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5 text-[#9CA3AF]">Newest</div>
            <div class="text-lg font-bold text-white text-lg"><?php echo $newest_backup ? date('M j, Y', $newest_backup['modified']) : 'N/A'; ?></div>
        </div>
    </div>

    <!-- Filter / Search (optional placeholder for consistency) -->
    <div class="flex flex-wrap gap-2 mb-6">
        <span class="px-3 py-1.5 bg-gray-700 text-gray-300 rounded-md text-sm">
            <i class="fas fa-hdd mr-1"></i> Dir: <code class="text-yellow-400"><?php echo htmlspecialchars(str_replace('\\', '/', realpath($backup_dir) ?: $backup_dir)); ?></code>
        </span>
        <?php if ($is_admin && !empty($backup_files)): ?>
            <button onclick="showCleanupModal()" class="px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-md text-sm transition-colors">
                <i class="fas fa-trash-alt mr-1"></i> Cleanup Old
            </button>
        <?php endif; ?>
    </div>

    <!-- CLI Instructions -->
    <div class="info-box rounded-xl p-5 mb-8">
        <div class="flex items-start gap-3">
            <i class="fas fa-terminal text-[#FBBF24] mt-1"></i>
            <div class="flex-1">
                <h3 class="text-[#FBBF24] font-semibold mb-1">Command Line Backup</h3>
                <p class="text-sm text-gray-400 mb-2">For automated backups via CLI or cron:</p>
                <div class="cli-command mb-2"><span class="prompt">$</span><span class="command"> php scripts/backup_cli.php</span></div>
                <p class="text-xs text-gray-500">Crontab: <code class="text-gray-300">0 2 * * * php /path/to/scripts/backup_cli.php --quiet</code></p>
            </div>
        </div>
    </div>

    <!-- Available Backups Table -->
    <div class="overflow-x-auto bg-gray-800 rounded-xl border border-gray-700">
        <table class="w-full min-w-[800px] text-left wp-list-table">
            <thead class="bg-gray-900 border-b border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Filename</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Size</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Created</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Age</th>
                    <th class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($backup_files)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                        <i class="fas fa-archive text-4xl text-gray-600 mb-3"></i>
                        <p>No backups found.</p>
                        <p class="text-sm mt-1">Click <strong>Create Backup</strong> to generate your first backup.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($backup_files as $backup):
                        $age = time() - $backup['modified'];
                        $age_days = floor($age / 86400);
                        $age_hours = floor(($age % 86400) / 3600);
                        $age_color = $age_days > 30 ? 'text-red-400' : ($age_days > 7 ? 'text-yellow-400' : 'text-gray-300');
                    ?>
                    <tr class="hover:bg-gray-700/50 transition-colors">
                        <td class="px-4 py-4 text-sm">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-file-code text-[#FBBF24]"></i>
                                <span class="font-mono text-white truncate max-w-xs" title="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <?php echo htmlspecialchars($backup['name']); ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-300"><?php echo format_bytes($backup['size']); ?></td>
                        <td class="px-4 py-4 text-sm text-gray-300">
                            <div><?php echo date('M d, Y', $backup['modified']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', $backup['modified']); ?></div>
                        </td>
                        <td class="px-4 py-4 text-sm <?php echo $age_color; ?>">
                            <?php if ($age_days > 0): ?><?php echo $age_days; ?>d <?php echo $age_hours; ?>h<?php else: ?>Today<?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-sm text-right">
                            <div class="flex justify-end gap-2">
                                <form method="POST" style="display:inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="download_backup">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" class="text-green-400 hover:text-green-300 text-xs" title="Download"><i class="fas fa-download"></i> Download</button>
                                </form>
                                <?php if ($is_admin): ?>
                                    <button onclick="showRenameModal('<?php echo htmlspecialchars($backup['name']); ?>')" class="text-blue-400 hover:text-blue-300 text-xs" title="Rename"><i class="fas fa-edit"></i> Rename</button>
                                    <button onclick="showRestoreInstructions('<?php echo htmlspecialchars($backup['name']); ?>')" class="text-[#FBBF24] hover:text-yellow-300 text-xs" title="Restore"><i class="fas fa-terminal"></i> Restore</button>
                                    <form method="POST" style="display:inline" onsubmit="return confirmDelete('<?php echo htmlspecialchars($backup['name']); ?>\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">')">
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-xs" title="Delete"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($backup_files)): ?>
    <div class="flex justify-between items-center mt-4 text-sm text-gray-400 px-2">
        <span><i class="fas fa-archive mr-1"></i> Total: <span class="text-white font-semibold"><?php echo count($backup_files); ?></span> files | <span class="text-white font-semibold"><?php echo format_bytes($total_size); ?></span></span>
    </div>
    <?php endif; ?>

    <!-- Best Practices -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-[#FBBF24]/10 rounded-lg"><i class="fas fa-clock text-[#FBBF24]"></i></div>
                <div><h3 class="text-white font-semibold mb-1 text-sm">Regular Backups</h3><p class="text-xs text-gray-400">Schedule daily backups during low-traffic hours using cron jobs.</p></div>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-green-500/10 rounded-lg"><i class="fas fa-shield-alt text-green-400"></i></div>
                <div><h3 class="text-white font-semibold mb-1 text-sm">Backup Retention</h3><p class="text-xs text-gray-400">Keep daily backups for 30 days, weekly for 3 months, and monthly for 1 year.</p></div>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-red-500/10 rounded-lg"><i class="fas fa-server text-red-400"></i></div>
                <div><h3 class="text-white font-semibold mb-1 text-sm">Off-site Storage</h3><p class="text-xs text-gray-400">Store encrypted backups in a different location or cloud storage for disaster recovery.</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Instructions Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black/50  flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 max-w-lg w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-white"><i class="fas fa-terminal text-[#FBBF24] mr-2"></i>Restore Command</h3>
            <button onclick="closeRestoreModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-4">
            <p class="text-sm text-gray-400">To restore <span id="restoreFileName" class="text-[#FBBF24] font-mono"></span>, run:</p>
            <div class="cli-command" id="restoreCommand"></div>
            <div class="mb-4 p-3 rounded-xl"><p class="text-xs text-red-400"><strong>Warning:</strong> This will completely replace your current database.</p></div>
        </div>
        <div class="flex justify-end mt-6">
            <button onclick="closeRestoreModal()" class="px-4 py-2 bg-gray-700 border border-gray-600 text-white rounded-lg hover:border-[#FBBF24] transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-black/50  flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-white"><i class="fas fa-cloud-upload-alt text-[#FBBF24] mr-2"></i>Upload Backup</h3>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="upload_backup">
            <div class="space-y-4">
                <p class="text-sm text-gray-400">Select a <code class="text-gray-300">.sql</code> or <code class="text-gray-300">.sql.gz</code> file to upload:</p>
                <input type="file" name="backup_file" accept=".sql,.gz" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-900 border border-gray-600 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#FBBF24] file:text-gray-900 file:font-semibold hover:file:bg-yellow-500">
                <div class="info-box p-3 rounded-xl">
                    <p class="text-xs text-[#FBBF24]"><i class="fas fa-info-circle mr-1"></i> Max upload size is determined by your PHP <code>upload_max_filesize</code> setting.</p>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-4 py-3 bg-[#FBBF24] text-gray-900 rounded-xl font-semibold hover:bg-yellow-500 transition-colors"><i class="fas fa-upload mr-1"></i> Upload</button>
                <button type="button" onclick="closeUploadModal()" class="flex-1 px-4 py-3 bg-gray-700 border border-gray-600 text-white rounded-xl font-semibold hover:border-[#FBBF24] transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div id="renameModal" class="fixed inset-0 bg-black/50  flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-white"><i class="fas fa-edit text-blue-400 mr-2"></i>Rename Backup</h3>
            <button onclick="closeRenameModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="rename_backup">
            <input type="hidden" name="old_name" id="renameOldName">
            <div class="space-y-4">
                <label class="block text-sm text-gray-400">New filename</label>
                <input type="text" name="new_name" id="renameNewName" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-900 border border-gray-600 text-white focus:border-[#FBBF24] outline-none font-mono"
                       placeholder="backup_YYYY-MM-DD_HH-MM-SS.sql">
                <p class="text-xs text-gray-500">Must end with <code>.sql</code> or <code>.sql.gz</code></p>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-4 py-3 bg-blue-500 text-white rounded-xl font-semibold hover:bg-blue-600 transition-colors"><i class="fas fa-check mr-1"></i> Rename</button>
                <button type="button" onclick="closeRenameModal()" class="flex-1 px-4 py-3 bg-gray-700 border border-gray-600 text-white rounded-xl font-semibold hover:border-[#FBBF24] transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Cleanup Modal -->
<div id="cleanupModal" class="fixed inset-0 bg-black/50  flex items-center justify-center hidden z-50">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-white"><i class="fas fa-trash-alt text-red-400 mr-2"></i>Cleanup Old Backups</h3>
            <button onclick="closeCleanupModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="cleanup_old">
            <div class="space-y-4">
                <p class="text-sm text-gray-400">Delete backup files older than:</p>
                <select name="days" class="w-full px-4 py-3 rounded-xl bg-gray-900 border border-gray-600 text-white focus:border-[#FBBF24] outline-none">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">1 year</option>
                </select>
                <div class="mb-4 p-3 rounded-xl"><p class="text-xs text-red-400"><strong>Warning:</strong> This action cannot be undone.</p></div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 px-4 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-colors"><i class="fas fa-trash mr-1"></i> Delete Old</button>
                <button type="button" onclick="closeCleanupModal()" class="flex-1 px-4 py-3 bg-gray-700 border border-gray-600 text-white rounded-xl font-semibold hover:border-[#FBBF24] transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Restore modal
    function showRestoreInstructions(filename) {
        document.getElementById('restoreFileName').textContent = filename;
        document.getElementById('restoreCommand').innerHTML = `<span class="prompt">$</span><span class="command">mysql -u root -p jdh_pos &lt; <?php echo str_replace('\\', '/', realpath($backup_dir) ?: $backup_dir); ?>/${filename}</span>`;
        document.getElementById('restoreModal').classList.remove('hidden');
    }
    function closeRestoreModal() { document.getElementById('restoreModal').classList.add('hidden'); }

    // Upload modal
    function showUploadModal() { document.getElementById('uploadModal').classList.remove('hidden'); }
    function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); }

    // Rename modal
    function showRenameModal(filename) {
        document.getElementById('renameOldName').value = filename;
        document.getElementById('renameNewName').value = filename;
        document.getElementById('renameModal').classList.remove('hidden');
    }
    function closeRenameModal() { document.getElementById('renameModal').classList.add('hidden'); }

    // Cleanup modal
    function showCleanupModal() { document.getElementById('cleanupModal').classList.remove('hidden'); }
    function closeCleanupModal() { document.getElementById('cleanupModal').classList.add('hidden'); }

    function confirmDelete(filename) { return confirm(`Are you sure you want to delete "${filename}"?`); }

    // Close modals on backdrop click
    document.querySelectorAll('.fixed').forEach(modal => {
        modal.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === 'Escape') {
            closeRestoreModal(); closeUploadModal(); closeRenameModal(); closeCleanupModal();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
?>
