<?php
/**
 * Cleanup duplicate Tailwind classes in admin files
 */

$adminDir = __DIR__ . '/../admin';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($adminDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') continue;
    $path = $fileInfo->getPathname();
    $content = file_get_contents($path);
    $original = $content;

    // Find class="..." attributes and deduplicate
    $content = preg_replace_callback('/class\s*=\s*"([^"]*)"/', function($matches) {
        $classes = preg_split('/\s+/', trim($matches[1]));
        $seen = [];
        $unique = [];
        foreach ($classes as $cls) {
            if ($cls === '') continue;
            if (!isset($seen[$cls])) {
                $seen[$cls] = true;
                $unique[] = $cls;
            }
        }
        return 'class="' . implode(' ', $unique) . '"';
    }, $content);

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Cleaned: " . str_replace($adminDir . DIRECTORY_SEPARATOR, '', $path) . "\n";
    }
}

echo "Done.\n";
