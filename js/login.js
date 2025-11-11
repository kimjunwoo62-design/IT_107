document.addEventListener('DOMContentLoaded', function () {

    // --- Show Password ---
    const showPasswordEl = document.getElementById('showPassword');
    const passwordEl = document.getElementById('password');

    if (showPasswordEl && passwordEl) {
        showPasswordEl.addEventListener('change', function () {
            passwordEl.type = this.checked ? 'text' : 'password';
        });
    }

    // --- AUTO DISABLE FORM IF LOCKED OUT ---
    const errorEl = document.getElementById('form-error');
    
    // IMPORTANT: Disable form immediately if locked out
    if (errorEl && errorEl.textContent.includes('Too many failed attempts')) {
        disableForm();
    }

    // --- Countdown Timer for Lockout ---
    if (errorEl && errorEl.dataset.lockoutSeconds) {
        let secondsLeft = parseInt(errorEl.dataset.lockoutSeconds, 10);

        // Make sure form is disabled when countdown starts
        disableForm();

        const updateTimer = () => {
            if (secondsLeft > 0) {
                errorEl.textContent = `Too many failed attempts. Please try again in ${secondsLeft} seconds.`;
                secondsLeft--;
            } else {
                errorEl.textContent = 'You can now try again. Please refresh the page.';
                clearInterval(timerInterval);
                // Re-enable the form
                enableForm();
            }
        };

        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // Initial call to display message immediately
    }

    // FUNCTION TO DISABLE FORM
    function disableForm() {
        const loginButton = document.getElementById('login-button');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const registerLink = document.getElementById('register-link');
        const showPasswordCheckbox = document.getElementById('showPassword');

        if (loginButton) loginButton.disabled = true;
        if (usernameInput) usernameInput.disabled = true;
        if (passwordInput) passwordInput.disabled = true;
        if (showPasswordCheckbox) showPasswordCheckbox.disabled = true;
        if (registerLink) registerLink.style.pointerEvents = 'none';
    }

    // FUNCTION TO ENABLE FORM
    function enableForm() {
        const loginButton = document.getElementById('login-button');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const registerLink = document.getElementById('register-link');
        const showPasswordCheckbox = document.getElementById('showPassword');

        if (loginButton) loginButton.disabled = false;
        if (usernameInput) usernameInput.disabled = false;
        if (passwordInput) passwordInput.disabled = false;
        if (showPasswordCheckbox) showPasswordCheckbox.disabled = false;
        if (registerLink) registerLink.style.pointerEvents = 'auto';
    }

});