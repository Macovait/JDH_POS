<?php
/**
 * Production Deployment Script
 * Zero-downtime deployment with safety checks.
 */
if (php_sapi_name() !== 'cli') die('CLI only');
echo "[Deploy] Starting...\n";
$root = realpath(__DIR__ . '/..');
$steps = [
    'check_php_version' => function() { $v = PHP_VERSION_ID; if ($v < 80200) throw new Exception('PHP 8.2+ required'); echo "  ok PHP " . PHP_VERSION . "\n"; },
    'check_extensions' => function() { $req = ['pdo_mysql','json','mbstring','openssl','curl','zip']; foreach ($req as $ext) { if (!extension_loaded($ext)) throw new Exception("Missing extension: {$ext}"); } echo "  ok extensions\n"; },
    'backup_db' => function() use ($root) { $file = $root . '/backups/pre_deploy_' . date('Ymd_His') . '.sql'; exec("mysqldump -h" . escapeshellarg(getenv('DB_HOST')?:'localhost') . " -u" . escapeshellarg(getenv('DB_USER')?:'root') . " -p" . escapeshellarg(getenv('DB_PASS')?:'') . " " . escapeshellarg(getenv('DB_NAME')?:'jdh_pos') . " > " . escapeshellarg($file) . " 2>/dev/null", $o, $rc); if ($rc !== 0) throw new Exception('DB backup failed'); echo "  ok backup\n"; },
    'run_migrations' => function() use ($root) { foreach (glob($root . '/database/migrations/*.php') as $f) { echo "  running " . basename($f) . "\n"; exec("php " . escapeshellarg($f) . " 2>&1", $o, $rc); if ($rc !== 0) throw new Exception("Migration failed: " . basename($f)); } echo "  ok migrations\n"; },
    'clear_cache' => function() use ($root) { $cacheDir = $root . '/storage/cache'; if (is_dir($cacheDir)) { foreach (glob($cacheDir . '/*') as $f) @unlink($f); } echo "  ok cache cleared\n"; },
];
foreach ($steps as $name => $fn) { try { echo "[Step] {$name}\n"; $fn(); } catch (Exception $e) { echo "  FAIL: " . $e->getMessage() . "\n"; exit(1); } }
echo "[Deploy] Complete.\n";
