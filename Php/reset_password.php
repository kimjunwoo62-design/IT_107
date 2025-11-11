<?php
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit();
}

$user_id = $_POST['user_id'] ?? null;
$a1 = trim($_POST['security_a1'] ?? '');
$a1_re = trim($_POST['security_a1_re'] ?? '');
$a2 = trim($_POST['security_a2'] ?? '');
$a2_re = trim($_POST['security_a2_re'] ?? '');
$a3 = trim($_POST['security_a3'] ?? '');
$a3_re = trim($_POST['security_a3_re'] ?? '');

// DEBUG: Print all POST data
error_log("=== FORGOT PASSWORD DEBUG ===");
error_log("User ID: " . $user_id);
error_log("Answer 1: " . $a1);
error_log("Answer 1 Re: " . $a1_re);
error_log("Answer 2: " . $a2);
error_log("Answer 2 Re: " . $a2_re);
error_log("Answer 3: " . $a3);
error_log("Answer 3 Re: " . $a3_re);

if (empty($user_id) || (empty($a1) && empty($a2) && empty($a3))) {
    error_log("ERROR: Empty user_id or all answers empty");
    $_SESSION['errors'] = ['form' => 'Please answer all security questions.'];
    header('Location: forgot_password.php');
    exit();
}

// Check if answers match re-enter answers
if ($a1 !== $a1_re || $a2 !== $a2_re || $a3 !== $a3_re) {
    error_log("ERROR: Answers don't match re-enter answers");
    $_SESSION['errors'] = ['form' => 'Your answers and re-enter answers do not match.'];
    header('Location: forgot_password.php');
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT security_a1_hash, security_a2_hash, security_a3_hash FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // DEBUG: Print user data from database
    error_log("User data from DB:");
    error_log(print_r($user, true));
    
    if (!$user) {
        error_log("ERROR: User not found with ID: " . $user_id);
        $_SESSION['errors'] = ['form' => 'User not found. Please start over.'];
        header('Location: forgot_password.php');
        exit();
    }

    // DEBUG: Check each security answer hash
    error_log("Security Hash 1: " . ($user['security_a1_hash'] ?? 'NULL'));
    error_log("Security Hash 2: " . ($user['security_a2_hash'] ?? 'NULL'));
    error_log("Security Hash 3: " . ($user['security_a3_hash'] ?? 'NULL'));

} catch (PDOException $e) {
    error_log("DATABASE ERROR: " . $e->getMessage());
    $_SESSION['errors'] = ['form' => 'Database error. Please try again.'];
    header('Location: forgot_password.php');
    exit();
}

// Check all three answers
$answer1_correct = !empty($a1) && !empty($user['security_a1_hash']) && password_verify($a1, $user['security_a1_hash']);
$answer2_correct = !empty($a2) && !empty($user['security_a2_hash']) && password_verify($a2, $user['security_a2_hash']);
$answer3_correct = !empty($a3) && !empty($user['security_a3_hash']) && password_verify($a3, $user['security_a3_hash']);

// DEBUG: Print verification results
error_log("Answer 1 correct: " . ($answer1_correct ? 'YES' : 'NO'));
error_log("Answer 2 correct: " . ($answer2_correct ? 'YES' : 'NO'));
error_log("Answer 3 correct: " . ($answer3_correct ? 'YES' : 'NO'));

$answer_correct = $answer1_correct && $answer2_correct && $answer3_correct;

error_log("All answers correct: " . ($answer_correct ? 'YES' : 'NO'));

if ($answer_correct) {
    // Success! Authorize password reset and redirect.
    error_log("SUCCESS: Password reset authorized for user ID: " . $user_id);
    $_SESSION['reset_authorized_for_user_id'] = $user_id;
    header('Location: change_password.php');
    exit();
} else {
    error_log("FAILED: One or more answers incorrect");
    $_SESSION['errors'] = ['form' => 'One or more of the provided answers were incorrect. Please try again.'];
    // Store username in session to preserve the form state
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch();
    if ($userData) {
        $_SESSION['forgot_password_username'] = $userData['username'];
        error_log("Username stored in session: " . $userData['username']);
    }
    header('Location: forgot_password.php');
    exit();
}
?>