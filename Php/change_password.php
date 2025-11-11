<?php
session_start();
require_once __DIR__ . '/db.php';

// Authorization Check
if (!isset($_SESSION['reset_authorized_for_user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['reset_authorized_for_user_id'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($password)) {
        $errors['password'] = 'New password cannot be empty.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Hash the new password and update the database
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');

        try {
            $stmt->execute([$passwordHash, $user_id]);
            // Clean up session and set success message for login page
            unset($_SESSION['reset_authorized_for_user_id']);
            $_SESSION['success_message'] = 'Successfully Change Password';
            header('Location: login.php');
            exit();
        } catch (PDOException $e) {
            $errors['form'] = 'A database error occurred.';
        }
    }
    
    if (!empty($errors['confirm_password'])) {
        $errors['form'] = 'Mismatch Password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiquorLink - Change Password</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .error-text { color: #e74c3c; font-size: 0.9em; margin-top: 4px; display: block; }
        #password-strength-status { margin-top: 5px; height: 10px; }
        .strength-weak { background-color: #e74c3c; }
        .strength-medium { background-color: #f39c12; }
        .strength-strong { background-color: #2ecc71; }
    </style>
</head>
<body>
    <div class="container full-width">
        <!-- Prospect Name Above Header -->
        <div class="prospect-name">LiquorLink - Bar Management System</div>
        
        <header class="elegant-header">
            <div class="logo"><h1>LiquorLink</h1></div>
            <nav><ul><li><a href="../index.html">HOME</a></li></ul></nav>
            <div class="header-buttons">
                <a href="../index.html" class="btn text-btn">Home</a>
            </div>
        </header>

        <main>
            <section class="auth-section">
                <div class="auth-container">
                    <h2 class="auth-title">Change Password</h2>
                    <?php if(isset($errors['form'])) { echo '<div class="error-text" style="text-align: center; margin-bottom: 1rem; font-weight: bold;">' . htmlspecialchars($errors['form']) . '</div>'; } ?>
                    <form class="elegant-form" action="" method="post">
                        <div class="form-section">
                            <div class="form-group">
                                <label for="new_password">Enter Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                                <div id="password-strength-status"></div>
                                <?php if(isset($errors['password'])) { echo '<div class="error-text">' . htmlspecialchars($errors['password']) . '</div>'; } ?>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Re-enter Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <?php if(isset($errors['confirm_password'])) { echo '<div class="error-text">' . htmlspecialchars($errors['confirm_password']) . '</div>'; } ?>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary-btn">Set New Password</button>
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
        // Basic password strength meter, can be moved to a shared JS file later
        const passwordEl = document.getElementById('new_password');
        const strengthStatusEl = document.getElementById('password-strength-status');

        if (passwordEl && strengthStatusEl) {
            passwordEl.addEventListener('keyup', function () {
                const val = passwordEl.value;
                let strength = 0;
                if (val.length >= 8) strength++;
                if (val.match(/[a-z]/) && val.match(/[A-Z]/)) strength++;
                if (val.match(/[0-9]/)) strength++;
                if (val.match(/[^a-zA-Z0-9]/)) strength++;

                strengthStatusEl.className = '';
                if (val.length > 0 && strength <= 2) {
                    strengthStatusEl.classList.add('strength-weak');
                } else if (strength === 3) {
                    strengthStatusEl.classList.add('strength-medium');
                } else if (strength >= 4) {
                    strengthStatusEl.classList.add('strength-strong');
                }
            });
        }
    </script>
</body>
</html>
