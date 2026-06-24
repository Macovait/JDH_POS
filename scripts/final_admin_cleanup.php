<?php
/**
 * Final cleanup: standardize form styles and buttons to match all_sales.php
 */

$adminDir = __DIR__ . '/../admin';

$skipPatterns = [
    '*Model.php', 'bootstrap.php', 'ajax_*.php', 'dash_data.php',
    'layouts/*', 'components/*',
    'check_*.php', 'diag_*.php', 'fix_*.php', 'apply_*.php',
    'db_diag.php', 'database_audit.php',
];

$replacements = [
    // Form inputs: transparent white → opaque slate
    'bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:border-accent' => 'w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500',
    // Remove leading w-full if it creates double w-full
    'w-full w-full' => 'w-full',
    // btn-primary → amber button
    'btn-primary px-6 py-2 rounded-lg text-slate-900 font-semibold' => 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors',
    'btn-primary px-6 py-2 rounded-lg text-slate-900' => 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors',
    'btn-primary px-4 py-2 rounded-lg text-slate-900' => 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors',
    'class="btn-primary' => 'class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-slate-900 text-sm font-semibold hover:bg-amber-400 transition-colors',
    // bg-white/10 rounded text-white → bg-slate-800 border border-slate-700
    'bg-white/10 rounded text-white hover:bg-white/20' => 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white',
    'bg-white/10 rounded-lg text-white hover:bg-white/20' => 'bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white',
    // pagination specific
    "'bg-white/10 text-white hover:bg-white/20'" => "'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'",
    "'bg-white/10 rounded text-white hover:bg-white/20'" => "'bg-slate-800 border border-slate-700 rounded text-slate-400 hover:bg-slate-700 hover:text-white'",
    // duplicate rounded fix
    'rounded-xl rounded-2xl' => 'rounded-xl',
    'rounded-lg rounded-xl' => 'rounded-lg',
    // border-white/10 → border-slate-700/60
    'border-white/10' => 'border-slate-700/60',
    // text-slate-300 labels that should be slate-500
    'class="block text-slate-300 text-sm mb-2"' => 'class="block text-slate-500 text-xs mb-1.5"',
    // page header titles
    'text-2xl font-bold text-white' => 'text-lg font-bold text-white',
    'text-xl font-bold text-white' => 'text-lg font-bold text-white',
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

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') continue;
    $path = $fileInfo->getPathname();
    $relPath = str_replace($adminDir . DIRECTORY_SEPARATOR, '', $path);
    if (shouldSkip($relPath, $skipPatterns)) continue;

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $find => $replace) {
        $content = str_replace($find, $replace, $content);
    }

    // Clean duplicate w-full from double replacement
    $content = str_replace('w-full w-full', 'w-full', $content);
    $content = str_replace('w-full  w-full', 'w-full', $content);

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Fixed: $relPath\n";
    }
}

echo "Done.\n";
