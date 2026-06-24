<?php
/**
 * Brands management page for JDH POS
 * Professional design for supermarkets, hotels, restaurants, chemists
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
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

$user_id = get_current_user_id();
$user_name = htmlspecialchars(get_current_user_name());
$user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'User';
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

$branches = [];
if (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view')) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) { error_log("Error fetching branches: " . $e->getMessage()); }
}

if (isset($_GET['branch_id']) && (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view'))) {
    $new_branch_id = (int) $_GET['branch_id'];
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1 AND deleted_at IS NULL");
    $stmt->execute([$new_branch_id, $tenant_id]);
    if ($stmt->fetch()) {
        $branch_id = $new_branch_id;
        $_SESSION['user']['branch_id'] = $branch_id;
        $_SESSION['current_branch']['id'] = $branch_id;
        $_SESSION['branch_id'] = $branch_id;
        $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$branch_id, $tenant_id]);
        $branch = $stmt->fetch();
        if ($branch) {
            $_SESSION['current_branch']['name'] = $branch['name'];
            $_SESSION['branch_name'] = $branch['name'];
            $branch_name = $branch['name'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token. Please refresh and try again.';
        echo json_encode($response);
        exit;
    }

    try {
        if ($_POST['ajax_action'] === 'toggle_status') {
            $brand_id = (int) $_POST['brand_id'];
            $current_status = (int) $_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE brands SET active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$new_status, $brand_id, $tenant_id]);
            $response = ['success' => true, 'message' => 'Brand status updated', 'new_status' => $new_status];
        } elseif ($_POST['ajax_action'] === 'bulk_delete') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids)) throw new Exception('No brands selected');
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id IN ($placeholders) AND tenant_id = ?");
            $stmt->execute(array_merge($ids, [$tenant_id]));
            if ($stmt->fetchColumn() > 0) throw new Exception('Cannot delete brands with associated products');
            $pdo->prepare("DELETE FROM brands WHERE id IN ($placeholders) AND tenant_id = ?")->execute(array_merge($ids, [$tenant_id]));
            $response = ['success' => true, 'message' => count($ids) . ' brands deleted successfully'];
        } elseif ($_POST['ajax_action'] === 'bulk_status') {
            $ids = $_POST['ids'] ?? [];
            $status = (int) $_POST['status'];
            if (empty($ids)) throw new Exception('No brands selected');
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE brands SET active = ?, updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?")->execute(array_merge([$status], $ids, [$tenant_id]));
            $response = ['success' => true, 'message' => count($ids) . ' brands ' . ($status ? 'activated' : 'deactivated')];
        } elseif ($_POST['ajax_action'] === 'quick_edit') {
            $brand_id = (int) ($_POST['brand_id'] ?? 0);
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            if (!$brand_id || !in_array($field, ['name','description'])) throw new Exception('Invalid parameters');
            if ($field === 'name' && empty(trim($value))) throw new Exception('Name cannot be empty');
            $pdo->prepare("UPDATE brands SET `$field` = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?")->execute([trim($value), $brand_id, $tenant_id]);
            $response = ['success' => true, 'message' => 'Updated'];
        } elseif ($_POST['ajax_action'] === 'quick_view') {
            $brand_id = (int) ($_POST['brand_id'] ?? 0);
            if (!$brand_id) throw new Exception('Brand ID required');
            $stmt = $pdo->prepare("SELECT b.*, (SELECT COUNT(*) FROM products WHERE brand_id = b.id AND tenant_id = b.tenant_id) as product_count FROM brands b WHERE b.id = ? AND b.tenant_id = ? LIMIT 1");
            $stmt->execute([$brand_id, $tenant_id]);
            $brand = $stmt->fetch();
            if (!$brand) throw new Exception('Brand not found');
            $prodStmt = $pdo->prepare("SELECT id, name, sku, price, image FROM products WHERE brand_id = ? AND tenant_id = ? AND deleted_at IS NULL ORDER BY name LIMIT 10");
            $prodStmt->execute([$brand_id, $tenant_id]);
            $products = $prodStmt->fetchAll();
            $response = ['success' => true, 'brand' => $brand, 'products' => $products];
        } elseif ($_POST['ajax_action'] === 'duplicate') {
            $brand_id = (int) ($_POST['brand_id'] ?? 0);
            if (!$brand_id) throw new Exception('Brand ID required');
            $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$brand_id, $tenant_id]);
            $brand = $stmt->fetch();
            if (!$brand) throw new Exception('Brand not found');
            unset($brand['id'], $brand['created_at'], $brand['updated_at']);
            $brand['name'] = $brand['name'] . ' (Copy)';
            $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($brand)));
            $vals = array_values($brand);
            $ph = implode(', ', array_fill(0, count($vals), '?'));
            $pdo->prepare("INSERT INTO brands ($cols, created_at, updated_at) VALUES ($ph, NOW(), NOW())")->execute($vals);
            $response = ['success' => true, 'message' => 'Brand duplicated', 'new_id' => $pdo->lastInsertId()];
        }
    } catch (Exception $e) { $response['message'] = $e->getMessage(); }
    echo json_encode($response);
    exit;
}

$csrf_token = generate_csrf_token();

$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'id_desc';

$sort_options = [
    'id_desc' => 'b.id DESC', 'id_asc' => 'b.id ASC',
    'name_asc' => 'b.name ASC', 'name_desc' => 'b.name DESC',
    'updated_desc' => 'b.updated_at DESC',
];
$order_by = $sort_options[$sort_by] ?? 'b.id DESC';

$sql = "SELECT b.id, b.name, b.description, b.active, b.image, b.created_at, b.updated_at,
        (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.tenant_id = b.tenant_id) as product_count
        FROM brands b
        WHERE b.tenant_id = ?";

$params = [$tenant_id];

if ($status_filter !== 'all') { $sql .= " AND b.active = ?"; $params[] = $status_filter === 'active' ? 1 : 0; }
if (!empty($search_term)) { $sql .= " AND b.name LIKE ?"; $params[] = "%$search_term%"; }
$sql .= " ORDER BY $order_by";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$brands = $stmt->fetchAll();


function displayBrandImage($image_path, $brand_name) {
    if ($image_path) {
        $full_path = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $image_path);
        if (file_exists($full_path)) {
            $mtime = @filemtime($full_path) ?: time();
            return '<img src="' . htmlspecialchars(base_url(ltrim($image_path, '/')) . '?v=' . $mtime, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8') . '" class="brand-thumb w-12 h-12 rounded-lg object-cover">';
        }
    }
    return '<div class="w-12 h-12 rounded-lg bg-slate-700 flex items-center justify-center text-slate-400"><i class="fas fa-tag"></i></div>';
}

$page_title = 'Brands | ' . ($branch_name ?? 'JDH POS');
ob_start();
?>

<!-- Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-base font-semibold text-white">Delete Brand</h3>
        </div>
        <p class="text-slate-400 text-sm mb-1">Are you sure you want to delete</p>
        <p id="deleteBrandName" class="font-semibold text-amber-400 mb-3 text-sm"></p>
        <p class="text-xs text-slate-500 mb-5">This action cannot be undone. Brands with associated products cannot be deleted.</p>
        <div class="flex gap-2">
            <button onclick="confirmDelete()" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash text-xs"></i> Delete
            </button>
            <button onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div id="bulkDeleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 ">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-base font-semibold text-white">Delete Selected Brands</h3>
        </div>
        <p class="text-slate-400 text-sm mb-4">Delete <span id="bulkDeleteCount" class="text-amber-400 font-bold">0</span> selected brands?</p>
        <p class="text-xs text-slate-500 mb-5">Brands with associated products cannot be deleted.</p>
        <div class="flex gap-2">
            <button onclick="confirmBulkDelete()" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash text-xs"></i> Delete All
            </button>
            <button onclick="closeBulkDeleteModal()" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- Quick View Modal -->
<div id="quickViewModal" class="fixed inset-0 bg-black/60  hidden items-center justify-center z-50 modal">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-md w-full mx-4 shadow-xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Brand Details</h3>
            <button onclick="closeModal('quickViewModal')" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700 text-slate-400 hover:text-white transition-colors"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div id="qvImage"></div>
                <div>
                    <div id="qvName" class="text-base font-bold text-white"></div>
                    <div id="qvProductCount" class="text-xs text-amber-400 mt-0.5"></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Status</div><div id="qvStatus" class="text-slate-300 font-medium"></div></div>
                <div class="bg-slate-900/60 rounded-lg p-2 border border-slate-700/40"><div class="text-xs text-slate-500">Created</div><div id="qvCreated" class="text-slate-300 font-medium"></div></div>
            </div>
            <div id="qvDescription" class="text-xs text-slate-400 bg-slate-900/40 rounded-lg p-2 border border-slate-700/30 hidden"></div>
            <div>
                <div class="text-xs text-slate-500 mb-2">Recent Products</div>
                <div id="qvProducts" class="space-y-1.5 max-h-48 overflow-y-auto"></div>
            </div>
        </div>
        <div class="flex gap-2 mt-4 pt-3 border-t border-slate-700/60">
            <a id="qvEditLink" href="#" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-pen text-xs"></i> Edit</a>
            <button onclick="closeModal('quickViewModal')" class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50"></div>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-0.5">Products</p>
        <h1 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-tags text-amber-400"></i> Brands</h1>
        <p class="text-sm text-slate-500 mt-0.5">Managing <span class="text-white font-medium"><?php echo count($brands); ?></span> brands in <span class="text-amber-400"><?php echo htmlspecialchars($branch_name); ?></span></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if (!empty($branches)): ?>
        <form method="GET" id="branchForm" class="inline">
            <select name="branch_id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                <?php foreach ($branches as $branch): ?>
                <option value="<?php echo $branch['id']; ?>" <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($search_term)): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>"><?php endif; ?>
            <?php if ($status_filter !== 'all'): ?><input type="hidden" name="status" value="<?php echo $status_filter; ?>"><?php endif; ?>
            <?php if ($sort_by !== 'id_desc'): ?><input type="hidden" name="sort" value="<?php echo $sort_by; ?>"><?php endif; ?>
        </form>
        <?php endif; ?>
        <a href="import_brands.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-upload text-xs"></i><span class="hidden sm:inline">Import</span>
        </a>
        <button onclick="exportBrands()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-download text-xs"></i><span class="hidden sm:inline">Export</span>
        </button>
        <a href="brand_form.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Brand
        </a>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <?php
    $totalBrands  = count($brands);
    $activeBrands = count(array_filter($brands, fn($b) => $b['active'] == 1));
    $inactiveBrands = $totalBrands - $activeBrands;
    $totalProducts  = array_sum(array_column($brands, 'product_count'));
    $stats = [
        ['label'=>'Total',    'value'=>$totalBrands,   'icon'=>'fa-tags',         'color'=>'text-blue-400'],
        ['label'=>'Active',   'value'=>$activeBrands,  'icon'=>'fa-check-circle', 'color'=>'text-emerald-400'],
        ['label'=>'Inactive', 'value'=>$inactiveBrands,'icon'=>'fa-pause-circle', 'color'=>'text-slate-400'],
        ['label'=>'Products', 'value'=>$totalProducts,  'icon'=>'fa-box',          'color'=>'text-amber-400'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-slate-700/60 flex items-center justify-center flex-shrink-0">
            <i class="fas <?php echo $s['icon']; ?> <?php echo $s['color']; ?> text-xs"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500"><?php echo $s['label']; ?></p>
            <p class="text-base font-bold <?php echo $s['color']; ?>"><?php echo $s['value']; ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status Tabs -->
<?php $active_tab = $status_filter ?: 'all'; ?>
<div class="flex items-center gap-1 mb-4 border-b border-slate-700/60 pb-2 overflow-x-auto">
    <a href="?<?php echo http_build_query(array_diff_key($_GET, array_flip(['status','page']))); ?>" class="px-3 py-1.5 text-sm rounded-lg whitespace-nowrap <?php echo $active_tab==='all'?'bg-amber-500 text-slate-900 font-semibold':'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
        All (<?php echo count($brands); ?>)
    </a>
    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['status','page'])), ['status'=>'active'])); ?>" class="px-3 py-1.5 text-sm rounded-lg whitespace-nowrap <?php echo $active_tab==='active'?'bg-emerald-500 text-slate-900 font-semibold':'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
        Active (<?php echo $activeBrands; ?>)
    </a>
    <a href="?<?php echo http_build_query(array_merge(array_diff_key($_GET, array_flip(['status','page'])), ['status'=>'inactive'])); ?>" class="px-3 py-1.5 text-sm rounded-lg whitespace-nowrap <?php echo $active_tab==='inactive'?'bg-slate-500 text-slate-900 font-semibold':'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
        Inactive (<?php echo $inactiveBrands; ?>)
    </a>
    <div class="ml-auto">
        <select onchange="window.location.href=this.value" class="bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">Sort</option>
            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'id_desc'])); ?>">Newest First</option>
            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'id_asc'])); ?>">Oldest First</option>
            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'name_asc'])); ?>">Name A-Z</option>
            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'name_desc'])); ?>">Name Z-A</option>
            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'updated_desc'])); ?>">Recently Updated</option>
        </select>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="hidden bg-slate-800/40 border border-slate-700/60 rounded-xl px-4 py-3 mb-4">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-400"><span id="selectedCount" class="text-white font-semibold">0</span> selected</span>
            <button onclick="selectAll()" class="text-xs text-amber-400 hover:text-amber-300 transition">Select All</button>
            <button onclick="clearSelection()" class="text-xs text-slate-500 hover:text-slate-300 transition">Clear</button>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="bulkStatusUpdate(1)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium hover:bg-emerald-500/20 transition-colors">
                <i class="fas fa-check text-xs"></i>Activate
            </button>
            <button onclick="bulkStatusUpdate(0)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-ban text-xs"></i>Deactivate
            </button>
            <button onclick="exportSelected()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-download text-xs"></i>Export Selected
            </button>
            <?php if (is_super_admin() || $user_role === 'Admin'): ?>
            <button onclick="openBulkDeleteModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash text-xs"></i>Delete
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl px-4 py-3 mb-4">
    <form method="GET" class="flex flex-col md:flex-row gap-3">
        <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
        <div class="flex-1 relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search brands…" id="searchInput"
                   class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-9 pr-3 py-2 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <div class="flex flex-wrap gap-2">
            <select name="status" class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-2 text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="all" <?php echo $status_filter==='all'?'selected':''; ?>>All Status</option>
                <option value="active" <?php echo $status_filter==='active'?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
            <select name="sort" class="bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-2 text-slate-300 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="id_desc" <?php echo $sort_by==='id_desc'?'selected':''; ?>>Newest First</option>
                <option value="id_asc" <?php echo $sort_by==='id_asc'?'selected':''; ?>>Oldest First</option>
                <option value="name_asc" <?php echo $sort_by==='name_asc'?'selected':''; ?>>Name A-Z</option>
                <option value="name_desc" <?php echo $sort_by==='name_desc'?'selected':''; ?>>Name Z-A</option>
                <option value="updated_desc" <?php echo $sort_by==='updated_desc'?'selected':''; ?>>Recently Updated</option>
            </select>
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-filter text-xs"></i><span class="hidden sm:inline">Filter</span>
            </button>
            <?php if (!empty($search_term) || $status_filter !== 'all' || $sort_by !== 'id_desc'): ?>
            <a href="brands.php?branch_id=<?php echo $branch_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-xmark text-xs"></i><span class="hidden sm:inline">Clear</span>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Brands Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800/60 border-b border-slate-700/40">
                <tr>
                    <th class="w-10 px-3 py-3 text-left">
                        <input type="checkbox" id="selectAllCheckbox" class="w-3.5 h-3.5 rounded accent-amber-500 cursor-pointer" onchange="toggleSelectAll(this)">
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Brand</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Description</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Products</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php if (empty($brands)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-16 text-center">
                        <i class="fas fa-tags text-3xl text-slate-700 mb-3 block"></i>
                        <p class="text-slate-400 font-medium mb-1">No brands found</p>
                        <p class="text-slate-500 text-xs mb-4"><?php echo (!empty($search_term) || $status_filter !== 'all') ? 'Try adjusting your filters' : 'Get started by adding your first brand'; ?></p>
                        <a href="brand_form.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                            <i class="fas fa-plus text-xs"></i> Add Brand
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($brands as $brand): ?>
                <tr class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-3 py-3">
                        <input type="checkbox" class="brand-checkbox w-3.5 h-3.5 rounded accent-amber-500 cursor-pointer" value="<?php echo $brand['id']; ?>">
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            <?php echo displayBrandImage($brand['image'], $brand['name']); ?>
                            <div>
                                <div class="font-medium text-white text-sm editable-name cursor-pointer hover:text-amber-400 transition-colors" title="Double-click to edit"
                                     data-id="<?php echo $brand['id']; ?>" data-field="name" data-value="<?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     ondblclick="inlineEdit(this)">
                                    <?php echo htmlspecialchars($brand['name']); ?>
                                </div>
                                <div class="text-xs text-slate-500">ID: <?php echo $brand['id']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3 max-w-xs">
                        <span class="text-slate-400 text-xs truncate block editable-desc cursor-pointer hover:text-slate-300 transition-colors" title="Double-click to edit"
                              data-id="<?php echo $brand['id']; ?>" data-field="description" data-value="<?php echo htmlspecialchars($brand['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                              ondblclick="inlineEdit(this)">
                            <?php echo htmlspecialchars($brand['description'] ?? '—'); ?>
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?php echo $brand['product_count'] > 0 ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-700/60 text-slate-400'; ?>">
                            <i class="fas fa-box"></i> <?php echo $brand['product_count']; ?>
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium <?php echo $brand['active'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-700/60 text-slate-400'; ?>">
                            <i class="fas fa-<?php echo $brand['active'] ? 'check-circle' : 'pause-circle'; ?>"></i>
                            <?php echo $brand['active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-1">
                            <button onclick="openQuickView(<?php echo $brand['id']; ?>)" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/60 transition-colors" title="Quick View">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <a href="brand_form.php?id=<?php echo $brand['id']; ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-amber-400 hover:bg-slate-700/60 transition-colors" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            <button onclick="toggleBrandStatus(<?php echo $brand['id']; ?>, <?php echo $brand['active']; ?>)" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700/60 transition-colors" title="<?php echo $brand['active'] ? 'Deactivate' : 'Activate'; ?>">
                                <i class="fas fa-<?php echo $brand['active'] ? 'ban' : 'check'; ?> text-xs"></i>
                            </button>
                            <button onclick="duplicateBrand(<?php echo $brand['id']; ?>)" class="p-1.5 rounded-lg text-slate-400 hover:text-amber-400 hover:bg-amber-500/10 transition-colors" title="Duplicate">
                                <i class="fas fa-clone text-xs"></i>
                            </button>
                            <?php if (is_super_admin() || $user_role === 'Admin'): ?>
                            <button onclick="openDeleteModal(<?php echo $brand['id']; ?>, '<?php echo htmlspecialchars($brand['name']); ?>')" class="p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Delete">
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

<script>
let selectedBrands = [];
let csrfToken = <?php echo json_encode($csrf_token); ?>;

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = { success: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/15 border-red-500/30 text-red-400', warning: 'bg-amber-500/15 border-amber-500/30 text-amber-400' };
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-triangle-exclamation' };
    toast.className = `flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium shadow-lg ${colors[type] || colors.success}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success} text-xs"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.transition = 'opacity 0.4s'; toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 3000);
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.brand-checkbox');
    checkboxes.forEach(cb => { cb.checked = checkbox.checked; updateSelection(cb); });
    updateBulkActions();
}

function updateSelection(checkbox) {
    const brandId = parseInt(checkbox.value);
    if (checkbox.checked) { if (!selectedBrands.includes(brandId)) selectedBrands.push(brandId); }
    else { selectedBrands = selectedBrands.filter(id => id !== brandId); }
    updateBulkActions();
}

function updateBulkActions() {
    const bar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const bulkDeleteCount = document.getElementById('bulkDeleteCount');
    if (selectedBrands.length > 0) { bar.classList.remove('hidden'); selectedCount.textContent = selectedBrands.length; if (bulkDeleteCount) bulkDeleteCount.textContent = selectedBrands.length; }
    else { bar.classList.add('hidden'); }
}

function selectAll() { document.querySelectorAll('.brand-checkbox').forEach(cb => { cb.checked = true; updateSelection(cb); }); }
function clearSelection() { document.querySelectorAll('.brand-checkbox').forEach(cb => { cb.checked = false; updateSelection(cb); }); }

document.querySelectorAll('.brand-checkbox').forEach(checkbox => { checkbox.addEventListener('change', () => updateSelection(checkbox)); });

function toggleBrandStatus(brandId, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    if (!confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this brand?`)) return;
    const formData = new FormData();
    formData.append('ajax_action', 'toggle_status');
    formData.append('brand_id', brandId);
    formData.append('current_status', currentStatus);
    formData.append('csrf_token', csrfToken);
    fetch('brands.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 600); } else showToast(data.message, 'error'); })
        .catch(() => showToast('Network error', 'error'));
}

function openDeleteModal(brandId, brandName) {
    document.getElementById('deleteBrandName').textContent = brandName;
    document.getElementById('deleteModal').classList.remove('hidden');
    window.confirmDelete = function() { bulkAction('bulk_delete', [brandId], 'brands.php'); };
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }
function openBulkDeleteModal() { document.getElementById('bulkDeleteModal').classList.remove('hidden'); }
function closeBulkDeleteModal() { document.getElementById('bulkDeleteModal').classList.add('hidden'); }
function confirmBulkDelete() { bulkAction('bulk_delete', selectedBrands, 'brands.php'); }
function bulkStatusUpdate(status) { bulkAction('bulk_status', selectedBrands, 'brands.php', { status: status }); }

function bulkAction(action, ids, url, extraData = {}) {
    if (ids.length === 0) return;
    const formData = new FormData();
    formData.append('ajax_action', action);
    formData.append('csrf_token', csrfToken);
    ids.forEach(id => formData.append('ids[]', id));
    Object.entries(extraData).forEach(([k, v]) => formData.append(k, v));
    fetch(url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 800); } else showToast(data.message, 'error'); })
        .catch(() => showToast('Network error', 'error'));
}

function exportBrands() { window.location.href = 'export_brands.php?branch_id=<?php echo $branch_id; ?>'; }
function exportSelected() {
    if (selectedBrands.length === 0) { showToast('No brands selected', 'warning'); return; }
    window.location.href = 'export_brands.php?branch_id=<?php echo $branch_id; ?>&ids=' + encodeURIComponent(selectedBrands.join(','));
}

// Inline editing
function inlineEdit(el) {
    if (el.querySelector('input, textarea')) return;
    const id = el.dataset.id;
    const field = el.dataset.field;
    const current = el.dataset.value;
    const input = document.createElement(field === 'description' ? 'textarea' : 'input');
    if (field !== 'description') { input.type = 'text'; }
    input.value = current;
    input.className = 'bg-slate-900 border border-amber-500 rounded px-1.5 py-0.5 text-sm text-white focus:outline-none';
    if (field === 'name') input.className += ' w-40';
    if (field === 'description') { input.className += ' w-full text-xs'; input.rows = 2; }
    el.innerHTML = '';
    el.appendChild(input);
    input.focus();
    input.select();

    function save() {
        const newVal = input.value.trim();
        if (newVal === current) { el.textContent = current || '—'; return; }
        const formData = new FormData();
        formData.append('ajax_action', 'quick_edit');
        formData.append('brand_id', id);
        formData.append('field', field);
        formData.append('value', newVal);
        formData.append('csrf_token', csrfToken);
        fetch('brands.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => { if (data.success) { showToast('Updated', 'success'); setTimeout(() => window.location.reload(), 500); } else { showToast(data.message || 'Error', 'error'); el.textContent = current || '—'; } })
            .catch(() => { showToast('Network error', 'error'); el.textContent = current || '—'; });
    }
    function cancel() { el.textContent = current || '—'; }
    input.addEventListener('blur', save);
    input.addEventListener('keydown', e => { if (e.key === 'Enter' && field !== 'description') { e.preventDefault(); save(); } if (e.key === 'Escape') { e.preventDefault(); cancel(); } });
}

// Quick View
function openQuickView(brandId) {
    const formData = new FormData();
    formData.append('ajax_action', 'quick_view');
    formData.append('brand_id', brandId);
    formData.append('csrf_token', csrfToken);
    fetch('brands.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast(data.message || 'Error loading brand', 'error'); return; }
            const b = data.brand;
            document.getElementById('qvName').textContent = b.name;
            document.getElementById('qvProductCount').textContent = b.product_count + ' product' + (b.product_count !== 1 ? 's' : '');
            document.getElementById('qvStatus').textContent = b.active ? 'Active' : 'Inactive';
            document.getElementById('qvStatus').className = 'text-sm font-medium ' + (b.active ? 'text-emerald-400' : 'text-slate-400');
            document.getElementById('qvCreated').textContent = b.created_at ? new Date(b.created_at).toLocaleDateString() : '—';
            document.getElementById('qvEditLink').href = 'brand_form.php?id=' + b.id;
            const desc = document.getElementById('qvDescription');
            if (b.description) { desc.textContent = b.description; desc.classList.remove('hidden'); } else { desc.classList.add('hidden'); }
            const imgDiv = document.getElementById('qvImage');
            if (b.image) {
                const rel = b.image.replace(/^\//, '').replace(/^public\//, '');
                imgDiv.innerHTML = '<img src="<?php echo base_url(''); ?>' + rel + '" class="w-14 h-14 rounded-xl object-cover border border-slate-700">';
            } else {
                imgDiv.innerHTML = '<div class="w-14 h-14 rounded-xl bg-slate-700 flex items-center justify-center text-slate-500"><i class="fas fa-tag text-lg"></i></div>';
            }
            const prodDiv = document.getElementById('qvProducts');
            prodDiv.innerHTML = '';
            if (data.products.length === 0) {
                prodDiv.innerHTML = '<div class="text-xs text-slate-500 text-center py-2">No products</div>';
            } else {
                data.products.forEach(p => {
                    const img = p.image ? '<img src="<?php echo base_url(''); ?>' + p.image.replace(/^\//, '').replace(/^public\//, '') + '" class="w-8 h-8 rounded-lg object-cover">' : '<div class="w-8 h-8 rounded-lg bg-slate-700 flex items-center justify-center text-slate-500"><i class="fas fa-cube text-xs"></i></div>';
                    prodDiv.innerHTML += '<div class="flex items-center gap-2 bg-slate-900/40 rounded-lg p-1.5 border border-slate-700/30"><div class="shrink-0">' + img + '</div><div class="min-w-0"><div class="text-xs text-white truncate">' + p.name + '</div><div class="text-xs text-slate-500">' + (p.sku || 'No SKU') + ' &middot; <?php echo htmlspecialchars(get_tenant_currency()); ?> ' + parseFloat(p.price).toFixed(2) + '</div></div></div>';
                });
            }
            const m = document.getElementById('quickViewModal');
            m.classList.remove('hidden'); m.classList.add('flex');
        })
        .catch(() => showToast('Network error', 'error'));
}

// Duplicate
function duplicateBrand(brandId) {
    if (!confirm('Duplicate this brand?')) return;
    const formData = new FormData();
    formData.append('ajax_action', 'duplicate');
    formData.append('brand_id', brandId);
    formData.append('csrf_token', csrfToken);
    fetch('brands.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 800); } else showToast(data.message || 'Error duplicating brand', 'error'); })
        .catch(() => showToast('Network error', 'error'));
}

// Debounced server-side search
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(this._timeout);
    this._timeout = setTimeout(() => { if (this.value.length === 0 || this.value.length >= 2) this.closest('form').submit(); }, 600);
});

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';