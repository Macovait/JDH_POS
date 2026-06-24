<?php
/**
 * Users Management - SaaS Admin - Zero-Trust Implementation
 *
 * Manage users with controlled cross-tenant access.
 * Super Admin only with audited cross-tenant operations.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/UsersModel.php';

admin_require_super_admin();

// Initialize Zero-Trust Context and Users Model
$context = TenantContext::getInstance();
$usersModel = new UsersModel();

// Handle form submissions
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
        $action = $_POST['action'] ?? '';

        try {
        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $role = trim($_POST['role'] ?? 'staff');
                $status = $_POST['status'] ?? 'active';

                if (empty($name) || empty($email) || empty($password)) {
                    throw new Exception('Name, email, and password are required.');
                }

                if (strlen($password) < 6) {
                    throw new Exception('Password must be at least 6 characters.');
                }

                // Check if email already exists using controlled method
                if ($usersModel->checkEmailExists($email)) {
                    throw new Exception('A user with this email already exists.');
                }

                // Create user using controlled method
                $usersModel->createUser([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'tenant_id' => $tenant_id > 0 ? $tenant_id : null,
                    'role' => $role,
                    'status' => $status,
                ]);

                $message = 'User created successfully!';
                $message_type = 'success';
                break;

            case 'update':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $role = trim($_POST['role'] ?? 'staff');
                $status = $_POST['status'] ?? 'active';

                if (empty($name) || empty($email)) {
                    throw new Exception('Name and email are required.');
                }

                // Check if email is taken by another user using controlled method
                if ($usersModel->checkEmailExists($email, $user_id)) {
                    throw new Exception('This email is already taken by another user.');
                }

                $update_data = [
                    'name' => $name,
                    'email' => $email,
                    'tenant_id' => $tenant_id > 0 ? $tenant_id : null,
                    'role' => $role,
                    'status' => $status,
                ];

                // Only update password if provided
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        throw new Exception('Password must be at least 6 characters.');
                    }
                    $update_data['password'] = $password;
                }

                // Update user using controlled method
                $usersModel->updateUser($user_id, $update_data);

                $message = 'User updated successfully!';
                $message_type = 'success';
                break;

            case 'delete':
                $user_id = (int)($_POST['user_id'] ?? 0);

                // Delete user using controlled method
                $usersModel->deleteUser($user_id);

                $message = 'User deleted successfully!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$company_filter = $_GET['tenant_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build filters array
$filters = [];
if ($search) {
    $filters['search'] = $search;
}
if ($company_filter !== '' && $company_filter !== '0') {
    $filters['tenant_id'] = (int)$company_filter;
}
if ($status_filter) {
    $filters['status'] = $status_filter;
}

// Get total count using controlled method
$total = $usersModel->getTotalUsers($filters);

// Get users with company info using controlled method
$users = $usersModel->getUsers($filters, $per_page, $offset);

// Get companies list for dropdown using controlled method
$companies_list = $usersModel->getCompanies();

// Pagination
$total_pages = ceil($total / $per_page);

// Page config
$current_page = 'users';
$page_title = 'Users';
$extra_css = <<<'HTML'
<link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
<style>
    .bg-slate-800/40 border border-slate-700/60 rounded-xl {
        background: rgba(17, 24, 39, 0.6);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1rem;
    }

    .bg-slate-800/40 border border-slate-700/60 rounded-xl-rounded {
        background: rgba(17, 24, 39, 0.6);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1rem;
    }

    select option {
        background: #1f2937;
        color: #fff;
    }

    .toast-enter {
        animation: toastSlideIn 0.3s ease-out forwards;
    }

    .toast-exit {
        animation: toastSlideOut 0.3s ease-in forwards;
    }

    @keyframes toastSlideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes toastSlideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
</style>
HTML;

ob_start();
?>
<div class="space-y-6">
            <!-- Toast Notification -->
            <?php if ($message): ?>
                <div id="toast" class="fixed top-6 right-6 z-[9999] max-w-md toast-enter">
                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl-rounded px-5 py-4 flex items-center gap-3 shadow-2xl <?= $message_type === 'success' ? 'border-green-500/30' : 'border-red-500/30' ?>">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 <?= $message_type === 'success' ? 'bg-green-500/20' : 'bg-red-500/20' ?>">
                            <i class="fa-solid <?= $message_type === 'success' ? 'fa-check text-green-400' : 'fa-xmark text-red-400' ?>"></i>
                        </div>
                        <p class="text-sm <?= $message_type === 'success' ? 'text-green-300' : 'text-red-300' ?>">
                            <?= htmlspecialchars($message) ?>
                        </p>
                        <button onclick="dismissToast()" class="ml-2 text-slate-500 hover:text-white transition">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-slate-400 text-sm">Manage all users across companies</p>
                </div>
                <button onclick="openModal('createModal')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-500 text-white font-semibold rounded-xl transition shadow-lg shadow-green-600/20">
                    <i class="fa-solid fa-plus text-sm"></i>
                    Add User
                </button>
            </div>

            <!-- Filter Bar -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-slate-400 text-xs font-medium mb-1.5 uppercase tracking-wider">Search</label>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search by name or email..."
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white placeholder-gray-500 text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                        </div>
                    </div>
                    <div class="w-full sm:w-48">
                        <label class="block text-slate-400 text-xs font-medium mb-1.5 uppercase tracking-wider">Company</label>
                        <select name="tenant_id"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                            <option value="">All Companies</option>
                            <?php foreach ($companies_list as $company): ?>
                                <option value="<?= $company['id'] ?>" <?= $company_filter === (string)$company['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($company['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full sm:w-40">
                        <label class="block text-slate-400 text-xs font-medium mb-1.5 uppercase tracking-wider">Status</label>
                        <select name="status"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                            <option value="">All Status</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="px-5 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm hover:bg-slate-700/50 transition flex items-center gap-2">
                            <i class="fa-solid fa-filter text-xs"></i>
                            Filter
                        </button>
                        <a href="users.php"
                            class="px-5 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm hover:bg-slate-700/50 transition flex items-center gap-2">
                            <i class="fa-solid fa-rotate-left text-xs"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-700/60">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Company</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-16 h-16 rounded-2xl bg-slate-800/50 flex items-center justify-center">
                                                <i class="fa-solid fa-users text-3xl text-slate-600"></i>
                                            </div>
                                            <p class="text-slate-400 font-medium">No users found</p>
                                            <p class="text-slate-500 text-sm">Try adjusting your filters or create a new user.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-green-500/20 to-green-600/20 flex items-center justify-center text-green-400 font-bold text-sm">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <span class="text-white font-medium text-sm"><?= htmlspecialchars($user['name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-300"><?= htmlspecialchars($user['email']) ?></td>
                                        <td class="px-6 py-4 text-sm">
                                            <?php if (!empty($user['company_name'])): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-500/10 text-blue-300 text-xs font-medium">
                                                    <i class="fa-solid fa-building text-[10px]"></i>
                                                    <?= htmlspecialchars($user['company_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-500 text-xs">No company</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium <?php switch ($user['role']) { case 'admin': echo 'bg-purple-500/15 text-purple-300'; break; case 'manager': echo 'bg-amber-500/15 text-amber-300'; break; case 'staff': echo 'bg-sky-500/15 text-sky-300'; break; default: echo 'bg-slate-500/15 text-slate-300'; } ?>">
                                                <?= ucfirst(htmlspecialchars($user['role'] ?? 'staff')) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php switch ($user['status']) { case 'active': echo 'bg-green-500/15 text-green-400'; break; case 'inactive': echo 'bg-slate-500/15 text-slate-400'; break; case 'suspended': echo 'bg-red-500/15 text-red-400'; break; default: echo 'bg-slate-500/15 text-slate-300'; } ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?php switch ($user['status']) { case 'active': echo 'bg-green-400'; break; case 'inactive': echo 'bg-slate-400'; break; case 'suspended': echo 'bg-red-400'; break; default: echo 'bg-slate-400'; } ?>"></span>
                                                <?= ucfirst(htmlspecialchars($user['status'] ?? 'active')) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-400">
                                            <?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—' ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user)) ?>)"
                                                    class="w-8 h-8 rounded-lg bg-blue-500/15 flex items-center justify-center text-blue-400 hover:bg-blue-500/25 transition"
                                                    title="Edit">
                                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                                </button>
                                                <button onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>')"
                                                    class="w-8 h-8 rounded-lg bg-red-500/15 flex items-center justify-center text-red-400 hover:bg-red-500/25 transition"
                                                    title="Delete">
                                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-700/60 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-slate-400 text-sm">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?> users
                        </p>
                        <div class="flex items-center gap-1.5">
                            <?php
                            $query_params = http_build_query(array_filter([
                                'search' => $search,
                                'tenant_id' => $company_filter,
                                'status' => $status_filter,
                            ]));
                            $base_url = '?' . ($query_params ? $query_params . '&' : '');
                            ?>
                            <?php if ($page > 1): ?>
                                <a href="<?= $base_url ?>page=<?= $page - 1 ?>"
                                    class="w-9 h-9 rounded-lg bg-slate-800/50 flex items-center justify-center text-slate-300 hover:bg-slate-700/50 transition text-sm">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="<?= $base_url ?>page=<?= $i ?>"
                                    class="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-medium transition <?= $i === $page ? 'bg-green-600 text-white' : 'bg-slate-800/50 text-slate-300 hover:bg-slate-700/50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= $base_url ?>page=<?= $page + 1 ?>"
                                    class="w-9 h-9 rounded-lg bg-slate-800/50 flex items-center justify-center text-slate-300 hover:bg-slate-700/50 transition text-sm">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php require_once __DIR__ . '/components/footer.php'; ?>
    </div>

    <!-- Create User Modal -->
    <div id="createModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-slate-700/60">
                <div>
                    <h2 class="text-lg font-bold text-white">Add New User</h2>
                    <p class="text-slate-400 text-sm mt-0.5">Create a user account and assign to a company.</p>
                </div>
                <button onclick="closeModal('createModal')" class="w-8 h-8 rounded-lg bg-slate-800/50 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700/50 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition"
                        placeholder="Enter full name">
                </div>

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Email <span class="text-red-400">*</span></label>
                    <input type="email" name="email" required
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition"
                        placeholder="user@example.com">
                </div>

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Password <span class="text-red-400">*</span></label>
                    <input type="password" name="password" required minlength="6"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition"
                        placeholder="Min 6 characters">
                </div>

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Company</label>
                    <select name="tenant_id"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                        <option value="0">No Company (Platform User)</option>
                        <?php foreach ($companies_list as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1.5">Role</label>
                        <select name="role"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1.5">Status</label>
                        <select name="status"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('createModal')"
                        class="px-5 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm hover:bg-slate-700/50 transition font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 bg-green-600 hover:bg-green-500 rounded-xl text-white text-sm font-semibold transition shadow-lg shadow-green-600/20">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-slate-700/60">
                <div>
                    <h2 class="text-lg font-bold text-white">Edit User</h2>
                    <p class="text-slate-400 text-sm mt-0.5">Update user details and permissions.</p>
                </div>
                <button onclick="closeModal('editModal')" class="w-8 h-8 rounded-lg bg-slate-800/50 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700/50 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" id="edit_name" required
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                </div>

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Email <span class="text-red-400">*</span></label>
                    <input type="email" name="email" id="edit_email" required
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                </div>

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Password</label>
                    <input type="password" name="password" id="edit_password" minlength="6"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition"
                        placeholder="Leave blank to keep current">
                    <p class="text-slate-500 text-xs mt-1">Leave empty to keep the existing password.</p>
                </div>

                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1.5">Company</label>
                    <select name="tenant_id" id="edit_company_id"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                        <option value="0">No Company (Platform User)</option>
                        <?php foreach ($companies_list as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1.5">Role</label>
                        <select name="role" id="edit_role"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1.5">Status</label>
                        <select name="status" id="edit_status"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('editModal')"
                        class="px-5 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm hover:bg-slate-700/50 transition font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 bg-green-600 hover:bg-green-500 rounded-xl text-white text-sm font-semibold transition shadow-lg shadow-green-600/20">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl w-full max-w-md">
            <div class="p-6 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-red-500/15 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-2xl text-red-400"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Delete User</h3>
                <p class="text-slate-400 text-sm">
                    Are you sure you want to delete <strong id="deleteUserName" class="text-white"></strong>?
                    This action cannot be undone.
                </p>
            </div>
            <form method="POST" class="px-6 pb-6 flex gap-3">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <button type="button" onclick="closeModal('deleteModal')"
                    class="flex-1 px-5 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm hover:bg-slate-700/50 transition font-medium">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-5 py-2.5 bg-red-600 hover:bg-red-500 rounded-xl text-white text-sm font-semibold transition">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            el.classList.remove('hidden');
            el.classList.add('flex');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            el.classList.add('hidden');
            el.classList.remove('flex');
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_company_id').value = user.tenant_id || '0';
            document.getElementById('edit_role').value = user.role || 'staff';
            document.getElementById('edit_status').value = user.status || 'active';
            openModal('editModal');
        }

        function openDeleteModal(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            openModal('deleteModal');
        }

        function dismissToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.remove('toast-enter');
                toast.classList.add('toast-exit');
                setTimeout(() => toast.remove(), 300);
            }
        }

        // Close modals when clicking outside
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Auto-dismiss toast after 5 seconds
        (function() {
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(() => {
                    dismissToast();
                }, 5000);
            }
        })();

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['createModal', 'editModal', 'deleteModal'].forEach(id => {
                    closeModal(id);
                });
            }
        });
    </script>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
