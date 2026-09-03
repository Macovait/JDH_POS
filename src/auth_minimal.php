<?php
/**
 * Minimal auth compatibility layer for the legacy test suite.
 *
 * This file provides the small subset of session helpers that the project's
 * verification tests expect, without depending on the full SaaS auth stack.
 */

if (!function_exists('start_session_minimal')) {
    function start_session_minimal(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // CLI/test runners may emit output before the session is started.
            // Buffering avoids the "headers already sent" warning while preserving
            // the expected session behavior for assertions.
            if (!headers_sent() && function_exists('ob_get_level') && ob_get_level() === 0) {
                ob_start();
            }

            @session_start();
        }
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        start_session_minimal();
        return !empty($_SESSION['is_logged_in']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): ?int
    {
        start_session_minimal();
        $userId = $_SESSION['user_id'] ?? null;

        if (is_array($userId)) {
            $userId = $userId[0] ?? null;
        }

        return is_numeric((string) $userId) ? (int) $userId : null;
    }
}

if (!function_exists('get_current_tenant_id')) {
    function get_current_tenant_id(): ?int
    {
        start_session_minimal();
        $tenantId = $_SESSION['tenant_id'] ?? null;

        if (is_array($tenantId)) {
            $tenantId = $tenantId[0] ?? null;
        }

        return is_numeric((string) $tenantId) ? (int) $tenantId : null;
    }
}
