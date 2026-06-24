<?php
/**
 * Multi-Currency Migration
 * Adds currency and exchange rate tables, plus currency columns to sales/products
 */

require_once __DIR__ . '/../../src/db.php';

class MultiCurrencyMigration
{
    private PDO $db;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? get_db_connection();
    }

    public function up(): bool
    {
        try {
            $this->db->beginTransaction();

            $this->createCurrenciesTable();
            $this->createExchangeRatesTable();
            $this->addCurrencyColumns();
            $this->seedDefaultCurrencies();

            $this->db->commit();
            echo "Multi-currency migration completed successfully!\n";
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Multi-Currency Migration Error: " . $e->getMessage());
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function createCurrenciesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS currencies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(3) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            symbol VARCHAR(10) NOT NULL,
            symbol_position ENUM('before','after') DEFAULT 'before',
            decimal_places INT DEFAULT 2,
            decimal_separator VARCHAR(1) DEFAULT '.',
            thousand_separator VARCHAR(1) DEFAULT ',',
            is_default BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($sql);
        echo "Created currencies table\n";
    }

    private function createExchangeRatesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS exchange_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_currency VARCHAR(3) NOT NULL,
            to_currency VARCHAR(3) NOT NULL,
            rate DECIMAL(19,8) NOT NULL,
            source VARCHAR(50) DEFAULT 'manual',
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_currency_pair (from_currency, to_currency),
            INDEX idx_from (from_currency),
            INDEX idx_to (to_currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($sql);
        echo "Created exchange_rates table\n";
    }

    private function addCurrencyColumns(): void
    {
        $tables = ['sales', 'quotations', 'expenses', 'purchase_orders', 'payments'];
        foreach ($tables as $table) {
            try {
                $this->db->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS currency VARCHAR(3) DEFAULT 'KES' AFTER total");
                $this->db->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS currency_total DECIMAL(19,4) DEFAULT NULL AFTER currency");
            } catch (PDOException $e) {
                // Column may already exist
                error_log("Column add skipped for {$table}: " . $e->getMessage());
            }
        }
        echo "Added currency columns to transactional tables\n";
    }

    private function seedDefaultCurrencies(): void
    {
        $currencies = [
            ['KES', 'Kenyan Shilling', 'KSh', 'before', 2],
            ['USD', 'US Dollar', '$', 'before', 2],
            ['EUR', 'Euro', '€', 'before', 2],
            ['GBP', 'British Pound', '£', 'before', 2],
            ['NGN', 'Nigerian Naira', '₦', 'before', 2],
            ['ZAR', 'South African Rand', 'R', 'before', 2],
            ['TZS', 'Tanzanian Shilling', 'TSh', 'before', 2],
            ['UGX', 'Ugandan Shilling', 'USh', 'before', 2],
            ['RWF', 'Rwandan Franc', 'RF', 'before', 2],
            ['INR', 'Indian Rupee', '₹', 'before', 2],
            ['AUD', 'Australian Dollar', 'A$', 'before', 2],
            ['CAD', 'Canadian Dollar', 'C$', 'before', 2],
            ['JPY', 'Japanese Yen', '¥', 'before', 0],
            ['CNY', 'Chinese Yuan', '¥', 'before', 2],
            ['AED', 'UAE Dirham', 'dh', 'before', 2],
            ['SAR', 'Saudi Riyal', 'SR', 'before', 2],
            ['EGP', 'Egyptian Pound', 'E£', 'before', 2],
            ['GHS', 'Ghanaian Cedi', 'GH₵', 'before', 2],
            ['XOF', 'West African CFA', 'CFA', 'after', 0],
            ['XAF', 'Central African CFA', 'CFA', 'after', 0],
            ['BRL', 'Brazilian Real', 'R$', 'before', 2],
            ['MXN', 'Mexican Peso', 'Mex$', 'before', 2],
            ['TRY', 'Turkish Lira', '₺', 'before', 2],
            ['PKR', 'Pakistani Rupee', '₨', 'before', 2],
            ['BDT', 'Bangladeshi Taka', '৳', 'before', 2],
            ['MYR', 'Malaysian Ringgit', 'RM', 'before', 2],
            ['THB', 'Thai Baht', '฿', 'before', 2],
            ['IDR', 'Indonesian Rupiah', 'Rp', 'before', 0],
            ['PHP', 'Philippine Peso', '₱', 'before', 2],
            ['VND', 'Vietnamese Dong', '₫', 'after', 0],
        ];

        $stmt = $this->db->prepare("INSERT IGNORE INTO currencies (code, name, symbol, symbol_position, decimal_places) VALUES (?, ?, ?, ?, ?)");
        foreach ($currencies as $c) {
            $stmt->execute($c);
        }

        // Set default
        $this->db->exec("UPDATE currencies SET is_default = TRUE WHERE code = 'KES'");
        echo "Seeded " . count($currencies) . " currencies\n";

        // Seed some common exchange rates (KES as base)
        $rates = [
            ['KES', 'USD', 0.0077],
            ['USD', 'KES', 130.00],
            ['KES', 'EUR', 0.0071],
            ['EUR', 'KES', 140.00],
            ['KES', 'GBP', 0.0060],
            ['GBP', 'KES', 165.00],
            ['KES', 'NGN', 11.50],
            ['NGN', 'KES', 0.087],
            ['KES', 'ZAR', 0.14],
            ['ZAR', 'KES', 7.10],
            ['USD', 'EUR', 0.92],
            ['EUR', 'USD', 1.09],
            ['USD', 'GBP', 0.78],
            ['GBP', 'USD', 1.28],
            ['USD', 'NGN', 1500.00],
            ['NGN', 'USD', 0.00067],
            ['USD', 'INR', 83.50],
            ['INR', 'USD', 0.012],
            ['USD', 'TZS', 2600.00],
            ['TZS', 'USD', 0.00038],
            ['USD', 'UGX', 3800.00],
            ['UGX', 'USD', 0.00026],
        ];

        $stmt = $this->db->prepare("INSERT IGNORE INTO exchange_rates (from_currency, to_currency, rate, source) VALUES (?, ?, ?, 'seed')");
        foreach ($rates as $r) {
            $stmt->execute($r);
        }
        echo "Seeded " . count($rates) . " exchange rates\n";
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli' && basename($argv[0] ?? '') === basename(__FILE__)) {
    $migration = new MultiCurrencyMigration();
    $migration->up();
}
