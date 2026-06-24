<?php
/**
 * HR Employees Management
 */

require_once __DIR__ . '/../../../src/paths.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security validation failed.';
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
                        INSERT INTO hr_employees (tenant_id, branch_id, first_name, last_name, email, phone, department, position, salary, hire_date, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

ensureHrTables($pdo);

$employees = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM hr_employees WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([$tenant_id]);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching employees: " . $e->getMessage());
}

$departments = array_unique(array_filter(array_column($employees, 'department')));
sort($departments);

$csrf_token = generate_csrf_token();
$page_title = 'HR Employees | Jakababa POS';
ob_start();
?>

<style>
    .card { background: #1F2937; border: 1px solid #374151; border-radius: 1rem; }
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 { background: #111827; border: 1px solid #374151; border-radius: 0.5rem; padding: 0.5rem 0.75rem; color: white; width: 100%; }
    .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500:focus { outline: none; border-color: #FBBF24; }
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    .employee-status-active { background: rgba(16, 185, 129, 0.1); color: #10B981; }
    .employee-status-on_leave { background: rgba(251, 191, 36, 0.1); color: #FBBF24; }
    .employee-status-terminated { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
</style>

<div class="fade-in">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2">
                <i class="fas fa-user-tie text-2xl text-[#3B82F6]"></i>
                <h1 class="text-2xl font-bold text-white">Employees</h1>
            </div>
            <p class="text-gray-400 text-sm mt-1">Manage your organization's employees</p>
        </div>
        <button onclick="openEmployeeModal()" class="px-4 py-2 bg-[#3B82F6] text-white rounded-lg font-medium hover:bg-[#2563EB] transition flex items-center gap-2">
            <i class="fas fa-plus"></i>
            <span>Add Employee</span>
        </button>
    </div>

    <?php if ($success_message): ?>
        <div class="mb-4 p-4 bg-[#10B981]/10 border border-[#10B981] rounded-lg text-[#10B981]">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="mb-4 p-4 bg-[#EF4444]/10 border border-[#EF4444] rounded-lg text-[#EF4444]">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#111827] border-b border-[#374151]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Employee</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Position</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Salary</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Hire Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#374151]">
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-user-tie text-3xl mb-2 opacity-50"></i>
                                <p>No employees found</p>
                                <button onclick="openEmployeeModal()" class="text-[#3B82F6] mt-2">Add your first employee</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr class="hover:bg-[#374151]/30">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-[#3B82F6]/10 rounded-full flex items-center justify-center">
                                            <span class="text-[#3B82F6] text-sm font-semibold"><?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?></span>
                                        </div>
                                        <span class="text-white font-medium"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-400 text-sm"><?php echo htmlspecialchars($emp['email'] ?? '-'); ?></div>
                                    <div class="text-gray-500 text-xs"><?php echo htmlspecialchars($emp['phone'] ?? ''); ?></div>
                                </td>
                                <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($emp['department'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($emp['position'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-white font-semibold">KSh <?php echo number_format($emp['salary']); ?></td>
                                <td class="px-4 py-3 text-gray-400"><?php echo $emp['hire_date'] ? date('d M Y', strtotime($emp['hire_date'])) : '-'; ?></td>
                                <td class="px-4 py-3">
                                    <span class="status-badge employee-status-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <button onclick="editEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)" class="text-gray-400 hover:text-[#3B82F6]"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')" class="text-gray-400 hover:text-[#EF4444]"><i class="fas fa-user-slash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="employeeModal" class="fixed inset-0 bg-black/70 hidden z-50">
    <div class="bg-[#1F2937] rounded-xl border border-[#374151] p-6 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-white" id="employeeModalTitle">Add Employee</h3>
            <button onclick="closeEmployeeModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" id="employeeAction" value="create">
            <input type="hidden" name="id" id="employeeId" value="0">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">First Name *</label>
                    <input type="text" name="first_name" id="empFirstName" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Last Name *</label>
                    <input type="text" name="last_name" id="empLastName" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Email</label>
                    <input type="email" name="email" id="empEmail" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Phone</label>
                    <input type="tel" name="phone" id="empPhone" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Department</label>
                    <input type="text" name="department" id="empDepartment" list="departments" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <datalist id="departments">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Position</label>
                    <input type="text" name="position" id="empPosition" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Salary (KSh)</label>
                    <input type="number" name="salary" id="empSalary" step="0.01" min="0" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Hire Date</label>
                    <input type="date" name="hire_date" id="empHireDate" value="<?php echo date('Y-m-d'); ?>" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1">Status</label>
                <select name="status" id="empStatus" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="active">Active</option>
                    <option value="on_leave">On Leave</option>
                    <option value="terminated">Terminated</option>
                </select>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#3B82F6] text-white rounded-lg font-medium hover:bg-[#2563EB]">Save Employee</button>
                <button type="button" onclick="closeEmployeeModal()" class="flex-1 px-4 py-2 bg-[#374151] text-white rounded-lg hover:bg-[#4B5563]">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEmployeeModal() { document.getElementById('employeeModal').classList.remove('hidden'); }
function closeEmployeeModal() { 
    document.getElementById('employeeModal').classList.add('hidden'); 
    document.getElementById('employeeAction').value = 'create'; 
    document.getElementById('employeeId').value = '0'; 
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
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app_close.php';
require_once __DIR__ . '/../../layouts/app.php';
?>

<?php
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
                INDEX idx_company (tenant_id),
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
                INDEX idx_company (tenant_id),
                INDEX idx_employee (employee_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("HR tables error: " . $e->getMessage());
    }
}
?>