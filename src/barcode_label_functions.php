<?php

function safe_require(string $fileName, string $baseDir = 'src', bool $required = true): void
{
    $paths = [
        __DIR__ . '/../' . $baseDir . '/' . $fileName,
        __DIR__ . '/' . $baseDir . '/' . $fileName,
        $baseDir . '/' . $fileName,
        $fileName,
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            if ($required) {
                require_once $path;
            } else {
                require_once $path;
            }
            return;
        }
    }

    if ($required) {
        throw new \RuntimeException("Required file not found: $fileName in $baseDir");
    }
}