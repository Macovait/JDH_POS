<?php
/**
 * Bulk Admin Tailwind CSS Update Script
 * Replaces custom CSS classes with pure Tailwind utility classes
 * to match public/pos/all_sales.php style.
 */

$adminDir = __DIR__ . '/../admin';

// Files to skip (models, ajax, bootstrap, debug/diagnostic, components, layouts)
$skipPatterns = [
    '*Model.php',
    'bootstrap.php',
    'ajax_*.php',
    'dash_data.php',
    'components/*',
    'layouts/*',
    'check_*.php',
    'diag_*.php',
    'fix_*.php',
    'apply_*.php',
    'db_diag.php',
    'database_audit.php',
];

// Safe string replacements: [find => replace]
// Order matters - longer/more specific patterns first
$replacements = [
    // Cards
    'glass-card' => 'bg-slate-800/40 border border-slate-700/60 rounded-xl',
    'card-laravel' => 'bg-slate-800/40 border border-slate-700/60 rounded-xl',
    'card-modern' => 'bg-slate-800/40 border border-slate-700/60 rounded-xl',
    'card-hover' => 'transition-all duration-200 hover:border-amber-500/30 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/40',

    // Buttons
    'btn-laravel btn-primary' => 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors',
    'btn-laravel btn-secondary' => 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors',
    'btn-laravel btn-danger' => 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors',
    'btn-laravel' => 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors',

    // Forms
    'form-input-modern' => 'w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500',
    'form-select-modern' => 'px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500',
    'form-textarea-modern' => 'w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-y',

    // Alerts
    'alert-success' => 'flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm',
    'alert-error' => 'flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm',
    'alert-info' => 'flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-400 text-sm',
    'alert-warning' => 'flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm',

    // Tables - replace class attribute on table-modern
    'table-modern' => 'w-full min-w-[860px]',

    // Section header/title
    'section-header' => 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5',
    'section-title' => 'text-lg font-bold text-white',

    // Stats
    'stat-laravel' => 'bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5',
    'stat-mini' => 'bg-slate-800/40 border border-slate-700/60 rounded-xl p-3',
    'stat-icon-laravel' => 'w-8 h-8 rounded-lg flex items-center justify-center',
    'stat-icon' => 'w-8 h-8 rounded-lg flex items-center justify-center',
    'stat-value' => 'text-sm font-bold',
    'stat-label' => 'text-xs text-slate-500 leading-none mt-0.5',
    'stat-change' => 'text-[10px]',
    'metric-up' => 'text-emerald-400',
    'metric-down' => 'text-red-400',
    'metric-neutral' => 'text-slate-400',

    // Chart containers
    'chart-container' => 'bg-slate-800/40 border border-slate-700/60 rounded-xl p-3',
    'chart-title' => 'text-sm font-semibold text-white',

    // Badges
    'badge-mini' => 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
    'status-badge' => 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
    'status-active' => 'bg-emerald-500/20 text-emerald-400',
    'status-trial' => 'bg-blue-500/20 text-blue-400',
    'status-suspended' => 'bg-red-500/20 text-red-400',
    'status-pending' => 'bg-amber-500/20 text-amber-400',
    'status-cancelled' => 'bg-slate-500/20 text-slate-400',

    // Other
    'progress-bar' => 'h-2 bg-slate-700/50 rounded-full overflow-hidden',
    'progress-fill' => 'h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all duration-500',

    // Color palette: gray → slate (careful with bg-gray-900 which is body bg)
    'text-gray-400' => 'text-slate-400',
    'text-gray-500' => 'text-slate-500',
    'text-gray-600' => 'text-slate-600',
    'text-gray-300' => 'text-slate-300',
    'text-gray-200' => 'text-slate-200',
    'border-gray-800' => 'border-slate-700/60',
    'border-gray-700' => 'border-slate-700',
    'bg-gray-800' => 'bg-slate-800',
    'bg-gray-700' => 'bg-slate-700',
    'hover:bg-white/5' => 'hover:bg-slate-700/30',
    'bg-white/5' => 'bg-slate-800/50',
];

function shouldSkip(string $file, array $patterns): bool {
    $name = basename($file);
    foreach ($patterns as $pat) {
        if (fnmatch($pat, $name) || fnmatch($pat, str_replace('\\', '/', $file))) {
            return true;
        }
    }
    return false;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($adminDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$changed = [];
$skipped = [];

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relPath = str_replace($adminDir . DIRECTORY_SEPARATOR, '', $path);

    if (shouldSkip($relPath, $skipPatterns)) {
        $skipped[] = $relPath;
        continue;
    }

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $find => $replace) {
        $content = str_replace($find, $replace, $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changed[] = $relPath;
    }
}

echo "=== Admin Tailwind Bulk Update ===\n\n";
echo "Changed files (" . count($changed) . "):\n";
foreach ($changed as $f) {
    echo "  ✓ $f\n";
}
echo "\nSkipped files (" . count($skipped) . "):\n";
foreach ($skipped as $f) {
    echo "  - $f\n";
}
echo "\nDone.\n";
