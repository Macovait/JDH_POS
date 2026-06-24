<?php
/**
 * Simple logout script
 */

session_name('jakababa_saas_sid');
session_start();

// Destroy session
$_SESSION = [];
session_destroy();

// Redirect to login
header('Location: auth/login.php');
exit;
?>
