<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Login Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];
    $input = ['username' => $username];

    if (empty($username)) { $errors['username'] = 'Username or ID Number is required.'; }
    if (empty($password)) { $errors['password'] = 'Password is required.'; }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username OR id_number = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Check if user is locked out
        if ($user && $user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            $remaining = strtotime($user['lockout_until']) - time();
            $_SESSION['lockout_seconds'] = $remaining;
            $_SESSION['locked_out'] = true;
            
        } elseif ($user && password_verify($password, $user['password'])) {
            // Successful login
            $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?');
            $stmt->execute([$user['id']]);
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            unset($_SESSION['locked_out'], $_SESSION['lockout_seconds'], $_SESSION['login_failures']);
            header('Location: dashboard.php');
            exit();
            
        } else {
            // Failed login
            if ($user) {
                $new_attempts = $user['failed_login_attempts'] + 1;
                $lockout_time = null;
                
                // Apply lockout based on consecutive attempts
                if ($new_attempts >= 9) { 
                    $lockout_time = date('Y-m-d H:i:s', time() + 60);
                } elseif ($new_attempts >= 6) { 
                    $lockout_time = date('Y-m-d H:i:s', time() + 30);
                } elseif ($new_attempts >= 3) { 
                    $lockout_time = date('Y-m-d H:i:s', time() + 15);
                }
                
                $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, lockout_until = ? WHERE id = ?');
                $stmt->execute([$new_attempts, $lockout_time, $user['id']]);
                
                if ($lockout_time) {
                    $remaining = strtotime($lockout_time) - time();
                    $_SESSION['lockout_seconds'] = $remaining;
                    $_SESSION['locked_out'] = true;
                    // Remember which user was locked out so the GET page can re-check DB and show timer
                    $_SESSION['last_attempt_username'] = $user['username'];
                }
                
                $_SESSION['login_failures'] = $new_attempts;
                // Show forgot-password transiently when threshold reached
                $_SESSION['show_forgot_password'] = ($new_attempts >= 2);
            } else {
                $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
                // keep last attempted username so display logic can attempt to resolve lockout state
                $_SESSION['last_attempt_username'] = $username;
                // Show forgot-password transiently when threshold reached
                $_SESSION['show_forgot_password'] = ($_SESSION['login_failures'] >= 2);
            }
        }
    }
    
    header('Location: login.php');
    exit();
}

// --- Display Logic ---
// Ensure we always have a numeric counter for consecutive login failures in session.
// This counter represents consecutive failed attempts in the current session and is
// reset on successful login (see successful login branch above). By default start at 0
// so the "Forgot Password" link will NOT be visible on the first visit.
if (!isset($_SESSION['login_failures']) || !is_int($_SESSION['login_failures'])) {
    $_SESSION['login_failures'] = 0;
}

$lockout_seconds = 0;
$is_locked_out = false;
$login_failures = (int) $_SESSION['login_failures'];
$show_forgot_password = !empty($_SESSION['show_forgot_password']);

// Clear the transient display flag so it does not persist across unrelated visits.
if (isset($_SESSION['show_forgot_password'])) {
    unset($_SESSION['show_forgot_password']);
}

// If we have a last attempted username, re-check DB for an active lockout so the GET page can
// reliably show the remaining timer even after the POST+redirect flow or when the session flag
// was cleared previously.
if (!empty($_SESSION['last_attempt_username'])) {
    try {
        $checkStmt = $pdo->prepare('SELECT lockout_until FROM users WHERE username = :u OR id_number = :u LIMIT 1');
        $checkStmt->execute(['u' => $_SESSION['last_attempt_username']]);
        $row = $checkStmt->fetch();
        if ($row && $row['lockout_until'] && strtotime($row['lockout_until']) > time()) {
            $remaining = strtotime($row['lockout_until']) - time();
            $_SESSION['lockout_seconds'] = $remaining;
            $_SESSION['locked_out'] = true;
            $is_locked_out = true;
            $lockout_seconds = $remaining;
        } else {
            // clear any expired lockout info
            unset($_SESSION['lockout_seconds']);
            unset($_SESSION['locked_out']);
            // keep last_attempt_username for a short while; optionally clear it here
            // unset($_SESSION['last_attempt_username']);
        }
    } catch (Exception $e) {
        // ignore DB errors for display purposes
    }
} else {
    $is_locked_out = isset($_SESSION['locked_out']) && $_SESSION['locked_out'];
    $lockout_seconds = $_SESSION['lockout_seconds'] ?? 0;
}

// Get success message from session (for registration success)
$success_message = $_SESSION['success_message'] ?? '';
if (!empty($success_message)) {
    unset($_SESSION['success_message']); // Clear after retrieving
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiquorLink - Login</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .error-text { color: #e74c3c; font-size: 0.9em; margin-top: 10px; display: block; text-align: center; font-weight: bold; }
        .success-text { color: #2ecc71; font-size: 0.9em; margin-top: 10px; margin-bottom: 15px; display: block; text-align: center; font-weight: bold; padding: 10px; background-color: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.3); border-radius: 4px; }
        input:disabled, button:disabled { background-color: #f8f9fa !important; cursor: not-allowed; opacity: 0.6; }
        .password-wrapper { position: relative; }
        .password-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; }
        .forgot-password-link { text-align: center; margin: 10px 0; display: none; }
        .forgot-password-link.show { display: block; }
        a[disabled] { pointer-events: none; opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container full-width">
        <!-- Prospect Name Above Header -->
        <div class="prospect-name">LiquorLink - Bar Management System</div>
        
        <header class="elegant-header">
            <div class="logo"><h1>LiquorLink</h1></div>
            <nav><ul></ul></nav>
            <div class="header-buttons">
                <a href="../index.html" class="btn text-btn">Home</a>
                <a href="register.php" class="btn text-btn">Register</a>
            </div>
        </header>
        <main>
            <section class="auth-section">
                <div class="auth-container">
                    <h2 class="auth-title">Member Login</h2>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="success-text" id="success-message" style="display: block;">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="error-text" id="form-error" data-lockout-seconds="<?php echo $lockout_seconds; ?>" style="<?php echo $is_locked_out ? '' : 'display:none;'; ?>">
                        <?php if ($is_locked_out): ?>
                            Too many failed attempts. Please try again in <span id="countdown"><?php echo $lockout_seconds; ?></span> seconds.
                        <?php endif; ?>
                    </div>
                    
                    <form id="login-form" class="elegant-form" action="login.php" method="post" novalidate>
                        <div class="form-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">Username or ID Number</label>
                                    <input type="text" id="username" name="username">
                                    <div class="error-text" id="username-error" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="password" name="password">
                                        <button type="button" class="password-toggle" id="togglePassword">👁️</button>
                                    </div>
                                    <div class="error-text" id="password-error" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="flex: 0 0 100%;">
                                    <input type="checkbox" id="showPassword" style="width: auto; margin-right: 5px;">
                                    <label for="showPassword" style="display: inline; font-weight: normal;">Show Password</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="forgot-password-link <?php echo $show_forgot_password ? 'show' : ''; ?>" id="forgot-password-link">
                            <a href="forgot_password.php" id="forgot-link" class="text-link">Forgot Password? Reset Here</a>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" id="login-button" class="btn primary-btn">Sign In</button>
                            <p class="form-note">
                                Don't have an account? <a href="register.php" id="register-link" class="text-link">Please register here</a>
                            </p>
                        </div>
                    </form>
                </div>
            </section>
        </main>
        <footer>
            <p>&copy; 2023 LiquorLink Bar Management System. All rights reserved.</p>
        </footer>
    </div>

    <script>
        // Disable browser back button
        history.pushState(null, null, location.href);
        window.onpopstate = function() { history.go(1); };

        document.addEventListener('DOMContentLoaded', function() {
            // --- Show/Hide Password (checkbox) ---
            const showPasswordEl = document.getElementById('showPassword');
            const passwordEl = document.getElementById('password');
            if (showPasswordEl && passwordEl) {
                showPasswordEl.addEventListener('change', function() {
                    passwordEl.type = this.checked ? 'text' : 'password';
                });
            }

            // --- Show/Hide Password (toggle button) ---
            const togglePassword = document.getElementById('togglePassword');
            if (togglePassword && passwordEl) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordEl.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordEl.setAttribute('type', type);
                    this.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
                    showPasswordEl.checked = type === 'text';
                });
            }

            // --- Lockout Timer Logic ---
            const errorEl = document.getElementById('form-error');
            const countdownEl = document.getElementById('countdown');
            const loginButton = document.getElementById('login-button');
            const usernameInput = document.getElementById('username');
            const registerLink = document.getElementById('register-link');
            const forgotLink = document.getElementById('forgot-link');

            if (errorEl && errorEl.dataset.lockoutSeconds && parseInt(errorEl.dataset.lockoutSeconds) > 0) {
                let secondsLeft = parseInt(errorEl.dataset.lockoutSeconds);
                
                // Disable form immediately
                disableForm();
                errorEl.style.display = 'block';

                const timerInterval = setInterval(function() {
                    secondsLeft--;
                    if (countdownEl) countdownEl.textContent = secondsLeft;
                    
                    if (secondsLeft <= 0) {
                        clearInterval(timerInterval);
                        location.reload();
                    }
                }, 1000);
            }

            function disableForm() {
                if (loginButton) loginButton.disabled = true;
                if (usernameInput) usernameInput.disabled = true;
                if (passwordEl) passwordEl.disabled = true;
                if (showPasswordEl) showPasswordEl.disabled = true;
                if (togglePassword) togglePassword.disabled = true;
                if (registerLink) registerLink.setAttribute('disabled', 'true');
                if (forgotLink) forgotLink.setAttribute('disabled', 'true');
            }

            // --- Custom Form Validation ---
            const loginForm = document.getElementById('login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Clear previous errors
                    const usernameError = document.getElementById('username-error');
                    const passwordError = document.getElementById('password-error');
                    
                    if (usernameError) {
                        usernameError.style.display = 'none';
                        usernameError.textContent = '';
                    }
                    if (passwordError) {
                        passwordError.style.display = 'none';
                        passwordError.textContent = '';
                    }
                    
                    // Remove error styling
                    if (usernameInput) {
                        usernameInput.style.borderColor = '';
                    }
                    if (passwordEl) {
                        passwordEl.style.borderColor = '';
                    }
                    
                    // Validate username
                    if (!usernameInput || !usernameInput.value.trim()) {
                        isValid = false;
                        if (usernameError) {
                            usernameError.textContent = 'Username or ID Number is required.';
                            usernameError.style.display = 'block';
                        }
                        if (usernameInput) {
                            usernameInput.style.borderColor = '#e74c3c';
                        }
                    }
                    
                    // Validate password
                    if (!passwordEl || !passwordEl.value.trim()) {
                        isValid = false;
                        if (passwordError) {
                            passwordError.textContent = 'Password is required.';
                            passwordError.style.display = 'block';
                        }
                        if (passwordEl) {
                            passwordEl.style.borderColor = '#e74c3c';
                        }
                    }
                    
                    // Prevent form submission if validation fails
                    if (!isValid) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>

