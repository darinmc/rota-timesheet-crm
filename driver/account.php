<?php
// /sts-app/driver/account.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

// Check if user is driver
if ($_SESSION['role'] !== 'driver') {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

// Include sidebar
include __DIR__ . '/../includes/sidebar.php';

// Toast messages
$toast = $_SESSION['toast'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast'], $_SESSION['toast_type']);

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

// Get driver data from drivers table
$stmt = $db->prepare("SELECT * FROM drivers WHERE user_id = ?");
$stmt->execute([$user_id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

// If no driver record exists, create empty array with defaults
if (!$driver) {
    $driver = [
        'license_number' => '',
        'license_expiry' => '',
        'hire_date' => '',
        'vehicle_registration' => '',
        'vehicle_make' => '',
        'vehicle_model' => '',
        'vehicle_type' => 'sedan',
        'vehicle_year' => '',
        'vehicle_color' => '',
        'dbs_check' => 0,
        'dbs_expiry' => '',
        'insurance_valid' => 0,
        'insurance_expiry' => '',
        'emergency_contact_name' => '',
        'emergency_contact_phone' => '',
        'current_status' => 'pending',
        'notes' => ''
    ];
}

// Handle form submissions - ONLY driver-editable fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Fields drivers can edit
    $vehicle_registration = trim($_POST['vehicle_registration'] ?? '');
    $vehicle_make = trim($_POST['vehicle_make'] ?? '');
    $vehicle_model = trim($_POST['vehicle_model'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? 'sedan';
    $vehicle_year = trim($_POST['vehicle_year'] ?? '');
    $vehicle_color = trim($_POST['vehicle_color'] ?? '');
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Check if driver record exists
    $check = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $check->execute([$user_id]);
    
    if ($check->fetch()) {
        // Update existing - ONLY driver-editable fields
        $stmt = $db->prepare("
            UPDATE drivers SET 
                vehicle_registration = ?,
                vehicle_make = ?,
                vehicle_model = ?,
                vehicle_type = ?,
                vehicle_year = ?,
                vehicle_color = ?,
                emergency_contact_name = ?,
                emergency_contact_phone = ?,
                notes = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $success = $stmt->execute([
            $vehicle_registration,
            $vehicle_make,
            $vehicle_model,
            $vehicle_type,
            $vehicle_year,
            $vehicle_color,
            $emergency_contact_name,
            $emergency_contact_phone,
            $notes,
            $user_id
        ]);
    } else {
        // Insert new with defaults for admin fields
        $stmt = $db->prepare("
            INSERT INTO drivers (
                user_id, vehicle_registration, vehicle_make, vehicle_model, vehicle_type,
                vehicle_year, vehicle_color, emergency_contact_name, emergency_contact_phone,
                notes, current_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([
            $user_id,
            $vehicle_registration,
            $vehicle_make,
            $vehicle_model,
            $vehicle_type,
            $vehicle_year,
            $vehicle_color,
            $emergency_contact_name,
            $emergency_contact_phone,
            $notes,
            'pending'
        ]);
    }
    
    if ($success) {
        $_SESSION['toast'] = "✅ Profile updated successfully!";
        $_SESSION['toast_type'] = 'success';
    } else {
        $_SESSION['toast'] = "❌ Failed to update profile";
        $_SESSION['toast_type'] = 'error';
    }
    
    header('Location: account.php');
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['toast'] = "All password fields are required";
        $_SESSION['toast_type'] = 'error';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['toast'] = "New passwords do not match";
        $_SESSION['toast_type'] = 'error';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['toast'] = "Password must be at least 6 characters";
        $_SESSION['toast_type'] = 'error';
    } elseif (!password_verify($current_password, $user['password_hash'])) {
        $_SESSION['toast'] = "Current password is incorrect";
        $_SESSION['toast_type'] = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($update_stmt->execute([$hashed_password, $user_id])) {
            // Password changed - log out and redirect to login
            session_destroy();
            session_start();
            $_SESSION['toast'] = "✅ Password changed successfully! Please login with your new password.";
            $_SESSION['toast_type'] = 'success';
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit();
        } else {
            $_SESSION['toast'] = "Failed to change password";
            $_SESSION['toast_type'] = 'error';
        }
    }
    
    header('Location: account.php');
    exit();
}

// Helper functions
function display_date($date) {
    if (empty($date)) return 'Not set';
    return date('jS F Y', strtotime($date));
}

function display_check($value) {
    return $value ? '✅ Yes' : '❌ No';
}
?>

<style>
/* Driver Account Styles */
* {
    box-sizing: border-box;
}

.main-content {
    overflow-x: auto;
    padding: 20px;
}

.account-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 15px;
    width: 100%;
}

/* Header */
.account-header {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}

.account-header h1 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.role-badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    margin-left: 8px;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 5px;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 20px;
    flex-wrap: wrap;
    overflow-x: auto;
}

.tab {
    padding: 10px 20px;
    background: none;
    border: none;
    font-size: 14px;
    cursor: pointer;
    color: #64748b;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    font-weight: 500;
    white-space: nowrap;
}

.tab:hover {
    color: #3b82f6;
}

.tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Cards */
.account-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    width: 100%;
}

.account-card h2 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #1e293b;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 10px;
}

.account-card h3 {
    font-size: 15px;
    color: #475569;
    margin: 15px 0 10px 0;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: 160px 1fr;
    gap: 12px;
    margin-bottom: 15px;
}

.info-label {
    font-weight: 600;
    color: #64748b;
    font-size: 13px;
}

.info-value {
    color: #1e293b;
    font-size: 14px;
    word-break: break-word;
}

/* Read-only field styling */
.readonly-field {
    background: #f8fafc;
    color: #475569;
}

/* Admin-only fields section */
.admin-section {
    background: #fffbeb;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #fef3c7;
}

.admin-section h2 {
    color: #b45309;
    border-bottom-color: #fed7aa;
}

.admin-section .info-label {
    color: #b45309;
}

.admin-badge {
    display: inline-block;
    background: #f59e0b;
    color: white;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 12px;
    margin-left: 10px;
    vertical-align: middle;
}

/* Forms */
.form-group {
    margin-bottom: 15px;
    width: 100%;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #475569;
    font-size: 13px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.form-control[readonly] {
    background: #f8fafc;
    cursor: default;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
    width: 100%;
}

.btn {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
}

.btn:hover {
    background: #2563eb;
}

/* Vehicle Display */
.vehicle-display {
    background: #f8fafc;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}

.vehicle-reg {
    font-size: 20px;
    font-weight: bold;
    color: #10b981;
    font-family: monospace;
    letter-spacing: 1px;
}

/* Toast */
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

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

/* Action Buttons */
.driver-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.action-btn {
    background: #f1f5f9;
    color: #1e293b;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.action-btn:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 768px) {
    .account-container {
        padding: 10px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 5px;
    }
    
    .info-label {
        margin-bottom: 2px;
    }
    
    .tabs {
        gap: 0;
        overflow-x: auto;
    }
    
    .tab {
        padding: 8px 12px;
        font-size: 13px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .account-card {
        padding: 15px;
    }
    
    .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="main-content">
    <div class="account-container">
        
        <!-- Toast Messages -->
        <?php if ($toast): ?>
        <div class="toast-container">
            <div class="toast-message toast-<?= $toast_type ?>">
                <span><?= htmlspecialchars($toast) ?></span>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="account-header">
            <h1>👤 My Driver Account 
                <span class="role-badge">DRIVER</span>
            </h1>
            <p style="font-size: 14px; opacity: 0.9;">Manage your profile and vehicle information</p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('profile')">📋 Profile</button>
            <button class="tab" onclick="showTab('vehicle')">🚗 Vehicle</button>
            <button class="tab" onclick="showTab('password')">🔐 Password</button>
        </div>
        
        <!-- Profile Tab -->
        <div id="profile-tab" class="tab-content active">
            <!-- Personal Information Card -->
            <div class="account-card">
                <h2>👤 Personal Information</h2>
                
                <div class="info-grid">
                    <div class="info-label">Driver ID:</div>
                    <div class="info-value" style="font-family: monospace; color: #3b82f6;">
                        #<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?>
                    </div>
                    
                    <div class="info-label">Username:</div>
                    <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                    
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                    
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    
                    <div class="info-label">Phone:</div>
                    <div class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                    
                    <div class="info-label">Emergency Contact:</div>
                    <div class="info-value">
                        <?php if (!empty($driver['emergency_contact_name'])): ?>
                            <?= htmlspecialchars($driver['emergency_contact_name']) ?>
                            <?php if (!empty($driver['emergency_contact_phone'])): ?>
                                (<?= htmlspecialchars($driver['emergency_contact_phone']) ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-label">Member Since:</div>
                    <div class="info-value"><?= date('jS F Y', strtotime($user['created_at'])) ?></div>
                    
                    <div class="info-label">Last Login:</div>
                    <div class="info-value"><?= date('jS F Y, H:i', strtotime($user['last_login'])) ?></div>
                </div>
            </div>
            
            <!-- Driver Notes Card - Editable -->
            <div class="account-card">
                <h2>📝 Personal Notes</h2>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Add your own notes or reminders (only visible to you)</p>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="e.g., Remember to refuel, preferred parking spot, etc..."><?= htmlspecialchars($driver['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn">💾 Save Notes</button>
                </form>
            </div>
            
            <!-- Admin-Only Fields (View Only) -->
            <div class="admin-section">
                <h2>📋 Employment & Compliance <span class="admin-badge">Admin Managed</span></h2>
                <div class="info-grid">
                    <div class="info-label">License Number:</div>
                    <div class="info-value"><?= htmlspecialchars($driver['license_number'] ?? 'Not set') ?></div>
                    
                    <div class="info-label">License Expiry:</div>
                    <div class="info-value"><?= display_date($driver['license_expiry'] ?? '') ?></div>
                    
                    <div class="info-label">Hire Date:</div>
                    <div class="info-value"><?= display_date($driver['hire_date'] ?? '') ?></div>
                    
                    <div class="info-label">DBS Check:</div>
                    <div class="info-value"><?= display_check($driver['dbs_check'] ?? 0) ?></div>
                    
                    <div class="info-label">DBS Expiry:</div>
                    <div class="info-value"><?= display_date($driver['dbs_expiry'] ?? '') ?></div>
                    
                    <div class="info-label">Insurance Valid:</div>
                    <div class="info-value"><?= display_check($driver['insurance_valid'] ?? 0) ?></div>
                    
                    <div class="info-label">Insurance Expiry:</div>
                    <div class="info-value"><?= display_date($driver['insurance_expiry'] ?? '') ?></div>
                    
                    <div class="info-label">Driver Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-<?= $driver['current_status'] ?? 'pending' ?>">
                            <?= ucfirst($driver['current_status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
                <p style="font-size: 12px; color: #b45309; margin-top: 15px; padding-top: 10px; border-top: 1px solid #fed7aa;">
                    <i class="fas fa-info-circle"></i> These fields are managed by the office. 
                    Please contact admin if any information needs updating.
                </p>
            </div>
        </div>
        
        <!-- Vehicle Tab (Driver Editable) -->
        <div id="vehicle-tab" class="tab-content">
            <div class="account-card">
                <h2>🚗 Vehicle Information</h2>
                
                <!-- Current Vehicle Display -->
                <div class="vehicle-display">
                    <div style="margin-bottom: 10px;">
                        <span style="color: #64748b; font-size: 12px;">REGISTRATION</span>
                        <div class="vehicle-reg">
                            <?= !empty($driver['vehicle_registration']) ? 
                                htmlspecialchars($driver['vehicle_registration']) : 
                                'Not set' ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($driver['vehicle_make']) || !empty($driver['vehicle_model'])): ?>
                        <div style="margin-top: 10px;">
                            <span style="color: #64748b; font-size: 12px;">VEHICLE</span>
                            <div style="font-size: 16px; font-weight: 500;">
                                <?= htmlspecialchars($driver['vehicle_make']) ?> 
                                <?= htmlspecialchars($driver['vehicle_model']) ?>
                                <?php if (!empty($driver['vehicle_year'])): ?>
                                    (<?= htmlspecialchars($driver['vehicle_year']) ?>)
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($driver['vehicle_color'])): ?>
                                <div style="font-size: 13px; color: #64748b; margin-top: 5px;">
                                    Color: <?= htmlspecialchars($driver['vehicle_color']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($driver['vehicle_type'])): ?>
                                <div style="font-size: 13px; color: #64748b;">
                                    Type: <?= ucfirst($driver['vehicle_type']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 10px; color: #94a3b8; font-style: italic;">
                            No vehicle details added yet
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Update Form - Driver Editable Only -->
                <h3>✏️ Update Vehicle Details</h3>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vehicle_registration">Vehicle Registration</label>
                            <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" 
                                   value="<?= htmlspecialchars($driver['vehicle_registration'] ?? '') ?>" 
                                   placeholder="e.g., AB12 CDE"
                                   oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type</label>
                            <select class="form-control" id="vehicle_type" name="vehicle_type">
                                <option value="sedan" <?= ($driver['vehicle_type'] ?? '') == 'sedan' ? 'selected' : '' ?>>Sedan</option>
                                <option value="suv" <?= ($driver['vehicle_type'] ?? '') == 'suv' ? 'selected' : '' ?>>SUV</option>
                                <option value="minivan" <?= ($driver['vehicle_type'] ?? '') == 'minivan' ? 'selected' : '' ?>>Minivan</option>
                                <option value="wheelchair" <?= ($driver['vehicle_type'] ?? '') == 'wheelchair' ? 'selected' : '' ?>>Wheelchair Accessible</option>
                                <option value="luxury" <?= ($driver['vehicle_type'] ?? '') == 'luxury' ? 'selected' : '' ?>>Luxury</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vehicle_make">Make</label>
                            <input type="text" class="form-control" id="vehicle_make" name="vehicle_make" 
                                   value="<?= htmlspecialchars($driver['vehicle_make'] ?? '') ?>" 
                                   placeholder="e.g., Toyota">
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_model">Model</label>
                            <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" 
                                   value="<?= htmlspecialchars($driver['vehicle_model'] ?? '') ?>" 
                                   placeholder="e.g., Camry">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vehicle_year">Year</label>
                            <input type="number" class="form-control" id="vehicle_year" name="vehicle_year" 
                                   value="<?= htmlspecialchars($driver['vehicle_year'] ?? '') ?>" 
                                   placeholder="e.g., 2020"
                                   min="1990" max="<?= date('Y') + 1 ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_color">Color</label>
                            <input type="text" class="form-control" id="vehicle_color" name="vehicle_color" 
                                   value="<?= htmlspecialchars($driver['vehicle_color'] ?? '') ?>" 
                                   placeholder="e.g., Blue">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emergency_contact_name">Emergency Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                   value="<?= htmlspecialchars($driver['emergency_contact_name'] ?? '') ?>" 
                                   placeholder="e.g., Jane Smith">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_phone">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                   value="<?= htmlspecialchars($driver['emergency_contact_phone'] ?? '') ?>" 
                                   placeholder="e.g., 07700 900123">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">💾 Update Vehicle Details</button>
                </form>
            </div>
        </div>
        
<!-- Password Tab -->
<div id="password-tab" class="tab-content">
    <div class="account-card">
        <h2>🔐 Change Password</h2>
        
        <form method="POST">
            <input type="hidden" name="change_password" value="1">
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                <small style="color: #64748b;">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <button type="submit" class="btn">🔑 Change Password</button>
        </form>
        
        <!-- Helpful message instead of broken link -->
        <div style="margin-top: 20px; padding: 12px; background: #f8fafc; border-radius: 8px; text-align: center; border-left: 3px solid #f59e0b;">
            <p style="margin: 0; font-size: 13px; color: #64748b;">
                💡 <strong>Forgot your password?</strong><br>
                Please contact your administrator to reset your password.
            </p>
        </div>
    </div>
</div>
        
        <!-- Quick Actions -->
        <div class="driver-actions">
            <a href="dashboard.php" class="action-btn">🏠 Dashboard</a>
            <a href="weekly.php" class="action-btn">📅 Weekly Rota</a>
            <a href="<?= BASE_URL ?>auth/logout.php" class="action-btn" style="background: #fee2e2; color: #dc2626;">🔓 Logout</a>
        </div>
        
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Password confirmation validation
document.querySelector('form[action=""]')?.addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
        e.preventDefault();
        alert('New passwords do not match!');
        confirmPassword.focus();
    }
});

// Auto-format registration number
const regInput = document.getElementById('vehicle_registration');
if (regInput) {
    regInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
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

function toggleSidebar() {
    document.body.classList.toggle("sidebar-collapsed");
}

// Check if URL has #password hash and open password tab
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash === '#password') {
        showTab('password');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>