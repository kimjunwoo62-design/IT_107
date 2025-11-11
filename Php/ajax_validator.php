<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
// ajax_validator.php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$response = ['exists' => false];
$type = $_POST['type'] ?? '';
$value = $_POST['value'] ?? '';

if (!$type || !$value) {
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}

try {
    if ($type === 'id_number') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id_number = ?');
    } elseif ($type === 'username') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    } else {
        echo json_encode(['error' => 'Invalid validation type.']);
        exit;
    }

    $stmt->execute([$value]);

    if ($stmt->fetch()) {
        $response['exists'] = true;
    }
} catch (PDOException $e) {
    // In a real application, you'd log this error.
    // For now, we'll send a generic error to the client.
    echo json_encode(['error' => 'Database query failed.']);
    exit;
}

echo json_encode($response);
