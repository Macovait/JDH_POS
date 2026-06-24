<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!defined('APP_NAME')) {
    define('APP_NAME', 'JDH POS');
};

class Email
{
    private static ?PHPMailer $mailer = null;
    private static array $config = [];

    public static function init(array $config = []): void
    {
        $defaults = [
            'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'smtp_port' => getenv('SMTP_PORT') ?: 587,
            'smtp_username' => getenv('SMTP_USERNAME') ?: '',
            'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
            'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
            'from_email' => getenv('EMAIL_FROM') ?: 'noreply@jakababa.com',
            'from_name' => getenv('EMAIL_FROM_NAME') ?: APP_NAME,
            'reply_to' => getenv('EMAIL_REPLY_TO') ?: '',
            'use_smtp' => getenv('SMTP_ENABLED') === 'true',
        ];

        self::$config = array_merge($defaults, $config);
    }

    private static function getMailer(): PHPMailer
    {
        if (self::$mailer === null) {
            self::$mailer = new PHPMailer(true);

            if (self::$config['use_smtp'] ?? false) {
                self::$mailer->isSMTP();
                self::$mailer->Host = self::$config['smtp_host'];
                self::$mailer->Port = self::$config['smtp_port'];
                self::$mailer->Username = self::$config['smtp_username'];
                self::$mailer->Password = self::$config['smtp_password'];
                self::$mailer->SMTPSecure = self::$config['smtp_encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                self::$mailer->SMTPAuth = !empty(self::$config['smtp_username']);
            } else {
                self::$mailer->isMail();
            }

            self::$mailer->setFrom(
                self::$config['from_email'],
                self::$config['from_name']
            );

            if (!empty(self::$config['reply_to'])) {
                self::$mailer->addReplyTo(self::$config['reply_to']);
            }
        }

        return self::$mailer;
    }

    public static function send(array $options): bool
    {
        try {
            $mail = self::getMailer();
            $mail->clearAddresses();
            $mail->clearAttachments();
            $mail->clearBCCs();
            $mail->clearCCs();

            $to = $options['to'] ?? '';
            $name = $options['name'] ?? '';
            $subject = $options['subject'] ?? '';
            $body = $options['body'] ?? '';
            $is_html = $options['is_html'] ?? true;
            $attachments = $options['attachments'] ?? [];
            $cc = $options['cc'] ?? [];
            $bcc = $options['bcc'] ?? [];

            if (empty($to) || empty($subject) || empty($body)) {
                throw new Exception('Missing required fields: to, subject, body');
            }

            $mail->addAddress($to, $name);
            $mail->Subject = $subject;

            if ($is_html) {
                $mail->isHTML(true);
                $mail->Body = self::wrapHtml($body, $subject);
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }

            foreach ($cc as $email) {
                $mail->addCC($email);
            }

            foreach ($bcc as $email) {
                $mail->addBCC($email);
            }

            foreach ($attachments as $file) {
                if (is_array($file)) {
                    $mail->addAttachment($file['path'], $file['name'] ?? '');
                } else {
                    $mail->addAttachment($file);
                }
            }

            return $mail->send();

        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    public static function sendTemplate(string $template, array $data, array $options): bool
    {
        $templates = self::loadTemplates();
        $template_file = $templates[$template] ?? null;

        if (!$template_file || !file_exists($template_file)) {
            return self::send($options);
        }

        ob_start();
        extract($data);
        include $template_file;
        $body = ob_get_clean();

        $options['body'] = $body;
        $options['is_html'] = true;

        return self::send($options);
    }

    private static function wrapHtml(string $body, string $subject): string
    {
        $logo = self::$config['from_name'] ?? APP_NAME;
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$subject</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr style="background:#1E3A8A;">
                        <td style="padding:20px 30px;">
                            <h1 style="margin:0;color:#FBBF24;font-size:24px;font-weight:700;">$logo</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;color:#374151;font-size:16px;line-height:1.6;">
                            $body
                        </td>
                    </tr>
                    <tr style="background:#f9fafb;">
                        <td style="padding:20px 30px;text-align:center;color:#9ca3af;font-size:14px;">
                            <p style="margin:0;">This email was sent by $logo</p>
                            <p style="margin:5px 0 0;">&copy; 2026 All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private static function loadTemplates(): array
    {
        return [
            'receipt' => __DIR__ . '/../templates/email/receipt.php',
            'welcome' => __DIR__ . '/../templates/email/welcome.php',
            'password_reset' => __DIR__ . '/../templates/email/password_reset.php',
            'low_stock' => __DIR__ . '/../templates/email/low_stock.php',
            'daily_report' => __DIR__ . '/../templates/email/daily_report.php',
            'customer_welcome' => __DIR__ . '/../templates/email/customer_welcome.php',
        ];
    }

    public static function test(array $config = []): array
    {
        try {
            self::init($config);
            $mail = self::getMailer();
            $mail->addAddress(self::$config['smtp_username']);
            $mail->Subject = 'Test Email';
            $mail->Body = self::wrapHtml('This is a test email from ' . (APP_NAME ?: 'JDH POS'), 'Test Email');
            $mail->send();
            return ['success' => true, 'message' => 'Test email sent'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}