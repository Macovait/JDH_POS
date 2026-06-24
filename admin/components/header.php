<?php
/**
 * Admin Header Component
 */
require_once __DIR__ . '/../bootstrap.php';

$admin_name = admin_current_name();
$admin_role = admin_current_role();
?>
<header class="bg-slate-900/80 backdrop-blur border-b border-slate-700/60 sticky top-0 z-40">
    <div class="flex items-center justify-between px-6 py-3">
        <div class="flex items-center gap-4">
            <h2 class="text-white font-semibold"><?php echo $page_title ?? 'Admin Panel'; ?></h2>
        </div>

        <div class="flex items-center gap-4">
            <a href="<?php echo admin_url('notifications.php'); ?>" class="relative p-2 text-slate-400 hover:text-white transition">
                <i class="fas fa-bell"></i>
                <span class="absolute top-1 right-1 w-2 h-2 bg-amber-500 rounded-full"></span>
            </a>

            <div class="flex items-center gap-3 pl-4 border-l border-slate-700">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                    <i class="fas fa-user text-white text-xs"></i>
                </div>
                <div class="hidden md:block">
                    <p class="text-white text-sm font-medium"><?php echo htmlspecialchars($admin_name); ?></p>
                    <p class="text-slate-500 text-xs"><?php echo htmlspecialchars($admin_role); ?></p>
                </div>
            </div>
        </div>
    </div>
</header>
