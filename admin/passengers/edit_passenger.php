<?php
// /sts-app/admin/passengers/edit_passenger.php
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

// Toast messages
$toast = $_SESSION['toast'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast'], $_SESSION['toast_type']);

// Get passenger ID
$passenger_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$passenger_id) {
    header('Location: passengers.php');
    exit;
}

// Fetch passenger data
try {
    $stmt = $db->prepare("SELECT * FROM passengers WHERE passenger_id = ?");
    $stmt->execute([$passenger_id]);
    $passenger = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$passenger) {
        $_SESSION['toast'] = "Passenger not found";
        $_SESSION['toast_type'] = 'error';
        header('Location: passengers.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['toast'] = "Error loading passenger: " . $e->getMessage();
    $_SESSION['toast_type'] = 'error';
    header('Location: passengers.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_passenger'])) {
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
            UPDATE passengers 
            SET full_name = ?, 
                display_name = ?, 
                boss_shortcode = ?,
                phone = ?, 
                email = ?, 
                address = ?, 
                postcode = ?, 
                passenger_type = ?, 
                emergency_contact_name = ?, 
                emergency_contact_phone = ?, 
                notes = ?, 
                is_active = ?
            WHERE passenger_id = ?
        ");
        
        $stmt->execute([
            $full_name, 
            $display_name, 
            $boss_shortcode,
            $phone, 
            $email, 
            $address, 
            $postcode, 
            $passenger_type, 
            $emergency_contact_name, 
            $emergency_contact_phone, 
            $notes, 
            $is_active,
            $passenger_id
        ]);
        
        $_SESSION['toast'] = "✅ Passenger updated successfully!";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: edit_passenger.php?id=" . $passenger_id);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch weekly schedule for this passenger
try {
    $scheduleStmt = $db->prepare("
        SELECT s.*, l.name AS destination_name, l.short_name 
        FROM passenger_regular_schedules s
        LEFT JOIN locations l ON s.destination_id = l.location_id
        WHERE s.passenger_id = ? AND s.is_active = 1
        ORDER BY 
            FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
            FIELD(s.session, 'AM', 'PM', 'EVE', 'EVE2'),
            s.pickup_time
    ");
    $scheduleStmt->execute([$passenger_id]);
    $scheduleItems = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $scheduleItems = [];
}
?>

<style>
/* Two-column layout for edit page */
.edit-layout {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.edit-form-column {
    flex: 1;
    min-width: 350px;
}

.edit-schedule-column {
    flex: 1;
    min-width: 400px;
    position: relative;
}

/* Vertical divider line */
.edit-schedule-column::before {
    content: '';
    position: absolute;
    left: -15px;
    top: 0;
    bottom: 0;
    width: 1px;
    background: linear-gradient(to bottom, #e2e8f0, #cbd5e1, #e2e8f0);
}

/* Responsive - stack on mobile */
@media (max-width: 900px) {
    .edit-layout {
        flex-direction: column;
    }
    
    .edit-schedule-column::before {
        display: none;
    }
    
    .edit-schedule-column {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 2px solid #e2e8f0;
    }
}

/* Form Container */
.form-container {
    max-width: 100%;
    background: white;
    padding: 25px;
    border-radius: 12px;
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
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-control[readonly] {
    background-color: #f1f5f9;
    cursor: default;
    pointer-events: none;
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
    border-radius: 8px;
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
    border-radius: 8px;
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
    border-radius: 8px;
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

/* Schedule Card */
.schedule-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.schedule-stats {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.stat-pill {
    background: #f1f5f9;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    color: #475569;
}

.stat-pill.am {
    background: #dbeafe;
    color: #1e40af;
}

.stat-pill.pm {
    background: #fef3c7;
    color: #92400e;
}

.stat-pill.eve {
    background: #ede9fe;
    color: #5b21b6;
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.schedule-table th {
    background: #f8fafc;
    padding: 10px 8px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}

.schedule-table td {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.day-separator td {
    background: #f1f5f9;
    padding: 6px 10px;
    font-weight: 600;
    font-size: 12px;
    color: #334155;
}

.session-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.session-am {
    background: #dbeafe;
    color: #1e40af;
}

.session-pm {
    background: #fef3c7;
    color: #92400e;
}

.session-eve {
    background: #ede9fe;
    color: #5b21b6;
}

.wc-badge {
    background: #fee2e2;
    color: #dc2626;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 700;
}

.freq-badge {
    background: #f3e8ff;
    color: #7c3aed;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
}

.empty-schedule {
    text-align: center;
    padding: 40px;
    color: #64748b;
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
    
    .schedule-table {
        font-size: 11px;
    }
    
    .schedule-table th, .schedule-table td {
        padding: 6px 4px;
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
        
        <div class="edit-layout">
            
            <!-- LEFT COLUMN: Edit Form -->
            <div class="edit-form-column">
                <div style="margin-bottom: 25px;">
                    <h1 style="margin: 0 0 5px 0; color: #1e293b;">✏️ Edit Passenger</h1>
                    <p style="color: #64748b; margin: 0;">Update passenger information</p>
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
                            <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($passenger['full_name']) ?>">
                            <div class="auto-gen-hint">
                                <span>📝 Enter full name (e.g., John Charles Michael Carlton)</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="display_name">Display Name <span class="info-badge">Auto-generated</span></label>
                            <input type="text" class="form-control" id="display_name" name="display_name" readonly tabindex="-1" value="<?= htmlspecialchars($passenger['display_name'] ?? '') ?>">
                            <div class="auto-gen-hint">
                                <span>🔍 Auto-generated from full name (first + last name + postcode)</span>
                                <button type="button" class="regenerate-btn" onclick="generateDisplayName()" tabindex="-1">⟳ Regenerate</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="boss_shortcode">Boss Shortcode <span class="info-badge">Auto-generated</span></label>
                            <input type="text" class="form-control" id="boss_shortcode" name="boss_shortcode" readonly tabindex="-1" value="<?= htmlspecialchars($passenger['boss_shortcode'] ?? '') ?>">
                            <div class="auto-gen-hint">
                                <span>🔍 Auto-generated from display name initials + postcode</span>
                                <button type="button" class="regenerate-btn" onclick="generateShortcode()" tabindex="-1">⟳ Regenerate</button>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($passenger['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($passenger['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($passenger['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="postcode">Postcode</label>
                                <input type="text" class="form-control" id="postcode" name="postcode" value="<?= htmlspecialchars($passenger['postcode'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="passenger_type">Passenger Type</label>
                                <select class="form-control" id="passenger_type" name="passenger_type">
                                    <option value="regular" <?= ($passenger['passenger_type'] ?? 'regular') == 'regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="wheelchair" <?= ($passenger['passenger_type'] ?? '') == 'wheelchair' ? 'selected' : '' ?>>Wheelchair</option>
                                    <option value="corporate" <?= ($passenger['passenger_type'] ?? '') == 'corporate' ? 'selected' : '' ?>>Corporate</option>
                                    <option value="vip" <?= ($passenger['passenger_type'] ?? '') == 'vip' ? 'selected' : '' ?>>VIP</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($passenger['emergency_contact_name'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= htmlspecialchars($passenger['emergency_contact_phone'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($passenger['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" value="1" <?= $passenger['is_active'] ? 'checked' : '' ?>>
                                <label for="is_active" style="margin-bottom: 0;">Active (available for scheduling)</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_passenger" class="btn-save">💾 Save Changes</button>
                            <a href="passengers.php" class="btn-cancel">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- RIGHT COLUMN: Weekly Schedule -->
            <div class="edit-schedule-column">
                <div class="schedule-card">
                    <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #1e293b;">
                        📅 Weekly Schedule for <?= htmlspecialchars($passenger['full_name']) ?>
                    </h3>
                    
                    <?php if (empty($scheduleItems)): ?>
                        <div class="empty-schedule">
                            <p>No regular schedule set for this passenger.</p>
                            <a href="schedule/index.php?passenger_id=<?= $passenger_id ?>" 
                               style="display: inline-block; margin-top: 10px; background: #10b981; color: white; padding: 8px 16px; 
                                      text-decoration: none; border-radius: 6px; font-size: 13px;">
                                + Add Schedule
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Summary Stats -->
                        <div class="schedule-stats">
                            <div class="stat-pill">📋 Total: <?= count($scheduleItems) ?></div>
                            <div class="stat-pill am">☀️ AM: <?= count(array_filter($scheduleItems, fn($i) => $i['session'] == 'AM')) ?></div>
                            <div class="stat-pill pm">🌙 PM: <?= count(array_filter($scheduleItems, fn($i) => $i['session'] == 'PM')) ?></div>
                            <div class="stat-pill eve">⭐ EVE: <?= count(array_filter($scheduleItems, fn($i) => $i['session'] == 'EVE')) ?></div>
                        </div>
                        
                        <!-- Schedule Table -->
                        <div style="overflow-x: auto;">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Session</th>
                                        <th>Time</th>
                                        <th>Destination</th>
                                        <th style="width: 40px;">WC</th>
                                        <th style="width: 50px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $currentDay = '';
                                    foreach ($scheduleItems as $item): 
                                        $isNewDay = $currentDay !== $item['day_of_week'];
                                        $currentDay = $item['day_of_week'];
                                    ?>
                                        <?php if ($isNewDay): ?>
                                            <tr class="day-separator">
                                                <td colspan="6"><strong><?= $item['day_of_week'] ?></strong></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td style="font-size: 12px; color: #64748b;">&nbsp;</td>
                                            <td>
                                                <span class="session-badge session-<?= strtolower($item['session']) ?>">
                                                    <?= $item['session'] ?>
                                                </span>
                                            </td>
                                            <td style="font-family: monospace;"><?= date('H:i', strtotime($item['pickup_time'])) ?></td>
                                            <td>
                                                <?= htmlspecialchars($item['destination_name'] ?? 'Unknown') ?>
                                                <?php if (!empty($item['short_name'])): ?>
                                                    <small style="color: #94a3b8;">(<?= htmlspecialchars($item['short_name']) ?>)</small>
                                                <?php endif; ?>
                                                <?php if ($item['frequency_note'] && $item['frequency_note'] !== 'Weekly'): ?>
                                                    <span class="freq-badge" style="margin-left: 6px;"><?= htmlspecialchars($item['frequency_note']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($item['requires_wheelchair']): ?>
                                                    <span class="wc-badge">WC</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <a href="schedule/index.php?passenger_id=<?= $passenger_id ?>&edit_id=<?= $item['id'] ?>" 
                                                   style="color: #3b82f6; text-decoration: none; margin-right: 5px;" title="Edit">✏️</a>
                                                <a href="schedule/index.php?passenger_id=<?= $passenger_id ?>&delete_id=<?= $item['id'] ?>" 
                                                   onclick="return confirm('Delete this schedule entry?')" 
                                                   style="color: #ef4444; text-decoration: none;" title="Delete">🗑️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <a href="schedule/index.php?passenger_id=<?= $passenger_id ?>" 
                               style="background: #10b981; color: white; padding: 10px 20px; text-decoration: none; 
                                      border-radius: 6px; display: inline-block; font-weight: 500; font-size: 13px;">
                                📋 Edit Full Weekly Schedule
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
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

// Generate initial values if needed
document.addEventListener('DOMContentLoaded', function() {
    const currentDisplayName = document.getElementById('display_name').value.trim();
    const currentFullName = document.getElementById('full_name').value.trim();
    const currentPostcode = document.getElementById('postcode').value.trim();
    
    let expectedDisplayName = '';
    if (currentFullName) {
        const nameParts = currentFullName.split(/\s+/).filter(part => part.length > 0);
        if (nameParts.length === 1) {
            expectedDisplayName = nameParts[0];
        } else {
            expectedDisplayName = nameParts[0] + ' ' + nameParts[nameParts.length - 1];
        }
        if (currentPostcode) {
            expectedDisplayName += ' ' + currentPostcode;
        }
    }
    
    if (currentDisplayName !== expectedDisplayName && currentFullName) {
        generateDisplayName();
    } else if (currentFullName) {
        generateShortcode();
    }
});

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