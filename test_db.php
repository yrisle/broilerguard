<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3307;dbname=broilerguard",
        "root",
        ""
    );

    echo "CONNECTED!";
} catch (PDOException $e) {
    die($e->getMessage());
}