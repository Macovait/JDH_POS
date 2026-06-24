<?php
/**
 * Billing Dashboard - Jakababa POS
 * Pure Tailwind CSS
 */

$page_title = 'Billing & Subscription';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

use Jakababa\Services\PaymentService;

$current_branch_id = get_current_branch_id();
$tenantId = $_SESSION['tenant_id'] ?? null;
if (!$tenantId) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = get_db_connection();

// Get subscription info
$subscription = null;
$billingHistory = [];
$plans = [];
$stripePublishableKey = '';
$billingError = null;

try {
    if (!class_exists('Jakababa\Services\PaymentService')) {
        require_once SRC_PATH . '/Services/PaymentService.php';
    }

    $paymentService = new PaymentService($pdo);

    if ($tenantId) {
        $subscription = $paymentService->getTenantSubscription((int) $tenantId);
        $billingHistory = $paymentService->getBillingHistory((int) $tenantId);
    }
} catch (\Throwable $e) {
    error_log('Billing subscription error: ' . $e->getMessage());
    $billingError = 'Unable to load billing information. Please try again later.';
}

try {
    // Try pos_plans (SaaS schema) first, then fallback to plans (legacy)
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tables = array_map('strtolower', $tables);

    if (in_array('pos_plans', $tables)) {
        $stmt = $pdo->query("SELECT * FROM pos_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array('plans', $tables)) {
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE status = 'active' AND branch_id = ? ORDER BY price_monthly ASC");
        $stmt->execute([(int) $current_branch_id]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    error_log('Billing plans error: ' . $e->getMessage());
}

try {
    $config = require __DIR__ . '/../../config/payment.php';
    $stripePublishableKey = $config['stripe']['publishable_key'] ?? '';
} catch (\Throwable $e) {
    error_log('Billing config error: ' . $e->getMessage());
}

// Handle messages
$message = $_SESSION['billing_message'] ?? null;
$messageType = $_SESSION['billing_message_type'] ?? null;
unset($_SESSION['billing_message'], $_SESSION['billing_message_type']);

// Status color helper
$sub_color = 'slate';
if ($subscription) {
    $sub_color = $subscription['status'] === 'active' ? 'emerald' : ($subscription['status'] === 'trial' ? 'amber' : 'rose');
}

$invoice_count = count($billingHistory);
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-file-invoice-dollar text-amber-400"></i> Billing & Subscription
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Manage your plan and payment methods</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="toggleAutoRefresh()" id="autoRefreshBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors" title="Auto-refresh subscription status">
            <i class="fas fa-sync-alt text-xs"></i> <span id="autoRefreshLabel">Auto: Off</span>
        </button>
        <button onclick="window.location.reload()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors" title="Refresh now">
            <i class="fas fa-redo text-xs"></i> Refresh
        </button>
        <a href="home.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Dashboard
        </a>
    </div>
</div>

<?php if ($billingError): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($billingError); ?>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg <?php echo $messageType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400'; ?> text-sm">
    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<?php if ($subscription): ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-tag text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400 truncate">$<?php echo number_format($subscription['amount'], 2); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $subscription['billing_cycle'] === 'yearly' ? 'Yearly' : 'Monthly'; ?> Cost</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-calendar text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400 truncate"><?php echo date('M j, Y', strtotime($subscription['next_billing_date'])); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Next Billing</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-users text-purple-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-purple-400 truncate"><?php echo ($subscription['users_count'] ?? 0) . ' / ' . ($subscription['max_users'] ?? '∞'); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Team Members</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-store text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400 truncate"><?php echo ($subscription['branches_count'] ?? 0) . ' / ' . ($subscription['max_branches'] ?? '∞'); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Branches</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Left column: Current plan + Available Plans -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Current Subscription -->
        <?php if ($subscription): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-crown text-amber-400"></i> Current Plan
                </h3>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-<?php echo $sub_color; ?>-500/15 text-<?php echo $sub_color; ?>-400 ring-1 ring-<?php echo $sub_color; ?>-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-<?php echo $sub_color; ?>-400"></span>
                    <?php echo ucfirst($subscription['status']); ?>
                </span>
            </div>
            <div class="p-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-<?php echo $sub_color; ?>-500/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-crown text-<?php echo $sub_color; ?>-400 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider font-medium">Plan</p>
                        <h2 class="text-base font-bold text-white"><?php echo htmlspecialchars($subscription['plan_name']); ?></h2>
                    </div>
                </div>

                <!-- Usage bars -->
                <?php if (($subscription['max_users'] ?? 0) > 0): ?>
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-slate-400">Team Members</span>
                        <span class="text-xs text-slate-300 font-medium"><?php echo ($subscription['users_count'] ?? 0) . ' / ' . $subscription['max_users']; ?></span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo min(100, (($subscription['users_count'] ?? 0) / $subscription['max_users']) * 100); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (($subscription['max_branches'] ?? 0) > 0): ?>
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-slate-400">Branches</span>
                        <span class="text-xs text-slate-300 font-medium"><?php echo ($subscription['branches_count'] ?? 0) . ' / ' . $subscription['max_branches']; ?></span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo min(100, (($subscription['branches_count'] ?? 0) / $subscription['max_branches']) * 100); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex gap-2">
                    <button onclick="showUpgradeModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                        <i class="fas fa-arrow-up text-xs"></i> Upgrade Plan
                    </button>
                    <?php if ($subscription['status'] === 'active'): ?>
                    <button onclick="cancelSubscription()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">
                        <i class="fas fa-pause text-xs"></i> Cancel
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 text-center">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-crown text-amber-400 text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-white mb-1">No Active Subscription</h3>
            <p class="text-sm text-slate-500 mb-4 max-w-md mx-auto">Choose a plan to unlock premium features, unlimited storage, and priority support.</p>
            <button onclick="showUpgradeModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                <i class="fas fa-rocket text-xs"></i> Get Started
            </button>
        </div>
        <?php endif; ?>

        <!-- Available Plans -->
        <div id="plans-section">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-layer-group text-amber-400"></i> Available Plans
                </h3>
                <span class="text-xs text-slate-500">Compare features to find your perfect fit</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php foreach ($plans as $plan):
                    $is_current = $subscription && $subscription['plan_id'] == $plan['id'];
                ?>
                <div class="bg-slate-800/40 border <?php echo $is_current ? 'border-amber-500/40' : 'border-slate-700/60'; ?> rounded-xl p-4 relative overflow-hidden hover:border-amber-500/30 transition-colors">
                    <?php if ($is_current): ?>
                    <span class="absolute top-2 right-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30">Current</span>
                    <?php endif; ?>

                    <h4 class="font-semibold text-white text-sm mb-1"><?php echo htmlspecialchars($plan['name']); ?></h4>
                    <p class="text-xs text-slate-500 mb-3 line-clamp-2 min-h-[32px]"><?php echo htmlspecialchars($plan['description'] ?? 'Perfect for growing businesses'); ?></p>

                    <div class="inline-flex items-baseline gap-0.5 px-2.5 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 mb-4">
                        <span class="text-lg font-bold text-amber-400">$<?php echo number_format($plan['price_monthly'], 0); ?></span>
                        <span class="text-xs text-slate-500">/mo</span>
                    </div>

                    <div class="space-y-2 mb-4">
                        <div class="flex items-center gap-2 text-xs text-slate-300">
                            <div class="w-6 h-6 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
                                <i class="fas fa-users text-purple-400 text-[10px]"></i>
                            </div>
                            <span><?php echo $plan['max_users']; ?> users</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-300">
                            <div class="w-6 h-6 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
                                <i class="fas fa-store text-emerald-400 text-[10px]"></i>
                            </div>
                            <span><?php echo $plan['max_branches']; ?> branches</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-300">
                            <div class="w-6 h-6 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                                <i class="fas fa-box text-blue-400 text-[10px]"></i>
                            </div>
                            <span><?php echo $plan['max_products']; ?> products</span>
                        </div>
                    </div>

                    <?php if (!$is_current): ?>
                    <button onclick="selectPlan(<?php echo $plan['id']; ?>)" class="w-full py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/20 hover:border-amber-500/50 transition-colors">
                        Select Plan
                    </button>
                    <?php else: ?>
                    <div class="w-full py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium text-center">
                        <i class="fas fa-check mr-1"></i> Active Plan
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right column: Invoices + Quick Actions -->
    <div class="space-y-4">

        <!-- Recent Invoices -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-amber-400"></i> Recent Invoices
                    <span class="text-xs text-slate-500 font-normal">(<?php echo $invoice_count; ?>)</span>
                </h3>
            </div>
            <?php if (empty($billingHistory)): ?>
            <div class="px-4 py-10 text-center">
                <i class="fas fa-receipt text-3xl text-slate-700 block mb-3"></i>
                <p class="text-slate-500 text-sm">No invoices yet</p>
                <p class="text-xs text-slate-600 mt-1">Invoices will appear here after your first payment</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-700/40 max-h-72 overflow-y-auto">
                <?php foreach (array_slice($billingHistory, 0, 10) as $invoice):
                    $inv_paid = $invoice['status'] === 'paid';
                    $inv_color = $inv_paid ? 'emerald' : 'amber';
                ?>
                <div class="flex items-center justify-between px-3 py-2.5 hover:bg-slate-700/30 transition-colors cursor-pointer" onclick="viewInvoice(<?php echo (int)$invoice['id']; ?>)">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-<?php echo $inv_color; ?>-500/10 flex items-center justify-center shrink-0">
                            <i class="fas fa-<?php echo $inv_paid ? 'check' : 'clock'; ?> text-<?php echo $inv_color; ?>-400 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-white">Invoice #<?php echo str_pad($invoice['id'], 4, '0', STR_PAD_LEFT); ?></p>
                            <p class="text-xs text-slate-500"><?php echo date('M j, Y', strtotime($invoice['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-amber-400">$<?php echo number_format($invoice['amount'], 2); ?></p>
                        <div class="flex items-center justify-end gap-1.5 mt-0.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-<?php echo $inv_color; ?>-500/15 text-<?php echo $inv_color; ?>-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-<?php echo $inv_color; ?>-400"></span>
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                            <span class="text-[10px] text-slate-600 group-hover:text-slate-400"><i class="fas fa-eye"></i></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-bolt text-amber-400"></i> Quick Actions
                </h3>
            </div>
            <div class="divide-y divide-slate-700/40">
                <?php
                $quick_links = [
                    ['href' => '../billing/mpesa_settings.php', 'icon' => 'fa-mobile-alt', 'color' => 'green',  'title' => 'M-Pesa Settings',  'desc' => 'Configure mobile payments'],
                    ['href' => '../billing/sms_settings.php',   'icon' => 'fa-paper-plane', 'color' => 'blue',   'title' => 'SMS Settings',      'desc' => 'Manage notification SMS'],
                    ['href' => '../billing/payment_history.php','icon' => 'fa-receipt',      'color' => 'amber',  'title' => 'Payment History',   'desc' => 'View all transactions'],
                ];
                foreach ($quick_links as $link):
                ?>
                <a href="<?php echo $link['href']; ?>" class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-700/30 transition-colors group">
                    <div class="w-8 h-8 rounded-lg bg-<?php echo $link['color']; ?>-500/10 flex items-center justify-center shrink-0">
                        <i class="fas <?php echo $link['icon']; ?> text-<?php echo $link['color']; ?>-400 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-slate-200"><?php echo $link['title']; ?></p>
                        <p class="text-xs text-slate-500"><?php echo $link['desc']; ?></p>
                    </div>
                    <i class="fas fa-chevron-right text-slate-600 text-xs group-hover:text-slate-400 transition-colors"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Detail Modal -->
<div id="invoiceModal" class="fixed inset-0 bg-black/60  hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white">Invoice Details</h3>
            <button onclick="closeInvoiceModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="invoiceModalContent" class="space-y-3 text-sm"></div>
        <div class="flex gap-2 pt-3 mt-3 border-t border-slate-700/60">
            <button onclick="downloadCurrentInvoice()" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-download text-xs"></i> Download
            </button>
            <button onclick="closeInvoiceModal()" class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<?php if (!empty($stripePublishableKey)): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe('<?php echo htmlspecialchars($stripePublishableKey); ?>');

    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        const colors = { success: 'bg-emerald-800/95 border-l-4 border-emerald-400 text-white', error: 'bg-red-800/95 border-l-4 border-red-400 text-white', warning: 'bg-amber-800/95 border-l-4 border-amber-400 text-white', info: 'bg-slate-800 border-l-4 border-blue-400 text-slate-200' };
        toast.className = `${colors[type] || colors.info} px-4 py-3 rounded-lg text-sm font-medium shadow-2xl `;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>${escapeHtml(message)}`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
    }

    function escapeHtml(t) { if(!t) return ''; const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

    let currentInvoice = null;
    const invoiceData = <?php echo json_encode(array_slice($billingHistory, 0, 10)); ?>;

    function viewInvoice(invoiceId) {
        currentInvoice = invoiceData.find(inv => inv.id == invoiceId);
        if (!currentInvoice) return;
        const content = document.getElementById('invoiceModalContent');
        const paid = currentInvoice.status === 'paid';
        const color = paid ? 'emerald' : 'amber';
        content.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-${color}-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-receipt text-${color}-400 text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-white">Invoice #${String(currentInvoice.id).padStart(4,'0')}</p>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-${color}-500/15 text-${color}-400 ring-1 ring-${color}-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-${color}-400"></span>${escapeHtml(currentInvoice.status.charAt(0).toUpperCase() + currentInvoice.status.slice(1))}
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div><p class="text-slate-500 mb-0.5">Amount</p><p class="text-white font-semibold">$${Number(currentInvoice.amount).toFixed(2)}</p></div>
                <div><p class="text-slate-500 mb-0.5">Date</p><p class="text-white">${new Date(currentInvoice.created_at).toLocaleDateString()}</p></div>
                <div><p class="text-slate-500 mb-0.5">Billing Cycle</p><p class="text-white">${escapeHtml(currentInvoice.billing_cycle || 'Monthly')}</p></div>
                <div><p class="text-slate-500 mb-0.5">Gateway</p><p class="text-white">${escapeHtml(currentInvoice.gateway || 'Stripe')}</p></div>
            </div>
            ${currentInvoice.description ? `<div><p class="text-slate-500 mb-0.5 text-xs">Description</p><p class="text-slate-300 text-xs">${escapeHtml(currentInvoice.description)}</p></div>` : ''}
        `;
        document.getElementById('invoiceModal').classList.remove('hidden');
    }

    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.add('hidden');
        currentInvoice = null;
    }

    function downloadCurrentInvoice() {
        if (!currentInvoice) return;
        const text = `INVOICE #${String(currentInvoice.id).padStart(4,'0')}\nStatus: ${currentInvoice.status.toUpperCase()}\nAmount: $${Number(currentInvoice.amount).toFixed(2)}\nDate: ${new Date(currentInvoice.created_at).toLocaleDateString()}\nBilling Cycle: ${currentInvoice.billing_cycle || 'Monthly'}\nGateway: ${currentInvoice.gateway || 'Stripe'}\n${currentInvoice.description ? 'Description: ' + currentInvoice.description : ''}`;
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice_${String(currentInvoice.id).padStart(4,'0')}.txt`;
        a.click();
        URL.revokeObjectURL(url);
        showToast('Invoice downloaded', 'success');
    }

    function selectPlan(planId) {
        showToast('Processing checkout...', 'info');
        fetch('<?php echo base_url("api/create-checkout.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: planId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.checkout_url) {
                if (data.gateway === 'stripe' && data.session_id) {
                    stripe.redirectToCheckout({ sessionId: data.session_id });
                } else {
                    window.location.href = data.checkout_url;
                }
            } else {
                showToast(data.error || 'Failed to create checkout', 'error');
            }
        })
        .catch(err => {
            console.error('Checkout error:', err);
            showToast('Failed to initiate checkout. Please try again.', 'error');
        });
    }

    function cancelSubscription() {
        if (!confirm('Are you sure you want to cancel your subscription?')) return;
        showToast('Cancelling subscription...', 'info');
        fetch('<?php echo base_url("api/cancel-subscription.php"); ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Subscription cancelled successfully', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.error || 'Failed to cancel', 'error');
            }
        })
        .catch(err => {
            console.error('Cancel error:', err);
            showToast('Failed to cancel subscription. Please try again.', 'error');
        });
    }

    function showUpgradeModal() {
        document.getElementById('plans-section').scrollIntoView({ behavior: 'smooth' });
    }

    // Auto-hide flash messages
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) setTimeout(() => { el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(() => el.remove(), 500); }, 5000);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeInvoiceModal();
    });

    // Close modal on backdrop click
    document.getElementById('invoiceModal').addEventListener('click', e => { if (e.target === document.getElementById('invoiceModal')) closeInvoiceModal(); });

    // Auto-refresh toggle
    let autoRefreshInterval = null;
    function toggleAutoRefresh() {
        const btn = document.getElementById('autoRefreshBtn');
        const label = document.getElementById('autoRefreshLabel');
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            if (label) label.textContent = 'Auto: Off';
            if (btn) { btn.classList.remove('text-emerald-400','border-emerald-500/40'); btn.classList.add('text-slate-400','border-slate-700'); }
            showToast('Auto-refresh disabled', 'info');
        } else {
            autoRefreshInterval = setInterval(() => { window.location.reload(); }, 30000);
            if (label) label.textContent = 'Auto: 30s';
            if (btn) { btn.classList.remove('text-slate-400','border-slate-700'); btn.classList.add('text-emerald-400','border-emerald-500/40'); }
            showToast('Auto-refresh enabled (30s)', 'success');
        }
    }
</script>
<?php else: ?>
<script>
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        const colors = { success: 'bg-emerald-800/95 border-l-4 border-emerald-400 text-white', error: 'bg-red-800/95 border-l-4 border-red-400 text-white', warning: 'bg-amber-800/95 border-l-4 border-amber-400 text-white', info: 'bg-slate-800 border-l-4 border-blue-400 text-slate-200' };
        toast.className = `${colors[type] || colors.info} px-4 py-3 rounded-lg text-sm font-medium shadow-2xl `;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>${message}`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
    }

    function escapeHtml(t) { if(!t) return ''; const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

    let currentInvoice = null;
    const invoiceData = <?php echo json_encode(array_slice($billingHistory, 0, 10)); ?>;

    function viewInvoice(invoiceId) {
        currentInvoice = invoiceData.find(inv => inv.id == invoiceId);
        if (!currentInvoice) return;
        const content = document.getElementById('invoiceModalContent');
        const paid = currentInvoice.status === 'paid';
        const color = paid ? 'emerald' : 'amber';
        content.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-${color}-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-receipt text-${color}-400 text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-white">Invoice #${String(currentInvoice.id).padStart(4,'0')}</p>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-${color}-500/15 text-${color}-400 ring-1 ring-${color}-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-${color}-400"></span>${escapeHtml(currentInvoice.status.charAt(0).toUpperCase() + currentInvoice.status.slice(1))}
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div><p class="text-slate-500 mb-0.5">Amount</p><p class="text-white font-semibold">$${Number(currentInvoice.amount).toFixed(2)}</p></div>
                <div><p class="text-slate-500 mb-0.5">Date</p><p class="text-white">${new Date(currentInvoice.created_at).toLocaleDateString()}</p></div>
                <div><p class="text-slate-500 mb-0.5">Billing Cycle</p><p class="text-white">${escapeHtml(currentInvoice.billing_cycle || 'Monthly')}</p></div>
                <div><p class="text-slate-500 mb-0.5">Gateway</p><p class="text-white">${escapeHtml(currentInvoice.gateway || 'Stripe')}</p></div>
            </div>
            ${currentInvoice.description ? `<div><p class="text-slate-500 mb-0.5 text-xs">Description</p><p class="text-slate-300 text-xs">${escapeHtml(currentInvoice.description)}</p></div>` : ''}
        `;
        document.getElementById('invoiceModal').classList.remove('hidden');
    }

    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.add('hidden');
        currentInvoice = null;
    }

    function downloadCurrentInvoice() {
        if (!currentInvoice) return;
        const text = `INVOICE #${String(currentInvoice.id).padStart(4,'0')}\nStatus: ${currentInvoice.status.toUpperCase()}\nAmount: $${Number(currentInvoice.amount).toFixed(2)}\nDate: ${new Date(currentInvoice.created_at).toLocaleDateString()}\nBilling Cycle: ${currentInvoice.billing_cycle || 'Monthly'}\nGateway: ${currentInvoice.gateway || 'Stripe'}\n${currentInvoice.description ? 'Description: ' + currentInvoice.description : ''}`;
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice_${String(currentInvoice.id).padStart(4,'0')}.txt`;
        a.click();
        URL.revokeObjectURL(url);
        showToast('Invoice downloaded', 'success');
    }

    function selectPlan(planId) {
        showToast('Payment gateway not configured. Please contact support.', 'warning');
    }

    function cancelSubscription() {
        showToast('Payment gateway not configured. Please contact support.', 'warning');
    }

    function showUpgradeModal() {
        document.getElementById('plans-section').scrollIntoView({ behavior: 'smooth' });
    }

    // Auto-hide flash messages
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) setTimeout(() => { el.style.transition='opacity .5s'; el.style.opacity='0'; setTimeout(() => el.remove(), 500); }, 5000);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeInvoiceModal();
    });

    // Close modal on backdrop click
    document.getElementById('invoiceModal').addEventListener('click', e => { if (e.target === document.getElementById('invoiceModal')) closeInvoiceModal(); });

    // Auto-refresh toggle
    let autoRefreshInterval = null;
    function toggleAutoRefresh() {
        const btn = document.getElementById('autoRefreshBtn');
        const label = document.getElementById('autoRefreshLabel');
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            if (label) label.textContent = 'Auto: Off';
            if (btn) { btn.classList.remove('text-emerald-400','border-emerald-500/40'); btn.classList.add('text-slate-400','border-slate-700'); }
            showToast('Auto-refresh disabled', 'info');
        } else {
            autoRefreshInterval = setInterval(() => { window.location.reload(); }, 30000);
            if (label) label.textContent = 'Auto: 30s';
            if (btn) { btn.classList.remove('text-slate-400','border-slate-700'); btn.classList.add('text-emerald-400','border-emerald-500/40'); }
            showToast('Auto-refresh enabled (30s)', 'success');
        }
    }
</script>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
