<?php
$file = 'c:\xampp\htdocs\JDH_POS\src\functions.php';
$content = file_get_contents($file);

// Check around check_permission
$pos = strpos($content, 'function check_permission');
if ($pos !== false) {
    echo "Found check_permission at $pos\n";
    echo bin2hex(substr($content, $pos, 400)) . "\n";
}

// Check around get_settings db_fetch_all
$pos2 = strpos($content, "SELECT setting_key, setting_value FROM settings");
if ($pos2 !== false) {
    echo "Found settings query at $pos2\n";
    echo bin2hex(substr($content, $pos2 - 100, 500)) . "\n";
}
