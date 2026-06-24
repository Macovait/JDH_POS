<?php
class OtpService {
    private $config;
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->config = require __DIR__ . '/otp_config.php';
        $this->pdo = $pdo;
        $this->initTable();
    }
    
    private function initTable(): void {
        if (!$this->pdo) return;
        $sql = "CREATE TABLE IF NOT EXISTS otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY, phone VARCHAR(20) NOT NULL,
            otp VARCHAR(10) NOT NULL, purpose VARCHAR(50) DEFAULT 'login',
            attempts INT DEFAULT 0, verified TINYINT(1) DEFAULT 0,
            expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone), INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try { $this->pdo->exec($sql); } catch (Exception $e) {}
    }
    
    public function generateAndSend(string $phone, string $purpose = 'login'): array {
        if ($this->isRateLimited($phone)) {
            return ['success' => false, 'message' => 'Please wait before requesting another code'];
        }
        $this->cleanExpiredCodes($phone);
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO otp_codes (phone, otp, purpose, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$phone, $otp, $purpose, $expiresAt]);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to generate code'];
        }
        
        return $this->sendViaAfricasTalking($phone, $otp);
    }
    
    private function sendViaAfricasTalking(string $phone, string $otp): array {
        $cfg = $this->config['africastalking'] ?? [];
        $username = $cfg['username'] ?? 'sandbox';
        $apiKey = $cfg['api_key'] ?? '';
        
        if ($apiKey === 'YOUR_AT_API_KEY_HERE' || empty($apiKey)) {
            return ['success' => false, 'message' => 'SMS not configured. Add API key to otp_config.php', 'otp' => $otp];
        }
        
        $message = "Your JDH POS code is: {$otp}. Valid 10 mins. Do not share.";
        $data = ['username' => $username, 'to' => $phone, 'message' => $message];
        if (!empty($cfg['sender_id'])) $data['from'] = $cfg['sender_id'];
        
        $ch = curl_init('https://api.africastalking.com/version1/messaging');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['apiKey: ' . $apiKey, 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code == 201) {
            $res = json_decode($response, true);
            if (($res['SMSMessageData']['Recipients'][0]['statusCode'] ?? 0) == 101) {
                return ['success' => true, 'message' => 'Code sent via SMS'];
            }
        }
        return ['success' => false, 'message' => 'SMS failed. Check API key.'];
    }
    
    public function verify(string $phone, string $otp): array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM otp_codes WHERE phone = ? AND otp = ? AND verified = 0 AND expires_at > NOW()");
            $stmt->execute([$phone, $otp]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                $this->incrementAttempts($phone);
                return ['success' => false, 'message' => 'Invalid or expired code'];
            }
            
            if ($record['attempts'] >= ($record['max_attempts'] ?? 3)) {
                return ['success' => false, 'message' => 'Too many attempts. Request new code.'];
            }
            
            $stmt = $this->pdo->prepare("UPDATE otp_codes SET verified = 1 WHERE id = ?");
            $stmt->execute([$record['id']]);
            
            return ['success' => true, 'message' => 'Verified'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Verification error'];
        }
    }
    
    private function isRateLimited(string $phone): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $stmt->execute([$phone]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) { return false; }
    }
    
    private function cleanExpiredCodes(string $phone): void {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM otp_codes WHERE phone = ? OR expires_at < NOW()");
            $stmt->execute([$phone]);
        } catch (Exception $e) {}
    }
    
    private function incrementAttempts(string $phone): void {
        try {
            $stmt = $this->pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE phone = ? AND verified = 0");
            $stmt->execute([$phone]);
        } catch (Exception $e) {}
    }
}
