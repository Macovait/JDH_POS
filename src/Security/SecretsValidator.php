<?php
declare(strict_types=1);

/**
 * Secrets Validator - Validates critical configuration on application boot
 *
 * Ensures that:
 * - Required secrets are present and non-empty
 * - Encryption keys meet minimum entropy requirements
 * - Known-compromised keys (previously committed to Git) are rejected
 * - Production environments have stricter validation
 *
 * @package Jakababa\Security
 * @version 1.0.0
 */

namespace Jakababa\Security;

class SecretsValidator
{
    /**
     * Known-compromised keys that were previously committed to the repository.
     * These MUST be rotated and can never be used again.
     */
    private const COMPROMISED_KEYS = [
        'c1624c8c7c31e302cc8c8ccc868ee477a5d4fd2d884dd134a9811410f95137f8',
    ];

    /**
     * Minimum acceptable length for encryption keys (hex-encoded = 64 chars for 32 bytes)
     */
    private const MIN_KEY_LENGTH = 64;

    /**
     * Required environment variables that must be set
     */
    private const REQUIRED_VARS = [
        'ENCRYPTION_KEY',
        'DB_HOST',
        'DB_NAME',
    ];

    /**
     * Additional variables required in production
     */
    private const PRODUCTION_REQUIRED_VARS = [
        'ENCRYPTION_KEY',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
    ];

    private array $errors = [];
    private array $warnings = [];

    /**
     * Run all validation checks.
     *
     * @param bool $throwOnError If true, throws RuntimeException on critical failures
     * @return bool True if all checks pass
     * @throws \RuntimeException If critical validation fails and $throwOnError is true
     */
    public function validate(bool $throwOnError = true): bool
    {
        $this->errors = [];
        $this->warnings = [];

        $env = $this->getEnvironment();

        $this->validateRequiredVars($env);
        $this->validateEncryptionKey();
        $this->validateNotCompromised();
        $this->validateKeyEntropy();

        if ($env === 'production') {
            $this->validateProductionSettings();
        }

        if (!empty($this->errors) && $throwOnError) {
            $message = "Security configuration errors:\n" . implode("\n", array_map(
                fn($e) => "  • {$e}",
                $this->errors
            ));
            throw new \RuntimeException($message);
        }

        // Log warnings (non-blocking)
        if (!empty($this->warnings)) {
            foreach ($this->warnings as $warning) {
                error_log("[SecretsValidator WARNING] {$warning}");
            }
        }

        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    private function getEnvironment(): string
    {
        return strtolower(getenv('APP_ENV') ?: 'development');
    }

    private function validateRequiredVars(string $env): void
    {
        $required = $env === 'production'
            ? self::PRODUCTION_REQUIRED_VARS
            : self::REQUIRED_VARS;

        foreach ($required as $var) {
            $value = getenv($var);
            if ($value === false || $value === '') {
                $this->errors[] = "Missing required environment variable: {$var}";
            }
        }
    }

    private function validateEncryptionKey(): void
    {
        $key = getenv('ENCRYPTION_KEY');

        if ($key === false || $key === '') {
            // Already reported in validateRequiredVars
            return;
        }

        if (strlen($key) < self::MIN_KEY_LENGTH) {
            $this->errors[] = sprintf(
                'ENCRYPTION_KEY too short (%d chars). Minimum %d chars required. Generate with: php -r "echo bin2hex(random_bytes(32));"',
                strlen($key),
                self::MIN_KEY_LENGTH
            );
        }

        // Check for non-hex characters (should be hex-encoded)
        if (!ctype_xdigit($key)) {
            $this->warnings[] = 'ENCRYPTION_KEY contains non-hex characters. Consider using hex encoding for consistency.';
        }
    }

    private function validateNotCompromised(): void
    {
        $key = getenv('ENCRYPTION_KEY');

        if ($key === false || $key === '') {
            return;
        }

        if (in_array($key, self::COMPROMISED_KEYS, true)) {
            $this->errors[] = 'ENCRYPTION_KEY matches a known-compromised key that was previously committed to Git. '
                . 'You MUST generate a new key: php -r "echo bin2hex(random_bytes(32));"';
        }
    }

    private function validateKeyEntropy(): void
    {
        $key = getenv('ENCRYPTION_KEY');

        if ($key === false || $key === '' || strlen($key) < self::MIN_KEY_LENGTH) {
            return;
        }

        // Check for obviously weak patterns (all same char, sequential, etc.)
        $uniqueChars = count(array_unique(str_split($key)));
        if ($uniqueChars < 8) {
            $this->errors[] = 'ENCRYPTION_KEY has insufficient entropy (too few unique characters). Generate a proper random key.';
        }
    }

    private function validateProductionSettings(): void
    {
        // Debug must be off in production
        $debug = getenv('APP_DEBUG');
        if ($debug !== false && strtolower($debug) === 'true') {
            $this->warnings[] = 'APP_DEBUG=true in production environment. This exposes sensitive information.';
        }

        // Display errors must be off
        $displayErrors = getenv('DISPLAY_ERRORS');
        if ($displayErrors !== false && strtolower($displayErrors) === 'true') {
            $this->warnings[] = 'DISPLAY_ERRORS=true in production. Error details should not be exposed to users.';
        }

        // Check CORS configuration
        $corsOrigins = getenv('API_ALLOWED_ORIGINS');
        if ($corsOrigins === '*') {
            $this->warnings[] = 'API_ALLOWED_ORIGINS=* in production. Restrict to specific domains.';
        }

        // Check for default/weak DB password
        $dbPass = getenv('DB_PASS');
        if ($dbPass === '' || $dbPass === false) {
            $this->errors[] = 'DB_PASS is empty in production. A strong database password is required.';
        }
    }
}
