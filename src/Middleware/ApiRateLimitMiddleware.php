<?php
declare(strict_types=1);

/**
 * API Rate Limit Middleware
 *
 * Enforces per-IP request throttling on API and AJAX endpoints.
 * Uses the existing RateLimiter class with database or Redis backend.
 *
 * Usage:
 *   require_once __DIR__ . '/../../src/Middleware/ApiRateLimitMiddleware.php';
 *   \Jakababa\Middleware\enforce_api_rate_limit();       // Standard API limit
 *   \Jakababa\Middleware\enforce_api_rate_limit('login'); // Login-specific limit
 *
 * @package Jakababa\Middleware
 * @version 1.0.0
 */

namespace Jakababa\Middleware;

require_once __DIR__ . '/../Security/RateLimiter.php';

use JDH\Security\RateLimiter;

/**
 * Get the client IP address, handling proxies.
 */
function get_client_ip(): string
{
    // Check for forwarded IP (when behind a reverse proxy)
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
    foreach ($headers as $header) {
        $value = $_SERVER[$header] ?? '';
        if (!empty($value)) {
            // X-Forwarded-For may contain multiple IPs; take the first (client)
            $ips = array_map('trim', explode(',', $value));
            $ip = $ips[0];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get the singleton RateLimiter instance.
 */
function get_rate_limiter(): RateLimiter
{
    static $limiter = null;
    if ($limiter === null) {
        $driver = 'database';
        if (extension_loaded('redis') && (getenv('REDIS_ENABLED') === 'true' || getenv('REDIS_HOST'))) {
            $driver = 'redis';
        }

        $limiter = new RateLimiter([
            'driver' => $driver,
            'limits' => [
                'api_requests' => [
                    'max' => (int)(getenv('API_RATE_LIMIT') ?: 100),
                    'decay' => (int)(getenv('API_RATE_LIMIT_WINDOW') ?: 60),
                ],
                'login_attempts' => [
                    'max' => (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5),
                    'decay' => (int)(getenv('LOGIN_LOCKOUT_TIME') ?: 900),
                ],
                'password_reset' => [
                    'max' => 3,
                    'decay' => 3600,
                ],
                'mfa_attempts' => [
                    'max' => 3,
                    'decay' => 300,
                ],
            ],
            'block_duration' => 3600,
        ]);
    }
    return $limiter;
}

/**
 * Enforce rate limiting on the current request.
 *
 * Checks the client IP against the specified rate limit type.
 * Returns 429 with Retry-After header if the limit is exceeded.
 *
 * @param string $type Rate limit type key (api_requests, login_attempts, etc.)
 */
function enforce_api_rate_limit(string $type = 'api_requests'): void
{
    $limiter = get_rate_limiter();
    $clientIp = get_client_ip();

    // Check if already blocked
    if ($limiter->isBlocked($clientIp, $type)) {
        $retryAfter = $limiter->getBlockRemaining($clientIp, $type);
        send_rate_limit_response($retryAfter, 0);
    }

    // Increment and check
    $result = $limiter->increment($clientIp, $type);

    // Set rate limit headers
    header('X-RateLimit-Limit: ' . $result['max_attempts']);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset: ' . (time() + $result['reset_in']));

    if ($result['remaining'] <= 0) {
        $retryAfter = $limiter->getBlockRemaining($clientIp, $type);
        send_rate_limit_response($retryAfter ?: 60, 0);
    }
}

/**
 * Send a 429 Too Many Requests response and exit.
 */
function send_rate_limit_response(int $retryAfter, int $remaining): void
{
    http_response_code(429);
    header('Content-Type: application/json');
    header('Retry-After: ' . $retryAfter);
    header('X-RateLimit-Remaining: ' . $remaining);

    echo json_encode([
        'success' => false,
        'error' => 'Too many requests. Please try again later.',
        'retry_after' => $retryAfter,
    ]);
    exit;
}

/**
 * Reset rate limit for a client (e.g., after successful login).
 *
 * @param string $type Rate limit type to reset
 */
function reset_rate_limit(string $type = 'login_attempts'): void
{
    $limiter = get_rate_limiter();
    $clientIp = get_client_ip();
    $limiter->reset($clientIp, $type);
}
