<?php
/**
 * Input Validation Helper
 * Centralized validation for user inputs
 */

class InputValidator
{
    private array $errors = [];
    private array $data = [];
    
    /**
     * Validate and sanitize integer
     */
    public function int(string $key, $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if ($value === null || $value === '') {
            $this->data[$key] = null;
            return $this;
        }
        
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            $this->errors[$key] = "$key must be a valid integer";
        } elseif ($int < $min || $int > $max) {
            $this->errors[$key] = "$key must be between $min and $max";
        } else {
            $this->data[$key] = $int;
        }
        
        return $this;
    }
    
    /**
     * Validate and sanitize float/decimal
     */
    public function float(string $key, $value, float $min = -INF, float $max = INF, bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if ($value === null || $value === '') {
            $this->data[$key] = null;
            return $this;
        }
        
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false) {
            $this->errors[$key] = "$key must be a valid number";
        } elseif ($float < $min || $float > $max) {
            $this->errors[$key] = "$key must be between $min and $max";
        } else {
            $this->data[$key] = $float;
        }
        
        return $this;
    }
    
    /**
     * Validate and sanitize string
     */
    public function string(string $key, $value, int $minLen = 0, int $maxLen = 255, bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if ($value === null || $value === '') {
            $this->data[$key] = null;
            return $this;
        }
        
        $str = trim((string) $value);
        $len = mb_strlen($str);
        
        if ($len < $minLen) {
            $this->errors[$key] = "$key must be at least $minLen characters";
        } elseif ($len > $maxLen) {
            $this->errors[$key] = "$key must be at most $maxLen characters";
        } else {
            $this->data[$key] = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        }
        
        return $this;
    }
    
    /**
     * Validate email address
     */
    public function email(string $key, $value, bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if ($value === null || $value === '') {
            $this->data[$key] = null;
            return $this;
        }
        
        $email = filter_var($value, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            $this->errors[$key] = "$key must be a valid email address";
        } else {
            $this->data[$key] = $email;
        }
        
        return $this;
    }
    
    /**
     * Validate enum/set values
     */
    public function enum(string $key, $value, array $allowed, bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if ($value === null || $value === '') {
            $this->data[$key] = null;
            return $this;
        }
        
        if (!in_array($value, $allowed, true)) {
            $this->errors[$key] = "$key must be one of: " . implode(', ', $allowed);
        } else {
            $this->data[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Validate date
     */
    public function date(string $key, $value, string $format = 'Y-m-d', bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if ($value === null || $value === '') {
            $this->data[$key] = null;
            return $this;
        }
        
        $date = DateTime::createFromFormat($format, $value);
        if (!$date || $date->format($format) !== $value) {
            $this->errors[$key] = "$key must be a valid date ($format)";
        } else {
            $this->data[$key] = $date->format($format);
        }
        
        return $this;
    }
    
    /**
     * Validate array
     */
    public function array(string $key, $value, bool $required = true): self
    {
        if ($required && ($value === null || $value === '')) {
            $this->errors[$key] = "$key is required";
            return $this;
        }
        
        if (!is_array($value)) {
            $this->errors[$key] = "$key must be an array";
        } else {
            $this->data[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function errors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get first error message
     */
    public function firstError(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }
    
    /**
     * Get validated data
     */
    public function data(): array
    {
        return $this->data;
    }
    
    /**
     * Get single validated value
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * Throw exception if validation fails
     */
    public function validate(): void
    {
        if ($this->fails()) {
            throw new ValidationException($this->firstError(), $this->errors());
        }
    }
}

/**
 * Validation Exception
 */
class ValidationException extends Exception
{
    private array $errors;
    
    public function __construct(string $message, array $errors = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
