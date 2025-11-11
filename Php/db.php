<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
// db.php - create a PDO connection to MySQL
// Update these values to match your environment
$db_host = '127.0.0.1';
$db_name = 'registration_db';
$db_user = 'root';
$db_pass = '';
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    // In production, don't echo details. For local development we show the error.
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

?>
