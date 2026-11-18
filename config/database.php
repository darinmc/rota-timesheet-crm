<?php

$host = "localhost";
$dbname = "sts_transport";
$username = "sts_user";
$password = "StsApp2026!Secure";

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Database connection failed: " . $e->getMessage());

}