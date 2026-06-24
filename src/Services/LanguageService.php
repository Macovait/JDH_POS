<?php
/**
 * Language / Translation Service
 *
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Services;

class LanguageService {
    private static ?self $instance = null;
    private string $locale;
    private array $translations = [];
    private array $fallbackTranslations = [];
    private static array $rtlLocales = ['ar', 'he', 'fa', 'ur'];

    public static function getInstance(): self {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        $this->locale = $_SESSION['locale'] ?? ($_COOKIE['locale'] ?? 'en');
        $this->loadTranslations($this->locale);
        $this->loadFallback();
    }

    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): void {
        $this->locale = $locale;
        $_SESSION['locale'] = $locale;
        setcookie('locale', $locale, time() + 86400 * 30, '/');
        $this->loadTranslations($locale);
    }

    public function isRtl(): bool { return in_array($this->locale, self::$rtlLocales, true); }

    public function translate(string $key, array $replace = []): string {
        $keys = explode('.', $key);
        $value = $this->resolve($keys, $this->translations) ?? $this->resolve($keys, $this->fallbackTranslations) ?? $key;
        foreach ($replace as $k => $v) { $value = str_replace(':' . $k, $v, $value); }
        return $value;
    }

    public static function availableLocales(): array {
        $path = __DIR__ . '/../../resources/lang/';
        $locales = [];
        foreach (glob($path . '*.json') as $file) {
            $locales[] = basename($file, '.json');
        }
        return $locales;
    }

    private function loadTranslations(string $locale): void {
        $file = __DIR__ . '/../../resources/lang/' . $locale . '.json';
        $this->translations = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    }

    private function loadFallback(): void {
        $file = __DIR__ . '/../../resources/lang/en.json';
        $this->fallbackTranslations = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    }

    private function resolve(array $keys, array $array) {
        $value = $array;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) return null;
            $value = $value[$k];
        }
        return is_string($value) ? $value : null;
    }
}

if (!function_exists('__t')) {
    function __t(string $key, array $replace = []): string {
        return \JDH_POS\Services\LanguageService::getInstance()->translate($key, $replace);
    }
}

if (!function_exists('locale')) {
    function locale(): string {
        return \JDH_POS\Services\LanguageService::getInstance()->getLocale();
    }
}

if (!function_exists('is_rtl')) {
    function is_rtl(): bool {
        return \JDH_POS\Services\LanguageService::getInstance()->isRtl();
    }
}
