<?php
// /sts-app/admin/locations/edit_location.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get location ID
$location_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$location_id) {
    header('Location: locations.php');
    exit;
}

// Fetch location data
try {
    $stmt = $db->prepare("SELECT * FROM locations WHERE location_id = ?");
    $stmt->execute([$location_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$location) {
        $_SESSION['toast'] = "Location not found";
        $_SESSION['toast_type'] = 'error';
        header('Location: locations.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['toast'] = "Error loading location: " . $e->getMessage();
    $_SESSION['toast_type'] = 'error';
    header('Location: locations.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $short_name = trim($_POST['short_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            throw new Exception("Location name is required");
        }
        
        // Auto-generate short_name if empty
        if (empty($short_name)) {
            $nameParts = explode(' ', $name);
            $shortName = '';
            
            if (count($nameParts) === 1) {
                $shortName = substr($nameParts[0], 0, 15);
            } else {
                $shortName = $nameParts[0] . ' ' . end($nameParts);
                if (strlen($shortName) > 30) {
                    $shortName = substr($shortName, 0, 27) . '...';
                }
            }
            
            if ($postcode) {
                $shortName .= ' ' . $postcode;
            }
            
            $short_name = $shortName;
        }
        
        $stmt = $db->prepare("
            UPDATE locations 
            SET name = ?, 
                short_name = ?, 
                address = ?, 
                postcode = ?,
                notes = ?,
                contact_name = ?,
                contact_phone = ?,
                contact_email = ?,
                is_active = ?
            WHERE location_id = ?
        ");
        
        $stmt->execute([$name, $short_name, $address, $postcode, $notes, $contact_name, $contact_phone, $contact_email, $is_active, $location_id]);
        
        $_SESSION['toast'] = "✅ Location updated successfully!";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: locations.php");
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
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.form-control:disabled, .form-control[readonly] {
    background-color: #f1f5f9;
    cursor: default;
}

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
    color: #10b981;
    cursor: pointer;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
}

.regenerate-btn:hover {
    background: #d1fae5;
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

hr {
    margin: 20px 0;
    border: none;
    border-top: 1px solid #e2e8f0;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #10b981;
    display: inline-block;
}
</style>

<div class="main-content">
    <div style="padding: 20px;">
        <div style="margin-bottom: 25px;">
            <h1 style="margin: 0 0 5px 0; color: #1e293b;">✏️ Edit Location</h1>
            <p style="color: #64748b; margin: 0;">Update location information</p>
        </div>
        
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="locationForm">
                <!-- Basic Information Section -->
                <div class="section-title">📍 Basic Information</div>
                
                <div class="form-group">
                    <label for="name">Location Name *</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($location['name']) ?>">
                    <div class="auto-gen-hint">
                        <span>📝 Enter location name (e.g., City Limits, Sheffield)</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="short_name">Short Name <span class="info-badge">Auto-generated</span></label>
                    <input type="text" class="form-control" id="short_name" name="short_name" readonly tabindex="-1" value="<?= htmlspecialchars($location['short_name'] ?? '') ?>">
                    <div class="auto-gen-hint">
                        <span>🔍 Auto-generated from name + postcode</span>
                        <button type="button" class="regenerate-btn" onclick="generateShortName()" tabindex="-1">⟳ Regenerate</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($location['address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="postcode">Postcode</label>
                    <input type="text" class="form-control" id="postcode" name="postcode" value="<?= htmlspecialchars($location['postcode'] ?? '') ?>">
                </div>
                
                <hr>
                
                <!-- Contact Information Section -->
                <div class="section-title">📞 Contact Information</div>
                
                <div class="form-group">
                    <label for="contact_name">Contact Name</label>
                    <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= htmlspecialchars($location['contact_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($location['contact_phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= htmlspecialchars($location['contact_email'] ?? '') ?>">
                </div>
                
                <hr>
                
                <!-- Additional Information Section -->
                <div class="section-title">📝 Additional Information</div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($location['notes'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $location['is_active'] ? 'checked' : '' ?>>
                        <label for="is_active" style="margin-bottom: 0;">Active (available for scheduling)</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-save">💾 Save Changes</button>
                    <a href="locations.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to generate short name from name and postcode
function generateShortName() {
    const name = document.getElementById('name').value.trim();
    const postcode = document.getElementById('postcode').value.trim();
    
    if (!name) {
        document.getElementById('short_name').value = '';
        return;
    }
    
    // Split name into parts
    const nameParts = name.split(/\s+/).filter(part => part.length > 0);
    
    let shortName = '';
    
    if (nameParts.length === 1) {
        // Only one name part - take first 15 characters
        shortName = nameParts[0].substring(0, 15);
        if (nameParts[0].length > 15) {
            shortName += '...';
        }
    } else {
        // Take first word and last word
        const firstWord = nameParts[0];
        const lastWord = nameParts[nameParts.length - 1];
        shortName = firstWord + ' ' + lastWord;
        
        // Trim if too long
        if (shortName.length > 30) {
            shortName = shortName.substring(0, 27) + '...';
        }
    }
    
    // Add postcode if it exists
    if (postcode) {
        shortName += ' ' + postcode;
    }
    
    // Update the short_name field
    document.getElementById('short_name').value = shortName;
}

// Auto-generate when name changes
document.getElementById('name').addEventListener('blur', function() {
    generateShortName();
});

// Auto-generate when postcode changes
document.getElementById('postcode').addEventListener('blur', function() {
    generateShortName();
});

// Also generate on input for real-time updates
document.getElementById('name').addEventListener('input', function() {
    clearTimeout(window.shortNameTimeout);
    window.shortNameTimeout = setTimeout(generateShortName, 500);
});

document.getElementById('postcode').addEventListener('input', function() {
    clearTimeout(window.shortNameTimeout);
    window.shortNameTimeout = setTimeout(generateShortName, 500);
});

// Generate short name if fields have values
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('name').value) {
        generateShortName();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>