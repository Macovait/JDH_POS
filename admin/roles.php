<?php
/**
 * Roles Management - SaaS Admin - Zero-Trust Implementation
 *
 * Manage roles and permissions with controlled cross-tenant access.
 * Super Admin only with audited cross-tenant operations.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/RolesModel.php';

admin_require_super_admin();

// Initialize Zero-Trust Context and Roles Model
$context = TenantContext::getInstance();
$rolesModel = new RolesModel();

$message = '';
$message_type = '';

// Generate CSRF token
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Get permission modules from controlled model
$permission_modules = $rolesModel->getPermissionModules();

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
                $slug = trim($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;

                if (empty($name) || empty($slug)) {
                    throw new Exception('Name and slug are required.');
                }

                // Check slug uniqueness using controlled method
                if ($rolesModel->checkSlugExists($slug)) {
                    throw new Exception('Role slug already exists.');
                }

                // Build permissions array
                $permissions = [];
                foreach ($permission_modules as $module => $mod) {
                    foreach ($mod['actions'] as $act) {
                        if (isset($_POST["perm_{$module}_{$act}"])) {
                            $permissions[$module][] = $act;
                        }
                    }
                }

                // Create role using controlled method
                $rolesModel->createRole([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'permissions' => json_encode($permissions),
                    'is_system' => 0,
                    'active' => $active,
                ]);

                $message = 'Role created successfully!';
                $message_type = 'success';
                break;

            case 'update':
                $role_id = (int) ($_POST['role_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;

                if (empty($name) || empty($slug)) {
                    throw new Exception('Name and slug are required.');
                }

                // Check slug uniqueness using controlled method
                if ($rolesModel->checkSlugExists($slug, $role_id)) {
                    throw new Exception('Role slug already exists.');
                }

                // Build permissions array
                $permissions = [];
                foreach ($permission_modules as $module => $mod) {
                    foreach ($mod['actions'] as $act) {
                        if (isset($_POST["perm_{$module}_{$act}"])) {
                            $permissions[$module][] = $act;
                        }
                    }
                }

                // Update role using controlled method
                $rolesModel->updateRole($role_id, [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'permissions' => json_encode($permissions),
                    'active' => $active,
                ]);

                $message = 'Role updated successfully!';
                $message_type = 'success';
                break;

            case 'delete':
                $role_id = (int) ($_POST['role_id'] ?? 0);

                // Delete role using controlled method (includes validation)
                $rolesModel->deleteRole($role_id);

                $message = 'Role deleted successfully!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    }
}

// Get roles with controlled access
$roles = $rolesModel->getRoles();

$current_page = 'roles';
$page_title = 'Roles';
 $extra_css = <<<'HTML'
<link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
<style>
    .glass { background: rgba(17, 24, 39, 0.6); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.1); border-radius: 1rem; }
    .perm-checkbox { accent-color: #22c55e; }
</style>
HTML;

ob_start();
?>
<div class="space-y-6">
            <!-- Toast Notification -->
            <?php if ($message): ?>
                <div id="toast" class="fixed top-5 right-5 z-50 max-w-sm w-full glass p-4 flex items-start gap-3 shadow-2xl animate-slide-in <?= $message_type === 'error' ? 'border-red-500/40' : 'border-green-500/40' ?>">
                    <div class="mt-0.5">
                        <?php if ($message_type === 'error'): ?>
                            <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-xmark text-red-400"></i>
                            </div>
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-check text-green-400"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold <?= $message_type === 'error' ? 'text-red-400' : 'text-green-400' ?>">
                            <?= $message_type === 'error' ? 'Error' : 'Success' ?>
                        </p>
                        <p class="text-sm text-slate-300 mt-0.5"><?= htmlspecialchars($message) ?></p>
                    </div>
                    <button onclick="document.getElementById('toast').remove()" class="text-slate-500 hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Header Row -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-white">Roles Management</h2>
                    <p class="text-sm text-slate-400 mt-1">Configure roles and permissions for the POS system</p>
                </div>
                <button onclick="openCreateModal()" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl flex items-center gap-2 transition-colors">
                    <i class="fa-solid fa-plus"></i>
                    Add Role
                </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass rounded-xl p-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Total Roles</p>
                    <p class="text-lg font-bold text-white mt-1"><?= count($roles) ?></p>
                </div>
                <div class="glass rounded-xl p-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wider">System Roles</p>
                    <p class="text-2xl font-bold text-blue-400 mt-1"><?= count(array_filter($roles, fn($r) => $r['is_system'])) ?></p>
                </div>
                <div class="glass rounded-xl p-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Custom Roles</p>
                    <p class="text-2xl font-bold text-green-400 mt-1"><?= count(array_filter($roles, fn($r) => !$r['is_system'])) ?></p>
                </div>
                <div class="glass rounded-xl p-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Active</p>
                    <p class="text-2xl font-bold text-emerald-400 mt-1"><?= count(array_filter($roles, fn($r) => $r['active'])) ?></p>
                </div>
            </div>

            <!-- Roles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($roles as $role):
                    $perms = json_decode($role['permissions'] ?? '{}', true);
                    $perm_count = 0;
                    if (isset($perms['all']) && $perms['all'] === true) {
                        $perm_count = count($permission_modules);
                    } else {
                        foreach ($perms as $actions) {
                            if (is_array($actions)) {
                                $perm_count += count($actions);
                            }
                        }
                    }
                ?>
                    <div class="glass rounded-2xl p-5 hover:border-slate-700/70 transition-all group">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-xl bg-green-500/15 flex items-center justify-center">
                                    <i class="fa-solid fa-user-shield text-green-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-white"><?= htmlspecialchars($role['name']) ?></h3>
                                    <p class="text-xs text-slate-500"><?= htmlspecialchars($role['slug']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <?php if ($role['is_system']): ?>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-blue-500/15 text-blue-400 rounded-full">System</span>
                                <?php endif; ?>
                                <?php if ($role['active']): ?>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-green-500/15 text-green-400 rounded-full">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-slate-500/15 text-slate-400 rounded-full">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($role['description']): ?>
                            <p class="text-sm text-slate-400 mb-3 line-clamp-2"><?= htmlspecialchars($role['description']) ?></p>
                        <?php endif; ?>

                        <div class="flex items-center gap-4 mb-4 text-sm">
                            <div class="flex items-center gap-1.5 text-slate-400">
                                <i class="fa-solid fa-key text-xs"></i>
                                <span><?= $perm_count ?> permission<?= $perm_count !== 1 ? 's' : '' ?></span>
                            </div>
                            <?php if (isset($perms['all']) && $perms['all'] === true): ?>
                                <div class="flex items-center gap-1.5 text-yellow-400">
                                    <i class="fa-solid fa-crown text-xs"></i>
                                    <span class="text-xs font-medium">Full Access</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Permission modules preview -->
                        <?php if (!(isset($perms['all']) && $perms['all'] === true) && !empty($perms)): ?>
                            <div class="flex flex-wrap gap-1.5 mb-4">
                                <?php foreach ($perms as $mod => $actions):
                                    if (!is_array($actions) || !isset($permission_modules[$mod])) continue;
                                ?>
                                    <span class="px-2 py-0.5 text-[11px] bg-slate-800/50 text-slate-300 rounded-md">
                                        <?= htmlspecialchars($permission_modules[$mod]['label']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex gap-2 pt-3 border-t border-slate-700/30">
                            <button onclick='openEditModal(<?= json_encode($role, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                class="flex-1 px-3 py-2 bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 rounded-lg text-sm font-medium transition-colors">
                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                            </button>
                            <?php if (!$role['is_system']): ?>
                                <button onclick="openDeleteModal(<?= $role['id'] ?>, '<?= htmlspecialchars(addslashes($role['name'])) ?>')"
                                    class="px-3 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-lg text-sm font-medium transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($roles)): ?>
                    <div class="col-span-full text-center py-16">
                        <i class="fa-solid fa-user-shield text-5xl text-slate-700 mb-4"></i>
                        <p class="text-slate-400 text-lg">No roles found</p>
                        <p class="text-slate-500 text-sm mt-1">Create your first role to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/components/footer.php'; ?>
    </div>

    <!-- Create/Edit Modal -->
    <div id="roleModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="glass rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 glass rounded-t-2xl px-6 py-4 border-b border-slate-700/60 flex items-center justify-between z-10">
                <h2 id="roleModalTitle" class="text-lg font-bold text-white">Add Role</h2>
                <button onclick="closeRoleModal()" class="text-slate-400 hover:text-white w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-700/50 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="roleForm" method="POST" class="p-6">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" id="roleFormAction" value="create">
                <input type="hidden" name="role_id" id="roleFormId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Role Name <span class="text-red-400">*</span></label>
                        <input type="text" name="name" id="roleName" required autocomplete="off"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition-colors"
                            placeholder="e.g. Branch Manager">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Slug <span class="text-red-400">*</span></label>
                        <input type="text" name="slug" id="roleSlug" required autocomplete="off"
                            class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition-colors"
                            placeholder="e.g. branch_manager">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Description</label>
                    <textarea name="description" id="roleDescription" rows="2"
                        class="w-full px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-white text-sm placeholder-gray-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/20 transition-colors resize-none"
                        placeholder="Brief description of this role..."></textarea>
                </div>

                <div class="mb-5">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="active" id="roleActive" checked
                            class="w-4 h-4 rounded border-slate-700/70 bg-slate-800/50 text-green-500 focus:ring-green-500/20 perm-checkbox">
                        <span class="text-sm text-slate-300">Active</span>
                    </label>
                </div>

                <!-- Permissions -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-slate-300">Permissions</label>
                        <button type="button" onclick="toggleAllPermissions()" class="text-xs text-green-400 hover:text-green-300 font-medium">
                            Toggle All
                        </button>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($permission_modules as $module => $mod): ?>
                            <div class="bg-slate-700/30 border border-slate-700/30 rounded-xl p-3">
                                <div class="flex items-center gap-2 mb-2.5">
                                    <i class="fa-solid <?= $mod['icon'] ?> text-green-400 text-sm w-5"></i>
                                    <span class="text-sm font-semibold text-white"><?= $mod['label'] ?></span>
                                </div>
                                <div class="flex flex-wrap gap-x-5 gap-y-2 pl-7">
                                    <?php foreach ($mod['actions'] as $action): ?>
                                        <label class="flex items-center gap-2 cursor-pointer group">
                                            <input type="checkbox" name="perm_<?= $module ?>_<?= $action ?>"
                                                id="perm_<?= $module ?>_<?= $action ?>"
                                                class="w-3.5 h-3.5 rounded border-slate-700/70 bg-slate-800/50 text-green-500 focus:ring-green-500/20 perm-checkbox perm-item">
                                            <span class="text-sm text-slate-400 group-hover:text-slate-200 capitalize transition-colors"><?= $action ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-700/30">
                    <button type="button" onclick="closeRoleModal()"
                        class="px-5 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-slate-300 text-sm font-medium hover:bg-slate-700/50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="roleFormSubmit"
                        class="px-5 py-2.5 bg-green-600 hover:bg-green-700 rounded-xl text-white text-sm font-semibold transition-colors">
                        Create Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="glass rounded-2xl w-full max-w-sm p-6">
            <div class="w-14 h-14 rounded-2xl bg-red-500/15 flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-triangle-exclamation text-2xl text-red-400"></i>
            </div>
            <h3 class="text-lg font-bold text-white text-center mb-2">Delete Role</h3>
            <p class="text-sm text-slate-400 text-center mb-1">Are you sure you want to delete</p>
            <p id="deleteRoleName" class="text-sm font-semibold text-white text-center mb-5"></p>
            <p class="text-xs text-slate-500 text-center mb-6">This action cannot be undone. Users assigned to this role will lose their permissions.</p>
            <form method="POST" class="flex gap-3">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="role_id" id="deleteRoleId" value="">
                <button type="button" onclick="closeDeleteModal()"
                    class="flex-1 px-4 py-2.5 bg-slate-800/50 border border-slate-700/60 rounded-xl text-slate-300 text-sm font-medium hover:bg-slate-700/50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 rounded-xl text-white text-sm font-semibold transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <script>
        function slugify(text) {
            return text.toString().toLowerCase()
                .replace(/\s+/g, '_')
                .replace(/[^\w_]+/g, '')
                .replace(/__+/g, '_')
                .replace(/^_+/, '')
                .replace(/_+$/, '');
        }

        const nameInput = document.getElementById('roleName');
        const slugInput = document.getElementById('roleSlug');
        let slugManuallyEdited = false;

        slugInput.addEventListener('input', function() {
            slugManuallyEdited = true;
        });

        nameInput.addEventListener('input', function() {
            if (!slugManuallyEdited) {
                slugInput.value = slugify(this.value);
            }
        });

        function openCreateModal() {
            document.getElementById('roleModalTitle').textContent = 'Add Role';
            document.getElementById('roleFormAction').value = 'create';
            document.getElementById('roleFormId').value = '';
            document.getElementById('roleFormSubmit').textContent = 'Create Role';
            nameInput.value = '';
            slugInput.value = '';
            slugManuallyEdited = false;
            document.getElementById('roleDescription').value = '';
            document.getElementById('roleActive').checked = true;
            document.querySelectorAll('.perm-item').forEach(cb => cb.checked = false);
            const modal = document.getElementById('roleModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openEditModal(role) {
            document.getElementById('roleModalTitle').textContent = 'Edit Role';
            document.getElementById('roleFormAction').value = 'update';
            document.getElementById('roleFormId').value = role.id;
            document.getElementById('roleFormSubmit').textContent = 'Update Role';
            nameInput.value = role.name;
            slugInput.value = role.slug;
            slugManuallyEdited = true;
            document.getElementById('roleDescription').value = role.description || '';
            document.getElementById('roleActive').checked = role.active == 1;

            document.querySelectorAll('.perm-item').forEach(cb => cb.checked = false);

            if (role.permissions) {
                try {
                    let perms = typeof role.permissions === 'string' ? JSON.parse(role.permissions) : role.permissions;
                    if (perms && perms.all === true) {
                        document.querySelectorAll('.perm-item').forEach(cb => cb.checked = true);
                    } else if (perms) {
                        for (const [mod, actions] of Object.entries(perms)) {
                            if (Array.isArray(actions)) {
                                actions.forEach(act => {
                                    const cb = document.getElementById('perm_' + mod + '_' + act);
                                    if (cb) cb.checked = true;
                                });
                            }
                        }
                    }
                } catch (e) {}
            }

            const modal = document.getElementById('roleModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRoleModal() {
            const modal = document.getElementById('roleModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteRoleId').value = id;
            document.getElementById('deleteRoleName').textContent = '"' + name + '"';
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function toggleAllPermissions() {
            const items = document.querySelectorAll('.perm-item');
            const allChecked = Array.from(items).every(cb => cb.checked);
            items.forEach(cb => cb.checked = !allChecked);
        }

        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        });

        <?php if ($message): ?>
        setTimeout(function() {
            const toast = document.getElementById('toast');
            if (toast) toast.remove();
        }, 5000);
        <?php endif; ?>
    </script>
</div>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

