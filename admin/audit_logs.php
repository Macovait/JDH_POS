<?php
/**
 * Audit Log Viewer - Admin only
 * View all system activity for compliance and debugging
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Audit/AuditLogger.php';

admin_require_super_admin();

use JDH\POS\Audit\AuditLogger;

$pdo = admin_require_db(['admins']);

// Initialize logger for querying
$logger = new AuditLogger($pdo, $_SESSION['user_id'] ?? 0);

// Parse filters
$filters = [];
if (!empty($_GET['user_id'])) {
    $filters['user_id'] = (int) $_GET['user_id'];
}
if (!empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}
if (!empty($_GET['entity_type'])) {
    $filters['entity_type'] = $_GET['entity_type'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

// Get logs
$result = $logger->query($filters, $limit, $offset);
$logs = $result['records'];
$total = $result['total'];
$totalPages = (int) ceil($total / $limit);

$page_title = 'Audit Logs | JDH POS';
$current_page = 'audit_logs';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Audit Logs</h1>
        <div class="text-sm text-slate-400">
            Total: <?php echo number_format($total); ?> entries
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800 rounded-lg p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label class="block text-xs text-slate-400 mb-1">User ID</label>
                <input type="number" name="user_id" value="<?php echo htmlspecialchars($_GET['user_id'] ?? ''); ?>"
                    class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Action</label>
                <select name="action" class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="create" <?php echo ($_GET['action'] ?? '') === 'create' ? 'selected' : ''; ?>>Create</option>
                    <option value="update" <?php echo ($_GET['action'] ?? '') === 'update' ? 'selected' : ''; ?>>Update</option>
                    <option value="delete" <?php echo ($_GET['action'] ?? '') === 'delete' ? 'selected' : ''; ?>>Delete</option>
                    <option value="login" <?php echo ($_GET['action'] ?? '') === 'login' ? 'selected' : ''; ?>>Login</option>
                    <option value="view" <?php echo ($_GET['action'] ?? '') === 'view' ? 'selected' : ''; ?>>View</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Entity Type</label>
                <input type="text" name="entity_type" value="<?php echo htmlspecialchars($_GET['entity_type'] ?? ''); ?>"
                    class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-sm"
                    placeholder="e.g. product, sale">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                    class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                    class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm">
                    Filter
                </button>
                <a href="?" class="bg-slate-600 hover:bg-slate-700 px-4 py-2 rounded text-sm">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-slate-800 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700">
                    <tr>
                        <th class="px-4 py-3 text-left">Time</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Entity</th>
                        <th class="px-4 py-3 text-left">Description</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-0">
                                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                    <div class="w-20 h-20 rounded-2xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center mb-5">
                                        <i class="fas fa-shield-alt text-3xl text-blue-400"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-white mb-2">
                                        <?= $search ? 'No matching logs' : 'No audit logs' ?>
                                    </h3>
                                    <p class="text-sm text-slate-500 max-w-sm mb-5">
                                        <?= $search 
                                            ? 'Try adjusting your search filters to find the audit logs you are looking for.'
                                            : 'Audit logs will appear here when users perform actions. All activities are tracked for security compliance.'
                                        ?>
                                    </p>
                                    <?php if ($search): ?>
                                        <a href="audit_logs.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                                            <i class="fas fa-times text-xs"></i>
                                            Clear Filters
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-700/50">
                                <td class="px-4 py-3 text-slate-400">
                                    <?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($log['user_id']): ?>
                                        <span class="text-blue-400">#<?php echo $log['user_id']; ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-500">System</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $actionColors = [
                                        'create' => 'bg-green-600',
                                        'update' => 'bg-blue-600',
                                        'delete' => 'bg-red-600',
                                        'login' => 'bg-purple-600',
                                        'login_failed' => 'bg-orange-600',
                                        'view' => 'bg-slate-600'
                                    ];
                                    $color = $actionColors[$log['action']] ?? 'bg-slate-600';
                                    ?>
                                    <span class="<?php echo $color; ?> px-2 py-1 rounded text-xs">
                                        <?php echo ucfirst($log['action']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-slate-300"><?php echo $log['entity_type']; ?></span>
                                    <?php if ($log['entity_id']): ?>
                                        <span class="text-slate-500">#<?php echo $log['entity_id']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 max-w-md">
                                    <?php echo htmlspecialchars($log['description'] ?? '-'); ?>
                                    <?php if ($log['new_values'] || $log['old_values']): ?>
                                        <button onclick="toggleDetails(<?php echo $log['id']; ?>)" 
                                            class="text-blue-400 hover:text-blue-300 text-xs ml-2">
                                            [Details]
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-xs">
                                    <?php echo $log['ip_address']; ?>
                                </td>
                            </tr>
                            <?php if ($log['new_values'] || $log['old_values']): ?>
                                <tr id="details-<?php echo $log['id']; ?>" class="hidden bg-slate-900/50">
                                    <td colspan="6" class="px-4 py-3">
                                        <div class="grid grid-cols-2 gap-4 text-xs">
                                            <?php if ($log['old_values']): ?>
                                                <div>
                                                    <span class="text-red-400 font-semibold">Before:</span>
                                                    <pre class="mt-1 p-2 bg-slate-800 rounded overflow-x-auto"><?php echo json_encode($log['old_values'], JSON_PRETTY_PRINT); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($log['new_values']): ?>
                                                <div>
                                                    <span class="text-green-400 font-semibold">After:</span>
                                                    <pre class="mt-1 p-2 bg-slate-800 rounded overflow-x-auto"><?php echo json_encode($log['new_values'], JSON_PRETTY_PRINT); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-4 py-3 bg-slate-700 flex justify-between items-center">
                <div class="text-sm text-slate-400">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                            class="bg-slate-600 hover:bg-slate-500 px-3 py-1 rounded text-sm">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                            class="bg-slate-600 hover:bg-slate-500 px-3 py-1 rounded text-sm">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    row.classList.toggle('hidden');
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>

