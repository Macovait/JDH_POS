<?php
/**
 * User Add/Edit Form - Slim & Compact Design
 * Layout: Main Content (Left) + Sidebar (Right) with Slim Cards
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('users.manage') && !is_super_admin()) {
    enforce_permission('users.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();
$is_superadmin = is_super_admin();

if (empty($tenant_id)) {
    die('Access denied: tenant context required.');
}

$csrf_token = generate_csrf_token();

// Detect available columns
$has_phone = false;
$has_address = false;
$has_branch_id = false;

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $has_phone = $stmt->rowCount() > 0;
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'address'");
    $has_address = $stmt->rowCount() > 0;
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
    $has_branch_id = $stmt->rowCount() > 0;
} catch (Exception $e) {}

// Get roles with colors
$roles_by_id = [];
$roles_list = [];
$role_colors = [
    'super admin' => ['color' => '#FBBF24', 'bg' => 'rgba(251,191,36,0.15)'],
    'admin' => ['color' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.15)'],
    'manager' => ['color' => '#8B5CF6', 'bg' => 'rgba(139,92,246,0.15)'],
    'cashier' => ['color' => '#10B981', 'bg' => 'rgba(16,185,129,0.15)'],
    'staff' => ['color' => '#6B7280', 'bg' => 'rgba(107,114,128,0.15)'],
];
try {
    $col_stmt = $pdo->query("DESCRIBE roles");
    $role_columns = [];
    while ($col = $col_stmt->fetch(PDO::FETCH_ASSOC)) {
        $role_columns[] = $col['Field'];
    }
    
    $tenant_col = in_array('tenant_id', $role_columns) ? 'tenant_id' : 'NULL';
    $color_select = in_array('color', $role_columns) ? ', color, bg_color, text_color' : '';
    
    if ($tenant_col === 'NULL') {
        // No tenant_id column - get all non-deleted roles
        $deleted_check = in_array('deleted_at', $role_columns) ? "WHERE deleted_at IS NULL" : "";
        $stmt = $pdo->query("SELECT id, name $color_select FROM roles $deleted_check ORDER BY name");
        $db_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Filter by tenant_id (system roles + tenant-specific roles)
        $deleted_check = in_array('deleted_at', $role_columns) ? "AND deleted_at IS NULL" : "";
        $stmt = $pdo->prepare("SELECT id, name $color_select FROM roles WHERE ($tenant_col = ? OR $tenant_col IS NULL OR is_system = 1) $deleted_check ORDER BY name");
        $stmt->execute([$tenant_id]);
        $db_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($db_roles as $role) {
        $name_lower = strtolower($role['name']);
        $roles_by_id[$role['id']] = [
            'id' => $role['id'],
            'name' => $role['name'],
            'label' => $role['name'],
            'color' => $role['color'] ?? ($role_colors[$name_lower]['color'] ?? '#6B7280'),
            'bg_color' => $role['bg_color'] ?? ($role_colors[$name_lower]['bg'] ?? 'rgba(107,114,128,0.15)'),
            'text_color' => $role['text_color'] ?? ($role['color'] ?? '#6B7280'),
            'description' => $role['description'] ?? ''
        ];
        $roles_list[] = $roles_by_id[$role['id']];
    }
} catch (Exception $e) {
    error_log("Roles query error: " . $e->getMessage());
}

// Get branches
$branches = [];
try {
    // Check if branches table has deleted_at column
    $has_branch_deleted = false;
    try { 
        $stmt = $pdo->query("SHOW COLUMNS FROM branches LIKE 'deleted_at'");
        $has_branch_deleted = $stmt->rowCount() > 0;
    } catch (Exception $e) {}
    
    // Query all branches for this tenant (users can be assigned to any branch in the company)
    $sql = "SELECT id, name, code, location FROM branches WHERE tenant_id = ?";
    if ($has_branch_deleted) {
        $sql .= " AND deleted_at IS NULL";
    }
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Branches query error: " . $e->getMessage());
}

// Fetch existing user
$user = null;
$edit_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($edit_user_id > 0) {
    try {
        $select_fields = "id, name, email, username, role_id, is_active";
        if ($has_phone) $select_fields .= ", phone";
        if ($has_address) $select_fields .= ", address";
        if ($has_branch_id) $select_fields .= ", branch_id";
        
        $stmt = $pdo->prepare("SELECT {$select_fields} FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$edit_user_id, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            header('Location: users.php?error=' . urlencode('User not found'));
            exit;
        }
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        header('Location: users.php?error=' . urlencode('Error loading user data'));
        exit;
    }
}

$is_edit = $edit_user_id > 0;
$duplicate_from = isset($_GET['duplicate_from']) ? intval($_GET['duplicate_from']) : 0;
if ($duplicate_from > 0 && !$is_edit) {
    try {
        $select_fields = "id, name, email, username, role_id, is_active";
        if ($has_phone) $select_fields .= ", phone";
        if ($has_address) $select_fields .= ", address";
        if ($has_branch_id) $select_fields .= ", branch_id";
        $stmt = $pdo->prepare("SELECT {$select_fields} FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$duplicate_from, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user['name'] = $user['name'] . ' (Copy)';
            $user['email'] = '';
            $user['username'] = '';
        }
    } catch (Exception $e) {
        error_log("Error fetching user for duplicate: " . $e->getMessage());
    }
}
$is_duplicate = $duplicate_from > 0 && !$is_edit;

// Handle POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please try again.';
    }
    
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = !empty($_POST['role_id']) ? (int)$_POST['role_id'] : null;
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $username = trim($_POST['username'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($username) && !empty($email)) {
        $username = explode('@', $email)[0];
    }

    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($role_id)) $errors[] = 'Role is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    
    if (!empty($phone)) {
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone_clean) < 10) {
            $errors[] = 'Phone number must be at least 10 digits';
        }
    }
    
    if (!empty($username) && !preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, underscores and hyphens';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND tenant_id = ? AND deleted_at IS NULL");
                $stmt->execute([$email, $id, $tenant_id]);
                if ($stmt->fetch()) $errors[] = 'Email already exists';
                
                if (!empty($username)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? AND tenant_id = ? AND deleted_at IS NULL");
                    $stmt->execute([$username, $id, $tenant_id]);
                    if ($stmt->fetch()) $errors[] = 'Username already exists';
                }
                
                if (empty($errors)) {
                    $sql = "UPDATE users SET name = ?, email = ?, username = ?, role_id = ?, is_active = ?";
                    $params = [$name, $email, $username ?: null, $role_id, $is_active];
                    
                    if ($has_phone) { $sql .= ", phone = ?"; $params[] = $phone ?: null; }
                    if ($has_address) { $sql .= ", address = ?"; $params[] = $address ?: null; }
                    if ($has_branch_id) { $sql .= ", branch_id = ?"; $params[] = $branch_id; }
                    
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $errors[] = 'Password must be at least 6 characters';
                        } else {
                            $sql .= ", password_hash = ?";
                            $params[] = password_hash($password, PASSWORD_DEFAULT);
                            $sql .= ", require_password_change = 1";
                        }
                    }
                    
                    $sql .= ", updated_at = NOW() WHERE id = ? AND tenant_id = ?";
                    $params[] = $id;
                    $params[] = $tenant_id;
                    
                    if (empty($errors)) {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $pdo->commit();
                        header('Location: users.php?success=' . urlencode('User updated successfully'));
                        exit;
                    }
                }
            } else {
                // CREATE — enforce user quota
                require_once __DIR__ . '/../../src/Security/PlanEnforcement.php';
                PlanEnforcement::loadTenantPlan($pdo, $tenant_id);
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL");
                $countStmt->execute([$tenant_id]);
                $userCount = (int) $countStmt->fetchColumn();
                if (!PlanEnforcement::checkLimit('max_users', $userCount)) {
                    $errors[] = 'User limit reached for your plan. Please upgrade to add more users.';
                }

                if (empty($password)) {
                    $errors[] = 'Password is required for new users';
                } elseif (strlen($password) < 6) {
                    $errors[] = 'Password must be at least 6 characters';
                }
                
                if (empty($errors)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND deleted_at IS NULL");
                    $stmt->execute([$email, $tenant_id]);
                    if ($stmt->fetch()) $errors[] = 'Email already exists';
                    
                    if (!empty($username)) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND tenant_id = ? AND deleted_at IS NULL");
                        $stmt->execute([$username, $tenant_id]);
                        if ($stmt->fetch()) $errors[] = 'Username already exists';
                    }
                    
                    if (empty($errors)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        $fields = ['tenant_id', 'name', 'email', 'username', 'role_id', 'password_hash', 'is_active', 'created_at'];
                        $placeholders = ['?', '?', '?', '?', '?', '?', '1', 'NOW()'];
                        $params = [$tenant_id, $name, $email, $username ?: null, $role_id, $hashed_password];
                        
                        if ($has_phone) { $fields[] = 'phone'; $placeholders[] = '?'; $params[] = $phone ?: null; }
                        if ($has_address) { $fields[] = 'address'; $placeholders[] = '?'; $params[] = $address ?: null; }
                        if ($has_branch_id) { $fields[] = 'branch_id'; $placeholders[] = '?'; $params[] = $branch_id; }
                        
                        $sql = "INSERT INTO users (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        
                        $pdo->commit();
                        header('Location: users.php?success=' . urlencode('User created successfully'));
                        exit;
                    }
                }
            }
            $pdo->rollBack();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error: " . $e->getMessage());
            $errors[] = 'Database error: ' . (isset($_GET['debug']) ? $e->getMessage() : 'Please try again later');
        }
    }
}

$page_title = ($is_edit ? 'Edit' : ($is_duplicate ? 'Duplicate' : 'Add')) . ' User';
ob_start();
?>

<div class="space-y-4" id="user-form-content">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Users</p>
            <h1 class="text-lg font-bold text-white"><?php echo $is_edit ? 'Edit User' : ($is_duplicate ? 'Duplicate User' : 'Add User'); ?></h1>
        </div>
        <div>
            <a href="users.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-arrow-left text-xs"></i> Back</a>
        </div>
    </div>

    <!-- Error Messages - Slim -->
    <?php if (!empty($errors)): ?>
        <div class="mb-3 p-2 rounded-lg bg-red-500/10 border border-red-500/30">
            <div class="flex items-start gap-2">
                <i class="fas fa-exclamation-triangle text-red-400 text-xs mt-0.5"></i>
                <div>
                    <strong class="text-red-400 text-xs">Please fix:</strong>
                    <ul class="mt-1 space-y-0.5">
                        <?php foreach ($errors as $error): ?>
                            <li class="text-white/70 text-xs flex items-center gap-1">
                                <i class="fas fa-circle text-red-400 text-[4px]"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Two Column Layout -->
    <div class="flex flex-col lg:flex-row gap-4">
        
        <!-- LEFT SIDE - Main Form -->
        <div class="flex-1 min-w-0">
            <form method="POST" id="userForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id" value="<?php echo $edit_user_id; ?>">

                <!-- Basic Information Card -->
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-2 mb-2 pb-1 border-b border-slate-700">
                        <i class="fas fa-id-card text-amber-400 text-xs"></i>
                        <h2 class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider">Basic Information</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Full Name <span class="text-red-400">*</span></label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Email <span class="text-red-400">*</span></label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400"
                                placeholder="auto-generated">
                            <p class="text-[9px] text-slate-500 mt-0.5">Auto-generated from email if empty</p>
                        </div>
                        <?php if ($has_phone): ?>
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400"
                                placeholder="+254 XXX XXX XXX">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Role Card -->
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-2 mb-2 pb-1 border-b border-slate-700">
                        <i class="fas fa-user-shield text-purple-400 text-xs"></i>
                        <h2 class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider">Role & Branch</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Role <span class="text-red-400">*</span></label>
                            <?php if (empty($roles_list)): ?>
                                <div class="w-full px-2 py-1.5 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-xs">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>No roles found. Please create roles first.
                                </div>
                            <?php else: ?>
                                <select name="role_id" required class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400" id="roleSelect">
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles_list as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                            data-color="<?php echo htmlspecialchars($role['color']); ?>"
                                            data-bg="<?php echo htmlspecialchars($role['bg_color']); ?>"
                                            <?php echo (isset($user['role_id']) && $user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_branch_id): ?>
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Branch</label>
                            <?php if (empty($branches)): ?>
                                <div class="w-full px-2 py-1.5 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-400 text-xs">
                                    <i class="fas fa-info-circle mr-1"></i>No branches found. User will have no branch assigned.
                                </div>
                            <?php else: ?>
                                <select name="branch_id" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400">
                                    <option value="">-- No Branch --</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?php echo $b['id']; ?>" 
                                            <?php echo (isset($user['branch_id']) && $user['branch_id'] == $b['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Role Preview -->
                    <div id="rolePreview" class="mt-2 p-1.5 bg-slate-900/30 rounded-md hidden items-center gap-2">
                        <i class="fas fa-info-circle text-amber-400 text-[10px]"></i>
                        <span class="text-[10px] text-slate-400">Selected:</span>
                        <span id="rolePreviewBadge" class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-medium"></span>
                    </div>
                </div>

                <!-- Security Card -->
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-2 mb-2 pb-1 border-b border-slate-700">
                        <i class="fas fa-lock text-emerald-400 text-xs"></i>
                        <h2 class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider">Security</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">
                                <?php echo $is_edit ? 'New Password' : 'Password'; ?>
                                <?php if (!$is_edit): ?><span class="text-red-400">*</span><?php endif; ?>
                            </label>
                            <input type="password" name="password" id="password" 
                                <?php echo $is_edit ? '' : 'required'; ?> minlength="6"
                                class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400"
                                placeholder="<?php echo $is_edit ? 'Leave empty to keep' : 'Minimum 6 chars'; ?>">
                            <div class="mt-1 h-1 bg-slate-700 rounded-full overflow-hidden">
                                <div id="passwordStrength" class="h-full w-0 transition-all rounded-full"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Status</label>
                            <div class="flex items-center gap-2">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" id="statusToggle" class="sr-only peer"
                                        <?php echo (!$is_edit) || (isset($user['is_active']) && $user['is_active'] == 1) ? 'checked' : ''; ?>>
                                    <div class="w-8 h-4 bg-slate-700 rounded-full peer peer-checked:bg-amber-500 peer-checked:after:translate-x-4 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all"></div>
                                    <span class="ml-2 text-xs text-slate-300" id="statusLabel">
                                        <?php echo (!$is_edit) || (isset($user['is_active']) && $user['is_active'] == 1) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Card -->
                <?php if ($has_address): ?>
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-2 mb-2 pb-1 border-b border-slate-700">
                        <i class="fas fa-map-marker-alt text-blue-400 text-xs"></i>
                        <h2 class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider">Address</h2>
                    </div>
                    <textarea name="address" rows="2" class="w-full px-2 py-1.5 bg-slate-900 border border-slate-600 rounded-lg text-white text-xs focus:outline-none focus:border-amber-400 resize-y"
                        placeholder="Enter address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <?php endif; ?>

                <!-- Form Actions -->
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors"><i class="fas fa-save text-xs"></i> <?php echo $is_edit ? 'Update' : 'Create'; ?></button>
                    <a href="users.php" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-times text-xs"></i> Cancel</a>
                </div>
            </form>
        </div>

        <!-- RIGHT SIDE - Sidebar -->
        <div class="lg:w-56 flex-shrink-0">
            <div class="sticky top-6 space-y-3">
                
                <!-- Profile Card -->
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3 text-center">
                    <div class="w-14 h-14 mx-auto mb-2 bg-slate-700 rounded-xl flex items-center justify-center text-lg font-bold text-white">
                        <?php 
                            $initial = strtoupper(substr($user['name'] ?? ($_POST['name'] ?? 'N'), 0, 2));
                            echo htmlspecialchars($initial);
                        ?>
                    </div>
                    <div class="font-semibold text-white text-sm" id="sidebarName">
                        <?php echo htmlspecialchars($user['name'] ?? ($_POST['name'] ?? 'New User')); ?>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5" id="sidebarEmail">
                        <?php echo htmlspecialchars($user['email'] ?? ($_POST['email'] ?? 'user@example.com')); ?>
                    </div>
                </div>

                <!-- Quick Info -->
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3">
                    <h3 class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                        <i class="fas fa-info-circle text-[9px]"></i> Quick Info
                    </h3>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-[10px]">Status</span>
                            <span class="font-semibold text-[10px]" id="sidebarStatus">
                                <?php echo (!$is_edit) || (isset($user['is_active']) && $user['is_active'] == 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-[10px]">ID</span>
                            <span class="font-mono text-slate-300 text-[10px]">#<?php echo $edit_user_id ?: 'New'; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-[10px]">Created</span>
                            <span class="text-slate-300 text-[10px]"><?php echo $is_edit ? date('M d, Y') : 'Today'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-3">
                    <h3 class="text-[10px] font-semibold text-amber-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                        <i class="fas fa-question-circle text-[9px]"></i> Help
                    </h3>
                    <p class="text-[10px] text-slate-400 mb-2">Need help managing users?</p>
                    <a href="#" class="text-[10px] text-amber-400 hover:text-amber-300">View Documentation →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live profile update
const nameInput = document.querySelector('input[name="name"]');
const emailInput = document.querySelector('input[name="email"]');
const sidebarName = document.getElementById('sidebarName');
const sidebarEmail = document.getElementById('sidebarEmail');
const profileAvatar = document.querySelector('.w-14.h-14');

if (nameInput && sidebarName) {
    nameInput.addEventListener('input', function() {
        sidebarName.textContent = this.value || 'New User';
        if (profileAvatar) {
            const initials = this.value.substring(0, 2).toUpperCase() || 'NU';
            profileAvatar.textContent = initials;
        }
    });
}
if (emailInput && sidebarEmail) {
    emailInput.addEventListener('input', function() {
        sidebarEmail.textContent = this.value || 'user@example.com';
    });
}

// Password strength
const pwdInput = document.getElementById('password');
const strengthBar = document.getElementById('passwordStrength');
function checkStrength(password) {
    let score = 0;
    let color = '#ef4444';
    let width = 0;
    if (password.length >= 6) score++;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[$@#&!]/.test(password)) score++;
    switch(score) {
        case 0: case 1: width = 20; color = '#ef4444'; break;
        case 2: width = 40; color = '#f59e0b'; break;
        case 3: width = 60; color = '#eab308'; break;
        case 4: width = 80; color = '#3b82f6'; break;
        default: width = 100; color = '#10b981';
    }
    return { width, color };
}
if (pwdInput && strengthBar) {
    pwdInput.addEventListener('input', function() {
        const { width, color } = checkStrength(this.value);
        strengthBar.style.width = width + '%';
        strengthBar.style.backgroundColor = color;
    });
}

// Role preview
const roleSelect = document.getElementById('roleSelect');
const rolePreview = document.getElementById('rolePreview');
const rolePreviewBadge = document.getElementById('rolePreviewBadge');
if (roleSelect && rolePreview && rolePreviewBadge) {
    function updateRolePreview() {
        const opt = roleSelect.options[roleSelect.selectedIndex];
        if (opt && opt.value) {
            const name = opt.text;
            const color = opt.dataset.color || '#6B7280';
            const bgColor = opt.dataset.bg || (color + '20');
            rolePreviewBadge.innerHTML = name;
            rolePreviewBadge.style.backgroundColor = bgColor;
            rolePreviewBadge.style.color = color;
            rolePreview.classList.remove('hidden');
            rolePreview.classList.add('flex');
        } else {
            rolePreview.classList.add('hidden');
            rolePreview.classList.remove('flex');
        }
    }
    roleSelect.addEventListener('change', updateRolePreview);
    updateRolePreview();
} else if (rolePreview) {
    // Hide preview if no role select dropdown (empty roles)
    rolePreview.classList.add('hidden');
}

// Status toggle
const statusToggle = document.getElementById('statusToggle');
const statusLabel = document.getElementById('statusLabel');
const sidebarStatus = document.getElementById('sidebarStatus');
if (statusToggle && statusLabel) {
    statusToggle.addEventListener('change', function() {
        const status = this.checked ? 'Active' : 'Inactive';
        statusLabel.textContent = status;
        if (sidebarStatus) sidebarStatus.textContent = status;
    });
}

// Form validation
const userForm = document.getElementById('userForm');
const hasRoles = <?php echo json_encode(!empty($roles_list)); ?>;

if (userForm) {
    userForm.addEventListener('submit', function(e) {
        const name = document.querySelector('input[name="name"]')?.value.trim();
        const email = document.querySelector('input[name="email"]')?.value.trim();
        const role = document.querySelector('select[name="role_id"]')?.value;
        const password = document.querySelector('input[name="password"]')?.value;
        const isEdit = <?php echo json_encode($is_edit); ?>;
        
        if (!name) { e.preventDefault(); alert('Name is required'); return false; }
        if (!email) { e.preventDefault(); alert('Email is required'); return false; }
        if (!hasRoles) { 
            e.preventDefault(); 
            alert('No roles available. Please create roles first before adding users.'); 
            return false; 
        }
        if (!role) { e.preventDefault(); alert('Role is required'); return false; }
        if (!isEdit && (!password || password.length === 0)) {
            e.preventDefault(); alert('Password is required for new users'); return false;
        }
        if (password && password.length > 0 && password.length < 6) {
            e.preventDefault(); alert('Password must be at least 6 characters'); return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault(); alert('Please enter a valid email address'); return false;
        }
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') window.location.href = 'users.php';
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault(); userForm?.submit();
    }
});

// Auto-focus
document.querySelector('input[name="name"]')?.focus();

// Unsaved changes warning
let formChanged = false;
document.querySelectorAll('#userForm input, #userForm select, #userForm textarea').forEach(input => {
    if (input.type !== 'password') {
        input.addEventListener('input', () => { formChanged = true; });
    }
    input.addEventListener('change', () => { formChanged = true; });
});
window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Leave anyway?';
        return e.returnValue;
    }
});
userForm?.addEventListener('submit', () => { formChanged = false; });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>