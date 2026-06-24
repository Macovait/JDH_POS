<?php
/**
 * List Returns - Return Management System
 * Pure Tailwind CSS Design - Slim Cards Version (All Features Preserved)
 * @version 5.6
 * @updated 2026-06-04
 */

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

if (!check_permission('sales.returns') && !is_super_admin()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    enforce_permission('sales.returns');
}

safe_require('db.php', 'src', true);

$page_title = 'Returns Management';
ob_start();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

$currency_symbol = 'KSh';
$pdo = null;
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([(int)$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency_symbol = $curr;
} catch (Exception $e) {}

$_csrf_token_generated = generate_csrf_token();
if (empty($_SESSION['csrf_token_time'])) {
    $_SESSION['csrf_token_time'] = time();
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !verify_csrf_token($csrf_token)) {
        $_SESSION['error_message'] = 'Invalid CSRF token';
        header("Location: list_sell_return.php");
        exit;
    }
    $action = $_POST['bulk_action'];
    $return_ids = $_POST['return_ids'] ?? [];
    
    if (!empty($return_ids)) {
        try {
            $pdo = get_db_connection();
            $pdo->beginTransaction();
            $placeholders = str_repeat('?,', count($return_ids) - 1) . '?';
            
            if ($action === 'delete') {
                $stmt = $pdo->prepare("UPDATE returns SET status = 'voided', deleted_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?");
                $params = array_merge($return_ids, [$tenant_id]);
                $stmt->execute($params);
                $message = count($return_ids) . " return(s) voided successfully";
            } elseif ($action === 'complete') {
                $stmt = $pdo->prepare("UPDATE returns SET status = 'completed', updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ? AND status = 'pending'");
                $params = array_merge($return_ids, [$tenant_id]);
                $stmt->execute($params);
                $message = $stmt->rowCount() . " return(s) marked as completed";
            }
            $pdo->commit();
            $_SESSION['success_message'] = $message;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Bulk action failed: " . $e->getMessage();
        }
        header("Location: list_sell_return.php");
        exit;
    }
}

// Get branch name for display
$branch_name = 'Main Branch';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$branch_id, $tenant_id]);
    $branch = $stmt->fetch();
    if ($branch) $branch_name = $branch['name'];
} catch (Exception $e) {}

// Statistics
$return_stats = ['total_returns' => 0, 'completed' => 0, 'pending' => 0, 'rejected' => 0, 'total_amount' => 0, 'completed_amount' => 0];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_returns, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected, COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed_amount FROM returns WHERE tenant_id = ? AND branch_id = ?");
    $stmt->execute([$tenant_id, $branch_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stats_result) $return_stats = $stats_result;
} catch (Exception $e) {}

// High value threshold
$high_value_threshold = 10000;
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'high_value_return_threshold' AND tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $threshold = $stmt->fetchColumn();
    if ($threshold) $high_value_threshold = (float)$threshold;
} catch (Exception $e) {}

// User role for approval limits
$user_role = 'cashier';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT r.name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([get_current_user_id()]);
    $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role_data) $user_role = strtolower($role_data['name']);
} catch (Exception $e) {}
$user_approval_limit = 5000;
if ($user_role === 'manager') $user_approval_limit = 50000;
if ($user_role === 'admin' || is_super_admin()) $user_approval_limit = 999999;

// Top returned products
$top_returned_products = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT p.name, COUNT(ri.id) as return_count, SUM(ri.quantity) as total_quantity, SUM(ri.subtotal) as total_value FROM return_items ri JOIN returns r ON ri.return_id = r.id JOIN products p ON ri.product_id = p.id WHERE r.tenant_id = ? AND r.branch_id = ? AND r.status = 'completed' GROUP BY p.id ORDER BY return_count DESC LIMIT 5");
    $stmt->execute([$tenant_id, $branch_id]);
    $top_returned_products = $stmt->fetchAll();
} catch (Exception $e) {}

// Return reasons
$return_reasons = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT reason, COUNT(*) as count, SUM(amount) as total_amount FROM returns WHERE tenant_id = ? AND branch_id = ? AND status = 'completed' AND reason IS NOT NULL AND reason != '' GROUP BY reason ORDER BY count DESC LIMIT 5");
    $stmt->execute([$tenant_id, $branch_id]);
    $return_reasons = $stmt->fetchAll();
} catch (Exception $e) {}

// Recent returns
$recent_returns = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT r.id, r.return_number, r.status, r.amount, r.created_at, s.invoice_number, c.name as customer_name, CASE WHEN r.amount > ? THEN 1 ELSE 0 END as high_value FROM returns r LEFT JOIN sales s ON r.sale_id = s.id LEFT JOIN customers c ON r.customer_id = c.id WHERE r.tenant_id = ? AND r.branch_id = ? ORDER BY r.created_at DESC LIMIT 5");
    $stmt->execute([$high_value_threshold, $tenant_id, $branch_id]);
    $recent_returns = $stmt->fetchAll();
    foreach ($recent_returns as &$ret) {
        $ret['amount_formatted'] = number_format($ret['amount'], 0);
        $ret['date_formatted'] = date('M d, H:i', strtotime($ret['created_at']));
    }
} catch (Exception $e) {}

// Show messages
if (isset($_SESSION['success_message'])) {
    $success_msg = htmlspecialchars($_SESSION['success_message']);
    echo "<div class='fixed top-4 right-4 z-50 ' id='flash-message'>
            <div class='bg-emerald-500/90  text-white px-3 py-1.5 rounded-lg shadow-lg text-xs'>
                <i class='fas fa-check-circle mr-1'></i> {$success_msg}
            </div>
          </div>";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_msg = htmlspecialchars($_SESSION['error_message']);
    echo "<div class='fixed top-4 right-4 z-50 ' id='flash-message'>
            <div class='bg-red-500/90  text-white px-3 py-1.5 rounded-lg shadow-lg text-xs'>
                <i class='fas fa-exclamation-circle mr-1'></i> {$error_msg}
            </div>
          </div>";
    unset($_SESSION['error_message']);
}
?>

<style>
@keyframes fadeInUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
@keyframes shimmer { 0% { background-position:-200% 0; } 100% { background-position:200% 0; } }
@keyframes pulse-glow { 0%,100% { box-shadow:0 0 0 0 rgba(251,191,36,0); } 50% { box-shadow:0 0 12px 2px rgba(251,191,36,0.15); } }
@keyframes countUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
.-up { animation:fadeInUp 0.5s ease-out forwards; opacity:0; }
.animate-delay-1 { animation-delay:0.1s; }
.animate-delay-2 { animation-delay:0.2s; }
.animate-delay-3 { animation-delay:0.3s; }
.animate-delay-4 { animation-delay:0.4s; }
.animate-delay-5 { animation-delay:0.5s; }
.skeleton-shimmer { background:linear-gradient(90deg,#1e293b 25%,#334155 50%,#1e293b 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; }
.bg-slate-800\/40 { background:rgba(30,41,59,0.6); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.06); }
.border-slate-700\/60 { border-color:rgba(51,65,85,0.6); }
.rounded-xl { border-radius:0.75rem; }
.rounded-xl-hover:hover { background:rgba(30,41,59,0.8); border-color:rgba(251,191,36,0.2); transform:translateY(-2px); box-shadow:0 8px 32px rgba(0,0,0,0.3); }
.stat-icon-glow { animation:pulse-glow 3s ease-in-out infinite; }
.filter-pill.active { background:rgba(251,191,36,0.15); border-color:rgba(251,191,36,0.4); color:#fbbf24; }
.table-row-hover:hover { background:rgba(255,255,255,0.03); }
.custom-scroll::-webkit-scrollbar { width:6px; height:6px; }
.custom-scroll::-webkit-scrollbar-track { background:rgba(15,23,42,0.5); border-radius:3px; }
.custom-scroll::-webkit-scrollbar-thumb { background:rgba(100,116,139,0.4); border-radius:3px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background:rgba(148,163,184,0.5); }
</style>

<?php
$statusTw = [
    'completed' => ['bg'=>'bg-emerald-500/10','text'=>'text-emerald-400','ring'=>'ring-emerald-500/20','dot'=>'bg-emerald-400','icon'=>'fa-check-circle'],
    'pending'   => ['bg'=>'bg-amber-500/10','text'=>'text-amber-400','ring'=>'ring-amber-500/20','dot'=>'bg-amber-400','icon'=>'fa-clock'],
    'rejected'  => ['bg'=>'bg-red-500/10','text'=>'text-red-400','ring'=>'ring-red-500/20','dot'=>'bg-red-400','icon'=>'fa-times-circle'],
    'voided'    => ['bg'=>'bg-slate-500/10','text'=>'text-slate-400','ring'=>'ring-slate-500/20','dot'=>'bg-slate-400','icon'=>'fa-ban'],
];
$statusLabel = ['completed'=>'Completed','pending'=>'Pending','rejected'=>'Rejected','voided'=>'Voided'];
?>

<div class="max-w-7xl mx-auto px-4 py-4 space-y-4 animate-fade-in">
    <!-- Hero Header -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-2 -up">
        <div>
            <div class="flex items-center gap-2 mb-0.5 text-xs text-amber-400">
                <span class="font-semibold tracking-wider">RETURNS MANAGEMENT</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Returns Management</h1>
            <p class="text-xs text-gray-500 mt-0.5">Live &bull; <?php echo htmlspecialchars($branch_name); ?></p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="select_sale_for_return.php" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 text-xs font-medium hover:bg-gray-700 hover:text-white transition-all">
                <i class="fas fa-arrow-left mr-1 text-amber-400"></i> Back
            </a>
            <a href="create_sell_return.php" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-500/30 text-amber-400 text-xs font-medium hover:from-amber-500/30 hover:to-orange-500/30 hover:border-amber-500/50 transition-all">
                <i class="fas fa-plus mr-1 text-amber-400"></i> New Return
            </a>
            <button onclick="openAnalyticsModal()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-gray-400 text-xs font-medium hover:bg-gray-700 transition-all">
                <i class="fas fa-chart-pie mr-1 text-amber-400"></i> Analytics
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
        <?php $statCards = [
            ['id'=>'stat-total','label'=>'Total Returns','value'=>$return_stats['total_returns']??0,'color'=>'amber','icon'=>'fa-exchange-alt','grad'=>'from-amber-500/20 to-orange-500/20'],
            ['id'=>'stat-completed','label'=>'Completed','value'=>$return_stats['completed']??0,'color'=>'emerald','icon'=>'fa-check-circle','grad'=>'from-emerald-500/20 to-teal-500/20'],
            ['id'=>'stat-pending','label'=>'Pending','value'=>$return_stats['pending']??0,'color'=>'orange','icon'=>'fa-clock','grad'=>'from-orange-500/20 to-red-500/20'],
            ['id'=>'stat-avg','label'=>'Avg Return','value'=>($return_stats['completed']??0)>0?number_format(($return_stats['completed_amount']??0)/$return_stats['completed']):0,'color'=>'purple','icon'=>'fa-chart-line','grad'=>'from-purple-500/20 to-pink-500/20'],
            ['id'=>'stat-rejected','label'=>'Rejected','value'=>$return_stats['rejected']??0,'color'=>'red','icon'=>'fa-times-circle','grad'=>'from-red-500/20 to-rose-500/20'],
        ]; ?>
        <?php foreach ($statCards as $i=>$card): ?>
        <div class="bg-gray-800/40 border border-gray-700 rounded-lg p-3 transition-all duration-300">
            <div class="flex items-center justify-between mb-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br <?php echo $card['grad']; ?> border border-<?php echo $card['color']; ?>-500/20 flex items-center justify-center">
                    <i class="fas <?php echo $card['icon']; ?> text-<?php echo $card['color']; ?>-400 text-xs"></i>
                </div>
                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-widest"><?php echo $card['label']; ?></span>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="<?php echo $card['id']; ?>">0</div>
        </div>
        <?php endforeach; ?>
    </div>

<!-- Filter Toolbar -->
    <div class="bg-gray-800/40 border border-gray-700 rounded-lg p-3 -up animate-delay-2">
        <div class="flex flex-col lg:flex-row gap-2 items-start lg:items-center justify-between">
            <div class="flex flex-col sm:flex-row gap-1.5 items-stretch sm:items-center flex-1 min-w-0">
                <div class="relative flex-1 max-w-md">
                    <label for="search-input" class="sr-only">Search returns</label>
                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                    <input type="text" id="search-input" placeholder="Search returns, invoices, customers..."
                        class="w-full pl-8 pr-3 py-1.5 bg-gray-900/50 border border-gray-700 rounded-lg text-white text-xs placeholder-gray-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
</div>
        </div>
    <!-- Returns Table -->
    <div class="bg-gray-800/40 border border-gray-700 rounded-lg overflow-hidden -up animate-delay-3">
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-700/50">
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase">
                            <label for="select-all" class="sr-only">Select all returns</label>
                            <input type="checkbox" id="select-all" class="rounded border-gray-600 bg-gray-800 text-amber-500 focus:ring-amber-500/20" onchange="toggleSelectAll()">
                        </th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase">Return #</th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase">Invoice</th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase text-right">Amount</th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-[10px] font-medium text-gray-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="returns-table-body">
                    <!-- Skeleton Loading -->
                    <?php for($i=0; $i<5; $i++): ?>
                    <tr class="border-b border-gray-700/30">
                        <td class="px-4 py-2"><div class="w-4 h-4 rounded skeleton-shimmer"></div></td>
                        <td class="px-4 py-2"><div class="h-3 w-20 rounded skeleton-shimmer"></div></td>
                        <td class="px-4 py-2"><div class="h-3 w-16 rounded-full skeleton-shimmer"></div></td>
                        <td class="px-4 py-2"><div class="h-3 w-24 rounded skeleton-shimmer"></div></td>
                        <td class="px-4 py-2"><div class="h-3 w-16 rounded skeleton-shimmer"></div></td>
                        <td class="px-4 py-2 text-right"><div class="h-3 w-16 rounded skeleton-shimmer ml-auto"></div></td>
                        <td class="px-4 py-2"><div class="h-3 w-20 rounded skeleton-shimmer"></div></td>
                        <td class="px-4 py-2 text-right"><div class="h-3 w-8 rounded skeleton-shimmer ml-auto"></div></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div id="pagination-container" class="px-4 py-3 border-t border-gray-700/50"></div>
    </div>

<!-- Recent Returns Timeline -->
    <div class="bg-gray-800/40 border border-gray-700 rounded-lg overflow-hidden -up animate-delay-4">
        <div class="px-4 py-2 border-b border-gray-700/50 flex items-center gap-2">
            <div class="w-6 h-6 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <i class="fas fa-history text-amber-400 text-xs"></i>
            </div>
            <span class="text-xs font-semibold text-white text-xs font-semibold text-amber-400 uppercase tracking-wider">Recent Activity</span>
            <span class="text-[10px] font-medium text-gray-500 ml-auto px-2 py-0.5 rounded-full bg-gray-800/60 border border-gray-700/40">Last 5</span>
        </div>
        <div id="recent-returns-list" class="divide-y divide-gray-700/30">
            <?php if (empty($recent_returns)): ?>
                <div class="p-6 text-center">
                    <div class="w-10 h-10 rounded-full bg-gray-800/80 flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-inbox text-gray-600 text-sm"></i>
                    </div>
                    <p class="text-xs text-gray-500">No recent returns</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_returns as $idx=>$ret): ?>
                <a href="view_sell_return.php?id=<?php echo $ret['id']; ?>" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-700/20 transition-all group <?php echo $ret['high_value'] ? 'bg-red-500/[0.03]' : ''; ?>">
                    <div class="relative">
                        <div class="w-9 h-9 rounded-lg <?php echo $ret['high_value'] ? 'bg-gradient-to-br from-red-500/20 to-rose-500/20 border-red-500/20' : 'bg-gradient-to-br from-amber-500/20 to-orange-500/20 border-amber-500/20'; ?> border flex items-center justify-center shrink-0 transition-transform group-hover:scale-110">
                            <i class="fas fa-undo-alt <?php echo $ret['high_value'] ? 'text-red-400' : 'text-amber-400'; ?> text-xs"></i>
                        </div>
                        <?php if($idx===0): ?><div class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-400 border-2 border-gray-900"></div><?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-xs font-semibold text-white group-hover:text-amber-400 transition-colors">#<?php echo htmlspecialchars($ret['return_number']); ?></span>
                            <?php $st = $statusTw[$ret['status']] ?? $statusTw['pending']; ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium <?php echo $st['bg'].' '.$st['text'].' ring-1 '.$st['ring']; ?>">
                                <span class="w-1 h-1 rounded-full <?php echo $st['dot']; ?> animate-pulse"></span>
                                <?php echo $statusLabel[$ret['status']] ?? ucfirst($ret['status']); ?>
                            </span>
                            <?php if($ret['high_value']): ?>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                                <i class="fas fa-exclamation-triangle text-[8px]"></i> High Value
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-500 truncate mt-0.5">
                            <span class="text-gray-400"><?php echo htmlspecialchars($ret['customer_name'] ?? 'Walk-in'); ?></span>
                            <span class="mx-1 text-gray-600">&bull;</span>
                            <span>Inv: <?php echo htmlspecialchars($ret['invoice_number'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-xs font-bold <?php echo $ret['high_value'] ? 'text-red-400' : 'text-amber-400'; ?>"><?php echo $currency_symbol . ' ' . $ret['amount_formatted']; ?></div>
                        <div class="text-[10px] text-gray-500 flex items-center justify-end gap-1">
                            <i class="far fa-clock text-[9px]"></i> <?php echo $ret['date_formatted']; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two Column Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 -up animate-delay-5">
        <?php if (!empty($top_returned_products)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-700/50 flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <i class="fas fa-layer-group text-purple-400 text-xs"></i>
                </div>
                <span class="text-sm font-semibold text-white">Most Returned Products</span>
                <span class="text-[10px] font-medium text-slate-500 ml-auto px-2 py-0.5 rounded-full bg-slate-800/60 border border-slate-700/40">Last 30 days</span>
            </div>
            <div class="p-5 space-y-4">
                <?php $maxCount = !empty($top_returned_products) ? $top_returned_products[0]['return_count'] : 1; ?>
                <?php foreach ($top_returned_products as $product): ?>
                <div class="group">
                    <div class="flex justify-between items-center text-xs mb-1.5">
                        <span class="text-slate-300 font-medium truncate pr-2 group-hover:text-white transition-colors"><?php echo htmlspecialchars($product['name']); ?></span>
                        <span class="text-slate-500 shrink-0"><?php echo (int) ($product['return_count'] ?? 0); ?> <span class="text-slate-600">returns</span> &bull; <?php echo $currency_symbol . ' ' . number_format((float) ($product['total_value'] ?? 0), 0); ?></span>
                    </div>
                    <div class="h-1.5 bg-slate-700/50 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-purple-400 via-amber-400 to-orange-400 rounded-full transition-all duration-700" style="width: <?php echo ($product['return_count'] / max(1, $maxCount)) * 100; ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($return_reasons)): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-700/50 flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <i class="fas fa-lightbulb text-emerald-400 text-xs"></i>
                </div>
                <span class="text-sm font-semibold text-white">Return Reasons Analysis</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ($return_reasons as $reason): ?>
                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 text-center hover:border-amber-500/20 transition-all group">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500/10 to-orange-500/10 border border-amber-500/20 flex items-center justify-center mx-auto mb-2 group-hover:scale-110 transition-transform">
                            <i class="fas fa-tag text-amber-400 text-[10px]"></i>
                        </div>
                        <div class="text-xs font-semibold text-white"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reason['reason']))); ?></div>
                        <div class="text-[11px] text-slate-500 mt-0.5"><?php echo $reason['count']; ?> returns</div>
                        <div class="text-[10px] text-amber-400/70 mt-1 font-medium"><?php echo $currency_symbol . ' ' . number_format((float) ($reason['total_amount'] ?? 0), 0); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Advanced Analytics Modal -->
    <div id="analytics-modal" class="fixed inset-0 bg-black/80 hidden items-start justify-center z-50 no-print transition-all py-8">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl max-w-5xl w-full mx-4 max-h-[85vh] overflow-y-auto custom-scroll">
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-700/50 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/20 flex items-center justify-center">
                        <i class="fas fa-chart-pie text-amber-400 text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Analytics Dashboard</h3>
                        <p class="text-[11px] text-slate-500">Return insights & trends</p>
                    </div>
                </div>
                <button onclick="closeAnalyticsModal()" class="w-8 h-8 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-400 hover:text-white hover:bg-slate-700/80 transition-all flex items-center justify-center shrink-0">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 h-[220px] flex flex-col">
                        <div class="flex items-center gap-2 mb-3 shrink-0">
                            <div class="w-6 h-6 rounded-md bg-amber-500/10 flex items-center justify-center">
                                <i class="fas fa-wave-square text-amber-400 text-[10px]"></i>
                            </div>
                            <h4 class="text-[11px] font-semibold text-amber-400 uppercase tracking-wider">Return Trend</h4>
                        </div>
                        <div class="flex-1 min-h-0 relative">
                            <canvas id="trend-chart"></canvas>
                        </div>
                    </div>
                    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 h-[220px] flex flex-col">
                        <div class="flex items-center gap-2 mb-3 shrink-0">
                            <div class="w-6 h-6 rounded-md bg-emerald-500/10 flex items-center justify-center">
                                <i class="fas fa-chart-pie text-emerald-400 text-[10px]"></i>
                            </div>
                            <h4 class="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider">Reason Distribution</h4>
                        </div>
                        <div class="flex-1 min-h-0 relative">
                            <canvas id="reason-chart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 h-[200px] flex flex-col">
                    <div class="flex items-center gap-2 mb-3 shrink-0">
                        <div class="w-6 h-6 rounded-md bg-purple-500/10 flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-purple-400 text-[10px]"></i>
                        </div>
                        <h4 class="text-[11px] font-semibold text-purple-400 uppercase tracking-wider">Monthly Overview</h4>
                    </div>
                    <div class="flex-1 min-h-0 relative">
                        <canvas id="monthly-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var API_BASE = '../../ajax/';
var CURRENCY = '<?php echo $currency_symbol; ?>';
var BRANCH_ID = <?php echo (int)$branch_id; ?>;
var TENANT_ID = <?php echo (int)$tenant_id; ?>;
var HIGH_VALUE_THRESHOLD = <?php echo (int)$high_value_threshold; ?>;
var PER_PAGE = 15;
var CURRENT_STATUS = '';
var CURRENT_SEARCH = '';

var statusConfig = {
    completed: { bg: 'bg-emerald-500/10', text: 'text-emerald-400', ring: 'ring-emerald-500/20', dot: 'bg-emerald-400', icon: 'fa-check-circle' },
    pending:   { bg: 'bg-amber-500/10', text: 'text-amber-400', ring: 'ring-amber-500/20', dot: 'bg-amber-400', icon: 'fa-clock' },
    rejected:  { bg: 'bg-red-500/10', text: 'text-red-400', ring: 'ring-red-500/20', dot: 'bg-red-400', icon: 'fa-times-circle' },
    voided:    { bg: 'bg-slate-500/10', text: 'text-slate-400', ring: 'ring-slate-500/20', dot: 'bg-slate-400', icon: 'fa-ban' }
};

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        var d = new Date(dateStr);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    } catch(e) { return dateStr; }
}

function number_format(num) {
    return parseFloat(num).toLocaleString();
}

function animateValue(id, target, duration) {
    var el = document.getElementById(id);
    if (!el) return;
    var start = 0;
    var startTime = null;
    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = number_format(Math.floor(start + (target - start) * eased));
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

function animateBars() {
    document.querySelectorAll('[data-bar-width]').forEach(function(bar) {
        var target = parseFloat(bar.getAttribute('data-bar-width'));
        setTimeout(function() { bar.style.width = target + '%'; }, 300);
    });
}

function loadReturns(page) {
    if (!page) page = 1;
    var tbody = document.getElementById('returns-table-body');
    // Skeleton
    var skel = '';
    for (var s = 0; s < 5; s++) {
        skel += '<tr class="border-b border-slate-700/30">';
        for (var c = 0; c < 8; c++) {
            var cls = (c === 5 || c === 7) ? 'ml-auto' : '';
            var w = ['w-4','w-20','w-16','w-24','w-16','w-16','w-20','w-8'][c];
            var round = (c === 0 || c === 2) ? 'rounded-full' : 'rounded';
            skel += '<td class="px-4 py-3"><div class="h-3 ' + w + ' ' + round + ' skeleton-shimmer ' + cls + '"></div></td>';
        }
        skel += '</tr>';
    }
    tbody.innerHTML = skel;

    var params = new URLSearchParams({
        branch_id: BRANCH_ID, tenant_id: TENANT_ID, page: page, limit: PER_PAGE,
        status: CURRENT_STATUS, search: CURRENT_SEARCH
    });
    fetch(API_BASE + 'search_returns.php?' + params)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                renderReturnsTable(data.returns);
                renderPagination(data.pagination || { current_page: page, total_pages: Math.ceil((data.total||0)/PER_PAGE), total: data.total||0 });
                updateStatBars(data.stats);
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-10 text-center text-red-400 text-sm">Error loading returns</td></tr>';
            }
        })
        .catch(function(err) {
            console.error('Fetch error:', err);
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-10 text-center text-red-400 text-sm">Failed to load returns</td></tr>';
        });
}

function renderReturnsTable(returns) {
    var tbody = document.getElementById('returns-table-body');
    if (!returns || returns.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-10 text-center"><div class="w-12 h-12 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3"><i class="fas fa-inbox text-slate-600 text-lg"></i></div><p class="text-sm text-slate-500">No returns found</p></td></tr>';
        return;
    }
    var html = '';
    returns.forEach(function(ret, idx) {
        var st = statusConfig[ret.status] || statusConfig.pending;
        var high = ret.amount > HIGH_VALUE_THRESHOLD;
        var delay = idx * 0.05;
        html += '<tr class="border-b border-slate-700/30 table-row-hover transition-colors" style="animation:fadeInUp 0.4s ease-out ' + delay + 's both;">';
        html += '<td class="px-4 py-3"><input type="checkbox" id="return-cb-' + ret.id + '" class="return-checkbox rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20" value="' + ret.id + '" onchange="updateSelectAllState()"><label for="return-cb-' + ret.id + '" class="sr-only">Select return #' + ret.return_number + '</label></td>';
        html += '<td class="px-4 py-3"><a href="view_sell_return.php?id=' + ret.id + '" class="text-xs font-semibold text-white hover:text-amber-400 transition-colors">#' + ret.return_number + '</a></td>';
        html += '<td class="px-4 py-3"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium ' + st.bg + ' ' + st.text + ' ring-1 ' + st.ring + '"><span class="w-1 h-1 rounded-full ' + st.dot + ' animate-pulse"></span>' + (ret.status ? ret.status.charAt(0).toUpperCase() + ret.status.slice(1) : 'Pending') + '</span></td>';
        html += '<td class="px-4 py-3"><span class="text-xs text-slate-300">' + (ret.customer_name || 'Walk-in') + '</span></td>';
        html += '<td class="px-4 py-3"><span class="text-xs text-slate-500 font-mono">' + (ret.invoice_number || 'N/A') + '</span></td>';
        html += '<td class="px-4 py-3 text-right"><span class="text-xs font-bold ' + (high ? 'text-red-400' : 'text-white') + '">' + CURRENCY + ' ' + number_format(ret.amount) + '</span></td>';
        html += '<td class="px-4 py-3"><span class="text-[11px] text-slate-500">' + formatDate(ret.created_at) + '</span></td>';
        html += '<td class="px-4 py-3 text-right">';
        html += '<div class="flex items-center justify-end gap-1">';
        html += '<a href="view_sell_return.php?id=' + ret.id + '" class="w-7 h-7 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:text-amber-400 hover:border-amber-500/30 transition-all" title="View"><i class="fas fa-eye text-[10px]"></i></a>';
        if (ret.status === 'pending') {
            html += '<button onclick="quickApprove(' + ret.id + ')" class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 hover:bg-emerald-500/20 transition-all" title="Approve"><i class="fas fa-check text-[10px]"></i></button>';
        }
        html += '</div></td></tr>';
    });
    tbody.innerHTML = html;
}

function renderPagination(pagination) {
    var container = document.getElementById('pagination-container');
    if (!pagination || pagination.total_pages <= 1) { container.innerHTML = ''; return; }
    var current = pagination.current_page || 1;
    var total = pagination.total_pages || 1;
    var html = '<div class="flex items-center justify-between">';
    html += '<span class="text-[11px] text-slate-500">Showing ' + ((current-1)*PER_PAGE+1) + ' - ' + Math.min(current*PER_PAGE, pagination.total||0) + ' of ' + (pagination.total||0) + '</span>';
    html += '<div class="flex items-center gap-1">';
    html += '<button onclick="loadReturns(' + (current-1) + ')" ' + (current<=1?'disabled':'') + ' class="w-7 h-7 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-all"><i class="fas fa-chevron-left text-[10px]"></i></button>';
    var startPage = Math.max(1, current - 2);
    var endPage = Math.min(total, current + 2);
    if (startPage > 1) { html += '<span class="px-2 text-xs text-slate-500">...</span>'; }
    for (var i = startPage; i <= endPage; i++) {
        var active = i === current ? 'bg-amber-500/15 border-amber-500/30 text-amber-400' : 'bg-slate-800/60 border-slate-700/40 text-slate-400 hover:text-white';
        html += '<button onclick="loadReturns(' + i + ')" class="w-7 h-7 rounded-lg border flex items-center justify-center text-[11px] font-medium transition-all ' + active + '">' + i + '</button>';
    }
    if (endPage < total) { html += '<span class="px-2 text-xs text-slate-500">...</span>'; }
    html += '<button onclick="loadReturns(' + (current+1) + ')" ' + (current>=total?'disabled':'') + ' class="w-7 h-7 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition-all"><i class="fas fa-chevron-right text-[10px]"></i></button>';
    html += '</div></div>';
    container.innerHTML = html;
}

function updateStatBars(stats) {
    if (!stats) return;
    var total = stats.total || 1;
    var bars = { 'stat-total': stats.total||0, 'stat-completed': stats.completed||0, 'stat-pending': stats.pending||0, 'stat-rejected': stats.rejected||0 };
    for (var id in bars) {
        var el = document.getElementById(id); if (!el) continue;
        var bar = el.closest('.stat-card-wrap') ? el.closest('.stat-card-wrap').querySelector('[data-bar-width]') : null;
        if (bar) { bar.setAttribute('data-bar-width', Math.min(100, (bars[id]/total)*100)); bar.style.width = '0%'; }
    }
    setTimeout(animateBars, 100);
}

function toggleSelectAll() {
    var master = document.getElementById('select-all');
    var boxes = document.querySelectorAll('.return-checkbox');
    boxes.forEach(function(cb) { cb.checked = master.checked; });
}
function updateSelectAllState() {
    var boxes = document.querySelectorAll('.return-checkbox');
    var checked = document.querySelectorAll('.return-checkbox:checked');
    document.getElementById('select-all').checked = boxes.length > 0 && checked.length === boxes.length;
}

function changePerPage(val) {
    PER_PAGE = parseInt(val);
    loadReturns(1);
}

function quickApprove(id) {
    if (!confirm('Approve this return?')) return;
    console.log('Approve return', id);
}

function exportReturns() {
    alert('Export functionality coming soon');
}

// Search debounce
var searchTimeout;
document.getElementById('search-input').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        CURRENT_SEARCH = e.target.value;
        loadReturns(1);
    }, 400);
});

// Status filter pills
document.querySelectorAll('.filter-pill').forEach(function(pill) {
    pill.addEventListener('click', function() {
        document.querySelectorAll('.filter-pill').forEach(function(p) { p.classList.remove('active'); });
        this.classList.add('active');
        CURRENT_STATUS = this.getAttribute('data-status');
        loadReturns(1);
    });
});

function openAnalyticsModal() {
    var modal = document.getElementById('analytics-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(loadCharts, 100);
}

function closeAnalyticsModal() {
    var modal = document.getElementById('analytics-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function loadCharts() {
    var trendCtx = document.getElementById('trend-chart');
    if (trendCtx && !trendCtx.chart) {
        trendCtx.chart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Returns',
                    data: [12, 19, 8, 15, 22, 18, 14],
                    borderColor: '#fbbf24',
                    backgroundColor: 'rgba(251,191,36,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#fbbf24',
                    pointBorderColor: '#1e293b',
                    pointBorderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#64748b', font: { size: 10 } } }, y: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#64748b', font: { size: 10 } } } } }
        });
    }
    var reasonCtx = document.getElementById('reason-chart');
    if (reasonCtx && !reasonCtx.chart) {
        reasonCtx.chart = new Chart(reasonCtx, {
            type: 'doughnut',
            data: {
                labels: ['Damaged', 'Wrong Item', 'Not Satisfied', 'Other'],
                datasets: [{
                    data: [30, 25, 20, 25],
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#64748b'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 10 }, padding: 15, usePointStyle: true } } }, cutout: '65%' }
        });
    }
    var monthlyCtx = document.getElementById('monthly-chart');
    if (monthlyCtx && !monthlyCtx.chart) {
        monthlyCtx.chart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Returns',
                    data: [45, 52, 38, 65, 48, 55],
                    backgroundColor: 'rgba(251,191,36,0.2)',
                    borderColor: '#fbbf24',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } }, y: { grid: { color: 'rgba(255,255,255,0.03)' }, ticks: { color: '#64748b', font: { size: 10 } } } } }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    animateValue('stat-total', <?php echo (int)($return_stats['total_returns'] ?? 0); ?>, 800);
    animateValue('stat-completed', <?php echo (int)($return_stats['completed'] ?? 0); ?>, 800);
    animateValue('stat-pending', <?php echo (int)($return_stats['pending'] ?? 0); ?>, 800);
    animateValue('stat-rejected', <?php echo (int)($return_stats['rejected'] ?? 0); ?>, 800);
    animateValue('stat-avg', <?php echo (int)(($return_stats['completed'] ?? 0) > 0 ? ($return_stats['completed_amount'] ?? 0) / $return_stats['completed'] : 0); ?>, 800);
    setTimeout(animateBars, 500);
    loadReturns(1);
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>