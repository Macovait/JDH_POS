<?php
// Clear opcache for the API index.php file
$apiFile = __DIR__ . '/public/api/v1/index.php';
if (function_exists('opcache_invalidate')) {
    $result = opcache_invalidate($apiFile, true);
    echo $result ? 'Opcache cleared for index.php' : 'Failed to clear opcache';
} else {
    echo 'Opcache not available';
}
