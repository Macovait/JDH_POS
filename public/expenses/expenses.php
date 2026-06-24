<?php
/**
 * Expenses Management - Jakababa POS System
 * Pure Tailwind CSS - Matching users.php layout
 */

$page_title = 'Expenses';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('expenses.manage') && !is_super_admin()) {
    enforce_permission('expenses.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();
$is_superadmin = is_super_admin();

$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please refresh the page.';
        $message_type = 'error';
        $csrf_token = generate_csrf_token();
    } else {
        try {
            $pdo->beginTransaction();

            switch ($action) {
                case 'create':
                    $category_id  = (int) ($_POST['category_id'] ?? 0);
                    $amount       = (float) ($_POST['amount'] ?? 0);
                    $description  = trim($_POST['description'] ?? '');
                    $expense_date = trim($_POST['expense_date'] ?? '');
                    $payment_method = trim($_POST['payment_method'] ?? '');
                    $reference    = trim($_POST['reference'] ?? '');
                    $expense_branch = (int) ($_POST['branch_id'] ?? $branch_id);

                    $errors = [];
                    if ($category_id <= 0)   $errors[] = 'Category is required';
                    if ($amount <= 0)        $errors[] = 'Amount must be greater than zero';
                    if (empty($description)) $errors[] = 'Description is required';
                    if (empty($expense_date)) $errors[] = 'Expense date is required';

                    if (!empty($errors)) {
                        throw new Exception(implode(', ', $errors));
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO expenses (tenant_id, branch_id, category_id, user_id, amount, description, expense_date, payment_method, reference, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $expense_branch ?: null,
                        $category_id,
                        $user_id,
                        $amount,
                        $description,
                        $expense_date,
                        $payment_method ?: null,
                        $reference ?: null,
                    ]);

                    $message = 'Expense created successfully!';
                    $message_type = 'success';
                    
                    $csrf_token = generate_csrf_token();
                    break;

                case 'update':
                    $expense_id   = (int) ($_POST['expense_id'] ?? 0);
                    $category_id  = (int) ($_POST['category_id'] ?? 0);
                    $amount       = (float) ($_POST['amount'] ?? 0);
                    $description  = trim($_POST['description'] ?? '');
                    $expense_date = trim($_POST['expense_date'] ?? '');
                    $payment_method = trim($_POST['payment_method'] ?? '');
                    $reference    = trim($_POST['reference'] ?? '');
                    $expense_branch = (int) ($_POST['branch_id'] ?? 0);

                    $errors = [];
                    if ($expense_id <= 0)    $errors[] = 'Invalid expense';
                    if ($category_id <= 0)   $errors[] = 'Category is required';
                    if ($amount <= 0)        $errors[] = 'Amount must be greater than zero';
                    if (empty($description)) $errors[] = 'Description is required';
                    if (empty($expense_date)) $errors[] = 'Expense date is required';

                    if (!empty($errors)) {
                        throw new Exception(implode(', ', $errors));
                    }

                    $stmt = $pdo->prepare("
                        UPDATE expenses
                        SET category_id = ?, amount = ?, description = ?, expense_date = ?,
                            payment_method = ?, reference = ?, branch_id = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmt->execute([
                        $category_id,
                        $amount,
                        $description,
                        $expense_date,
                        $payment_method ?: null,
                        $reference ?: null,
                        $expense_branch ?: null,
                        $expense_id,
                        $tenant_id,
                    ]);

                    $message = 'Expense updated successfully!';
                    $message_type = 'success';
                    
                    $csrf_token = generate_csrf_token();
                    break;

                case 'approve':
                    $expense_id = (int) ($_POST['expense_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE expenses SET status = 'approved', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$expense_id, $tenant_id]);
                    $message = 'Expense approved!';
                    $message_type = 'success';
                    break;

                case 'reject':
                    $expense_id = (int) ($_POST['expense_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE expenses SET status = 'rejected', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$expense_id, $tenant_id]);
                    $message = 'Expense rejected.';
                    $message_type = 'success';
                    break;

                case 'delete':
                    $expense_id = (int) ($_POST['expense_id'] ?? 0);
                    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$expense_id, $tenant_id]);
                    $message = 'Expense deleted successfully!';
                    $message_type = 'success';
                    break;

                case 'create_category':
                    $cat_name = trim($_POST['cat_name'] ?? '');
                    $cat_desc = trim($_POST['cat_description'] ?? '');
                    if (empty($cat_name)) {
                        throw new Exception('Category name is required');
                    }
                    $stmt = $pdo->prepare("INSERT INTO expense_categories (tenant_id, name, description, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
                    $stmt->execute([$tenant_id, $cat_name, $cat_desc ?: null]);
                    $message = 'Category added successfully!';
                    $message_type = 'success';
                    break;
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Filters
$search        = trim($_GET['s'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_cat    = $_GET['category'] ?? '';
$sort_by       = $_GET['sort'] ?? 'newest';

$where  = " WHERE e.tenant_id = ? ";
$params = [$tenant_id];

if (!empty($search)) {
    $where .= " AND (e.description LIKE ? OR e.reference LIKE ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (in_array($filter_status, ['pending', 'approved', 'rejected'], true)) {
    $where .= " AND e.status = ? ";
    $params[] = $filter_status;
}
if (!empty($filter_cat)) {
    $where .= " AND e.category_id = ? ";
    $params[] = (int) $filter_cat;
}

// Sort order
$order_by = match($sort_by) {
    'oldest' => 'e.expense_date ASC, e.id ASC',
    'amount_desc' => 'e.amount DESC',
    'amount_asc' => 'e.amount ASC',
    default => 'e.expense_date DESC, e.id DESC',
};

// Categories
try {
    $stmt = $pdo->prepare("SELECT id, name FROM expense_categories WHERE (tenant_id = ? OR tenant_id IS NULL) AND is_active = 1 ORDER BY name");
    $stmt->execute([$tenant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    error_log('Expense categories query error: ' . $e->getMessage());
}

// Branches
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// Expenses list
try {
    $sql = "
        SELECT e.id, e.amount, e.description, e.expense_date, e.payment_method, e.reference,
               e.status, e.category_id, e.branch_id, e.user_id, e.created_at,
               c.name AS category_name,
               b.name AS branch_name,
               u.name AS user_name
        FROM expenses e
        LEFT JOIN expense_categories c ON c.id = e.category_id
        LEFT JOIN branches b ON b.id = e.branch_id
        LEFT JOIN users u ON u.id = e.user_id
        $where
        ORDER BY $order_by
        LIMIT 500
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expenses = [];
    error_log('Expenses query error: ' . $e->getMessage());
}

// Stats
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'sum_month' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
            COALESCE(SUM(CASE WHEN status = 'approved' AND MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE()) THEN amount ELSE 0 END), 0) AS sum_month
        FROM expenses WHERE tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats = $row;
    }
} catch (Exception $e) {
    // ignore
}

$status_colors = [
    'pending'  => 'bg-amber-500/15 text-amber-400 border border-amber-500/30',
    'approved' => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
    'rejected' => 'bg-red-500/15 text-red-400 border border-red-500/30',
];
?>

<div class="space-y-4">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Finance</p>
            <h1 class="text-lg font-bold text-white">Expenses</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="expense_categories.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-tag text-xs"></i> Categories
            </a>
            <button onclick="openModal('addExpenseModal')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-xs font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-plus text-xs"></i> Add Expense
            </button>
        </div>
    </div>

    <!-- Success/Error Message -->
    <?php if ($message): ?>
    <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm border <?php echo $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : ($message_type === 'warning' ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-red-500/10 border-red-500/40 text-red-400'); ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> flex-shrink-0"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-slate-700/60 flex items-center justify-center shrink-0"><i class="fas fa-receipt text-slate-400 text-xs"></i></div>
            <div><div class="text-xl font-bold text-white"><?php echo (int) $stats['total']; ?></div><div class="text-[10px] text-slate-500 uppercase tracking-wide">Total</div></div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-clock text-amber-400 text-xs"></i></div>
            <div><div class="text-xl font-bold text-amber-400"><?php echo (int) $stats['pending']; ?></div><div class="text-[10px] text-slate-500 uppercase tracking-wide">Pending</div></div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-check-circle text-emerald-400 text-xs"></i></div>
            <div><div class="text-xl font-bold text-emerald-400"><?php echo (int) $stats['approved']; ?></div><div class="text-[10px] text-slate-500 uppercase tracking-wide">Approved</div></div>
        </div>
        <div class="bg-slate-800/50 border border-amber-500/20 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-calendar text-amber-400 text-xs"></i></div>
            <div><div class="text-lg font-bold text-amber-400"><?php echo number_format((float) $stats['sum_month'], 2); ?></div><div class="text-[10px] text-slate-500 uppercase tracking-wide">This Month</div></div>
        </div>
    </div>

    <!-- Status Tabs -->
    <?php $active_tab = $filter_status ?: 'all'; ?>
    <div class="flex flex-wrap items-center gap-2">
        <a href="?<?php echo http_build_query(array_diff_key($_GET, array_flip(['status', 'page']))); ?>" class="px-3 py-1.5 text-sm rounded-lg font-medium whitespace-nowrap transition-colors <?php echo $active_tab === 'all' ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:text-white hover:bg-slate-700'; ?>">
            All <span class="opacity-70">(<?php echo (int) $stats['total']; ?>)</span>
        </a>
        <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['status', 'page'])), ['status' => 'pending'])); ?>" class="px-3 py-1.5 text-sm rounded-lg font-medium whitespace-nowrap transition-colors <?php echo $active_tab === 'pending' ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:text-white hover:bg-slate-700'; ?>">
            Pending <span class="opacity-70">(<?php echo (int) $stats['pending']; ?>)</span>
        </a>
        <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['status', 'page'])), ['status' => 'approved'])); ?>" class="px-3 py-1.5 text-sm rounded-lg font-medium whitespace-nowrap transition-colors <?php echo $active_tab === 'approved' ? 'bg-emerald-500 text-white' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:text-white hover:bg-slate-700'; ?>">
            Approved <span class="opacity-70">(<?php echo (int) $stats['approved']; ?>)</span>
        </a>
        <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['status', 'page'])), ['status' => 'rejected'])); ?>" class="px-3 py-1.5 text-sm rounded-lg font-medium whitespace-nowrap transition-colors <?php echo $active_tab === 'rejected' ? 'bg-red-500 text-white' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:text-white hover:bg-slate-700'; ?>">
            Rejected <span class="opacity-70">(<?php echo (int) $stats['rejected']; ?>)</span>
        </a>
        <div class="ml-auto">
            <select onchange="window.location.href=this.value" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-sm focus:outline-none focus:border-amber-500">
                <option value="">Sort by</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest'])); ?>" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'oldest'])); ?>" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'amount_desc'])); ?>" <?php echo $sort_by === 'amount_desc' ? 'selected' : ''; ?>>Amount: High to Low</option>
                <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'amount_asc'])); ?>" <?php echo $sort_by === 'amount_asc' ? 'selected' : ''; ?>>Amount: Low to High</option>
            </select>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" class="flex flex-wrap gap-2">
        <?php foreach (array_diff_key($_GET, array_flip(['s', 'cat', 'branch', 'status', 'sort', 'page'])) as $k => $v): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
        <?php endforeach; ?>
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
            <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full pl-7 pr-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors"
                   placeholder="Search expenses…">
        </div>
        <?php if (!empty($categories)): ?>
        <select name="cat" class="px-2 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if (!empty($branches)): ?>
        <select name="branch" class="px-2 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo (isset($_GET['branch']) && $_GET['branch'] == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="px-3 py-1.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs rounded-lg hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-filter text-xs mr-1"></i>Filter
        </button>
        <?php if (!empty($search) || !empty($_GET['cat']) || !empty($_GET['branch'])): ?>
        <a href="?" class="px-3 py-1.5 bg-slate-700 border border-slate-600 text-slate-400 text-xs rounded-lg hover:bg-slate-600 transition-colors">
            <i class="fas fa-times text-xs"></i>
        </a>
        <?php endif; ?>
    </form>

    <!-- Expenses Table -->
    <div class="overflow-x-auto bg-slate-800/40 rounded-xl border border-slate-700/60">
        <table class="w-full min-w-[1000px] text-left">
            <thead class="bg-slate-900/50 border-b border-slate-700/60">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Branch</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-slate-500">No expenses found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $exp):
                        $status = $exp['status'] ?? 'pending';
                        $statusBadge = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400 border border-slate-500/30';
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-4 py-3 text-sm text-slate-300">
                            <?php echo htmlspecialchars(date('M d, Y', strtotime($exp['expense_date']))); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-white">
                            <div class="font-medium"><?php echo htmlspecialchars($exp['description']); ?></div>
                            <?php if (!empty($exp['reference'])): ?>
                                <div class="text-xs text-slate-500 mt-0.5">Ref: <?php echo htmlspecialchars($exp['reference']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-300">
                            <?php echo htmlspecialchars($exp['category_name'] ?? '—'); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-300">
                            <?php echo htmlspecialchars($exp['branch_name'] ?? '—'); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-white font-semibold">
                            <?php echo number_format((float) $exp['amount'], 2); ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusBadge; ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-300">
                            <?php echo htmlspecialchars($exp['user_name'] ?? '—'); ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <button type="button" title="Edit"
                                        onclick='openEditExpense(<?php echo json_encode(["id"=>(int)$exp["id"],"category_id"=>(int)($exp["category_id"]??0),"amount"=>$exp["amount"],"description"=>$exp["description"],"expense_date"=>$exp["expense_date"],"payment_method"=>$exp["payment_method"],"reference"=>$exp["reference"],"branch_id"=>(int)($exp["branch_id"]??0)],JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'
                                        class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-amber-400 hover:bg-amber-500/10 transition-colors">
                                    <i class="fas fa-pen text-[10px]"></i>
                                </button>
                                <?php if ($status === 'pending'): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="expense_id" value="<?php echo (int) $exp['id']; ?>">
                                    <button type="submit" title="Approve" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10 transition-colors">
                                        <i class="fas fa-check text-[10px]"></i>
                                    </button>
                                </form>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="expense_id" value="<?php echo (int) $exp['id']; ?>">
                                    <button type="submit" title="Reject" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                                        <i class="fas fa-ban text-[10px]"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this expense?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="expense_id" value="<?php echo (int) $exp['id']; ?>">
                                    <button type="submit" title="Delete" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Input and label styles
$INP_E = 'w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
$LBL_E = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1';
?>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 modal-overlay">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-plus-circle text-amber-400 text-sm"></i> Add Expense</h2>
            <button onclick="closeModal('addExpenseModal')" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="post" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="<?php echo $LBL_E; ?>">Category *</label>
                <select name="category_id" required class="<?php echo $INP_E; ?>">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Description *</label>
                <input type="text" name="description" required class="<?php echo $INP_E; ?>" placeholder="Brief description">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="<?php echo $LBL_E; ?>">Amount *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required class="<?php echo $INP_E; ?>" placeholder="0.00">
                </div>
                <div>
                    <label class="<?php echo $LBL_E; ?>">Date *</label>
                    <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required class="<?php echo $INP_E; ?>">
                </div>
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Payment Method</label>
                <select name="payment_method" class="<?php echo $INP_E; ?>">
                    <option value="">Select</option>
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="card">Card</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Reference</label>
                <input type="text" name="reference" class="<?php echo $INP_E; ?>" placeholder="Receipt / invoice number">
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Branch</label>
                <select name="branch_id" class="<?php echo $INP_E; ?>">
                    <option value="">Select branch</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo isset($branch_id) && $branch_id == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Save Expense</button>
                <button type="button" onclick="closeModal('addExpenseModal')" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Expense Modal -->
<div id="editExpenseModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 modal-overlay">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-pen text-amber-400 text-sm"></i> Edit Expense</h2>
            <button onclick="closeModal('editExpenseModal')" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="post" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="expense_id" id="edit_expense_id">
            <div>
                <label class="<?php echo $LBL_E; ?>">Category *</label>
                <select name="category_id" id="edit_category_id" required class="<?php echo $INP_E; ?>">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Description *</label>
                <input type="text" name="description" id="edit_description" required class="<?php echo $INP_E; ?>">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="<?php echo $LBL_E; ?>">Amount *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" required class="<?php echo $INP_E; ?>">
                </div>
                <div>
                    <label class="<?php echo $LBL_E; ?>">Date *</label>
                    <input type="date" name="expense_date" id="edit_expense_date" required class="<?php echo $INP_E; ?>">
                </div>
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Payment Method</label>
                <select name="payment_method" id="edit_payment_method" class="<?php echo $INP_E; ?>">
                    <option value="">Select</option>
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="card">Card</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Reference</label>
                <input type="text" name="reference" id="edit_reference" class="<?php echo $INP_E; ?>">
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Branch</label>
                <select name="branch_id" id="edit_branch_id" class="<?php echo $INP_E; ?>">
                    <option value="">Select branch</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Update Expense</button>
                <button type="button" onclick="closeModal('editExpenseModal')" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50 modal-overlay">
    <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 w-full max-w-md mx-4 shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white flex items-center gap-2"><i class="fas fa-tag text-amber-400 text-sm"></i> Add Category</h2>
            <button onclick="closeModal('addCategoryModal')" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <form method="post" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="create_category">
            <div>
                <label class="<?php echo $LBL_E; ?>">Name *</label>
                <input type="text" name="cat_name" required class="<?php echo $INP_E; ?>">
            </div>
            <div>
                <label class="<?php echo $LBL_E; ?>">Description</label>
                <textarea name="cat_description" rows="3" class="<?php echo $INP_E; ?> resize-none"></textarea>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Save Category</button>
                <button type="button" onclick="closeModal('addCategoryModal')" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}

function openEditExpense(data) {
    document.getElementById('edit_expense_id').value = data.id;
    document.getElementById('edit_category_id').value = data.category_id || '';
    document.getElementById('edit_description').value = data.description || '';
    document.getElementById('edit_amount').value = data.amount || '';
    document.getElementById('edit_expense_date').value = data.expense_date || '';
    document.getElementById('edit_payment_method').value = data.payment_method || '';
    document.getElementById('edit_reference').value = data.reference || '';
    document.getElementById('edit_branch_id').value = data.branch_id || '';
    openModal('editExpenseModal');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(function(m) {
            if (!m.classList.contains('hidden')) closeModal(m.id);
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>