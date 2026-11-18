<?php
// /sts-app/admin/users/edit_user.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/header.php';

// Toast messages - MUST be after header.php if header.php also has toast
// Let's put it here to be safe
$toast = $_SESSION['toast'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
// Don't unset yet - we need to display it in the HTML first
// We'll unset after displaying in the HTML section

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['toast'] = "❌ Access denied. Admin only.";
    $_SESSION['toast_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit();
}

// Include sidebar
include __DIR__ . '/../../includes/sidebar.php';

// Get user ID
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$user_id) {
    header('Location: users.php');
    exit;
}

// Fetch user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['toast'] = "Admin user not found";
        $_SESSION['toast_type'] = 'error';
        header('Location: users.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['toast'] = "Error loading user: " . $e->getMessage();
    $_SESSION['toast_type'] = 'error';
    header('Location: users.php');
    exit;
}

// Function to generate random password
function generateRandomPassword($length = 10) {
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
        $address = trim($_POST['address'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
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
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
            throw new Exception("Email already exists for another user.");
        }
        
        // Check if username already exists for another user
        if (!empty($username)) {
            $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $user_id]);
            if ($check->fetch()) {
                throw new Exception("Username already exists for another user.");
            }
        }
        
        // Build update query
        $stmt = $db->prepare("
            UPDATE users SET 
                username = ?,
                email = ?,
                first_name = ?,
                last_name = ?,
                phone = ?,
                address = ?,
                postcode = ?,
                notes = ?,
                status = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$username, $email, $first_name, $last_name, $phone, $address, $postcode, $notes, $status, $user_id]);
        
        // Reset password if requested
        $new_password_display = null;
        if ($reset_password) {
            $new_password = generateRandomPassword(10);
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update->execute([$password_hash, $user_id]);
            $new_password_display = $new_password;
        }
        
        // Set session variables for toast
        $_SESSION['toast'] = "✅ Admin user updated successfully!";
        $_SESSION['toast_type'] = 'success';
        
        if ($reset_password) {
            $_SESSION['new_admin_password'] = $new_password_display;
        }
        
        session_write_close();
        
        // Redirect to same page to show updated data and toast
        header("Location: edit_user.php?id=" . $user_id);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if we have a new password to display (from session after redirect)
$new_password_display = null;
if (isset($_SESSION['new_admin_password'])) {
    $new_password_display = $_SESSION['new_admin_password'];
    unset($_SESSION['new_admin_password']);
}

// Also check for toast from redirect
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    $toast_type = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast'], $_SESSION['toast_type']);
}
?>

<style>
.form-container {
    max-width: 700px;
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
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

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin: 20px 0 15px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #8b5cf6;
    display: inline-block;
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
.toast-info { background: #3b82f6; border-left-color: #2563eb; }

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

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .form-container {
        padding: 20px;
    }
    
    .btn-save, .btn-cancel {
        width: 100%;
        text-align: center;
    }
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
            <h1 style="margin: 0 0 5px 0; color: #1e293b;">✏️ Edit Admin User</h1>
            <p style="color: #64748b; margin: 0;">Update administrator account details</p>
        </div>
        
        <?php if ($new_password_display): ?>
        <div class="success-box">
            <h3>✅ Password Reset!</h3>
            <div class="credentials-box">
                <p><strong>New Temporary Password:</strong> <span id="passwordCopy"><?= htmlspecialchars($new_password_display) ?></span></p>
                <button class="copy-btn" onclick="copyPassword()">📋 Copy Password</button>
            </div>
            <p style="margin-top: 10px; font-size: 12px; color: #065f46;">
                Please give this password to the admin user.
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
                <!-- Personal Information -->
                <div class="section-title">👤 Personal Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input type="text" class="form-control" id="postcode" name="postcode" value="<?= htmlspecialchars($user['postcode'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>">
                    </div>
                </div>
                
                <hr>
                
                <!-- Account Settings -->
                <div class="section-title">⚙️ Account Settings</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active - Can log in</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive - Cannot log in</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Internal notes about this admin..."><?= htmlspecialchars($user['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="password-reset">
                    <div class="checkbox-group">
                        <input type="checkbox" id="reset_password" name="reset_password" value="1">
                        <label for="reset_password" style="margin-bottom: 0;">Reset Password</label>
                    </div>
                    <div class="auto-gen-hint" style="margin-top: 8px; font-size: 12px; color: #b45309;">
                        🔑 Check this box to generate a new temporary password for this admin user.
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">💾 Save Changes</button>
                    <a href="users.php" class="btn-cancel">Cancel</a>
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