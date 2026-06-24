<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();

$page_title = 'Projects';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

ob_start();
?>

<div class="space-y-4">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Management</div>
            <h1 class="text-lg font-bold text-white">Projects</h1>
            <p class="text-sm text-slate-500 mt-0.5 mt-1">Manage store initiatives and rollouts</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors"><i class="fas fa-plus"></i> New Project</button>
        </div>
    </div>

    <!-- Project Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="" style="--accent-color: #10b981;"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="px-2 py-1 rounded-md bg-emerald-500/10 text-emerald-400 text-xs font-medium">Active</span>
                <span class="text-sm text-slate-500 mt-0.5 text-xs">Due Oct 30</span>
            </div>
            <h4 class="text-lg font-bold text-white text-sm mb-1">Q4 Inventory Audit</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs mb-4">Complete full stock count across all branches before quarter end.</p>
            <div class="w-full bg-slate-700/50 rounded-full h-2 mb-3">
                <div class="bg-amber-500 h-2 rounded-full" style="width: 65%"></div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>65% complete</span>
                <div class="flex -space-x-2">
                    <div class="w-6 h-6 rounded-full bg-amber-500/20 border border-slate-700 flex items-center justify-center text-[10px] text-amber-400"><i class="fas fa-user"></i></div>
                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-slate-700 flex items-center justify-center text-[10px] text-emerald-400"><i class="fas fa-user"></i></div>
                </div>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="" style="--accent-color: #3b82f6;"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="px-2 py-1 rounded-md bg-blue-500/10 text-blue-400 text-xs font-medium">Planning</span>
                <span class="text-sm text-slate-500 mt-0.5 text-xs">Due Nov 15</span>
            </div>
            <h4 class="text-lg font-bold text-white text-sm mb-1">Loyalty Program Launch</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs mb-4">Roll out the new customer loyalty and rewards system.</p>
            <div class="w-full bg-slate-700/50 rounded-full h-2 mb-3">
                <div class="bg-blue-500 h-2 rounded-full" style="width: 25%"></div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>25% complete</span>
                <div class="flex -space-x-2">
                    <div class="w-6 h-6 rounded-full bg-blue-500/20 border border-slate-700 flex items-center justify-center text-[10px] text-blue-400"><i class="fas fa-user"></i></div>
                </div>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="" style="--accent-color: #f59e0b;"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="px-2 py-1 rounded-md bg-amber-500/10 text-amber-400 text-xs font-medium">In Review</span>
                <span class="text-sm text-slate-500 mt-0.5 text-xs">Due Sep 20</span>
            </div>
            <h4 class="text-lg font-bold text-white text-sm mb-1">Staff Training Materials</h4>
            <p class="text-sm text-slate-500 mt-0.5 text-xs mb-4">Prepare onboarding docs and training videos for new hires.</p>
            <div class="w-full bg-slate-700/50 rounded-full h-2 mb-3">
                <div class="bg-emerald-500 h-2 rounded-full" style="width: 90%"></div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>90% complete</span>
                <div class="flex -space-x-2">
                    <div class="w-6 h-6 rounded-full bg-amber-500/20 border border-slate-700 flex items-center justify-center text-[10px] text-amber-400"><i class="fas fa-user"></i></div>
                    <div class="w-6 h-6 rounded-full bg-blue-500/20 border border-slate-700 flex items-center justify-center text-[10px] text-blue-400"><i class="fas fa-user"></i></div>
                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-slate-700 flex items-center justify-center text-[10px] text-emerald-400"><i class="fas fa-user"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Panel -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
        <div class=""></div>
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-bolt text-amber-400"></i>
            <h3 class="text-lg font-bold text-white text-base">Recent Activity</h3>
        </div>
        <div class="space-y-3">
            <div class="flex items-center gap-3 text-sm">
                <div class="w-8 h-8 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xs"><i class="fas fa-check"></i></div>
                <div class="flex-1">
                    <p class="text-slate-300 text-xs">Inventory audit task <span class="text-emerald-400">completed</span></p>
                </div>
                <span class="text-sm text-slate-500 mt-0.5 text-xs">2h ago</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <div class="w-8 h-8 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 text-xs"><i class="fas fa-comment"></i></div>
                <div class="flex-1">
                    <p class="text-slate-300 text-xs">New comment added on Loyalty Program Launch</p>
                </div>
                <span class="text-sm text-slate-500 mt-0.5 text-xs">5h ago</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <div class="w-8 h-8 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400 text-xs"><i class="fas fa-plus"></i></div>
                <div class="flex-1">
                    <p class="text-slate-300 text-xs">New project created: Staff Training Materials</p>
                </div>
                <span class="text-sm text-slate-500 mt-0.5 text-xs">1d ago</span>
            </div>
        </div>
    </div>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
