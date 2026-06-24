<?php
/**
 * Email Service - SaaS Notifications
 * Handles invoices, payment confirmations, trial reminders
 */

namespace Jakababa\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

class EmailService
{
    private PDO $pdo;
    private PHPMailer $mailer;
    private array $config;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../../config/email.php';
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }
    
    private function setupMailer(): void
    {
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['smtp_host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['smtp_username'];
        $this->mailer->Password = $this->config['smtp_password'];
        $this->mailer->SMTPSecure = $this->config['smtp_secure'];
        $this->mailer->Port = $this->config['smtp_port'];
        
        // Default from
        $this->mailer->setFrom(
            $this->config['from_email'], 
            $this->config['from_name']
        );
    }
    
    /**
     * Send invoice email to customer
     */
    public function sendInvoice(int $tenantId, int $invoiceId): bool
    {
        try {
            // Get invoice data
            $stmt = $this->pdo->prepare("
                SELECT i.*, c.name as company_name, c.email as company_email
                FROM invoices i
                JOIN companies c ON i.tenant_id = c.id
                WHERE i.id = ? AND i.tenant_id = ?
            ");
            $stmt->execute([$invoiceId, $tenantId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                throw new Exception('Invoice not found');
            }
            
            // Get tenant settings for branding
            $settings = $this->getTenantSettings($tenantId);
            
            $this->mailer->addAddress($invoice['company_email']);
            $this->mailer->Subject = "Invoice #{$invoice['invoice_number']} - {$settings['company_name']}";
            
            $html = $this->getInvoiceTemplate($invoice, $settings);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            
            $result = $this->mailer->send();
            
            // Log email
            $this->logEmail($tenantId, 'invoice', $invoice['company_email'], $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService::sendInvoice failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation(int $tenantId, int $paymentId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as company_name, c.email as company_email,
                       s.plan_id, plans.name as plan_name
                FROM payments p
                JOIN companies c ON p.tenant_id = c.id
                LEFT JOIN subscriptions s ON p.tenant_id = s.tenant_id AND s.status = 'active'
                LEFT JOIN plans ON s.plan_id = plans.slug
                WHERE p.id = ? AND p.tenant_id = ?
            ");
            $stmt->execute([$paymentId, $tenantId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('Payment not found');
            }
            
            $settings = $this->getTenantSettings($tenantId);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($payment['company_email']);
            $this->mailer->Subject = "Payment Confirmation - {$settings['company_name']}";
            
            $html = $this->getPaymentConfirmationTemplate($payment, $settings);
            $this->mailer->Body = $html;
            
            $result = $this->mailer->send();
            $this->logEmail($tenantId, 'payment_confirmation', $payment['company_email'], $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService::sendPaymentConfirmation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send trial ending reminder
     */
    public function sendTrialEndingReminder(int $tenantId, int $daysLeft): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.name as company_name, c.email as company_email, s.trial_ends_at
                FROM companies c
                JOIN subscriptions s ON c.id = s.tenant_id
                WHERE c.id = ? AND s.status = 'trial'
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $settings = $this->getTenantSettings($tenantId);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($data['company_email']);
            $this->mailer->Subject = "Trial ends in {$daysLeft} days - Action Required";
            
            $html = $this->getTrialReminderTemplate($data, $daysLeft, $settings);
            $this->mailer->Body = $html;
            
            $result = $this->mailer->send();
            $this->logEmail($tenantId, 'trial_reminder', $data['company_email'], $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService::sendTrialEndingReminder failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment failed notification
     */
    public function sendPaymentFailed(int $tenantId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.name as company_name, c.email as company_email,
                       s.current_period_ends_at
                FROM companies c
                JOIN subscriptions s ON c.id = s.tenant_id
                WHERE c.id = ? AND s.status = 'past_due'
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $settings = $this->getTenantSettings($tenantId);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($data['company_email']);
            $this->mailer->Subject = "Payment Failed - Please Update Your Billing Information";
            
            $html = $this->getPaymentFailedTemplate($data, $settings);
            $this->mailer->Body = $html;
            
            $result = $this->mailer->send();
            $this->logEmail($tenantId, 'payment_failed', $data['company_email'], $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService::sendPaymentFailed failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send subscription cancelled confirmation
     */
    public function sendSubscriptionCancelled(int $tenantId, string $endDate): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT name as company_name, email as company_email
                FROM companies WHERE id = ?
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $settings = $this->getTenantSettings($tenantId);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($data['company_email']);
            $this->mailer->Subject = "Subscription Cancelled - {$settings['company_name']}";
            
            $html = $this->getSubscriptionCancelledTemplate($data, $endDate, $settings);
            $this->mailer->Body = $html;
            
            $result = $this->mailer->send();
            $this->logEmail($tenantId, 'subscription_cancelled', $data['company_email'], $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService::sendSubscriptionCancelled failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email to new subscriber
     */
    public function sendWelcomeEmail(int $tenantId, string $planName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT name as company_name, email as company_email
                FROM companies WHERE id = ?
            ");
            $stmt->execute([$tenantId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return false;
            }
            
            $settings = $this->getTenantSettings($tenantId);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($data['company_email']);
            $this->mailer->Subject = "Welcome to {$planName}! Get Started with {$settings['company_name']}";
            
            $html = $this->getWelcomeTemplate($data, $planName, $settings);
            $this->mailer->Body = $html;
            
            $result = $this->mailer->send();
            $this->logEmail($tenantId, 'welcome', $data['company_email'], $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService::sendWelcomeEmail failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process scheduled emails (trial reminders, etc.)
     */
    public function processScheduledEmails(): array
    {
        $results = [];
        $config = require __DIR__ . '/../../config/payment.php';
        
        // Trial reminders
        foreach ($config['notifications']['trial_warning_days'] as $days) {
            $stmt = $this->pdo->prepare("
                SELECT tenant_id, trial_ends_at
                FROM subscriptions
                WHERE status = 'trial'
                AND DATE(trial_ends_at) = DATE(DATE_ADD(NOW(), INTERVAL ? DAY))
            ");
            $stmt->execute([$days]);
            $trials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($trials as $trial) {
                $result = $this->sendTrialEndingReminder($trial['tenant_id'], $days);
                $results[] = [
                    'type' => 'trial_reminder',
                    'tenant_id' => $trial['tenant_id'],
                    'days' => $days,
                    'sent' => $result,
                ];
            }
        }
        
        return $results;
    }
    
    // Private methods
    
    private function getTenantSettings(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT company_name as company_name, 
                   logo_url,
                   'noreply@jakababa.com' as support_email
            FROM companies WHERE id = ?
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'company_name' => 'Jakababa POS',
            'logo_url' => '',
            'support_email' => 'support@jakababa.com',
        ];
    }
    
    private function logEmail(int $tenantId, string $type, string $recipient, bool $success): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_logs (tenant_id, type, recipient, status, sent_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $type, $recipient, $success ? 'sent' : 'failed']);
    }
    
    // Email templates
    
    private function getInvoiceTemplate(array $invoice, array $settings): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 12px 24px; background: #3B82F6; color: white; text-decoration: none; border-radius: 5px; }
                .amount { font-size: 24px; font-weight: bold; color: #3B82F6; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$settings['company_name']}</h1>
                    <h2>Invoice #{$invoice['invoice_number']}</h2>
                </div>
                <div class='content'>
                    <p>Hello {$invoice['company_name']},</p>
                    <p>Thank you for your business. Please find your invoice details below:</p>
                    
                    <div style='text-align: center; padding: 20px; background: white; margin: 20px 0;'>
                        <p class='amount'>$" . number_format($invoice['amount'], 2) . "</p>
                        <p>Due Date: " . date('F j, Y', strtotime($invoice['due_date'])) . "</p>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='{$this->config['app_url']}/dashboard/billing' class='btn'>View Invoice</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Questions? Contact us at {$settings['support_email']}</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPaymentConfirmationTemplate(array $payment, array $settings): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #10B981; color: white; padding: 20px; text-align: center; }
                .success { color: #10B981; font-size: 48px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Payment Received!</h1>
                </div>
                <div class='content'>
                    <div class='success'>✓</div>
                    <h2 style='text-align: center;'>Thank You!</h2>
                    <p>Hello {$payment['company_name']},</p>
                    <p>We've received your payment of <strong>$" . number_format($payment['amount'], 2) . "</strong> for your {$payment['plan_name']} subscription.</p>
                    <p>Transaction ID: {$payment['transaction_id']}</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getTrialReminderTemplate(array $data, int $daysLeft, array $settings): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #F59E0B; color: white; padding: 20px; text-align: center; }
                .btn { display: inline-block; padding: 12px 24px; background: #3B82F6; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Trial Ending Soon</h1>
                </div>
                <div class='content'>
                    <p>Hello {$data['company_name']},</p>
                    <p>Your free trial ends in <strong>{$daysLeft} days</strong> (" . date('F j, Y', strtotime($data['trial_ends_at'])) . ").</p>
                    <p>Don't lose access to your POS system. Upgrade now to continue using all features.</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->config['app_url']}/dashboard/billing' class='btn'>Upgrade Now</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPaymentFailedTemplate(array $data, array $settings): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #EF4444; color: white; padding: 20px; text-align: center; }
                .btn { display: inline-block; padding: 12px 24px; background: #3B82F6; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Payment Failed</h1>
                </div>
                <div class='content'>
                    <p>Hello {$data['company_name']},</p>
                    <p>We were unable to process your recent payment. Your subscription will remain active until " . date('F j, Y', strtotime($data['current_period_ends_at'])) . ".</p>
                    <p>Please update your payment method to avoid service interruption:</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->config['app_url']}/dashboard/billing' class='btn'>Update Payment Method</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getSubscriptionCancelledTemplate(array $data, string $endDate, array $settings): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #6B7280; color: white; padding: 20px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Subscription Cancelled</h1>
                </div>
                <div class='content'>
                    <p>Hello {$data['company_name']},</p>
                    <p>Your subscription has been cancelled as requested. You'll continue to have access until " . date('F j, Y', strtotime($endDate)) . ".</p>
                    <p>We're sorry to see you go! If you have any feedback, please reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getWelcomeTemplate(array $data, string $planName, array $settings): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 20px; text-align: center; }
                .btn { display: inline-block; padding: 12px 24px; background: #10B981; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to {$planName}!</h1>
                </div>
                <div class='content'>
                    <p>Hello {$data['company_name']},</p>
                    <p>Thank you for subscribing to {$planName}! Your POS system is now fully activated.</p>
                    <h3>Quick Start:</h3>
                    <ul>
                        <li><a href='{$this->config['app_url']}/pos'>Start Selling</a></li>
                        <li><a href='{$this->config['app_url']}/products'>Add Products</a></li>
                        <li><a href='{$this->config['app_url']}/dashboard'>View Dashboard</a></li>
                    </ul>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$this->config['app_url']}/pos' class='btn'>Launch POS</a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
