<?php
/**
 * Category Form - Add/Edit Category
 * Standalone page matching Laravel layout style + Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();
$branch_id = get_current_branch_id();

if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$category_id = intval($_GET['id'] ?? 0);
$is_edit = $category_id > 0;

$category = null;
$name = '';
$description = '';
$parent_id = null;
$color = '#FBBF24';
$icon = 'tag';
$status = 'active';
$errors = [];

// Fetch parent categories for dropdown
$parent_categories = [];
if ($pdo) {
    $parent_sql = 'SELECT id, name FROM categories WHERE tenant_id = ? AND status = \'active\' AND deleted_at IS NULL';
    $parent_params = [$tenant_id];
    if ($is_edit) {
        $parent_sql .= ' AND id != ?';
        $parent_params[] = $category_id;
    }
    $parent_sql .= ' ORDER BY name ASC';
    $stmt = $pdo->prepare($parent_sql);
    $stmt->execute($parent_params);
    $parent_categories = $stmt->fetchAll();
}

if ($is_edit && $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL');
    $stmt->execute([$category_id, $tenant_id]);
    $category = $stmt->fetch();
    if (!$category) {
        header('Location: list_categories.php?error=' . urlencode('Category not found'));
        exit;
    }
    $name = $category['name'];
    $description = $category['description'] ?? '';
    $parent_id = $category['parent_id'];
    $color = $category['color'] ?? '#FBBF24';
    $icon = $category['icon'] ?? 'tag';
    $status = $category['status'] ?? 'active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $color = $_POST['color'] ?? '#FBBF24';
    $icon = $_POST['icon'] ?? 'tag';
    $status = isset($_POST['status']) ? 'active' : 'inactive';

    if (empty($name)) {
        $errors[] = 'Category name is required';
    }

    if (empty($errors)) {
        if ($is_edit) {
            $check = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id != ? AND tenant_id = ? AND deleted_at IS NULL');
            $check->execute([$name, $category_id, $tenant_id]);
        } else {
            $check = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND tenant_id = ? AND deleted_at IS NULL');
            $check->execute([$name, $tenant_id]);
        }
        if ($check->fetch()) {
            $errors[] = 'Category name already exists';
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare('
                    UPDATE categories
                    SET name = ?, description = ?, parent_id = ?, color = ?, icon = ?, status = ?, updated_at = NOW(), updated_by = ?
                    WHERE id = ? AND tenant_id = ?
                ');
                $stmt->execute([$name, $description, $parent_id, $color, $icon, $status, $user_id, $category_id, $tenant_id]);

                log_activity($user_id, 'category.updated', ['category_id' => $category_id, 'category_name' => $name], $tenant_id);
                header('Location: list_categories.php?success=' . urlencode('Category updated successfully'));
                exit;
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO categories (tenant_id, name, description, parent_id, color, icon, status, sort_order, created_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?)
                ');
                $stmt->execute([$tenant_id, $name, $description, $parent_id, $color, $icon, $status, $user_id]);

                $new_id = $pdo->lastInsertId();
                log_activity($user_id, 'category.created', ['category_id' => $new_id, 'category_name' => $name], $tenant_id);
                header('Location: list_categories.php?success=' . urlencode('Category created successfully'));
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error saving category: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save category';
        }
    }
}

$page_title = $is_edit ? 'Edit Category' : 'Add Category';
ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Products</p>
        <h1 class="text-lg font-bold text-white"><?php echo $is_edit ? 'Edit Category' : 'Add New Category'; ?></h1>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo $is_edit ? 'Update category details' : 'Create a new product category'; ?></p>
    </div>
    <div>
        <a href="list_categories.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Categories
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
        <div class="flex items-start gap-3 text-red-400">
            <i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i>
            <div class="text-white">
                <h3 class="font-semibold mb-2">Please fix the following errors:</h3>
                <ul class="list-disc list-inside text-sm space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Category Name <span class="text-red-400">*</span></label>
            <input type="text" name="name" required
                   value="<?php echo htmlspecialchars($name); ?>"
                   placeholder="Enter category name"
                   class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Parent Category</label>
            <select name="parent_id"
                    class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors">
                <option value="">None (Top Level)</option>
                <?php foreach ($parent_categories as $parent): ?>
                    <option value="<?php echo $parent['id']; ?>" <?php echo $parent_id == $parent['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($parent['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Description</label>
            <textarea name="description" rows="2"
                      placeholder="Enter category description (optional)"
                      class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none resize-none transition-colors"><?php echo htmlspecialchars($description); ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" value="<?php echo htmlspecialchars($color); ?>"
                           class="w-12 h-9 bg-slate-900/60 border border-slate-700 rounded-lg cursor-pointer">
                    <span class="text-slate-400 text-sm"><?php echo htmlspecialchars($color); ?></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">Icon</label>
                <select name="icon"
                        class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors">
                    <option value="tag" <?php echo $icon === 'tag' ? 'selected' : ''; ?>>Tag</option>
                    <option value="folder" <?php echo $icon === 'folder' ? 'selected' : ''; ?>>Folder</option>
                    <option value="star" <?php echo $icon === 'star' ? 'selected' : ''; ?>>Star</option>
                    <option value="box" <?php echo $icon === 'box' ? 'selected' : ''; ?>>Box</option>
                    <option value="store" <?php echo $icon === 'store' ? 'selected' : ''; ?>>Store</option>
                    <option value="shopping-cart" <?php echo $icon === 'shopping-cart' ? 'selected' : ''; ?>>Cart</option>
                    <option value="cog" <?php echo $icon === 'cog' ? 'selected' : ''; ?>>Settings</option>
                    <option value="heart" <?php echo $icon === 'heart' ? 'selected' : ''; ?>>Heart</option>
                </select>
            </div>
        </div>

        <?php if ($is_edit): ?>
            <div class="flex items-center gap-3 px-4 py-3 bg-slate-900/40 rounded-xl border border-slate-700">
                <i class="fas fa-power-off text-amber-400 text-sm"></i>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="sr-only peer" <?php echo $status === 'active' ? 'checked' : ''; ?>>
                    <div class="w-10 h-5 bg-slate-600 rounded-full peer peer-checked:bg-emerald-500 transition-colors"></div>
                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </label>
                <span class="text-sm text-slate-300">Category Active</span>
                <span class="text-xs text-slate-500 ml-auto">Inactive categories won't appear in POS</span>
            </div>
        <?php endif; ?>

        <div class="flex gap-2 pt-4 border-t border-slate-700/60">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-<?php echo $is_edit ? 'pen' : 'plus'; ?> text-xs"></i> <?php echo $is_edit ? 'Update Category' : 'Create Category'; ?>
            </button>
            <a href="list_categories.php" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) {
            if (!(e.ctrlKey && e.key === 's')) return;
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
        if (e.key === 'Escape') {
            window.location.href = 'list_categories.php';
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
