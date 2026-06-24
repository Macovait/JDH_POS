<?php
/**
 * Inventory management page for Jakababa POS
 * Complete inventory management with branch-specific filtering
 */

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 2) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_login();

// Check for inventory management permission
if (!check_permission('inventory.manage') && !is_super_admin()) {
    enforce_permission('inventory.manage');
}

$pdo = get_db_connection();
$tenant_id = (int) get_current_tenant_id();

if ($tenant_id <= 0) {
    header('Location: ../auth/login.php?error=no_tenant');
    exit;
}

// Get current user info for navbar
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$user_default_branch = get_current_branch_id(); // Use the function instead of session directly

// Get all branches for filtering - only if user has permission
$branches = [];
if (check_permission('branches.view') || is_super_admin()) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1 ORDER BY name");
        $stmt->execute([$tenant_id]);
        if ($stmt) {
            $branches = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching branches: " . $e->getMessage());
        $branches = [];
    }
}

// Handle branch switching - use session-based branch
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $user_default_branch;

// Verify user has access to this branch
if (!is_super_admin()) {
    // Check if user is assigned to this branch
        $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id, $tenant_id]);
    $user_data = $stmt->fetch();

    if ($user_data && $user_data['branch_id'] > 0 && $user_data['branch_id'] != $branch_id) {
        // User doesn't have access to this branch, redirect to their default
        header('Location: inventory.php?branch_id=' . $user_default_branch);
        exit;
    }
}

// Update session if user has permission and branch is valid
if (isset($_GET['branch_id']) && (check_permission('branches.view') || is_super_admin())) {
    $_SESSION['user']['branch_id'] = $branch_id;
    $_SESSION['current_branch']['id'] = $branch_id;
}

// Get branch name
$branch_name = 'Main Branch';
try {
    $stmt = $pdo->prepare('SELECT name FROM branches WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$branch_id, $tenant_id]);
    $branch = $stmt->fetch();
    if ($branch) {
        $branch_name = $branch['name'];
        // Update session with branch name
        $_SESSION['current_branch']['name'] = $branch_name;
    }
} catch (PDOException $e) {
    error_log("Error fetching branch name: " . $e->getMessage());
}

// Handle filter parameters
$search = $_GET['search'] ?? '';
$category_filter = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$status_filter = $_GET['status'] ?? 'all';

// Get categories for filter - show categories that have products in this branch
$categories = [];
try {
    $sql = "
        SELECT DISTINCT c.id, c.name, c.color 
        FROM categories c
        JOIN products p ON c.id = p.category_id
        JOIN inventory i ON p.id = i.product_id AND i.tenant_id = p.tenant_id
        WHERE i.branch_id = ? AND i.tenant_id = ? AND c.status = 'active' AND c.deleted_at IS NULL
        ORDER BY c.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branch_id, $tenant_id]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Handle POST actions with GET redirects
$redirect_msg = '';
$redirect_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update':
            $product_id = intval($_POST['product_id'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $reorder_level = intval($_POST['reorder_level'] ?? 5);
            $notes = trim($_POST['notes'] ?? '');
            if ($product_id > 0) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                    $stmt->execute([$product_id, $branch_id, $tenant_id]);
                    $current = $stmt->fetch();
                    $old_stock = $current ? $current['stock'] : 0;
                    $stmt = $pdo->prepare('SELECT 1 FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                    $stmt->execute([$product_id, $branch_id, $tenant_id]);
                    if ($stmt->fetch()) {
                        $pdo->prepare('UPDATE inventory SET stock = ?, reorder_level = ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?')->execute([$stock, $reorder_level, $product_id, $branch_id, $tenant_id]);
                    } else {
                        $pdo->prepare('INSERT INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')->execute([$tenant_id, $product_id, $branch_id, $stock, $reorder_level]);
                    }
                    if ($old_stock != $stock) {
                        $change = $stock - $old_stock;
                        $pdo->prepare('INSERT INTO inventory_logs (tenant_id, product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')->execute([$tenant_id, $product_id, $branch_id, $old_stock, $stock, $change, $notes ?: 'Manual update', $user_id]);
                    }
                    $pdo->commit();
                    $redirect_msg = 'Stock updated successfully';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error updating inventory: " . $e->getMessage());
                    $redirect_msg = 'Failed to update stock: ' . $e->getMessage();
                    $redirect_type = 'error';
                }
            }
            break;

        case 'bulk_update':
            $products = $_POST['products'] ?? [];
            $success_count = 0;
            $error_count = 0;
            $pdo->beginTransaction();
            foreach ($products as $product_id => $data) {
                try {
                    $stock = intval($data['stock'] ?? 0);
                    $reorder_level = intval($data['reorder_level'] ?? 5);
                    $stmt = $pdo->prepare('SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                    $stmt->execute([$product_id, $branch_id, $tenant_id]);
                    $current = $stmt->fetch();
                    $old_stock = $current ? $current['stock'] : 0;
                    if ($current) {
                        $pdo->prepare('UPDATE inventory SET stock = ?, reorder_level = ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?')->execute([$stock, $reorder_level, $product_id, $branch_id, $tenant_id]);
                    } else {
                        $pdo->prepare('INSERT INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')->execute([$tenant_id, $product_id, $branch_id, $stock, $reorder_level]);
                    }
                    if ($old_stock != $stock) {
                        $change = $stock - $old_stock;
                        $pdo->prepare('INSERT INTO inventory_logs (tenant_id, product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')->execute([$tenant_id, $product_id, $branch_id, $old_stock, $stock, $change, 'Bulk update', $user_id]);
                    }
                    $success_count++;
                } catch (Exception $e) {
                    $error_count++;
                    error_log("Error in bulk update for product $product_id: " . $e->getMessage());
                }
            }
            $pdo->commit();
            if ($success_count > 0) {
                $redirect_msg = "$success_count products updated successfully";
                if ($error_count > 0) $redirect_msg .= " ($error_count failed)";
            } else {
                $redirect_msg = 'No products were updated';
                $redirect_type = 'error';
            }
            break;

        case 'adjust':
            $product_id = intval($_POST['product_id'] ?? 0);
            $adjustment = intval($_POST['adjustment'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($product_id > 0 && $adjustment != 0) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                    $stmt->execute([$product_id, $branch_id, $tenant_id]);
                    $current = $stmt->fetch();
                    if (!$current) throw new Exception('Product not found in inventory');
                    $old_stock = $current['stock'];
                    $new_stock = max(0, $old_stock + $adjustment);
                    $pdo->prepare('UPDATE inventory SET stock = ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?')->execute([$new_stock, $product_id, $branch_id, $tenant_id]);
                    $pdo->prepare('INSERT INTO inventory_logs (tenant_id, product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')->execute([$tenant_id, $product_id, $branch_id, $old_stock, $new_stock, $adjustment, "Adjustment: $reason", $user_id]);
                    $pdo->commit();
                    $redirect_msg = 'Stock adjusted successfully';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error adjusting inventory: " . $e->getMessage());
                    $redirect_msg = 'Failed to adjust stock: ' . $e->getMessage();
                    $redirect_type = 'error';
                }
            }
            break;

        case 'add_product':
            $product_id = intval($_POST['product_id'] ?? 0);
            $initial_stock = intval($_POST['initial_stock'] ?? 0);
            $reorder_level = intval($_POST['reorder_level'] ?? 5);
            if ($product_id > 0) {
                try {
                        $stmt = $pdo->prepare('SELECT 1 FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                        $stmt->execute([$product_id, $branch_id, $tenant_id]);
                        if ($stmt->fetch()) {
                            $redirect_msg = 'Product already exists in inventory for this branch';
                            $redirect_type = 'error';
                        } else {
                            $pdo->beginTransaction();
                            $pdo->prepare('INSERT INTO inventory (tenant_id, product_id, branch_id, stock, reorder_level, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')->execute([$tenant_id, $product_id, $branch_id, $initial_stock, $reorder_level]);
                            if ($initial_stock > 0) {
                                $pdo->prepare('INSERT INTO inventory_logs (tenant_id, product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')->execute([$tenant_id, $product_id, $branch_id, 0, $initial_stock, $initial_stock, "Initial stock setup", $user_id]);
                            }
                            $pdo->commit();
                            $redirect_msg = 'Product added to inventory successfully';
                        }
                } catch (Exception $e) {
                    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                    error_log("Error adding to inventory: " . $e->getMessage());
                    $redirect_msg = 'Failed to add product: ' . $e->getMessage();
                    $redirect_type = 'error';
                }
            }
            break;

        case 'remove_product':
            if (is_super_admin() || $user_role === 'Admin') {
                $product_id = intval($_POST['product_id'] ?? 0);
                if ($product_id > 0) {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare('SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                        $stmt->execute([$product_id, $branch_id, $tenant_id]);
                        $current = $stmt->fetch();
                        if ($current && $current['stock'] > 0) {
                            $pdo->prepare('INSERT INTO inventory_logs (tenant_id, product_id, branch_id, old_stock, new_stock, change_amount, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')->execute([$tenant_id, $product_id, $branch_id, $current['stock'], 0, -$current['stock'], "Product removed from inventory", $user_id]);
                        }
                        $pdo->prepare('DELETE FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?')->execute([$product_id, $branch_id, $tenant_id]);
                        $pdo->commit();
                        $redirect_msg = 'Product removed from inventory';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("Error removing from inventory: " . $e->getMessage());
                        $redirect_msg = 'Failed to remove product: ' . $e->getMessage();
                        $redirect_type = 'error';
                    }
                }
            }
            break;

        case 'update_settings':
            if (is_super_admin() || $user_role === 'Admin' || $user_role === 'Manager') {
                $global_reorder = intval($_POST['global_reorder'] ?? 5);
                try {
                    $pdo->prepare('UPDATE inventory SET reorder_level = ? WHERE branch_id = ? AND tenant_id = ?')->execute([$global_reorder, $branch_id, $tenant_id]);
                    $redirect_msg = 'Inventory settings updated successfully';
                } catch (Exception $e) {
                    error_log("Error updating settings: " . $e->getMessage());
                    $redirect_msg = 'Failed to update settings';
                    $redirect_type = 'error';
                }
            }
            break;
    }

    if ($redirect_msg) {
        $param = $redirect_type === 'error' ? 'error' : 'success';
        $qs = http_build_query(array_merge($_GET, [$param => $redirect_msg]));
        header('Location: inventory.php?' . $qs);
        exit;
    }
}

// Read flash messages from GET
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// FIXED: Build inventory query to show ONLY products that exist in this branch's inventory
$sql = "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.price as selling_price,
        p.cost_price,
        c.id as category_id,
        c.name AS category_name,
        c.color AS category_color,
        i.stock,
        i.reorder_level,
        i.expiry_date,
        i.location,
        i.batch_number,
        i.minimum_stock,
        i.maximum_stock,
        (p.price * i.stock) as stock_value,
        (p.cost_price * i.stock) as stock_cost,
        CASE 
            WHEN i.stock <= 0 THEN 'out'
            WHEN i.stock <= i.reorder_level THEN 'low'
            WHEN i.stock <= (i.reorder_level * 2) THEN 'medium'
            ELSE 'good'
        END AS stock_status,
        CASE 
            WHEN i.stock <= 0 THEN 'Out of Stock'
            WHEN i.stock <= i.reorder_level THEN 'Low Stock'
            WHEN i.stock <= (i.reorder_level * 2) THEN 'Medium Stock'
            ELSE 'Good Stock'
        END AS status_label,
        (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count
    FROM inventory i
    INNER JOIN products p ON i.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE i.branch_id = :branch_id
    AND i.tenant_id = :tenant_id
    AND p.deleted_at IS NULL AND (p.active = 1 OR p.active IS NULL)
";

$params = [':branch_id' => $branch_id, ':tenant_id' => $tenant_id];

// Apply filters
if (!empty($search)) {
    $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($category_filter > 0) {
    $sql .= " AND p.category_id = :category";
    $params[':category'] = $category_filter;
}

if ($status_filter === 'in') {
    $sql .= " AND i.stock > i.reorder_level";
} elseif ($status_filter === 'low') {
    $sql .= " AND i.stock > 0 AND i.stock <= i.reorder_level";
} elseif ($status_filter === 'out') {
    $sql .= " AND i.stock <= 0";
}

// Get status counts for tabs (without status filter)
$status_counts = ['all' => 0, 'in' => 0, 'low' => 0, 'out' => 0];
try {
    $count_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN i.stock <= 0 THEN 1 ELSE 0 END) as out_count,
            SUM(CASE WHEN i.stock > 0 AND i.stock <= i.reorder_level THEN 1 ELSE 0 END) as low_count,
            SUM(CASE WHEN i.stock > i.reorder_level THEN 1 ELSE 0 END) as in_count
        FROM inventory i
        INNER JOIN products p ON i.product_id = p.id
        WHERE i.branch_id = :branch_id
        AND i.tenant_id = :tenant_id
        AND p.deleted_at IS NULL AND (p.active = 1 OR p.active IS NULL)
    ";
    $count_params = [':branch_id' => $branch_id, ':tenant_id' => $tenant_id];
    if (!empty($search)) {
        $count_sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR p.description LIKE :search)";
        $count_params[':search'] = "%$search%";
    }
    if ($category_filter > 0) {
        $count_sql .= " AND p.category_id = :category";
        $count_params[':category'] = $category_filter;
    }
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $status_counts['all'] = (int)$counts['total'];
    $status_counts['out'] = (int)$counts['out_count'];
    $status_counts['low'] = (int)$counts['low_count'];
    $status_counts['in'] = (int)$counts['in_count'];
} catch (PDOException $e) {
    error_log("Error fetching status counts: " . $e->getMessage());
}

// Sorting
$sort = $_GET['sort'] ?? 'status';

switch ($sort) {
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    case 'stock_high':
        $sql .= " ORDER BY i.stock DESC";
        break;
    case 'stock_low':
        $sql .= " ORDER BY i.stock ASC";
        break;
    case 'value_high':
        $sql .= " ORDER BY stock_value DESC";
        break;
    default:
        $sql .= " ORDER BY CASE WHEN i.stock <= 0 THEN 1 WHEN i.stock <= i.reorder_level THEN 2 ELSE 3 END, p.name ASC";
}

$inventory = $pdo->prepare($sql);
$inventory->execute($params);
$inventory_items = $inventory->fetchAll();

// Get products not in this branch's inventory (for add dropdown)
$products_not_in_inventory = $pdo->prepare('
    SELECT p.id, p.name, p.sku, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.deleted_at IS NULL AND (p.active = 1 OR p.active IS NULL)
    AND p.id NOT IN (
        SELECT product_id FROM inventory WHERE branch_id = ? AND tenant_id = ?
    )
    ORDER BY p.name
');
$products_not_in_inventory->execute([$branch_id, $tenant_id]);
$available_products = $products_not_in_inventory->fetchAll();

// Get recent inventory logs for this branch only
$logs = $pdo->prepare('
    SELECT 
        l.*,
        p.name as product_name,
        u.name as user_name
    FROM inventory_logs l
    JOIN products p ON l.product_id = p.id
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.branch_id = ? AND l.tenant_id = ?
    ORDER BY l.created_at DESC
    LIMIT 20
');
$logs->execute([$branch_id, $tenant_id]);
$recent_logs = $logs->fetchAll();

// Calculate statistics (only for items in this branch)
$total_products = count($inventory_items);
$total_stock = array_sum(array_column($inventory_items, 'stock'));
$total_value = array_sum(array_column($inventory_items, 'stock_value'));
$total_cost = array_sum(array_column($inventory_items, 'stock_cost'));
$potential_profit = $total_value - $total_cost;
$low_stock_count = count(array_filter($inventory_items, fn($item) => $item['stock_status'] === 'low'));
$out_of_stock_count = count(array_filter($inventory_items, fn($item) => $item['stock_status'] === 'out'));

// Count products in this branch vs total products
$total_products_global = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND deleted_at IS NULL");
$total_products_global->execute([$tenant_id]);
$total_products_global = $total_products_global->fetchColumn();
$not_in_inventory_count = $total_products_global - $total_products;

$page_title = 'Inventory Management';
$current_year = date('Y');
$can_delete = is_super_admin() || $user_role === 'Admin';
$currency_symbol = 'KSh';

// Debug info
error_log("Inventory page - Branch ID: $branch_id, Branch Name: $branch_name, Products in inventory: $total_products");

ob_start();
?>


<div class="fade-in">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Inventory</p>
            <h1 class="text-lg font-bold text-white">Inventory Management</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Stock for <span class="text-amber-400 font-semibold"><?php echo htmlspecialchars($branch_name); ?></span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if (!empty($available_products)): ?>
                <button onclick="openAddModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Add Product
                </button>
            <?php endif; ?>
            <?php if (!empty($inventory_items)): ?>
                <button onclick="openBulkEditModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    <i class="fas fa-pen-alt text-xs"></i> Bulk Edit
                </button>
            <?php endif; ?>
            <?php if (is_super_admin() || $user_role === 'Admin' || $user_role === 'Manager'): ?>
                <button onclick="openSettingsModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    <i class="fas fa-cog text-xs"></i> Settings
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-emerald-500/10 border border-emerald-500/40 rounded-lg text-emerald-400 text-sm">
            <i class="fas fa-check-circle flex-shrink-0"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="mb-4 flex items-center gap-2 px-4 py-3 bg-red-500/10 border border-red-500/40 rounded-lg text-red-400 text-sm">
            <i class="fas fa-exclamation-circle flex-shrink-0"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-cubes text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo $total_products; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">In Branch</div>
                <div class="text-[10px] text-slate-600"><?php echo $not_in_inventory_count; ?> to add</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-cubes-stacked text-emerald-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-white"><?php echo number_format($total_stock); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Total Stock</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-coins text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($total_value, 0); ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Value</div>
                <div class="text-[10px] text-slate-600">Profit: <?php echo $currency_symbol . ' ' . number_format($potential_profit, 0); ?></div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-exclamation-triangle text-amber-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-400"><?php echo $low_stock_count; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Low Stock</div>
            </div>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0"><i class="fas fa-times-circle text-red-400 text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-red-400"><?php echo $out_of_stock_count; ?></div>
                <div class="text-[10px] text-slate-500 uppercase tracking-wide">Out of Stock</div>
            </div>
        </div>
    </div>

    <?php
    $tab_params = array_diff_key($_GET, ['status' => 1, 'page' => 1]);
    $tab_qs = http_build_query($tab_params);
    $tab_base = $tab_qs ? 'inventory.php?' . $tab_qs . '&' : 'inventory.php?';
    ?>
    <!-- Status Tabs -->
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="<?php echo $tab_base; ?>status=all" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $status_filter === 'all' ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:border-amber-500/50 hover:text-amber-400'; ?>">
            All <span class="opacity-70">(<?php echo $status_counts['all']; ?>)</span>
        </a>
        <a href="<?php echo $tab_base; ?>status=in" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $status_filter === 'in' ? 'bg-emerald-500 text-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:border-emerald-500/50 hover:text-emerald-400'; ?>">
            In Stock <span class="opacity-70">(<?php echo $status_counts['in']; ?>)</span>
        </a>
        <a href="<?php echo $tab_base; ?>status=low" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $status_filter === 'low' ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:border-amber-500/50 hover:text-amber-400'; ?>">
            Low Stock <span class="opacity-70">(<?php echo $status_counts['low']; ?>)</span>
        </a>
        <a href="<?php echo $tab_base; ?>status=out" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $status_filter === 'out' ? 'bg-red-500 text-white' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:border-red-500/50 hover:text-red-400'; ?>">
            Out of Stock <span class="opacity-70">(<?php echo $status_counts['out']; ?>)</span>
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-2">
            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
            <?php if ($status_filter !== 'all'): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <?php endif; ?>
            <div class="md:col-span-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by name, SKU..."
                    class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
            </div>
            <div>
                <select name="category" class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select name="sort" class="w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors" onchange="this.form.submit()">
                    <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                    <option value="stock_high" <?php echo $sort === 'stock_high' ? 'selected' : ''; ?>>Stock High-Low</option>
                    <option value="stock_low" <?php echo $sort === 'stock_low' ? 'selected' : ''; ?>>Stock Low-High</option>
                    <option value="value_high" <?php echo $sort === 'value_high' ? 'selected' : ''; ?>>Value High-Low</option>
                </select>
            </div>
            <div class="flex gap-2 md:col-span-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                    <i class="fas fa-filter text-xs"></i> Apply
                </button>
                <a href="inventory.php?branch_id=<?php echo $branch_id; ?>" class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 text-sm hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-slate-700/60 flex justify-between items-center">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-clipboard-list text-amber-400"></i>
                Inventory — <?php echo htmlspecialchars($branch_name); ?>
            </h2>
            <span class="text-xs text-slate-500"><?php echo count($inventory_items); ?> items</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input type="checkbox" id="select-all" class="w-4 h-4 rounded accent-amber-500">
                        </th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Reorder</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Value</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($inventory_items)): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-slate-500">
                                <i class="fas fa-box-open text-3xl mb-2 opacity-40 block"></i>
                                No inventory items found in this branch
                                <?php if (!empty($available_products)): ?>
                                    <button onclick="openAddModal()" class="block mx-auto mt-2 text-amber-400 hover:underline text-sm">Add products to this branch</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory_items as $item):
                            $stockBadge = match($item['stock_status']) {
                                'good'   => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
                                'medium' => 'bg-amber-500/15 text-amber-400 border border-amber-500/30',
                                'low'    => 'bg-red-500/15 text-red-400 border border-red-500/30',
                                default  => 'bg-slate-500/15 text-slate-400 border border-slate-500/30',
                            };
                            $stockIcon = match($item['stock_status']) {
                                'good'   => 'check-circle',
                                'medium' => 'clock',
                                'low'    => 'exclamation-triangle',
                                default  => 'times-circle',
                            };
                        ?>
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" class="item-checkbox w-4 h-4 rounded accent-amber-500">
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-white text-sm"><?php echo htmlspecialchars($item['name']); ?></div>
                                <?php if ($item['variant_count'] > 0): ?>
                                    <span class="text-[11px] text-amber-400"><?php echo $item['variant_count']; ?> variants</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-400 font-mono text-xs"><?php echo htmlspecialchars($item['sku'] ?: '—'); ?></td>
                            <td class="px-4 py-3">
                                <?php if ($item['category_name']): ?>
                                    <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: <?php echo $item['category_color'] ?? '#fbbf24'; ?>20; color: <?php echo $item['category_color'] ?? '#fbbf24'; ?>">
                                        <?php echo htmlspecialchars($item['category_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono font-semibold <?php echo $item['stock'] < $item['reorder_level'] ? 'text-red-400' : 'text-white'; ?>"><?php echo $item['stock']; ?></span>
                                <?php if (!empty($item['location'])): ?>
                                    <div class="text-[11px] text-slate-500"><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($item['location']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-500 text-sm"><?php echo $item['reorder_level']; ?></td>
                            <td class="px-4 py-3 text-right text-amber-400 font-semibold text-sm"><?php echo $currency_symbol . ' ' . number_format($item['stock_value'], 0); ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $stockBadge; ?>">
                                    <i class="fas fa-<?php echo $stockIcon; ?> text-[9px]"></i>
                                    <?php echo $item['status_label']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="openUpdateModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>', <?php echo $item['stock']; ?>, <?php echo $item['reorder_level']; ?>)" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-amber-400 hover:bg-amber-500/10 transition-colors" title="Update">
                                        <i class="fas fa-pen text-xs"></i>
                                    </button>
                                    <button onclick="openAdjustModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>', <?php echo $item['stock']; ?>)" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10 transition-colors" title="Adjust">
                                        <i class="fas fa-arrows-alt text-xs"></i>
                                    </button>
                                    <?php if ($can_delete): ?>
                                        <button onclick="openRemoveModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Remove">
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
    </div>

    <!-- Recent Activity -->
    <?php if (!empty($recent_logs)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/60">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-history text-amber-400"></i>
                Recent Inventory Activity
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 border-b border-slate-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Date/Time</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Change</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">New Stock</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php foreach ($recent_logs as $log):
                        $change = $log['change_amount'];
                        $chgClass = $change > 0 ? 'text-emerald-400' : ($change < 0 ? 'text-red-400' : 'text-slate-500');
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-4 py-3 text-slate-500 text-xs"><?php echo date('M j, H:i', strtotime($log['created_at'])); ?></td>
                        <td class="px-4 py-3 text-white text-sm"><?php echo htmlspecialchars($log['product_name']); ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="<?php echo $chgClass; ?> font-semibold text-sm"><?php echo $change > 0 ? '+' : ''; ?><?php echo $change; ?></span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-white text-sm"><?php echo $log['new_stock']; ?></td>
                        <td class="px-4 py-3 text-slate-400 text-sm"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                        <td class="px-4 py-3 text-slate-500 text-xs"><?php echo htmlspecialchars($log['notes'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Shared modal input classes -->
    <?php
    $INP = 'w-full px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors';
    $LBL = 'block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1';
    ?>

    <!-- Update Stock Modal -->
    <div id="updateModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 max-w-md w-full mx-4 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-pen text-amber-400 text-sm"></i> Update Stock
                </h3>
                <button onclick="closeUpdateModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" id="updateProductId">
                <div>
                    <label class="<?php echo $LBL; ?>">Product</label>
                    <p id="updateProductName" class="text-white font-medium text-sm"></p>
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Stock Quantity</label>
                    <input type="number" name="stock" id="updateStock" required min="0" class="<?php echo $INP; ?>">
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Reorder Level</label>
                    <input type="number" name="reorder_level" id="updateReorderLevel" required min="0" class="<?php echo $INP; ?>">
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="Reason for update" class="<?php echo $INP; ?>">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Update Stock</button>
                    <button type="button" onclick="closeUpdateModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 max-w-md w-full mx-4 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-arrows-alt text-emerald-400 text-sm"></i> Adjust Stock
                </h3>
                <button onclick="closeAdjustModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="product_id" id="adjustProductId">
                <div>
                    <label class="<?php echo $LBL; ?>">Product</label>
                    <p id="adjustProductName" class="text-white font-medium text-sm"></p>
                    <p class="text-xs text-slate-500 mt-0.5">Current stock: <span id="currentStock" class="text-amber-400 font-semibold"></span></p>
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Adjustment Amount</label>
                    <input type="number" name="adjustment" id="adjustment" required placeholder="e.g. +10 or -5" class="<?php echo $INP; ?>">
                    <p class="text-xs text-slate-500 mt-1">Positive to add, negative to remove</p>
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Reason</label>
                    <input type="text" name="reason" required placeholder="e.g. Received from supplier" class="<?php echo $INP; ?>">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-500/15 border border-emerald-500/40 text-emerald-400 font-semibold text-sm hover:bg-emerald-500/25 transition-colors">Apply Adjustment</button>
                    <button type="button" onclick="closeAdjustModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 max-w-md w-full mx-4 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-plus-circle text-amber-400 text-sm"></i> Add Product to Branch
                </h3>
                <button onclick="closeAddModal()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"><i class="fas fa-times text-xs"></i></button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="action" value="add_product">
                <div>
                    <label class="<?php echo $LBL; ?>">Select Product</label>
                    <select name="product_id" required class="<?php echo $INP; ?>">
                        <option value="">Choose a product</option>
                        <?php foreach ($available_products as $product): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?><?php if (!empty($product['sku'])): ?> (<?php echo htmlspecialchars($product['sku']); ?>)<?php endif; ?><?php if (!empty($product['category_name'])): ?> — <?php echo htmlspecialchars($product['category_name']); ?><?php endif; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Initial Stock</label>
                    <input type="number" name="initial_stock" required min="0" value="0" class="<?php echo $INP; ?>">
                </div>
                <div>
                    <label class="<?php echo $LBL; ?>">Reorder Level</label>
                    <input type="number" name="reorder_level" required min="0" value="5" class="<?php echo $INP; ?>">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 font-semibold text-sm hover:bg-amber-500/25 transition-colors">Add to Branch</button>
                    <button type="button" onclick="closeAddModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove Product Modal -->
    <div id="removeModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
        <div class="bg-slate-800 border border-slate-700/60 rounded-xl p-5 max-w-md w-full mx-4 shadow-2xl">
            <div class="flex items-center gap-3 mb-4">
                <span class="w-9 h-9 rounded-full bg-red-500/15 flex items-center justify-center flex-shrink-0"><i class="fas fa-exclamation-triangle text-red-400"></i></span>
                <h3 class="text-base font-semibold text-white">Remove from Inventory</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="action" value="remove_product">
                <input type="hidden" name="product_id" id="removeProductId">
                <p class="text-slate-400 text-sm mb-5">Remove <span id="removeProductName" class="text-white font-semibold"></span> from this branch? This cannot be undone.</p>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/40 text-red-400 font-semibold text-sm hover:bg-red-500/25 transition-colors">Remove</button>
                    <button type="button" onclick="closeRemoveModal()" class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 font-medium text-sm hover:bg-slate-600 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once __DIR__ . '/_modal_bulk_edit.php'; ?>

    <?php require_once __DIR__ . '/_modal_settings.php'; ?>

    <script>
        const MODALS = ['updateModal','adjustModal','addModal','removeModal','bulkEditModal','settingsModal'];
        function openModal(id) { const m = document.getElementById(id); if(m){ m.classList.remove('hidden'); m.classList.add('flex'); } }
        function closeModal(id) { const m = document.getElementById(id); if(m){ m.classList.add('hidden'); m.classList.remove('flex'); } }

        function openUpdateModal(id, name, stock, reorder) {
            document.getElementById('updateProductId').value = id;
            document.getElementById('updateProductName').textContent = name;
            document.getElementById('updateStock').value = stock;
            document.getElementById('updateReorderLevel').value = reorder;
            openModal('updateModal');
        }
        function closeUpdateModal() { closeModal('updateModal'); }

        function openAdjustModal(id, name, stock) {
            document.getElementById('adjustProductId').value = id;
            document.getElementById('adjustProductName').textContent = name;
            document.getElementById('currentStock').textContent = stock;
            document.getElementById('adjustment').value = '';
            openModal('adjustModal');
        }
        function closeAdjustModal() { closeModal('adjustModal'); }

        function openAddModal()      { openModal('addModal'); }
        function closeAddModal()     { closeModal('addModal'); }

        function openRemoveModal(id, name) {
            document.getElementById('removeProductId').value = id;
            document.getElementById('removeProductName').textContent = name;
            openModal('removeModal');
        }
        function closeRemoveModal()  { closeModal('removeModal'); }
        function openBulkEditModal() { openModal('bulkEditModal'); }
        function closeBulkEditModal(){ closeModal('bulkEditModal'); }
        function openSettingsModal() { openModal('settingsModal'); }
        function closeSettingsModal(){ closeModal('settingsModal'); }

        MODALS.forEach(id => {
            const m = document.getElementById(id);
            if (m) m.addEventListener('click', e => { if (e.target === m) closeModal(id); });
        });

        // Select all checkboxes
        document.getElementById('select-all')?.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = isChecked);
        });

        // Auto-hide success message
        setTimeout(() => {
            const successMsg = document.querySelector('.bg-emerald-500\/10');
            if (successMsg) {
                successMsg.style.transition = 'opacity 0.5s';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }
        }, 5000);

        // Connection status
        function updateOnlineStatus() {
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                if (navigator.onLine) {
                    statusEl.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                } else {
                    statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
                }
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.target.matches('input, textarea, select')) return;

            // Alt + A - Add product
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                openAddModal();
            }

            // Alt + B - Bulk edit
            if (e.altKey && e.key === 'b') {
                e.preventDefault();
                openBulkEditModal();
            }

            // Escape - Close modal
            if (e.key === 'Escape') {
                closeUpdateModal();
                closeAdjustModal();
                closeAddModal();
                closeRemoveModal();
                closeBulkEditModal();
                closeSettingsModal();
            }
        });
    </script>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>