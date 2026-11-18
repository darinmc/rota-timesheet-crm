<?php
// /sts-app/admin/drivers/add_driver.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['toast'] = "❌ Access denied. Admin only.";
    $_SESSION['toast_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit();
}

// Include sidebar
include __DIR__ . '/../../includes/sidebar.php';

// Function to generate random password
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Variables for password display
$generated_password = '';
$new_driver_username = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Validate
        if (empty($first_name) || empty($last_name)) {
            throw new Exception("First name and last name are required");
        }
        if (empty($email)) {
            throw new Exception("Email is required");
        }
        if (empty($username)) {
            // Auto-generate username from email
            $username = explode('@', $email)[0];
            // Make sure username is unique
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $username = $username . rand(100, 999);
            }
        }
        
        // Check if email already exists
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new Exception("Email already exists. Please use a different email.");
        }
        
        // Check if username already exists
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            throw new Exception("Username already exists. Please use a different username.");
        }
        
        // Generate random password
        $generated_password = generateRandomPassword(10);
        $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
        
        // Insert new driver
        $stmt = $db->prepare("
            INSERT INTO users (username, email, first_name, last_name, phone, password_hash, role, status)
            VALUES (?, ?, ?, ?, ?, ?, 'driver', ?)
        ");
        
        $stmt->execute([$username, $email, $first_name, $last_name, $phone, $password_hash, $status]);
        
        $new_driver_username = $username;
        
        $_SESSION['toast'] = "✅ Driver created successfully!";
        $_SESSION['toast_type'] = 'success';
        
        // Store password in session to display on next page load
        $_SESSION['new_driver_password'] = $generated_password;
        $_SESSION['new_driver_username'] = $username;
        
        session_write_close();
        header("Location: add_driver.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if we have a new driver password to display
$show_password = isset($_GET['success']) && isset($_SESSION['new_driver_password']);
if ($show_password) {
    $new_password = $_SESSION['new_driver_password'];
    $new_username = $_SESSION['new_driver_username'];
    // Clear session variables after showing
    unset($_SESSION['new_driver_password'], $_SESSION['new_driver_username']);
}
?>

<style>
.form-container {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e293b;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 30px;
}

.btn-save {
    background: #10b981;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-save:hover {
    background: #059669;
}

.btn-cancel {
    background: #64748b;
    color: white;
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: background 0.2s;
    display: inline-block;
}

.btn-cancel:hover {
    background: #475569;
}

.error-message {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 3px solid #dc2626;
}

.success-box {
    background: #d1fae5;
    border: 1px solid #10b981;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.success-box h3 {
    margin: 0 0 10px 0;
    color: #065f46;
}

.credentials-box {
    background: #fef3c7;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
    font-family: monospace;
    font-size: 16px;
}

.credentials-box p {
    margin: 5px 0;
}

.copy-btn {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-top: 10px;
}

.copy-btn:hover {
    background: #2563eb;
}

.auto-gen-hint {
    font-size: 11px;
    color: #64748b;
    margin-top: 4px;
}
</style>

<div class="main-content">
    <div style="padding: 20px;">
        <div style="margin-bottom: 25px;">
            <h1 style="margin: 0 0 5px 0; color: #1e293b;">🚗 Add New Driver</h1>
            <p style="color: #64748b; margin: 0;">Create a new driver account. A temporary password will be generated.</p>
        </div>
        
        <?php if ($show_password): ?>
        <div class="success-box">
            <h3>✅ Driver Created Successfully!</h3>
            <p>Please provide these credentials to the driver:</p>
            <div class="credentials-box">
                <p><strong>Username:</strong> <span id="usernameCopy"><?= htmlspecialchars($new_username) ?></span></p>
                <p><strong>Password:</strong> <span id="passwordCopy"><?= htmlspecialchars($new_password) ?></span></p>
                <button class="copy-btn" onclick="copyCredentials()">📋 Copy Credentials</button>
            </div>
            <p style="margin-top: 10px; font-size: 12px; color: #065f46;">
                ⚠️ The driver should change their password after first login.
            </p>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="auto-gen-hint">
                        📧 Used for login and notifications
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <div class="auto-gen-hint">
                        🔑 Leave blank to auto-generate from email
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" selected>Active - Can log in immediately</option>
                        <option value="inactive">Inactive - Cannot log in until activated</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">💾 Create Driver Account</button>
                    <a href="drivers.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyCredentials() {
    const username = document.getElementById('usernameCopy').innerText;
    const password = document.getElementById('passwordCopy').innerText;
    const credentials = `Username: ${username}\nPassword: ${password}`;
    
    navigator.clipboard.writeText(credentials).then(() => {
        const btn = document.querySelector('.copy-btn');
        const originalText = btn.innerText;
        btn.innerText = '✅ Copied!';
        setTimeout(() => {
            btn.innerText = originalText;
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>