<?php
/**
 * Central Input Validation System
 * Validates and sanitizes all input data consistently
 */

namespace JDH\POS\Validation {

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $rules = [];
    
    /**
     * Define validation rules
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }
    
    /**
     * Validate data against rules
     */
    public function validate(array $data): bool
    {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
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
        return $this->errors[0] ?? null;
    }
    
    /**
     * Get validated and sanitized data
     */
    public function validated(): array
    {
        $validated = [];
        foreach ($this->rules as $field => $rules) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->sanitize($this->data[$field], $rules);
            }
        }
        return $validated;
    }
    
    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, $value, string $rule): void
    {
        // Parse rule with parameters: rule:param1,param2
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];
        
        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, "{$field} is required.");
                }
                break;
                
            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->addError($field, "{$field} must be a string.");
                }
                break;
                
            case 'int':
            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "{$field} must be an integer.");
                }
                break;
                
            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->addError($field, "{$field} must be numeric.");
                }
                break;
                
            case 'float':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    $this->addError($field, "{$field} must be a decimal number.");
                }
                break;
                
            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$field} must be a valid email address.");
                }
                break;
                
            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_string($value) && strlen($value) < $min) {
                    $this->addError($field, "{$field} must be at least {$min} characters.");
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, "{$field} must be at least {$min}.");
                }
                break;
                
            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (is_string($value) && strlen($value) > $max) {
                    $this->addError($field, "{$field} must not exceed {$max} characters.");
                } elseif (is_numeric($value) && $value > $max) {
                    $this->addError($field, "{$field} must not exceed {$max}.");
                }
                break;
                
            case 'between':
                $min = (int) ($params[0] ?? 0);
                $max = (int) ($params[1] ?? PHP_INT_MAX);
                if (is_numeric($value) && ($value < $min || $value > $max)) {
                    $this->addError($field, "{$field} must be between {$min} and {$max}.");
                }
                break;
                
            case 'in':
                $allowed = $params;
                if ($value !== null && !in_array($value, $allowed, true)) {
                    $this->addError($field, "{$field} must be one of: " . implode(', ', $allowed));
                }
                break;
                
            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->addError($field, "{$field} must be an array.");
                }
                break;
                
            case 'bool':
            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->addError($field, "{$field} must be true or false.");
                }
                break;
                
            case 'date':
                if ($value !== null && !strtotime($value)) {
                    $this->addError($field, "{$field} must be a valid date.");
                }
                break;
                
            case 'url':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$field} must be a valid URL.");
                }
                break;
                
            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value !== null && !preg_match($pattern, $value)) {
                    $this->addError($field, "{$field} format is invalid.");
                }
                break;
                
            case 'alpha':
                if ($value !== null && !preg_match('/^[a-zA-Z]+$/', $value)) {
                    $this->addError($field, "{$field} may only contain letters.");
                }
                break;
                
            case 'alphanumeric':
                if ($value !== null && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    $this->addError($field, "{$field} may only contain letters and numbers.");
                }
                break;
                
            case 'phone':
                if ($value !== null && !preg_match('/^[\d\s\-\+\(\)]+$/', $value)) {
                    $this->addError($field, "{$field} must be a valid phone number.");
                }
                break;
                
            case 'uuid':
                if ($value !== null && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $value)) {
                    $this->addError($field, "{$field} must be a valid UUID.");
                }
                break;
                
            case 'json':
                if ($value !== null) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->addError($field, "{$field} must be valid JSON.");
                    }
                }
                break;
                
            case 'confirmed':
                $confirmationField = $field . '_confirmation';
                if ($value !== ($this->data[$confirmationField] ?? null)) {
                    $this->addError($field, "{$field} confirmation does not match.");
                }
                break;
                
            case 'unique':
                // Requires database check - skip for now
                break;
                
            case 'exists':
                // Requires database check - skip for now
                break;
        }
    }
    
    /**
     * Sanitize value based on rules
     */
    private function sanitize($value, $rules)
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        // Determine type from rules
        if (in_array('int', $rules) || in_array('integer', $rules)) {
            return (int) $value;
        }
        
        if (in_array('float', $rules)) {
            return (float) $value;
        }
        
        if (in_array('bool', $rules) || in_array('boolean', $rules)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        if (in_array('email', $rules)) {
            return strtolower(trim($value));
        }
        
        if (in_array('string', $rules)) {
            return trim($value);
        }
        
        if (in_array('array', $rules)) {
            return is_array($value) ? $value : [$value];
        }
        
        // Default: trim strings
        if (is_string($value)) {
            return trim($value);
        }
        
        return $value;
    }
}

} // End namespace JDH\POS\Validation

namespace {
    /**
     * Global helper functions for validation
     */
    
    if (!function_exists('validate')) {
        /**
         * Validate input data
         * 
         * @param array $data Input data
         * @param array $rules Validation rules
         * @return array ['valid' => bool, 'errors' => array, 'data' => array]
         */
        function validate(array $data, array $rules): array
        {
            $validator = new JDH\POS\Validation\Validator();
            $validator->rules($rules);
            $valid = $validator->validate($data);
            
            return [
                'valid' => $valid,
                'errors' => $validator->errors(),
                'data' => $valid ? $validator->validated() : []
            ];
        }
    }

    if (!function_exists('validate_or_fail')) {
        /**
         * Validate or return JSON error response (for AJAX endpoints)
         */
        function validate_or_fail(array $data, array $rules): array
        {
            $result = validate($data, $rules);
            
            if (!$result['valid']) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'errors' => $result['errors']
                ]);
                exit;
            }
            
            return $result['data'];
        }
    }

    if (!function_exists('sanitize_input')) {
        /**
         * Basic input sanitization
         */
        function sanitize_input($data, string $type = 'string')
        {
            if ($data === null) return null;
            
            switch ($type) {
                case 'int':
                    return filter_var($data, FILTER_VALIDATE_INT);
                case 'float':
                    return filter_var($data, FILTER_VALIDATE_FLOAT);
                case 'email':
                    return filter_var($data, FILTER_SANITIZE_EMAIL);
                case 'url':
                    return filter_var($data, FILTER_SANITIZE_URL);
                case 'bool':
                    return filter_var($data, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                case 'string':
                default:
                    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
            }
        }
    }

    if (!function_exists('get_post_data')) {
        /**
         * Safely get POST data with sanitization
         */
        function get_post_data(string $key, string $type = 'string', $default = null)
        {
            if (!isset($_POST[$key])) {
                return $default;
            }
            return sanitize_input($_POST[$key], $type);
        }
    }
    
    if (!function_exists('get_get_data')) {
        /**
         * Safely get GET data with sanitization
         */
        function get_get_data(string $key, string $type = 'string', $default = null)
        {
            if (!isset($_GET[$key])) {
                return $default;
            }
            return sanitize_input($_GET[$key], $type);
        }
    }
}
