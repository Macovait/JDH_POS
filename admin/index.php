<?php
/**
 * Admin entry point
 *
 * Keeps the legacy /admin/ URL working by routing users to the
 * shared login or dashboard flow.
 */

require_once __DIR__ . '/bootstrap.php';

if (admin_is_authenticated()) {
    header('Location: ' . admin_url('dashboard.php'));
    exit;
}

header('Location: ' . admin_url('login.php'));
exit;
