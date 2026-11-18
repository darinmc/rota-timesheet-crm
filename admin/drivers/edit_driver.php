<?php
// /sts-app/admin/drivers/edit_driver.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Toast messages
$toast = $_SESSION['toast'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast'], $_SESSION['toast_type']);

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['toast'] = "❌ Access denied. Admin only.";
    $_SESSION['toast_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit();
}

// Include sidebar
include __DIR__ . '/../../includes/sidebar.php';

// Get driver ID
$driver_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$driver_id) {
    header('Location: drivers.php');
    exit;
}

// Fetch driver data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'driver'");
    $stmt->execute([$driver_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        $_SESSION['toast'] = "Driver not found";
        $_SESSION['toast_type'] = 'error';
        header('Location: drivers.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['toast'] = "Error loading driver: " . $e->getMessage();
    $_SESSION['toast_type'] = 'error';
    header('Location: drivers.php');
    exit;
}

// Function to generate random password (NO special characters for easier login)
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $reset_password = isset($_POST['reset_password']) ? true : false;
        
        // Validate
        if (empty($first_name) || empty($last_name)) {
            throw new Exception("First name and last name are required");
        }
        if (empty($email)) {
            throw new Exception("Email is required");
        }
        
        // Check if email already exists for another user
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $driver_id]);
        if ($check->fetch()) {
            throw new Exception("Email already exists for another user.");
        }
        
        // Check if username already exists for another user
        if (!empty($username)) {
            $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $driver_id]);
            if ($check->fetch()) {
                throw new Exception("Username already exists for another user.");
            }
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $updates[] = "username = ?";
        $params[] = $username;
        
        $updates[] = "email = ?";
        $params[] = $email;
        
        $updates[] = "first_name = ?";
        $params[] = $first_name;
        
        $updates[] = "last_name = ?";
        $params[] = $last_name;
        
        $updates[] = "phone = ?";
        $params[] = $phone;
        
        $updates[] = "status = ?";
        $params[] = $status;
        
        // Reset password if requested
        $new_password_display = null;
        if ($reset_password) {
            $new_password = generateRandomPassword(8); // Simpler password for drivers
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $password_hash;
            $new_password_display = $new_password;
        }
        
        $params[] = $driver_id;
        
        $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $_SESSION['toast'] = "✅ Driver updated successfully!";
        $_SESSION['toast_type'] = 'success';
        
        if ($reset_password && $new_password_display) {
            $_SESSION['new_driver_password'] = $new_password_display;
        }
        
        session_write_close();
        header("Location: edit_driver.php?id=" . $driver_id);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if we have a new password to display
$new_password_display = null;
if (isset($_SESSION['new_driver_password'])) {
    $new_password_display = $_SESSION['new_driver_password'];
    unset($_SESSION['new_driver_password']);
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

.password-reset {
    background: #fef3c7;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
    border-left: 3px solid #f59e0b;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.checkbox-group input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-group label {
    margin-bottom: 0;
    cursor: pointer;
}

hr {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #e2e8f0;
}

/* Toast styling */
.toast-container {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10000;
    max-width: 400px;
    width: 90%;
}

.toast-message {
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 4px solid;
    animation: slideDown 0.3s ease;
}

.toast-success { background: #10b981; border-left-color: #059669; }
.toast-error { background: #ef4444; border-left-color: #dc2626; }

.toast-close {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 18px;
    margin-left: 15px;
}

@keyframes slideDown {
    from { transform: translate(-50%, -20px); opacity: 0; }
    to { transform: translate(-50%, 0); opacity: 1; }
}
</style>

<div class="main-content">
    <div style="padding: 20px;">
        
        <!-- Toast Messages -->
        <?php if ($toast): ?>
        <div class="toast-container">
            <div class="toast-message toast-<?= $toast_type ?>">
                <span><?= htmlspecialchars($toast) ?></span>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 25px;">
            <h1 style="margin: 0 0 5px 0; color: #1e293b;">✏️ Edit Driver</h1>
            <p style="color: #64748b; margin: 0;">Update driver information</p>
        </div>
        
        <?php if ($new_password_display): ?>
        <div class="success-box">
            <h3>✅ Password Reset!</h3>
            <div class="credentials-box">
                <p><strong>New Temporary Password:</strong> <span id="passwordCopy"><?= htmlspecialchars($new_password_display) ?></span></p>
                <button class="copy-btn" onclick="copyPassword()">📋 Copy Password</button>
            </div>
            <p style="margin-top: 10px; font-size: 12px; color: #065f46;">
                Please give this password to the driver. They can change it after logging in.
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
                    <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= htmlspecialchars($driver['first_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= htmlspecialchars($driver['last_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($driver['email']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($driver['username'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($driver['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?= $driver['status'] === 'active' ? 'selected' : '' ?>>Active - Can log in</option>
                        <option value="inactive" <?= $driver['status'] === 'inactive' ? 'selected' : '' ?>>Inactive - Cannot log in</option>
                    </select>
                </div>
                
                <hr>
                
                <div class="password-reset">
                    <div class="checkbox-group">
                        <input type="checkbox" id="reset_password" name="reset_password" value="1">
                        <label for="reset_password" style="margin-bottom: 0;">Reset Password</label>
                    </div>
                    <div class="auto-gen-hint" style="margin-top: 8px; font-size: 12px; color: #b45309;">
                        🔑 Check this box to generate a new temporary password for the driver.
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">💾 Save Changes</button>
                    <a href="drivers.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyPassword() {
    const password = document.getElementById('passwordCopy').innerText;
    
    navigator.clipboard.writeText(password).then(() => {
        const btn = document.querySelector('.copy-btn');
        const originalText = btn.innerText;
        btn.innerText = '✅ Copied!';
        setTimeout(() => {
            btn.innerText = originalText;
        }, 2000);
    });
}

// Auto-remove toast after 4 seconds
setTimeout(() => {
    const toast = document.querySelector('.toast-message');
    if (toast) {
        toast.style.opacity = '0';
        setTimeout(() => toast?.remove(), 500);
    }
}, 4000);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>