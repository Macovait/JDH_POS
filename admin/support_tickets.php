<?php
/**
 * Support Tickets Management
 * Platform-wide support ticket queue for superadmins.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

// Generate CSRF token
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
    try {
        $action = $_POST['action'] ?? '';
        $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
        switch ($action) {
            case 'update_status':
                db_update('support_tickets', [
                    'status' => $_POST['status'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$ticket_id]);
                $message = 'Ticket status updated.';
                $message_type = 'success';
                break;
            case 'assign':
                db_update('support_tickets', [
                    'assigned_to_admin_id' => (int) $_POST['admin_id'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$ticket_id]);
                $message = 'Ticket assigned.';
                $message_type = 'success';
                break;
            case 'reply':
                // For now, log reply as a tenant note
                db_insert('tenant_notes', [
                    'tenant_id' => (int) $_POST['tenant_id'],
                    'type' => 'support',
                    'note' => "Support reply on ticket {$_POST['ticket_number']}: " . $_POST['reply_text'],
                    'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                db_update('support_tickets', [
                    'status' => $_POST['status'] === 'resolved' ? 'resolved' : 'waiting_for_customer',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$ticket_id]);
                $message = 'Reply sent.';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// ---- FILTERS ----
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
if ($status_filter) {
    $where[] = 't.status = ?';
    $params[] = $status_filter;
}
if ($priority_filter) {
    $where[] = 't.priority = ?';
    $params[] = $priority_filter;
}
if ($category_filter) {
    $where[] = 't.category = ?';
    $params[] = $category_filter;
}
if ($search) {
    $where[] = '(t.subject LIKE ? OR t.ticket_number LIKE ? OR tn.name LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = implode(' AND ', $where);

// ---- STATS ----
$openCount = db_fetch_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'");
$resolvedToday = db_fetch_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()");
$highPriority = db_fetch_value("SELECT COUNT(*) FROM support_tickets WHERE status = 'open' AND priority = 'high'");
$avgResponse = db_fetch_value("SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)), 0) FROM support_tickets WHERE status != 'open' AND updated_at > created_at");

// ---- TICKETS ----
$total = db_fetch_value("SELECT COUNT(*) FROM support_tickets t WHERE {$whereSql}", $params);

$tickets = db_fetch_all(
    "SELECT t.*, tn.name as tenant_name, tn.slug, a.name as assigned_name
     FROM support_tickets t
     LEFT JOIN pos_tenants tn ON t.tenant_id = tn.id
     LEFT JOIN admins a ON t.assigned_to_admin_id = a.id
     WHERE {$whereSql}
     ORDER BY FIELD(t.status, 'open', 'waiting_for_customer', 'in_progress', 'resolved', 'closed'),
              FIELD(t.priority, 'critical', 'high', 'medium', 'low'),
              t.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// ---- ADMINS FOR ASSIGNMENT ----
$admins = db_fetch_all("SELECT id, name FROM admins WHERE role IN ('admin', 'support', 'super_admin') ORDER BY name");

$current_page = 'support_access';
$page_title = 'Support Tickets';
$breadcrumbs = [
    ['label' => 'Platform', 'url' => 'dashboard.php'],
    ['label' => 'Support Tickets']
];

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Support Tickets</h1>
            <p class="text-slate-400 text-sm mt-1">Manage platform support requests</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/20 border-red-500/30 text-red-400' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Open</p>
            <p class="text-white font-semibold text-lg"><?= $openCount ?></p>
            <p class="text-slate-500 text-xs">Awaiting response</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Resolved Today</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $resolvedToday ?></p>
            <p class="text-slate-500 text-xs">Closed tickets</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">High Priority</p>
            <p class="text-red-400 font-semibold text-lg"><?= $highPriority ?></p>
            <p class="text-slate-500 text-xs">Needs attention</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Avg Response</p>
            <p class="text-white font-semibold text-lg"><?= (int) $avgResponse ?>m</p>
            <p class="text-slate-500 text-xs">First response time</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tickets..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="waiting_for_customer" <?= $status_filter === 'waiting_for_customer' ? 'selected' : '' ?>>Waiting for Customer</option>
                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
            <select name="priority" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Priority</option>
                <option value="critical" <?= $priority_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
                <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
            <select name="category" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Categories</option>
                <option value="billing" <?= $category_filter === 'billing' ? 'selected' : '' ?>>Billing</option>
                <option value="technical" <?= $category_filter === 'technical' ? 'selected' : '' ?>>Technical</option>
                <option value="account" <?= $category_filter === 'account' ? 'selected' : '' ?>>Account</option>
                <option value="feature_request" <?= $category_filter === 'feature_request' ? 'selected' : '' ?>>Feature Request</option>
                <option value="other" <?= $category_filter === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="support_tickets.php" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <form method="POST" action="export_csv.php" class="inline" target="_blank">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="table" value="support_tickets">
        <input type="hidden" name="columns" value='[{"field":"id","label":"Ticket ID"},{"field":"tenant_id","label":"Tenant ID"},{"field":"subject","label":"Subject"},{"field":"status","label":"Status","format":"status"},{"field":"priority","label":"Priority"},{"field":"category","label":"Category"},{"field":"assigned_to_admin_id","label":"Assigned To"},{"field":"created_at","label":"Created","format":"date"}]'>
        <input type="hidden" name="filename" value="support_tickets">
        <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 mb-4"><i class="fas fa-download mr-1"></i> Export CSV</button>
    </form>

    <!-- Tickets Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Ticket</th>
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Subject</th>
                        <th class="px-3 py-2.5">Priority</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Assigned</th>
                        <th class="px-3 py-2.5">Created</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-0">
                            <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                <div class="w-20 h-20 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center mb-5">
                                    <i class="fas fa-ticket-alt text-3xl text-purple-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-white mb-2">
                                    <?= $search ? 'No matching tickets' : 'No support tickets' ?>
                                </h3>
                                <p class="text-sm text-slate-500 max-w-sm mb-5">
                                    <?= $search 
                                        ? 'Try adjusting your search or filters to find the tickets you are looking for.'
                                        : 'Support tickets will appear here when tenants submit requests. All tickets are tracked and monitored.'
                                    ?>
                                </p>
                                <?php if ($search): ?>
                                    <a href="support_tickets.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                                        <i class="fas fa-times text-xs"></i>
                                        Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($tickets as $tk): ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <p class="text-white font-mono text-xs"><?= htmlspecialchars($tk['ticket_number']) ?></p>
                            <span class="text-slate-500 text-xs"><?= ucfirst($tk['category']) ?></span>
                        </td>
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $tk['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm">
                                <?= htmlspecialchars($tk['tenant_name'] ?? 'Unknown') ?>
                            </a>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($tk['slug'] ?? '') ?></p>
                        </td>
                        <td class="px-3 py-2.5 text-white text-sm max-w-xs truncate" title="<?= htmlspecialchars($tk['subject']) ?>">
                            <?= htmlspecialchars($tk['subject']) ?>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs <?= $tk['priority'] === 'critical' ? 'bg-red-500/20 text-red-400' : ($tk['priority'] === 'high' ? 'bg-orange-500/20 text-orange-400' : ($tk['priority'] === 'medium' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-slate-500/20 text-slate-400')) ?>">
                                <?= ucfirst($tk['priority']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded text-xs <?= $tk['status'] === 'open' ? 'bg-red-500/20 text-red-400' : ($tk['status'] === 'in_progress' ? 'bg-blue-500/20 text-blue-400' : ($tk['status'] === 'resolved' ? 'bg-emerald-500/20 text-emerald-400' : ($tk['status'] === 'waiting_for_customer' ? 'bg-purple-500/20 text-purple-400' : 'bg-slate-500/20 text-slate-400'))) ?>">
                                <?= ucfirst(str_replace('_', ' ', $tk['status'])) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= htmlspecialchars($tk['assigned_name'] ?? 'Unassigned') ?></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, g:i a', strtotime($tk['created_at'])) ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openModal('replyModal<?= $tk['id'] ?>')" class="w-8 h-8 bg-indigo-500/20 rounded-lg flex items-center justify-center text-indigo-400 hover:bg-indigo-500/30" title="Reply"><i class="fas fa-reply text-xs"></i></button>
                                <button onclick="openModal('assignModal<?= $tk['id'] ?>')" class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-500/30" title="Assign"><i class="fas fa-user-plus text-xs"></i></button>
                            </div>
                        </td>
                    </tr>

                    <!-- Reply Modal -->
                    <div id="replyModal<?= $tk['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-lg mx-4">
                            <h3 class="text-lg font-bold text-white mb-4">Reply to <?= htmlspecialchars($tk['ticket_number']) ?></h3>
                            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="ticket_id" value="<?= $tk['id'] ?>">
                                <input type="hidden" name="tenant_id" value="<?= $tk['tenant_id'] ?>">
                                <input type="hidden" name="ticket_number" value="<?= htmlspecialchars($tk['ticket_number']) ?>">
                                <div class="mb-3">
                                    <p class="text-slate-400 text-xs mb-1">Subject</p>
                                    <p class="text-white text-sm"><?= htmlspecialchars($tk['subject']) ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-slate-400 text-xs mb-1">Reply</label>
                                    <textarea name="reply_text" rows="4" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" required></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-slate-400 text-xs mb-1">Set Status</label>
                                    <select name="status" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        <option value="waiting_for_customer">Waiting for Customer</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="in_progress">In Progress</option>
                                    </select>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('replyModal<?= $tk['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Send Reply</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Assign Modal -->
                    <div id="assignModal<?= $tk['id'] ?>" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
                        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
                            <h3 class="text-lg font-bold text-white mb-4">Assign Ticket</h3>
                            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="ticket_id" value="<?= $tk['id'] ?>">
                                <div class="mb-4">
                                    <label class="block text-slate-400 text-xs mb-1">Assign to</label>
                                    <select name="admin_id" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($admins as $ad): ?>
                                        <option value="<?= $ad['id'] ?>"><?= htmlspecialchars($ad['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('assignModal<?= $tk['id'] ?>')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Assign</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-700/60 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&priority=<?= urlencode($priority_filter) ?>&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&priority=<?= urlencode($priority_filter) ?>&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&priority=<?= urlencode($priority_filter) ?>&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.getElementById(id).classList.remove('flex');
}
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

