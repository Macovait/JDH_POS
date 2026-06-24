<?php
/**
 * Replace transparent white styles with slate equivalents across admin pages
 */

$adminDir = __DIR__ . '/../admin';

$skipPatterns = [
    '*Model.php', 'bootstrap.php', 'ajax_*.php', 'dash_data.php',
    'layouts/*', 'components/*',
    'check_*.php', 'diag_*.php', 'fix_*.php', 'apply_*.php',
    'db_diag.php', 'database_audit.php',
];

$replacements = [
    // Transparent white backgrounds → slate
    'bg-white/5' => 'bg-slate-700/30',
    'bg-white/10' => 'bg-slate-700/50',
    'bg-white/15' => 'bg-slate-700/60',
    'bg-white/20' => 'bg-slate-700/70',
    'bg-white/30' => 'bg-slate-700/80',
    'hover:bg-white/5' => 'hover:bg-slate-700/30',
    'hover:bg-white/10' => 'hover:bg-slate-700/50',
    'hover:bg-white/20' => 'hover:bg-slate-700/70',
    // Transparent white borders → slate
    'border-white/5' => 'border-slate-700/30',
    'border-white/10' => 'border-slate-700/50',
    'border-white/20' => 'border-slate-700/70',
    // Text colors
    'text-gray-400' => 'text-slate-400',
    'text-gray-500' => 'text-slate-500',
    'text-gray-600' => 'text-slate-600',
    // Remaining bg-gray
    'bg-gray-400' => 'bg-slate-400',
    'bg-gray-500' => 'bg-slate-500',
    'bg-gray-600' => 'bg-slate-600',
    'bg-gray-700' => 'bg-slate-700',
    'bg-gray-800' => 'bg-slate-800',
    'bg-gray-900' => 'bg-slate-900',
    // border-gray
    'border-gray-400' => 'border-slate-400',
    'border-gray-500' => 'border-slate-500',
    'border-gray-600' => 'border-slate-600',
    'border-gray-700' => 'border-slate-700',
    'border-gray-800' => 'border-slate-800',
    'border-gray-900' => 'border-slate-900',
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

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Fixed: $relPath\n";
    }
}

echo "Done.\n";
