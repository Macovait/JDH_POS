<?php
declare(strict_types=1);

/**
 * Plugin Manager Admin Page
 * Lists, activates, and deactivates plugins.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Auth check (super admin or system admin permission)
if (empty($_SESSION['user_id']) || (!is_super_admin() && !check_permission('system.admin'))) {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db_connection();
$tenantId = $_SESSION['tenant_id'] ?? null;

require_once SRC_PATH . '/Plugin/PluginManager.php';
require_once SRC_PATH . '/Plugin/PluginRegistry.php';
require_once SRC_PATH . '/Plugin/PluginInterface.php';
require_once SRC_PATH . '/Plugin/AbstractPlugin.php';

$manager = new \JDH\POS\Plugin\PluginManager($pdo, $tenantId);
$plugins = $manager->getPluginStatuses();

$page_title = 'Plugin Manager';
ob_start();
?>

<div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Plugin Manager</h1>
                <p class="text-sm text-slate-400 mt-1">Manage installed plugins and extensions</p>
            </div>
            <div class="text-xs text-slate-500 bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-800">
                <?= count(array_filter($plugins, fn($p) => $p['status'] === 'active')) ?> active / <?= count($plugins) ?> total
            </div>
        </div>

        <?php if (empty($plugins)): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-puzzle-piece text-slate-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">No plugins found</h3>
                <p class="text-sm text-slate-400">Place plugin folders in <code class="bg-slate-800 px-2 py-0.5 rounded text-amber-400">/plugins/</code> to get started.</p>
            </div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($plugins as $plugin): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 flex items-start justify-between hover:border-slate-700 transition-colors">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-base font-semibold text-white"><?= htmlspecialchars($plugin['name']) ?></h3>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium uppercase tracking-wide <?= $plugin['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-700 text-slate-400' ?>">
                                    <?= $plugin['status'] ?>
                                </span>
                                <span class="text-xs text-slate-500">v<?= htmlspecialchars($plugin['version']) ?></span>
                            </div>
                            <p class="text-sm text-slate-400 mb-2"><?= htmlspecialchars($plugin['description']) ?></p>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="text-slate-500">By <?= htmlspecialchars($plugin['author'] ?: 'Unknown') ?></span>
                                <?php if (!empty($plugin['features'])): ?>
                                    <span class="text-slate-600">|</span>
                                    <?php foreach ($plugin['features'] as $feature): ?>
                                        <span class="bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded"><?= htmlspecialchars($feature) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <?php if ($plugin['status'] === 'active'): ?>
                                <form method="post" action="<?= base_url('ajax/plugin_manager.php') ?>" class="inline" onsubmit="return confirm('Deactivate this plugin?');">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($plugin['slug']) ?>">
                                    <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg border border-slate-700 transition-colors">
                                        Deactivate
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= base_url('ajax/plugin_manager.php') ?>" class="inline">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($plugin['slug']) ?>">
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs rounded-lg transition-colors">
                                        Activate
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-8 bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-3">How to Create a Plugin</h3>
            <ol class="text-sm text-slate-400 space-y-2 list-decimal list-inside">
                <li>Create a folder inside <code class="bg-slate-800 px-1.5 py-0.5 rounded text-amber-400">/plugins/</code> (e.g., <code class="bg-slate-800 px-1.5 py-0.5 rounded">my-plugin</code>).</li>
                <li>Add a <code class="bg-slate-800 px-1.5 py-0.5 rounded">plugin.php</code> file with a class extending <code class="bg-slate-800 px-1.5 py-0.5 rounded">AbstractPlugin</code>.</li>
                <li>Optionally add a <code class="bg-slate-800 px-1.5 py-0.5 rounded">manifest.json</code> for metadata.</li>
                <li>Return here and click <strong>Activate</strong>.</li>
            </ol>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
