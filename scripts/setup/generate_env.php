<?php
/**
 * Environment Setup Script
 * 
 * Generates a .env file from .env.example with fresh cryptographic secrets.
 * Run this after cloning the repository for the first time.
 *
 * Usage:
 *   php scripts/setup/generate_env.php
 *   php scripts/setup/generate_env.php --force   (overwrite existing .env)
 *
 * @package JDH_POS
 * @version 1.0
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__, 2);
$envFile = $rootDir . '/.env';
$exampleFile = $rootDir . '/.env.example';

// Check if .env already exists
if (file_exists($envFile) && !in_array('--force', $argv ?? [], true)) {
    echo "\033[33m⚠  .env already exists. Use --force to overwrite.\033[0m\n";
    echo "   Location: {$envFile}\n";
    exit(1);
}

// Ensure .env.example exists
if (!file_exists($exampleFile)) {
    echo "\033[31m✗  .env.example not found at: {$exampleFile}\033[0m\n";
    exit(1);
}

// Generate cryptographic secrets
$secrets = [
    'ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
];

echo "\033[36m🔧 Generating .env from .env.example...\033[0m\n\n";

// Read the example file
$content = file_get_contents($exampleFile);

// Replace empty secret values with generated ones
foreach ($secrets as $key => $value) {
    // Match lines like: KEY= or KEY=<empty>
    $content = preg_replace(
        '/^(' . preg_quote($key, '/') . ')=\s*$/m',
        '$1=' . $value,
        $content
    );
}

// Write the new .env file
if (file_put_contents($envFile, $content) === false) {
    echo "\033[31m✗  Failed to write .env file.\033[0m\n";
    exit(1);
}

// Set restrictive permissions (Unix only)
if (PHP_OS_FAMILY !== 'Windows') {
    chmod($envFile, 0600);
}

echo "\033[32m✓  .env created successfully.\033[0m\n";
echo "   Location: {$envFile}\n\n";
echo "\033[36mGenerated secrets:\033[0m\n";
foreach ($secrets as $key => $value) {
    $masked = substr($value, 0, 8) . '...' . substr($value, -4);
    echo "   {$key} = {$masked}\n";
}

echo "\n\033[33m⚡ Next steps:\033[0m\n";
echo "   1. Review .env and set your database credentials (DB_USER, DB_PASS)\n";
echo "   2. Set your APP_URL\n";
echo "   3. Configure payment gateways (M-Pesa keys) if needed\n";
echo "   4. Run: php scripts/reset_database.php --execute --force\n";
echo "   5. Run: php scripts/seed_default_users.php\n";
echo "\n\033[31m⚠  IMPORTANT: Never commit .env to version control!\033[0m\n";
