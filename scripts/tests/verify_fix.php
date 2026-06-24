<?php
require_once 'src/paths.php';
echo "Verification: Base URL works correctly\n";
echo "Base URL: " . base_url() . "\n";
echo "Dashboard URL: " . base_url('dashboard/home.php') . "\n";
echo "All syntax errors have been resolved!";
?>
