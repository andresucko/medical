// Biblioteca de validación para formularios

class FormValidator {
    constructor(formElement) {
        this.form = formElement;
        this.errors = {};
        this.fields = {};
        this.init();
    }

    init() {
        // Configurar campos del formulario
        this.setupFields();
        this.bindEvents();
    }

    setupFields() {
        const inputs = this.form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            const fieldName = input.name || input.id;
            if (fieldName) {
                this.fields[fieldName] = {
                    element: input,
                    validators: this.getValidatorsForField(input),
                    errorElement: null
                };
            }
        });
    }

    getValidatorsForField(input) {
        const validators = [];
        const type = input.type;
        const required = input.hasAttribute('required');

        // Validadores básicos
        if (required) {
            validators.push(this.validateRequired);
        }

        // Validadores por tipo
        switch (type) {
            case 'email':
                validators.push(this.validateEmail);
                break;
            case 'password':
                validators.push(this.validatePassword);
                break;
            case 'tel':
                validators.push(this.validatePhone);
                break;
            case 'number':
                validators.push(this.validateNumber);
                break;
            case 'date':
                validators.push(this.validateDate);
                break;
            case 'time':
                validators.push(this.validateTime);
                break;
        }

        // Validadores por atributos personalizados
        if (input.hasAttribute('minlength')) {
            const minLength = parseInt(input.getAttribute('minlength'));
            validators.push((value) => this.validateMinLength(value, minLength));
        }

        if (input.hasAttribute('maxlength')) {
            const maxLength = parseInt(input.getAttribute('maxlength'));
            validators.push((value) => this.validateMaxLength(value, maxLength));
        }

        if (input.hasAttribute('pattern')) {
            const pattern = new RegExp(input.getAttribute('pattern'));
            validators.push((value) => this.validatePattern(value, pattern));
        }

        return validators;
    }

    bindEvents() {
        Object.keys(this.fields).forEach(fieldName => {
            const field = this.fields[fieldName];
            field.element.addEventListener('blur', () => this.validateField(fieldName));
            field.element.addEventListener('input', () => this.clearFieldError(fieldName));
        });

        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
            }
        });
    }

    validate() {
        let isValid = true;
        this.errors = {};

        Object.keys(this.fields).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });

        this.updateUI();
        return isValid;
    }

    validateField(fieldName) {
        const field = this.fields[fieldName];
        const value = field.element.value.trim();
        const errors = [];

        field.validators.forEach(validator => {
            const result = validator.call(this, value, field.element);
            if (result !== true) {
                errors.push(result);
            }
        });

        if (errors.length > 0) {
            this.errors[fieldName] = errors;
            return false;
        } else {
            delete this.errors[fieldName];
            return true;
        }
    }

    clearFieldError(fieldName) {
        if (this.errors[fieldName]) {
            delete this.errors[fieldName];
            this.updateFieldUI(fieldName);
        }
    }

    updateUI() {
        Object.keys(this.fields).forEach(fieldName => {
            this.updateFieldUI(fieldName);
        });
    }

    updateFieldUI(fieldName) {
        const field = this.fields[fieldName];
        const hasError = this.errors[fieldName];

        // Actualizar clases del input
        field.element.classList.toggle('error', hasError);
        field.element.classList.toggle('success', !hasError && field.element.value);

        // Crear o actualizar elemento de error
        let errorElement = field.errorElement;
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            field.errorElement = errorElement;
            field.element.parentNode.appendChild(errorElement);
        }

        if (hasError) {
            errorElement.textContent = hasError[0];
            errorElement.classList.add('show');
        } else {
            errorElement.classList.remove('show');
        }
    }

    // Validadores individuales mejorados
    validateRequired(value) {
        const trimmed = value.trim();
        return trimmed !== '' || 'Este campo es obligatorio';
    }

    validateEmail(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'El email es obligatorio';

        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        if (!emailRegex.test(trimmed)) {
            return 'Ingresa un email válido';
        }

        // Validar dominio
        const domain = trimmed.split('@')[1];
        if (domain && domain.length > 253) {
            return 'El dominio del email es demasiado largo';
        }

        return true;
    }

    validatePassword(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'La contraseña es obligatoria';

        const errors = [];

        if (trimmed.length < 8) {
            errors.push('al menos 8 caracteres');
        }
        if (trimmed.length > 128) {
            errors.push('máximo 128 caracteres');
        }
        if (!/(?=.*[a-z])/.test(trimmed)) {
            errors.push('una letra minúscula');
        }
        if (!/(?=.*[A-Z])/.test(trimmed)) {
            errors.push('una letra mayúscula');
        }
        if (!/(?=.*\d)/.test(trimmed)) {
            errors.push('un número');
        }
        if (!/(?=.*[^A-Za-z0-9])/.test(trimmed)) {
            errors.push('un carácter especial');
        }

        // Verificar contraseñas comunes
        const commonPasswords = ['password', '123456', '123456789', 'qwerty', 'abc123', 'password123'];
        if (commonPasswords.includes(trimmed.toLowerCase())) {
            errors.push('contraseña más segura');
        }

        if (errors.length > 0) {
            return `La contraseña debe contener ${errors.join(', ')}`;
        }

        return true;
    }

    validatePhone(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'El teléfono es obligatorio';

        // Remover todos los caracteres no numéricos excepto + y espacios
        const cleanPhone = trimmed.replace(/[^\d+\s]/g, '');

        // Validar formato internacional
        const phoneRegex = /^\+?[\d\s\-\(\)]{10,}$/;
        if (!phoneRegex.test(cleanPhone)) {
            return 'Ingresa un número de teléfono válido';
        }

        // Validar longitud
        const digitsOnly = cleanPhone.replace(/\D/g, '');
        if (digitsOnly.length < 10 || digitsOnly.length > 15) {
            return 'El número de teléfono debe tener entre 10 y 15 dígitos';
        }

        return true;
    }

    validateNumber(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'Este campo es obligatorio';

        const num = parseFloat(trimmed);
        if (isNaN(num)) {
            return 'Ingresa un número válido';
        }

        return true;
    }

    validateDate(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'La fecha es obligatoria';

        const date = new Date(trimmed);
        if (isNaN(date.getTime())) {
            return 'Ingresa una fecha válida';
        }

        // Validar que no sea una fecha futura (para fechas de nacimiento)
        const today = new Date();
        if (date > today && this.field.element.hasAttribute('data-past-only')) {
            return 'La fecha no puede ser futura';
        }

        return true;
    }

    validateTime(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'La hora es obligatoria';

        const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/;
        if (!timeRegex.test(trimmed)) {
            return 'Ingresa una hora válida (HH:MM o HH:MM:SS)';
        }

        return true;
    }

    validateURL(value) {
        const trimmed = value.trim();
        if (!trimmed) return true; // URL es opcional

        try {
            new URL(trimmed);
            return true;
        } catch {
            return 'Ingresa una URL válida';
        }
    }

    validatePostalCode(value) {
        const trimmed = value.trim();
        if (!trimmed) return 'El código postal es obligatorio';

        // Validar código postal español
        const postalRegex = /^(0[1-9]|[1-4][0-9]|5[0-2])[0-9]{3}$/;
        if (!postalRegex.test(trimmed)) {
            return 'Ingresa un código postal válido (5 dígitos)';
        }

        return true;
    }

    validateDNI(value) {
        const trimmed = value.trim().toUpperCase();
        if (!trimmed) return 'El DNI es obligatorio';

        const dniRegex = /^[0-9]{8}[A-Z]$/;
        if (!dniRegex.test(trimmed)) {
            return 'Ingresa un DNI válido (8 números + 1 letra)';
        }

        // Validar letra del DNI
        const letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        const number = parseInt(trimmed.substring(0, 8));
        const expectedLetter = letters[number % 23];

        if (trimmed[8] !== expectedLetter) {
            return 'La letra del DNI no es correcta';
        }

        return true;
    }

    validateCreditCard(value) {
        const trimmed = value.trim().replace(/\s/g, '');
        if (!trimmed) return true; // Tarjeta opcional

        // Algoritmo de Luhn
        let sum = 0;
        let isEven = false;

        for (let i = trimmed.length - 1; i >= 0; i--) {
            let digit = parseInt(trimmed[i]);

            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }

            sum += digit;
            isEven = !isEven;
        }

        return sum % 10 === 0 || 'Número de tarjeta inválido';
    }

    validateMinLength(value, minLength) {
        return value.length >= minLength || `Mínimo ${minLength} caracteres`;
    }

    validateMaxLength(value, maxLength) {
        return value.length <= maxLength || `Máximo ${maxLength} caracteres`;
    }

    validatePattern(value, pattern) {
        return pattern.test(value) || 'Formato inválido';
    }

    // Métodos públicos
    isValid() {
        return Object.keys(this.errors).length === 0;
    }

    getErrors() {
        return this.errors;
    }

    getValues() {
        const values = {};
        Object.keys(this.fields).forEach(fieldName => {
            values[fieldName] = this.fields[fieldName].element.value.trim();
        });
        return values;
    }
}

// Sistema avanzado de sanitización y validación de entrada
const Sanitizer = {
    // Sanitizar HTML básico
    sanitizeHTML(str) {
        if (!str) return '';

        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    },

    // Sanitizar entrada general
    sanitizeInput(str, options = {}) {
        if (!str) return '';

        let sanitized = str;

        // Remover caracteres peligrosos
        if (!options.allowHtml) {
            sanitized = sanitized.replace(/[<>]/g, '');
        }

        // Remover caracteres de control
        sanitized = sanitized.replace(/[\x00-\x1F\x7F-\x9F]/g, '');

        // Normalizar espacios
        if (!options.preserveSpaces) {
            sanitized = sanitized.replace(/\s+/g, ' ').trim();
        }

        // Limitar longitud
        if (options.maxLength && sanitized.length > options.maxLength) {
            sanitized = sanitized.substring(0, options.maxLength);
        }

        return sanitized;
    },

    // Sanitizar para uso en SQL (escapar caracteres especiales)
    sanitizeSQL(str) {
        if (!str) return '';

        return str.replace(/['"\\]/g, '\\$&');
    },

    // Sanitizar para uso en URLs
    sanitizeURL(url) {
        if (!url) return '';

        try {
            const parsed = new URL(url);
            return parsed.toString();
        } catch {
            return '';
        }
    },

    // Sanitizar email
    sanitizeEmail(email) {
        if (!email) return '';

        const sanitized = email.toLowerCase().trim();

        // Validar formato básico
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(sanitized)) {
            return '';
        }

        return sanitized;
    },

    // Sanitizar teléfono
    sanitizePhone(phone) {
        if (!phone) return '';

        // Mantener solo números, espacios, paréntesis, guiones y +
        return phone.replace(/[^\d\s\-\(\)\+]/g, '').trim();
    },

    // Sanitizar número
    sanitizeNumber(value, options = {}) {
        if (!value) return '';

        const num = parseFloat(value.toString().replace(/[^\d.-]/g, ''));

        if (isNaN(num)) return '';

        if (options.min !== undefined && num < options.min) {
            return options.min.toString();
        }

        if (options.max !== undefined && num > options.max) {
            return options.max.toString();
        }

        if (options.decimals !== undefined) {
            return num.toFixed(options.decimals);
        }

        return num.toString();
    },

    // Sanitizar fecha
    sanitizeDate(dateStr) {
        if (!dateStr) return '';

        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';

        return date.toISOString().split('T')[0];
    },

    // Sanitizar texto para búsqueda
    sanitizeSearchTerm(term) {
        if (!term) return '';

        return term
            .trim()
            .toLowerCase()
            .replace(/[^\w\sáéíóúüñ]/g, '') // Mantener caracteres alfanuméricos y espacios
            .replace(/\s+/g, ' ')
            .trim();
    },

    // Sanitizar nombre de archivo
    sanitizeFilename(filename) {
        if (!filename) return '';

        return filename
            .replace(/[^a-zA-Z0-9._-]/g, '_') // Reemplazar caracteres no válidos con _
            .replace(/_{2,}/g, '_') // Reemplazar múltiples _ con uno solo
            .replace(/^_+|_+$/g, '') // Remover _ del inicio y fin
            .toLowerCase();
    },

    // Sanitizar JSON
    sanitizeJSON(jsonStr) {
        if (!jsonStr) return '';

        try {
            const parsed = JSON.parse(jsonStr);
            return JSON.stringify(parsed);
        } catch {
            return '';
        }
    },

    // Escapar expresiones regulares
    escapeRegExp(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    },

    // Sanitizar para atributos HTML
    sanitizeAttribute(value) {
        if (!value) return '';

        return value
            .replace(/[<>"'&]/g, '') // Remover caracteres peligrosos
            .trim();
    },

    // Sanitizar CSS
    sanitizeCSS(css) {
        if (!css) return '';

        // Remover propiedades CSS peligrosas
        const dangerousProps = ['javascript', 'vbscript', 'expression', 'behavior', 'import'];
        let sanitized = css;

        dangerousProps.forEach(prop => {
            const regex = new RegExp(`${prop}\\s*:.*?;`, 'gi');
            sanitized = sanitized.replace(regex, '');
        });

        return sanitized;
    },

    // Sanitizar para prevenir XSS
    sanitizeXSS(input) {
        if (!input) return '';

        return input
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#x27;')
            .replace(/\//g, '&#x2F;');
    },

    // Validar y sanitizar entrada completa
    sanitizeAll(input, type = 'text', options = {}) {
        if (!input) return '';

        let sanitized = input.toString();

        // Aplicar sanitización XSS primero
        sanitized = this.sanitizeXSS(sanitized);

        // Aplicar sanitización específica por tipo
        switch (type) {
            case 'email':
                sanitized = this.sanitizeEmail(sanitized);
                break;
            case 'phone':
                sanitized = this.sanitizePhone(sanitized);
                break;
            case 'number':
                sanitized = this.sanitizeNumber(sanitized, options);
                break;
            case 'date':
                sanitized = this.sanitizeDate(sanitized);
                break;
            case 'url':
                sanitized = this.sanitizeURL(sanitized);
                break;
            case 'search':
                sanitized = this.sanitizeSearchTerm(sanitized);
                break;
            case 'filename':
                sanitized = this.sanitizeFilename(sanitized);
                break;
            case 'json':
                sanitized = this.sanitizeJSON(sanitized);
                break;
            case 'html':
                sanitized = this.sanitizeHTML(sanitized);
                break;
            case 'sql':
                sanitized = this.sanitizeSQL(sanitized);
                break;
            default:
                sanitized = this.sanitizeInput(sanitized, options);
        }

        return sanitized;
    }
};

// Función para inicializar validadores en todos los formularios
function initializeFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        new FormValidator(form);
    });
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Trigger animation
    setTimeout(() => notification.classList.add('show'), 100);

    // Remove after duration
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Función para sanitizar todos los inputs de un formulario
function sanitizeForm(form) {
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        if (input.type !== 'password' && input.type !== 'submit') {
            input.value = Sanitizer.sanitizeInput(input.value);
        }
    });
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFormValidation);
} else {
    initializeFormValidation();
}