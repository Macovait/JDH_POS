<?php
/**
 * Currency Manager
 * Multi-currency support with exchange rates
 */

namespace Jakababa\Currency;

class CurrencyManager
{
    private PDO $pdo;
    private int $tenantId;
    private string $baseCurrency;
    private array $exchangeRates = [];

    public function __construct(PDO $pdo, int $tenantId, string $baseCurrency = 'KES')
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->baseCurrency = $baseCurrency;
        $this->loadExchangeRates();
    }

    /**
     * Load exchange rates from database
     */
    private function loadExchangeRates(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT from_currency, to_currency, rate 
                FROM exchange_rates 
                WHERE tenant_id = ?
            ");
            $stmt->execute([$this->tenantId]);
            
            while ($row = $stmt->fetch()) {
                $key = $row['from_currency'] . '_' . $row['to_currency'];
                $this->exchangeRates[$key] = (float)$row['rate'];
            }
        } catch (Exception $e) {
            error_log("Failed to load exchange rates: " . $e->getMessage());
        }
    }

    /**
     * Convert amount between currencies
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // Try direct rate
        $key = $fromCurrency . '_' . $toCurrency;
        if (isset($this->exchangeRates[$key])) {
            return $amount * $this->exchangeRates[$key];
        }

        // Try inverse rate (to -> from)
        $inverseKey = $toCurrency . '_' . $fromCurrency;
        if (isset($this->exchangeRates[$inverseKey])) {
            return $amount / $this->exchangeRates[$inverseKey];
        }

        // Convert via base currency
        $toBase = $this->convertToBase($amount, $fromCurrency);
        return $this->convertFromBase($toBase, $toCurrency);
    }

    /**
     * Convert to base currency
     */
    private function convertToBase(float $amount, string $currency): float
    {
        if ($currency === $this->baseCurrency) {
            return $amount;
        }

        $key = $currency . '_' . $this->baseCurrency;
        if (isset($this->exchangeRates[$key])) {
            return $amount * $this->exchangeRates[$key];
        }

        return $amount;
    }

    /**
     * Convert from base currency
     */
    private function convertFromBase(float $amount, string $currency): float
    {
        if ($currency === $this->baseCurrency) {
            return $amount;
        }

        $key = $this->baseCurrency . '_' . $currency;
        if (isset($this->exchangeRates[$key])) {
            return $amount * $this->exchangeRates[$key];
        }

        return $amount;
    }

    /**
     * Update exchange rate
     */
    public function updateRate(string $fromCurrency, string $toCurrency, float $rate): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO exchange_rates (tenant_id, from_currency, to_currency, rate, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE rate = VALUES(rate), updated_at = NOW()
        ");
        $stmt->execute([$this->tenantId, $fromCurrency, $toCurrency, $rate]);

        $this->exchangeRates["{$fromCurrency}_{$toCurrency}"] = $rate;

        $this->logActivity("currency.update", "Updated rate {$fromCurrency} -> {$toCurrency}: {$rate}");

        return ['success' => true, 'message' => 'Exchange rate updated'];
    }

    /**
     * Get all exchange rates
     */
    public function getRates(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM exchange_rates 
            WHERE tenant_id = ?
            ORDER BY from_currency, to_currency
        ");
        $stmt->execute([$this->tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Format amount with currency symbol
     */
    public function format(float $amount, string $currency = ''): string
    {
        $currency = $currency ?: $this->baseCurrency;
        
        $symbols = [
            'KES' => 'KSh',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'UGX' => 'UGX',
            'TZS' => 'TZS'
        ];

        $symbol = $symbols[$currency] ?? $currency;
        
        return "{$symbol} " . number_format($amount, 2);
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, string $description): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, description, meta, tenant_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                0,
                $action,
                $description,
                json_encode(['tenant_id' => $this->tenantId]),
                $this->tenantId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log currency activity: " . $e->getMessage());
        }
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT from_currency as currency FROM exchange_rates WHERE tenant_id = ?
            UNION
            SELECT DISTINCT to_currency as currency FROM exchange_rates WHERE tenant_id = ?
        ");
        $stmt->execute([$this->tenantId, $this->tenantId]);
        
        $currencies = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($currencies)) {
            $currencies = [$this->baseCurrency];
        }

        return $currencies;
    }
}