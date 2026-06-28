<?php
declare(strict_types=1);

/**
 * Centralized CORS Handler
 *
 * Validates the request Origin against a configurable allowlist and sets
 * appropriate CORS headers. Replaces hardcoded Access-Control-Allow-Origin: *
 * across all endpoints.
 *
 * Configuration via environment:
 *   API_ALLOWED_ORIGINS=https://shop.example.com,https://admin.example.com
 *   API_ALLOWED_ORIGINS=*  (only for development)
 *
 * @package Jakababa\Security
 * @version 1.0.0
 */

namespace Jakababa\Security;

class CorsHandler
{
    private static ?self $instance = null;
    private array $allowedOrigins;
    private bool $allowAll;

    private function __construct()
    {
        $originsEnv = getenv('API_ALLOWED_ORIGINS') ?: '';
        $appUrl = getenv('APP_URL') ?: '';
        $storefrontUrl = getenv('STOREFRONT_URL') ?: '';

        if ($originsEnv === '*') {
            $this->allowAll = true;
            $this->allowedOrigins = [];
            return;
        }

        $this->allowAll = false;
        $origins = [];

        // Parse comma-separated origins from env
        if (!empty($originsEnv)) {
            $origins = array_map('trim', explode(',', $originsEnv));
        }

        // Always allow the app's own origin and storefront
        if (!empty($appUrl)) {
            $origins[] = rtrim($appUrl, '/');
            $parsed = parse_url($appUrl);
            if (isset($parsed['scheme'], $parsed['host'])) {
                $origins[] = $parsed['scheme'] . '://' . $parsed['host'];
            }
        }

        if (!empty($storefrontUrl)) {
            $origins[] = rtrim($storefrontUrl, '/');
        }

        // Always allow same-origin requests (localhost variants for dev)
        $origins[] = 'http://localhost';
        $origins[] = 'http://localhost:3000';
        $origins[] = 'http://127.0.0.1';

        // Deduplicate and normalize
        $this->allowedOrigins = array_unique(array_filter(array_map(
            fn(string $o) => rtrim(strtolower(trim($o)), '/'),
            $origins
        )));
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set CORS headers based on the request Origin.
     *
     * Call this at the top of any endpoint that needs CORS.
     * Handles OPTIONS preflight requests automatically.
     */
    public function handleCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($this->isOriginAllowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Company-ID, X-Tenant-ID, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Check if a given origin is in the allowlist.
     */
    public function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true; // Same-origin requests have no Origin header
        }

        if ($this->allowAll) {
            return true;
        }

        $normalized = rtrim(strtolower(trim($origin)), '/');
        return in_array($normalized, $this->allowedOrigins, true);
    }
}

/**
 * Convenience function for endpoints that don't use the class directly.
 * Call at the top of any AJAX/API endpoint to set CORS headers.
 */
function apply_cors_headers(): void
{
    CorsHandler::getInstance()->handleCors();
}
