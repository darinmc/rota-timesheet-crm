<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth-check.php';

$user_role = $_SESSION['role'] ?? 'driver';
$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';

define("BASE_URL", "/sts-app/");
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<title>STS Transport System</title>

<!-- Standard favicon for browsers -->
<link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/DisShef.png">

<!-- Apple touch icon (for iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>assets/images/DisShef.png">

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

</head>

<body>

<header class="header">

<button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>

<!-- Mobile menu button (visible only on mobile) -->
<button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>

<div class="welcome">
    <img src="<?= BASE_URL ?>assets/images/DisShef.png" alt="STS Logo" class="welcome-logo">
    Welcome <?= htmlspecialchars($username) ?>
</div>

<div class="user-info">
    <span><?= htmlspecialchars($email) ?></span>
    <a href="<?= BASE_URL ?>auth/logout.php" class="logout-btn">Logout</a>
</div>

</header>

<!-- Mobile overlay menu -->
<div id="mobileMenu" class="mobile-menu-overlay">
    <div class="mobile-menu-header">
        <span>🚕 STS Transport</span>
        <button class="close-menu" onclick="toggleMobileMenu()">×</button>
    </div>
    <nav class="mobile-menu-nav">
        <!-- Menu items will be populated by JavaScript -->
    </nav>
</div>

<div class="dashboard-layout">