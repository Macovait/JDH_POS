<?php
/**
 * Breached Password Detection - HaveIBeenPwned API integration
 * 
 * @package JDH_POS\Security
 * @version 1.0
 */

namespace JDH\Security;

use Exception;

class BreachDetection
{
    private ?string $apiKey;
    private bool $enabled = false;
    
    /**
     * Constructor
     * 
     * @param string|null $apiKey HIBP API key
     */
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
        $this->enabled = !empty($apiKey);
    }
    
    /**
     * Enable HaveIBeenPwned API
     * 
     * @param string $apiKey API key
     * @return self
     */
    public function enableHaveIBeenPwnedAPI(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        $this->enabled = true;
        return $this;
    }
    
    /**
     * Check if password has been breached
     * 
     * @param string $password Password to check
     * @return bool True if breached, false if safe
     */
    public function isPasswordBreached(string $password): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);
        
        $url = "https://api.pwnedpasswords.com/range/{$prefix}";
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'JAKPOS-Security/1.0',
                CURLOPT_HTTPHEADER => [
                    'hibp-api-key: ' . $this->apiKey,
                    'Add-Padding: true'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                foreach (explode("\n", $response) as $line) {
                    if (strpos($line, $suffix) === 0) {
                        $parts = explode(':', $line);
                        $count = isset($parts[1]) ? (int) $parts[1] : 0;
                        return $count > 0;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Breach detection failed: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get breach count for password
     * 
     * @param string $password Password to check
     * @return int Number of times breached
     */
    public function getBreachCount(string $password): int
    {
        if (!$this->enabled) {
            return 0;
        }
        
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);
        
        $url = "https://api.pwnedpasswords.com/range/{$prefix}";
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'JAKPOS-Security/1.0'
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                foreach (explode("\n", $response) as $line) {
                    if (strpos($line, $suffix) === 0) {
                        $parts = explode(':', $line);
                        return isset($parts[1]) ? (int) $parts[1] : 0;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Breach count failed: " . $e->getMessage());
        }
        
        return 0;
    }
}