<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();

$page_title = 'Documentation';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

ob_start();
?>

<div class="space-y-4">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Reference</div>
            <h1 class="text-lg font-bold text-white">Documentation</h1>
            <p class="text-sm text-slate-500 mt-0.5 mt-1">Guides and reference for JDH POS</p>
        </div>
    </div>

    <!-- Guide Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="#" class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden hover:border-amber-500/30 transition group">
            <div class=""></div>
            <i class="fas fa-cash-register text-amber-400 text-2xl mb-3 block"></i>
            <h4 class="text-lg font-bold text-white text-sm mb-1 group-hover:text-amber-400 transition">POS Guide</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs">Learn how to use the point of sale system.</p>
        </a>
        <a href="#" class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden hover:border-amber-500/30 transition group">
            <div class="" style="--accent-color: #10b981;"></div>
            <i class="fas fa-boxes-stacked text-emerald-400 text-2xl mb-3 block"></i>
            <h4 class="text-lg font-bold text-white text-sm mb-1 group-hover:text-emerald-400 transition">Inventory</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs">Manage products, stock, and suppliers.</p>
        </a>
        <a href="#" class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden hover:border-amber-500/30 transition group">
            <div class="" style="--accent-color: #3b82f6;"></div>
            <i class="fas fa-chart-line text-blue-400 text-2xl mb-3 block"></i>
            <h4 class="text-lg font-bold text-white text-sm mb-1 group-hover:text-blue-400 transition">Reports</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs">Generate sales, finance, and analytics reports.</p>
        </a>
        <a href="#" class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden hover:border-amber-500/30 transition group">
            <div class="" style="--accent-color: #8b5cf6;"></div>
            <i class="fas fa-users text-violet-400 text-2xl mb-3 block"></i>
            <h4 class="text-lg font-bold text-white text-sm mb-1 group-hover:text-violet-400 transition">CRM & HR</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs">Customer management and employee tools.</p>
        </a>
    </div>

    <!-- Getting Started Panel -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <div class=""></div>
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-rocket text-amber-400"></i>
            <h3 class="text-lg font-bold text-white text-base">Getting Started</h3>
        </div>
        <div class="space-y-4">
            <div class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-amber-500/10 flex items-center justify-center flex-shrink-0 text-amber-400 text-sm font-bold">1</div>
                <div>
                    <h4 class="text-slate-200 font-medium text-sm">Set up your company profile</h4>
                    <p class="text-sm text-slate-500 mt-0.5 text-xs mt-1">Add your business name, logo, and branches from the Settings page.</p>
                </div>
            </div>
            <div class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-amber-500/10 flex items-center justify-center flex-shrink-0 text-amber-400 text-sm font-bold">2</div>
                <div>
                    <h4 class="text-slate-200 font-medium text-sm">Add your products</h4>
                    <p class="text-sm text-slate-500 mt-0.5 text-xs mt-1">Go to Inventory > Add Product and create your catalog with pricing and stock levels.</p>
                </div>
            </div>
            <div class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-amber-500/10 flex items-center justify-center flex-shrink-0 text-amber-400 text-sm font-bold">3</div>
                <div>
                    <h4 class="text-slate-200 font-medium text-sm">Make your first sale</h4>
                    <p class="text-sm text-slate-500 mt-0.5 text-xs mt-1">Open the POS, add items to the cart, and complete the checkout.</p>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
