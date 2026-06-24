<?php
/**
 * Phinx Database Migration Configuration
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load .env if exists
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv($line);
    }
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'jdh_pos';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $dbHost,
            'name' => $dbName,
            'user' => $dbUser,
            'pass' => $dbPass,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'name' => 'jdh_pos_test',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => getenv('DB_PASS') ?: 'root',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => getenv('DB_HOST') ?: 'db',
            'name' => getenv('DB_NAME') ?: 'jdh_pos',
            'user' => getenv('DB_USER') ?: 'jdh_user',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ]
    ],
    'version_order' => 'creation'
];
