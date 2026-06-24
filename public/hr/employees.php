<?php
/**
 * HR Employees Management
 * Pure Tailwind CSS
 */

$page_title = 'HR Employees';
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
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $salary = floatval($_POST['salary'] ?? 0);
            $hire_date = $_POST['hire_date'] ?? date('Y-m-d');
            $status = $_POST['status'] ?? 'active';

            if (empty($first_name) || empty($last_name)) {
                $error_message = 'First name and last name are required';
            } else {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO hr_employees (tenant_id, branch_id, first_name, last_name, email, phone, department, position, salary, hire_date, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$tenant_id, $branch_id, $first_name, $last_name, $email, $phone, $department, $position, $salary, $hire_date, $status, $user_id]);
                    $success_message = 'Employee added successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error creating employee: " . $e->getMessage());
                    $error_message = 'Failed to add employee';
                }
            }
        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $salary = floatval($_POST['salary'] ?? 0);
            $hire_date = $_POST['hire_date'] ?? null;
            $status = $_POST['status'] ?? 'active';

            if ($id > 0 && !empty($first_name) && !empty($last_name)) {
                try {
                    $stmt = $pdo->prepare('
                        UPDATE hr_employees SET first_name = ?, last_name = ?, email = ?, phone = ?, department = ?, position = ?, salary = ?, hire_date = ?, status = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ');
                    $stmt->execute([$first_name, $last_name, $email, $phone, $department, $position, $salary, $hire_date, $status, $id, $tenant_id]);
                    $success_message = 'Employee updated successfully';
                    
                } catch (PDOException $e) {
                    error_log("Error updating employee: " . $e->getMessage());
                    $error_message = 'Failed to update employee';
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('UPDATE hr_employees SET status = "terminated", updated_at = NOW() WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$id, $tenant_id]);
                    $success_message = 'Employee terminated';
                    
                } catch (PDOException $e) {
                    error_log("Error terminating employee: " . $e->getMessage());
                    $error_message = 'Failed to terminate employee';
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();

// Fetch employees
$employees = [];
try {
    $stmt = $pdo->prepare('
        SELECT * FROM hr_employees 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ');
    $stmt->execute([$tenant_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching employees: " . $e->getMessage());
}

// Get unique departments for datalist
$departments = array_unique(array_filter(array_column($employees, 'department')));
sort($departments);

// Status badge colors
$status_colors = [
    'active' => 'bg-emerald-500/15 text-emerald-400',
    'on_leave' => 'bg-amber-500/15 text-amber-400',
    'terminated' => 'bg-red-500/15 text-red-400',
];

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-user-tie text-blue-400"></i> Employees
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Manage your organization's employees
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="openEmployeeModal()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/15 border border-blue-500/30 text-blue-400 text-sm font-medium hover:bg-blue-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Employee
        </button>
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

<!-- Employees Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1000px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Department</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Position</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Salary</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Hire Date</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center">
                        <i class="fas fa-user-tie text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No employees found</p>
                        <button onclick="openEmployeeModal()" class="mt-2 inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 transition-colors">
                            <i class="fas fa-plus text-xs"></i> Add your first employee
                        </button>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($employees as $emp):
                    $status = $emp['status'] ?? 'active';
                    $status_class = $status_colors[$status] ?? 'bg-slate-500/15 text-slate-400';
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                                <span class="text-blue-400 text-xs font-semibold"><?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?></span>
                            </div>
                            <span class="text-sm text-white font-medium"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></span>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if (!empty($emp['email'])): ?>
                        <div class="text-sm text-slate-300"><?php echo htmlspecialchars($emp['email']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($emp['phone'])): ?>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($emp['phone']); ?></div>
                        <?php endif; ?>
                        <?php if (empty($emp['email']) && empty($emp['phone'])): ?>
                        <div class="text-sm text-slate-500">—</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo htmlspecialchars($emp['department'] ?? '—'); ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo htmlspecialchars($emp['position'] ?? '—'); ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-semibold text-amber-400"><?php echo $currency . ' ' . number_format((float)$emp['salary'], 0); ?></span>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400">
                        <?php echo $emp['hire_date'] ? date('d M Y', strtotime($emp['hire_date'])) : '—'; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='editEmployee(<?php echo json_encode($emp); ?>)' 
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-blue-400 hover:bg-blue-500/20 hover:text-blue-300 transition-colors" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <?php if ($status !== 'terminated'): ?>
                            <button onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')" 
                                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-red-400 hover:bg-red-500/20 hover:text-red-300 transition-colors" title="Terminate">
                                <i class="fas fa-user-slash text-xs"></i>
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
</div>

<!-- Employee Modal -->
<div id="employeeModal" class="fixed inset-0 bg-black/70 hidden z-50 items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700/60 p-6 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto shadow-xl">
        <div class="flex justify-between items-center mb-4 sticky top-0 bg-slate-800 pb-2">
            <h3 class="text-lg font-semibold text-white" id="employeeModalTitle">Add Employee</h3>
            <button onclick="closeEmployeeModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" id="employeeAction" value="create">
            <input type="hidden" name="id" id="employeeId" value="0">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">First Name *</label>
                    <input type="text" name="first_name" id="empFirstName" required 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Last Name *</label>
                    <input type="text" name="last_name" id="empLastName" required 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" id="empEmail" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Phone</label>
                    <input type="tel" name="phone" id="empPhone" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Department</label>
                    <input type="text" name="department" id="empDepartment" list="departments" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <datalist id="departments">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Position</label>
                    <input type="text" name="position" id="empPosition" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Salary</label>
                    <input type="number" name="salary" id="empSalary" step="0.01" min="0" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Hire Date</label>
                    <input type="date" name="hire_date" id="empHireDate" value="<?php echo date('Y-m-d'); ?>" 
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Status</label>
                <select name="status" id="empStatus" 
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="active">Active</option>
                    <option value="on_leave">On Leave</option>
                    <option value="terminated">Terminated</option>
                </select>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                    Save Employee
                </button>
                <button type="button" onclick="closeEmployeeModal()" class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEmployeeModal() {
    document.getElementById('employeeModal').classList.remove('hidden');
    document.getElementById('employeeModal').style.display = 'flex';
}

function closeEmployeeModal() {
    document.getElementById('employeeModal').classList.add('hidden');
    document.getElementById('employeeModal').style.display = 'none';
    document.getElementById('employeeModalTitle').innerText = 'Add Employee';
    document.getElementById('employeeAction').value = 'create';
    document.getElementById('employeeId').value = '0';
    document.getElementById('empFirstName').value = '';
    document.getElementById('empLastName').value = '';
    document.getElementById('empEmail').value = '';
    document.getElementById('empPhone').value = '';
    document.getElementById('empDepartment').value = '';
    document.getElementById('empPosition').value = '';
    document.getElementById('empSalary').value = '';
    document.getElementById('empHireDate').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('empStatus').value = 'active';
}

function editEmployee(emp) {
    document.getElementById('employeeModalTitle').innerText = 'Edit Employee';
    document.getElementById('employeeAction').value = 'update';
    document.getElementById('employeeId').value = emp.id;
    document.getElementById('empFirstName').value = emp.first_name || '';
    document.getElementById('empLastName').value = emp.last_name || '';
    document.getElementById('empEmail').value = emp.email || '';
    document.getElementById('empPhone').value = emp.phone || '';
    document.getElementById('empDepartment').value = emp.department || '';
    document.getElementById('empPosition').value = emp.position || '';
    document.getElementById('empSalary').value = emp.salary || '';
    document.getElementById('empHireDate').value = emp.hire_date || '';
    document.getElementById('empStatus').value = emp.status || 'active';
    openEmployeeModal();
}

function deleteEmployee(id, name) {
    if (confirm('Terminate employee: ' + name + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">' +
                        '<input type="hidden" name="action" value="delete">' +
                        '<input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEmployeeModal();
    }
});

// Close modal on outside click
document.getElementById('employeeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmployeeModal();
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