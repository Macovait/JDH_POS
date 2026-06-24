<?php
/**
 * Categories Management - Jakababa POS System
 * Complete CRUD with hierarchy, product counts, and premium 1:1 card design
 * Using Tailwind CSS for modern styling
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('categories.manage') && !is_super_admin()) {
    enforce_permission('categories.manage');
}

$page_title = 'Categories';
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$current_branch_id = get_current_branch_id();
// ============================================================
// HANDLE AJAX REQUESTS FIRST (BEFORE ANY OUTPUT)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Prevent any PHP warnings/notices from breaking JSON
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        $pdo = get_db_connection();
        
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'load':
                $where_extra = 'c.deleted_at IS NULL';
                $params = [];

                if ($tenant_id) {
                    $where_extra .= ' AND c.tenant_id = ?';
                    $params[] = $tenant_id;
                }

                $sql = "
                    SELECT c.id, c.name, c.description, c.color, c.icon, c.status, c.sort_order,
                           c.created_at, c.updated_at, c.parent_id,
                           COUNT(DISTINCT p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL AND p.active = 1
                    WHERE {$where_extra}
                    GROUP BY c.id
                    ORDER BY c.sort_order ASC, c.name ASC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response = ['success' => true, 'categories' => $categories];
                break;

            case 'create':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $color = $_POST['color'] ?? '#FBBF24';
                $icon = $_POST['icon'] ?? 'tag';

                // Debug logging
                error_log("Creating category: name=$name, tenant_id=$tenant_id, user_id=$user_id");

                if (empty($name)) {
                    throw new Exception('Category name is required');
                }

                if (empty($tenant_id)) {
                    throw new Exception('Tenant ID is missing. Please log in again.');
                }

                $check = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND tenant_id = ? AND deleted_at IS NULL");
                $check->execute([$name, $tenant_id]);
                if ($check->fetch()) {
                    throw new Exception('Category name already exists');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO categories (tenant_id, name, description, parent_id, color, icon, status, sort_order, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', 0, NOW())
                ");
                $stmt->execute([$tenant_id, $name, $description, $parent_id, $color, $icon]);

                $response = ['success' => true, 'message' => 'Category created successfully'];
                break;

            case 'bulk_delete':
                $ids = $_POST['ids'] ?? '';
                if (empty($ids)) {
                    throw new Exception('No categories selected');
                }
                
                $ids = array_map('intval', explode(',', $ids));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ({$placeholders}) AND deleted_at IS NULL");
                $stmt->execute($ids);
                $productCount = $stmt->fetchColumn();
                
                if ($productCount > 0) {
                    throw new Exception("Cannot delete categories with products. $productCount products are assigned.");
                }
                
                $stmt = $pdo->prepare("UPDATE categories SET deleted_at = NOW(), deleted_by = ? WHERE id IN ({$placeholders})");
                $params = array_merge([$user_id], $ids);
                $stmt->execute($params);
                
                $response = ['success' => true, 'message' => count($ids) . ' categories deleted successfully'];
                break;

            case 'bulk_activate':
                $ids = $_POST['ids'] ?? '';
                if (empty($ids)) {
                    throw new Exception('No categories selected');
                }
                
                $ids = array_map('intval', explode(',', $ids));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $stmt = $pdo->prepare("UPDATE categories SET status = 'active', updated_at = NOW() WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
                
                $response = ['success' => true, 'message' => count($ids) . ' categories activated successfully'];
                break;

            case 'bulk_deactivate':
                $ids = $_POST['ids'] ?? '';
                if (empty($ids)) {
                    throw new Exception('No categories selected');
                }
                
                $ids = array_map('intval', explode(',', $ids));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $stmt = $pdo->prepare("UPDATE categories SET status = 'inactive', updated_at = NOW() WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
                
                $response = ['success' => true, 'message' => count($ids) . ' categories deactivated successfully'];
                break;

            default:
                throw new Exception('Invalid action');
        }

    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}

// ============================================================
// REGULAR PAGE LOAD - INCLUDE LAYOUT
// ============================================================
ob_start();
?>

<!-- Quick Create Modal -->
<div id="quickCreateModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-amber-500/15 border border-amber-500/30 flex items-center justify-center">
                    <i class="fas fa-folder-plus text-amber-400 text-xs"></i>
                </div>
                <h3 class="text-sm font-semibold text-white">Quick Create Category</h3>
            </div>
            <button onclick="closeQuickCreate()" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700 text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-xs text-slate-400 font-medium mb-1 block">Name <span class="text-red-400">*</span></label>
                <input type="text" id="qcName" placeholder="e.g. Beverages"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label class="text-xs text-slate-400 font-medium mb-1 block">Description</label>
                <input type="text" id="qcDescription" placeholder="Optional description"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-400 font-medium mb-1 block">Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" id="qcColor" value="#FBBF24"
                               class="w-8 h-8 rounded-lg border border-slate-700 bg-slate-900 cursor-pointer">
                        <span class="text-xs text-slate-500">Pick color</span>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-slate-400 font-medium mb-1 block">Icon</label>
                    <select id="qcIcon" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="tag">🏷 Tag</option>
                        <option value="box">📦 Box</option>
                        <option value="utensils">🍴 Utensils</option>
                        <option value="tshirt">👕 Clothing</option>
                        <option value="laptop">💻 Electronics</option>
                        <option value="pills">💊 Pharmacy</option>
                        <option value="car">🚗 Auto</option>
                        <option value="home">🏠 Home</option>
                        <option value="leaf">🌿 Fresh</option>
                        <option value="beer">🍺 Drinks</option>
                        <option value="bread-slice">🍞 Bakery</option>
                        <option value="fish">🐟 Seafood</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs text-slate-400 font-medium mb-1 block">Parent Category</label>
                <select id="parentSelect" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="">None (Top Level)</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button onclick="submitQuickCreate()"
                    class="flex-1 px-3 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-plus mr-1 text-xs"></i> Create
            </button>
            <button onclick="closeQuickCreate()"
                    class="px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-red-500/15 border border-red-500/30 flex items-center justify-center">
                <i class="fas fa-trash text-red-400 text-sm"></i>
            </div>
            <h3 class="text-sm font-semibold text-white">Delete Category</h3>
        </div>
        <p class="text-xs text-slate-400 mb-1">Are you sure you want to delete</p>
        <p id="deleteModalName" class="text-sm font-semibold text-amber-400 mb-3"></p>
        <p class="text-xs text-slate-500 mb-4">Categories with assigned products cannot be deleted.</p>
        <div class="flex gap-2">
            <button onclick="confirmDelete()"
                    class="flex-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash mr-1"></i> Delete
            </button>
            <button onclick="closeDeleteModal()"
                    class="flex-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-700 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-layer-group text-amber-400"></i> Product Categories
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <span id="totalCategories" class="text-slate-300 font-medium">0</span> categories &middot;
            <span id="totalProducts" class="text-amber-400 font-medium">0</span> products
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2 shrink-0">
        <button onclick="openQuickCreate()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-bolt text-xs"></i> Quick Add
        </button>
        <a href="category_form.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-xs font-semibold hover:bg-amber-600 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Category
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-layer-group text-amber-400 text-xs"></i>
        </div>
        <div>
            <div class="text-xl font-bold text-amber-400" id="totalCategoriesStat">0</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
        </div>
        <div>
            <div class="text-xl font-bold text-emerald-400" id="activeCategoriesStat">0</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Active</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-ban text-red-400 text-xs"></i>
        </div>
        <div>
            <div class="text-xl font-bold text-red-400" id="inactiveCategoriesStat">0</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Inactive</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-box text-blue-400 text-xs"></i>
        </div>
        <div>
            <div class="text-xl font-bold text-blue-400" id="totalProductsStat">0</div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Products</div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div class="flex flex-wrap gap-1.5">
            <button onclick="setStatusFilter('all')" id="pill-all"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-colors bg-amber-500/20 text-amber-400 border-amber-500/30">
                All <span class="opacity-70" id="pill-all-count">(0)</span>
            </button>
            <button onclick="setStatusFilter('active')" id="pill-active"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-colors bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                Active <span class="opacity-70" id="pill-active-count">(0)</span>
            </button>
            <button onclick="setStatusFilter('inactive')" id="pill-inactive"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-colors bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700">
                Inactive <span class="opacity-70" id="pill-inactive-count">(0)</span>
            </button>
        </div>
        <div class="flex items-center gap-2">
            <div class="relative">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" id="searchInput" placeholder="Search categories…"
                       class="pl-7 pr-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 w-48"
                       oninput="filterCategories(this.value)" autocomplete="off">
            </div>
            <select id="sortSelect" onchange="setSortOrder(this.value)"
                    class="px-2 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
                <option value="products_desc">Most Products</option>
                <option value="products_asc">Least Products</option>
                <option value="newest">Newest</option>
            </select>
            <!-- View toggle -->
            <div class="flex items-center bg-slate-900 border border-slate-700 rounded-lg overflow-hidden">
                <button onclick="setView('table')" id="viewTable"
                        class="w-7 h-7 flex items-center justify-center text-amber-400 bg-slate-700 transition-colors" title="Table view">
                    <i class="fas fa-list text-xs"></i>
                </button>
                <button onclick="setView('grid')" id="viewGrid"
                        class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white transition-colors" title="Grid view">
                    <i class="fas fa-grid-2 text-xs"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="hidden mb-4 px-3 py-2.5 bg-slate-800/60 border border-amber-500/30 rounded-xl">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" class="w-3.5 h-3.5 rounded accent-amber-500">
            <span class="text-sm text-slate-400"><span id="selectedCountText" class="text-white font-semibold">0</span> selected</span>
            <button onclick="clearSelection()" class="text-xs text-slate-500 hover:text-slate-300 transition-colors">Clear</button>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="bulkActivate()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium hover:bg-emerald-500/20 transition-colors">
                <i class="fas fa-check text-xs"></i> Activate
            </button>
            <button onclick="bulkDeactivate()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-ban text-xs"></i> Deactivate
            </button>
            <button onclick="bulkDelete()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-colors">
                <i class="fas fa-trash text-xs"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Table View -->
<div id="tableView" class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 w-8">
                        <input type="checkbox" id="selectAllTop" onchange="toggleSelectAll()" class="w-3.5 h-3.5 rounded accent-amber-500">
                    </th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-12">Icon</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:table-cell">Description</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Products</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40" id="categoriesTableBody">
                <tr>
                    <td colspan="7" class="px-4 py-14 text-center">
                        <div class="w-10 h-10 border-2 border-amber-500/30 border-t-amber-400 rounded-full animate-spin mx-auto mb-3"></div>
                        <p class="text-slate-500 text-sm">Loading categories…</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <!-- Table Footer -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium" id="showingStart">0</span>–<span class="text-slate-300 font-medium" id="showingEnd">0</span>
            of <span class="text-slate-300 font-medium" id="totalRecords">0</span>
        </p>
        <div class="flex items-center gap-1" id="paginationControls">
            <button onclick="changePage(currentPage - 1)" id="prevPageBtn"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors" disabled>
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
            <div class="flex gap-1" id="pageNumbers"></div>
            <button onclick="changePage(currentPage + 1)" id="nextPageBtn"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors" disabled>
                <i class="fas fa-chevron-right text-xs"></i>
            </button>
            <select onchange="changePerPage(this.value)"
                    class="ml-2 px-2 py-1 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>
</div>

<!-- Grid View (hidden by default) -->
<div id="gridView" class="hidden">
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3" id="categoriesGrid"></div>
    <div class="flex items-center justify-between mt-4">
        <p class="text-xs text-slate-500">Showing <span class="text-slate-300 font-medium" id="gridShowingStart">0</span>–<span class="text-slate-300 font-medium" id="gridShowingEnd">0</span> of <span class="text-slate-300 font-medium" id="gridTotalRecords">0</span></p>
        <div class="flex items-center gap-1" id="gridPaginationControls"></div>
    </div>
</div>

<script>
let categories = [];
let filteredCategories = [];
let selectedCategories = new Set();
let currentPage = 1;
let perPage = 25;
let totalPages = 1;
let currentStatusFilter = 'all';
let currentSort = 'name_asc';
let currentView = 'table';
let pendingDeleteId = null;

// ── AJAX helper (BOM-safe) ───────────────────────────────────
async function ajaxPost(body) {
    const r = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body
    });
    const text = await r.text();
    return JSON.parse(text.replace(/^\uFEFF/, ''));
}

// ── LOAD ────────────────────────────────────────────────────
async function loadCategories() {
    try {
        const data = await ajaxPost('action=load');
        if (data.success) {
            categories = data.categories || [];
            applyFilters();
            updateStats();
            updateParentSelect();
        } else {
            showToast(data.message || 'Failed to load', 'error');
            renderEmpty('Failed to load categories. Please refresh.');
        }
    } catch(e) {
        console.error(e);
        showToast('Error loading categories', 'error');
        renderEmpty('Error loading. Please refresh.');
    }
}

function renderEmpty(msg) {
    document.getElementById('categoriesTableBody').innerHTML =
        `<tr><td colspan="7" class="px-4 py-14 text-center"><i class="fas fa-exclamation-circle text-3xl text-slate-700 block mb-3"></i><p class="text-slate-500 text-sm">${msg}</p></td></tr>`;
}

// ── FILTERS & SORT ──────────────────────────────────────────
function applyFilters() {
    const search = (document.getElementById('searchInput').value || '').toLowerCase().trim();
    filteredCategories = categories.filter(c => {
        const matchStatus = currentStatusFilter === 'all' || c.status === currentStatusFilter;
        const matchSearch = !search || c.name.toLowerCase().includes(search) ||
            (c.description && c.description.toLowerCase().includes(search));
        return matchStatus && matchSearch;
    });
    sortCategories();
    currentPage = 1;
    render();
}

function sortCategories() {
    filteredCategories.sort((a, b) => {
        switch(currentSort) {
            case 'name_asc':     return a.name.localeCompare(b.name);
            case 'name_desc':    return b.name.localeCompare(a.name);
            case 'products_desc':return (b.product_count||0) - (a.product_count||0);
            case 'products_asc': return (a.product_count||0) - (b.product_count||0);
            case 'newest':       return new Date(b.created_at||0) - new Date(a.created_at||0);
            default:             return 0;
        }
    });
}

function setStatusFilter(status) {
    currentStatusFilter = status;
    ['all','active','inactive'].forEach(s => {
        const btn = document.getElementById('pill-' + s);
        if (s === status) {
            btn.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-colors bg-amber-500/20 text-amber-400 border-amber-500/30';
        } else {
            btn.className = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-colors bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700';
        }
    });
    applyFilters();
}

function filterCategories(q) { applyFilters(); }

function setSortOrder(val) { currentSort = val; applyFilters(); }

// ── VIEW TOGGLE ─────────────────────────────────────────────
function setView(view) {
    currentView = view;
    document.getElementById('tableView').classList.toggle('hidden', view !== 'table');
    document.getElementById('gridView').classList.toggle('hidden', view !== 'grid');
    document.getElementById('viewTable').className = 'w-7 h-7 flex items-center justify-center transition-colors ' + (view === 'table' ? 'text-amber-400 bg-slate-700' : 'text-slate-400 hover:text-white');
    document.getElementById('viewGrid').className  = 'w-7 h-7 flex items-center justify-center transition-colors ' + (view === 'grid'  ? 'text-amber-400 bg-slate-700' : 'text-slate-400 hover:text-white');
    render();
}

// ── RENDER ──────────────────────────────────────────────────
function render() {
    if (currentView === 'table') renderTable();
    else renderGrid();
}

function renderTable() {
    const tbody = document.getElementById('categoriesTableBody');
    if (filteredCategories.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-14 text-center">
            <i class="fas fa-folder-open text-4xl text-slate-700 block mb-3"></i>
            <p class="text-slate-500 text-sm mb-3">No categories found</p>
            <button onclick="openQuickCreate()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                <i class="fas fa-plus text-xs"></i> Add Category
            </button></td></tr>`;
        updatePagination(0, 0, 0);
        return;
    }
    const start = (currentPage - 1) * perPage;
    const end   = Math.min(start + perPage, filteredCategories.length);
    const page  = filteredCategories.slice(start, end);

    tbody.innerHTML = page.map(c => {
        const color  = c.color || '#FBBF24';
        const prefix = c.parent_id ? '<span class="text-slate-600 mr-1">—</span>' : '';
        const sel    = selectedCategories.has(parseInt(c.id));
        return `
        <tr class="hover:bg-slate-700/20 transition-colors ${sel ? 'bg-amber-900/10' : ''}" data-id="${c.id}">
            <td class="px-3 py-2.5">
                <input type="checkbox" class="cat-checkbox w-3.5 h-3.5 rounded accent-amber-500"
                       value="${c.id}" ${sel ? 'checked' : ''} onchange="toggleSelection(event,${c.id})">
            </td>
            <td class="px-3 py-2.5">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                     style="background-color:${color}20;color:${color};">
                    <i class="fas fa-${c.icon||'tag'} text-xs"></i>
                </div>
            </td>
            <td class="px-3 py-2.5">
                <div class="text-sm font-medium text-white hover:text-amber-400 cursor-pointer transition-colors"
                     onclick="editCategory(${c.id})">${prefix}${escapeHtml(c.name)}</div>
                <div class="text-xs text-slate-600 mt-0.5">${formatDate(c.created_at)}</div>
            </td>
            <td class="px-3 py-2.5 hidden md:table-cell">
                <div class="text-xs text-slate-400 max-w-[200px] truncate">
                    ${c.description ? escapeHtml(c.description) : '<span class="text-slate-600 italic">—</span>'}
                </div>
            </td>
            <td class="px-3 py-2.5">
                <button onclick="toggleStatus(${c.id},'${c.status}')"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border cursor-pointer transition-colors
                            ${c.status === 'active'
                                ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/25'
                                : 'bg-slate-500/15 text-slate-400 border-slate-500/30 hover:bg-slate-500/25'}">
                    <i class="fas ${c.status === 'active' ? 'fa-check' : 'fa-ban'} text-[8px]"></i>
                    ${c.status === 'active' ? 'Active' : 'Inactive'}
                </button>
            </td>
            <td class="px-3 py-2.5 text-right">
                <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                    <i class="fas fa-box text-slate-600 text-[10px]"></i> ${c.product_count || 0}
                </span>
            </td>
            <td class="px-3 py-2.5">
                <div class="flex items-center justify-center gap-1">
                    <a href="category_form.php?id=${c.id}"
                       class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-amber-400 hover:bg-amber-500/10 transition-colors" title="Edit">
                        <i class="fas fa-pen text-[10px]"></i>
                    </a>
                    <button onclick="openDeleteModal(${c.id},'${escapeHtml(c.name).replace(/'/g,"\\\'")}')"
                            class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Delete">
                        <i class="fas fa-trash text-[10px]"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    updatePagination(filteredCategories.length, start + 1, end);
    syncSelectAll();
}

function renderGrid() {
    const grid = document.getElementById('categoriesGrid');
    if (filteredCategories.length === 0) {
        grid.innerHTML = `<div class="col-span-full text-center py-14">
            <i class="fas fa-folder-open text-4xl text-slate-700 block mb-3"></i>
            <p class="text-slate-500 text-sm">No categories found</p></div>`;
        document.getElementById('gridTotalRecords').textContent = 0;
        return;
    }
    const start = (currentPage - 1) * perPage;
    const end   = Math.min(start + perPage, filteredCategories.length);
    const page  = filteredCategories.slice(start, end);

    grid.innerHTML = page.map(c => {
        const color = c.color || '#FBBF24';
        return `
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 hover:border-amber-500/30 transition-all cursor-pointer group"
             onclick="editCategory(${c.id})">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background-color:${color}20;color:${color};">
                    <i class="fas fa-${c.icon||'tag'}"></i>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                    ${c.status === 'active' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-slate-500/15 text-slate-400 border border-slate-500/30'}">
                    ${c.status === 'active' ? 'Active' : 'Inactive'}
                </span>
            </div>
            <div class="text-sm font-semibold text-white group-hover:text-amber-400 transition-colors truncate">${escapeHtml(c.name)}</div>
            <div class="text-xs text-slate-500 mt-0.5 truncate">${c.description ? escapeHtml(c.description) : 'No description'}</div>
            <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-700/40">
                <span class="text-xs text-slate-500"><i class="fas fa-box text-slate-600 mr-1 text-[10px]"></i>${c.product_count||0} products</span>
                <a href="category_form.php?id=${c.id}" onclick="event.stopPropagation()"
                   class="text-xs text-amber-400 hover:text-amber-300 transition-colors">Edit →</a>
            </div>
        </div>`;
    }).join('');

    document.getElementById('gridShowingStart').textContent = start + 1;
    document.getElementById('gridShowingEnd').textContent = end;
    document.getElementById('gridTotalRecords').textContent = filteredCategories.length;
}

// ── STATS ────────────────────────────────────────────────────
function updateStats() {
    const total    = categories.length;
    const active   = categories.filter(c => c.status === 'active').length;
    const inactive = total - active;
    const prods    = categories.reduce((s, c) => s + (parseInt(c.product_count)||0), 0);
    document.getElementById('totalCategories').textContent    = total;
    document.getElementById('totalCategoriesStat').textContent= total;
    document.getElementById('activeCategoriesStat').textContent= active;
    document.getElementById('inactiveCategoriesStat').textContent= inactive;
    document.getElementById('totalProductsStat').textContent  = prods;
    document.getElementById('totalProducts').textContent      = prods;
    document.getElementById('pill-all-count').textContent     = `(${total})`;
    document.getElementById('pill-active-count').textContent  = `(${active})`;
    document.getElementById('pill-inactive-count').textContent= `(${inactive})`;
}

// ── PAGINATION ───────────────────────────────────────────────
function updatePagination(total, start, end) {
    document.getElementById('showingStart').textContent = total > 0 ? start : 0;
    document.getElementById('showingEnd').textContent   = end;
    document.getElementById('totalRecords').textContent = total;
    totalPages = Math.ceil(total / perPage) || 1;
    const nums = document.getElementById('pageNumbers');
    nums.innerHTML = '';
    const lo = Math.max(1, currentPage - 2), hi = Math.min(totalPages, currentPage + 2);
    for (let i = lo; i <= hi; i++) {
        const b = document.createElement('button');
        b.className = `w-7 h-7 flex items-center justify-center rounded-lg text-xs border font-medium transition-colors ${i === currentPage ? 'bg-amber-500/20 border-amber-500/40 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700'}`;
        b.textContent = i;
        b.onclick = () => changePage(i);
        nums.appendChild(b);
    }
    document.getElementById('prevPageBtn').disabled = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;
}

function changePage(p) {
    if (p < 1 || p > totalPages) return;
    currentPage = p;
    render();
}

function changePerPage(n) { perPage = parseInt(n); currentPage = 1; render(); }

// ── SELECTION ────────────────────────────────────────────────
function toggleSelection(e, id) {
    e.stopPropagation();
    id = parseInt(id);
    selectedCategories.has(id) ? selectedCategories.delete(id) : selectedCategories.add(id);
    updateBulkBar();
}

function toggleSelectAll() {
    const cb = document.getElementById('selectAllCheckbox');
    const isChecked = cb ? cb.checked : false;
    ['selectAllCheckbox','selectAllTop'].forEach(id => {
        const el = document.getElementById(id); if(el) el.checked = isChecked;
    });
    if (isChecked) {
        filteredCategories.slice((currentPage-1)*perPage, currentPage*perPage)
            .forEach(c => selectedCategories.add(parseInt(c.id)));
    } else {
        selectedCategories.clear();
    }
    updateBulkBar();
    renderTable();
}

function clearSelection() {
    selectedCategories.clear();
    ['selectAllCheckbox','selectAllTop'].forEach(id => { const el = document.getElementById(id); if(el) el.checked = false; });
    updateBulkBar();
    render();
}

function syncSelectAll() {
    const page = filteredCategories.slice((currentPage-1)*perPage, currentPage*perPage);
    const allSel = page.length > 0 && page.every(c => selectedCategories.has(parseInt(c.id)));
    ['selectAllCheckbox','selectAllTop'].forEach(id => { const el = document.getElementById(id); if(el) el.checked = allSel; });
}

function updateBulkBar() {
    const bar = document.getElementById('bulkActionsBar');
    document.getElementById('selectedCountText').textContent = selectedCategories.size;
    bar.classList.toggle('hidden', selectedCategories.size === 0);
}

// ── STATUS TOGGLE ────────────────────────────────────────────
async function toggleStatus(id, currentStatus) {
    const newAction = currentStatus === 'active' ? 'bulk_deactivate' : 'bulk_activate';
    try {
        const data = await ajaxPost(`action=${newAction}&ids=${id}`);
        if (data.success) { showToast(data.message, 'success'); loadCategories(); }
        else showToast(data.message, 'error');
    } catch(e) { showToast('Error updating status', 'error'); }
}

// ── DELETE MODAL ─────────────────────────────────────────────
function openDeleteModal(id, name) {
    pendingDeleteId = id;
    document.getElementById('deleteModalName').textContent = name;
    const m = document.getElementById('deleteModal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function closeDeleteModal() {
    pendingDeleteId = null;
    const m = document.getElementById('deleteModal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
async function confirmDelete() {
    if (!pendingDeleteId) return;
    closeDeleteModal();
    try {
        const data = await ajaxPost(`action=bulk_delete&ids=${pendingDeleteId}`);
        if (data.success) { showToast(data.message, 'success'); loadCategories(); }
        else showToast(data.message, 'error');
    } catch(e) { showToast('Error deleting category', 'error'); }
}

function editCategory(id) { window.location.href = `category_form.php?id=${id}`; }

// ── BULK OPERATIONS ──────────────────────────────────────────
async function bulkActivate() {
    if (!selectedCategories.size) return;
    try {
        const data = await ajaxPost(`action=bulk_activate&ids=${[...selectedCategories].join(',')}`);
        if (data.success) { showToast(data.message,'success'); clearSelection(); loadCategories(); }
        else showToast(data.message,'error');
    } catch(e) { showToast('Error','error'); }
}
async function bulkDeactivate() {
    if (!selectedCategories.size) return;
    try {
        const data = await ajaxPost(`action=bulk_deactivate&ids=${[...selectedCategories].join(',')}`);
        if (data.success) { showToast(data.message,'success'); clearSelection(); loadCategories(); }
        else showToast(data.message,'error');
    } catch(e) { showToast('Error','error'); }
}
async function bulkDelete() {
    if (!selectedCategories.size) return;
    if (!confirm(`Delete ${selectedCategories.size} selected categories? This cannot be undone.`)) return;
    try {
        const data = await ajaxPost(`action=bulk_delete&ids=${[...selectedCategories].join(',')}`);
        if (data.success) { showToast(data.message,'success'); clearSelection(); loadCategories(); }
        else showToast(data.message,'error');
    } catch(e) { showToast('Error','error'); }
}

// ── QUICK CREATE MODAL ────────────────────────────────────────
function openQuickCreate() {
    const m = document.getElementById('quickCreateModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    setTimeout(() => document.getElementById('qcName').focus(), 50);
}
function closeQuickCreate() {
    const m = document.getElementById('quickCreateModal');
    m.classList.add('hidden'); m.classList.remove('flex');
    document.getElementById('qcName').value = '';
    document.getElementById('qcDescription').value = '';
}
async function submitQuickCreate() {
    const name = document.getElementById('qcName').value.trim();
    if (!name) { showToast('Category name is required','warning'); document.getElementById('qcName').focus(); return; }
    const color = document.getElementById('qcColor').value;
    const icon  = document.getElementById('qcIcon').value;
    const desc  = document.getElementById('qcDescription').value.trim();
    const pid   = document.getElementById('parentSelect').value;
    try {
        const data = await ajaxPost(`action=create&name=${encodeURIComponent(name)}&description=${encodeURIComponent(desc)}&color=${encodeURIComponent(color)}&icon=${encodeURIComponent(icon)}&parent_id=${encodeURIComponent(pid)}`);
        if (data.success) { showToast(data.message,'success'); closeQuickCreate(); loadCategories(); }
        else showToast(data.message,'error');
    } catch(e) { showToast('Error creating category','error'); }
}

function updateParentSelect() {
    const sel = document.getElementById('parentSelect');
    if (!sel) return;
    sel.innerHTML = '<option value="">None (Top Level)</option>' +
        categories.filter(c => c.status === 'active')
            .map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

// ── TOAST ─────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const bgColor = type==='success'?'bg-emerald-500/90':type==='error'?'bg-red-500/90':type==='warning'?'bg-amber-500/90':'bg-blue-500/90';
    const icon    = type==='success'?'fa-check-circle':type==='error'?'fa-exclamation-circle':type==='warning'?'fa-exclamation-triangle':'fa-info-circle';
    const t = document.createElement('div');
    t.className = `flex items-center gap-3 px-4 py-2.5 rounded-xl shadow-xl text-sm text-white ${bgColor}`;
    t.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHtml(msg)}</span>`;
    const container = document.getElementById('toastContainer') || document.body;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

// ── UTILITIES ─────────────────────────────────────────────────
function escapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
}
function formatDate(s) {
    if (!s) return '';
    return new Date(s).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'});
}

// ── KEYBOARD SHORTCUTS ────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.target.matches('input,textarea,select')) return;
    if (e.altKey && e.key === 'a') { e.preventDefault(); openQuickCreate(); }
    if (e.key === 'Escape') { closeQuickCreate(); closeDeleteModal(); }
});

// ── MODAL BACKDROP CLOSE ──────────────────────────────────────
['quickCreateModal','deleteModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) {
        if (e.target === this) { closeQuickCreate(); closeDeleteModal(); }
    });
});

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadCategories);
</script>

<div id="toastContainer" class="fixed bottom-4 right-4 z-[2000] flex flex-col gap-2 pointer-events-none"></div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>