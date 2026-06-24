<?php
/**
 * Two-Factor Authentication Service
 * 
 * Supports multiple 2FA methods:
 * - Email (OTP via email)
 * - SMS (OTP via SMS)
 * - TOTP (Time-based One-Time Password - Google Authenticator compatible)
 * 
 * @package JakababaPOS
 * @version 2.0.0
 */

namespace JakababaPOS\Services\Security;

class TwoFactorService
{
    private \PDO $db;
    private static ?TwoFactorService $instance = null;
    
    const TABLE_PREFIX = 'pos_';
    const TOKEN_LENGTH = 6;
    const TOKEN_EXPIRY = 300; // 5 minutes
    const MAX_ATTEMPTS = 3;
    const LOCKOUT_DURATION = 900; // 15 minutes
    
    const METHOD_EMAIL = 'email';
    const METHOD_SMS = 'sms';
    const METHOD_TOTP = 'totp';
    
    /**
     * Get singleton instance
     */
    public static function getInstance(\PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Check if 2FA is enabled for user
     */
    public function isEnabled(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 2fa_enabled, 2fa_method FROM " . self::TABLE_PREFIX . "users
            WHERE id = :id
        ");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row && $row['2fa_enabled'] && !empty($row['2fa_method']);
    }
    
    /**
     * Get user's 2FA method
     */
    public function getMethod(int $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT 2fa_method FROM " . self::TABLE_PREFIX . "users
            WHERE id = :id AND 2fa_enabled = 1
        ");
        $stmt->execute([':id' => $userId]);
        
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * Enable 2FA for user
     */
    public function enable(int $userId, string $method, ?string $secret = null): bool
    {
        global $db;
        
        if (!in_array($method, [self::METHOD_EMAIL, self::METHOD_SMS, self::METHOD_TOTP])) {
            return false;
        }
        
        $secret = $secret ?? ($method === self::METHOD_TOTP ? $this->generateTotpSecret() : null);
        
        $stmt = $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "users 
            SET 2fa_enabled = 1, 2fa_method = :method, 2fa_secret = :secret, 2fa_enabled_at = NOW()
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':id' => $userId,
            ':method' => $method,
            ':secret' => $secret
        ]);
        
        if ($result) {
            $audit = AuditLogger::getInstance($db);
            $audit->logSecurity('2fa_enabled', ['user_id' => $userId, 'method' => $method]);
        }
        
        return $result;
    }
    
    /**
     * Disable 2FA for user
     */
    public function disable(int $userId): bool
    {
        global $db;
        
        $stmt = $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "users 
            SET 2fa_enabled = 0, 2fa_method = NULL, 2fa_secret = NULL
            WHERE id = :id
        ");
        
        $result = $stmt->execute([':id' => $userId]);
        
        if ($result) {
            $audit = AuditLogger::getInstance($db);
            $audit->logSecurity('2fa_disabled', ['user_id' => $userId]);
        }
        
        return $result;
    }
    
    /**
     * Initiate 2FA verification
     */
    public function verify(int $userId): ?string
    {
        $method = $this->getMethod($userId);
        
        if (!$method) {
            return null;
        }
        
        // Generate token
        $token = $this->generateToken();
        
        // Store token
        $this->storeToken($userId, $method, $token);
        
        // Send token based on method
        switch ($method) {
            case self::METHOD_EMAIL:
                $this->sendEmailToken($userId, $token);
                break;
            case self::METHOD_SMS:
                $this->sendSmsToken($userId, $token);
                break;
            case self::METHOD_TOTP:
                // For TOTP, we don't send - user uses authenticator app
                break;
        }
        
        return $method === self::METHOD_TOTP ? 'totp' : 'pending';
    }
    
    /**
     * Verify 2FA token
     */
    public function verifyToken(int $userId, string $token): array
    {
        // Check if locked out
        if ($this->isLockedOut($userId)) {
            return [
                'success' => false,
                'error' => 'Account locked. Please try again later.',
                'locked' => true
            ];
        }
        
        // Get user's 2FA method
        $method = $this->getMethod($userId);
        
        if (!$method) {
            return ['success' => false, 'error' => '2FA not enabled'];
        }
        
        // For TOTP, use special verification
        if ($method === self::METHOD_TOTP) {
            return $this->verifyTotp($userId, $token);
        }
        
        // For email/SMS, verify stored token
        return $this->verifyStoredToken($userId, $token);
    }
    
    /**
     * Verify TOTP token
     */
    private function verifyTotp(int $userId, string $token): array
    {
        $stmt = $this->db->prepare("
            SELECT 2fa_secret FROM " . self::TABLE_PREFIX . "users
            WHERE id = :id AND 2fa_enabled = 1
        ");
        $stmt->execute([':id' => $userId]);
        $secret = $stmt->fetchColumn();
        
        if (!$secret) {
            return ['success' => false, 'error' => 'Invalid configuration'];
        }
        
        // Verify TOTP
        $valid = $this->verifyTotpCode($secret, $token);
        
        if ($valid) {
            $this->clearFailedAttempts($userId);
            return ['success' => true];
        }
        
        return $this->handleFailedAttempt($userId, 'Invalid code');
    }
    
    /**
     * Verify stored email/SMS token
     */
    private function verifyStoredToken(int $userId, string $token): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM " . self::TABLE_PREFIX . "two_factor_tokens
            WHERE user_id = :user_id AND token = :token AND expires_at > NOW() AND is_used = 0
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $token
        ]);
        
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$record) {
            return $this->handleFailedAttempt($userId, 'Invalid or expired token');
        }
        
        // Mark token as used
        $this->db->prepare("UPDATE " . self::TABLE_PREFIX . "two_factor_tokens SET is_used = 1 WHERE id = :id")
            ->execute([':id' => $record['id']]);
        
        $this->clearFailedAttempts($userId);
        
        return ['success' => true];
    }
    
    /**
     * Handle failed verification attempt
     */
    private function handleFailedAttempt(int $userId, string $reason): array
    {
        global $db;
        
        // Record failed attempt
        $attempts = $this->recordFailedAttempt($userId);
        
        // Log the failure
        $audit = AuditLogger::getInstance($db);
        $audit->logSecurity('2fa_failed', ['user_id' => $userId, 'reason' => $reason, 'attempts' => $attempts]);
        
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->lockOut($userId);
            
            return [
                'success' => false,
                'error' => 'Too many failed attempts. Account locked for 15 minutes.',
                'locked' => true
            ];
        }
        
        $remaining = self::MAX_ATTEMPTS - $attempts;
        
        return [
            'success' => false,
            'error' => $reason,
            'attempts_remaining' => $remaining
        ];
    }
    
    /**
     * Check if user is locked out
     */
    public function isLockedOut(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM " . self::TABLE_PREFIX . "two_factor_tokens
            WHERE user_id = :user_id AND is_used = 0 
            AND token = 'LOCKOUT'
            AND expires_at > NOW()
        ");
        
        $stmt->execute([':user_id' => $userId]);
        
        return (int) $stmt->fetchColumn() > 0;
    }
    
    /**
     * Lock out user
     */
    private function lockOut(int $userId): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION);
        
        $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "two_factor_tokens
            (user_id, method, token, expires_at, is_used)
            VALUES (:user_id, 'lockout', 'LOCKOUT', :expires_at, 0)
        ")->execute([
            ':user_id' => $userId,
            ':expires_at' => $expiresAt
        ]);
    }
    
    /**
     * Record failed attempt
     */
    private function recordFailedAttempt(int $userId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "two_factor_tokens
            (user_id, method, token, expires_at, is_used)
            VALUES (:user_id, 'failed', 'FAILED', DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
        ");
        
        $stmt->execute([':user_id' => $userId]);
        
        // Count failed attempts in last hour
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM " . self::TABLE_PREFIX . "two_factor_tokens
            WHERE user_id = :user_id AND token = 'FAILED' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        $stmt->execute([':user_id' => $userId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Clear failed attempts
     */
    private function clearFailedAttempts(int $userId): void
    {
        $this->db->prepare("
            DELETE FROM " . self::TABLE_PREFIX . "two_factor_tokens
            WHERE user_id = :user_id AND token IN ('FAILED', 'LOCKOUT')
        ")->execute([':user_id' => $userId]);
    }
    
    /**
     * Store verification token
     */
    private function storeToken(int $userId, string $method, string $token): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_EXPIRY);
        
        // Invalidate any existing unused tokens
        $this->db->prepare("
            UPDATE " . self::TABLE_PREFIX . "two_factor_tokens 
            SET is_used = 1 
            WHERE user_id = :user_id AND is_used = 0
        ")->execute([':user_id' => $userId]);
        
        // Insert new token
        $this->db->prepare("
            INSERT INTO " . self::TABLE_PREFIX . "two_factor_tokens
            (user_id, method, token, expires_at)
            VALUES (:user_id, :method, :token, :expires_at)
        ")->execute([
            ':user_id' => $userId,
            ':method' => $method,
            ':token' => $token,
            ':expires_at' => $expiresAt
        ]);
    }
    
    /**
     * Send token via email
     */
    private function sendEmailToken(int $userId, string $token): void
    {
        $stmt = $this->db->prepare("SELECT email, name FROM " . self::TABLE_PREFIX . "users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            return;
        }
        
        $subject = 'Your verification code';
        $message = "
            <h2>Verification Code</h2>
            <p>Hi {$user['name']},</p>
            <p>Your verification code is: <strong>{$token}</strong></p>
            <p>This code expires in 5 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";
        
        $this->sendEmail($user['email'], $subject, $message);
    }
    
    /**
     * Send token via SMS
     */
    private function sendSmsToken(int $userId, string $token): void
    {
        // Get phone number
        $stmt = $this->db->prepare("SELECT phone FROM " . self::TABLE_PREFIX . "users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $phone = $stmt->fetchColumn();
        
        if (!$phone) {
            return;
        }
        
        $this->sendSms($phone, "Your verification code is: {$token}");
    }
    
    /**
     * Generate random token
     */
    private function generateToken(): string
    {
        return str_pad((string) random_int(0, 999999), self::TOKEN_LENGTH, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate TOTP secret
     */
    private function generateTotpSecret(): string
    {
        return bin2hex(random_bytes(20));
    }
    
    /**
     * Verify TOTP code
     */
    private function verifyTotpCode(string $secret, string $code): bool
    {
        // Standard TOTP algorithm
        $time = floor(time() / 30);
        
        // Check current and previous time windows
        for ($i = -1; $i <= 1; $i++) {
            if ($this->generateTotp($secret, $time + $i) === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate TOTP code
     */
    public function generateTotp(string $secret, ?int $time = null): string
    {
        $time = $time ?? floor(time() / 30);
        
        $secretBytes = hex2bin($secret);
        $timeBytes = pack('J', $time);
        
        $hash = hash_hmac('sha1', $timeBytes, $secretBytes);
        
        $offset = hexdec(substr($hash, -1)) & 0x0F;
        
        $code = hexdec(substr($hash, $offset * 2, 8)) & 0x7FFFFFFF;
        
        return str_pad((string) ($code % 1000000), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate TOTP QR code URL
     */
    public function getTotpQrUrl(string $secret, string $email, string $appName): string
    {
        $issuer = urlencode($appName);
        $account = urlencode($email);
        
        return "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";
    }
    
    /**
     * Send email helper
     */
    private function sendEmail(string $to, string $subject, string $body): void
    {
        // Use existing email service
        global $db;
        require_once __DIR__ . '/../../email.php';
        \send_email($to, $subject, $body);
    }
    
    /**
     * Send SMS helper
     */
    private function sendSms(string $to, string $message): void
    {
        // Use existing SMS service
        global $db;
        require_once __DIR__ . '/../../sms.php';
        \send_sms($to, $message);
    }
}

/**
 * Helper functions
 */
if (!function_exists('is2faEnabled')) {
    function is2faEnabled(int $userId): bool
    {
        global $db;
        $service = TwoFactorService::getInstance($db);
        return $service->isEnabled($userId);
    }
}

if (!function_exists('initiate2fa')) {
    function initiate2fa(int $userId): ?string
    {
        global $db;
        $service = TwoFactorService::getInstance($db);
        return $service->verify($userId);
    }
}

if (!function_exists('verify2fa')) {
    function verify2fa(int $userId, string $token): array
    {
        global $db;
        $service = TwoFactorService::getInstance($db);
        return $service->verifyToken($userId, $token);
    }
}