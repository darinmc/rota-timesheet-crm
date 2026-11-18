<?php
// /sts-app/auth/login.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Define BASE_URL if not defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/sts-app/');
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

// Check for logout message
if (isset($_GET['message'])) {
    $success = htmlspecialchars($_GET['message']);
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'driver/dashboard.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username/email and password";
    } else {
        // Check if login is email or username
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                $error = "Your account is not active. Please contact administrator.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                
                // Update last login
                $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
                
                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . 'admin/dashboard.php');
                } else {
                    header('Location: ' . BASE_URL . 'driver/dashboard.php');
                }
                exit();
            }
        } else {
            $error = "Invalid username/email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - STS Transport System</title>
    
    <!-- Standard favicon for browsers -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/DisShef.png">
    
    <!-- Apple touch icon (for iOS home screen) -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>assets/images/DisShef.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8fafc;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .login-header {
                padding: 25px 20px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
            
            .login-body {
                padding: 25px 20px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            .btn-login {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🚕 STS Transport</h1>
            <p>Sign in to your account</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Enter username or email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter password" required>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
        
        <div class="login-footer">
            &copy; <?= date('Y') ?> STS Transport System. All rights reserved.
        </div>
    </div>
</body>
</html>