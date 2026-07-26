<?php

try {

    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=broilerguard;charset=utf8mb4",
        "root",
        ""
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e){

    die($e->getMessage());

}