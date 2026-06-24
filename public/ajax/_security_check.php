<?php
/**
 * AJAX Security Check
 * Include this at the top of all AJAX endpoints
 * Enforces authentication and permissions
 */

require_once __DIR__ . '/../../src/Security/ApiSecurity.php';

// Enforce API security
ApiSecurity::enforce();
?>
