<?php
/**
 * Currency Service
 * Handles multi-currency operations, exchange rates, and formatting
 */

namespace JDH\Services;

use PDO;
use Exception;

class CurrencyService
{
    private PDO $db;
    private ?string $baseCurrency = null;
    private array $rateCache = [];
    private int $cacheTtl = 300; // 5 minutes

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all active currencies
     */
    public function getAllCurrencies(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, code ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'symbol_position' => 'before', 'decimal_places' => 2]];
        }
    }

    /**
     * Get default currency
     */
    public function getDefaultCurrency(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM currencies WHERE is_default = 1 LIMIT 1");
            $currency = $stmt->fetch(PDO::FETCH_ASSOC);
            return $currency ?: $this->fallbackCurrency();
        } catch (Exception $e) {
            return $this->fallbackCurrency();
        }
    }

    private function fallbackCurrency(): array
    {
        return ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'symbol_position' => 'before', 'decimal_places' => 2];
    }

    /**
     * Get currency by code
     */
    public function getCurrency(string $code): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM currencies WHERE code = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$code]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get current active currency (from session or default)
     */
    public function getCurrentCurrency(): array
    {
        $sessionCurrency = $_SESSION['current_currency'] ?? null;
        if ($sessionCurrency) {
            $currency = $this->getCurrency($sessionCurrency);
            if ($currency) {
                return $currency;
            }
        }
        return $this->getDefaultCurrency();
    }

    /**
     * Set current currency in session
     */
    public function setCurrentCurrency(string $code): bool
    {
        $currency = $this->getCurrency($code);
        if ($currency) {
            $_SESSION['current_currency'] = $code;
            return true;
        }
        return false;
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = "{$from}_{$to}";
        if (isset($this->rateCache[$cacheKey])) {
            return $this->rateCache[$cacheKey];
        }

        try {
            // Direct rate
            $stmt = $this->db->prepare("SELECT rate FROM exchange_rates WHERE from_currency = ? AND to_currency = ? LIMIT 1");
            $stmt->execute([$from, $to]);
            $rate = $stmt->fetchColumn();

            if ($rate !== false) {
                $this->rateCache[$cacheKey] = (float) $rate;
                return (float) $rate;
            }

            // Try via base currency (KES or default)
            $base = $this->getDefaultCurrency()['code'];
            $rateFrom = $this->getExchangeRate($from, $base);
            $rateTo = $this->getExchangeRate($base, $to);
            $computed = $rateFrom * $rateTo;
            $this->rateCache[$cacheKey] = $computed;
            return $computed;
        } catch (Exception $e) {
            return 1.0;
        }
    }

    /**
     * Convert amount between currencies
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $rate = $this->getExchangeRate($from, $to);
        return $amount * $rate;
    }

    /**
     * Convert to base currency
     */
    public function convertToBase(float $amount, string $from): float
    {
        $base = $this->getDefaultCurrency()['code'];
        return $this->convert($amount, $from, $base);
    }

    /**
     * Convert from base currency
     */
    public function convertFromBase(float $amount, string $to): float
    {
        $base = $this->getDefaultCurrency()['code'];
        return $this->convert($amount, $base, $to);
    }

    /**
     * Format amount with currency symbol
     */
    public function format(float $amount, ?string $currencyCode = null, bool $showSymbol = true): string
    {
        $currency = $currencyCode ? ($this->getCurrency($currencyCode) ?: $this->getDefaultCurrency()) : $this->getCurrentCurrency();
        $code = $currency['code'];
        $symbol = $currency['symbol'] ?? $code;
        $position = $currency['symbol_position'] ?? 'before';
        $decimals = (int) ($currency['decimal_places'] ?? 2);
        $decimalSep = $currency['decimal_separator'] ?? '.';
        $thousandSep = $currency['thousand_separator'] ?? ',';

        $formatted = number_format($amount, $decimals, $decimalSep, $thousandSep);

        if (!$showSymbol) {
            return $formatted . ' ' . $code;
        }

        return $position === 'before' ? $symbol . $formatted : $formatted . ' ' . $symbol;
    }

    /**
     * Update or insert exchange rate
     */
    public function updateRate(string $from, string $to, float $rate, string $source = 'manual'): bool
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO exchange_rates (from_currency, to_currency, rate, source) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rate = VALUES(rate), source = VALUES(source), last_updated = NOW()");
            $stmt->execute([$from, $to, $rate, $source]);
            // Clear cache for this pair
            unset($this->rateCache["{$from}_{$to}"]);
            return true;
        } catch (Exception $e) {
            error_log("Exchange rate update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a new currency
     */
    public function addCurrency(array $data): bool
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO currencies (code, name, symbol, symbol_position, decimal_places, decimal_separator, thousand_separator, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['code'],
                $data['name'],
                $data['symbol'],
                $data['symbol_position'] ?? 'before',
                $data['decimal_places'] ?? 2,
                $data['decimal_separator'] ?? '.',
                $data['thousand_separator'] ?? ',',
                $data['is_active'] ?? 1
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Add currency failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get exchange rate matrix for a currency
     */
    public function getRateMatrix(string $baseCode): array
    {
        $stmt = $this->db->prepare("SELECT to_currency, rate FROM exchange_rates WHERE from_currency = ?");
        $stmt->execute([$baseCode]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

/**
 * Global helper functions
 */

if (!function_exists('currency_service')) {
    function currency_service(): CurrencyService
    {
        static $service = null;
        if ($service === null) {
            $pdo = function_exists('get_db_connection') ? get_db_connection() : null;
            if (!$pdo) {
                throw new Exception('Database connection not available for currency service');
            }
            $service = new CurrencyService($pdo);
        }
        return $service;
    }
}

if (!function_exists('format_currency')) {
    function format_currency(float $amount, ?string $currencyCode = null, bool $showSymbol = true): string
    {
        return currency_service()->format($amount, $currencyCode, $showSymbol);
    }
}

if (!function_exists('convert_currency')) {
    function convert_currency(float $amount, string $from, string $to): float
    {
        return currency_service()->convert($amount, $from, $to);
    }
}

if (!function_exists('current_currency')) {
    function current_currency(): array
    {
        return currency_service()->getCurrentCurrency();
    }
}
