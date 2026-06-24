/**
 * Form Validator
 * Client-side form validation with real-time feedback
 * @version 1.0.0
 */

class FormValidator {
    constructor(formElement, options = {}) {
        this.form = typeof formElement === 'string' 
            ? document.querySelector(formElement) 
            : formElement;
        
        if (!this.form) return;
        
        this.options = {
            validateOnBlur: true,
            validateOnInput: true,
            showErrors: true,
            ...options
        };
        
        this.errors = new Map();
        this.rules = new Map();
        
        this.init();
    }
    
    init() {
        // Add validation styles
        if (!document.getElementById('validation-styles')) {
            const style = document.createElement('style');
            style.id = 'validation-styles';
            style.textContent = `
                .field-error {
                    border-color: #ef4444 !important;
                    background-color: rgba(239, 68, 68, 0.1) !important;
                }
                .field-success {
                    border-color: #10b981 !important;
                }
                .error-message {
                    color: #ef4444;
                    font-size: 12px;
                    margin-top: 4px;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }
                .error-message::before {
                    content: '⚠️';
                }
                .field-hint {
                    color: #64748b;
                    font-size: 12px;
                    margin-top: 4px;
                }
            `;
            document.head.appendChild(style);
        }
        
        // Bind events
        if (this.options.validateOnBlur) {
            this.form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('blur', () => this.validateField(field));
            });
        }
        
        if (this.options.validateOnInput) {
            this.form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('input', () => this.validateField(field));
            });
        }
        
        // Form submit validation
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                e.stopPropagation();
                this.focusFirstError();
            }
        });
    }
    
    /**
     * Add validation rule to a field
     */
    addRule(fieldName, rules) {
        this.rules.set(fieldName, rules);
    }
    
    /**
     * Validate a single field
     */
    validateField(field) {
        const name = field.name;
        const value = field.value.trim();
        const rules = this.rules.get(name);
        
        if (!rules) return true;
        
        const errors = [];
        
        // Required check
        if (rules.required && !value) {
            errors.push(rules.requiredMessage || 'This field is required');
        }
        
        // Min length
        if (rules.minLength && value.length < rules.minLength) {
            errors.push(`Must be at least ${rules.minLength} characters`);
        }
        
        // Max length
        if (rules.maxLength && value.length > rules.maxLength) {
            errors.push(`Must be no more than ${rules.maxLength} characters`);
        }
        
        // Pattern/Regex
        if (rules.pattern && value && !rules.pattern.test(value)) {
            errors.push(rules.patternMessage || 'Invalid format');
        }
        
        // Email validation
        if (rules.email && value && !this.isValidEmail(value)) {
            errors.push('Please enter a valid email address');
        }
        
        // Number validation
        if (rules.number && value && isNaN(value)) {
            errors.push('Please enter a valid number');
        }
        
        // Min value
        if (rules.minValue && value && Number(value) < rules.minValue) {
            errors.push(`Must be at least ${rules.minValue}`);
        }
        
        // Max value
        if (rules.maxValue && value && Number(value) > rules.maxValue) {
            errors.push(`Must be no more than ${rules.maxValue}`);
        }
        
        // Phone validation
        if (rules.phone && value && !this.isValidPhone(value)) {
            errors.push('Please enter a valid phone number');
        }
        
        // Password strength
        if (rules.password && value && !this.isStrongPassword(value)) {
            errors.push(rules.passwordMessage || 'Password is not strong enough');
        }
        
        // Custom validator
        if (rules.custom && value) {
            const customError = rules.custom(value, field);
            if (customError) {
                errors.push(customError);
            }
        }
        
        // Match another field
        if (rules.match && value) {
            const matchField = this.form.querySelector(`[name="${rules.match}"]`);
            if (matchField && value !== matchField.value) {
                errors.push(rules.matchMessage || 'Fields do not match');
            }
        }
        
        // Update UI
        if (errors.length > 0) {
            this.errors.set(name, errors);
            this.showFieldError(field, errors[0]);
            return false;
        } else {
            this.errors.delete(name);
            this.showFieldSuccess(field);
            return true;
        }
    }
    
    /**
     * Validate entire form
     */
    validate() {
        this.errors.clear();
        let isValid = true;
        
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    /**
     * Show error for a field
     */
    showFieldError(field, message) {
        field.classList.remove('field-success');
        field.classList.add('field-error');
        
        // Remove existing error
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) existingError.remove();
        
        if (this.options.showErrors) {
            const error = document.createElement('div');
            error.className = 'error-message';
            error.textContent = message;
            field.parentNode.appendChild(error);
        }
    }
    
    /**
     * Show success state for a field
     */
    showFieldSuccess(field) {
        field.classList.remove('field-error');
        field.classList.add('field-success');
        
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) existingError.remove();
    }
    
    /**
     * Focus first field with error
     */
    focusFirstError() {
        const firstError = this.form.querySelector('.field-error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    /**
     * Get all errors
     */
    getErrors() {
        return Object.fromEntries(this.errors);
    }
    
    /**
     * Check if form is valid
     */
    isValid() {
        return this.errors.size === 0;
    }
    
    // Validation helpers
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    isValidPhone(phone) {
        return /^[\d\s\-+()]{7,20}$/.test(phone);
    }
    
    isStrongPassword(password) {
        return password.length >= 8 
            && /[A-Z]/.test(password)
            && /[a-z]/.test(password)
            && /[0-9]/.test(password)
            && /[^A-Za-z0-9]/.test(password);
    }
}

// Password strength indicator
class PasswordStrengthIndicator {
    constructor(inputSelector, meterSelector) {
        this.input = document.querySelector(inputSelector);
        this.meter = document.querySelector(meterSelector);
        
        if (!this.input || !this.meter) return;
        
        this.input.addEventListener('input', () => this.updateStrength());
    }
    
    updateStrength() {
        const password = this.input.value;
        const strength = this.calculateStrength(password);
        
        this.meter.style.width = `${strength.score * 25}%`;
        this.meter.style.backgroundColor = strength.color;
        
        if (this.meter.nextElementSibling) {
            this.meter.nextElementSibling.textContent = strength.label;
        }
    }
    
    calculateStrength(password) {
        let score = 0;
        
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'];
        const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        
        return {
            score: Math.min(score, 4),
            color: colors[Math.min(score, 4)],
            label: labels[Math.min(score, 4)]
        };
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FormValidator, PasswordStrengthIndicator };
}
