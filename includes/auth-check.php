<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

    header("Location: /sts-app/auth/login.php");
    exit();

}

function isAdmin()
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isDriver()
{
    return ($_SESSION['role'] ?? '') === 'driver';
}