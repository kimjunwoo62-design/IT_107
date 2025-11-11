<?php
session_start();
require_once __DIR__ . '/db.php';

// --- Validation Helper Functions ---
function validateName($name, $field_name, $isRequired = true) {
    if (empty($name)) { 
        return $isRequired ? "$field_name is required." : null; 
    }
    
    // Check for special characters
    if (!preg_match('/^[a-zA-Z\s.-]*$/', $name)) { 
        return "$field_name contains invalid characters (special characters not allowed)."; 
    }
    
    // Check for numbers followed by letters (not allowed)
    if (preg_match('/\d+[a-zA-Z]/', $name) || preg_match('/[a-zA-Z]\d+/', $name)) {
        return "$field_name cannot contain numbers followed by letters or letters followed by numbers.";
    }
    
    // Check for double spaces
    if (strpos($name, '  ') !== false) { 
        return "$field_name contains double spaces."; 
    }
    
    // Check if all capital letters (more than 1 character)
    if (strtoupper($name) === $name && strlen($name) > 1) { 
        return "$field_name cannot be all capital letters."; 
    }
    
    // Check for 3 consecutive identical letters (case insensitive)
    if (preg_match('/(.)\\1\\1/i', $name)) { 
        return "$field_name contains 3 consecutive identical letters."; 
    }
    
    // Check if the name follows the "Capital then small letters" format.
    if (!preg_match('/^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/', $name)) {
        return "$field_name must start with a capital letter and be followed by lowercase letters.";
    }
    
    return null;
}

function validateExtensionName($extension) {
    if (empty($extension)) { return null; }
    
    // Allow Roman numerals and standard extensions
    $allowed = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'jr', 'sr', 'jr.', 'sr.'];
    $lower = strtolower($extension);
    
    // If it's in the allowed list, it's valid but don't register in database (per requirement)
    if (in_array($lower, $allowed)) {
        return null;
    }
    
    // For other extensions, apply basic validation
    if (!preg_match('/^[a-zA-Z0-9.\s]*$/', $extension)) {
        return "Extension name contains invalid characters.";
    }
    
    return null;
}

function validateAddress($address, $field_name, $isRequired = true)
{
    if (empty($address)) {
        return $isRequired ? "$field_name is required." : null;
    }

    if (!preg_match('/^[a-zA-Z0-9\s.,-]*$/', $address)) {
        return "$field_name contains invalid characters.";
    }

    return null;
}

function validatePasswordStrength($password)
{
    $errors = [];
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "at least one lowercase letter";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "at least one uppercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "at least one number";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "at least one special character";
    }

    if (empty($errors)) {
        return null;
    }

    return "Password must contain " . implode(', ', $errors) . ".";
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $input = [];
    $fields = [
        'id_number', 'firstName', 'middleInitial', 'lastName', 'extensionName', 'birthdate', 'sex',
        'purok', 'barangay', 'cityMunicipality', 'province', 'country', 'zipCode',
        'email', 'regUsername', 'regPassword', 'confirmPassword',
        'security_q1', 'security_a1', 'security_q2', 'security_a2', 'security_q3', 'security_a3'
    ];
    foreach ($fields as $field) { $input[$field] = trim($_POST[$field] ?? ''); }

    // --- Validation ---
    
    // ID Number validation
    if (empty($input['id_number'])) { 
        $errors['id_number'] = 'ID Number is required.'; 
    } elseif (!preg_match('/^[0-9]{4}-[0-9]{4}$/', $input['id_number'])) { 
        $errors['id_number'] = 'ID Number must be in the format xxxx-xxxx.'; 
    } else {
        // Check if ID number already exists (Primary Key check)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id_number = ?');
        $stmt->execute([$input['id_number']]);
        if ($stmt->fetch()) { 
            $errors['id_number'] = 'ID Number already exists in the database.'; 
        }
    }
    
    // Name validations
    $errors['firstName'] = validateName($input['firstName'], 'First Name', true);
    $errors['lastName'] = validateName($input['lastName'], 'Last Name', true);
    if (!empty($input['middleInitial'])) { 
        $errors['middleInitial'] = validateName($input['middleInitial'], 'Middle Initial', false); 
    }
    
    // Extension Name validation
    if (!empty($input['extensionName'])) { 
        $errors['extensionName'] = validateExtensionName($input['extensionName']); 
    }
    
    // Birthdate and Age validation
    $age = 0;
    if (empty($input['birthdate'])) { 
        $errors['birthdate'] = 'Birthdate is required.'; 
    } else {
        try {
            $d = new DateTime($input['birthdate']);
            $today = new DateTime();
            $age = $d->diff($today)->y;
            if ($age < 18) { 
                $errors['birthdate'] = 'You must be at least 18 years old (legal age only).'; 
            }
        } catch (Exception $e) { 
            $errors['birthdate'] = 'Invalid birthdate format.'; 
        }
    }
    
    if (empty($input['sex'])) {
        $errors['sex'] = 'Sex is required.';
    }
    
    // Address validations
    $errors['purok'] = validateAddress($input['purok'], 'Purok/Street', false);
    $errors['barangay'] = validateAddress($input['barangay'], 'Barangay');
    $errors['cityMunicipality'] = validateAddress($input['cityMunicipality'], 'City/Municipality');
    $errors['province'] = validateAddress($input['province'], 'Province');
    $errors['country'] = validateAddress($input['country'], 'Country');

    if (empty($input['zipCode'])) {
        $errors['zipCode'] = 'Zip Code is required.';
    } elseif (!preg_match('/^[0-9]{4,10}$/', $input['zipCode'])) {
        $errors['zipCode'] = 'Zip Code must contain only numbers (4-10 digits).';
    }
    
    // Email validation
    if (empty($input['email'])) { 
        $errors['email'] = 'Email is required.'; 
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) { 
        $errors['email'] = 'Invalid email format.'; 
    }
    
    // Username validation
    if (empty($input['regUsername'])) { 
        $errors['regUsername'] = 'Username is required.'; 
    } elseif (!preg_match('/^[a-zA-Z0-9_]*$/', $input['regUsername'])) {
        $errors['regUsername'] = 'Username can only contain letters, numbers, and underscores.';
    }
    
    // Password validation
    if (empty($input['regPassword'])) {
        $errors['regPassword'] = 'Password is required.';
    } elseif (strlen($input['regPassword']) < 8) {
        $errors['regPassword'] = 'Password must be at least 8 characters long.';
    } else {
        $errors['regPassword'] = validatePasswordStrength($input['regPassword']);
    }
    
    if ($input['regPassword'] !== $input['confirmPassword']) { 
        $errors['confirmPassword'] = 'Passwords do not match.'; 
    }
    
    // Security Questions validation
    if (empty($input['security_q1']) || empty($input['security_a1'])) { 
        $errors['security_q1'] = 'Security Question 1 and Answer are required.'; 
    }
    if (empty($input['security_q2']) || empty($input['security_a2'])) {
        $errors['security_q2'] = 'Security Question 2 and Answer are required.';
    }
    if (empty($input['security_q3']) || empty($input['security_a3'])) {
        $errors['security_q3'] = 'Security Question 3 and Answer are required.';
    }
    
    $questions = [$input['security_q1'], $input['security_q2'], $input['security_q3']];
    if (count($questions) !== count(array_unique(array_filter($questions)))) { 
        $errors['security_q1'] = 'Each security question must be unique.'; 
    }
    
    // Remove null values from errors
    $errors = array_filter($errors);
    
    // Check for existing username and email (only if no other errors)
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$input['regUsername'], $input['email']]);
        $existing = $stmt->fetch();
        if ($existing) {
            $stmt2 = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
            $stmt2->execute([$existing['id']]);
            $userData = $stmt2->fetch();
            if ($userData['username'] === $input['regUsername']) {
                $errors['regUsername'] = 'Username already exists in the database.';
            }
            if ($userData['email'] === $input['email']) {
                $errors['email'] = 'Email already exists in the database.';
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['input'] = $input;
        header('Location: register.php');
        exit();
    } else {
        $passwordHash = password_hash($input['regPassword'], PASSWORD_DEFAULT);
        $sa1Hash = password_hash($input['security_a1'], PASSWORD_DEFAULT);
        $sa2Hash = password_hash($input['security_a2'], PASSWORD_DEFAULT);
        $sa3Hash = password_hash($input['security_a3'], PASSWORD_DEFAULT);
        $addressParts = [$input['purok'], $input['barangay'], $input['cityMunicipality'], $input['province'], $input['country'], $input['zipCode']];
        $address = implode(', ', array_filter($addressParts));
        $sql = "INSERT INTO users (id_number, first_name, middle_name, last_name, name_extension, username, password, birthdate, age, address, sex, email, security_q1, security_a1_hash, security_q2, security_a2_hash, security_q3, security_a3_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input['id_number'], $input['firstName'], empty($input['middleInitial']) ? null : $input['middleInitial'],
                $input['lastName'], empty($input['extensionName']) ? null : $input['extensionName'], $input['regUsername'],
                $passwordHash, $input['birthdate'], $age, $address, $input['sex'], $input['email'],
                $input['security_q1'], $sa1Hash, $input['security_q2'], $sa2Hash, $input['security_q3'], $sa3Hash
            ]);
            $_SESSION['success_message'] = "Registration successful! You can now log in.";
            header('Location: login.php');
            exit();
        } catch (PDOException $e) {
            // Show actual error for debugging (remove in production)
            $errorMessage = 'A database error occurred.';
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'id_number') !== false) {
                    $errorMessage = 'ID Number already exists.';
                    $_SESSION['errors'] = ['id_number' => $errorMessage];
                } elseif (strpos($e->getMessage(), 'username') !== false) {
                    $errorMessage = 'Username already exists.';
                    $_SESSION['errors'] = ['regUsername' => $errorMessage];
                } elseif (strpos($e->getMessage(), 'email') !== false) {
                    $errorMessage = 'Email already exists.';
                    $_SESSION['errors'] = ['email' => $errorMessage];
                } else {
                    $_SESSION['errors'] = ['form' => $errorMessage];
                }
            } else {
                $_SESSION['errors'] = ['form' => $errorMessage . ' Details: ' . $e->getMessage()];
            }
            $_SESSION['input'] = $input;
            header('Location: register.php');
            exit();
        }
    }
}

// --- Display Logic ---
$errors = $_SESSION['errors'] ?? [];
$input = $_SESSION['input'] ?? [];
unset($_SESSION['errors'], $_SESSION['input']);
function display_error($field, $errors) { if (isset($errors[$field])) { echo '<div class="error-text">' . htmlspecialchars($errors[$field]) . '</div>'; } }
function old_input($field, $input) { return htmlspecialchars($input[$field] ?? ''); }
function old_select($field, $value, $input) { if (isset($input[$field]) && $input[$field] === $value) { return 'selected'; } return ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiquorLink - Registration</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .required-ast { color: red; } .optional-text { color: red; font-weight: normal; font-size: 0.9em; }
        .error-text { color: #e74c3c; font-size: 0.9em; margin-top: 4px; display: block; }
        #password-strength-status { margin-top: 5px; height: 10px; width: 100%; }
        .strength-weak { background-color: #e74c3c; } .strength-medium { background-color: #f39c12; } .strength-strong { background-color: #2ecc71; }
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
                <a href="login.php" class="btn text-btn">Log-in</a>
            </div>
        </header>
        <main>
            <section class="auth-section multi-step-section">
                <div class="auth-container multi-step-container">
                    <h2 class="auth-title">Member Registration</h2>
                    <?php display_error('form', $errors); ?>
                    
                    <!-- Step Indicators -->
                    <div class="step-indicators">
                        <div class="step-indicator active" data-step="1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Profile</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-indicator" data-step="2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Account</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-indicator" data-step="3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Security</div>
                        </div>
                    </div>

                    <form id="register-form" class="elegant-form multi-step-form" action="register.php" method="post" novalidate>

                        <!-- Step 1: Personal and Address Information -->
                        <div class="step-content active" data-step="1">
                            <div class="step-1-layout">
                                <div class="form-section">
                                    <h3 class="step-title">Personal Information</h3>
                                    <div class="step-form-grid">
                                        <div class="form-group"><label for="id_number">ID Number <span class="required-ast">*</span></label><input type="text" id="id_number" name="id_number" placeholder="xxxx-xxxx" required maxlength="9" value="<?php echo old_input('id_number', $input); ?>"><?php display_error('id_number', $errors); ?></div>
                                        <div class="form-group"><label for="firstName">First Name <span class="required-ast">*</span></label><input type="text" id="firstName" name="firstName" required maxlength="50" value="<?php echo old_input('firstName', $input); ?>"><?php display_error('firstName', $errors); ?></div>
                                        <div class="form-group"><label for="middleInitial">Middle Initial <span class="optional-text">(optional)</span></label><input type="text" id="middleInitial" name="middleInitial" maxlength="50" value="<?php echo old_input('middleInitial', $input); ?>"><?php display_error('middleInitial', $errors); ?></div>
                                        <div class="form-group"><label for="lastName">Last Name <span class="required-ast">*</span></label><input type="text" id="lastName" name="lastName" required maxlength="50" value="<?php echo old_input('lastName', $input); ?>"><?php display_error('lastName', $errors); ?></div>
                                        <div class="form-group"><label for="extensionName">Extension Name <span class="optional-text">(optional)</span></label><input type="text" id="extensionName" name="extensionName" placeholder="Jr., Sr., III, etc." maxlength="10" value="<?php echo old_input('extensionName', $input); ?>"><?php display_error('extensionName', $errors); ?></div>
                                        <div class="form-group"><label for="birthdate">Birthdate <span class="required-ast">*</span></label><input type="date" id="birthdate" name="birthdate" required value="<?php echo old_input('birthdate', $input); ?>"><?php display_error('birthdate', $errors); ?></div>
                                        <div class="form-group"><label for="age">Age</label><input type="number" id="age" name="age" readonly placeholder="Auto" value="<?php echo old_input('age', $input); ?>"></div>
                                        <div class="form-group"><label for="sex">Sex <span class="required-ast">*</span></label><select id="sex" name="sex" required><option value="">Select</option><option value="male" <?php echo old_select('sex', 'male', $input); ?>>Male</option><option value="female" <?php echo old_select('sex', 'female', $input); ?>>Female</option><option value="other" <?php echo old_select('sex', 'other', $input); ?>>Other</option></select><?php display_error('sex', $errors); ?></div>
                                    </div>
                                </div>
                                <div class="form-section">
                                    <h3 class="step-title">Address Information</h3>
                                    <div class="step-form-grid">
                                        <div class="form-group"><label for="purok">Purok/Street <span class="optional-text">(optional)</span></label><input type="text" id="purok" name="purok" maxlength="100" value="<?php echo old_input('purok', $input); ?>"></div>
                                        <div class="form-group"><label for="barangay">Barangay <span class="required-ast">*</span></label><input type="text" id="barangay" name="barangay" required maxlength="50" value="<?php echo old_input('barangay', $input); ?>"><?php display_error('barangay', $errors); ?></div>
                                        <div class="form-group"><label for="cityMunicipality">City/Municipality <span class="required-ast">*</span></label><input type="text" id="cityMunicipality" name="cityMunicipality" required maxlength="50" value="<?php echo old_input('cityMunicipality', $input); ?>"><?php display_error('cityMunicipality', $errors); ?></div>
                                        <div class="form-group"><label for="province">Province <span class="required-ast">*</span></label><input type="text" id="province" name="province" required maxlength="50" value="<?php echo old_input('province', $input); ?>"><?php display_error('province', $errors); ?></div>
                                        <div class="form-group"><label for="country">Country <span class="required-ast">*</span></label><input type="text" id="country" name="country" value="<?php echo old_input('country', $input) ?: 'Philippines'; ?>" required maxlength="50"><?php display_error('country', $errors); ?></div>
                                        <div class="form-group"><label for="zipCode">Zip Code <span class="required-ast">*</span></label><input type="text" id="zipCode" name="zipCode" required maxlength="10" value="<?php echo old_input('zipCode', $input); ?>"><?php display_error('zipCode', $errors); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn next-btn" onclick="nextStep()">Next</button>
                            </div>
                        </div>

                        <!-- Step 2: Account Information -->
                        <div class="step-content" data-step="2">
                            <h3 class="step-title">Account Information</h3>
                            <div class="step-form-grid step2-grid">
                                <div class="form-group"><label for="email">Email Address <span class="required-ast">*</span></label><input type="email" id="email" name="email" required maxlength="100" value="<?php echo old_input('email', $input); ?>"><?php display_error('email', $errors); ?></div>
                                <div class="form-group"><label for="regUsername">Username <span class="required-ast">*</span></label><input type="text" id="regUsername" name="regUsername" required maxlength="50" value="<?php echo old_input('regUsername', $input); ?>"><?php display_error('regUsername', $errors); ?></div>
                                <div class="form-group"><label for="regPassword">Password <span class="required-ast">*</span></label><input type="password" id="regPassword" name="regPassword" required maxlength="255"><div id="password-strength-status"></div><?php display_error('regPassword', $errors); ?></div>
                                <div class="form-group"><label for="confirmPassword">Re-enter Password <span class="required-ast">*</span></label><input type="password" id="confirmPassword" name="confirmPassword" required maxlength="255"><?php display_error('confirmPassword', $errors); ?></div>
                                <div class="form-group span-all"><input type="checkbox" id="showPassword" style="width: auto; margin-right: 5px;"><label for="showPassword" style="display: inline; font-weight: normal;">Show Password</label></div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn back-btn" onclick="prevStep()">Back</button>
                                <button type="button" class="btn next-btn" onclick="nextStep()">Next</button>
                            </div>
                        </div>

                        <!-- Step 3: Security Questions -->
                        <div class="step-content" data-step="3">
                             <h3 class="step-title">Security Questions</h3>
                             <div class="step-form-grid step3-grid">
                                <div class="qa-row span-all">
                                    <div class="form-group"><label for="security_q1">Question 1 <span class="required-ast">*</span></label><select id="security_q1" name="security_q1" required><option value="">Choose a question...</option><option value="best_friend_elementary" <?php echo old_select('security_q1', 'best_friend_elementary', $input); ?>>Who is your best friend in Elementary?</option><option value="favorite_pet_name" <?php echo old_select('security_q1', 'favorite_pet_name', $input); ?>>What is the name of your favorite pet?</option><option value="favorite_teacher_hs" <?php echo old_select('security_q1', 'favorite_teacher_hs', $input); ?>>Who is your favorite teacher in high school?</option></select></div>
                                    <div class="form-group"><label for="security_a1">Answer 1 <span class="required-ast">*</span></label><input type="password" id="security_a1" name="security_a1" placeholder="Your Answer" required maxlength="100" value="<?php echo old_input('security_a1', $input); ?>"><?php display_error('security_q1', $errors); ?></div>
                                </div>
                                <div class="qa-row span-all">
                                    <div class="form-group"><label for="security_q2">Question 2 <span class="required-ast">*</span></label><select id="security_q2" name="security_q2" required><option value="">Choose a question...</option><option value="best_friend_elementary" <?php echo old_select('security_q2', 'best_friend_elementary', $input); ?>>Who is your best friend in Elementary?</option><option value="favorite_pet_name" <?php echo old_select('security_q2', 'favorite_pet_name', $input); ?>>What is the name of your favorite pet?</option><option value="favorite_teacher_hs" <?php echo old_select('security_q2', 'favorite_teacher_hs', $input); ?>>Who is your favorite teacher in high school?</option></select></div>
                                    <div class="form-group"><label for="security_a2">Answer 2 <span class="required-ast">*</span></label><input type="password" id="security_a2" name="security_a2" placeholder="Your Answer" required maxlength="100" value="<?php echo old_input('security_a2', $input); ?>"><?php display_error('security_q2', $errors); ?></div>
                                </div>
                                <div class="qa-row span-all">
                                    <div class="form-group"><label for="security_q3">Question 3 <span class="required-ast">*</span></label><select id="security_q3" name="security_q3" required><option value="">Choose a question...</option><option value="best_friend_elementary" <?php echo old_select('security_q3', 'best_friend_elementary', $input); ?>>Who is your best friend in Elementary?</option><option value="favorite_pet_name" <?php echo old_select('security_q3', 'favorite_pet_name', $input); ?>>What is the name of your favorite pet?</option><option value="favorite_teacher_hs" <?php echo old_select('security_q3', 'favorite_teacher_hs', $input); ?>>Who is your favorite teacher in high school?</option></select></div>
                                    <div class="form-group"><label for="security_a3">Answer 3 <span class="required-ast">*</span></label><input type="password" id="security_a3" name="security_a3" placeholder="Your Answer" required maxlength="100" value="<?php echo old_input('security_a3', $input); ?>"><?php display_error('security_q3', $errors); ?></div>
                                </div>
                                <div class="form-group span-all"><input type="checkbox" id="showSecurityAnswers" style="width: auto; margin-right: 5px;"><label for="showSecurityAnswers" style="display: inline; font-weight: normal;">Show Answers</label></div>
                             </div>
                            <div class="step-actions">
                                <button type="button" class="btn back-btn" onclick="prevStep()">Back</button>
                                <button type="submit" class="btn primary-btn">Submit Registration</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </main>
        <footer>
            <p>&copy; 2023 LiquorLink Bar Management System. All rights reserved.</p>
        </footer>
    </div>
    <script src="../js/register.js"></script>
</body>
</html>