<?php
/**
 * M-Pesa Gateway Setup Script
 * Run this to add M-Pesa tables and sample configuration
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

echo "=============================================\n";
echo "M-PESA GATEWAY SETUP\n";
echo "=============================================\n\n";

try {
    // Create payment_gateways table
    echo "Creating payment_gateways table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS payment_gateways (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            gateway_code VARCHAR(50) NOT NULL,
            gateway_name VARCHAR(100) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            config JSON NOT NULL,
            test_mode TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE,
            UNIQUE KEY uq_company_gateway (tenant_id, gateway_code),
            INDEX idx_company_active (tenant_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ payment_gateways table created\n\n";
    
    // Create payment_transactions table
    echo "Creating payment_transactions table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS payment_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            sale_id BIGINT UNSIGNED NOT NULL,
            gateway_id INT UNSIGNED NOT NULL,
            transaction_type ENUM('sale', 'refund', 'payout') DEFAULT 'sale',
            amount DECIMAL(14,2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            gateway_reference VARCHAR(255),
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            request_payload JSON,
            response_payload JSON,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id),
            INDEX idx_company_status (tenant_id, status),
            INDEX idx_gateway_ref (gateway_reference),
            INDEX idx_sale (sale_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ payment_transactions table created\n\n";
    
    // Create unallocated_payments table for C2B payments without matching sale
    echo "Creating unallocated_payments table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS unallocated_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            transaction_id VARCHAR(100) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            account_reference VARCHAR(100) NOT NULL,
            payer_name VARCHAR(150),
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'allocated', 'refunded') DEFAULT 'pending',
            allocated_to_sale_id BIGINT UNSIGNED NULL,
            allocated_at DATETIME NULL,
            allocated_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE,
            INDEX idx_company_status (tenant_id, status),
            INDEX idx_transaction (transaction_id),
            INDEX idx_account_ref (account_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ unallocated_payments table created\n\n";
    
    // Add sample M-Pesa configuration for sandbox testing
    echo "Setting up sample M-Pesa configuration...\n";
    
    // Get first company
    $stmt = $db->query("SELECT id, name FROM companies ORDER BY id LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($company) {
        echo "Found company: {$company['name']} (ID: {$company['id']})\n";
        
        // Check if M-Pesa already configured
        $check = $db->prepare("
            SELECT id FROM payment_gateways 
            WHERE tenant_id = ? AND gateway_code = 'mpesa'
        ");
        $check->execute([$company['id']]);
        
        if ($check->fetch()) {
            echo "⚠️ M-Pesa already configured for this company\n";
        } else {
            // Sample sandbox credentials (replace with real ones)
            $sampleConfig = [
                'consumer_key' => 'YOUR_CONSUMER_KEY_HERE',
                'consumer_secret' => 'YOUR_CONSUMER_SECRET_HERE',
                'shortcode' => '174379', // Test shortcode
                'passkey' => 'YOUR_PASSKEY_HERE',
                'initiator_name' => 'testapi',
                'security_credential' => '',
                'callback_url' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/JDH_POS/public/api/webhooks/mpesa'
            ];
            
            $stmt = $db->prepare("
                INSERT INTO payment_gateways 
                (tenant_id, gateway_code, gateway_name, config, test_mode, is_active, created_at)
                VALUES (?, 'mpesa', 'M-Pesa', ?, 1, 1, NOW())
            ");
            
            $stmt->execute([
                $company['id'],
                json_encode($sampleConfig)
            ]);
            
            echo "✅ Sample M-Pesa configuration added (SANDBOX MODE)\n";
            echo "   IMPORTANT: Update credentials in database or admin panel\n";
        }
    } else {
        echo "⚠️ No companies found. Skipping sample config.\n";
    }
    
    echo "\n=============================================\n";
    echo "SETUP COMPLETE!\n";
    echo "=============================================\n\n";
    
    echo "Next Steps:\n";
    echo "1. Get M-Pesa Daraja API credentials from:\n";
    echo "   https://developer.safaricom.co.ke/\n\n";
    echo "2. Update configuration in admin panel or database:\n";
    echo "   - consumer_key\n";
    echo "   - consumer_secret\n";
    echo "   - shortcode\n";
    echo "   - passkey\n\n";
    echo "3. Register your callback URL in Safaricom portal:\n";
    echo "   " . ($_SERVER['HTTP_HOST'] ? 'https://' . $_SERVER['HTTP_HOST'] : 'https://YOUR_DOMAIN') . "/JDH_POS/public/api/webhooks/mpesa\n\n";
    echo "4. Test with STK Push from the POS\n\n";
    
    echo "For C2B (Paybill/Till) payments, also register URLs:\n";
    echo "   POST /api/mpesa.php?action=register-c2b\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
