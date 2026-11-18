<?php
// /sts-app/admin/passengers/add_passenger.php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $display_name = trim($_POST['display_name'] ?? $full_name);
        $boss_shortcode = trim($_POST['boss_shortcode'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $passenger_type = $_POST['passenger_type'] ?? 'regular';
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($full_name)) {
            throw new Exception("Passenger name is required");
        }
        
        $stmt = $db->prepare("
            INSERT INTO passengers (full_name, display_name, boss_shortcode, phone, email, address, postcode, passenger_type, emergency_contact_name, emergency_contact_phone, notes, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$full_name, $display_name, $boss_shortcode, $phone, $email, $address, $postcode, $passenger_type, $emergency_contact_name, $emergency_contact_phone, $notes, $is_active]);
        
        $_SESSION['toast'] = "✅ Passenger added successfully!";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: passengers.php");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<style>
.form-container {
    max-width: 800px;
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
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-control:disabled, .form-control[readonly] {
    background-color: #f1f5f9;
    cursor: default;
}

/* Make readonly fields not focusable with tab */
.form-control[readonly] {
    pointer-events: none;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-group input {
    width: auto;
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

.auto-gen-hint {
    font-size: 11px;
    color: #64748b;
    margin-top: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.regenerate-btn {
    background: none;
    border: none;
    color: #3b82f6;
    cursor: pointer;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
}

.regenerate-btn:hover {
    background: #eff6ff;
    text-decoration: underline;
}

.info-badge {
    background: #e2e8f0;
    color: #475569;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: 8px;
}
</style>

<div class="main-content">
    <div style="padding: 20px;">
        <div style="margin-bottom: 25px;">
            <h1 style="margin: 0 0 5px 0; color: #1e293b;">➕ Add New Passenger</h1>
            <p style="color: #64748b; margin: 0;">Create a new passenger record</p>
        </div>
        
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="passengerForm">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    <div class="auto-gen-hint">
                        <span>📝 Enter full name (e.g., John Charles Michael Carlton)</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="display_name">Display Name <span class="info-badge">Auto-generated</span></label>
                    <input type="text" class="form-control" id="display_name" name="display_name" readonly tabindex="-1" value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
                    <div class="auto-gen-hint">
                        <span>🔍 Auto-generated from full name (first + last name + postcode)</span>
                        <button type="button" class="regenerate-btn" onclick="generateDisplayName()" tabindex="-1">⟳ Regenerate</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="boss_shortcode">Boss Shortcode <span class="info-badge">Auto-generated</span></label>
                    <input type="text" class="form-control" id="boss_shortcode" name="boss_shortcode" readonly tabindex="-1" value="<?= htmlspecialchars($_POST['boss_shortcode'] ?? '') ?>">
                    <div class="auto-gen-hint">
                        <span>🔍 Auto-generated from display name initials + postcode</span>
                        <button type="button" class="regenerate-btn" onclick="generateShortcode()" tabindex="-1">⟳ Regenerate</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="postcode">Postcode</label>
                    <input type="text" class="form-control" id="postcode" name="postcode" value="<?= htmlspecialchars($_POST['postcode'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="passenger_type">Passenger Type</label>
                    <select class="form-control" id="passenger_type" name="passenger_type">
                        <option value="regular" <?= ($_POST['passenger_type'] ?? 'regular') == 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="wheelchair" <?= ($_POST['passenger_type'] ?? '') == 'wheelchair' ? 'selected' : '' ?>>Wheelchair</option>
                        <option value="corporate" <?= ($_POST['passenger_type'] ?? '') == 'corporate' ? 'selected' : '' ?>>Corporate</option>
                        <option value="vip" <?= ($_POST['passenger_type'] ?? '') == 'vip' ? 'selected' : '' ?>>VIP</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="emergency_contact_name">Emergency Contact Name</label>
                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?= (!isset($_POST['is_active']) || $_POST['is_active'] == '1') ? 'checked' : '' ?>>
                        <label for="is_active" style="margin-bottom: 0;">Active (available for scheduling)</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">💾 Save Passenger</button>
                    <a href="passengers.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to generate display name from full name (first + last name + postcode)
function generateDisplayName() {
    const fullName = document.getElementById('full_name').value.trim();
    const postcode = document.getElementById('postcode').value.trim();
    
    if (!fullName) {
        document.getElementById('display_name').value = '';
        return;
    }
    
    // Split name into parts
    const nameParts = fullName.split(/\s+/).filter(part => part.length > 0);
    
    let displayName = '';
    
    if (nameParts.length === 1) {
        // Only one name part
        displayName = nameParts[0];
    } else {
        // Get first name and last name only
        const firstName = nameParts[0];
        const lastName = nameParts[nameParts.length - 1];
        displayName = firstName + ' ' + lastName;
    }
    
    // Add postcode if it exists
    if (postcode) {
        displayName += ' ' + postcode;
    }
    
    // Update the display_name field
    document.getElementById('display_name').value = displayName;
    
    // After updating display name, update the boss shortcode
    generateShortcode();
}

// Function to generate boss shortcode from display name initials + postcode
function generateShortcode() {
    // Get the display name
    let displayName = document.getElementById('display_name').value.trim();
    
    // If display name is empty, use full name as fallback
    if (!displayName) {
        const fullName = document.getElementById('full_name').value.trim();
        const postcode = document.getElementById('postcode').value.trim();
        
        if (fullName) {
            const nameParts = fullName.split(/\s+/).filter(part => part.length > 0);
            let shortName = '';
            if (nameParts.length === 1) {
                shortName = nameParts[0];
            } else {
                shortName = nameParts[0] + ' ' + nameParts[nameParts.length - 1];
            }
            if (postcode) {
                shortName += ' ' + postcode;
            }
            displayName = shortName;
        }
    }
    
    const postcode = document.getElementById('postcode').value.trim();
    
    if (!displayName) {
        document.getElementById('boss_shortcode').value = '';
        return;
    }
    
    // Extract just the name part (without postcode) for initials
    let nameForInitials = displayName;
    if (postcode && displayName.includes(postcode)) {
        nameForInitials = displayName.replace(postcode, '').trim();
    }
    
    // Split name into parts
    const nameParts = nameForInitials.split(/\s+/).filter(part => part.length > 0);
    
    // Get first letter of first name part
    const firstInitial = nameParts[0] ? nameParts[0].charAt(0).toUpperCase() : '';
    
    // Get first letter of last name part (last part of the name)
    const lastInitial = nameParts[nameParts.length - 1] ? 
                        nameParts[nameParts.length - 1].charAt(0).toUpperCase() : '';
    
    // Build shortcode
    let shortcode = firstInitial;
    if (lastInitial && lastInitial !== firstInitial) {
        shortcode += ' ' + lastInitial;
    } else if (lastInitial && nameParts.length > 1) {
        shortcode += ' ' + lastInitial;
    }
    
    // Add postcode if it exists
    if (postcode) {
        shortcode += ' ' + postcode;
    }
    
    // Update the boss_shortcode field
    document.getElementById('boss_shortcode').value = shortcode;
}

// Auto-generate when full name changes
document.getElementById('full_name').addEventListener('blur', function() {
    generateDisplayName();
});

// Auto-generate when postcode changes
document.getElementById('postcode').addEventListener('blur', function() {
    generateDisplayName();
});

// Also generate on input for real-time updates
document.getElementById('full_name').addEventListener('input', function() {
    clearTimeout(window.displayNameTimeout);
    window.displayNameTimeout = setTimeout(generateDisplayName, 500);
});

document.getElementById('postcode').addEventListener('input', function() {
    clearTimeout(window.displayNameTimeout);
    window.displayNameTimeout = setTimeout(generateDisplayName, 500);
});

// Generate initial values if fields have values
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('full_name').value || document.getElementById('postcode').value) {
        generateDisplayName();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>