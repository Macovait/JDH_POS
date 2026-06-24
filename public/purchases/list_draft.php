<?php
/**
 * List Draft Sales Page for Jakababa POS
 * Display and manage all draft sales with filtering, actions, and advanced features
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$tenant_id = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

$page_title = 'Draft Sales';
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? get_current_branch_id());

// Initialize variables
$error = '';
$success = '';
$debug_info = [];

// Handle success/error messages from redirects
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted':
            $success = 'Draft deleted successfully';
            break;
        case 'converted':
            $success = 'Draft converted to sale successfully';
            break;
        case 'updated':
            $success = 'Draft updated successfully';
            break;
        case 'created':
            $success = 'Draft created successfully';
            break;
        case 'error':
            $error = 'An error occurred. Please try again.';
            break;
    }
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'updated_desc';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$customer_filter = $_GET['customer'] ?? '';

// Fetch draft sales with pagination
$drafts = [];
$total_drafts = 0;
$customers_list = [];

try {
    $pdo = get_db_connection();

    // Get unique customers for filter dropdown
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        WHERE s.branch_id = ? AND s.tenant_id = ? AND s.status = 'draft' AND s.customer_id IS NOT NULL
        ORDER BY c.name
    ");
    $stmt->execute([$user_branch, $tenant_id]);
    $customers_list = $stmt->fetchAll();

    // Build base query
    $count_query = "SELECT COUNT(DISTINCT s.id) as count FROM sales s WHERE s.branch_id = ? AND s.tenant_id = ? AND s.status = 'draft'";
    $data_query = "
        SELECT s.*, 
               c.name as customer_name, 
               c.phone as customer_phone,
               c.email as customer_email,
                (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id AND tenant_id = ?) as item_count,
                (SELECT COALESCE(SUM(quantity), 0) FROM sale_items WHERE sale_id = s.id AND tenant_id = ?) as total_items,
               u.name as created_by_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.branch_id = ? AND s.tenant_id = ? AND s.status = 'draft'";

    $params = [$user_branch, $tenant_id, $tenant_id, $tenant_id];
    $data_params = [$user_branch, $tenant_id, $tenant_id, $tenant_id];

    // Add search filter
    if (!empty($search)) {
        $count_query .= " AND (CAST(s.id AS CHAR) LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.notes LIKE ?)";
        $data_query .= " AND (CAST(s.id AS CHAR) LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.notes LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        $data_params = array_merge($data_params, [$search_term, $search_term, $search_term, $search_term]);
    }

    // Add date filters
    if (!empty($date_from)) {
        $count_query .= " AND DATE(s.created_at) >= ?";
        $data_query .= " AND DATE(s.created_at) >= ?";
        $params[] = $date_from;
        $data_params[] = $date_from;
    }

    if (!empty($date_to)) {
        $count_query .= " AND DATE(s.created_at) <= ?";
        $data_query .= " AND DATE(s.created_at) <= ?";
        $params[] = $date_to;
        $data_params[] = $date_to;
    }

    // Add customer filter
    if (!empty($customer_filter)) {
        $count_query .= " AND s.customer_id = ?";
        $data_query .= " AND s.customer_id = ?";
        $params[] = $customer_filter;
        $data_params[] = $customer_filter;
    }

    // Add sorting
    switch ($sort) {
        case 'updated_asc':
            $data_query .= " ORDER BY s.updated_at ASC";
            break;
        case 'created_desc':
            $data_query .= " ORDER BY s.created_at DESC";
            break;
        case 'created_asc':
            $data_query .= " ORDER BY s.created_at ASC";
            break;
        case 'total_desc':
            $data_query .= " ORDER BY s.total DESC";
            break;
        case 'total_asc':
            $data_query .= " ORDER BY s.total ASC";
            break;
        case 'items_desc':
            $data_query .= " ORDER BY item_count DESC";
            break;
        case 'items_asc':
            $data_query .= " ORDER BY item_count ASC";
            break;
        default:
            $data_query .= " ORDER BY s.updated_at DESC";
    }

    // Get total count
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_drafts = $stmt->fetch()['count'];

    // Get drafts with pagination
    $data_query .= " LIMIT ? OFFSET ?";
    $data_params[] = $limit;
    $data_params[] = $offset;

    $debug_info['query'] = $data_query;
    $debug_info['params'] = $data_params;

    $stmt = $pdo->prepare($data_query);
    $stmt->execute($data_params);
    $drafts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching drafts: " . $e->getMessage());
    $error = "Failed to load draft data. Please try again.";
    $debug_info['error'] = $e->getMessage();
}

$total_pages = ceil($total_drafts / $limit);

// Calculate summary statistics
$summary = [
    'total_drafts' => $total_drafts,
    'total_value' => 0,
    'avg_value' => 0,
    'total_items' => 0,
    'avg_items' => 0,
    'oldest_draft' => null,
    'newest_draft' => null
];

if (!empty($drafts)) {
    foreach ($drafts as $draft) {
        $summary['total_value'] += (float) ($draft['total'] ?? 0);
        $summary['total_items'] += (int) ($draft['total_items'] ?? 0);

        $created = strtotime($draft['created_at']);
        if ($summary['oldest_draft'] === null || $created < $summary['oldest_draft']) {
            $summary['oldest_draft'] = $created;
        }
        if ($summary['newest_draft'] === null || $created > $summary['newest_draft']) {
            $summary['newest_draft'] = $created;
        }
    }

    $summary['avg_value'] = $total_drafts > 0 ? $summary['total_value'] / $total_drafts : 0;
    $summary['avg_items'] = $total_drafts > 0 ? round($summary['total_items'] / $total_drafts, 1) : 0;
}

// Determine if filters are active
$has_filters = !empty($search) || !empty($date_from) || !empty($date_to) || !empty($customer_filter) || $sort !== 'updated_desc';

// Currency symbol
$currency_symbol = get_tenant_currency();
ob_start();
?>

<style>
    .page-enter {
        animation: fadeIn 0.5s ease-in-out;
    }

    /* Card hover accent */
    .card-accent:hover {
        background: rgba(31, 41, 55, 0.8);
        border-color: rgba(251, 191, 36, 0.3);
    }

    /* Draft card */
    .draft-card {
        position: relative;
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 1.5rem;
        padding: 1.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }

    .draft-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #FBBF24, #F59E0B);
        transform: translateX(-100%);
        transition: transform 0.5s ease;
    }

    .draft-card:hover {
        transform: translateY(-4px);
        border-color: rgba(251, 191, 36, 0.3);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
    }

    .draft-card:hover::before {
        transform: translateX(0);
    }

    /* Draft badge */
    .draft-badge {
        background: rgba(251, 191, 36, 0.15);
        color: #FBBF24;
        border: 1px solid rgba(251, 191, 36, 0.3);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Stat card */
    .stat-card {
        background: #1F2937;
        border: 1px solid #374151;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        border-color: #FBBF24;
        transform: translateY(-2px);
    }

    /* Gradient text */
    .gradient-text {
        background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Form input */
    .form-input {
        background: #111827;
        border: 1px solid #374151;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        color: white;
        transition: all 0.2s ease;
    }

    .form-input:focus {
        border-color: #FBBF24;
        outline: none;
        box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
    }

    .form-input::placeholder {
        color: #6B7280;
    }

    /* Action button */
    .action-btn {
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    /* Debug panel */
    .debug-panel {
        position: fixed;
        top: 1rem;
        right: 1rem;
        max-width: 400px;
        max-height: 80vh;
        overflow-y: auto;
        background: rgba(31, 41, 55, 0.95);
        backdrop-filter: blur(8px);
        border: 1px solid #FBBF24;
        border-radius: 1rem;
        padding: 1rem;
        font-size: 0.75rem;
        z-index: 9999;
        display: none;
        color: white;
    }

    .debug-panel.visible {
        display: block;
    }

    .debug-toggle {
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: #FBBF24;
        color: #000;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: bold;
        cursor: pointer;
        z-index: 10000;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.5rem;
        background: rgba(251, 191, 36, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Line clamp */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<div class="fade-in page-enter">

    <!-- Messages -->
    <?php if ($error): ?>
        <div class="mb-4 bg-[#EF4444]/10 border border-[#EF4444] rounded-xl p-4 ">
            <div class="flex items-center gap-3 text-[#EF4444]">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-4 bg-[#10B981]/10 border border-[#10B981] rounded-xl p-4 ">
            <div class="flex items-center gap-3 text-[#10B981]">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 ">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold flex items-center gap-2 text-white">
                <span class="gradient-text">Draft Sales</span>
                <?php if ($total_drafts > 0): ?>
                    <span class="text-sm bg-[#FBBF24]/20 text-[#FBBF24] px-3 py-1 rounded-full">
                        <?php echo $total_drafts; ?> total
                    </span>
                <?php endif; ?>
            </h1>
            <p class="text-[#9CA3AF] text-sm mt-1">Manage and continue your saved draft transactions</p>
        </div>
        <div class="flex gap-3">
            <a href="add_draft.php"
                class="bg-[#FBBF24] hover:bg-[#F59E0B] text-[#1E3A8A] px-4 py-2 rounded-xl transition flex items-center gap-2 text-sm font-medium group">
                <i class="fas fa-plus group-hover:rotate-90 transition-transform"></i>
                New Draft
            </a>
            <button onclick="exportDrafts()"
                class="bg-[#3B82F6] hover:bg-[#2563EB] text-white px-4 py-2 rounded-xl transition flex items-center gap-2 text-sm font-medium">
                <i class="fas fa-download"></i>
                Export
            </button>
            <button onclick="toggleFilters()"
                class="bg-[#1F2937] hover:bg-[#374151] text-white px-4 py-2 rounded-xl transition flex items-center gap-2 text-sm font-medium border border-[#374151]">
                <i class="fas fa-sliders-h"></i>
                Filters
                <?php if ($has_filters): ?>
                    <span class="w-2 h-2 bg-[#FBBF24] rounded-full"></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 ">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[#FBBF24] text-sm">Total Drafts</span>
                <i class="fas fa-file-pen text-[#FBBF24]"></i>
            </div>
            <p class="text-2xl font-bold text-white"><?php echo $summary['total_drafts']; ?></p>
            <p class="text-xs text-[#9CA3AF] mt-1">Awaiting completion</p>
        </div>

        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[#8B5CF6] text-sm">Total Value</span>
                <i class="fas fa-coins text-[#8B5CF6]"></i>
            </div>
            <p class="text-2xl font-bold text-white"><?php echo $currency_symbol; ?>
                <?php echo number_format($summary['total_value'], 0); ?></p>
            <p class="text-xs text-[#9CA3AF] mt-1">Avg: <?php echo $currency_symbol; ?>
                <?php echo number_format($summary['avg_value'], 0); ?></p>
        </div>

        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[#3B82F6] text-sm">Total Items</span>
                <i class="fas fa-cubes text-[#3B82F6]"></i>
            </div>
            <p class="text-2xl font-bold text-white"><?php echo $summary['total_items']; ?></p>
            <p class="text-xs text-[#9CA3AF] mt-1">Avg: <?php echo $summary['avg_items']; ?> per draft</p>
        </div>

        <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[#EC4899] text-sm">Date Range</span>
                <i class="fas fa-calendar text-[#EC4899]"></i>
            </div>
            <p class="text-sm font-semibold text-white">
                <?php echo $summary['oldest_draft'] ? date('d M', $summary['oldest_draft']) : '-'; ?> -
                <?php echo $summary['newest_draft'] ? date('d M', $summary['newest_draft']) : '-'; ?>
            </p>
            <p class="text-xs text-[#9CA3AF] mt-1">Last 30 days</p>
        </div>
    </div>

    <!-- Advanced Filters Panel -->
    <div id="filtersPanel"
        class="bg-[#1F2937] rounded-xl p-5 border border-[#374151] mb-6  <?php echo $has_filters ? '' : 'hidden'; ?>">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm text-[#9CA3AF] mb-1">Search</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF]"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="ID, customer, phone..." class="form-input w-full pl-10">
                    </div>
                </div>

                <!-- Customer Filter -->
                <div>
                    <label class="block text-sm text-[#9CA3AF] mb-1">Customer</label>
                    <select name="customer" class="form-input w-full">
                        <option value="">All Customers</option>
                        <?php foreach ($customers_list as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm text-[#9CA3AF] mb-1">From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-input w-full">
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm text-[#9CA3AF] mb-1">To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-input w-full">
                </div>
            </div>

            <div class="flex justify-between items-center">
                <div class="flex gap-2">
                    <select name="sort" class="form-input">
                        <option value="updated_desc" <?php echo $sort === 'updated_desc' ? 'selected' : ''; ?>>
                            Recently Updated</option>
                        <option value="updated_asc" <?php echo $sort === 'updated_asc' ? 'selected' : ''; ?>>Oldest
                            Updated</option>
                        <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>Newest
                            First</option>
                        <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>Oldest
                            First</option>
                        <option value="total_desc" <?php echo $sort === 'total_desc' ? 'selected' : ''; ?>>Highest
                            Value</option>
                        <option value="total_asc" <?php echo $sort === 'total_asc' ? 'selected' : ''; ?>>Lowest Value
                        </option>
                        <option value="items_desc" <?php echo $sort === 'items_desc' ? 'selected' : ''; ?>>Most Items
                        </option>
                        <option value="items_asc" <?php echo $sort === 'items_asc' ? 'selected' : ''; ?>>Fewest Items
                        </option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="bg-[#FBBF24] hover:bg-[#F59E0B] text-[#1E3A8A] px-6 py-2 rounded-lg transition font-medium">
                        Apply Filters
                    </button>

                    <?php if ($has_filters): ?>
                        <a href="list_draft.php"
                            class="bg-[#EF4444] hover:bg-[#DC2626] text-white px-6 py-2 rounded-lg transition font-medium">
                            Clear All
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Active Filters Display -->
    <?php if ($has_filters): ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-sm text-[#9CA3AF]">Active filters:</span>
            <?php if (!empty($search)): ?>
                <span class="bg-[#FBBF24]/20 text-[#FBBF24] text-xs px-3 py-1 rounded-full flex items-center gap-1">
                    <i class="fas fa-search"></i>
                    "<?php echo htmlspecialchars($search); ?>"
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>"
                        class="ml-1 hover:text-white">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
            <?php endif; ?>
            <?php if (!empty($customer_filter)): ?>
                <span class="bg-[#FBBF24]/20 text-[#FBBF24] text-xs px-3 py-1 rounded-full flex items-center gap-1">
                    <i class="fas fa-user"></i>
                    Customer ID: <?php echo htmlspecialchars($customer_filter); ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['customer' => ''])); ?>"
                        class="ml-1 hover:text-white">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
            <?php endif; ?>
            <?php if (!empty($date_from)): ?>
                <span class="bg-[#FBBF24]/20 text-[#FBBF24] text-xs px-3 py-1 rounded-full flex items-center gap-1">
                    <i class="fas fa-calendar"></i>
                    From: <?php echo $date_from; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => ''])); ?>"
                        class="ml-1 hover:text-white">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
            <?php endif; ?>
            <?php if (!empty($date_to)): ?>
                <span class="bg-[#FBBF24]/20 text-[#FBBF24] text-xs px-3 py-1 rounded-full flex items-center gap-1">
                    <i class="fas fa-calendar"></i>
                    To: <?php echo $date_to; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['date_to' => ''])); ?>"
                        class="ml-1 hover:text-white">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Drafts Grid -->
    <?php if (empty($drafts)): ?>
        <div class="bg-[#1F2937] rounded-xl p-12 text-center border border-[#374151] ">
            <div class="empty-state-icon">
                <i class="fas fa-file-pen text-3xl text-[#FBBF24]"></i>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">No Drafts Found</h3>
            <p class="text-[#9CA3AF] mb-6">
                <?php echo $has_filters ? 'No drafts match your filter criteria.' : 'Start by creating your first draft sale.'; ?>
            </p>
            <?php if ($has_filters): ?>
                <a href="list_draft.php"
                    class="inline-flex items-center gap-2 bg-[#FBBF24] hover:bg-[#F59E0B] text-[#1E3A8A] px-6 py-3 rounded-xl transition font-medium">
                    <i class="fas fa-times"></i>
                    Clear Filters
                </a>
            <?php else: ?>
                <a href="add_draft.php"
                    class="inline-flex items-center gap-2 bg-[#FBBF24] hover:bg-[#F59E0B] text-[#1E3A8A] px-6 py-3 rounded-xl transition font-medium group">
                    <i class="fas fa-plus group-hover:rotate-90 transition-transform"></i>
                    Create Your First Draft
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 ">
            <?php foreach ($drafts as $draft): ?>
                <div class="draft-card">
                    <!-- Card Header -->
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs text-[#9CA3AF]">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo date('d M H:i', strtotime($draft['created_at'])); ?>
                                </span>
                                <span class="draft-badge">
                                    <i class="fas fa-pen"></i>
                                    Draft
                                </span>
                            </div>
                            <h3 class="font-semibold text-lg text-white">
                                <?php echo htmlspecialchars($draft['customer_name'] ?? 'Walk-in Customer'); ?>
                            </h3>
                            <?php if (!empty($draft['customer_phone'])): ?>
                                <p class="text-xs text-[#9CA3AF] flex items-center gap-1 mt-1">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($draft['customer_phone']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($draft['customer_email'])): ?>
                                <p class="text-xs text-[#9CA3AF] flex items-center gap-1">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($draft['customer_email']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-[#9CA3AF]">ID</span>
                            <p class="text-sm font-mono text-white">
                                #<?php echo str_pad($draft['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>

                    <!-- Card Stats -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="bg-[#111827] rounded-lg p-2">
                            <p class="text-xs text-[#9CA3AF]">Items</p>
                            <p class="text-lg font-semibold text-white"><?php echo $draft['item_count']; ?></p>
                            <p class="text-xs text-[#6B7280]"><?php echo $draft['total_items'] ?? 0; ?> total qty</p>
                        </div>
                        <div class="bg-[#111827] rounded-lg p-2">
                            <p class="text-xs text-[#9CA3AF]">Created By</p>
                            <p class="text-sm font-semibold text-white truncate">
                                <?php echo htmlspecialchars($draft['created_by_name'] ?? 'Unknown'); ?></p>
                            <p class="text-xs text-[#6B7280]"><?php echo date('d M', strtotime($draft['created_at'])); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Notes Preview -->
                    <?php if (!empty($draft['notes'])): ?>
                        <div class="mb-4 p-3 bg-[#111827] rounded-lg">
                            <p class="text-xs text-[#9CA3AF] flex items-center gap-1 mb-1">
                                <i class="fas fa-sticky-note"></i>
                                Notes
                            </p>
                            <p class="text-xs text-[#D1D5DB] line-clamp-2">
                                <?php echo htmlspecialchars($draft['notes']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Total and Actions -->
                    <div class="border-t border-[#374151] pt-4">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-[#9CA3AF]">Total</span>
                            <div class="text-right">
                                <span class="text-xl font-bold text-[#FBBF24]">
                                    <?php echo $currency_symbol; ?>         <?php echo number_format($draft['total'], 0); ?>
                                </span>
                                <?php if (!empty($draft['discount']) && $draft['discount'] > 0): ?>
                                    <p class="text-xs text-[#6B7280]">Includes discount</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <a href="view_draft.php?id=<?php echo $draft['id']; ?>"
                                class="flex-1 bg-[#FBBF24] hover:bg-[#F59E0B] text-[#1E3A8A] px-3 py-2.5 rounded-xl transition text-sm font-medium flex items-center justify-center gap-2 group">
                                <i class="fas fa-eye group-hover:rotate-12 transition-transform"></i>
                                View
                            </a>
                            <button
                                onclick="deleteDraft(<?php echo $draft['id']; ?>, '<?php echo htmlspecialchars(addslashes($draft['customer_name'] ?? 'Walk-in Customer')); ?>')"
                                class="bg-[#EF4444] hover:bg-[#DC2626] text-white p-2.5 rounded-xl transition action-btn"
                                title="Delete Draft">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Last Updated -->
                    <div class="mt-3 text-xs text-[#6B7280] flex items-center gap-1">
                        <i class="far fa-clock"></i>
                        Updated: <?php echo date('d M Y H:i', strtotime($draft['updated_at'] ?? $draft['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4 ">
                <div class="text-sm text-[#9CA3AF]">
                    Showing <span class="font-semibold text-white"><?php echo $offset + 1; ?></span> to
                    <span class="font-semibold text-white"><?php echo min($offset + $limit, $total_drafts); ?></span> of
                    <span class="font-semibold text-white"><?php echo $total_drafts; ?></span> drafts
                </div>

                <div class="flex gap-2">
                    <!-- Previous Page -->
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                        class="px-4 py-2 rounded-lg bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24] transition <?php echo $page <= 1 ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                            class="px-4 py-2 rounded-lg bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24] transition">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="px-4 py-2 text-[#9CA3AF]">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                            class="px-4 py-2 rounded-lg transition <?php echo $i === $page ? 'bg-[#FBBF24] text-[#1E3A8A] font-semibold' : 'bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24]'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="px-4 py-2 text-[#9CA3AF]">...</span>
                        <?php endif; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                            class="px-4 py-2 rounded-lg bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24] transition"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <!-- Next Page -->
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                        class="px-4 py-2 rounded-lg bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24] transition <?php echo $page >= $total_pages ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Items per page selector -->
                <div class="flex items-center gap-2">
                    <span class="text-sm text-[#9CA3AF]">Show:</span>
                    <select onchange="changeLimit(this.value)"
                        class="bg-[#1F2937] border border-[#374151] rounded-lg px-3 py-2 text-sm text-white">
                        <option value="12" <?php echo $limit == 12 ? 'selected' : ''; ?>>12</option>
                        <option value="24" <?php echo $limit == 24 ? 'selected' : ''; ?>>24</option>
                        <option value="48" <?php echo $limit == 48 ? 'selected' : ''; ?>>48</option>
                        <option value="96" <?php echo $limit == 96 ? 'selected' : ''; ?>>96</option>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/80  hidden items-center justify-center z-50">
    <div class="bg-[#1F2937] rounded-xl border border-[#374151] w-full max-w-md p-6 ">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-[#EF4444]/20 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-2xl text-[#EF4444]"></i>
            </div>
            <div>
                <h3 class="text-xl font-semibold text-white">Delete Draft</h3>
                <p id="deleteCustomerName" class="text-sm text-[#9CA3AF]"></p>
            </div>
        </div>

        <p class="text-[#D1D5DB] mb-6">
            Are you sure you want to delete this draft? This action cannot be undone and all items will be
            permanently removed.
        </p>

        <div class="flex gap-3">
            <button onclick="confirmDelete()"
                class="flex-1 bg-[#EF4444] hover:bg-[#DC2626] text-white py-3 rounded-lg font-semibold transition flex items-center justify-center gap-2">
                <i class="fas fa-trash"></i>
                Delete Permanently
            </button>
            <button onclick="closeDeleteModal()"
                class="flex-1 bg-[#374151] hover:bg-[#4B5563] text-white py-3 rounded-lg font-semibold transition">
                Cancel
            </button>
        </div>

        <p class="text-xs text-[#6B7280] text-center mt-4">
            This action is irreversible
        </p>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay"
    class="fixed inset-0 bg-black/80  hidden items-center justify-center z-[60]">
    <div class="bg-[#1F2937] rounded-xl p-8 flex flex-col items-center">
        <div class="w-16 h-16 border-4 border-[#FBBF24] border-t-transparent rounded-full animate-spin mb-4"></div>
        <p class="text-lg font-semibold text-white">Loading...</p>
        <p class="text-sm text-[#9CA3AF]">Please wait</p>
    </div>
</div>

<!-- Connection Status -->
<div id="connection-status"
    class="fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]">
    <i class="fas fa-wifi"></i>
    <span>Online</span>
</div>

<script>
    let draftToDelete = null;

    // Debug toggle
    function toggleDebug() {
        document.getElementById('debugPanel').classList.toggle('visible');
    }

    // Delete functions
    function deleteDraft(draftId, customerName) {
        draftToDelete = draftId;
        document.getElementById('deleteCustomerName').textContent = `Draft for: ${customerName}`;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
        draftToDelete = null;
    }

    function confirmDelete() {
        if (draftToDelete) {
            showLoading();
            window.location.href = 'delete_draft.php?id=' + draftToDelete;
        }
    }

    // Filters panel
    function toggleFilters() {
        const panel = document.getElementById('filtersPanel');
        panel.classList.toggle('hidden');
    }

    // Loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingOverlay').classList.add('flex');
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('loadingOverlay').classList.remove('flex');
    }

    // Export drafts
    function exportDrafts() {
        const params = new URLSearchParams({
            search: '<?php echo $search; ?>',
            sort: '<?php echo $sort; ?>',
            date_from: '<?php echo $date_from; ?>',
            date_to: '<?php echo $date_to; ?>',
            customer: '<?php echo $customer_filter; ?>'
        });
        window.location.href = 'export_drafts.php?' + params.toString();
    }

    // Change items per page
    function changeLimit(limit) {
        const params = new URLSearchParams(window.location.search);
        params.set('limit', limit);
        params.set('page', '1');
        window.location.search = params.toString();
    }

    // Close modal when clicking outside
    document.addEventListener('click', function (event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            closeDeleteModal();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) {
            return;
        }

        // Ctrl + F - Focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('input[name="search"]')?.focus();
        }

        // Ctrl + N - New draft
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'add_draft.php';
        }

        // Ctrl + E - Export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportDrafts();
        }

        // Escape - Close modal
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // Auto-hide success messages after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.bg-\\[\\#10B981\\]\\/10, .bg-\\[\\#EF4444\\]\\/10').forEach(el => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        });
    }, 5000);

    // Connection status
    function updateOnlineStatus() {
        const statusEl = document.getElementById('connection-status');
        if (statusEl) {
            if (navigator.onLine) {
                statusEl.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]';
            } else {
                statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
                statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#EF4444] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]';
            }
        }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
