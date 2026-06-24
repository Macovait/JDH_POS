<?php
/**
 * Billing Invoice Generator
 * Create manual invoices and credit notes for tenants.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

$message = '';
$message_type = '';

// Generate CSRF token
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Invalid security token. Please refresh and try again.';
        $message_type = 'error';
    } else {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_invoice') {
            $tenant_id = (int) $_POST['tenant_id'];
            $amount = (float) $_POST['amount'];
            $currency = $_POST['currency'] ?? 'USD';
            $description = $_POST['description'] ?? '';
            $period_start = $_POST['period_start'] ?? date('Y-m-d');
            $period_end = $_POST['period_end'] ?? date('Y-m-d');

            // Create invoice
            $invoiceData = [
                'tenant_id' => $tenant_id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $_POST['auto_charge'] ? 'pending' : 'draft',
                'description' => $description,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'due_date' => $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
                'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            db_insert('pos_invoices', $invoiceData);
            $invoiceId = $pdo->lastInsertId();

            // Add line item
            db_insert('invoice_line_items', [
                'invoice_id' => $invoiceId,
                'description' => $description ?: 'Manual charge',
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $message = "Invoice #{$invoiceId} created.";
            $message_type = 'success';
        } elseif ($action === 'create_credit_note') {
            $tenant_id = (int) $_POST['tenant_id'];
            $amount = (float) $_POST['amount'];
            $reason = $_POST['reason'] ?? '';
            $original_invoice_id = !empty($_POST['original_invoice_id']) ? (int) $_POST['original_invoice_id'] : null;

            db_insert('credit_notes', [
                'tenant_id' => $tenant_id,
                'original_invoice_id' => $original_invoice_id,
                'amount' => $amount,
                'currency' => $_POST['currency'] ?? 'USD',
                'reason' => $reason,
                'status' => 'open',
                'created_by_admin_id' => $_SESSION['admin_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $message = 'Credit note created.';
            $message_type = 'success';
        } elseif ($action === 'mark_paid') {
            db_update('pos_invoices', [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $_POST['invoice_id']]);
            $message = 'Invoice marked as paid.';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// ---- FILTERS ----
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tab = $_GET['tab'] ?? 'invoices';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// ---- STATS ----
$totalInvoices = db_fetch_value("SELECT COUNT(*) FROM pos_invoices");
$totalUnpaid = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM pos_invoices WHERE status IN ('pending','overdue')");
$totalPaid = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM pos_invoices WHERE status = 'paid'");
$totalCreditNotes = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM credit_notes WHERE status = 'open'");

$tenants = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name ASC LIMIT 50");

if ($tab === 'invoices') {
    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(t.name LIKE ? OR i.description LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($status_filter) {
        $where[] = 'i.status = ?';
        $params[] = $status_filter;
    }
    $whereSql = implode(' AND ', $where);
    $total = db_fetch_value("SELECT COUNT(*) FROM pos_invoices i JOIN pos_tenants t ON i.tenant_id = t.id WHERE {$whereSql}", $params);
    $items = db_fetch_all(
        "SELECT i.*, t.name as tenant_name, t.slug
         FROM pos_invoices i
         JOIN pos_tenants t ON i.tenant_id = t.id
         WHERE {$whereSql}
         ORDER BY i.created_at DESC
         LIMIT {$per_page} OFFSET {$offset}",
        $params
    );
} else {
    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(t.name LIKE ? OR cn.reason LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($status_filter) {
        $where[] = 'cn.status = ?';
        $params[] = $status_filter;
    }
    $whereSql = implode(' AND ', $where);
    $total = db_fetch_value("SELECT COUNT(*) FROM credit_notes cn JOIN pos_tenants t ON cn.tenant_id = t.id WHERE {$whereSql}", $params);
    $items = db_fetch_all(
        "SELECT cn.*, t.name as tenant_name, t.slug, i.id as orig_invoice_num
         FROM credit_notes cn
         JOIN pos_tenants t ON cn.tenant_id = t.id
         LEFT JOIN pos_invoices i ON cn.original_invoice_id = i.id
         WHERE {$whereSql}
         ORDER BY cn.created_at DESC
         LIMIT {$per_page} OFFSET {$offset}",
        $params
    );
}

$current_page = 'revenue';
$page_title = 'Invoice Generator';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Invoice Generator</h1>
            <p class="text-slate-400 text-sm mt-1">Create and manage manual invoices & credit notes</p>
        </div>
        <div class="flex gap-2">
            <?php if ($tab === 'invoices'): ?>
            <button onclick="openModal('invoiceModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold"><i class="fas fa-plus mr-1"></i> New Invoice</button>
            <?php else: ?>
            <button onclick="openModal('creditModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold"><i class="fas fa-plus mr-1"></i> New Credit Note</button>
            <?php endif; ?>
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
            <p class="text-slate-400 text-xs mb-1">Total Invoices</p>
            <p class="text-white font-semibold text-lg"><?= $totalInvoices ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Unpaid</p>
            <p class="text-rose-400 font-semibold text-lg">$<?= number_format($totalUnpaid, 2) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Paid</p>
            <p class="text-emerald-400 font-semibold text-lg">$<?= number_format($totalPaid, 2) ?></p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Open Credit Notes</p>
            <p class="text-amber-400 font-semibold text-lg">$<?= number_format($totalCreditNotes, 2) ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4 border-b border-slate-700/60 pb-2">
        <a href="?tab=invoices" class="px-4 py-2 text-sm rounded-lg transition <?= $tab === 'invoices' ? 'bg-amber-500/20 text-amber-400 font-medium' : 'text-slate-400 hover:text-white' ?>">Invoices</a>
        <a href="?tab=credit_notes" class="px-4 py-2 text-sm rounded-lg transition <?= $tab === 'credit_notes' ? 'bg-amber-500/20 text-amber-400 font-medium' : 'text-slate-400 hover:text-white' ?>">Credit Notes</a>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm flex-1 min-w-[200px]">
            <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                <option value="">All Status</option>
                <?php if ($tab === 'invoices'): ?>
                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <?php else: ?>
                <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="applied" <?= $status_filter === 'applied' ? 'selected' : '' ?>>Applied</option>
                <option value="void" <?= $status_filter === 'void' ? 'selected' : '' ?>>Void</option>
                <?php endif; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            <a href="invoice_generator.php?tab=<?= $tab ?>" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Clear</a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="px-3 py-2.5">Tenant</th>
                        <th class="px-3 py-2.5">Description</th>
                        <th class="px-3 py-2.5 text-right">Amount</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Date</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-500"><i class="fas fa-file-invoice text-3xl mb-3 block"></i>No records found</td></tr>
                    <?php else: foreach ($items as $it):
                        if ($tab === 'invoices') {
                            $statusColor = match($it['status']) {
                                'paid' => 'bg-emerald-500/20 text-emerald-400',
                                'pending' => 'bg-amber-500/20 text-amber-400',
                                'draft' => 'bg-slate-500/20 text-slate-400',
                                'overdue' => 'bg-red-500/20 text-red-400',
                                default => 'bg-slate-500/20 text-slate-400',
                            };
                        } else {
                            $statusColor = match($it['status']) {
                                'open' => 'bg-emerald-500/20 text-emerald-400',
                                'applied' => 'bg-blue-500/20 text-blue-400',
                                'void' => 'bg-slate-500/20 text-slate-400',
                                default => 'bg-slate-500/20 text-slate-400',
                            };
                        }
                    ?>
                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition">
                        <td class="px-3 py-2.5">
                            <a href="tenant_detail.php?id=<?= $it['tenant_id'] ?>" class="text-white hover:text-amber-400 transition text-sm"><?= htmlspecialchars($it['tenant_name']) ?></a>
                        </td>
                        <td class="px-3 py-2.5 text-slate-300 text-xs max-w-xs truncate"><?= htmlspecialchars($it['description'] ?? $it['reason'] ?? '') ?></td>
                        <td class="px-3 py-2.5 text-right text-white text-sm font-medium">$<?= number_format($it['amount'], 2) ?> <?= htmlspecialchars($it['currency'] ?? 'USD') ?></td>
                        <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded text-[10px] <?= $statusColor ?>"><?= ucfirst($it['status']) ?></span></td>
                        <td class="px-3 py-2.5 text-slate-400 text-xs"><?= date('M j, Y', strtotime($it['created_at'])) ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <?php if ($tab === 'invoices' && $it['status'] === 'pending'): ?>
                            <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="invoice_id" value="<?= $it['id'] ?>">
                                <button type="submit" class="px-3 py-1 bg-emerald-500/20 rounded text-emerald-400 text-xs hover:bg-emerald-500/30">Mark Paid</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $totalPages = ceil($total / $per_page); if ($totalPages > 1): ?>
        <div class="px-5 py-4 border-t border-slate-700/60 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&tab=<?= $tab ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&tab=<?= $tab ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&tab=<?= $tab ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Invoice Modal -->
<div id="invoiceModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-lg font-bold text-white mb-4">Create Invoice</h3>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="create_invoice">
            <div class="space-y-3">
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Tenant</label>
                    <select name="tenant_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="">Select tenant...</option>
                        <?php foreach ($tenants as $tn): ?>
                        <option value="<?= $tn['id'] ?>"><?= htmlspecialchars($tn['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Amount</label>
                    <input type="number" step="0.01" name="amount" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Currency</label>
                    <select name="currency" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Description</label>
                    <input type="text" name="description" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Monthly subscription...">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Period Start</label>
                        <input type="date" name="period_start" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs mb-1">Period End</label>
                        <input type="date" name="period_end" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Due Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                </div>
                <label class="flex items-center gap-2 text-slate-300 text-sm">
                    <input type="checkbox" name="auto_charge" value="1" checked>
                    Auto-charge (mark as pending)
                </label>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('invoiceModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Credit Note Modal -->
<div id="creditModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 w-full max-w-sm mx-4">
        <h3 class="text-lg font-bold text-white mb-4">Create Credit Note</h3>
        <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="create_credit_note">
            <div class="space-y-3">
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Tenant</label>
                    <select name="tenant_id" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="">Select tenant...</option>
                        <?php foreach ($tenants as $tn): ?>
                        <option value="<?= $tn['id'] ?>"><?= htmlspecialchars($tn['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Amount</label>
                    <input type="number" step="0.01" name="amount" required class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Currency</label>
                    <select name="currency" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Original Invoice ID (optional)</label>
                    <input type="number" name="original_invoice_id" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="#123">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs mb-1">Reason</label>
                    <textarea name="reason" rows="2" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm" placeholder="Goodwill credit..."></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeModal('creditModal')" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors px-4 py-2 rounded-lg text-sm font-semibold">Create</button>
            </div>
        </form>
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

