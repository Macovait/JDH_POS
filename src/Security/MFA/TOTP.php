<?php
/**
 * TOTP (Time-based One-Time Password) MFA
 * 
 * @package JDH_POS\Security\MFA
 * @version 1.0
 */

namespace JDH\Security\MFA;

use Exception;

class TOTP
{
    private int $digits = 6;
    private int $period = 30;
    private string $algorithm = 'sha1';
    
    /**
     * Generate a secret key
     * 
     * @param int $length Secret length
     * @return string
     */
    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
        }
        
        return $secret;
    }
    
    /**
     * Generate TOTP code
     * 
     * @param string $secret Secret key
     * @param int|null $timestamp Timestamp (null for current time)
     * @return string
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $counter = floor($timestamp / $this->period);
        $hash = hash_hmac($this->algorithm, pack('J', $counter), $this->base32Decode($secret), true);
        
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $this->digits);
        
        return str_pad((string) $code, $this->digits, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code
     * 
     * @param string $secret Secret key
     * @param string $code User-provided code
     * @param int $window Past/future windows to check
     * @return bool
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $timestamp = time();
        
        for ($i = -$window; $i <= $window; $i++) {
            $checkTime = $timestamp + ($i * $this->period);
            if ($this->generateCode($secret, $checkTime) === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate QR code URL for Google Authenticator
     * 
     * @param string $secret Secret key
     * @param string $account User account (email)
     * @param string $issuer Company name
     * @return string
     */
    public function getQRCodeUrl(string $secret, string $account, string $issuer = 'JAKPOS'): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => $this->algorithm,
            'digits' => $this->digits,
            'period' => $this->period
        ];
        
        return 'otpauth://totp/' . $label . '?' . http_build_query($params);
    }
    
    /**
     * Get recovery codes
     * 
     * @param int $count Number of codes
     * @return array
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2));
        }
        return $codes;
    }
    
    /**
     * Base32 decode
     * 
     * @param string $secret Base32 encoded secret
     * @return string
     */
    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $length = strlen($secret);
        $buffer = 0;
        $bits = 0;
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $value = strpos($alphabet, $secret[$i]);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            
            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        
        return $result;
    }
}