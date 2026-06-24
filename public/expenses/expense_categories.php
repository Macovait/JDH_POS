<?php
/**
 * Expense Categories Management page for Jakababa POS
 * 
 * Manages expense categories, budgets, and category settings.
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

// Check for expense categories management permission
if (!check_permission('expenses.manage') && !is_super_admin()) {
    enforce_permission('expenses.manage');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

// Get current user info
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);
$is_super_admin = is_super_admin();

// Handle POST actions (delete, toggle only; create/update moved to expense_category_form.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_msg = '';
    $redirect_type = 'success';

    switch ($action) {
        case 'delete':
            if ($is_super_admin || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $check = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = ?');
                        $check->execute([$id]);
                        if ($check->fetchColumn() > 0) {
                            $pdo->prepare('UPDATE expense_categories SET is_active = 0 WHERE id = ?')->execute([$id]);
                            $redirect_msg = 'Category deactivated (has associated expenses)';
                        } else {
                            $pdo->prepare('DELETE FROM expense_categories WHERE id = ?')->execute([$id]);
                            $redirect_msg = 'Category deleted successfully';
                        }
                        log_activity('expense_category.deleted', null, ['category_id' => $id], $user_id, get_current_tenant_id());
                    } catch (PDOException $e) {
                        error_log("Error deleting category: " . $e->getMessage());
                        $redirect_msg = 'Failed to delete category';
                        $redirect_type = 'error';
                    }
                }
            }
            break;

        case 'toggle_status':
            if ($is_super_admin || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);
                $current_status = intval($_POST['current_status'] ?? 1);
                $new_status = $current_status ? 0 : 1;
                try {
                    $pdo->prepare('UPDATE expense_categories SET is_active = ? WHERE id = ?')->execute([$new_status, $id]);
                    $redirect_msg = 'Category ' . ($new_status ? 'activated' : 'deactivated') . ' successfully';
                    log_activity('expense_category.status_toggled', null, ['category_id' => $id, 'new_status' => $new_status], $user_id, get_current_tenant_id());
                } catch (PDOException $e) {
                    error_log("Error toggling category status: " . $e->getMessage());
                    $redirect_msg = 'Failed to update category status';
                    $redirect_type = 'error';
                }
            }
            break;
    }

    if ($redirect_msg) {
        $param = $redirect_type === 'error' ? 'error' : 'success';
        header('Location: expense_categories.php?' . $param . '=' . urlencode($redirect_msg));
        exit;
    }
}

// Read flash messages from GET
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Get all categories with statistics
$categories = [];
try {
    $sql = "
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM expenses WHERE category_id = c.id) as expense_count,
            (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = c.id AND approved = 1) as total_spent,
            (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = c.id AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())) as month_spent,
            (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = c.id AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as last_30_days,
            (SELECT MAX(expense_date) FROM expenses WHERE category_id = c.id) as last_used
        FROM expense_categories c
        ORDER BY 
            CASE WHEN c.is_active = 1 THEN 0 ELSE 1 END,
            c.name
    ";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get summary statistics
$stats = [
    'total_categories' => count($categories),
    'active_categories' => count(array_filter($categories, fn($c) => $c['is_active'])),
    'inactive_categories' => count(array_filter($categories, fn($c) => !$c['is_active'])),
    'total_budget' => array_sum(array_column($categories, 'budget')),
    'total_spent' => array_sum(array_column($categories, 'total_spent')),
    'month_spent' => array_sum(array_column($categories, 'month_spent')),
    'categories_with_expenses' => count(array_filter($categories, fn($c) => $c['expense_count'] > 0))
];

// Predefined colors for category selection
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

// Predefined icons
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

$page_title = 'Expense Categories';
$current_year = date('Y');
$can_delete = ($is_super_admin || $user_role === 'Admin');
ob_start();
?>

<style>
        /* Clean card style */
        .card {
            background: #1F2937;
            border: 1px solid #4B5563;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        /* Stats card */
        .stats-card {
            background: #1F2937;
            border: 1px solid #4B5563;
            border-radius: 1rem;
            padding: 1.25rem;
            transition: all 0.2s;
        }

        .stats-card:hover {
            border-color: #FBBF24;
            transform: translateY(-2px);
        }

        /* Category card */
        .category-card {
            background: #1F2937;
            border: 1px solid #4B5563;
            border-radius: 1rem;
            padding: 1.25rem;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .category-card:hover {
            border-color: #FBBF24;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(0, 0, 0, 0.5);
        }

        .category-card.inactive {
            opacity: 0.7;
            background: #1F2937;
        }

        .category-color-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        /* Status badges */
        .badge-active {
            background: #065F46;
            color: #D1FAE5;
            border: 1px solid #10B981;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-inactive {
            background: #7F1D1D;
            color: #FEE2E2;
            border: 1px solid #EF4444;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Budget indicator */
        .budget-bar {
            height: 6px;
            background: #374151;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .budget-fill {
            height: 100%;
            background: linear-gradient(90deg, #FBBF24, #F59E0B);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .budget-danger {
            background: linear-gradient(90deg, #EF4444, #DC2626);
        }

        .budget-warning {
            background: linear-gradient(90deg, #F59E0B, #D97706);
        }

        /* Input fields */
        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 {
            background: #111827;
            border: 1px solid #4B5563;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: #F9FAFB;
            width: 100%;
            transition: all 0.2s;
        }

        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500:focus {
            outline: none;
            border-color: #FBBF24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.3);
        }

        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500::placeholder {
            color: #6B7280;
        }

        /* Color picker */
        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .color-option:hover {
            transform: scale(1.1);
        }

        .color-option.selected {
            border-color: #F3F4F6;
            box-shadow: 0 0 0 2px #FBBF24;
        }

        /* Buttons */
        .btn-primary {
            background: #FBBF24;
            color: #1E3A8A;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
            border: 1px solid #FBBF24;
        }

        .btn-primary:hover {
            background: #F59E0B;
            border-color: #F59E0B;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(251, 191, 36, 0.3);
        }

        .btn-secondary {
            background: #374151;
            color: #F3F4F6;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
            border: 1px solid #4B5563;
        }

        .btn-secondary:hover {
            background: #4B5563;
            border-color: #6B7280;
        }

        .btn-danger {
            background: #7F1D1D;
            color: #FEE2E2;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
            border: 1px solid #EF4444;
        }

        .btn-danger:hover {
            background: #991B1B;
            border-color: #DC2626;
        }

        /* Action buttons */
        .action-btn {
            color: #9CA3AF;
            transition: all 0.2s;
            padding: 0.25rem;
            border-radius: 0.25rem;
        }

        .action-btn:hover {
            color: #FBBF24;
            background: #374151;
        }

        .action-btn.delete:hover {
            color: #EF4444;
        }

        /* Messages */
        .message-success {
            background: #065F46;
            color: #D1FAE5;
            border: 1px solid #10B981;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .message-error {
            background: #7F1D1D;
            color: #FEE2E2;
            border: 1px solid #EF4444;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        /* Simple animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        /* Modal */
        .modal {
            transition: opacity 0.3s ease;
        }

        /* Icon preview */
        .icon-preview {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

</style>

<div class="fade-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#F3F4F6]">Expense Categories</h1>
                <p class="text-[#9CA3AF] text-sm mt-1">Manage expense categories and budgets</p>
            </div>

            <?php if ($is_super_admin || $user_role === 'Admin'): ?>
                <a href="expense_category_form.php" class="btn-primary flex items-center gap-2">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Category</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="mb-4 message-success">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-4 message-error">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="stats-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[#9CA3AF] text-sm">Total Categories</span>
                    <i class="fas fa-tags text-[#FBBF24]"></i>
                </div>
                <p class="text-2xl font-bold text-[#F3F4F6]">
                    <?php echo $stats['total_categories']; ?>
                </p>
                <p class="text-xs text-[#9CA3AF] mt-1">
                    <?php echo $stats['active_categories']; ?> active
                </p>
            </div>

            <div class="stats-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[#9CA3AF] text-sm">Active Categories</span>
                    <i class="fas fa-check-circle text-[#10B981]"></i>
                </div>
                <p class="text-2xl font-bold text-[#F3F4F6]">
                    <?php echo $stats['active_categories']; ?>
                </p>
                <p class="text-xs text-[#9CA3AF] mt-1">
                    <?php echo $stats['inactive_categories']; ?> inactive
                </p>
            </div>

            <div class="stats-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[#9CA3AF] text-sm">Total Budget</span>
                    <i class="fas fa-chart-pie text-[#FBBF24]"></i>
                </div>
                <p class="text-2xl font-bold text-[#F3F4F6]">KSh
                    <?php echo number_format($stats['total_budget'], 0); ?>
                </p>
                <p class="text-xs text-[#9CA3AF] mt-1">Across all categories</p>
            </div>

            <div class="stats-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[#9CA3AF] text-sm">Monthly Spend</span>
                    <i class="fas fa-chart-line text-[#10B981]"></i>
                </div>
                <p class="text-2xl font-bold text-[#F3F4F6]">KSh
                    <?php echo number_format($stats['month_spent'], 0); ?>
                </p>
                <p class="text-xs text-[#9CA3AF] mt-1">Current month</p>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if (empty($categories)): ?>
                <div class="col-span-full text-center py-12">
                    <div class="w-20 h-20 bg-[#1F2937] rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-tags text-3xl text-[#FBBF24]"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-[#F3F4F6] mb-2">No Categories Found</h3>
                    <p class="text-[#9CA3AF] mb-6">Create your first expense category to get started</p>
                    <?php if ($is_super_admin || $user_role === 'Admin'): ?>
                        <a href="expense_category_form.php" class="btn-primary inline-block">
                            <i class="fas fa-plus mr-2"></i>Create Category
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <?php
                    $is_active = $category['is_active'] ?? 1;
                    $budget = floatval($category['budget'] ?? 0);
                    $month_spent = floatval($category['month_spent'] ?? 0);
                    $budget_percentage = $budget > 0 ? min(100, round(($month_spent / $budget) * 100)) : 0;
                    $budget_class = '';
                    if ($budget_percentage >= 90) {
                        $budget_class = 'budget-danger';
                    } elseif ($budget_percentage >= 75) {
                        $budget_class = 'budget-warning';
                    }
                    ?>
                    <div class="category-card <?php echo !$is_active ? 'inactive' : ''; ?>">
                        <div class="category-color-bar"
                            style="background-color: <?php echo $category['color'] ?? '#FBBF24'; ?>;"></div>

                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="icon-preview"
                                    style="background-color: <?php echo ($category['color'] ?? '#FBBF24') . '20'; ?>; color: <?php echo $category['color'] ?? '#FBBF24'; ?>;">
                                    <i class="<?php echo $category['icon'] ?? 'fas fa-tag'; ?>"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-[#F3F4F6]">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </h3>
                                    <?php if (!empty($category['description'])): ?>
                                        <p class="text-xs text-[#9CA3AF]">
                                            <?php echo htmlspecialchars($category['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($is_active): ?>
                                    <span class="badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="bg-[#111827] rounded-lg p-2">
                                <p class="text-xs text-[#9CA3AF]">Total Spent</p>
                                <p class="font-semibold text-[#FBBF24]">KSh
                                    <?php echo number_format($category['total_spent'] ?? 0, 0); ?>
                                </p>
                            </div>
                            <div class="bg-[#111827] rounded-lg p-2">
                                <p class="text-xs text-[#9CA3AF]">This Month</p>
                                <p class="font-semibold text-[#FBBF24]">KSh
                                    <?php echo number_format($month_spent, 0); ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($budget > 0): ?>
                            <div class="mb-3">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-[#9CA3AF]">Monthly Budget</span>
                                    <span class="text-[#F3F4F6]">KSh
                                        <?php echo number_format($budget, 0); ?>
                                    </span>
                                </div>
                                <div class="budget-bar">
                                    <div class="budget-fill <?php echo $budget_class; ?>"
                                        style="width: <?php echo $budget_percentage; ?>%;"></div>
                                </div>
                                <div class="flex justify-between text-xs mt-1">
                                    <span class="text-[#9CA3AF]">Spent: KSh
                                        <?php echo number_format($month_spent, 0); ?>
                                    </span>
                                    <span class="<?php echo $budget_percentage >= 90 ? 'text-[#EF4444]' : 'text-[#9CA3AF]'; ?>">
                                        <?php echo $budget_percentage; ?>%
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center justify-between text-xs text-[#9CA3AF] mb-3">
                            <span><i class="far fa-clock mr-1"></i>Last used:
                                <?php echo $category['last_used'] ? date('d M Y', strtotime($category['last_used'])) : 'Never'; ?>
                            </span>
                            <span><i class="fas fa-receipt mr-1"></i>
                                <?php echo $category['expense_count']; ?> expenses
                            </span>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#374151]">
                            <!-- View Expenses -->
                            <a href="expenses.php?category=<?php echo $category['id']; ?>" class="action-btn"
                                title="View Expenses">
                                <i class="fas fa-list"></i>
                            </a>

                            <!-- Edit -->
                            <a href="expense_category_form.php?id=<?php echo $category['id']; ?>"
                                class="action-btn" title="Edit Category">
                                <i class="fas fa-edit"></i>
                            </a>

                            <!-- Toggle Status (Admin only) -->
                            <?php if ($can_delete): ?>
                                <form method="POST" class="inline">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $category['is_active']; ?>">
                                    <button type="submit" class="action-btn"
                                        title="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?>"
                                        onclick="return confirm('<?php echo $is_active ? 'Deactivate' : 'Activate'; ?> this category?')">
                                        <i class="fas <?php echo $is_active ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Delete (Admin only) -->
                            <?php if ($can_delete): ?>
                                <button
                                    onclick="openDeleteModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>', <?php echo $category['expense_count']; ?>)"
                                    class="action-btn delete" title="Delete Category">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/80 flex items-center justify-center hidden z-50">
        <div class="bg-[#1F2937] rounded-xl border border-[#4B5563] p-6 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 text-[#EF4444] mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
                <h3 class="text-xl font-semibold text-[#F3F4F6]">Delete Category</h3>
            </div>

            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteCategoryId">

                <p class="text-[#9CA3AF] mb-4">
                    Are you sure you want to delete <span id="deleteCategoryName"
                        class="text-[#F3F4F6] font-semibold"></span>?
                </p>

                <div id="deleteCategoryWarning" class="bg-[#7F1D1D] text-[#FEE2E2] p-3 rounded-lg mb-4 text-sm hidden">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    This category has <span id="deleteExpenseCount">0</span> expense(s). It will be deactivated instead
                    of deleted.
                </div>

                <p class="text-[#9CA3AF] text-sm mb-6">
                    This action cannot be undone.
                </p>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 btn-danger" id="deleteButton">
                        Delete Category
                    </button>
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Status -->
    <div id="connection-status"
        class="fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#4B5563]">
        <i class="fas fa-wifi"></i>
        <span>Online</span>
    </div>

    <script>
        // Delete modal functions
        function openDeleteModal(id, name, expenseCount) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteCategoryName').textContent = name;
            document.getElementById('deleteExpenseCount').textContent = expenseCount;

            const warningDiv = document.getElementById('deleteCategoryWarning');
            const deleteButton = document.getElementById('deleteButton');

            if (expenseCount > 0) {
                warningDiv.classList.remove('hidden');
                deleteButton.textContent = 'Deactivate Category';
            } else {
                warningDiv.classList.add('hidden');
                deleteButton.textContent = 'Delete Category';
            }

            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.fixed').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });

        // Connection status
        function updateOnlineStatus() {
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                if (navigator.onLine) {
                    statusEl.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                    statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#4B5563]';
                } else {
                    statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
                    statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#EF4444] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#4B5563]';
                }
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Auto-hide success message
        setTimeout(() => {
            const successMsg = document.querySelector('.message-success');
            if (successMsg) {
                successMsg.style.transition = 'opacity 0.5s';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }
        }, 5000);
    </script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
?>