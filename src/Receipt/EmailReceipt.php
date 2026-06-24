<?php
/**
 * Email Receipt System
 * Sends receipts via email using PHPMailer
 */

namespace Jakababa\Receipt;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailReceipt
{
    private PDO $pdo;
    private array $config;
    private int $tenantId;
    private int $branchId;

    public function __construct(PDO $pdo, array $config, int $tenantId, int $branchId)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
    }

    /**
     * Send receipt via email
     * @param array $saleData Sale information
     * @param array $customer Customer data
     * @param string $email Email address
     * @return array Response with success status
     */
    public function send(array $saleData, array $customer, string $email): array
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }

        $subject = "Receipt #{$saleData['receipt_number']} - " . ($this->config['company_name'] ?? 'POS');
        $body = $this->generateReceiptBody($saleData, $customer);

        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            if (!empty($this->config['smtp_host'])) {
                $mail->isSMTP();
                $mail->Host = $this->config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['smtp_user'] ?? '';
                $mail->Password = $this->config['smtp_pass'] ?? '';
                $mail->SMTPSecure = $this->config['smtp_encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $this->config['smtp_port'] ?? 587;
            }

            // Recipients
            $mail->setFrom($this->config['email_from'] ?? 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), $this->config['company_name'] ?? 'POS');
            $mail->addAddress($email, $customer['name'] ?? 'Customer');

            // Content
            $mail->isHTML(true);
            $mail->subject($subject);
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();

            $this->logEmail($saleData['id'], $email, 'success');

            return ['success' => true, 'message' => 'Receipt sent successfully'];
        } catch (Exception $e) {
            $this->logEmail($saleData['id'], $email, 'failed', $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send email: ' . $e->getMessage()];
        }
    }

    /**
     * Generate HTML receipt body
     */
    private function generateReceiptBody(array $saleData, array $customer): string
    {
        $html = "<html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
        $html .= "<h2 style='color: #1f2937;'>" . ($this->config['company_name'] ?? 'POS') . "</h2>";
        $html .= "<p>Receipt #{$saleData['receipt_number']}</p>";
        $html .= "<p>Date: " . date('d M Y H:i', strtotime($saleData['created_at'])) . "</p>";
        
        if (!empty($customer['name'])) {
            $html .= "<p>Customer: {$customer['name']}</p>";
        }
        
        $html .= "<hr><table style='width: 100%; border-collapse: collapse;'>";
        $html .= "<tr><th style='text-align: left; border-bottom: 1px solid #ddd; padding: 8px;'>Item</th>";
        $html .= "<th style='text-align: right; border-bottom: 1px solid #ddd; padding: 8px;'>Amount</th></tr>";
        
        foreach ($saleData['items'] ?? [] as $item) {
            $html .= "<tr><td style='border-bottom: 1px solid #ddd; padding: 8px;'>{$item['name']}</td>";
            $html .= "<td style='text-align: right; border-bottom: 1px solid #ddd; padding: 8px;'>" . ($this->config['currency'] ?? 'KES') . " " . number_format($item['total'], 2) . "</td></tr>";
        }
        
        $html .= "</table><hr>";
        $html .= "<p style='text-align: right; font-weight: bold;'>Total: " . ($this->config['currency'] ?? 'KES') . " " . number_format($saleData['total'], 2) . "</p>";
        $html .= "<p>" . ($this->config['receipt_footer'] ?? 'Thank you for your business!') . "</p>";
        $html .= "</body></html>";

        return $html;
    }

    /**
     * Log email attempt
     */
    private function logEmail(int $saleId, string $email, string $status, ?string $error = null): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO receipt_emails (tenant_id, branch_id, sale_id, email, status, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$this->tenantId, $this->branchId, $saleId, $email, $status, $error]);
        } catch (Exception $e) {
            error_log("Failed to log email receipt: " . $e->getMessage());
        }
    }

    /**
     * Get email receipt statistics
     */
    public function getStats(int $days = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM receipt_emails
            WHERE tenant_id = ? AND branch_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY status
        ");
        $stmt->execute([$this->tenantId, $this->branchId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}