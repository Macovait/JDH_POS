<?php
/**
 * Suppliers management page for Jakababa POS
 * Laravel-style layout with Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('suppliers.manage') && !is_super_admin()) {
    enforce_permission('suppliers.manage');
}

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$can_delete = is_super_admin() || check_permission('suppliers.delete');

// Handle GET messages
$message = $_GET['success'] ?? ($_GET['error'] ?? '');
$message_type = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');

// Handle delete/toggle via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete':
            if ($can_delete) {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $check_po = $pdo->prepare('SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?');
                        $check_po->execute([$id]);
                        $po_count = $check_po->fetchColumn();

                        if ($po_count > 0) {
                            $stmt = $pdo->prepare('UPDATE suppliers SET active = 0 WHERE id = ?');
                            $stmt->execute([$id]);
                            $message = 'Supplier has been deactivated (has purchase orders)';
                        } else {
                            $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
                            $stmt->execute([$id]);
                            $message = 'Supplier deleted successfully';
                        }
                        $message_type = 'success';

                        log_activity($user_id, 'supplier.deleted', ['supplier_id' => $id], null, get_current_tenant_id());

                    } catch (PDOException $e) {
                        error_log("Error deleting supplier: " . $e->getMessage());
                        $message = 'Failed to delete supplier';
                        $message_type = 'error';
                    }
                }
            }
            break;

        case 'toggle_status':
            if ($can_delete) {
                $id = intval($_POST['id'] ?? 0);
                $status = intval($_POST['status'] ?? 0);

                if ($id > 0) {
                    try {
                        $stmt = $pdo->prepare('UPDATE suppliers SET active = ? WHERE id = ?');
                        $stmt->execute([$status, $id]);

                        $message = 'Supplier status updated successfully';
                        $message_type = 'success';

                        log_activity($user_id, 'supplier.status_changed', [
                            'supplier_id' => $id, 'new_status' => $status
                        ], get_current_tenant_id());

                    } catch (PDOException $e) {
                        error_log("Error toggling supplier status: " . $e->getMessage());
                        $message = 'Failed to update supplier status';
                        $message_type = 'error';
                    }
                }
            }
            break;
    }
    } // end csrf-valid else
}

// Get search/filter/sort parameters
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort'] ?? 'recent';

$sql = "
    SELECT 
        s.*,
        (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = s.id) as po_count,
        (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = s.id AND status = 'pending') as pending_po_count,
        (SELECT MAX(created_at) FROM purchase_orders WHERE supplier_id = s.id) as last_order_date
    FROM suppliers s
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.contact LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter === 'active') {
    $sql .= " AND s.active = 1";
} elseif ($filter === 'inactive') {
    $sql .= " AND s.active = 0";
}

switch ($sort) {
    case 'name_asc':  $sql .= ' ORDER BY s.name ASC'; break;
    case 'name_desc': $sql .= ' ORDER BY s.name DESC'; break;
    default:          $sql .= ' ORDER BY s.id DESC'; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

$stats = [
    'total' => count($suppliers),
    'active' => count(array_filter($suppliers, fn($s) => $s['active'])),
    'total_pos' => array_sum(array_column($suppliers, 'po_count')),
    'pending_pos' => array_sum(array_column($suppliers, 'pending_po_count'))
];

$page_title = 'Supplier Management';
ob_start();
?>

<div class="fade-in">

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
        <h1 class="text-lg font-bold text-white">Supplier Management</h1>
        <p class="text-xs text-slate-500 mt-0.5"><?php echo number_format($stats['total']); ?> supplier<?php echo $stats['total'] !== 1 ? 's' : ''; ?> found</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="supplier_form.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-xs font-semibold hover:bg-amber-400 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Supplier
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg <?php echo $message_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400'; ?> text-sm toast-slide">
    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php foreach ([
        ['label'=>'Total Suppliers','value'=>$stats['total'],      'icon'=>'fa-truck',        'color'=>'text-blue-400',   'bg'=>'bg-blue-500/10'],
        ['label'=>'Active',         'value'=>$stats['active'],     'icon'=>'fa-check-circle', 'color'=>'text-emerald-400','bg'=>'bg-emerald-500/10'],
        ['label'=>'Total POs',      'value'=>$stats['total_pos'],  'icon'=>'fa-file-invoice', 'color'=>'text-amber-400',  'bg'=>'bg-amber-500/10'],
        ['label'=>'Pending POs',    'value'=>$stats['pending_pos'],'icon'=>'fa-clock',        'color'=>'text-orange-400', 'bg'=>'bg-orange-500/10'],
    ] as $card): ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo number_format($card['value']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter pills + search -->
<?php $inactive_suppliers = $stats['total'] - $stats['active']; ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">
    <div class="flex flex-wrap gap-1.5">
        <?php
        $s_pills = [
            ''         => ['label'=>'All',     'count'=>$stats['total'],     'on'=>'bg-amber-500/20 text-amber-400 border-amber-500/30',     'off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'active'   => ['label'=>'Active',  'count'=>$stats['active'],    'on'=>'bg-emerald-500/20 text-emerald-400 border-emerald-500/30','off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'inactive' => ['label'=>'Inactive','count'=>$inactive_suppliers, 'on'=>'bg-slate-500/20 text-slate-300 border-slate-500/30',     'off'=>'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
        ];
        foreach ($s_pills as $val => $pill):
            $active_pill = ($filter === $val) || ($val === '' && ($filter === 'all' || $filter === ''));
            $href = $val === ''
                ? '?' . http_build_query(array_diff_key($_GET, array_flip(['filter','page'])))
                : '?' . http_build_query(array_merge(array_diff_key($_GET, array_flip(['filter','page'])), ['filter' => $val]));
        ?>
        <a href="<?php echo htmlspecialchars($href); ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $active_pill ? $pill['on'] : $pill['off']; ?>">
            <?php echo $pill['label']; ?> <span class="text-xs opacity-70">(<?php echo (int)$pill['count']; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <?php foreach (array_diff_key($_GET, array_flip(['search','page'])) as $k => $v): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
        <?php endforeach; ?>
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Name, contact, phone, email…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <select name="sort" onchange="this.form.submit()" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Recently Added</option>
            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A–Z</option>
            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z–A</option>
        </select>
        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <?php if (!empty($search) || ($filter !== 'all' && $filter !== '')): ?>
        <a href="suppliers.php" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Suppliers table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Supplier</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact Info</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Purchase Orders</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Last Order</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-14 text-center">
                        <i class="fas fa-truck text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No suppliers found</p>
                        <?php if (!empty($search) || ($filter !== 'all' && $filter !== '')): ?>
                        <a href="suppliers.php" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-times text-xs"></i> Clear filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                                <i class="fas fa-building text-amber-400 text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                <div class="text-xs text-slate-500">ID: #<?php echo $supplier['id']; ?></div>
                                <?php if (!empty($supplier['tax_id'])): ?>
                                <div class="text-xs text-slate-500">Tax: <?php echo htmlspecialchars($supplier['tax_id']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($supplier['payment_terms'])): ?>
                                <div class="text-xs text-slate-500">Terms: <?php echo htmlspecialchars($supplier['payment_terms']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="space-y-0.5 text-xs">
                            <?php if (!empty($supplier['contact'])): ?>
                            <div class="flex items-center gap-1 text-slate-300"><i class="fas fa-user text-slate-500 w-3"></i><?php echo htmlspecialchars($supplier['contact']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['phone'])): ?>
                            <div class="flex items-center gap-1 text-slate-300"><i class="fas fa-phone text-slate-500 w-3"></i><?php echo htmlspecialchars($supplier['phone']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['email'])): ?>
                            <div class="flex items-center gap-1 text-slate-300"><i class="fas fa-envelope text-slate-500 w-3"></i><?php echo htmlspecialchars($supplier['email']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($supplier['address'])): ?>
                            <div class="flex items-center gap-1 text-slate-300"><i class="fas fa-map-pin text-slate-500 w-3"></i><?php echo htmlspecialchars($supplier['address']); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-sm">
                        <span class="font-semibold text-amber-400"><?php echo $supplier['po_count']; ?></span>
                        <span class="text-slate-500 text-xs ml-1">total</span>
                        <?php if ($supplier['pending_po_count'] > 0): ?>
                        <div><span class="font-semibold text-orange-400"><?php echo $supplier['pending_po_count']; ?></span><span class="text-slate-500 text-xs ml-1">pending</span></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm">
                        <?php if ($supplier['last_order_date']): ?>
                        <div class="text-slate-300"><?php echo date('d M Y', strtotime($supplier['last_order_date'])); ?></div>
                        <?php else: ?>
                        <span class="text-slate-600">No orders</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($supplier['active']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">
                            <i class="fas fa-check-circle mr-1 text-[10px]"></i>Active
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30">
                            <i class="fas fa-ban mr-1 text-[10px]"></i>Inactive
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="supplier_form.php?id=<?php echo $supplier['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <?php if ($can_delete): ?>
                            <button onclick="toggleStatus(<?php echo $supplier['id']; ?>, <?php echo $supplier['active']; ?>)"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-<?php echo $supplier['active'] ? 'amber' : 'emerald'; ?>-500/20 hover:text-<?php echo $supplier['active'] ? 'amber' : 'emerald'; ?>-400 transition-colors"
                                    title="<?php echo $supplier['active'] ? 'Deactivate' : 'Activate'; ?>">
                                <i class="fas fa-<?php echo $supplier['active'] ? 'pause' : 'play'; ?> text-xs"></i>
                            </button>
                            <?php endif; ?>
                            <a href="../purchases/purchase_orders.php?supplier=<?php echo $supplier['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-blue-500/20 hover:text-blue-400 transition-colors" title="View POs">
                                <i class="fas fa-file-invoice text-xs"></i>
                            </a>
                            <?php if ($can_delete): ?>
                            <button onclick="openDeleteModal(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['name'], ENT_QUOTES); ?>')"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Delete">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($suppliers)): ?>
    <div class="flex items-center justify-between px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">Showing <span class="text-slate-300 font-medium"><?php echo count($suppliers); ?></span> supplier<?php echo count($suppliers) !== 1 ? 's' : ''; ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 max-w-md w-full mx-4">
        <div class="flex items-center gap-2.5 mb-4">
            <i class="fas fa-exclamation-triangle text-amber-400"></i>
            <h3 class="text-base font-semibold text-white">Delete Supplier</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteSupplierId">
            <p class="text-slate-300 text-sm mb-5">
                Are you sure you want to delete <span id="deleteSupplierName" class="text-white font-semibold"></span>?
                This action cannot be undone.
            </p>
            <div class="flex gap-2.5">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/25 transition-colors">
                    <i class="fas fa-trash-alt text-xs"></i>Delete
                </button>
                <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?php echo json_encode(generate_csrf_token()); ?>;

    function toggleStatus(id, currentStatus) {
        const form = document.createElement('form');
        form.method = 'POST';
        const fields = { csrf_token: CSRF_TOKEN, action: 'toggle_status', id: id, status: currentStatus ? 0 : 1 };
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
    }

    function openDeleteModal(id, name) {
        document.getElementById('deleteSupplierId').value = id;
        document.getElementById('deleteSupplierName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    document.getElementById('deleteModal').addEventListener('click', function (e) {
        if (e.target === this) this.classList.add('hidden');
    });

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.altKey && e.key === 'a') { e.preventDefault(); window.location.href = 'supplier_form.php'; }
        if (e.key === 'Escape') { document.getElementById('deleteModal').classList.add('hidden'); }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const msg = document.querySelector('.toast-slide');
        if (msg) {
            setTimeout(() => { msg.style.transition = 'opacity 0.5s ease'; msg.style.opacity = '0'; setTimeout(() => msg.remove(), 500); }, 5000);
        }
    });
</script>

</div><!-- /.fade-in -->

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
