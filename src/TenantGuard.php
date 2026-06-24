<?php
/**
 * Tenant Guard - Zero-Trust Implementation
 * Middleware for intercepting requests and enforcing tenant context
 */

require_once __DIR__ . '/TenantContext.php';

class TenantGuard {
    /**
     * Enforce tenant context on all requests
     * Call this at the beginning of every request lifecycle
     */
    public static function enforce(Request $request = null): void {
        try {
            $context = TenantContext::getInstance();

            // Log context for debugging (only in development)
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("TENANT GUARD: Context validated - " . $context);
            }

            // Additional request-level validations can be added here
            self::validateRequestHeaders($request);
            self::validateRequestParameters($request);

        } catch (SecurityException $e) {
            self::handleSecurityViolation($e, $request);
        } catch (Exception $e) {
            self::handleGenericError($e, $request);
        }
    }

    /**
     * Enforce tenant context for AJAX requests
     * Includes additional validation for asynchronous calls
     */
    public static function enforceAjax(Request $request = null): void {
        // Basic enforcement first
        self::enforce($request);

        try {
            // Validate AJAX tenant token
            self::validateAjaxToken();

            // Additional AJAX-specific validations
            self::validateAjaxHeaders();

        } catch (SecurityException $e) {
            self::handleAjaxSecurityViolation($e);
        }
    }

    /**
     * Validate request headers for security
     */
    private static function validateRequestHeaders(Request $request = null): void {
        // Check for suspicious headers that might indicate tampering
        $suspiciousHeaders = [
            'X-Tenant-Override',
            'X-Company-Spoof',
            'X-Branch-Override',
        ];

        foreach ($suspiciousHeaders as $header) {
            if (self::getHeader($header, $request)) {
                throw new SecurityException("Suspicious header detected: {$header}");
            }
        }
    }

    /**
     * Validate request parameters for tenant context tampering
     */
    private static function validateRequestParameters(Request $request = null): void {
        $context = TenantContext::getInstance();

        // Check for tenant_id override in GET/POST (cross-tenant access control)
        if (isset($_GET['tenant_id']) || isset($_POST['tenant_id'])) {
            if (!$context->allowCrossTenant()) {
                throw new SecurityException("Unauthorized tenant parameter override: tenant_id");
            }
            error_log("SaaS ADMIN OVERRIDE: tenant_id override by user " . $context->getUserId());
        }

        // Check for branch_id override — allow if matching current branch or user has switch permission
        $requestedBranchId = null;
        if (isset($_GET['branch_id'])) {
            $requestedBranchId = (int) $_GET['branch_id'];
        } elseif (isset($_POST['branch_id'])) {
            $requestedBranchId = (int) $_POST['branch_id'];
        }

        if ($requestedBranchId !== null && $requestedBranchId > 0) {
            $currentBranchId = function_exists('get_current_branch_id') ? (int) (get_current_branch_id() ?? 0) : 0;

            // No-op: branch_id matches current session branch — safe, not an override
            if ($requestedBranchId === $currentBranchId) {
                return;
            }

            // Actual branch switch requested — check permission
            $canSwitchBranch = (function_exists('is_super_admin') && is_super_admin())
                || (function_exists('check_permission') && check_permission('branches.view'));

            if (!$canSwitchBranch) {
                throw new SecurityException('Unauthorized branch parameter override');
            }

            error_log("Branch switch authorized for user " . ($context->getUserId() ?? 'unknown') .
                " from {$currentBranchId} to {$requestedBranchId}");
        }
    }

    /**
     * Validate AJAX tenant token
     */
    private static function validateAjaxToken(): void {
        $context = TenantContext::getInstance();

        // Check for X-Tenant-Token header
        $token = self::getHeader('X-Tenant-Token');

        if (!$token) {
            throw new SecurityException('Missing AJAX tenant token');
        }

        // Validate token matches session
        $sessionToken = $_SESSION['tenant_token'] ?? null;
        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            throw new SecurityException('Invalid AJAX tenant token');
        }
    }

    /**
     * Validate AJAX-specific headers
     */
    private static function validateAjaxHeaders(): void {
        // Ensure proper AJAX headers
        $contentType = self::getHeader('Content-Type');
        $xhrHeader = self::getHeader('X-Requested-With');

        if (!$xhrHeader || $xhrHeader !== 'XMLHttpRequest') {
            throw new SecurityException('Invalid AJAX request headers');
        }
    }

    /**
     * Handle security violations
     */
    private static function handleSecurityViolation(SecurityException $e, Request $request = null): void {
        // Log detailed security violation
        $context = TenantContext::getInstance();
        $userInfo = $context->getUserId() ? "User ID: {$context->getUserId()}" : 'No user context';
        $requestInfo = self::getRequestInfo($request);

        error_log("SECURITY VIOLATION: {$e->getMessage()} | {$userInfo} | {$requestInfo}");

        // Return appropriate error response
        if (self::isAjaxRequest()) {
            self::sendJsonError('Security violation detected', 403);
        } else {
            self::sendHtmlError('Access Denied', 'A security violation was detected. Please contact support if this persists.', 403);
        }

        exit;
    }

    /**
     * Handle AJAX security violations
     */
    private static function handleAjaxSecurityViolation(SecurityException $e): void {
        // Log and return JSON error for AJAX
        $context = TenantContext::getInstance();
        error_log("AJAX SECURITY VIOLATION: {$e->getMessage()} | User ID: {$context->getUserId()}");

        self::sendJsonError('Security violation detected', 403);
        exit;
    }

    /**
     * Handle generic errors
     */
    private static function handleGenericError(Exception $e, Request $request = null): void {
        $context = TenantContext::getInstance();
        $requestInfo = self::getRequestInfo($request);

        error_log("TENANT GUARD ERROR: {$e->getMessage()} | User ID: {$context->getUserId()} | {$requestInfo}");

        if (self::isAjaxRequest()) {
            self::sendJsonError('An error occurred', 500);
        } else {
            self::sendHtmlError('Error', 'An unexpected error occurred. Please try again.', 500);
        }

        exit;
    }

    /**
     * Get request information for logging
     */
    private static function getRequestInfo(Request $request = null): string {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        return "Method: {$method} | URI: {$uri} | IP: {$ip} | UA: " . substr($userAgent, 0, 100);
    }

    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get HTTP header value
     */
    private static function getHeader(string $name, Request $request = null): ?string {
        $headerName = 'HTTP_' . str_replace('-', '_', strtoupper($name));

        if (isset($_SERVER[$headerName])) {
            return $_SERVER[$headerName];
        }

        // Check alternative header formats
        $alternatives = [
            str_replace('_', '-', $headerName),
            strtolower($name),
            ucwords(str_replace(['_', '-'], ' ', strtolower($name)), '- ')
        ];

        foreach ($alternatives as $alt) {
            if (isset($_SERVER[$alt])) {
                return $_SERVER[$alt];
            }
        }

        return null;
    }

    /**
     * Send JSON error response
     */
    private static function sendJsonError(string $message, int $code = 500): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
    }

    /**
     * Send HTML error response
     */
    private static function sendHtmlError(string $title, string $message, int $code = 500): void {
        http_response_code($code);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($title); ?></title>
            <style>
                body { font-family: system-ui, sans-serif; background: #0b1021; color: #e5e7eb; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .error-container { text-align: center; max-width: 500px; padding: 2rem; }
                .error-code { font-size: 6rem; font-weight: bold; color: #ef4444; margin-bottom: 1rem; }
                .error-title { font-size: 2rem; font-weight: bold; margin-bottom: 1rem; }
                .error-message { font-size: 1.1rem; color: #9ca3af; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-code"><?php echo $code; ?></div>
                <h1 class="error-title"><?php echo htmlspecialchars($title); ?></h1>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            </div>
        </body>
        </html>
        <?php
    }
}

/**
 * Simple Request wrapper for dependency injection
 */
class Request {
    private $headers = [];
    private $method;
    private $uri;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = getallheaders();
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getUri(): string {
        return $this->uri;
    }

    public function getHeader(string $name): ?string {
        return $this->headers[$name] ?? null;
    }

    public function getHeaders(): array {
        return $this->headers;
    }
}
