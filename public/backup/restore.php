<?php
/**
 * Database Restore page for Jakababa POS
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('restore.run') && !is_super_admin()) {
    enforce_permission('restore.run');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);

$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) { mkdir($backup_dir, 0755, true); }

$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql' && is_file($backup_dir . $file)) {
            $fp = $backup_dir . $file;
            $backup_files[] = ['name' => $file, 'size' => filesize($fp), 'modified' => filemtime($fp), 'path' => $fp];
        }
    }
    usort($backup_files, fn($a, $b) => $b['modified'] - $a['modified']);
}

$db_name = ''; $db_size = 0; $table_count = 0;
try {
    $stmt = $pdo->query('SELECT DATABASE() as db_name'); $db_name = $stmt->fetch()['db_name'];
    $stmt = $pdo->query('SHOW TABLES'); $table_count = $stmt->rowCount();
    $stmt = $pdo->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()");
    $db_size = $stmt->fetch()['size'] ?? 0;
} catch (PDOException $e) { error_log("Error getting database info: " . $e->getMessage()); }

$backup_message = '';
$backup_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_backup') {
        $backup_message = 'Backup creation requested. Please use CLI for actual backup.';
    } elseif ($_POST['action'] === 'delete_backup' && isset($_POST['backup_file'])) {
        if (is_super_admin() || $user_role === 'Admin') {
            $backup_file = basename($_POST['backup_file']);
            $file_path = $backup_dir . $backup_file;
            if (file_exists($file_path) && is_file($file_path)) {
                unlink($file_path);
                $backup_message = 'Backup file deleted successfully';
                log_activity($user_id, 'backup.deleted', ['file' => $backup_file], null, get_current_tenant_id());
                header('Location: restore.php?success=deleted'); exit;
            } else { $backup_error = 'Backup file not found'; }
        }
    } elseif ($_POST['action'] === 'download_backup' && isset($_POST['backup_file'])) {
        $backup_file = basename($_POST['backup_file']);
        $file_path = $backup_dir . $backup_file;
        if (file_exists($file_path) && is_file($file_path)) {
            header('Content-Type: application/sql'); header('Content-Disposition: attachment; filename="' . $backup_file . '"');
            header('Content-Length: ' . filesize($file_path)); readfile($file_path); exit;
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'deleted') { $backup_message = 'Backup file deleted successfully'; }

$page_title = 'Database Restore | Jakababa POS';
$can_delete = is_super_admin() || $user_role === 'Admin';
include_once __DIR__ . '/../layouts/app.php';
?>

<style>
    .inline-flex items-center justify-center { display: inline-flex; align-items: center; justify-content: center; }
    .cli-command { background: #1E1E1E; color: #D4D4D4; font-family: 'Courier New', monospace; padding: 1rem; border-radius: 0.75rem; border: 1px solid #374151; overflow-x: auto; }
    .cli-command .prompt { color: #10B981; } .cli-command .command { color: #FBBF24; }
    .backup-row-hover:hover { background: rgba(251, 191, 36, 0.05); }
    .mb-4 { background: rgba(239, 68, 68, 0.1); border-left: 4px solid #EF4444; }
    button { cursor: pointer; position: relative; z-index: 10; }
</style>

<div class="fade-in">

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 ">
        <div><h1 class="text-3xl font-bold text-amber-400">Database Management</h1><p class="text-gray-400 mt-1">Backup, restore, and manage your database</p></div>
        <div class="flex gap-2">
            <button id="createBackupBtn" type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-black rounded-xl font-semibold hover:bg-amber-600 transition-all">
                <div class="inline-flex items-center justify-center w-4 h-4" data-icon="document-arrow-down" data-type="outline"></div><span class="text-sm">Create Backup</span>
            </button>
            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/50 rounded-xl border border-slate-700 text-gray-300 hover:text-white hover:border-amber-500 transition-all">
                <div class="inline-flex items-center justify-center w-4 h-4" data-icon="arrow-path" data-type="outline"></div><span class="text-sm">Refresh</span>
            </button>
        </div>
    </div>

    <?php if ($backup_message): ?><div class="mb-6 bg-emerald-500/10 border border-emerald-500 rounded-xl p-4 "><div class="flex items-center gap-3 text-emerald-400"><div class="inline-flex items-center justify-center w-5 h-5" data-icon="check-circle" data-type="outline"></div><span><?php echo htmlspecialchars($backup_message); ?></span></div></div><?php endif; ?>
    <?php if ($backup_error): ?><div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4 "><div class="flex items-start gap-3 text-red-400"><div class="inline-flex items-center justify-center w-5 h-5 flex-shrink-0 mt-0.5" data-icon="exclamation-triangle" data-type="outline"></div><div><?php echo htmlspecialchars($backup_error); ?></div></div></div><?php endif; ?>

    <!-- Database Info Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8 ">
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500">Database Name</p><p class="text-lg font-bold text-white font-mono"><?php echo htmlspecialchars($db_name ?: 'jakababa_pos'); ?></p></div><div class="p-3 bg-amber-500/10 rounded-xl"><div class="inline-flex items-center justify-center w-6 h-6 text-amber-400" data-icon="circle-stack" data-type="outline"></div></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500">Tables</p><p class="text-2xl font-bold text-emerald-400"><?php echo number_format($table_count); ?></p></div><div class="p-3 bg-emerald-500/10 rounded-xl"><div class="inline-flex items-center justify-center w-6 h-6 text-emerald-400" data-icon="table-cells" data-type="outline"></div></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500">Database Size</p><p class="text-lg font-bold text-amber-400"><?php echo $db_size > 1048576 ? round($db_size / 1048576, 2) . ' MB' : ($db_size > 1024 ? round($db_size / 1024, 2) . ' KB' : $db_size . ' bytes'); ?></p></div><div class="p-3 bg-amber-500/10 rounded-xl"><div class="inline-flex items-center justify-center w-6 h-6 text-amber-400" data-icon="hard-drive" data-type="outline"></div></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-center justify-between"><div><p class="text-xs text-gray-500">Backups Available</p><p class="text-2xl font-bold text-red-400"><?php echo count($backup_files); ?></p></div><div class="p-3 bg-red-500/10 rounded-xl"><div class="inline-flex items-center justify-center w-6 h-6 text-red-400" data-icon="archive-box" data-type="outline"></div></div></div></div>
    </div>

    <!-- Security Warning -->
    <div class="mb-4 bg-slate-800/40 rounded-xl border border-slate-700 p-4 mb-6 ">
        <div class="flex items-start gap-3"><div class="inline-flex items-center justify-center w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" data-icon="shield-exclamation" data-type="outline"></div><div><h3 class="text-red-400 font-semibold mb-1">Security Notice</h3><p class="text-sm text-gray-400">Database restore operations are restricted to CLI only for security. This prevents unauthorized access and accidental data loss.</p></div></div>
    </div>

    <!-- CLI Instructions -->
    <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-6 mb-8 ">
        <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-4"><div class="inline-flex items-center justify-center w-5 h-5 text-amber-400" data-icon="command-line" data-type="outline"></div>CLI Restore Instructions</h2>
        <div class="space-y-4">
            <p class="text-sm text-gray-400">To restore a database backup, use the following command:</p>
            <div class="cli-command"><span class="prompt">$</span><span class="command"> php scripts/restore_cli.php /path/to/backup.sql</span></div>
            <p class="text-sm text-gray-400 mt-2">Example:</p>
            <div class="cli-command"><span class="prompt">$</span><span class="command"> php scripts/restore_cli.php <?php echo $backup_dir; ?>backup_<?php echo date('Y-m-d'); ?>.sql</span></div>
            <div class="bg-amber-500/5 rounded-xl p-3 mt-2"><p class="text-xs text-gray-400"><strong class="text-amber-400">Note:</strong> The restore script will prompt for confirmation. This operation cannot be undone.</p></div>
        </div>
    </div>

    <!-- Available Backups -->
    <div class="bg-slate-800/40 rounded-xl border border-slate-700 overflow-hidden ">
        <div class="p-4 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2"><div class="inline-flex items-center justify-center w-5 h-5 text-amber-400" data-icon="archive-box" data-type="outline"></div>Available Backups</h2>
            <span class="text-sm text-gray-400"><?php echo count($backup_files); ?> files</span>
        </div>

        <?php if (empty($backup_files)): ?>
            <div class="p-12 text-center text-gray-500"><div class="flex flex-col items-center gap-3"><div class="inline-flex items-center justify-center w-16 h-16 text-gray-600" data-icon="archive-box" data-type="outline"></div><p class="text-lg">No backups found</p><p class="text-sm max-w-md">Use the CLI command above to create your first backup.</p></div></div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/50 border-b border-slate-700"><tr><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Filename</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Size</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Modified</th><th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach ($backup_files as $backup): ?>
                            <tr class="backup-row-hover transition-colors">
                                <td class="px-6 py-4"><div class="flex items-center gap-2"><div class="inline-flex items-center justify-center w-4 h-4 text-amber-400" data-icon="document-text" data-type="outline"></div><span class="font-mono text-sm text-white"><?php echo htmlspecialchars($backup['name']); ?></span></div></td>
                                <td class="px-6 py-4"><?php $s = $backup['size']; echo $s > 1048576 ? round($s / 1048576, 2) . ' MB' : ($s > 1024 ? round($s / 1024, 2) . ' KB' : $s . ' bytes'); ?></td>
                                <td class="px-6 py-4"><div class="flex flex-col"><span class="text-white"><?php echo date('M j, Y', $backup['modified']); ?></span><span class="text-xs text-gray-500"><?php echo date('H:i:s', $backup['modified']); ?></span></div></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="showRestoreInstructions('<?php echo htmlspecialchars($backup['name']); ?>')" class="p-2 text-gray-400 hover:text-amber-400 transition-colors" title="Restore command"><div class="inline-flex items-center justify-center w-5 h-5" data-icon="command-line" data-type="outline"></div></button>
                                        <form method="POST" style="display:inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="action" value="download_backup"><input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>"><button type="submit" class="p-2 text-gray-400 hover:text-emerald-400 transition-colors" title="Download"><div class="inline-flex items-center justify-center w-5 h-5" data-icon="arrow-down-tray" data-type="outline"></div></button></form>
                                        <?php if ($can_delete): ?><form method="POST" style="display:inline" onsubmit="return confirmDelete('<?php echo htmlspecialchars($backup['name']); ?>\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">')"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>"><button type="submit" class="p-2 text-gray-400 hover:text-red-400 transition-colors" title="Delete"><div class="inline-flex items-center justify-center w-5 h-5" data-icon="trash" data-type="outline"></div></button></form><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-slate-800/50 border-t border-slate-700 flex justify-between items-center text-sm text-gray-400">
                <div class="flex items-center gap-2"><div class="inline-flex items-center justify-center w-4 h-4" data-icon="archive-box" data-type="outline"></div><span>Total: <span class="text-white font-semibold"><?php echo count($backup_files); ?></span></span></div>
                <span class="flex items-center gap-1"><span class="w-2 h-2 bg-amber-500 rounded-full"></span>CLI Only</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Maintenance Tips -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-start gap-3"><div class="p-2 bg-amber-500/10 rounded-lg"><div class="inline-flex items-center justify-center w-5 h-5 text-amber-400" data-icon="clock" data-type="outline"></div></div><div><h3 class="text-white font-semibold mb-1">Regular Backups</h3><p class="text-sm text-gray-400">Schedule backups using cron jobs. Recommended: daily for active systems.</p><div class="mt-2 cli-command text-xs"><span class="prompt">$</span><span class="command"> 0 2 * * * php /path/to/scripts/backup_cli.php</span></div></div></div></div>
        <div class="bg-slate-800/40 rounded-xl border border-slate-700 p-4"><div class="flex items-start gap-3"><div class="p-2 bg-amber-500/10 rounded-lg"><div class="inline-flex items-center justify-center w-5 h-5 text-amber-400" data-icon="shield-check" data-type="outline"></div></div><div><h3 class="text-white font-semibold mb-1">Backup Security</h3><p class="text-sm text-gray-400">Store backups securely. Consider encrypting sensitive backups and keeping off-site copies.</p></div></div></div>
    </div>
</div>

<!-- Restore Instructions Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black/50  flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 max-w-lg w-full mx-4">
        <div class="flex items-center justify-between mb-4"><h3 class="text-xl font-semibold text-white flex items-center gap-2"><div class="inline-flex items-center justify-center w-6 h-6 text-amber-400" data-icon="command-line" data-type="outline"></div>Restore Command</h3><button onclick="closeRestoreModal()" class="text-gray-400 hover:text-white"><div class="inline-flex items-center justify-center w-5 h-5" data-icon="x-mark" data-type="outline"></div></button></div>
        <div class="space-y-4">
            <p class="text-sm text-gray-400">To restore <span id="restoreFileName" class="text-amber-400 font-mono"></span>, run:</p>
            <div class="cli-command" id="restoreCommand"></div>
            <div class="bg-red-500/10 border border-red-500 rounded-xl p-3"><p class="text-xs text-red-400"><strong>Warning:</strong> This will completely replace your current database.</p></div>
        </div>
        <div class="flex justify-end mt-6"><button onclick="closeRestoreModal()" class="px-4 py-2 bg-slate-800 border border-slate-700 text-white rounded-xl hover:border-amber-500 transition-colors">Close</button></div>
    </div>
</div>

<!-- Backup Instructions Modal -->
<div id="backupModal" class="fixed inset-0 bg-black/50  flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 max-w-lg w-full mx-4">
        <div class="flex items-center justify-between mb-4"><h3 class="text-xl font-semibold text-white flex items-center gap-2"><div class="inline-flex items-center justify-center w-6 h-6 text-amber-400" data-icon="document-arrow-down" data-type="outline"></div>Create Backup</h3><button onclick="closeBackupModal()" class="text-gray-400 hover:text-white"><div class="inline-flex items-center justify-center w-5 h-5" data-icon="x-mark" data-type="outline"></div></button></div>
        <div class="space-y-4">
            <p class="text-sm text-gray-400">To create a database backup, use the following command:</p>
            <div class="cli-command"><span class="prompt">$</span><span class="command"> php scripts/backup_cli.php <?php echo $backup_dir; ?>backup_<?php echo date('Y-m-d_His'); ?>.sql</span></div>
            <p class="text-sm text-gray-400 mt-2">This will create a timestamped backup file.</p>
        </div>
        <div class="flex justify-end mt-6"><button onclick="closeBackupModal()" class="px-4 py-2 bg-slate-800 border border-slate-700 text-white rounded-xl hover:border-amber-500 transition-colors">Close</button></div>
    </div>
</div>

<script>
    window.showBackupInstructions = function () { document.getElementById('backupModal').classList.remove('hidden'); };
    window.closeBackupModal = function () { document.getElementById('backupModal').classList.add('hidden'); };
    window.showRestoreInstructions = function (filename) {
        document.getElementById('restoreFileName').textContent = filename;
        document.getElementById('restoreCommand').innerHTML = '<span class="prompt">$</span><span class="command"> php scripts/restore_cli.php <?php echo $backup_dir; ?>' + filename + '</span>';
        document.getElementById('restoreModal').classList.remove('hidden');
    };
    window.closeRestoreModal = function () { document.getElementById('restoreModal').classList.add('hidden'); };
    window.confirmDelete = function (filename) { return confirm('Delete "' + filename + '"? This cannot be undone.'); };

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('createBackupBtn')?.addEventListener('click', function (e) { e.preventDefault(); window.showBackupInstructions(); });
        document.querySelectorAll('.fixed').forEach(modal => { modal.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); }); });
        if (window.heroicons) window.heroicons.render();
    });

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.altKey && e.key === 'b') { e.preventDefault(); window.showBackupInstructions(); }
        if (e.key === 'Escape') { window.closeRestoreModal(); window.closeBackupModal(); }
    });

    function updateOnlineStatus() {
        const el = document.getElementById('connection-status');
        if (el) {
            if (navigator.onLine) { el.innerHTML = '<div class="inline-flex items-center justify-center w-3 h-3" data-icon="wifi" data-type="outline"></div><span>Online</span>'; el.className = 'fixed bottom-4 left-4 text-xs text-emerald-400 flex items-center gap-1 bg-slate-800/80  px-3 py-2 rounded-full border border-slate-700'; }
            else { el.innerHTML = '<div class="inline-flex items-center justify-center w-3 h-3" data-icon="wifi-slash" data-type="outline"></div><span>Offline</span>'; el.className = 'fixed bottom-4 left-4 text-xs text-red-400 flex items-center gap-1 bg-slate-800/80  px-3 py-2 rounded-full border border-slate-700'; }
        }
    }
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
</script>

<?php include_once __DIR__ . '/../layouts/app_close.php'; ?>
