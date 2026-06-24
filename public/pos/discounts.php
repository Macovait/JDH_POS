<?php
/**
 * Discounts management page for Jakababa POS
 * 
 * Manages discount rules and promotions.
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check for discounts management permission
if (!check_permission('sales.discounts') && !is_super_admin()) {
    enforce_permission('sales.discounts');
}

// Get current user info for navbar
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);
$tenant_id = (int) get_current_tenant_id();

// Handle CRUD operations
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Database connection with graceful error handling
$db_error = null;
$pdo = null;
try {
    $pdo = get_db_connection();
} catch (Throwable $e) {
    $pdo = null;
    $db_error = 'Unable to connect to the database. Please ensure MySQL is running and try again.';
    error_log('Database connection failed: ' . $e->getMessage());
}

// Get current currency symbol
$currency_symbol = 'KSh';
if ($pdo) {
    try {
        $currency = function_exists('current_currency') ? current_currency() : ['symbol' => get_settings('currency', 'KES')];
        $currency_symbol = $currency['symbol'] ?? 'KSh';
    } catch (Throwable $e) {
        error_log('Currency lookup failed: ' . $e->getMessage());
    }
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // Invalidate caches on any mutation
    $clearKeys = ['_discounts_products_', '_discounts_categories_', '_discounts_list_'];
    foreach ($clearKeys as $prefix) {
        foreach (array_keys($_SESSION) as $sessKey) {
            if (strpos($sessKey, $prefix) === 0) {
                unset($_SESSION[$sessKey]);
            }
        }
    }

    switch ($action) {
        case 'create':
        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'fixed';
            $value = floatval($_POST['value'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            $description = trim($_POST['description'] ?? '');
            $min_purchase = floatval($_POST['min_purchase'] ?? 0);
            $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
            $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
            $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
            $applicable_products = $_POST['applicable_products'] ?? 'all'; // all, specific, category
            $product_ids = isset($_POST['product_ids']) ? json_encode($_POST['product_ids']) : null;
            $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
            $priority = intval($_POST['priority'] ?? 0);

            // Validation
            $errors = [];

            if (empty($name)) {
                $errors[] = 'Discount name is required';
            }

            if ($value <= 0) {
                $errors[] = 'Value must be greater than 0';
            }

            if ($type === 'percent' && $value > 100) {
                $errors[] = 'Percentage cannot exceed 100%';
            }

            if ($valid_from && $valid_until && $valid_from > $valid_until) {
                $errors[] = 'Valid from date must be before valid until date';
            }

            if (empty($errors)) {
                try {
                    if ($id > 0) {
                        // Update existing discount
                        $stmt = $pdo->prepare('
                            UPDATE discounts 
                            SET name = ?, type = ?, value = ?, active = ?, description = ?,
                                min_purchase = ?, max_discount = ?, valid_from = ?, valid_until = ?,
                                applicable_products = ?, product_ids = ?, category_id = ?,
                                usage_limit = ?, priority = ?
                            WHERE id = ? AND tenant_id = ?
                        ');
                        $stmt->execute([
                            $name,
                            $type,
                            $value,
                            $active,
                            $description,
                            $min_purchase,
                            $max_discount,
                            $valid_from,
                            $valid_until,
                            $applicable_products,
                            $product_ids,
                            $category_id,
                            $usage_limit,
                            $priority,
                            $id,
                            $tenant_id
                        ]);

                        log_activity($user_id, 'discount.updated', [
                            'discount_id' => $id, 'discount_name' => $name
                        ], $tenant_id);

                        $success_message = 'Discount updated successfully';
                    } else {
                        // Check if discount already exists with same name
                        $check = $pdo->prepare('SELECT id FROM discounts WHERE name = ? AND tenant_id = ?');
                        $check->execute([$name, $tenant_id]);
                        if ($check->fetch()) {
                            $errors[] = 'Discount with this name already exists';
                        } else {
                            // Create new discount
                            $stmt = $pdo->prepare('
                                INSERT INTO discounts (
                                    name, type, value, active, description, min_purchase,
                                    max_discount, valid_from, valid_until, applicable_products,
                                    product_ids, category_id, usage_limit, priority, tenant_id, branch_id, created_at
                                ) VALUES (
                                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                                )
                            ');
                            $stmt->execute([
                                $name,
                                $type,
                                $value,
                                $active,
                                $description,
                                $min_purchase,
                                $max_discount,
                                $valid_from,
                                $valid_until,
                                $applicable_products,
                                $product_ids,
                                $category_id,
                                $usage_limit,
                                $priority,
                                $tenant_id,
                                $branch_id
                            ]);

                            $new_id = $pdo->lastInsertId();

                            log_activity($user_id, 'discount.created', [
                                'discount_id' => $new_id, 'discount_name' => $name
                            ], $tenant_id);

                            $success_message = 'Discount added successfully';
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error saving discount: " . $e->getMessage());
                    $errors[] = 'Database error: Failed to save discount';
                }
            }

            if (!empty($errors)) {
                $error_message = implode('<br>', $errors);
            }
            break;

        case 'delete':
            // Delete discount (Admin/Super Admin only)
            if (is_super_admin() || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);

                if ($id > 0) {
                    try {
                        $stmt = $pdo->prepare('DELETE FROM discounts WHERE id = ? AND tenant_id = ?');
                        $stmt->execute([$id, $tenant_id]);

                        log_activity($user_id, 'discount.deleted', ['discount_id' => $id], $tenant_id);

                        $success_message = 'Discount deleted successfully';
                    } catch (PDOException $e) {
                        error_log("Error deleting discount: " . $e->getMessage());
                        $error_message = 'Failed to delete discount';
                    }
                }
            }
            break;

        case 'toggle_status':
            // Toggle discount active status
            $id = intval($_POST['id'] ?? 0);
            $status = intval($_POST['status'] ?? 0);

            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('UPDATE discounts SET active = ? WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$status, $id, $tenant_id]);

                    $success_message = 'Discount status updated successfully';

                    log_activity($user_id, 'discount.status_changed', [
                        'discount_id' => $id, 'new_status' => $status
                    ], $tenant_id);

                } catch (PDOException $e) {
                    error_log("Error toggling discount status: " . $e->getMessage());
                    $error_message = 'Failed to update discount status';
                }
            }
            break;

        case 'duplicate':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('SELECT * FROM discounts WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$id, $tenant_id]);
                    $original = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($original) {
                        $ins = $pdo->prepare('
                            INSERT INTO discounts (
                                name, type, value, active, description, min_purchase,
                                max_discount, valid_from, valid_until, applicable_products,
                                product_ids, category_id, usage_limit, priority, tenant_id, branch_id, code, created_at
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                            )
                        ');
                        $ins->execute([
                            $original['name'] . ' (Copy)',
                            $original['type'],
                            $original['value'],
                            0,
                            $original['description'],
                            $original['min_purchase'],
                            $original['max_discount'],
                            $original['valid_from'],
                            $original['valid_until'],
                            $original['applicable_products'],
                            $original['product_ids'],
                            $original['category_id'],
                            $original['usage_limit'],
                            $original['priority'],
                            $tenant_id,
                            $branch_id,
                            $original['code'] ? ($original['code'] . '_COPY') : null
                        ]);
                        $success_message = 'Discount duplicated successfully';
                    }
                } catch (PDOException $e) {
                    error_log("Error duplicating discount: " . $e->getMessage());
                    $error_message = 'Failed to duplicate discount';
                }
            }
            break;

        case 'bulk_action':
            if (is_super_admin() || $user_role === 'Admin') {
                $bulkIds = isset($_POST['bulk_ids']) ? array_map('intval', (array)$_POST['bulk_ids']) : [];
                $bulkAction = $_POST['bulk_type'] ?? '';
                if (!empty($bulkIds)) {
                    $placeholders = implode(',', array_fill(0, count($bulkIds), '?'));
                    try {
                        if ($bulkAction === 'activate') {
                            $stmt = $pdo->prepare("UPDATE discounts SET active = 1 WHERE id IN ($placeholders) AND tenant_id = ?");
                            $stmt->execute(array_merge($bulkIds, [$tenant_id]));
                            $success_message = count($bulkIds) . ' discount(s) activated';
                        } elseif ($bulkAction === 'deactivate') {
                            $stmt = $pdo->prepare("UPDATE discounts SET active = 0 WHERE id IN ($placeholders) AND tenant_id = ?");
                            $stmt->execute(array_merge($bulkIds, [$tenant_id]));
                            $success_message = count($bulkIds) . ' discount(s) deactivated';
                        } elseif ($bulkAction === 'delete') {
                            $stmt = $pdo->prepare("DELETE FROM discounts WHERE id IN ($placeholders) AND tenant_id = ?");
                            $stmt->execute(array_merge($bulkIds, [$tenant_id]));
                            $success_message = count($bulkIds) . ' discount(s) deleted';
                        }
                    } catch (PDOException $e) {
                        error_log("Error bulk action: " . $e->getMessage());
                        $error_message = 'Failed to perform bulk action';
                    }
                }
            }
            break;
    }
}

// Get products and categories for advanced filtering (cached for 2 minutes)
$products = [];
$categories = [];
$prodCacheKey = '_discounts_products_' . $tenant_id . '_' . $current_branch_id;
$catCacheKey = '_discounts_categories_' . $tenant_id . '_' . $current_branch_id;

if (isset($_SESSION[$prodCacheKey]) && (time() - ($_SESSION[$prodCacheKey . '_time'] ?? 0) < 120)) {
    $products = $_SESSION[$prodCacheKey];
} elseif ($pdo) {
    $stmt = $pdo->prepare('SELECT id, name FROM products WHERE tenant_id = ? AND active = 1 AND (branch_id IS NULL OR branch_id = ? OR branch_id = 0) ORDER BY name');
    $stmt->execute([$tenant_id, $current_branch_id]);
    $products = $stmt->fetchAll();
    $_SESSION[$prodCacheKey] = $products;
    $_SESSION[$prodCacheKey . '_time'] = time();
}

if (isset($_SESSION[$catCacheKey]) && (time() - ($_SESSION[$catCacheKey . '_time'] ?? 0) < 120)) {
    $categories = $_SESSION[$catCacheKey];
} elseif ($pdo) {
    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE tenant_id = ? AND (branch_id IS NULL OR branch_id = ? OR branch_id = 0) ORDER BY name');
    $stmt->execute([$tenant_id, $current_branch_id]);
    $categories = $stmt->fetchAll();
    $_SESSION[$catCacheKey] = $categories;
    $_SESSION[$catCacheKey . '_time'] = time();
}

// Get search/filter parameters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all'; // all, active, inactive, expired
$type_filter = $_GET['type'] ?? 'all'; // all, fixed, percent

// Build query
$discounts = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'expired' => 0,
    'fixed' => 0,
    'percent' => 0
];

if ($pdo) {
    $listCacheKey = '_discounts_list_' . $tenant_id . '_' . md5(serialize([$search, $filter, $type_filter]));

    if (isset($_SESSION[$listCacheKey]) && (time() - ($_SESSION[$listCacheKey . '_time'] ?? 0) < 30)) {
        $discounts = $_SESSION[$listCacheKey]['discounts'];
        $stats = $_SESSION[$listCacheKey]['stats'];
    } else {
        $sql = "
            SELECT
                d.*,
                CASE
                    WHEN d.valid_until IS NULL THEN 'no-expiry'
                    WHEN d.valid_until < CURDATE() THEN 'expired'
                    WHEN d.valid_until = CURDATE() THEN 'expires-today'
                    WHEN d.valid_until <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expires-soon'
                    ELSE 'valid'
                END as expiry_status
            FROM discounts d
            WHERE 1=1
              AND d.tenant_id = ?
        ";

        $params = [$tenant_id];

        if (!empty($search)) {
            $sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
        }

        if ($filter === 'active') {
            $sql .= " AND d.active = 1 AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())";
        } elseif ($filter === 'inactive') {
            $sql .= " AND d.active = 0";
        } elseif ($filter === 'expired') {
            $sql .= " AND d.valid_until < CURDATE()";
        }

        if ($type_filter !== 'all') {
            $sql .= " AND d.type = ?";
            $params[] = $type_filter;
        }

        $sql .= " ORDER BY d.priority DESC, d.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $discounts = $stmt->fetchAll();

        // Single-pass statistics (O(n) instead of O(6n))
        $stats = [
            'total' => count($discounts),
            'active' => 0,
            'inactive' => 0,
            'expired' => 0,
            'fixed' => 0,
            'percent' => 0
        ];
        $today = date('Y-m-d');
        foreach ($discounts as $d) {
            if ($d['active'] == 1 && (is_null($d['valid_until']) || $d['valid_until'] >= $today)) {
                $stats['active']++;
            }
            if ($d['active'] == 0) {
                $stats['inactive']++;
            }
            if (!is_null($d['valid_until']) && $d['valid_until'] < $today) {
                $stats['expired']++;
            }
            if ($d['type'] === 'fixed') {
                $stats['fixed']++;
            } elseif ($d['type'] === 'percent') {
                $stats['percent']++;
            }
        }

        $_SESSION[$listCacheKey] = ['discounts' => $discounts, 'stats' => $stats];
        $_SESSION[$listCacheKey . '_time'] = time();
    }
} // end if ($pdo)

$page_title = 'Discount Management';
$can_delete = is_super_admin() || $user_role === 'Admin';
?>
<?php
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-tag text-amber-400"></i> Discount Management
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo $stats['total']; ?> discount<?php echo $stats['total'] !== 1 ? 's' : ''; ?> found
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button type="button" onclick="exportCSV()"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors">
            <i class="fas fa-file-csv text-xs"></i> Export
        </button>
        <a href="discount_form.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Discount
        </a>
    </div>
</div>

<?php if ($db_error): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-database"></i>
    <?php echo htmlspecialchars($db_error); ?>
    <button onclick="window.location.reload()" class="ml-auto underline hover:no-underline">Retry</button>
</div>
<?php endif; ?>

<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-5">
    <?php
    $cards = [
        ['label' => 'Total',    'value' => $stats['total'],    'icon' => 'fa-tag',          'color' => 'text-amber-400',   'bg' => 'bg-amber-500/10'],
        ['label' => 'Active',   'value' => $stats['active'],   'icon' => 'fa-check-circle', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Inactive', 'value' => $stats['inactive'], 'icon' => 'fa-pause',        'color' => 'text-slate-400',   'bg' => 'bg-slate-500/10'],
        ['label' => 'Expired',  'value' => $stats['expired'],  'icon' => 'fa-clock',        'color' => 'text-red-400',     'bg' => 'bg-red-500/10'],
        ['label' => 'Fixed',    'value' => $stats['fixed'],    'icon' => 'fa-dollar-sign',  'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10'],
        ['label' => 'Percent',  'value' => $stats['percent'],  'icon' => 'fa-percent',      'color' => 'text-purple-400',  'bg' => 'bg-purple-500/10'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo (int)$card['value']; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status pills + filters -->
<?php $active_tab = $filter ?: 'all'; ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">

    <!-- Status pills -->
    <div class="flex flex-wrap gap-1.5">
        <?php
        $pills = [
            ''         => ['label' => 'All',      'count' => $stats['total'],    'on' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',     'off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'active'   => ['label' => 'Active',   'count' => $stats['active'],   'on' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30','off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'inactive' => ['label' => 'Inactive', 'count' => $stats['inactive'], 'on' => 'bg-slate-500/20 text-slate-300 border-slate-500/30',     'off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'expired'  => ['label' => 'Expired',  'count' => $stats['expired'],  'on' => 'bg-red-500/20 text-red-400 border-red-500/30',           'off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
        ];
        foreach ($pills as $val => $pill):
            $active = $active_tab === ($val === '' ? 'all' : $val);
            $href_params = array_merge(array_diff_key($_GET, array_flip(['filter','page'])), $val !== '' ? ['filter' => $val] : []);
        ?>
        <a href="?<?php echo http_build_query($href_params); ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $active ? $pill['on'] : $pill['off']; ?>">
            <?php echo $pill['label']; ?>
            <span class="text-xs opacity-70">(<?php echo (int)$pill['count']; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter row -->
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <?php if ($active_tab !== 'all'): ?>
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($active_tab); ?>">
        <?php endif; ?>

        <!-- Search -->
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search name or description…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <!-- Type -->
        <select name="type" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="all"     <?php echo $type_filter === 'all'     ? 'selected' : ''; ?>>All Types</option>
            <option value="fixed"   <?php echo $type_filter === 'fixed'   ? 'selected' : ''; ?>>Fixed</option>
            <option value="percent" <?php echo $type_filter === 'percent' ? 'selected' : ''; ?>>Percent</option>
        </select>

        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <a href="discounts.php" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
    </form>
</div>

<!-- Bulk action bar -->
<div id="bulkBar" class="hidden mb-3 bg-slate-800/80 border border-slate-700/60 rounded-xl px-3 py-2 flex items-center justify-between">
    <div class="flex items-center gap-2 text-sm text-slate-300">
        <span id="bulkCount">0</span> selected
    </div>
    <div class="flex items-center gap-2">
        <button type="button" onclick="bulkAction('activate')" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-xs font-medium hover:bg-emerald-500/25 transition-colors">
            <i class="fas fa-play text-[10px] mr-1"></i>Activate
        </button>
        <button type="button" onclick="bulkAction('deactivate')" class="px-2.5 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-pause text-[10px] mr-1"></i>Deactivate
        </button>
        <button type="button" onclick="bulkAction('delete')" class="px-2.5 py-1.5 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/25 transition-colors">
            <i class="fas fa-trash text-[10px] mr-1"></i>Delete
        </button>
        <button type="button" onclick="clearSelection()" class="px-2.5 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times text-[10px] mr-1"></i>Clear
        </button>
    </div>
</div>

<!-- Discounts table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[800px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-10">
                        <input type="checkbox" id="selectAll" class="rounded border-slate-600 bg-slate-700 text-amber-500 focus:ring-amber-500/50" title="Select all">
                    </th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Discount</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Value</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Min Purchase</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Valid Period</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Code</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Priority</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Used</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($discounts)): ?>
                <tr>
                    <td colspan="11" class="px-4 py-14 text-center">
                        <i class="fas fa-tag text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No discounts found</p>
                        <?php if (!empty($search) || $active_tab !== 'all' || $type_filter !== 'all'): ?>
                        <a href="discounts.php" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-times text-xs"></i> Clear filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($discounts as $discount):
                    // Status
                    if ($discount['active'] && (is_null($discount['valid_until']) || $discount['valid_until'] >= date('Y-m-d'))) {
                        $st_class = 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
                        $st_label = 'Active';
                        $st_icon  = 'fa-check-circle';
                    } elseif (!is_null($discount['valid_until']) && $discount['valid_until'] < date('Y-m-d')) {
                        $st_class = 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30';
                        $st_label = 'Expired';
                        $st_icon  = 'fa-clock';
                    } else {
                        $st_class = 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
                        $st_label = 'Inactive';
                        $st_icon  = 'fa-circle-xmark';
                    }
                    // Type
                    $type_class = $discount['type'] === 'fixed'
                        ? 'bg-emerald-500/10 text-emerald-400'
                        : 'bg-amber-500/10 text-amber-400';
                    // Priority
                    if ($discount['priority'] >= 8) {
                        $pri_class = 'bg-red-500/10 text-red-400'; $pri_text = 'High';
                    } elseif ($discount['priority'] <= 3) {
                        $pri_class = 'bg-emerald-500/10 text-emerald-400'; $pri_text = 'Low';
                    } else {
                        $pri_class = 'bg-amber-500/10 text-amber-400'; $pri_text = 'Med';
                    }
                    // Expiry hint
                    $exp_hint = ''; $exp_class = 'text-slate-500';
                    if ($discount['expiry_status'] === 'expired')        { $exp_hint = 'Expired';        $exp_class = 'text-red-400'; }
                    elseif ($discount['expiry_status'] === 'expires-today') { $exp_hint = 'Expires today'; $exp_class = 'text-amber-400'; }
                    elseif ($discount['expiry_status'] === 'expires-soon')  { $exp_hint = 'Expires soon';  $exp_class = 'text-amber-400'; }
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <input type="checkbox" class="row-check rounded border-slate-600 bg-slate-700 text-amber-500 focus:ring-amber-500/50" value="<?php echo $discount['id']; ?>" data-name="<?php echo htmlspecialchars($discount['name'], ENT_QUOTES); ?>">
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-tag text-amber-400 text-xs shrink-0"></i>
                            <div>
                                <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($discount['name']); ?></div>
                                <?php if (!empty($discount['description'])): ?>
                                <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($discount['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $type_class; ?>">
                            <?php echo $discount['type'] === 'fixed' ? 'Fixed' : 'Percent'; ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($discount['type'] === 'fixed'): ?>
                            <span class="text-sm font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format((float)$discount['value'], 0); ?></span>
                        <?php else: ?>
                            <span class="text-sm font-bold text-amber-400"><?php echo $discount['value']; ?>%</span>
                        <?php endif; ?>
                        <?php if ($discount['max_discount']): ?>
                        <div class="text-xs text-slate-500">Max: <?php echo $currency_symbol . ' ' . number_format((float)$discount['max_discount'], 0); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($discount['min_purchase'] > 0): ?>
                            <span class="text-sm text-slate-300"><?php echo $currency_symbol . ' ' . number_format((float)$discount['min_purchase'], 0); ?></span>
                        <?php else: ?>
                            <span class="text-xs text-slate-600">No minimum</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($discount['valid_from'] || $discount['valid_until']): ?>
                            <?php if ($discount['valid_from']): ?>
                            <div class="text-xs text-slate-400">From: <?php echo date('d M Y', strtotime($discount['valid_from'])); ?></div>
                            <?php endif; ?>
                            <?php if ($discount['valid_until']): ?>
                            <div class="text-xs text-slate-400">Until: <?php echo date('d M Y', strtotime($discount['valid_until'])); ?></div>
                            <?php endif; ?>
                            <?php if ($exp_hint): ?>
                            <div class="text-xs <?php echo $exp_class; ?>"><?php echo $exp_hint; ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-xs text-slate-600">No expiry</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if (!empty($discount['code'])): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-700 border border-slate-600 text-amber-400 text-xs font-mono tracking-wider">
                            <i class="fas fa-ticket text-[10px]"></i><?php echo htmlspecialchars($discount['code']); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-xs text-slate-600">Auto</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $pri_class; ?>">
                            <?php echo $pri_text; ?> (<?php echo $discount['priority']; ?>)
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <?php
                        $used = (int)($discount['usage_count'] ?? 0);
                        $limit = (int)($discount['usage_limit'] ?? 0);
                        $usagePct = $limit > 0 ? min(100, round(($used / $limit) * 100)) : 0;
                        ?>
                        <div class="flex items-center gap-2">
                            <span class="text-xs <?php echo ($limit > 0 && $used >= $limit) ? 'text-red-400' : 'text-slate-400'; ?> whitespace-nowrap">
                                <?php echo $used; ?><?php echo $limit > 0 ? ' / ' . $limit : ''; ?>
                            </span>
                            <?php if ($limit > 0): ?>
                            <div class="w-16 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full <?php echo $usagePct >= 100 ? 'bg-red-500' : ($usagePct >= 75 ? 'bg-amber-500' : 'bg-emerald-500'); ?>" style="width: <?php echo $usagePct; ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium <?php echo $st_class; ?>">
                            <i class="fas <?php echo $st_icon; ?> text-xs"></i> <?php echo $st_label; ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-1.5">
                            <button type="button" onclick="previewDiscount(<?php echo $discount['id']; ?>)"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-blue-400 hover:bg-blue-500/20 transition-colors" title="Preview">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <a href="discount_form.php?id=<?php echo $discount['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <button type="button" onclick="ajaxToggle(<?php echo $discount['id']; ?>, <?php echo $discount['active']; ?>)"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 <?php echo $discount['active'] ? 'text-amber-400 hover:bg-amber-500/20' : 'text-emerald-400 hover:bg-emerald-500/20'; ?> transition-colors"
                                title="<?php echo $discount['active'] ? 'Deactivate' : 'Activate'; ?>" data-id="<?php echo $discount['id']; ?>">
                                <i class="fas <?php echo $discount['active'] ? 'fa-pause' : 'fa-play'; ?> text-xs toggle-icon-<?php echo $discount['id']; ?>"></i>
                            </button>
                            <button type="button" onclick="duplicateDiscount(<?php echo $discount['id']; ?>)"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-purple-400 hover:bg-purple-500/20 transition-colors" title="Duplicate">
                                <i class="fas fa-copy text-xs"></i>
                            </button>
                            <?php if (!empty($discount['code'])): ?>
                            <button type="button" onclick="copyCode('<?php echo htmlspecialchars($discount['code'], ENT_QUOTES); ?>')"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-emerald-400 hover:bg-emerald-500/20 transition-colors" title="Copy code">
                                <i class="fas fa-clipboard text-xs"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                            <button type="button" onclick="openDeleteModal(<?php echo $discount['id']; ?>, '<?php echo htmlspecialchars($discount['name'], ENT_QUOTES); ?>')"
                                class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-red-400 hover:bg-red-500/20 transition-colors" title="Delete">
                                <i class="fas fa-trash text-xs"></i>
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

    <!-- Table footer -->
    <?php if (!empty($discounts)): ?>
    <div class="flex items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium"><?php echo count($discounts); ?></span> discount<?php echo count($discounts) !== 1 ? 's' : ''; ?>
        </p>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span>Active</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span>Expired</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-slate-500 rounded-full"></span>Inactive</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60  flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-md w-full mx-4 shadow-2xl">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-triangle-exclamation text-red-400"></i>
            <h3 class="text-base font-semibold text-white">Delete Discount</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteDiscountId">
            <p class="text-sm text-slate-400 mb-5">
                Are you sure you want to delete <span id="deleteDiscountName" class="text-white font-semibold"></span>?
                This action cannot be undone.
            </p>
            <div class="flex gap-2">
                <button type="submit"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/25 transition-colors">
                    <i class="fas fa-trash text-xs"></i> Delete
                </button>
                <button type="button" onclick="closeDeleteModal()"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black/60  flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-lg w-full mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-eye text-blue-400"></i>
                <h3 class="text-base font-semibold text-white">Discount Preview</h3>
            </div>
            <button onclick="closePreviewModal()" class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-700 text-slate-400 hover:text-white hover:bg-slate-600 transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div id="previewContent" class="space-y-3 text-sm text-slate-300">
            <!-- Populated by JS -->
        </div>
        <div class="mt-5 flex justify-end">
            <button onclick="closePreviewModal()" class="px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    // Discount data embedded for preview
    const discountData = <?php echo json_encode(array_reduce($discounts, function($carry, $d) {
        $carry[$d['id']] = $d;
        return $carry;
    }, [])); ?>;

    // Select All / Bulk Selection
    const selectAll = document.getElementById('selectAll');
    const rowChecks = document.querySelectorAll('.row-check');
    const bulkBar = document.getElementById('bulkBar');
    const bulkCount = document.getElementById('bulkCount');

    function updateBulkBar() {
        const checked = document.querySelectorAll('.row-check:checked');
        bulkCount.textContent = checked.length;
        bulkBar.classList.toggle('hidden', checked.length === 0);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });
    }

    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });

    function clearSelection() {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
    }

    // AJAX Toggle (no page reload)
    function ajaxToggle(id, currentStatus) {
        const btn = document.querySelector(`button[data-id="${id}"]`);
        const icon = document.querySelector(`.toggle-icon-${id}`);
        if (btn) btn.disabled = true;

        fetch('discounts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_status&id=${id}&status=${currentStatus ? 0 : 1}`
        })
        .then(r => r.text())
        .then(() => {
            // Swap icon and class without reload
            const newActive = !currentStatus;
            if (icon) {
                icon.className = `fas ${newActive ? 'fa-pause' : 'fa-play'} text-xs toggle-icon-${id}`;
            }
            if (btn) {
                btn.title = newActive ? 'Deactivate' : 'Activate';
                btn.className = `w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 ${newActive ? 'text-amber-400 hover:bg-amber-500/20' : 'text-emerald-400 hover:bg-emerald-500/20'} transition-colors`;
                btn.setAttribute('onclick', `ajaxToggle(${id}, ${newActive ? 1 : 0})`);
            }
            // Update status badge
            const row = btn.closest('tr');
            const statusCell = row.querySelector('td:nth-child(10) span');
            if (statusCell) {
                if (newActive) {
                    statusCell.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
                    statusCell.innerHTML = '<i class="fas fa-check-circle text-xs"></i> Active';
                } else {
                    statusCell.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
                    statusCell.innerHTML = '<i class="fas fa-circle-xmark text-xs"></i> Inactive';
                }
            }
            showToast(newActive ? 'Discount activated' : 'Discount deactivated', 'success');
        })
        .catch(() => showToast('Failed to toggle status', 'error'))
        .finally(() => { if (btn) btn.disabled = false; });
    }

    // Copy Code to Clipboard
    function copyCode(code) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(() => showToast('Code copied: ' + code, 'success'));
        } else {
            const ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('Code copied: ' + code, 'success');
        }
    }

    // Duplicate Discount
    function duplicateDiscount(id) {
        if (!confirm('Duplicate this discount?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }

    // Bulk Action
    function bulkAction(type) {
        const checked = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
        if (checked.length === 0) return showToast('No items selected', 'warning');
        if (type === 'delete' && !confirm(`Delete ${checked.length} discount(s)? This cannot be undone.`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        let html = `<input type="hidden" name="action" value="bulk_action"><input type="hidden" name="bulk_type" value="${type}">`;
        checked.forEach(id => { html += `<input type="hidden" name="bulk_ids[]" value="${id}">`; });
        form.innerHTML = html;
        document.body.appendChild(form);
        form.submit();
    }

    // Preview Modal
    function previewDiscount(id) {
        const d = discountData[id];
        if (!d) return;
        const content = document.getElementById('previewContent');
        const currency = '<?php echo $currency_symbol; ?>';
        content.innerHTML = `
            <div class="grid grid-cols-2 gap-3">
                <div><span class="text-slate-500 text-xs">Name</span><div class="text-white font-medium">${d.name}</div></div>
                <div><span class="text-slate-500 text-xs">Type</span><div class="text-white font-medium capitalize">${d.type}</div></div>
                <div><span class="text-slate-500 text-xs">Value</span><div class="text-amber-400 font-medium">${d.type === 'fixed' ? currency + ' ' + d.value : d.value + '%'}</div></div>
                <div><span class="text-slate-500 text-xs">Priority</span><div class="text-white font-medium">${d.priority}</div></div>
                <div><span class="text-slate-500 text-xs">Min Purchase</span><div class="text-white font-medium">${d.min_purchase > 0 ? currency + ' ' + d.min_purchase : 'None'}</div></div>
                <div><span class="text-slate-500 text-xs">Max Discount</span><div class="text-white font-medium">${d.max_discount ? currency + ' ' + d.max_discount : 'None'}</div></div>
                <div><span class="text-slate-500 text-xs">Code</span><div class="text-white font-medium">${d.code || 'Auto'}</div></div>
                <div><span class="text-slate-500 text-xs">Usage</span><div class="text-white font-medium">${d.usage_count || 0}${d.usage_limit ? ' / ' + d.usage_limit : ''}</div></div>
            </div>
            ${d.valid_from || d.valid_until ? `
            <div class="pt-3 border-t border-slate-700/60">
                <span class="text-slate-500 text-xs">Valid Period</span>
                <div class="text-white">${d.valid_from ? 'From: ' + d.valid_from : ''}${d.valid_until ? ' &rarr; Until: ' + d.valid_until : ''}</div>
            </div>` : ''}
            ${d.description ? `<div class="pt-3 border-t border-slate-700/60"><span class="text-slate-500 text-xs">Description</span><div class="text-white">${d.description}</div></div>` : ''}
        `;
        document.getElementById('previewModal').classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
    }

    // Export to CSV
    function exportCSV() {
        const rows = [['ID','Name','Type','Value','Min Purchase','Max Discount','Code','Priority','Usage','Status','Valid From','Valid Until','Description']];
        Object.values(discountData).forEach(d => {
            const status = d.active ? 'Active' : 'Inactive';
            rows.push([
                d.id, d.name, d.type, d.value, d.min_purchase, d.max_discount || '', d.code || '',
                d.priority, (d.usage_count || 0) + (d.usage_limit ? '/' + d.usage_limit : ''), status,
                d.valid_from || '', d.valid_until || '', d.description || ''
            ]);
        });
        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'discounts_export.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('CSV exported successfully', 'success');
    }

    // Toast notification
    function showToast(message, type = 'info') {
        const colors = { success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/15 border-red-500/30 text-red-400', warning: 'bg-amber-500/15 border-amber-500/30 text-amber-400', info: 'bg-blue-500/15 border-blue-500/30 text-blue-400' };
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 rounded-lg border text-sm font-medium ${colors[type]} shadow-lg`;
        toast.innerHTML = `<i class="fas ${icons[type]} text-xs"></i> ${message}`;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; setTimeout(() => toast.remove(), 500); }, 3000);
    }

    // Delete Modal Functions
    function openDeleteModal(id, name) {
        document.getElementById('deleteDiscountId').value = id;
        document.getElementById('deleteDiscountName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.querySelectorAll('#deleteModal, #previewModal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;

        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            window.location.href = 'discount_form.php';
        }
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            window.location.href = 'index.php';
        }
        if (e.key === 'Escape') {
            document.querySelectorAll('#deleteModal, #previewModal').forEach(m => m.classList.add('hidden'));
        }
    });

    // Auto-hide success message after 5 seconds
    document.addEventListener('DOMContentLoaded', function () {
        const successMsg = document.querySelector('.bg-emerald-500\\/10');
        if (successMsg) {
            setTimeout(() => {
                successMsg.style.transition = 'opacity 0.5s ease';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }, 5000);
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
