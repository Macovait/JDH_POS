<?php
/**
 * SMS Receipt System
 * Sends receipts via SMS using Africa's Talking API
 */

namespace Jakababa\Receipt;

class SmsReceipt
{
    private PDO $pdo;
    private array $config;
    private int $tenantId;
    private int $branchId;
    private string $apiUrl = 'https://api.africastalking.com/v1/messaging';

    public function __construct(PDO $pdo, array $config, int $tenantId, int $branchId)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
    }

    /**
     * Send receipt via SMS
     * @param array $saleData Sale information
     * @param array $customer Customer data
     * @param string $phone Phone number
     * @return array Response with success status
     */
    public function send(array $saleData, array $customer, string $phone): array
    {
        if (empty($phone)) {
            return ['success' => false, 'error' => 'Phone number is required'];
        }

        // Format phone number
        $phone = $this->formatPhoneNumber($phone);
        
        $message = $this->generateSmsMessage($saleData, $customer);

        try {
            $response = $this->sendSms($phone, $message);
            
            $this->logSms($saleData['id'], $phone, $response['success'] ? 'success' : 'failed', 
                $response['message'] ?? null);

            return $response;
        } catch (Exception $e) {
            $this->logSms($saleData['id'], $phone, 'failed', $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    /**
     * Format phone number for Africa's Talking
     */
    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            return '+254' . $phone;
        }
        
        if (strlen($phone) === 10 && str_starts_with($phone, '07')) {
            return '+25' . $phone;
        }
        
        if (strlen($phone) === 12 && str_starts_with($phone, '254')) {
            return '+' . $phone;
        }

        return $phone;
    }

    /**
     * Send SMS via Africa's Talking API
     */
    private function sendSms(string $phone, string $message): array
    {
        if (empty($this->config['africastalking_username']) || empty($this->config['africastalking_api_key'])) {
            return ['success' => false, 'error' => 'SMS not configured'];
        }

        $postData = [
            'username' => $this->config['africastalking_username'],
            'to' => $phone,
            'message' => $message,
            'from' => $this->config['sms_sender'] ?? null
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'ApiKey: ' . $this->config['africastalking_api_key'],
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        return [
            'success' => $httpCode === 201 && ($data['status'] ?? '') === 'success',
            'message' => $data['message'] ?? $response
        ];
    }

    /**
     * Generate SMS message
     */
    private function generateSmsMessage(array $saleData, array $customer): string
    {
        $msg = ($this->config['company_name'] ?? 'POS') . " Receipt #{$saleData['receipt_number']}\n";
        $msg .= "Total: " . ($this->config['currency'] ?? 'KES') . " " . number_format($saleData['total'], 2) . "\n";
        $msg .= ($this->config['receipt_footer'] ?? 'Thank you!');
        
        return $msg;
    }

    /**
     * Log SMS attempt
     */
    private function logSms(int $saleId, string $phone, string $status, ?string $error = null): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO receipt_sms (tenant_id, branch_id, sale_id, phone, status, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$this->tenantId, $this->branchId, $saleId, $phone, $status, $error]);
        } catch (Exception $e) {
            error_log("Failed to log SMS receipt: " . $e->getMessage());
        }
    }

    /**
     * Get SMS statistics
     */
    public function getStats(int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) as count, SUM(price) as total_cost
            FROM receipt_sms
            WHERE tenant_id = ? AND branch_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY status
        ");
        $stmt->execute([$this->tenantId, $this->branchId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}