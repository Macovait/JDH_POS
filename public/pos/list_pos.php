<?php
/**
 * List POS Transactions Page for Jakababa POS
 * Display and manage all POS transactions with filtering and detailed view
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$page_title = 'POS Transactions | Jakababa POS';
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? 0);
$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

// Get branch filter
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$current_branch_id = $user_branch ?: $branch_filter;

// Business type filter
$business_type_filter = $_GET['business_type'] ?? '';

// User filter - regular users see only their own sales
$user_filter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

// Check if user is admin
$user_role = $_SESSION['role'] ?? '';
$is_admin = in_array($user_role, ['admin', 'owner', 'superadmin']) || is_super_admin();
$current_user_id = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);

if (!$is_admin && $branch_filter === 0) {
    $branch_filter = $current_branch_id;
}

// Get all branches for company
$branches = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching branches: " . $e->getMessage());
}

// Get available business types for filter
$available_business_types = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT business_type FROM sales WHERE tenant_id = " . (int)$tenant_id . " AND business_type IS NOT NULL AND business_type != ''");
    $bt_values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $bt_config_all = get_business_type_config();
    $business_types_list = $bt_config_all['business_types'] ?? [];
    foreach ($bt_values as $bt) {
        if (isset($business_types_list[$bt])) {
            $available_business_types[$bt] = $business_types_list[$bt]['name'] ?? ucfirst($bt);
        } else {
            $available_business_types[$bt] = ucfirst($bt);
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching business types: " . $e->getMessage());
}

// Pagination parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$payment_method = $_GET['payment_method'] ?? '';
$search = $_GET['search'] ?? '';

// Fetch POS transactions with pagination and filters
$transactions = [];
$total_transactions = 0;
$summary = [
    'total_amount' => 0,
    'cash_count' => 0,
    'card_count' => 0,
    'qris_count' => 0,
    'transfer_count' => 0,
    'mpesa_count' => 0,
    'ewallet_count' => 0
];

try {
    $pdo = get_db_connection();

    // Build query with filters
    $count_query = "SELECT COUNT(*) as count FROM sales WHERE tenant_id = ? AND pos_transaction = 1";
    $data_query = "
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as cashier_name,
               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.tenant_id = ? AND s.pos_transaction = 1";

    $params = [$tenant_id];
    $data_params = [$tenant_id];

    // Add branch filter
    if ($branch_filter > 0) {
        $count_query .= " AND branch_id = ?";
        $data_query .= " AND s.branch_id = ?";
        $params[] = $branch_filter;
        $data_params[] = $branch_filter;
    }

    // Add business type filter
    if (!empty($business_type_filter)) {
        $count_query .= " AND business_type = ?";
        $data_query .= " AND s.business_type = ?";
        $params[] = $business_type_filter;
        $data_params[] = $business_type_filter;
    }

    // Add user filter - non-admins only see their own sales
    if (!$is_admin) {
        $count_query .= " AND user_id = ?";
        $data_query .= " AND s.user_id = ?";
        $params[] = $current_user_id;
        $data_params[] = $current_user_id;
    } elseif ($user_filter > 0) {
        $count_query .= " AND user_id = ?";
        $data_query .= " AND s.user_id = ?";
        $params[] = $user_filter;
        $data_params[] = $user_filter;
    }

    // Date range filter
    if (!empty($date_from)) {
        $count_query .= " AND DATE(created_at) >= ?";
        $data_query .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
        $data_params[] = $date_from;
    }

    if (!empty($date_to)) {
        $count_query .= " AND DATE(created_at) <= ?";
        $data_query .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
        $data_params[] = $date_to;
    }

    // Payment method filter
    if (!empty($payment_method)) {
        $count_query .= " AND s.payment_method = ?";
        $data_query .= " AND s.payment_method = ?";
        $params[] = $payment_method;
        $data_params[] = $payment_method;
    }

    // Search filter
    if (!empty($search)) {
        $count_query .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR u.name LIKE ?)";
        $data_query .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR u.name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $data_params[] = $search_term;
        $data_params[] = $search_term;
        $data_params[] = $search_term;
        $data_params[] = $search_term;
    }

    // Get total count
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_transactions = $stmt->fetch()['count'];

    // Get transactions with pagination
    $data_query .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $data_params[] = $limit;
    $data_params[] = $offset;

    $stmt = $pdo->prepare($data_query);
    $stmt->execute($data_params);
    $transactions = $stmt->fetchAll();

    // Get summary statistics
    $summary_params = [$tenant_id, $date_from, $date_to];
    $summary_where = "tenant_id = ? AND pos_transaction = 1 AND DATE(created_at) BETWEEN ? AND ?";
    
    if ($branch_filter > 0) {
        $summary_where .= " AND branch_id = ?";
        $summary_params[] = $branch_filter;
    }
    if (!empty($business_type_filter)) {
        $summary_where .= " AND business_type = ?";
        $summary_params[] = $business_type_filter;
    }
    
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(total), 0) as total_amount,
            SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count,
            SUM(CASE WHEN payment_method IN ('card', 'credit_card', 'debit_card') THEN 1 ELSE 0 END) as card_count,
            SUM(CASE WHEN payment_method = 'qris' THEN 1 ELSE 0 END) as qris_count,
            SUM(CASE WHEN payment_method = 'transfer' OR payment_method = 'bank_transfer' THEN 1 ELSE 0 END) as transfer_count,
            SUM(CASE WHEN payment_method = 'mpesa' THEN 1 ELSE 0 END) as mpesa_count,
            SUM(CASE WHEN payment_method = 'ewallet' THEN 1 ELSE 0 END) as ewallet_count
        FROM sales
        WHERE $summary_where
    ");
    $stmt->execute($summary_params);
    $summary = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Error fetching POS transactions: " . $e->getMessage());
    $error = "Failed to load transaction data.";
}

$total_pages = ceil($total_transactions / $limit);

// Payment method icons and colors (using Font Awesome)
$payment_methods = [
    'cash' => ['icon' => 'fa-money-bill-wave', 'color' => 'text-green-400', 'bg' => 'bg-green-500/20', 'label' => 'Cash'],
    'card' => ['icon' => 'fa-credit-card', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/20', 'label' => 'Card'],
    'credit_card' => ['icon' => 'fa-credit-card', 'color' => 'text-purple-400', 'bg' => 'bg-purple-500/20', 'label' => 'Credit Card'],
    'debit_card' => ['icon' => 'fa-credit-card', 'color' => 'text-indigo-400', 'bg' => 'bg-indigo-500/20', 'label' => 'Debit Card'],
    'mpesa' => ['icon' => 'fa-mobile-alt', 'color' => 'text-green-400', 'bg' => 'bg-green-500/20', 'label' => 'M-Pesa'],
    'bank_transfer' => ['icon' => 'fa-university', 'color' => 'text-pink-400', 'bg' => 'bg-pink-500/20', 'label' => 'Bank Transfer'],
    'transfer' => ['icon' => 'fa-exchange-alt', 'color' => 'text-orange-400', 'bg' => 'bg-orange-500/20', 'label' => 'Transfer'],
    'qris' => ['icon' => 'fa-qrcode', 'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500/20', 'label' => 'QRIS'],
    'ewallet' => ['icon' => 'fa-wallet', 'color' => 'text-teal-400', 'bg' => 'bg-teal-500/20', 'label' => 'E-Wallet'],
    'credit' => ['icon' => 'fa-clock', 'color' => 'text-red-400', 'bg' => 'bg-red-500/20', 'label' => 'Credit'],
    'split' => ['icon' => 'fa-credit-card', 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/20', 'label' => 'Split Payment']
];

$currency_symbol = 'KSh';
?>
<?php
ob_start();
?>
<style>
    /* Table styles */
    .table-row {
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .table-row:hover {
        background: rgba(251, 191, 36, 0.1);
    }

    /* Stat card hover */
    .bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 {
        transition: all 0.3s ease;
        background: #1F2937;
        border: 1px solid #374151;
        border-radius: 1rem;
        padding: 1.25rem;
    }

    .bg-slate-800/50 border border-slate-700/60 rounded-xl p-3:hover {
        border-color: #FBBF24;
        transform: translateY(-2px);
    }

    /* Badge styles */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        gap: 0.25rem;
    }

    /* Form input focus */
    .form-input {
        background: #111827;
        border: 1px solid #374151;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        color: white;
        width: 100%;
        transition: all 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #FBBF24;
        box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
    }

    .form-input::placeholder {
        color: #6B7280;
    }

    /* Action button hover */
    .action-btn {
        transition: all 0.2s ease;
        padding: 0.5rem;
        border-radius: 0.5rem;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    /* Modal */
    .modal {
        transition: opacity 0.3s ease;
    }

    /* Gradient text */
    .gradient-text {
        background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
</style>

<div class="fade-in">

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
                <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Transactions</div>
                <h1 class="text-lg font-bold text-white">POS Transactions</h1>
                <p class="text-sm text-slate-500 mt-0.5 mt-1">View and manage all Point of Sale transactions</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="pos.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors"><i class="fas fa-shopping-cart"></i> Open POS</a>
                <button onclick="exportTransactions()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 ">
            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-[#9CA3AF]">Total Revenue</p>
                        <p class="text-xl font-bold text-white"><?php echo $currency_symbol; ?>
                            <?php echo number_format($summary['total_amount'] ?? 0, 0); ?>
                        </p>
                    </div>
                    <div class="bg-[#FBBF24]/20 p-3 rounded-full">
                        <i class="fas fa-coins text-[#FBBF24] text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-[#9CA3AF]">Cash</p>
                        <p class="text-xl font-bold text-white"><?php echo $summary['cash_count'] ?? 0; ?></p>
                    </div>
                    <div class="bg-[#10B981]/20 p-3 rounded-full">
                        <i class="fas fa-money-bill-wave text-[#10B981] text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-[#9CA3AF]">Card</p>
                        <p class="text-xl font-bold text-white"><?php echo $summary['card_count'] ?? 0; ?></p>
                    </div>
                    <div class="bg-[#3B82F6]/20 p-3 rounded-full">
                        <i class="fas fa-credit-card text-[#3B82F6] text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-[#9CA3AF]">M-Pesa</p>
                        <p class="text-xl font-bold text-white"><?php echo $summary['mpesa_count'] ?? 0; ?></p>
                    </div>
                    <div class="bg-[#F59E0B]/20 p-3 rounded-full">
                        <i class="fas fa-mobile-alt text-[#F59E0B] text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-[#1F2937] rounded-xl p-5 border border-[#374151] mb-6 ">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-input">
                </div>
                <div>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-input">
                </div>
                <div>
                    <select name="payment_method" class="form-input">
                        <option value="">All Payments</option>
                        <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="mpesa" <?php echo $payment_method === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                        <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="qris" <?php echo $payment_method === 'qris' ? 'selected' : ''; ?>>QRIS</option>
                        <option value="credit" <?php echo $payment_method === 'credit' ? 'selected' : ''; ?>>Credit
                        </option>
                        <option value="split" <?php echo $payment_method === 'split' ? 'selected' : ''; ?>>Split</option>
                    </select>
                </div>
                <?php if ($is_admin && !empty($branches)): ?>
                <div>
                    <select name="branch_id" class="form-input">
                        <option value="0">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if ($is_admin && !empty($available_business_types)): ?>
                <div>
                    <select name="business_type" class="form-input">
                        <option value="">All Business Types</option>
                        <?php foreach ($available_business_types as $bt_code => $bt_name): ?>
                            <option value="<?php echo $bt_code; ?>" <?php echo $business_type_filter === $bt_code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bt_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="md:col-span-<?php echo ($is_admin && !empty($branches) && !empty($available_business_types)) ? '1' : '2'; ?>">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search invoice, customer, cashier..." class="form-input">
                </div>
                <div class="md:col-span-6 flex justify-end gap-3">
                    <a href="list_pos.php"
                        class="px-4 py-2 bg-[#EF4444] text-white rounded-lg hover:bg-[#DC2626] transition text-sm font-medium">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                    <button type="submit"
                        class="px-4 py-2 bg-[#FBBF24] text-[#1E3A8A] rounded-lg hover:bg-[#F59E0B] transition text-sm font-medium">
                        <i class="fas fa-filter mr-1"></i>Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="bg-[#1F2937] rounded-xl border border-[#374151] overflow-hidden ">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#111827]">
                        <tr>
                            <th class="px-4 py-4 text-left text-sm font-semibold text-[#9CA3AF]">Time</th>
                            <th class="px-4 py-4 text-left text-sm font-semibold text-[#9CA3AF]">Invoice #</th>
                            <th class="px-4 py-4 text-left text-sm font-semibold text-[#9CA3AF]">Customer</th>
                            <th class="px-4 py-4 text-left text-sm font-semibold text-[#9CA3AF]">Cashier</th>
                            <th class="px-4 py-4 text-center text-sm font-semibold text-[#9CA3AF]">Items</th>
                            <th class="px-4 py-4 text-right text-sm font-semibold text-[#9CA3AF]">Amount</th>
                            <th class="px-4 py-4 text-center text-sm font-semibold text-[#9CA3AF]">Payment</th>
                            <th class="px-4 py-4 text-center text-sm font-semibold text-[#9CA3AF]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#374151]">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-[#9CA3AF]">
                                    <i class="fas fa-laptop text-4xl text-[#4B5563] mb-3"></i>
                                    <p class="text-lg">No POS transactions found</p>
                                    <p class="text-sm mt-1">Try adjusting your filters or open the POS to create
                                        transactions</p>
                                    <a href="pos.php"
                                        class="inline-block mt-4 bg-[#FBBF24] hover:bg-[#F59E0B] text-[#1E3A8A] px-6 py-2 rounded-lg transition">
                                        <i class="fas fa-shopping-cart mr-2"></i>Open POS
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t):
                                $method = $payment_methods[$t['payment_method']] ??
                                    ['icon' => 'fa-credit-card', 'color' => 'text-gray-400', 'bg' => 'bg-gray-500/20', 'label' => ucfirst($t['payment_method'])];
                                ?>
                                <tr class="table-row hover:bg-[#2D3748] transition"
                                    onclick="viewTransaction(<?php echo $t['id']; ?>)">
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-white"><?php echo date('H:i', strtotime($t['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-[#9CA3AF]">
                                            <?php echo date('d/m/Y', strtotime($t['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="font-mono text-sm text-[#FBBF24]">
                                            #<?php echo str_pad($t['id'], 6, '0', STR_PAD_LEFT); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-white">
                                            <?php echo htmlspecialchars($t['customer_name'] ?? 'Walk-in'); ?>
                                        </div>
                                        <?php if (!empty($t['customer_phone'])): ?>
                                            <div class="text-xs text-[#9CA3AF]">
                                                <i
                                                    class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($t['customer_phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-white"><?php echo htmlspecialchars($t['cashier_name']); ?>
                                    </td>
                                    <td class="px-4 py-4 text-center text-white"><?php echo $t['item_count']; ?></td>
                                    <td class="px-4 py-4 text-right font-mono font-semibold text-[#FBBF24]">
                                        <?php echo $currency_symbol; ?>         <?php echo number_format($t['total'], 0); ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-center">
                                            <span class="badge <?php echo $method['bg']; ?> <?php echo $method['color']; ?>">
                                                <i class="fas <?php echo $method['icon']; ?>"></i>
                                                <?php echo $method['label']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-center gap-2" onclick="event.stopPropagation()">
                                            <a href="view_sale.php?id=<?php echo $t['id']; ?>"
                                                class="action-btn text-[#9CA3AF] hover:text-[#3B82F6]" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="receipts/print_receipt.php?id=<?php echo $t['id']; ?>" target="_blank"
                                                class="action-btn text-[#9CA3AF] hover:text-[#FBBF24]" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <?php if (!empty($transactions)): ?>
                <div class="px-4 py-3 bg-[#111827] border-t border-[#374151] text-sm text-[#9CA3AF] flex justify-between">
                    <span><i class="fas fa-list mr-1"></i> Showing <?php echo $offset + 1; ?> to
                        <?php echo min($offset + $limit, $total_transactions); ?> of <?php echo $total_transactions; ?>
                        transactions</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-between items-center">
                <div class="text-sm text-[#9CA3AF]">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
                <div class="flex gap-2">
                    <a href="?page=<?php echo max(1, $page - 1); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&payment_method=<?php echo urlencode($payment_method); ?>&search=<?php echo urlencode($search); ?>&branch_id=<?php echo $branch_filter; ?>&business_type=<?php echo urlencode($business_type_filter); ?>"
                        class="px-4 py-2 rounded-lg bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24] transition <?php echo $page <= 1 ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&payment_method=<?php echo urlencode($payment_method); ?>&search=<?php echo urlencode($search); ?>&branch_id=<?php echo $branch_filter; ?>&business_type=<?php echo urlencode($business_type_filter); ?>"
                            class="px-4 py-2 rounded-lg transition <?php echo $i === $page ? 'bg-[#FBBF24] text-[#1E3A8A] font-semibold' : 'bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24]'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&payment_method=<?php echo urlencode($payment_method); ?>&search=<?php echo urlencode($search); ?>&branch_id=<?php echo $branch_filter; ?>&business_type=<?php echo urlencode($business_type_filter); ?>"
                        class="px-4 py-2 rounded-lg bg-[#1F2937] border border-[#374151] hover:border-[#FBBF24] transition <?php echo $page >= $total_pages ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-50 modal">
        <div class="bg-[#1F2937] rounded-xl border border-[#374151] w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-download text-[#FBBF24]"></i>
                    Export Transactions
                </h3>
                <button onclick="closeExportModal()" class="text-[#9CA3AF] hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="exportForm" action="export_pos_transactions.php" method="POST" class="space-y-4">
                <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
                <input type="hidden" name="date_to" value="<?php echo $date_to; ?>">
                <input type="hidden" name="payment_method" value="<?php echo $payment_method; ?>">
                <input type="hidden" name="search" value="<?php echo $search; ?>">

                <div>
                    <label class="block text-sm text-[#9CA3AF] mb-2">Export Format</label>
                    <select name="format" class="form-input">
                        <option value="csv">CSV (Excel)</option>
                        <option value="pdf">PDF Report</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-[#9CA3AF] mb-2">Report Type</label>
                    <select name="report_type" class="form-input">
                        <option value="summary">Summary Report</option>
                        <option value="detailed">Detailed Transactions</option>
                    </select>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit"
                        class="flex-1 bg-[#10B981] hover:bg-[#059669] text-white py-3 rounded-lg font-semibold transition">
                        <i class="fas fa-download mr-2"></i>Download
                    </button>
                    <button type="button" onclick="closeExportModal()"
                        class="flex-1 bg-[#EF4444] hover:bg-[#DC2626] text-white py-3 rounded-lg font-semibold transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Status -->
    <div id="connection-status"
        class="fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]">
        <i class="fas fa-wifi"></i>
        <span>Online</span>
    </div>

</div>

<script>
    // View transaction details
    function viewTransaction(transactionId) {
        window.location.href = 'view_sale.php?id=' + transactionId;
    }

    // Export functionality
    function exportTransactions() {
        document.getElementById('exportModal').classList.remove('hidden');
        document.getElementById('exportModal').classList.add('flex');
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
        document.getElementById('exportModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.addEventListener('click', function (event) {
        const modal = document.getElementById('exportModal');
        if (event.target === modal) {
            closeExportModal();
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
            document.querySelector('input[name="search"]').focus();
        }

        // Ctrl + E - Export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportTransactions();
        }

        // Ctrl + P - Open POS
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.location.href = 'pos.php';
        }

        // Escape - Close modal
        if (e.key === 'Escape') {
            closeExportModal();
        }
    });

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
require_once __DIR__ . '/../layouts/app_close.php';
