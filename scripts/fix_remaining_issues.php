<?php
/**
 * Fix remaining styling issues after bulk conversion
 */

$adminDir = __DIR__ . '/../admin';

$skipPatterns = [
    '*Model.php', 'bootstrap.php', 'ajax_*.php', 'dash_data.php',
    'layouts/*', 'components/*',
    'check_*.php', 'diag_*.php', 'fix_*.php', 'apply_*.php',
    'db_diag.php', 'database_audit.php',
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

    // Fix duplicate w-full patterns from replacement
    $content = preg_replace('/class="w-full px-4 py-2 w-full/', 'class="w-full', $content);
    $content = preg_replace('/class="w-full px-3 py-2 w-full/', 'class="w-full', $content);

    // Fix remaining bg-white/10 cancel buttons
    $content = str_replace(
        'px-6 py-2 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 transition-colors',
        'px-6 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors',
        $content
    );
    $content = str_replace(
        'px-3 py-1 bg-white/10 rounded text-white hover:bg-white/20 transition-colors',
        'px-3 py-1 bg-slate-800 border border-slate-700 rounded text-slate-400 hover:bg-slate-700 hover:text-white transition-colors',
        $content
    );
    $content = str_replace(
        "'bg-white/10 text-white hover:bg-white/20'",
        "'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'",
        $content
    );
    $content = str_replace(
        "'bg-white/10 rounded text-white hover:bg-white/20'",
        "'bg-slate-800 border border-slate-700 rounded text-slate-400 hover:bg-slate-700 hover:text-white'",
        $content
    );

    // Fix bg-accent (custom color) → amber
    $content = str_replace("'bg-accent text-slate-900'", "'bg-amber-500 text-slate-900'", $content);
    $content = str_replace('bg-accent', 'bg-amber-500', $content);

    // Fix focus:border-accent
    $content = str_replace('focus:border-accent', 'focus:ring-1 focus:ring-amber-500', $content);

    // Fix any remaining bg-white/10 border border-white/20 on elements
    $content = str_replace('bg-white/10 border border-white/20', 'bg-slate-800 border border-slate-700', $content);

    // Clean up duplicate w-full inside class attributes (general cleanup)
    $content = preg_replace_callback('/class="([^"]*)"/', function($m) {
        $cls = preg_replace('/\bw-full\b.*\bw-full\b/', 'w-full', $m[1]);
        return 'class="' . $cls . '"';
    }, $content);

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Fixed: $relPath\n";
    }
}

echo "Done.\n";
