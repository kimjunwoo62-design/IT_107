document.addEventListener('DOMContentLoaded', function () {

    // --- Validation Helper Functions ---
    function displayError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        // Remove existing error
        let errorEl = document.getElementById(fieldId + '-error');
        if (errorEl) {
            errorEl.remove();
        }

        // Add new error
        if (message) {
            errorEl = document.createElement('div');
            errorEl.id = fieldId + '-error';
            errorEl.className = 'error-text';
            errorEl.textContent = message;
            field.parentNode.appendChild(errorEl);
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }
    }

    function validateName(fieldId, fieldName) {
        const field = document.getElementById(fieldId);
        const value = field.value.trim();
        let message = null;

        if (!value && field.required) {
            message = `${fieldName} is a required field.`;
        } else if (value) {
            if (/\d/.test(value)) {
                message = `${fieldName} cannot contain numbers.`;
            } else if (/[^a-zA-Z\s'-]/.test(value)) {
                message = `${fieldName} contains invalid special characters.`;
            } else if (/\s\s/.test(value)) {
                message = `Please remove double spaces from ${fieldName}.`;
            } else if (value === value.toUpperCase() && value.length > 1) {
                message = `${fieldName} cannot be in all capital letters.`;
            } else if (/(.)\1\1/i.test(value)) {
                message = `Three consecutive identical letters are not allowed in ${fieldName}.`;
            } else if (!/^[A-Z][a-z]*(?:\s[A-Z][a-z]*)*$/.test(value)) {
                message = `Each part of ${fieldName} must start with a capital letter. Example: Juan Carlo`;
            }
        }
        displayError(fieldId, message);
        return !message;
    }

    // --- Age Calculation ---
    const birthEl = document.getElementById('birthdate');
    const ageEl = document.getElementById('age');

    function calculateAge(value) {
        if (!value) return '';
        try {
            const dob = new Date(value);
            if (isNaN(dob.getTime())) return ''; // Invalid date
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            return age >= 0 ? age : '';
        } catch (e) {
            return '';
        }
    }

    function updateAge() {
        if (!ageEl || !birthEl) return;
        const age = calculateAge(birthEl.value);
        ageEl.value = age;

        if (age !== '' && age < 18) {
            displayError('birthdate', 'You must be at least 18 years old.');
        } else {
            displayError('birthdate', null);
        }
    }

    // --- Real-time Validation & AJAX Checks ---
    function ajaxValidate(fieldId, type) {
        const field = document.getElementById(fieldId);
        const value = field.value.trim();

        if (value.length > 0) {
            const formData = new FormData();
            formData.append('type', type);
            formData.append('value', value);

            fetch('Php/ajax_validator.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    displayError(fieldId, `This ${type.replace('_', ' ')} is already taken.`);
                } else {
                    displayError(fieldId, null);
                }
            })
            .catch(error => {
                console.error('Validation error:', error);
            });
        }
    }

    document.getElementById('id_number').addEventListener('blur', () => ajaxValidate('id_number', 'id_number'));
    document.getElementById('regUsername').addEventListener('blur', () => ajaxValidate('regUsername', 'username'));
    document.getElementById('firstName').addEventListener('blur', () => validateName('firstName', 'First Name'));
    document.getElementById('lastName').addEventListener('blur', () => validateName('lastName', 'Last Name'));
    document.getElementById('middleInitial').addEventListener('blur', () => validateName('middleInitial', 'Middle Initial'));

    if (birthEl) {
        birthEl.addEventListener('change', updateAge);
        birthEl.addEventListener('blur', updateAge);
        updateAge(); // Initial calculation on page load
    }

    // --- Show Password ---
    const showPasswordEl = document.getElementById('showPassword');
    const passwordEl = document.getElementById('regPassword');
    const confirmPasswordEl = document.getElementById('confirmPassword');

    if (showPasswordEl && passwordEl && confirmPasswordEl) {
        showPasswordEl.addEventListener('change', function () {
            const type = this.checked ? 'text' : 'password';
            passwordEl.type = type;
            confirmPasswordEl.type = type;
        });
    }

    // --- Show Security Answers ---
    const showSecurityAnswersEl = document.getElementById('showSecurityAnswers');
    const securityAnswerEls = ['security_a1', 'security_a2', 'security_a3'].map(id => document.getElementById(id));

    if (showSecurityAnswersEl && securityAnswerEls.every(el => el)) {
        showSecurityAnswersEl.addEventListener('change', function () {
            const type = this.checked ? 'text' : 'password';
            securityAnswerEls.forEach(el => el.type = type);
        });
    }

    // --- Password Strength Meter ---
    const strengthStatusEl = document.getElementById('password-strength-status');

    if (passwordEl && strengthStatusEl) {
        passwordEl.addEventListener('keyup', function () {
            const val = passwordEl.value;
            let strength = 0;

            // Base strength on length
            if (val.length >= 8) strength += 1;
            if (val.length >= 12) strength += 1;

            // Check for character types
            if (val.match(/[a-z]/)) strength += 1; // Lowercase
            if (val.match(/[A-Z]/)) strength += 1; // Uppercase
            if (val.match(/[0-9]/)) strength += 1; // Numbers
            if (val.match(/[^a-zA-Z0-9]/)) strength += 1; // Special characters

            // Update UI
            strengthStatusEl.className = ''; // Reset classes
            if (val.length === 0) {
                // No password, do nothing
            } else if (strength < 3) {
                strengthStatusEl.classList.add('strength-weak');
            } else if (strength < 5) {
                strengthStatusEl.classList.add('strength-medium');
            } else {
                strengthStatusEl.classList.add('strength-strong');
            }
        });
    }

    // --- TODO: AJAX check for unique Username/ID on blur ---

    // --- Multi-Step Form Navigation ---
    let currentStep = 1;
    const totalSteps = 3;

    function initSteps() {
        showStep(1);
        updateStepIndicators();
    }

    function showStep(step) {
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.remove('active');
        });
        const stepContent = document.querySelector(`.step-content[data-step="${step}"]`);
        if (stepContent) {
            setTimeout(() => stepContent.classList.add('active'), 10);
        }
        currentStep = step;
        updateStepIndicators();

        const authContainer = document.querySelector('.auth-container');
        if (authContainer) {
            const isWideStep = step === 1;
            authContainer.style.maxWidth = isWideStep ? '95%' : '';
            authContainer.style.width = isWideStep ? '95%' : '';
        }
    }

    // Update step indicators
    function updateStepIndicators() {
        document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
            const stepNum = index + 1;
            indicator.classList.remove('active', 'completed');
            
            if (stepNum < currentStep) {
                indicator.classList.add('completed');
                // Mark previous lines as completed
                if (index > 0) {
                    const prevLine = indicator.previousElementSibling;
                    if (prevLine && prevLine.classList.contains('step-line')) {
                        prevLine.classList.add('completed');
                    }
                }
            } else if (stepNum === currentStep) {
                indicator.classList.add('active');
            }
            
            // Update line between current and previous step
            if (stepNum === currentStep && index > 0) {
                const prevLine = indicator.previousElementSibling;
                if (prevLine && prevLine.classList.contains('step-line')) {
                    prevLine.classList.add('completed');
                }
            }
        });
    }

    // Next step
    window.nextStep = function() {
        if (currentStep < totalSteps) {
            // Validate current step before proceeding
            if (validateStep(currentStep)) {
                showStep(currentStep + 1);
            }
        }
    };

    // Previous step
    window.prevStep = function() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    };

    // Validate current step
    function validateStep(step) {
        const stepContent = document.querySelector(`.step-content[data-step="${step}"]`);
        if (!stepContent) return true;

        const inputs = stepContent.querySelectorAll('input, select');
        let isStepValid = true;

        inputs.forEach(input => {
            // Skip readonly fields (like age which is auto-calculated)
            if (input.readOnly) {
                return;
            }
            
            const fieldId = input.id;
            const fieldName = input.labels[0]?.textContent.replace('*', '').trim() || fieldId;
            let isValid = true;

            if (input.required && !input.value.trim()) {
                displayError(fieldId, `${fieldName} is required.`);
                isValid = false;
            }

            if (isValid && input.value.trim()) {
                switch (fieldId) {
                    case 'firstName':
                    case 'lastName':
                    case 'middleInitial':
                        isValid = validateName(fieldId, fieldName);
                        break;
                    case 'id_number':
                        const idPattern = /^[0-9]{4}-[0-9]{4}$/;
                        if (!idPattern.test(input.value)) {
                            displayError(fieldId, 'ID Number must be in xxxx-xxxx format.');
                            isValid = false;
                        }
                        break;
                    case 'email':
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailPattern.test(input.value)) {
                            displayError(fieldId, 'Invalid email format.');
                            isValid = false;
                        }
                        break;
                    case 'regPassword':
                        if (input.value.length < 8) {
                             displayError(fieldId, 'Password must be at least 8 characters long.');
                             isValid = false;
                        } else {
                            // Check password strength
                            const hasLower = /[a-z]/.test(input.value);
                            const hasUpper = /[A-Z]/.test(input.value);
                            const hasNumber = /[0-9]/.test(input.value);
                            const hasSpecial = /[^a-zA-Z0-9]/.test(input.value);
                            
                            if (!hasLower || !hasUpper || !hasNumber || !hasSpecial) {
                                displayError(fieldId, 'Password must contain lowercase, uppercase, number, and special character.');
                                isValid = false;
                            }
                        }
                        break;
                    case 'confirmPassword':
                        const password = document.getElementById('regPassword');
                        if (password && input.value !== password.value) {
                            displayError(fieldId, 'Passwords do not match.');
                            isValid = false;
                        }
                        break;
                }
            }
            if (!isValid) {
                isStepValid = false;
            }
        });

        return isStepValid;
    }

    // Validate all fields in the form (comprehensive validation)
    function validateAllFields() {
        let allValid = true;
        
        // First, clear all previous errors
        document.querySelectorAll('.error-text').forEach(error => error.remove());
        document.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        
        // Validate all steps - temporarily make all steps visible for validation
        for (let step = 1; step <= totalSteps; step++) {
            const stepContent = document.querySelector(`.step-content[data-step="${step}"]`);
            if (stepContent) {
                // Temporarily make step visible for validation
                const wasActive = stepContent.classList.contains('active');
                if (!wasActive) {
                    stepContent.style.position = 'relative';
                    stepContent.style.opacity = '1';
                    stepContent.style.pointerEvents = 'all';
                    stepContent.style.display = 'block';
                }
                
                // Validate this step
                if (!validateStep(step)) {
                    allValid = false;
                }
                
                // Restore original state if it wasn't active
                if (!wasActive) {
                    stepContent.style.position = '';
                    stepContent.style.opacity = '';
                    stepContent.style.pointerEvents = '';
                    stepContent.style.display = '';
                }
            }
        }
        
        return allValid;
    }

    // Form submission handler
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            
            // Validate all fields before submitting
            const isValid = validateAllFields();
            console.log('Validation result:', isValid);
            
            if (!isValid) {
                console.log('Validation failed, preventing submission');
                e.preventDefault();
                e.stopPropagation();
                
                // Find first step with error and show it
                for (let step = 1; step <= totalSteps; step++) {
                    const stepContent = document.querySelector(`.step-content[data-step="${step}"]`);
                    if (stepContent) {
                        const errors = stepContent.querySelectorAll('.error-text');
                        if (errors.length > 0) {
                            console.log('Showing step with errors:', step);
                            showStep(step);
                            // Scroll to top of form
                            setTimeout(() => {
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }, 100);
                            break;
                        }
                    }
                }
                return false;
            }
            
            console.log('Validation passed, allowing form submission');
            
            // If all validations pass, allow form submission
            // Show loading state
            const submitBtn = registerForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Submitting...';
                
                // Re-enable after 10 seconds in case submission fails
                setTimeout(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }, 10000);
            }
            
            // Allow form to submit
            return true;
        });
    } else {
        console.error('Register form not found!');
    }

    // Initialize on page load
    initSteps();

});
