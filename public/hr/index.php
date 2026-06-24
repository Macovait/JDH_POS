<?php
/**
 * HR Dashboard - Human Resources Management
 * Pure Tailwind CSS — matches all_sales.php design system
 */

$page_title = 'HR Dashboard';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo       = get_db_connection();
$user_id   = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

// Filters
$search        = trim($_GET['s'] ?? '');
$status_filter = $_GET['status'] ?? '';
$dept_filter   = trim($_GET['dept'] ?? '');
$sort_by       = $_GET['sort'] ?? 'newest';

// Pagination
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

// Stats
$stats = [
    'total_employees'  => 0,
    'active_employees' => 0,
    'on_leave'         => 0,
    'departments'      => 0,
    'pending_leaves'   => 0,
    'terminated'       => 0,
];
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE tenant_id=?");
    $s->execute([$tenant_id]); $stats['total_employees'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE tenant_id=? AND status='active'");
    $s->execute([$tenant_id]); $stats['active_employees'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE tenant_id=? AND status='on_leave'");
    $s->execute([$tenant_id]); $stats['on_leave'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE tenant_id=? AND status='terminated'");
    $s->execute([$tenant_id]); $stats['terminated'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(DISTINCT department) FROM hr_employees WHERE tenant_id=? AND department IS NOT NULL AND department!=''");
    $s->execute([$tenant_id]); $stats['departments'] = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM hr_leave_requests WHERE tenant_id=? AND status='pending'");
    $s->execute([$tenant_id]); $stats['pending_leaves'] = (int)$s->fetchColumn();
} catch (PDOException $e) { error_log('HR stats: ' . $e->getMessage()); }

// Departments for filter dropdown
$departments = [];
try {
    $s = $pdo->prepare("SELECT DISTINCT department FROM hr_employees WHERE tenant_id=? AND department IS NOT NULL AND department!='' ORDER BY department");
    $s->execute([$tenant_id]);
    $departments = $s->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// WHERE builder
$where  = " WHERE e.tenant_id = ? ";
$params = [$tenant_id];
if (!empty($search)) {
    $where   .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.phone LIKE ?) ";
    $params[] = "%{$search}%"; $params[] = "%{$search}%";
    $params[] = "%{$search}%"; $params[] = "%{$search}%";
}
if (in_array($status_filter, ['active','on_leave','terminated'], true)) {
    $where .= " AND e.status = ? "; $params[] = $status_filter;
}
if (!empty($dept_filter)) {
    $where .= " AND e.department = ? "; $params[] = $dept_filter;
}
$order = match($sort_by) {
    'oldest' => " ORDER BY e.created_at ASC",
    'name'   => " ORDER BY e.first_name ASC, e.last_name ASC",
    default  => " ORDER BY e.created_at DESC",
};

// Total count
$total_employees = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM hr_employees e $where");
    $s->execute($params); $total_employees = (int)$s->fetchColumn();
} catch (PDOException $e) { error_log($e->getMessage()); }

// Employee list
$employees = [];
try {
    $sql = "SELECT e.*, d.name AS designation_name
            FROM hr_employees e
            LEFT JOIN hr_designations d ON d.id = e.designation_id
            $where $order LIMIT $limit OFFSET $offset";
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $employees = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

$total_pages = max(1, (int)ceil($total_employees / $limit));

// Pending leave requests (top 5 for info panel)
$pending_leaves = [];
try {
    $s = $pdo->prepare("
        SELECT lr.*, e.first_name, e.last_name
        FROM hr_leave_requests lr
        LEFT JOIN hr_employees e ON lr.employee_id = e.id
        WHERE lr.tenant_id=? AND lr.status='pending'
        ORDER BY lr.created_at DESC LIMIT 5
    ");
    $s->execute([$tenant_id]);
    $pending_leaves = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// QS helper
$build_qs = function(array $extra = []) use ($search, $status_filter, $dept_filter, $sort_by, $page) {
    $base = [];
    if ($search !== '')        $base['s']      = $search;
    if ($status_filter !== '') $base['status'] = $status_filter;
    if ($dept_filter !== '')   $base['dept']   = $dept_filter;
    if ($sort_by !== 'newest') $base['sort']   = $sort_by;
    if ($page > 1)             $base['page']   = $page;
    return http_build_query(array_merge($base, $extra));
};

// Badge maps
$status_tw = [
    'active'     => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'on_leave'   => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    'terminated' => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
];
$leave_tw = [
    'pending'  => 'bg-amber-500/15 text-amber-400',
    'approved' => 'bg-emerald-500/15 text-emerald-400',
    'rejected' => 'bg-red-500/15 text-red-400',
];
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-users-cog text-amber-400"></i> HR Dashboard
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format($stats['total_employees']); ?> employee<?php echo $stats['total_employees'] !== 1 ? 's' : ''; ?> · <?php echo number_format($stats['active_employees']); ?> active
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="employees.php?action=add"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-user-plus text-xs"></i> Add Employee
        </a>
        <a href="leave_requests.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-calendar-minus text-xs"></i> Leave Requests
            <?php if ($stats['pending_leaves'] > 0): ?>
            <span class="ml-0.5 bg-amber-500 text-slate-900 text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none"><?php echo $stats['pending_leaves']; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-5">
<?php foreach ([
    ['label'=>'Total','value'=>$stats['total_employees'],'sub'=>$stats['active_employees'].' active','icon'=>'fa-users','color'=>'text-blue-400','bg'=>'bg-blue-500/10'],
    ['label'=>'Active','value'=>$stats['active_employees'],'sub'=>'Working now','icon'=>'fa-user-check','color'=>'text-emerald-400','bg'=>'bg-emerald-500/10'],
    ['label'=>'On Leave','value'=>$stats['on_leave'],'sub'=>'Away','icon'=>'fa-plane-departure','color'=>'text-amber-400','bg'=>'bg-amber-500/10'],
    ['label'=>'Departments','value'=>$stats['departments'],'sub'=>'Active','icon'=>'fa-sitemap','color'=>'text-purple-400','bg'=>'bg-purple-500/10'],
    ['label'=>'Pending Leaves','value'=>$stats['pending_leaves'],'sub'=>'Need approval','icon'=>'fa-clock','color'=>'text-orange-400','bg'=>'bg-orange-500/10'],
] as $card): ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?> truncate"><?php echo $card['value']; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
            <div class="text-[10px] text-slate-600 leading-none mt-0.5"><?php echo $card['sub']; ?></div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Recent Employees & Pending Leaves -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    
    <!-- Recent Employees -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-user-friends text-blue-400 text-xs"></i> Recent Employees
            </h3>
            <a href="hr/employees.php" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">View All</a>
        </div>
        
        <?php if (empty($recent_employees)): ?>
            <div class="text-center py-12">
                <i class="fas fa-users text-4xl text-slate-700 block mb-3"></i>
                <p class="text-slate-500 text-sm">No employees yet</p>
                <a href="hr/employees.php?action=add" class="mt-2 inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Add your first employee
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-700/40">
                <?php foreach ($recent_employees as $emp):
                    $status = $emp['status'] ?? 'active';
                    $status_class = $emp_status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <div class="flex items-center justify-between p-3 hover:bg-slate-700/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                            <span class="text-blue-400 text-xs font-semibold"><?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?></span>
                        </div>
                        <div>
                            <p class="text-sm text-white font-medium"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($emp['department'] ?? 'No department'); ?></p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pending Leave Requests -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-calendar-minus text-amber-400 text-xs"></i> Pending Leave Requests
            </h3>
            <a href="hr/leave_requests.php" class="text-xs text-amber-400 hover:text-amber-300 transition-colors">View All</a>
        </div>
        
        <?php if (empty($pending_leaves)): ?>
            <div class="text-center py-12">
                <i class="fas fa-calendar-check text-4xl text-slate-700 block mb-3"></i>
                <p class="text-slate-500 text-sm">No pending leave requests</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-700/40">
                <?php foreach ($pending_leaves as $leave):
                    $leave_type = $leave['leave_type'] ?? 'Annual';
                ?>
                <div class="flex items-center justify-between p-3 hover:bg-slate-700/30 transition-colors">
                    <div>
                        <p class="text-sm text-white font-medium"><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></p>
                        <p class="text-xs text-slate-500">
                            <?php echo htmlspecialchars($leave_type); ?> · 
                            <?php echo date('d M', strtotime($leave['start_date'])); ?> - 
                            <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                        </p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-400">
                        Pending
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>