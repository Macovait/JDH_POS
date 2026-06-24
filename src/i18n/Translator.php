<?php
/**
 * Internationalization (i18n) System
 * Multi-language support with JSON translation files
 */

namespace JDH\POS\i18n;

class Translator
{
    private string $locale;
    private string $fallbackLocale = 'en';
    private array $translations = [];
    private string $translationsPath;
    
    public function __construct(string $locale = 'en', string $translationsPath = __DIR__ . '/../../resources/lang')
    {
        $this->locale = $locale;
        $this->translationsPath = $translationsPath;
        $this->loadTranslations();
    }
    
    /**
     * Set current locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->loadTranslations();
    }
    
    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
    
    /**
     * Translate a key with optional parameters
     * 
     * @param string $key Dot-notation key (e.g. 'auth.login.success')
     * @param array $params Replacement parameters
     * @return string
     */
    public function trans(string $key, array $params = []): string
    {
        $translation = $this->getTranslation($key);
        
        // Replace parameters
        foreach ($params as $param => $value) {
            $translation = str_replace(':' . $param, $value, $translation);
        }
        
        return $translation;
    }
    
    /**
     * Translate with pluralization
     * 
     * @param string $singular Key for singular
     * @param string $plural Key for plural
     * @param int $count Number to determine plural
     * @param array $params Replacement parameters
     * @return string
     */
    public function transChoice(string $singular, string $plural, int $count, array $params = []): string
    {
        $key = $count === 1 ? $singular : $plural;
        $params['count'] = (string) $count;
        
        return $this->trans($key, $params);
    }
    
    /**
     * Check if translation exists
     */
    public function has(string $key): bool
    {
        return $this->getTranslation($key) !== $key;
    }
    
    /**
     * Get all available locales
     */
    public function availableLocales(): array
    {
        $locales = [];
        
        foreach (glob($this->translationsPath . '/*.json') as $file) {
            $locales[] = basename($file, '.json');
        }
        
        return $locales;
    }
    
    /**
     * Load translations for current locale
     */
    private function loadTranslations(): void
    {
        $this->translations = [];
        
        // Load fallback first
        $fallbackFile = $this->translationsPath . '/' . $this->fallbackLocale . '.json';
        if (file_exists($fallbackFile)) {
            $this->translations = json_decode(file_get_contents($fallbackFile), true) ?? [];
        }
        
        // Override with current locale
        if ($this->locale !== $this->fallbackLocale) {
            $file = $this->translationsPath . '/' . $this->locale . '.json';
            if (file_exists($file)) {
                $localeTranslations = json_decode(file_get_contents($file), true) ?? [];
                $this->translations = array_merge($this->translations, $localeTranslations);
            }
        }
    }
    
    /**
     * Get translation by dot-notation key
     */
    private function getTranslation(string $key): string
    {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $key; // Return key if translation not found
            }
            $value = $value[$k];
        }
        
        return is_string($value) ? $value : $key;
    }
}

/**
 * Global translation helper functions
 */

if (!function_exists('__')) {
    /**
     * Translate a string
     * 
     * @param string $key Translation key
     * @param array $params Parameters to replace
     * @return string
     */
    function __(string $key, array $params = []): string
    {
        static $translator = null;
        
        if ($translator === null) {
            $locale = $_SESSION['locale'] ?? 'en';
            $translator = new Translator($locale);
        }
        
        return $translator->trans($key, $params);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translate with pluralization
     */
    function trans_choice(string $singular, string $plural, int $count, array $params = []): string
    {
        static $translator = null;
        
        if ($translator === null) {
            $locale = $_SESSION['locale'] ?? 'en';
            $translator = new Translator($locale);
        }
        
        return $translator->transChoice($singular, $plural, $count, $params);
    }
}

if (!function_exists('set_locale')) {
    /**
     * Set application locale
     */
    function set_locale(string $locale): void
    {
        $_SESSION['locale'] = $locale;
    }
}
