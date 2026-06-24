<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

$page_title = 'Support Center';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

ob_start();
?>

<div class="space-y-4">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Help Desk</div>
            <h1 class="text-lg font-bold text-white">Support Center</h1>
            <p class="text-sm text-slate-500 mt-0.5 mt-1">Get help with JDH POS</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors"><i class="fas fa-plus"></i> New Ticket</button>
        </div>
    </div>

    <!-- Feature Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="" style="--accent-color: #f59e0b;"></div>
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/10 flex items-center justify-center mb-4 shadow-lg shadow-amber-500/10">
                <i class="fas fa-life-ring text-amber-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-white text-base mb-1">Help Desk</h3>
            <p class="text-sm text-slate-500 mt-0.5 mb-4">Submit a support ticket and our team will respond within 24 hours.</p>
            <button class="w-full py-2 px-4 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition text-sm font-medium">
                Open Ticket
            </button>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="" style="--accent-color: #10b981;"></div>
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/20 to-teal-500/10 flex items-center justify-center mb-4 shadow-lg shadow-emerald-500/10">
                <i class="fas fa-book text-emerald-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-white text-base mb-1">Documentation</h3>
            <p class="text-sm text-slate-500 mt-0.5 mb-4">Browse guides, tutorials, and FAQs for every feature.</p>
            <a href="<?php echo base_url('dashboard/docs.php'); ?>" class="block w-full py-2 px-4 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition text-sm font-medium text-center">
                Browse Docs
            </a>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="" style="--accent-color: #3b82f6;"></div>
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/10 flex items-center justify-center mb-4 shadow-lg shadow-blue-500/10">
                <i class="fas fa-video text-blue-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-white text-base mb-1">Video Tutorials</h3>
            <p class="text-sm text-slate-500 mt-0.5 mb-4">Watch step-by-step walkthroughs on YouTube.</p>
            <button class="w-full py-2 px-4 rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500/20 transition text-sm font-medium">
                Watch Videos
            </button>
        </div>
    </div>

    <!-- FAQ Panel -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <div class=""></div>
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-circle-question text-amber-400"></i>
            <h3 class="text-lg font-bold text-white text-base">Frequently Asked Questions</h3>
        </div>
        <div class="space-y-3">
            <details class="group rounded-lg border border-slate-700/40 bg-slate-900/30">
                <summary class="cursor-pointer p-4 text-sm font-medium text-slate-300 flex items-center justify-between select-none">
                    How do I add a new product?
                    <i class="fas fa-chevron-down text-slate-500 group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="px-4 pb-4 text-slate-400 text-sm">
                    Navigate to Inventory > Add Product and fill in the required fields including name, price, stock quantity, and category.
                </div>
            </details>
            <details class="group rounded-lg border border-slate-700/40 bg-slate-900/30">
                <summary class="cursor-pointer p-4 text-sm font-medium text-slate-300 flex items-center justify-between select-none">
                    How do I process a refund?
                    <i class="fas fa-chevron-down text-slate-500 group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="px-4 pb-4 text-slate-400 text-sm">
                    Go to Sales > Returns, select the original sale, and follow the refund workflow to process the return.
                </div>
            </details>
            <details class="group rounded-lg border border-slate-700/40 bg-slate-900/30">
                <summary class="cursor-pointer p-4 text-sm font-medium text-slate-300 flex items-center justify-between select-none">
                    How do I switch branches?
                    <i class="fas fa-chevron-down text-slate-500 group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="px-4 pb-4 text-slate-400 text-sm">
                    Use the branch selector in the top header bar to switch between available branches instantly.
                </div>
            </details>
        </div>
    </div>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
