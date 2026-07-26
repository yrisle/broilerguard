<?php
// config/database.php - Database connection using PDO

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'broilerguard');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL password

// Set DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Optional: Set timezone to match system settings
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // Log error and show a friendly message (for production)
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

?>