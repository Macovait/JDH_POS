<?php
/**
 * Fix remaining gray color references in admin files
 */

$adminDir = __DIR__ . '/../admin';

$skipPatterns = [
    '*Model.php', 'bootstrap.php', 'ajax_*.php', 'dash_data.php',
    'components/*', 'layouts/*',
    'check_*.php', 'diag_*.php', 'fix_*.php', 'apply_*.php',
    'db_diag.php', 'database_audit.php',
];

$replacements = [
    'bg-gray-900/30' => 'bg-slate-900/30',
    'bg-gray-900/50' => 'bg-slate-900/50',
    'bg-gray-900/60' => 'bg-slate-900/60',
    'hover:bg-gray-900/50' => 'hover:bg-slate-900/50',
    'hover:bg-gray-900/30' => 'hover:bg-slate-900/30',
    'text-gray-900' => 'text-slate-900',
    'text-gray-800' => 'text-slate-800',
    'text-gray-700' => 'text-slate-700',
    'text-gray-600' => 'text-slate-600',
    'border-gray-900' => 'border-slate-900',
    'border-gray-600' => 'border-slate-600',
    'bg-gray-600' => 'bg-slate-600',
    'bg-gray-500' => 'bg-slate-500',
    'from-gray-500' => 'from-slate-500',
    'to-gray-500' => 'to-slate-500',
    'via-gray-500' => 'via-slate-500',
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
