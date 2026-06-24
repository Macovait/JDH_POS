<?php
// CLI-only restore script: php scripts/restore_cli.php /path/to/file.sql
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Use from CLI: php scripts/restore_cli.php path/to/file.sql\n");
    exit(1);
}
if ($argc < 2) { fwrite(STDERR, "Missing sql file path\n"); exit(1); }
$file = $argv[1];
if (!is_readable($file)) { fwrite(STDERR, "File not readable: $file\n"); exit(1); }
require_once __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';
$cmd = "mysql -u".escapeshellarg($config['db_user'])." -p".escapeshellarg($config['db_pass'])." ".escapeshellarg($config['db_name'])." < " . escapeshellarg($file);
passthru($cmd, $code);
if ($code === 0) echo "Restore complete from $file\n"; else echo "Restore failed code $code\n";