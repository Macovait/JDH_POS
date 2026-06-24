<?php
/**
 * Download backup file
 * Enforces authentication and path traversal protection
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/src/paths.php';

if (function_exists('safe_require')) {
    safe_require('auth.php', 'src');
} else {
    require_once $root_path . '/src/auth.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

if (!check_permission('settings.manage')) {
    http_response_code(403);
    die('Permission denied');
}

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die('No file specified');
}

// Prevent path traversal
$file = basename($file);
if (preg_match('/\.\.|[\/\\\\]/', $file)) {
    http_response_code(400);
    die('Invalid filename');
}

$backup_dir = STORAGE_PATH . '/backups';
$filepath = $backup_dir . '/' . $file;

if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Verify file is inside backup directory
if (realpath($filepath) === false || strpos(realpath($filepath), realpath($backup_dir)) !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Log activity
if (function_exists('log_activity')) {
    log_activity('backup_download', 'Downloaded backup: ' . $file);
}

// Send file
$mime = 'application/octet-stream';
if (str_ends_with($file, '.gz', null, get_current_tenant_id())) {
    $mime = 'application/gzip';
} elseif (str_ends_with($file, '.zip')) {
    $mime = 'application/zip';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filepath);
exit;
