<?php
/**
 * Print Invoice - Redirects to receipts/print_invoice.php
 * This file exists for backward compatibility with links expecting print_invoice.php in the pos/ folder
 */

require_once __DIR__ . '/../../src/paths.php';

// Preserve all query parameters
$query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$target = 'receipts/print_invoice.php' . $query_string;

// Use redirect (302) so browser updates the URL, or include if you want to keep URL
header('Location: ' . $target);
exit;
