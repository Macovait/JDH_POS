<?php
/**
 * Device Fingerprinting - Browser/device identification
 * 
 * @package JDH_POS\Security
 * @version 1.0
 */

namespace JDH\Security;

class DeviceFingerprint
{
    /**
     * Generate unique device fingerprint
     * 
     * @param array $data Device data
     * @return string
     */
    public function generate(array $data): string
    {
        $components = [
            $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
            $data['accept_language'] ?? $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $data['platform'] ?? php_uname('s'),
            $data['timezone'] ?? date_default_timezone_get(),
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['HTTP_ACCEPT'] ?? '',
            isset($_SERVER['HTTP_SEC_CH_UA']) ? json_encode($_SERVER['HTTP_SEC_CH_UA']) : '',
            isset($_SERVER['HTTP_SEC_CH_UA_PLATFORM']) ? $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] : '',
            $data['screen_resolution'] ?? '',
            $data['color_depth'] ?? '',
            $data['touch_support'] ?? false
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Get device type from user agent
     * 
     * @param string|null $userAgent
     * @return string
     */
    public function getDeviceType(?string $userAgent = null): string
    {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/mobile/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }
    
    /**
     * Get OS from user agent
     * 
     * @param string|null $userAgent
     * @return string
     */
    public function getOS(?string $userAgent = null): string
    {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $osList = [
            'Windows 10' => 'Windows NT 10.0',
            'Windows 8.1' => 'Windows NT 6.3',
            'Windows 8' => 'Windows NT 6.2',
            'Windows 7' => 'Windows NT 6.1',
            'Windows Vista' => 'Windows NT 6.0',
            'macOS' => 'Mac OS X',
            'iOS' => 'iPhone|iPad|iPod',
            'Android' => 'Android',
            'Linux' => 'Linux',
            'Chrome OS' => 'CrOS'
        ];
        
        foreach ($osList as $os => $pattern) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                return $os;
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Get browser from user agent
     * 
     * @param string|null $userAgent
     * @return string
     */
    public function getBrowser(?string $userAgent = null): string
    {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browsers = [
            'Edge' => 'Edg',
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Opera' => 'OPR',
            'IE' => 'MSIE|Trident'
        ];
        
        foreach ($browsers as $browser => $pattern) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                return $browser;
            }
        }
        
        return 'Unknown';
    }
}