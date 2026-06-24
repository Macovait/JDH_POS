<?php
/**
 * HR Leave Requests Management
 * Pure Tailwind CSS
 */

$page_title = 'Leave Requests';
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

$pdo = get_db_connection();
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$success_message = '';
$error_message = '';

// Ensure HR tables exist
ensureHrTables($pdo);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security validation failed. Please refresh the page.';
    } else {
        if ($action === 'create') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $leave_type = $_POST['leave_type'] ?? '';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            if ($employee_id > 0 && !empty($leave_type) && !empty($start_date) && !empty($end_date)) {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO hr_leave_requests (tenant_id, branch_id, employee_id, leave_type, start_date, end_date, reason, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$tenant_id, $branch_id, $employee_id, $leave_type, $start_date, $end_date, $reason]);
                    $success_message = 'Leave request submitted successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error creating leave request: " . $e->getMessage());
                    $error_message = 'Failed to submit leave request';
                }
            } else {
                $error_message = 'Please fill in all required fields';
            }
        } elseif ($action === 'approve' || $action === 'reject') {
            $id = intval($_POST['id'] ?? 0);
            $new_status = $action === 'approve' ? 'approved' : 'rejected';
            
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('
                        UPDATE hr_leave_requests SET status = ?, approved_by = ?, updated_at = NOW() 
                        WHERE id = ? AND tenant_id = ?
                    ');
                    $stmt->execute([$new_status, $user_id, $id, $tenant_id]);
                    $success_message = 'Leave request ' . $new_status;
                    
                    // Update employee status if leave is approved
                    if ($new_status === 'approved') {
                        // Get employee_id from leave request
                        $stmt2 = $pdo->prepare('SELECT employee_id FROM hr_leave_requests WHERE id = ?');
                        $stmt2->execute([$id]);
                        $leave = $stmt2->fetch(PDO::FETCH_ASSOC);
                        if ($leave) {
                            $stmt3 = $pdo->prepare('
                                UPDATE hr_employees SET status = "on_leave", updated_at = NOW() 
                                WHERE id = ? AND tenant_id = ?
                            ');
                            $stmt3->execute([$leave['employee_id'], $tenant_id]);
                        }
                    }
                    
                } catch (PDOException $e) {
                    error_log("Error updating leave request: " . $e->getMessage());
                    $error_message = 'Failed to update leave request';
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();

// Fetch leave requests
$leave_requests = [];
$employees = [];
$filter = $_GET['filter'] ?? 'all';

try {
    // Build query for leave requests
    $sql = '
        SELECT lr.*, e.first_name, e.last_name, e.department 
        FROM hr_leave_requests lr 
        LEFT JOIN hr_employees e ON lr.employee_id = e.id 
        WHERE lr.tenant_id = ?
    ';
    $params = [$tenant_id];
    
    if ($filter === 'pending') {
        $sql .= ' AND lr.status = "pending"';
    } elseif ($filter === 'approved') {
        $sql .= ' AND lr.status = "approved"';
    } elseif ($filter === 'rejected') {
        $sql .= ' AND lr.status = "rejected"';
    }
    
    $sql .= ' ORDER BY lr.created_at DESC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch active employees for dropdown
    $stmt = $pdo->prepare('
        SELECT id, first_name, last_name, department 
        FROM hr_employees 
        WHERE tenant_id = ? AND status = "active"
        ORDER BY first_name
    ');
    $stmt->execute([$tenant_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching leave requests: " . $e->getMessage());
}

// Status badge colors
$status_colors = [
    'pending' => 'bg-amber-500/15 text-amber-400',
    'approved' => 'bg-emerald-500/15 text-emerald-400',
    'rejected' => 'bg-red-500/15 text-red-400',
];

$leave_types = [
    'Annual' => 'Annual Leave',
    'Sick' => 'Sick Leave',
    'Maternity' => 'Maternity Leave',
    'Paternity' => 'Paternity Leave',
    'Unpaid' => 'Unpaid Leave',
    'Bereavement' => 'Bereavement Leave',
    'Study' => 'Study Leave',
    'Other' => 'Other',
];
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-calendar-minus text-amber-400"></i> Leave Requests
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Manage employee leave requests
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="openLeaveModal()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> New Request
        </button>
    </div>
</div>

<!-- Filter Tabs -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-2 mb-5">
    <div class="flex flex-wrap gap-1.5">
        <a href="?filter=all" 
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?php echo $filter === 'all' ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white'; ?>">
            All
        </a>
        <a href="?filter=pending" 
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?php echo $filter === 'pending' ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white'; ?>">
            Pending
        </a>
        <a href="?filter=approved" 
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?php echo $filter === 'approved' ? 'bg-emerald-500/20 text-emerald-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white'; ?>">
            Approved
        </a>
        <a href="?filter=rejected" 
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?php echo $filter === 'rejected' ? 'bg-red-500/20 text-red-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white'; ?>">
            Rejected
        </a>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Leave Requests Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Leave Type</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Start Date</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">End Date</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Days</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Reason</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($leave_requests)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center">
                        <i class="fas fa-calendar-minus text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No leave requests found</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($leave_requests as $lr): 
                    $start = strtotime($lr['start_date']);
                    $end = strtotime($lr['end_date']);
                    $days = ceil(($end - $start) / (60 * 60 * 24)) + 1;
                    $status = $lr['status'] ?? 'pending';
                    $status_class = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                                <span class="text-blue-400 text-xs font-semibold"><?php echo strtoupper(substr($lr['first_name'] ?? '', 0, 1)); ?></span>
                            </div>
                            <div>
                                <p class="text-sm text-white font-medium"><?php echo htmlspecialchars(($lr['first_name'] ?? '') . ' ' . ($lr['last_name'] ?? '')); ?></p>
                                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($lr['department'] ?? 'No department'); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo htmlspecialchars($leave_types[$lr['leave_type']] ?? $lr['leave_type']); ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo date('d M Y', strtotime($lr['start_date'])); ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo date('d M Y', strtotime($lr['end_date'])); ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-semibold text-amber-400"><?php echo $days; ?> day(s)</span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-slate-400 max-w-[200px] truncate" title="<?php echo htmlspecialchars($lr['reason'] ?? ''); ?>">
                            <?php echo htmlspecialchars($lr['reason'] ?? '—'); ?>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($status === 'pending'): ?>
                        <div class="flex items-center justify-center gap-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo $lr['id']; ?>">
                                <button type="submit" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 transition-colors" title="Approve">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?php echo $lr['id']; ?>">
                                <button type="submit" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-red-400 hover:bg-red-500/20 hover:text-red-300 transition-colors" title="Reject">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-slate-500 text-xs">—</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Leave Request Modal -->
<div id="leaveModal" class="fixed inset-0 bg-black/70 hidden z-50 items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 p-6 max-w-lg w-full mx-4 shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-white">New Leave Request</h3>
            <button onclick="closeLeaveModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="create">
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Employee *</label>
                <select name="employee_id" id="leaveEmployee" required 
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Leave Type *</label>
                <select name="leave_type" id="leaveType" required 
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">Select Type</option>
                    <?php foreach ($leave_types as $key => $value): ?>
                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Start Date *</label>
                    <input type="date" name="start_date" id="leaveStart" required 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">End Date *</label>
                    <input type="date" name="end_date" id="leaveEnd" required 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Reason</label>
                <textarea name="reason" id="leaveReason" rows="3" 
                          class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                          placeholder="Optional reason for leave..."></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition-colors">
                    Submit Request
                </button>
                <button type="button" onclick="closeLeaveModal()" class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openLeaveModal() {
    document.getElementById('leaveModal').classList.remove('hidden');
    document.getElementById('leaveModal').style.display = 'flex';
}

function closeLeaveModal() {
    document.getElementById('leaveModal').classList.add('hidden');
    document.getElementById('leaveModal').style.display = 'none';
    document.getElementById('leaveEmployee').value = '';
    document.getElementById('leaveType').value = '';
    document.getElementById('leaveStart').value = '';
    document.getElementById('leaveEnd').value = '';
    document.getElementById('leaveReason').value = '';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLeaveModal();
    }
});

// Close modal on outside click
document.getElementById('leaveModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLeaveModal();
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>

<?php
/**
 * Ensure HR tables exist
 */
function ensureHrTables($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hr_employees (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED DEFAULT 1,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                department VARCHAR(100),
                position VARCHAR(100),
                salary DECIMAL(12,2) DEFAULT 0,
                hire_date DATE,
                status ENUM('active', 'on_leave', 'terminated') DEFAULT 'active',
                created_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status),
                INDEX idx_department (department)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hr_leave_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED DEFAULT 1,
                employee_id INT UNSIGNED NOT NULL,
                leave_type VARCHAR(50) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                reason TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                approved_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_tenant (tenant_id),
                INDEX idx_employee (employee_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("HR tables error: " . $e->getMessage());
    }
}
?>