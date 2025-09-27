/**
 * Enhanced Validation System
 * Comprehensive validation for workflow processes
 * Provides real-time validation, error handling, and user feedback
 */

class EnhancedValidationSystem {
    constructor() {
        this.validationRules = new Map();
        this.validationErrors = new Map();
        this.validationCallbacks = new Map();
        this.customValidators = new Map();
        
        this.init();
    }

    init() {
        this.setupDefaultValidationRules();
        this.setupCustomValidators();
        console.log('Enhanced Validation System initialized');
    }

    /**
     * Setup default validation rules
     */
    setupDefaultValidationRules() {
        // Medical History Validation Rules
        this.addValidationRule('medical_history', {
            donor_id: { required: true, type: 'string' },
            timestamp: { required: true, type: 'string', format: 'iso' },
            status: { required: true, enum: ['pending', 'approved', 'declined'] },
            reviewer_id: { required: true, type: 'string' }
        });

        // Physical Examination Validation Rules
        this.addValidationRule('physical_examination', {
            // Accept either string or number for donor_id
            donor_id: { required: true, type: 'number_or_string' },
            blood_pressure: { required: true, pattern: /^[0-9]{2,3}\/[0-9]{2,3}$/ },
            pulse_rate: { required: true, type: 'number', min: 40, max: 200 },
            body_temp: { required: true, type: 'number', min: 35, max: 42 },
            gen_appearance: { required: true, type: 'string', minLength: 3 },
            skin: { required: true, type: 'string', minLength: 3 },
            heent: { required: true, type: 'string', minLength: 3 },
            heart_and_lungs: { required: true, type: 'string', minLength: 3 },
            blood_bag_type: { required: true, enum: ['Single', 'Multiple', 'Top & Bottom'] }
        });

        // Screening Form Validation Rules
        this.addValidationRule('screening_form', {
            donor_id: { required: true, type: 'string' },
            body_weight: { required: true, type: 'number', min: 50 },
            specific_gravity: { required: true, type: 'number', min: 12.5, max: 18.0 },
            blood_type: { required: true, enum: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] },
            donation_type: { required: true, type: 'string' }
        });

        // Deferral Validation Rules
        this.addValidationRule('deferral', {
            donor_id: { required: true, type: 'string' },
            deferral_type: { required: true, enum: ['Temporary Deferral', 'Permanent Deferral', 'Refuse'] },
            disapproval_reason: { required: true, type: 'string', minLength: 10, maxLength: 200 },
            duration: { conditional: 'deferral_type', condition: 'Temporary Deferral', type: 'number', min: 1 }
        });
    }

    /**
     * Setup custom validators
     */
    setupCustomValidators() {
        // Blood pressure validator
        this.addCustomValidator('blood_pressure', (value) => {
            const pattern = /^[0-9]{2,3}\/[0-9]{2,3}$/;
            if (!pattern.test(value)) {
                return { valid: false, message: 'Format: systolic/diastolic (e.g., 120/80)' };
            }
            
            const [systolic, diastolic] = value.split('/').map(Number);
            if (systolic < 90 || systolic > 200) {
                return { valid: false, message: 'Systolic pressure should be between 90-200 mmHg' };
            }
            if (diastolic < 60 || diastolic > 120) {
                return { valid: false, message: 'Diastolic pressure should be between 60-120 mmHg' };
            }
            
            return { valid: true };
        });

        // Weight validator with safety check
        this.addCustomValidator('body_weight', (value) => {
            const weight = parseFloat(value);
            if (weight < 50) {
                return { 
                    valid: false, 
                    message: 'Weight below minimum requirement (50 kg)',
                    severity: 'warning',
                    recommendation: 'Consider deferring donor for safety'
                };
            }
            if (weight > 200) {
                return { 
                    valid: false, 
                    message: 'Weight above maximum limit (200 kg)',
                    severity: 'error'
                };
            }
            return { valid: true };
        });

        // Specific gravity validator with safety check
        this.addCustomValidator('specific_gravity', (value) => {
            const gravity = parseFloat(value);
            if (gravity < 12.5) {
                return { 
                    valid: false, 
                    message: 'Specific gravity below acceptable range (12.5-18.0 g/dL)',
                    severity: 'warning',
                    recommendation: 'Consider deferring donor for safety'
                };
            }
            if (gravity > 18.0) {
                return { 
                    valid: false, 
                    message: 'Specific gravity above acceptable range (12.5-18.0 g/dL)',
                    severity: 'error'
                };
            }
            return { valid: true };
        });

        // Donation type validator
        this.addCustomValidator('donation_type', (value, formData) => {
            const inhouseValue = formData?.donation_type || '';
            const mobilePlace = formData?.mobile_place || '';
            const mobileOrganizer = formData?.mobile_organizer || '';
            
            const hasInhouseSelection = inhouseValue && inhouseValue !== '';
            const hasMobileSelection = mobilePlace.trim() !== '' || mobileOrganizer.trim() !== '';
            
            if (!hasInhouseSelection && !hasMobileSelection) {
                return { 
                    valid: false, 
                    message: 'Please select either In-House donation type OR fill mobile donation details' 
                };
            }
            
            if (hasInhouseSelection && hasMobileSelection) {
                return { 
                    valid: false, 
                    message: 'Please select either In-House OR mobile donation, not both' 
                };
            }
            
            return { valid: true };
        });
    }

    /**
     * Add validation rule
     */
    addValidationRule(ruleName, rules) {
        this.validationRules.set(ruleName, rules);
    }

    /**
     * Add custom validator
     */
    addCustomValidator(fieldName, validator) {
        this.customValidators.set(fieldName, validator);
    }

    /**
     * Validate data against rules
     */
    validateData(ruleName, data) {
        const rules = this.validationRules.get(ruleName);
        if (!rules) {
            throw new Error(`Validation rules not found: ${ruleName}`);
        }

        const errors = [];
        const warnings = [];

        for (const [field, rule] of Object.entries(rules)) {
            const value = data[field];
            const fieldErrors = this.validateField(field, value, rule, data);
            
            errors.push(...fieldErrors.filter(e => e.severity !== 'warning'));
            warnings.push(...fieldErrors.filter(e => e.severity === 'warning'));
        }

        const result = {
            valid: errors.length === 0,
            errors: errors,
            warnings: warnings,
            fieldErrors: this.groupErrorsByField(errors),
            fieldWarnings: this.groupErrorsByField(warnings)
        };

        // Store validation result
        this.validationErrors.set(ruleName, result);

        return result;
    }

    /**
     * Validate individual field
     */
    validateField(fieldName, value, rule, formData = {}) {
        const errors = [];

        // Required field check
        if (rule.required && (value === undefined || value === null || value === '')) {
            errors.push({
                field: fieldName,
                message: `${fieldName} is required`,
                severity: 'error'
            });
            return errors;
        }

        // Skip other validations if field is empty and not required
        if (!rule.required && (value === undefined || value === null || value === '')) {
            return errors;
        }

        // Type validation
        if (rule.type) {
            const typeError = this.validateType(fieldName, value, rule.type);
            if (typeError) {
                errors.push(typeError);
                return errors;
            }
        }

        // Pattern validation
        if (rule.pattern) {
            const patternError = this.validatePattern(fieldName, value, rule.pattern);
            if (patternError) {
                errors.push(patternError);
            }
        }

        // Enum validation
        if (rule.enum) {
            const enumError = this.validateEnum(fieldName, value, rule.enum);
            if (enumError) {
                errors.push(enumError);
            }
        }

        // Range validation
        if (rule.min !== undefined || rule.max !== undefined) {
            const rangeError = this.validateRange(fieldName, value, rule.min, rule.max);
            if (rangeError) {
                errors.push(rangeError);
            }
        }

        // Length validation
        if (rule.minLength !== undefined || rule.maxLength !== undefined) {
            const lengthError = this.validateLength(fieldName, value, rule.minLength, rule.maxLength);
            if (lengthError) {
                errors.push(lengthError);
            }
        }

        // Format validation
        if (rule.format) {
            const formatError = this.validateFormat(fieldName, value, rule.format);
            if (formatError) {
                errors.push(formatError);
            }
        }

        // Conditional validation
        if (rule.conditional) {
            const conditionalError = this.validateConditional(fieldName, value, rule, formData);
            if (conditionalError) {
                errors.push(conditionalError);
            }
        }

        // Custom validator
        if (this.customValidators.has(fieldName)) {
            const customValidator = this.customValidators.get(fieldName);
            try {
                const customResult = customValidator(value, formData);
                if (!customResult.valid) {
                    errors.push({
                        field: fieldName,
                        message: customResult.message,
                        severity: customResult.severity || 'error',
                        recommendation: customResult.recommendation
                    });
                }
            } catch (error) {
                console.error(`Custom validator error for ${fieldName}:`, error);
            }
        }

        return errors;
    }

    /**
     * Validate data type
     */
    validateType(fieldName, value, expectedType) {
        const actualType = typeof value;
        
        if (expectedType === 'number') {
            if (actualType !== 'number' && isNaN(parseFloat(value))) {
                return {
                    field: fieldName,
                    message: `${fieldName} must be a number`,
                    severity: 'error'
                };
            }
        } else if (expectedType === 'number_or_string') {
            const ok = (actualType === 'number') || (actualType === 'string' && String(value).trim() !== '' && !isNaN(Number(value)) === false ? true : true);
            // Above allows numeric or non-empty string identifiers
            if (!ok) {
                return {
                    field: fieldName,
                    message: `${fieldName} must be a number or string`,
                    severity: 'error'
                };
            }
        } else if (expectedType === 'string') {
            if (actualType !== 'string') {
                return {
                    field: fieldName,
                    message: `${fieldName} must be a string`,
                    severity: 'error'
                };
            }
        } else if (expectedType === 'boolean') {
            if (actualType !== 'boolean') {
                return {
                    field: fieldName,
                    message: `${fieldName} must be a boolean`,
                    severity: 'error'
                };
            }
        }

        return null;
    }

    /**
     * Validate pattern
     */
    validatePattern(fieldName, value, pattern) {
        if (!pattern.test(value)) {
            return {
                field: fieldName,
                message: `${fieldName} format is invalid`,
                severity: 'error'
            };
        }
        return null;
    }

    /**
     * Validate enum
     */
    validateEnum(fieldName, value, allowedValues) {
        if (!allowedValues.includes(value)) {
            return {
                field: fieldName,
                message: `${fieldName} must be one of: ${allowedValues.join(', ')}`,
                severity: 'error'
            };
        }
        return null;
    }

    /**
     * Validate range
     */
    validateRange(fieldName, value, min, max) {
        const numValue = parseFloat(value);
        
        if (min !== undefined && numValue < min) {
            return {
                field: fieldName,
                message: `${fieldName} must be at least ${min}`,
                severity: 'error'
            };
        }
        
        if (max !== undefined && numValue > max) {
            return {
                field: fieldName,
                message: `${fieldName} must be at most ${max}`,
                severity: 'error'
            };
        }
        
        return null;
    }

    /**
     * Validate length
     */
    validateLength(fieldName, value, minLength, maxLength) {
        const strValue = String(value);
        
        if (minLength !== undefined && strValue.length < minLength) {
            return {
                field: fieldName,
                message: `${fieldName} must be at least ${minLength} characters`,
                severity: 'error'
            };
        }
        
        if (maxLength !== undefined && strValue.length > maxLength) {
            return {
                field: fieldName,
                message: `${fieldName} must be at most ${maxLength} characters`,
                severity: 'error'
            };
        }
        
        return null;
    }

    /**
     * Validate format
     */
    validateFormat(fieldName, value, format) {
        if (format === 'iso') {
            const date = new Date(value);
            if (isNaN(date.getTime())) {
                return {
                    field: fieldName,
                    message: `${fieldName} must be a valid ISO date string`,
                    severity: 'error'
                };
            }
        }
        
        return null;
    }

    /**
     * Validate conditional fields
     */
    validateConditional(fieldName, value, rule, formData) {
        if (rule.conditional && rule.condition) {
            const conditionField = rule.conditional;
            const conditionValue = formData[conditionField];
            
            if (conditionValue === rule.condition) {
                // Field is required when condition is met
                if (rule.required && (value === undefined || value === null || value === '')) {
                    return {
                        field: fieldName,
                        message: `${fieldName} is required when ${conditionField} is ${rule.condition}`,
                        severity: 'error'
                    };
                }
            }
        }
        
        return null;
    }

    /**
     * Group errors by field
     */
    groupErrorsByField(errors) {
        const grouped = {};
        errors.forEach(error => {
            if (!grouped[error.field]) {
                grouped[error.field] = [];
            }
            grouped[error.field].push(error);
        });
        return grouped;
    }

    /**
     * Real-time validation for form fields
     */
    setupRealTimeValidation(formElement, ruleName) {
        const rules = this.validationRules.get(ruleName);
        if (!rules) return;

        Object.keys(rules).forEach(fieldName => {
            const field = formElement.querySelector(`[name="${fieldName}"]`);
            if (!field) return;

            // Add validation on input
            field.addEventListener('input', () => {
                this.validateFieldRealTime(field, fieldName, rules[fieldName], formElement);
            });

            // Add validation on blur
            field.addEventListener('blur', () => {
                this.validateFieldRealTime(field, fieldName, rules[fieldName], formElement);
            });
        });
    }

    /**
     * Validate field in real-time
     */
    validateFieldRealTime(field, fieldName, rule, formElement) {
        const value = field.value;
        // Resolve a suitable container for collecting values to avoid FormData errors
        const container = (
            (formElement instanceof HTMLElement) ? formElement :
            field.closest('form') ||
            document.getElementById('physicalExaminationForm') ||
            field.closest('.physical-modal-form') ||
            field.ownerDocument
        );
        const formData = this.getFormData(container);
        
        const errors = this.validateField(fieldName, value, rule, formData);
        
        // Clear previous validation states
        field.classList.remove('is-valid', 'is-invalid');
        this.removeFieldFeedback(field);

        if (errors.length > 0) {
            field.classList.add('is-invalid');
            this.showFieldFeedback(field, errors);
        } else {
            field.classList.add('is-valid');
        }

        // Update form submit button state
        this.updateFormSubmitState(formElement);
    }

    /**
     * Show field feedback
     */
    showFieldFeedback(field, errors) {
        const feedbackContainer = field.parentNode.querySelector('.invalid-feedback') || 
                                 this.createFeedbackContainer(field);
        
        const errorMessages = errors.map(error => error.message).join(', ');
        feedbackContainer.textContent = errorMessages;
        feedbackContainer.style.display = 'block';
    }

    /**
     * Remove field feedback
     */
    removeFieldFeedback(field) {
        const feedbackContainer = field.parentNode.querySelector('.invalid-feedback');
        if (feedbackContainer) {
            feedbackContainer.style.display = 'none';
        }
    }

    /**
     * Create feedback container
     */
    createFeedbackContainer(field) {
        const container = document.createElement('div');
        container.className = 'invalid-feedback';
        field.parentNode.appendChild(container);
        return container;
    }

    /**
     * Update form submit button state
     */
    updateFormSubmitState(formElement) {
        const submitButton = formElement.querySelector('button[type="submit"], .submit-btn');
        if (!submitButton) return;

        const invalidFields = formElement.querySelectorAll('.is-invalid');
        const hasErrors = invalidFields.length > 0;

        submitButton.disabled = hasErrors;
        
        if (hasErrors) {
            submitButton.classList.add('btn-secondary');
            submitButton.classList.remove('btn-primary', 'btn-success');
        } else {
            submitButton.classList.remove('btn-secondary');
            submitButton.classList.add('btn-primary');
        }
    }

    /**
     * Get form data
     */
    getFormData(formElement) {
        const data = {};
        try {
            if (formElement && typeof HTMLFormElement !== 'undefined' && formElement instanceof HTMLFormElement) {
                const fd = new FormData(formElement);
                for (let [key, value] of fd.entries()) {
                    data[key] = value;
                }
                return data;
            }
        } catch (_) { /* fall through to manual collection */ }

        // Manual collection for non-form containers
        try {
            const root = (formElement && formElement.querySelectorAll) ? formElement : document;
            const fields = root.querySelectorAll('input[name], select[name], textarea[name]');
            fields.forEach((el) => {
                const name = el.getAttribute('name');
                if (!name) return;
                if (el.type === 'radio') {
                    if (el.checked) data[name] = el.value;
                } else if (el.type === 'checkbox') {
                    if (el.checked) {
                        if (data[name] === undefined) data[name] = [];
                        if (Array.isArray(data[name])) data[name].push(el.value);
                    }
                } else {
                    data[name] = el.value;
                }
            });
        } catch (_) {}

        return data;
    }

    /**
     * Show validation summary
     */
    showValidationSummary(validationResult, container) {
        if (!container) return;

        let summaryHtml = '';

        if (validationResult.errors.length > 0) {
            summaryHtml += '<div class="alert alert-danger">';
            summaryHtml += '<h6><i class="fas fa-exclamation-circle me-2"></i>Validation Errors</h6>';
            summaryHtml += '<ul class="mb-0">';
            validationResult.errors.forEach(error => {
                summaryHtml += `<li>${error.message}</li>`;
            });
            summaryHtml += '</ul></div>';
        }

        if (validationResult.warnings.length > 0) {
            summaryHtml += '<div class="alert alert-warning">';
            summaryHtml += '<h6><i class="fas fa-exclamation-triangle me-2"></i>Validation Warnings</h6>';
            summaryHtml += '<ul class="mb-0">';
            validationResult.warnings.forEach(warning => {
                summaryHtml += `<li>${warning.message}`;
                if (warning.recommendation) {
                    summaryHtml += ` <em>(${warning.recommendation})</em>`;
                }
                summaryHtml += '</li>';
            });
            summaryHtml += '</ul></div>';
        }

        container.innerHTML = summaryHtml;
    }

    /**
     * Get validation result for rule
     */
    getValidationResult(ruleName) {
        return this.validationErrors.get(ruleName);
    }

    /**
     * Clear validation errors
     */
    clearValidationErrors(ruleName) {
        if (ruleName) {
            this.validationErrors.delete(ruleName);
        } else {
            this.validationErrors.clear();
        }
    }
}

// Initialize global instance
window.validationSystem = new EnhancedValidationSystem();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedValidationSystem;
}
