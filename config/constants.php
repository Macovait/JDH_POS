<?php
declare(strict_types=1);

/**
 * Constants file for Jakababa POS (SaaS Version)
 * Application-wide static constants.
 *
 * This file should be included AFTER paths.php and the main configuration.
 * It defines constants that are not dependent on the current company.
 *
 * @package Jakababa
 * @subpackage Config
 * @version 2.0
 */

// -----------------------------------------------------------------------------
// Prevent direct access
// -----------------------------------------------------------------------------
if (!defined('ROOT_PATH') && !defined('PATHS_LOADED')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// -----------------------------------------------------------------------------
// Application Identity
// -----------------------------------------------------------------------------
// OWNER/PLATFORM BRAND (SaaS Developer) - This never changes per tenant
defined('APP_NAME') || define('APP_NAME', 'JAKPOS');
defined('APP_OWNER_BRAND') || define('APP_OWNER_BRAND', 'JAKPOS');
defined('APP_OWNER_URL') || define('APP_OWNER_URL', 'https://jakpos.com');
defined('APP_OWNER_EMAIL') || define('APP_OWNER_EMAIL', 'support@jakpos.com');

// SaaS Platform Info
defined('APP_PLATFORM_NAME') || define('APP_PLATFORM_NAME', 'JAKPOS Cloud');
defined('APP_PLATFORM_TAGLINE') || define('APP_PLATFORM_TAGLINE', 'Powered by JAKPOS');

defined('APP_VERSION') || define('APP_VERSION', '2.0.0');

// -----------------------------------------------------------------------------
// Brand Colors (CSS variables)
// -----------------------------------------------------------------------------
defined('APP_BRAND_COLOR_PRIMARY') || define('APP_BRAND_COLOR_PRIMARY', '#1E3A8A');
defined('APP_BRAND_COLOR_PRIMARY_DARK') || define('APP_BRAND_COLOR_PRIMARY_DARK', '#0F2B5E');
defined('APP_BRAND_COLOR_ACCENT') || define('APP_BRAND_COLOR_ACCENT', '#FBBF24');
defined('APP_BRAND_COLOR_ACCENT_DARK') || define('APP_BRAND_COLOR_ACCENT_DARK', '#F59E0B');
defined('APP_BRAND_COLOR_SUCCESS') || define('APP_BRAND_COLOR_SUCCESS', '#10B981');
defined('APP_BRAND_COLOR_WARNING') || define('APP_BRAND_COLOR_WARNING', '#EF4444');
defined('APP_BRAND_COLOR_INFO') || define('APP_BRAND_COLOR_INFO', '#3B82F6');
defined('APP_BRAND_COLOR_PURPLE') || define('APP_BRAND_COLOR_PURPLE', '#8B5CF6');

// -----------------------------------------------------------------------------
// UI Colors (for neumorphic theme)
// -----------------------------------------------------------------------------
defined('APP_CARD_BG') || define('APP_CARD_BG', '#1F2937');
defined('APP_CARD_BORDER') || define('APP_CARD_BORDER', '#374151');
defined('APP_TEXT_PRIMARY') || define('APP_TEXT_PRIMARY', '#F9FAFB');
defined('APP_TEXT_SECONDARY') || define('APP_TEXT_SECONDARY', '#9CA3AF');
defined('APP_TEXT_MUTED') || define('APP_TEXT_MUTED', '#6B7280');

// -----------------------------------------------------------------------------
// Default System Settings (can be overridden by company settings)
// -----------------------------------------------------------------------------
defined('DEFAULT_CURRENCY') || define('DEFAULT_CURRENCY', 'KES');
defined('DEFAULT_TAX_RATE') || define('DEFAULT_TAX_RATE', 11);
defined('DEFAULT_TIMEZONE') || define('DEFAULT_TIMEZONE', 'Africa/Nairobi');
defined('ITEMS_PER_PAGE') || define('ITEMS_PER_PAGE', 20);
defined('SESSION_TIMEOUT') || define('SESSION_TIMEOUT', 7200);
defined('MAX_UPLOAD_SIZE') || define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50 MB

// -----------------------------------------------------------------------------
// SaaS‑Specific Constants (static, not company‑dependent)
// -----------------------------------------------------------------------------
defined('SAAS_MODE') || define('SAAS_MODE', true);               // Indicates multi-tenant mode
defined('ALLOW_SELF_REGISTER') || define('ALLOW_SELF_REGISTER', true);     // Allow companies to register themselves
defined('DEFAULT_PLAN_ID') || define('DEFAULT_PLAN_ID', 1);            // ID of the free plan

// -----------------------------------------------------------------------------
// Paths (defined in paths.php, but provide fallbacks)
// -----------------------------------------------------------------------------
defined('STORAGE_PATH') || define('STORAGE_PATH', ROOT_PATH . '/storage');
defined('UPLOAD_PATH') || define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
defined('BACKUP_PATH') || define('BACKUP_PATH', STORAGE_PATH . '/backups');
defined('LOG_PATH') || define('LOG_PATH', STORAGE_PATH . '/logs');
defined('TENANT_PATH') || define('TENANT_PATH', STORAGE_PATH . '/tenants');

// -----------------------------------------------------------------------------
// Debug Mode (may be overridden by config.php)
// -----------------------------------------------------------------------------
defined('APP_DEBUG') || define('APP_DEBUG', true);