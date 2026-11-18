<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if (!$login || !$password) {
    header("Location: login.php?error=missing");
    exit;
}

try {

    $stmt = $db->prepare("
        SELECT *
        FROM users
        WHERE (username = :login OR email = :login)
        AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        ':login' => $login
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Invalid login credentials.");
    }

    if (!password_verify($password, $user['password_hash'])) {
        die("Invalid login credentials.");
    }

    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $user['role'];

    // Update last login
    $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $update->execute([$user['id']]);

    // Redirect
    if ($user['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../driver/dashboard.php");
    }

    exit;

} catch (Exception $e) {
    die("Login error.");
}