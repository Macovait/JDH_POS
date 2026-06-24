<?php
/**
 * Expense Category add/edit form for Jakababa POS
 * Standalone form extracted from expense_categories.php
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('expenses.manage') && !is_super_admin()) {
    enforce_permission('expenses.manage');
}

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

$pdo = get_db_connection();
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_role = $_SESSION['user']['role'] ?? '';
$is_super_admin = is_super_admin();

// Predefined colors and icons
$color_options = [
    '#FBBF24' => 'Gold',
    '#F59E0B' => 'Amber',
    '#EF4444' => 'Red',
    '#10B981' => 'Green',
    '#3B82F6' => 'Blue',
    '#8B5CF6' => 'Purple',
    '#EC4899' => 'Pink',
    '#14B8A6' => 'Teal',
    '#F97316' => 'Orange',
    '#6B7280' => 'Gray'
];

$icon_options = [
    'fas fa-tag' => 'Tag',
    'fas fa-shopping-cart' => 'Shopping Cart',
    'fas fa-utensils' => 'Food',
    'fas fa-car' => 'Transport',
    'fas fa-building' => 'Office',
    'fas fa-bolt' => 'Utilities',
    'fas fa-home' => 'Rent',
    'fas fa-phone' => 'Phone',
    'fas fa-wifi' => 'Internet',
    'fas fa-laptop' => 'Electronics',
    'fas fa-gas-pump' => 'Fuel',
    'fas fa-plane' => 'Travel',
    'fas fa-gift' => 'Gifts',
    'fas fa-heart' => 'Health',
    'fas fa-graduation-cap' => 'Education',
    'fas fa-chart-line' => 'Marketing',
    'fas fa-tools' => 'Maintenance',
    'fas fa-file-invoice' => 'Insurance',
    'fas fa-coffee' => 'Coffee/Meals',
    'fas fa-box' => 'Supplies'
];

// Fetch existing category for edit
$category = null;
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($category_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM expense_categories WHERE id = ?');
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    if (!$category) {
        header('Location: expense_categories.php?error=' . urlencode('Category not found'));
        exit;
    }
}

$is_edit = $category_id > 0;

// Handle POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = trim($_POST['color'] ?? '#FBBF24');
    $icon = trim($_POST['icon'] ?? 'fas fa-tag');
    $budget = floatval($_POST['budget'] ?? 0);
    $budget_period = $_POST['budget_period'] ?? 'monthly';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $errors[] = 'Category name is required';
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE expense_categories SET name = ?, description = ?, color = ?, icon = ?, budget = ?, budget_period = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$name, $description, $color, $icon, $budget, $budget_period, $is_active, $id]);
                log_activity('expense_category.updated', null, ['category_id' => $id, 'category_name' => $name], $user_id, get_current_tenant_id());
                header('Location: expense_categories.php?success=' . urlencode('Category updated successfully'));
                exit;
            } else {
                $check = $pdo->prepare('SELECT id FROM expense_categories WHERE name = ?');
                $check->execute([$name]);
                if ($check->fetch()) {
                    $errors[] = 'Category with this name already exists';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO expense_categories (name, description, color, icon, budget, budget_period, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$name, $description, $color, $icon, $budget, $budget_period, $is_active, $user_id]);
                    log_activity('expense_category.created', null, ['category_name' => $name], $user_id, get_current_tenant_id());
                    header('Location: expense_categories.php?success=' . urlencode('Category created successfully'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving category: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save category';
        }
    }
}

$page_title = ($is_edit ? 'Edit' : 'Create') . ' Expense Category | JDH POS';
ob_start();
?>

<div class="max-w-3xl mx-auto ">
    <div class="flex items-center gap-3 mb-6">
        <a href="expense_categories.php" class="text-gray-400 hover:text-white transition">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider"><?php echo $is_edit ? 'Edit' : 'New'; ?></div>
            <h1 class="text-2xl font-bold text-white"><?php echo $is_edit ? 'Edit' : 'Create'; ?> Expense Category</h1>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-start gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle flex-shrink-0 mt-0.5"></i>
                <div class="text-white"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card p-6">
        <form method="POST" class="space-y-5" id="categoryForm">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="id" value="<?php echo $category_id; ?>">

            <div>
                <label class="block text-sm text-gray-400 mb-1">Category Name *</label>
                <input type="text" name="name" required
                    value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
                    class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                    placeholder="e.g., Office Supplies, Utilities, Rent">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Description</label>
                <textarea name="description" rows="2"
                    class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                    placeholder="Brief description of this category"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
            </div>

            <!-- Color Selection -->
            <div>
                <label class="block text-sm text-gray-400 mb-2">Category Color</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <?php foreach ($color_options as $color_code => $color_name): ?>
                        <div class="color-option w-8 h-8 rounded-full cursor-pointer border-2 <?php echo (isset($category['color']) && $category['color'] === $color_code) || (!isset($category['color']) && $color_code === '#FBBF24') ? 'border-white' : 'border-transparent'; ?>"
                            style="background-color: <?php echo $color_code; ?>"
                            onclick="selectColor('<?php echo $color_code; ?>', this)"
                            title="<?php echo $color_name; ?>"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="color" id="selectedColor" value="<?php echo htmlspecialchars($category['color'] ?? '#FBBF24'); ?>">
            </div>

            <!-- Icon Selection -->
            <div>
                <label class="block text-sm text-gray-400 mb-2">Category Icon</label>
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 max-h-48 overflow-y-auto p-2 bg-[#111827] rounded-lg">
                    <?php foreach ($icon_options as $icon_class => $icon_name): ?>
                        <div class="icon-option flex flex-col items-center p-2 rounded-lg cursor-pointer hover:bg-[#374151] <?php echo (isset($category['icon']) && $category['icon'] === $icon_class) || (!isset($category['icon']) && $icon_class === 'fas fa-tag') ? 'bg-[#374151] selected' : ''; ?>"
                            onclick="selectIcon('<?php echo $icon_class; ?>', this)"
                            title="<?php echo $icon_name; ?>">
                            <i class="<?php echo $icon_class; ?> text-[#FBBF24]"></i>
                            <span class="text-xs text-gray-400 mt-1"><?php echo $icon_name; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="icon" id="selectedIcon" value="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-tag'); ?>">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Monthly Budget (KSh)</label>
                    <input type="number" name="budget" step="0.01" min="0"
                        value="<?php echo isset($category['budget']) ? $category['budget'] : 0; ?>"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none"
                        placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Budget Period</label>
                    <select name="budget_period"
                        class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-white focus:border-[#FBBF24] outline-none">
                        <option value="monthly" <?php echo (isset($category['budget_period']) && $category['budget_period'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo (isset($category['budget_period']) && $category['budget_period'] === 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="yearly" <?php echo (isset($category['budget_period']) && $category['budget_period'] === 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo (!isset($category['is_active']) || $category['is_active']) ? 'checked' : ''; ?> class="rounded accent-[#FBBF24]">
                <label for="is_active" class="text-sm text-gray-400">Active (visible in expense forms)</label>
            </div>

            <div class="flex gap-3 pt-4 border-t border-[#374151]">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#FBBF24] text-black rounded-lg font-medium hover:bg-[#F59E0B] transition">
                    <i class="fas fa-save mr-2"></i><?php echo $is_edit ? 'Update' : 'Save'; ?> Category
                </button>
                <a href="expense_categories.php" class="flex-1 px-4 py-2 bg-[#374151] text-white rounded-lg font-medium hover:bg-[#4B5563] transition text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    function selectColor(color, el) {
        document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('border-white'));
        document.querySelectorAll('.color-option').forEach(opt => opt.classList.add('border-transparent'));
        el.classList.remove('border-transparent');
        el.classList.add('border-white');
        document.getElementById('selectedColor').value = color;
    }

    function selectIcon(iconClass, el) {
        document.querySelectorAll('.icon-option').forEach(opt => {
            opt.classList.remove('selected', 'bg-[#374151]');
        });
        el.classList.add('selected', 'bg-[#374151]');
        document.getElementById('selectedIcon').value = iconClass;
    }

    document.getElementById('categoryForm').addEventListener('submit', function (e) {
        const name = document.querySelector('input[name="name"]').value.trim();
        if (!name) {
            e.preventDefault();
            alert('Category name is required');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'expense_categories.php';
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
