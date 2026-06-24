<?php
/**
 * SMS Service - SaaS Notifications
 * Handles SMS alerts using Africa's Talking, Twilio, or custom SMS gateway
 */

namespace Jakababa\Services;

use PDO;

class SMSService
{
    private PDO $pdo;
    private array $config;
    private string $provider;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../../config/sms.php';
        $this->provider = $this->config['provider'];
    }
    
    /**
     * Send trial ending reminder via SMS
     */
    public function sendTrialReminder(int $tenantId, int $daysLeft): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.name as company_name, c.phone
                FROM companies c
                JOIN subscriptions s ON c.id = s.tenant_id
                WHERE c.id = ? AND s.status = 'trial'
                AND c.phone IS NOT NULL AND c.phone != ''
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $message = "Hi {$data['company_name']}, your Jakababa POS trial ends in {$daysLeft} days. Upgrade now at: " . $this->config['app_url'] . "/dashboard/billing";
            
            return $this->sendSMS($data['phone'], $message, $tenantId);
            
        } catch (\Exception $e) {
            error_log("SMSService::sendTrialReminder failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment failure alert
     */
    public function sendPaymentFailedAlert(int $tenantId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.name as company_name, c.phone, s.current_period_ends_at
                FROM companies c
                JOIN subscriptions s ON c.id = s.tenant_id
                WHERE c.id = ? AND s.status = 'past_due'
                AND c.phone IS NOT NULL AND c.phone != ''
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $endDate = date('M j', strtotime($data['current_period_ends_at']));
            $message = "URGENT: Your Jakababa POS payment failed. Service ends {$endDate}. Update payment method: " . $this->config['app_url'] . "/dashboard/billing";
            
            return $this->sendSMS($data['phone'], $message, $tenantId);
            
        } catch (\Exception $e) {
            error_log("SMSService::sendPaymentFailedAlert failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send low stock alert to tenant
     */
    public function sendLowStockAlert(int $tenantId, string $productName, int $quantity): bool
    {
        if (!($this->config['notifications']['low_stock_alerts'] ?? false)) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT phone FROM companies WHERE id = ? AND phone IS NOT NULL AND phone != ''
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $message = "ALERT: {$productName} is low on stock ({$quantity} remaining). Please restock. - Jakababa POS";
            
            return $this->sendSMS($data['phone'], $message, $tenantId);
            
        } catch (\Exception $e) {
            error_log("SMSService::sendLowStockAlert failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send daily sales summary
     */
    public function sendDailySalesSummary(int $tenantId, float $totalSales, int $transactionCount): bool
    {
        if (!($this->config['notifications']['daily_sales_summary'] ?? false)) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT phone FROM companies WHERE id = ? AND phone IS NOT NULL AND phone != ''
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $currency = $this->config['currency'] ?? 'KES';
            $message = "Daily Summary: {$transactionCount} sales, {$currency} " . number_format($totalSales, 2) . " total. View details at: " . $this->config['app_url'] . "/dashboard";
            
            return $this->sendSMS($data['phone'], $message, $tenantId);
            
        } catch (\Exception $e) {
            error_log("SMSService::sendDailySalesSummary failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send large sale notification
     */
    public function sendLargeSaleAlert(int $tenantId, float $amount, string $customerName = 'Guest'): bool
    {
        $threshold = $this->config['large_sale_threshold'] ?? 10000;
        
        if ($amount < $threshold) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT phone FROM companies WHERE id = ? AND phone IS NOT NULL AND phone != ''
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $currency = $this->config['currency'] ?? 'KES';
            $message = "Large Sale Alert: {$customerName} made a purchase of {$currency} " . number_format($amount, 2) . " - Jakababa POS";
            
            return $this->sendSMS($data['phone'], $message, $tenantId);
            
        } catch (\Exception $e) {
            error_log("SMSService::sendLargeSaleAlert failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send custom SMS
     */
    public function sendCustomSMS(int $tenantId, string $phone, string $message): bool
    {
        return $this->sendSMS($phone, $message, $tenantId);
    }
    
    /**
     * Send bulk SMS to customers
     */
    public function sendBulkToCustomers(int $tenantId, string $message, array $filters = []): array
    {
        try {
            $sql = "SELECT phone, name FROM customers WHERE tenant_id = ? AND phone IS NOT NULL AND phone != '' AND opt_in_sms = 1";
            $params = [$tenantId];
            
            if (!empty($filters['min_visits'])) {
                $sql .= " AND visit_count >= ?";
                $params[] = $filters['min_visits'];
            }
            
            if (!empty($filters['min_spent'])) {
                $sql .= " AND total_spent >= ?";
                $params[] = $filters['min_spent'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($customers as $customer) {
                $personalizedMessage = str_replace('{name}', $customer['name'], $message);
                $success = $this->sendSMS($customer['phone'], $personalizedMessage, $tenantId);
                $results[] = [
                    'phone' => $customer['phone'],
                    'name' => $customer['name'],
                    'sent' => $success,
                ];
            }
            
            return [
                'total' => count($customers),
                'successful' => count(array_filter($results, fn($r) => $r['sent'])),
                'results' => $results,
            ];
            
        } catch (\Exception $e) {
            error_log("SMSService::sendBulkToCustomers failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Process scheduled SMS reminders
     */
    public function processScheduledSMS(): array
    {
        $results = [];
        $config = require __DIR__ . '/../../config/payment.php';
        
        // Trial reminders
        if ($this->config['notifications']['trial_reminders']) {
            foreach ($config['notifications']['trial_warning_days'] as $days) {
                $stmt = $this->pdo->prepare("
                    SELECT tenant_id FROM subscriptions
                    WHERE status = 'trial'
                    AND DATE(trial_ends_at) = DATE(DATE_ADD(NOW(), INTERVAL ? DAY))
                ");
                $stmt->execute([$days]);
                $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($trials as $trial) {
                    $result = $this->sendTrialReminder($trial['tenant_id'], $days);
                    $results[] = [
                        'type' => 'trial_reminder',
                        'tenant_id' => $trial['tenant_id'],
                        'days' => $days,
                        'sent' => $result,
                    ];
                }
            }
        }
        
        // Payment failure alerts
        if ($this->config['notifications']['payment_failed_alerts']) {
            $stmt = $this->pdo->query("
                SELECT tenant_id FROM subscriptions
                WHERE status = 'past_due'
                AND last_payment_reminder_sent < DATE_SUB(NOW(), INTERVAL 3 DAY)
            ");
            $pastDue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($pastDue as $sub) {
                $result = $this->sendPaymentFailedAlert($sub['tenant_id']);
                
                // Update reminder sent timestamp
                $this->pdo->prepare("
                    UPDATE subscriptions SET last_payment_reminder_sent = NOW() WHERE tenant_id = ?
                ")->execute([$sub['tenant_id']]);
                
                $results[] = [
                    'type' => 'payment_failed',
                    'tenant_id' => $sub['tenant_id'],
                    'sent' => $result,
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get SMS balance/credits
     */
    public function getBalance(): array
    {
        switch ($this->provider) {
            case 'africastalking':
                return $this->getAfricasTalkingBalance();
            case 'twilio':
                return $this->getTwilioBalance();
            default:
                return ['provider' => $this->provider, 'balance' => 'unknown'];
        }
    }
    
    // Private methods
    
    private function sendSMS(string $phone, string $message, int $tenantId): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }
        
        // Format phone number
        $phone = $this->formatPhoneNumber($phone);
        
        $result = false;
        switch ($this->provider) {
            case 'africastalking':
                $result = $this->sendAfricasTalking($phone, $message);
                break;
            case 'twilio':
                $result = $this->sendTwilio($phone, $message);
                break;
            case 'custom':
                $result = $this->sendCustom($phone, $message);
                break;
        }
        
        // Log SMS
        $this->logSMS($tenantId, $phone, $message, $result);
        
        return $result;
    }
    
    private function formatPhoneNumber(string $phone): string
    {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing
        if (strlen($phone) === 9 && strpos($phone, '0') === 0) {
            // Kenya format: 07XXXXXXXX -> +2547XXXXXXXX
            $phone = '+254' . substr($phone, 1);
        } elseif (strlen($phone) === 10 && strpos($phone, '0') === 0) {
            // Generic: remove leading 0 and add +
            $phone = '+' . substr($phone, 1);
        } elseif (strlen($phone) < 10) {
            // Assume Kenya if short
            $phone = '+254' . $phone;
        }
        
        return $phone;
    }
    
    private function sendAfricasTalking(string $phone, string $message): bool
    {
        try {
            $username = $this->config['africastalking_username'];
            $apiKey = $this->config['africastalking_api_key'];
            $senderId = $this->config['africastalking_sender_id'] ?? '';
            
            $url = 'https://api.africastalking.com/version1/messaging';
            
            $data = [
                'username' => $username,
                'to' => $phone,
                'message' => $message,
            ];
            
            if ($senderId) {
                $data['from'] = $senderId;
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'apikey: ' . $apiKey,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 201;
            
        } catch (\Exception $e) {
            error_log("AfricasTalking SMS failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendTwilio(string $phone, string $message): bool
    {
        try {
            $sid = $this->config['twilio_sid'];
            $token = $this->config['twilio_token'];
            $from = $this->config['twilio_phone_number'];
            
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
            
            $data = [
                'From' => $from,
                'To' => $phone,
                'Body' => $message,
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 201;
            
        } catch (\Exception $e) {
            error_log("Twilio SMS failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendCustom(string $phone, string $message): bool
    {
        try {
            $url = $this->config['custom_gateway_url'];
            $apiKey = $this->config['custom_api_key'];
            $senderId = $this->config['custom_sender_id'] ?? 'Jakababa';
            
            $data = [
                'phone' => $phone,
                'message' => $message,
                'sender' => $senderId,
                'api_key' => $apiKey,
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
            
        } catch (\Exception $e) {
            error_log("Custom SMS gateway failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function getAfricasTalkingBalance(): array
    {
        try {
            $username = $this->config['africastalking_username'];
            $apiKey = $this->config['africastalking_api_key'];
            
            $url = 'https://api.africastalking.com/version1/user?username=' . urlencode($username);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'apikey: ' . $apiKey,
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            return [
                'provider' => 'africastalking',
                'balance' => $data['UserData']['balance'] ?? 'unknown',
                'currency' => $data['UserData']['currency'] ?? 'KES',
            ];
            
        } catch (\Exception $e) {
            return ['provider' => 'africastalking', 'error' => $e->getMessage()];
        }
    }
    
    private function getTwilioBalance(): array
    {
        // Twilio doesn't provide a simple balance API, you'd need to track usage
        return [
            'provider' => 'twilio',
            'balance' => 'Check Twilio Console',
        ];
    }
    
    private function logSMS(int $tenantId, string $phone, string $message, bool $success): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sms_logs (tenant_id, phone, message, status, sent_at, provider)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$tenantId, $phone, substr($message, 0, 160), $success ? 'sent' : 'failed', $this->provider]);
        } catch (\Exception $e) {
            error_log("Failed to log SMS: " . $e->getMessage());
        }
    }
}
