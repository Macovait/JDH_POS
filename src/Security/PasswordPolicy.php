<?php
/**
 * Password Policy Enforcer
 * Validates password strength and enforces security requirements
 * 
 * @package Jakababa\Security
 * @version 1.0.0
 */

class PasswordPolicy {
    
    private static array $defaultConfig = [
        'min_length' => 8,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'min_strength_score' => 3, // 0-4 scale
        'prevent_common_passwords' => true,
        'password_history_count' => 5, // Prevent reusing last 5 passwords
    ];
    
    /**
     * Common weak passwords to reject
     */
    private static array $commonPasswords = [
        'password', 'password123', '123456', '12345678', 'qwerty',
        'abc123', 'letmein', 'welcome', 'admin', 'root',
        '123123', 'password1', 'iloveyou', 'sunshine', 'princess',
        'football', 'baseball', 'dragon', 'master', 'shadow',
        'superman', 'batman', 'trustno1', 'access', 'passw0rd',
    ];
    
    /**
     * Validate password against policy
     */
    public static function validate(string $password, array $config = []): array {
        // Load config from SecurityConfig if available, otherwise use defaults
        $securityConfig = [];
        if (class_exists('SecurityConfig')) {
            SecurityConfig::load();
            $securityConfig = SecurityConfig::getPasswordConfig();
        }
        
        $config = array_merge(self::$defaultConfig, $securityConfig, $config);
        $errors = [];
        $score = 0;
        
        // Length checks
        if (strlen($password) < $config['min_length']) {
            $errors[] = "Password must be at least {$config['min_length']} characters long";
        }
        
        if (strlen($password) > $config['max_length']) {
            $errors[] = "Password must not exceed {$config['max_length']} characters";
        }
        
        // Character type checks
        if ($config['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } else {
            $score++;
        }
        
        if ($config['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } else {
            $score++;
        }
        
        if ($config['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        } else {
            $score++;
        }
        
        if ($config['require_symbols'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&*)';
        } else {
            $score++;
        }
        
        // Common password check
        if ($config['prevent_common_passwords']) {
            $lowerPassword = strtolower($password);
            foreach (self::$commonPasswords as $common) {
                if (str_contains($lowerPassword, $common) || $lowerPassword === $common) {
                    $errors[] = 'Password is too common or easily guessable';
                    break;
                }
            }
        }
        
        // Sequential characters check
        if (self::hasSequentialChars($password)) {
            $errors[] = 'Password contains sequential characters (e.g., 123, abc)';
        }
        
        // Repeated characters check
        if (self::hasRepeatedChars($password)) {
            $errors[] = 'Password contains repeated characters (e.g., aaa, 111)';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $score,
            'strength' => self::getStrengthLabel($score),
        ];
    }
    
    /**
     * Check if password has sequential characters
     */
    private static function hasSequentialChars(string $password): bool {
        $password = strtolower($password);
        $sequences = [
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm',
        ];
        
        foreach ($sequences as $sequence) {
            for ($i = 0; $i < strlen($sequence) - 2; $i++) {
                $pattern = substr($sequence, $i, 3);
                if (str_contains($password, $pattern)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if password has repeated characters
     */
    private static function hasRepeatedChars(string $password): bool {
        return preg_match('/(.)\1{2,}/', $password);
    }
    
    /**
     * Get strength label based on score
     */
    private static function getStrengthLabel(int $score): string {
        return match ($score) {
            0, 1 => 'very_weak',
            2 => 'weak',
            3 => 'fair',
            4 => 'strong',
            default => 'unknown',
        };
    }
    
    /**
     * Check if password was used before (password history)
     */
    public static function wasPasswordUsedBefore(PDO $pdo, int $userId, string $password): bool {
        $stmt = $pdo->prepare("
            SELECT password_hash FROM password_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($hashes as $hash) {
            if (password_verify($password, $hash)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Store password in history
     */
    public static function storePasswordHistory(PDO $pdo, int $userId, string $password): void {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO password_history (user_id, password_hash, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $hash]);
        
        // Keep only last 5 passwords
        $stmt = $pdo->prepare("
            DELETE FROM password_history 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM password_history 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ) as recent
            )
        ");
        $stmt->execute([$userId, $userId]);
    }
    
    /**
     * Generate a strong random password
     */
    public static function generatePassword(int $length = 12): string {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = [
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];
        
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password[] = $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        shuffle($password);
        return implode('', $password);
    }
    
    /**
     * Calculate password entropy (bits)
     */
    public static function calculateEntropy(string $password): float {
        $poolSize = 0;
        
        if (preg_match('/[a-z]/', $password)) $poolSize += 26;
        if (preg_match('/[A-Z]/', $password)) $poolSize += 26;
        if (preg_match('/[0-9]/', $password)) $poolSize += 10;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $poolSize += 32;
        
        return strlen($password) * log($poolSize, 2);
    }
    
    /**
     * Get password requirements as array
     */
    public static function getRequirements(array $config = []): array {
        $config = array_merge(self::$defaultConfig, $config);
        
        return [
            'min_length' => $config['min_length'],
            'max_length' => $config['max_length'],
            'require_uppercase' => $config['require_uppercase'],
            'require_lowercase' => $config['require_lowercase'],
            'require_numbers' => $config['require_numbers'],
            'require_symbols' => $config['require_symbols'],
            'prevent_common' => $config['prevent_common_passwords'],
        ];
    }
}
?>
