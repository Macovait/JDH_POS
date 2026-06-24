<?php
/**
 * Database Backup Manager for Jakababa POS
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('backup.run') && !is_super_admin()) {
    enforce_permission('backup.run');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);

$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) { mkdir($backup_dir, 0755, true); }

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'create_backup':
            try {
                $pdo = get_db_connection();
                $database = 'jakababa_pos';
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "backup_{$timestamp}.sql";
                $filepath = $backup_dir . $filename;
                $host = 'localhost'; $username = 'root'; $password = '';
                $command = sprintf('"C:\\xampp\\mysql\\bin\\mysqldump" --host=%s --user=%s --password=%s --routines --triggers --events %s > "%s" 2>&1', $host, $username, $password, $database, $filepath);
                exec($command, $output, $return_var);
                if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                    $success_message = "Backup created successfully: {$filename}";
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'backup_created', ?, ?, NOW())");
                    $stmt->execute([$user_id, "Created database backup: {$filename} (" . format_bytes(filesize($filepath)) . ")", $_SERVER['REMOTE_ADDR'] ?? null]);
                } else {
                    $result = createPhpBackup($pdo, $database, $filepath);
                    if ($result && file_exists($filepath) && filesize($filepath) > 0) {
                        $success_message = "Backup created using PHP method: {$filename}";
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'backup_created', ?, ?, NOW())");
                        $stmt->execute([$user_id, "Created database backup: {$filename}", $_SERVER['REMOTE_ADDR'] ?? null]);
                    } else { throw new Exception("Failed to create backup using both methods."); }
                }
            } catch (Exception $e) {
                error_log("Backup creation error: " . $e->getMessage());
                $error_message = "Failed to create backup: " . $e->getMessage();
            }
            break;
        case 'delete_backup':
            if (is_super_admin() || $user_role === 'Admin') {
                $backup_file = basename($_POST['backup_file'] ?? '');
                $file_path = $backup_dir . $backup_file;
                if (!empty($backup_file) && file_exists($file_path) && is_file($file_path)) {
                    $size = filesize($file_path);
                    if (unlink($file_path)) {
                        $success_message = "Backup file '{$backup_file}' deleted successfully";
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'backup_deleted', ?, ?, NOW())");
                        $stmt->execute([$user_id, "Deleted backup: {$backup_file} (" . format_bytes($size) . ")", $_SERVER['REMOTE_ADDR'] ?? null]);
                    } else { $error_message = 'Failed to delete backup file'; }
                } else { $error_message = 'Backup file not found'; }
            }
            break;
        case 'download_backup':
            $backup_file = basename($_POST['backup_file'] ?? '');
            $file_path = $backup_dir . $backup_file;
            if (!empty($backup_file) && file_exists($file_path) && is_file($file_path)) {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'backup_downloaded', ?, ?, NOW())");
                $stmt->execute([$user_id, "Downloaded backup: {$backup_file}", $_SERVER['REMOTE_ADDR'] ?? null]);
                header('Content-Description: File Transfer'); header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $backup_file . '"'); header('Content-Length: ' . filesize($file_path));
                readfile($file_path); exit;
            } else { $error_message = 'Backup file not found'; }
            break;
        case 'restore_backup':
            if (is_super_admin() || $user_role === 'Admin') {
                $backup_file = basename($_POST['backup_file'] ?? '');
                $file_path = $backup_dir . $backup_file;
                if (!empty($backup_file) && file_exists($file_path) && is_file($file_path)) {
                    try {
                        $sql = file_get_contents($file_path);
                        $queries = explode(';', $sql);
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                        $pdo->beginTransaction();
                        $query_count = 0;
                        foreach ($queries as $query) { $query = trim($query); if (!empty($query)) { $pdo->exec($query); $query_count++; } }
                        $pdo->commit();
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        $success_message = "Database restored from '{$backup_file}' ({$query_count} queries)";
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'backup_restored', ?, ?, NOW())");
                        $stmt->execute([$user_id, "Restored from backup: {$backup_file}", $_SERVER['REMOTE_ADDR'] ?? null]);
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        $error_message = "Failed to restore: " . $e->getMessage();
                    }
                } else { $error_message = 'Backup file not found'; }
            }
            break;
        case 'cleanup_old':
            if (is_super_admin() || $user_role === 'Admin') {
                $days = intval($_POST['days'] ?? 30);
                $cutoff = time() - ($days * 24 * 60 * 60);
                $deleted_count = 0; $deleted_size = 0;
                if (is_dir($backup_dir)) {
                    $files = scandir($backup_dir);
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                            $fp = $backup_dir . $file;
                            if (filemtime($fp) < $cutoff) { $sz = filesize($fp); if (unlink($fp)) { $deleted_count++; $deleted_size += $sz; } }
                        }
                    }
                }
                $success_message = "Deleted {$deleted_count} backups older than {$days} days (" . format_bytes($deleted_size) . " freed)";
            }
            break;
    }
}

function createPhpBackup($pdo, $database, $filepath) {
    try {
        $handle = fopen($filepath, 'w');
        if (!$handle) throw new Exception("Cannot open file: {$filepath}");
        fwrite($handle, "-- Jakababa POS Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSTART TRANSACTION;\n\n");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
            fwrite($handle, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n");
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                foreach ($rows as $row) {
                    $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                    fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES (" . implode(', ', $values) . ");\n");
                }
            }
        }
        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\nCOMMIT;\n");
        fclose($handle);
        return true;
    } catch (Exception $e) { error_log("PHP Backup error: " . $e->getMessage()); return false; }
}

$backup_files = [];
$total_size = 0;
$newest_backup = null;
$oldest_backup = null;

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql' && is_file($backup_dir . $file)) {
            $fp = $backup_dir . $file;
            $fs = filesize($fp); $ft = filemtime($fp);
            $backup_files[] = ['name' => $file, 'size' => $fs, 'modified' => $ft, 'path' => $fp, 'is_valid' => true];
            $total_size += $fs;
            if ($newest_backup === null || $ft > $newest_backup['modified']) $newest_backup = ['name' => $file, 'modified' => $ft];
            if ($oldest_backup === null || $ft < $oldest_backup['modified']) $oldest_backup = ['name' => $file, 'modified' => $ft];
        }
    }
    usort($backup_files, fn($a, $b) => $b['modified'] - $a['modified']);
}

$db_name = ''; $db_size = 0; $table_count = 0;
try {
    $stmt = $pdo->query('SELECT DATABASE() as db_name'); $db_name = $stmt->fetch()['db_name'] ?: 'jakababa_pos';
    $stmt = $pdo->query('SHOW TABLES'); $table_count = $stmt->rowCount();
    $stmt = $pdo->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()");
    $db_size = $stmt->fetch()['size'] ?? 0;
} catch (PDOException $e) { error_log("Error getting database info: " . $e->getMessage()); }

function format_bytes($bytes, $precision = 2) { $units = ['B','KB','MB','GB','TB']; $bytes = max($bytes, 0); $pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1); return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow]; }

$page_title = 'Database Backup | Jakababa POS';
$can_delete = is_super_admin() || $user_role === 'Admin';
$can_restore = is_super_admin() || $user_role === 'Admin';
$backup_dir_writable = is_writable($backup_dir);
$backup_dir_readable = is_readable($backup_dir);
include_once __DIR__ . '/../layouts/app.php';
?>

<style>
    .cli-command { background: #1E1E1E; color: #D4D4D4; font-family: 'Courier New', monospace; padding: 1rem; border-radius: 0.75rem; border: 1px solid #374151; overflow-x: auto; }
    .cli-command .prompt { color: #10B981; } .cli-command .command { color: #FBBF24; }
    .backup-row-hover:hover { background: rgba(251, 191, 36, 0.05); }
    .mb-4 { background: rgba(239, 68, 68, 0.1); border-left: 4px solid #EF4444; border-radius: 0.5rem; padding: 0.75rem; }
    .success-box { background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10B981; border-radius: 0.5rem; padding: 0.75rem; }
    .info-box { background: rgba(251, 191, 36, 0.1); border-left: 4px solid #FBBF24; border-radius: 0.5rem; padding: 0.75rem; }
    .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.5rem; }
    .status-dot.success { background: #10B981; box-shadow: 0 0 8px #10B981; }
    .status-dot.warning { background: #FBBF24; box-shadow: 0 0 8px #FBBF24; }
    .status-dot.error { background: #EF4444; box-shadow: 0 0 8px #EF4444; }
</style>

<div class="fade-in">

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 ">
        <div><h1 class="text-3xl font-bold text-white">Database Backup</h1><p class="text-gray-400 mt-1">Create, download, and manage database backups</p></div>
        <div class="flex gap-2">
            <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="create_backup"><button type="submit" class="inline-flex items-center gap-2 px-6 py-2 bg-amber-500 text-black rounded-xl font-semibold hover:bg-amber-600 transition-all" onclick="return confirm('Create a new database backup?')"><i class="fas fa-database"></i><span>Create Backup</span></button></form>
            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/50 rounded-xl border border-slate-700 text-gray-300 hover:text-white hover:border-amber-500 transition-all"><i class="fas fa-sync-alt"></i><span class="text-sm">Refresh</span></button>
        </div>
    </div>

    <!-- Directory Status -->
    <div class="mb-6 flex items-center gap-3 text-sm">
        <span class="text-gray-400">Backup Directory:</span>
        <code class="bg-primary-dark px-3 py-1 rounded-lg"><?php echo $backup_dir; ?></code>
        <span class="flex items-center">
            <?php if ($backup_dir_writable && $backup_dir_readable): ?><span class="status-dot success"></span><span class="text-emerald-400">Read/Write</span>
            <?php elseif ($backup_dir_readable): ?><span class="status-dot warning"></span><span class="text-red-400">Read Only</span>
            <?php else: ?><span class="status-dot error"></span><span class="text-red-400">No Access</span><?php endif; ?>
        </span>
    </div>

    <?php if ($success_message): ?><div class="mb-6 success-box border border-emerald-500/30 text-emerald-400"><div class="flex items-center gap-3"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success_message); ?></span></div></div><?php endif; ?>
    <?php if ($error_message): ?><div class="mb-6 mb-4 border border-red-500/30 text-red-400"><div class="flex items-start gap-3"><i class="fas fa-exclamation-circle mt-1"></i><div><?php echo htmlspecialchars($error_message); ?></div></div></div><?php endif; ?>

    <!-- Database Info Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8 ">
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-400">Database Name</p><p class="text-lg font-bold text-white font-mono"><?php echo htmlspecialchars($db_name); ?></p></div><div class="p-3 bg-amber-500/10 rounded-xl"><i class="fas fa-database text-amber-400 text-xl"></i></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-400">Tables</p><p class="text-2xl font-bold text-emerald-400"><?php echo number_format($table_count); ?></p></div><div class="p-3 bg-emerald-500/10 rounded-xl"><i class="fas fa-table text-emerald-400 text-xl"></i></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-400">Database Size</p><p class="text-lg font-bold text-amber-400"><?php echo format_bytes($db_size); ?></p></div><div class="p-3 bg-amber-500/10 rounded-xl"><i class="fas fa-hard-drive text-amber-400 text-xl"></i></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-400">Backups Available</p><p class="text-2xl font-bold text-red-400"><?php echo count($backup_files); ?></p></div><div class="p-3 bg-red-500/10 rounded-xl"><i class="fas fa-archive text-red-400 text-xl"></i></div></div></div>
    </div>

    <!-- Backup Info -->
    <div class="info-box mb-8 ">
        <div class="flex items-start gap-3"><i class="fas fa-info-circle text-amber-400 text-xl mt-1"></i><div class="flex-1"><h3 class="text-amber-400 font-semibold mb-2">About Backups</h3><p class="text-sm text-gray-300">Backup files include complete database structure, data, triggers, and events.</p><p class="text-sm text-gray-300 mt-2"><span class="text-emerald-400">✓</span> Automatic timestamps | <span class="text-emerald-400">✓</span> Foreign key handling | <span class="text-red-400">⚠</span> Restoring replaces all data</p></div></div>
    </div>

    <!-- Available Backups -->
    <div class="bg-slate-800/40 rounded-xl border border-slate-700 overflow-hidden ">
        <div class="p-4 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2"><i class="fas fa-archive text-amber-400"></i>Available Backups</h2>
            <div class="flex items-center gap-3">
                <?php if ($can_delete && !empty($backup_files)): ?><button onclick="showCleanupModal()" class="text-sm text-gray-400 hover:text-amber-400 transition-colors flex items-center gap-1"><i class="fas fa-trash-alt"></i>Cleanup Old</button><?php endif; ?>
                <span class="text-sm text-gray-400"><?php echo count($backup_files); ?> files</span>
            </div>
        </div>

        <?php if (empty($backup_files)): ?>
            <div class="p-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><i class="fas fa-archive text-6xl text-gray-600"></i><p class="text-lg">No backups found</p><p class="text-sm max-w-md text-gray-400">Click "Create Backup" to create your first backup.</p></div></div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/50 border-b border-slate-700"><tr><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Filename</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Size</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Created</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Age</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach ($backup_files as $backup): ?>
                            <?php $age = time() - $backup['modified']; $age_days = floor($age / 86400); $age_hours = floor(($age % 86400) / 3600); $age_minutes = floor(($age % 3600) / 60); ?>
                            <tr class="backup-row-hover transition-colors">
                                <td class="px-6 py-4"><div class="flex items-center gap-2"><i class="fas fa-file-code text-amber-400"></i><span class="font-mono text-sm text-white truncate max-w-xs" title="<?php echo htmlspecialchars($backup['name']); ?>"><?php echo htmlspecialchars($backup['name']); ?></span></div></td>
                                <td class="px-6 py-4"><span class="text-gray-300"><?php echo format_bytes($backup['size']); ?></span></td>
                                <td class="px-6 py-4"><div class="flex flex-col"><span class="text-white"><?php echo date('M j, Y', $backup['modified']); ?></span><span class="text-xs text-gray-500"><?php echo date('H:i:s', $backup['modified']); ?></span></div></td>
                                <td class="px-6 py-4"><?php if ($age_days > 0): ?><span class="<?php echo $age_days > 30 ? 'text-red-400' : 'text-gray-300'; ?>"><?php echo $age_days; ?>d <?php echo $age_hours; ?>h</span><?php elseif ($age_hours > 0): ?><span class="text-emerald-400"><?php echo $age_hours; ?>h <?php echo $age_minutes; ?>m</span><?php else: ?><span class="text-emerald-400"><?php echo $age_minutes; ?>m</span><?php endif; ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" style="display:inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="download_backup"><input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>"><button type="submit" class="p-2 text-gray-400 hover:text-emerald-400 transition-colors" title="Download"><i class="fas fa-download"></i></button></form>
                                        <?php if ($can_restore): ?><form method="POST" style="display:inline" onsubmit="return confirmRestore('<?php echo htmlspecialchars($backup['name']); ?>\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">')"><input type="hidden" name="action" value="restore_backup"><input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>"><button type="submit" class="p-2 text-gray-400 hover:text-amber-400 transition-colors" title="Restore"><i class="fas fa-undo-alt"></i></button></form><?php endif; ?>
                                        <?php if ($can_delete): ?><form method="POST" style="display:inline" onsubmit="return confirmDelete('<?php echo htmlspecialchars($backup['name']); ?>\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">')"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>"><button type="submit" class="p-2 text-gray-400 hover:text-red-400 transition-colors" title="Delete"><i class="fas fa-trash-alt"></i></button></form><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-slate-800/50 border-t border-slate-700 flex flex-wrap justify-between items-center gap-4 text-sm text-gray-400">
                <div class="flex items-center gap-4"><span><i class="fas fa-archive mr-1"></i>Total: <span class="text-white font-semibold"><?php echo count($backup_files); ?></span></span><span><i class="fas fa-hard-drive mr-1"></i>Size: <span class="text-white font-semibold"><?php echo format_bytes($total_size); ?></span></span></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Best Practices -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-start gap-3"><div class="p-2 bg-amber-500/10 rounded-lg"><i class="fas fa-clock text-amber-400"></i></div><div><h3 class="text-white font-semibold mb-1">Regular Backups</h3><p class="text-xs text-gray-400">Create backups daily during low-traffic hours.</p></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-start gap-3"><div class="p-2 bg-emerald-500/10 rounded-lg"><i class="fas fa-shield-alt text-emerald-400"></i></div><div><h3 class="text-white font-semibold mb-1">Backup Retention</h3><p class="text-xs text-gray-400">Keep daily backups for 30 days, weekly for 3 months.</p></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-start gap-3"><div class="p-2 bg-red-500/10 rounded-lg"><i class="fas fa-server text-red-400"></i></div><div><h3 class="text-white font-semibold mb-1">Off-site Storage</h3><p class="text-xs text-gray-400">Download and store important backups off-site.</p></div></div></div>
    </div>
</div>

<!-- Cleanup Modal -->
<div id="cleanupModal" class="fixed inset-0 bg-black/70  hidden items-center justify-center z-50">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold text-white flex items-center gap-2"><i class="fas fa-trash-alt text-amber-400"></i>Cleanup Old Backups</h3><button onclick="closeCleanupModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button></div>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="cleanup_old">
            <div class="space-y-4">
                <p class="text-sm text-gray-400">Delete backup files older than:</p>
                <select name="days" class="w-full px-4 py-3 rounded-xl bg-primary-dark border border-slate-700 text-white focus:ring-1 focus:ring-amber-500 focus:border-amber-500 outline-none"><option value="7">7 days</option><option value="14">14 days</option><option value="30" selected>30 days</option><option value="60">60 days</option><option value="90">90 days</option><option value="180">180 days</option><option value="365">1 year</option></select>
                <div class="mb-4"><p class="text-xs text-red-400"><i class="fas fa-exclamation-triangle mr-1"></i><strong>Warning:</strong> This action cannot be undone.</p></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="submit" class="flex-1 px-4 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-colors">Delete Old Backups</button><button type="button" onclick="closeCleanupModal()" class="flex-1 px-4 py-3 bg-slate-800 border border-slate-700 text-white rounded-xl font-semibold hover:border-amber-500 transition-colors">Cancel</button></div>
        </form>
    </div>
</div>

<script>
    function showCleanupModal() { document.getElementById('cleanupModal').classList.remove('hidden'); document.getElementById('cleanupModal').classList.add('flex'); }
    function closeCleanupModal() { document.getElementById('cleanupModal').classList.add('hidden'); document.getElementById('cleanupModal').classList.remove('flex'); }
    function confirmDelete(filename) { return confirm(`Delete "${filename}"? This cannot be undone.`); }
    function confirmRestore(filename) { return confirm(`WARNING: Restoring from "${filename}" will REPLACE ALL CURRENT DATA.\n\nAre you sure?`); }

    document.addEventListener('click', function (e) { const m = document.getElementById('cleanupModal'); if (e.target === m) closeCleanupModal(); });

    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer'); if (!container) return;
        const toast = document.createElement('div');
        const colors = { success: 'bg-emerald-500 text-white', error: 'bg-red-500 text-white', info: 'bg-amber-500 text-black', warning: 'bg-red-500 text-white' };
        toast.className = `flex items-center gap-2 ${colors[type]} px-4 py-3 rounded-xl shadow-lg `;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i><span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s ease'; setTimeout(() => toast.remove(), 300); }, 3000);
    }

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.altKey && e.key === 'c') { e.preventDefault(); showCleanupModal(); }
        if (e.key === 'Escape') { closeCleanupModal(); }
    });

    function updateOnlineStatus() {
        const el = document.getElementById('connection-status');
        if (el) {
            if (navigator.onLine) { el.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>'; el.className = 'fixed bottom-4 left-4 text-xs text-emerald-400 flex items-center gap-1 bg-slate-800/80  px-3 py-2 rounded-full border border-slate-700'; }
            else { el.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>'; el.className = 'fixed bottom-4 left-4 text-xs text-red-400 flex items-center gap-1 bg-slate-800/80  px-3 py-2 rounded-full border border-slate-700'; }
        }
    }
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'backup_created') showToast('Backup created successfully', 'success');
    else if (urlParams.get('success') === 'backup_deleted') showToast('Backup deleted successfully', 'success');
    else if (urlParams.get('success') === 'backup_restored') showToast('Database restored successfully', 'success');
</script>

<?php include_once __DIR__ . '/../layouts/app_close.php'; ?>
