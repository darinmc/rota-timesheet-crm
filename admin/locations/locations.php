<?php
// /sts-app/admin/locations/locations.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

// Handle toast messages
$toast = $_SESSION['toast'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast'], $_SESSION['toast_type']);

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

// Handle delete request
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM locations WHERE location_id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['toast'] = "🗑️ Location deleted successfully!";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: locations.php" . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
        exit;
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error deleting location: " . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    try {
        $id = intval($_GET['id']);
        $stmt = $db->prepare("SELECT is_active FROM locations WHERE location_id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_status = $current['is_active'] ? 0 : 1;
        $update = $db->prepare("UPDATE locations SET is_active = ? WHERE location_id = ?");
        $update->execute([$new_status, $id]);
        
        $_SESSION['toast'] = $new_status ? "✅ Location activated!" : "⏸️ Location deactivated!";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: locations.php" . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
        exit;
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error toggling status: " . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
}

// Get locations with search and filter
try {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? 'all';
    
    $query = "SELECT location_id as id, name, short_name, address, postcode, notes, contact_name, contact_phone, contact_email, is_active FROM locations WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (name LIKE ? OR short_name LIKE ? OR address LIKE ? OR postcode LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ? OR notes LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
    }
    
    if ($status_filter === 'active') {
        $query .= " AND is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND is_active = 0";
    }
    
    $query .= " ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error loading locations: " . $e->getMessage();
    $locations = [];
}

// Get stats
try {
    $stmt = $db->query("SELECT COUNT(*) FROM locations WHERE is_active = 1");
    $active_locations = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM locations WHERE is_active = 0");
    $inactive_locations = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM locations");
    $total_locations = $stmt->fetchColumn();
} catch (Exception $e) {
    $active_locations = 0;
    $inactive_locations = 0;
    $total_locations = 0;
}

// Get all locations for search dropdown (for autocomplete)
try {
    $all_locations_stmt = $db->query("SELECT location_id, name, short_name, postcode FROM locations ORDER BY name");
    $all_locations = $all_locations_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_locations = [];
}
?>

<style>
/* Location Manager Specific Styles */
.location-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.stat-card h4 {
    margin: 0 0 10px 0;
    color: #64748b;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2c3e50;
}

.stat-number.active {
    color: #10b981;
}

.stat-number.inactive {
    color: #ef4444;
}

/* Search Filter Bar */
.search-filter-bar {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.search-group {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #475569;
    font-size: 13px;
}

.search-input-wrapper {
    position: relative;
    width: 100%;
}

.search-input-modern {
    width: 100%;
    padding: 10px 30px 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
    background: white;
}

.search-input-modern:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.search-clear-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 18px;
    display: none;
    padding: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
}

.search-clear-btn:hover {
    color: #ef4444;
    background: #f1f5f9;
}

/* Dropdown results */
.search-dropdown-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 6px 6px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.dropdown-result-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dropdown-result-item:hover,
.dropdown-result-item.highlighted {
    background: #f1f5f9;
}

.result-name {
    font-weight: 500;
    color: #1e293b;
}

.result-detail {
    font-size: 12px;
    color: #64748b;
}

.result-postcode {
    font-size: 11px;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 12px;
}

.status-group {
    min-width: 150px;
}

.btn-primary {
    background: #10b981;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-primary:hover {
    background: #059669;
}

.btn-secondary {
    background: #64748b;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 6px;
    display: inline-block;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-secondary:hover {
    background: #475569;
}

.btn-success {
    background: #10b981;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 6px;
    display: inline-block;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-success:hover {
    background: #059669;
}

.action-buttons-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

/* Location Cards */
.location-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.location-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.2s;
    position: relative;
}

.location-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.location-card.inactive {
    opacity: 0.7;
    background: #f8fafc;
}

.card-header {
    padding: 15px 20px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header.inactive {
    background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
}

.location-name {
    font-size: 18px;
    font-weight: bold;
    margin: 0;
}

.status-badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 20px;
    font-weight: 600;
    background: rgba(255,255,255,0.2);
}

.card-body {
    padding: 15px 20px;
}

.info-row {
    display: flex;
    margin-bottom: 10px;
    font-size: 14px;
}

.info-label {
    width: 100px;
    font-weight: 600;
    color: #64748b;
}

.info-value {
    flex: 1;
    color: #1e293b;
}

.shortcode-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    background: #e2e8f0;
    color: #475569;
}

.contact-details {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
}

.contact-details .info-row {
    margin-bottom: 6px;
}

.card-actions {
    padding: 12px 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 10px;
    justify-content: space-between;
}

.card-actions .btn-sm {
    padding: 6px 12px;
    font-size: 13px;
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.2s;
}

.btn-edit {
    background: #3b82f6;
    color: white;
}

.btn-edit:hover {
    background: #2563eb;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

.btn-toggle {
    background: #64748b;
    color: white;
}

.btn-toggle:hover {
    background: #475569;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 10px;
    margin-top: 20px;
}

.empty-state h3 {
    color: #64748b;
    margin-bottom: 10px;
}

.empty-state p {
    color: #94a3b8;
    margin-bottom: 20px;
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

/* Responsive */
@media (max-width: 768px) {
    .location-grid {
        grid-template-columns: 1fr;
    }
    
    .search-form {
        flex-direction: column;
    }
    
    .search-group {
        width: 100%;
    }
    
    .status-group {
        width: 100%;
    }
    
    .action-buttons-bar {
        flex-direction: column;
    }
    
    .btn-primary, .btn-secondary, .btn-success {
        text-align: center;
    }
}
</style>

<div class="main-content">
    <div style="padding: 20px;">
        
        <!-- Display any PHP errors for debugging -->
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Toast Messages -->
        <?php if ($toast): ?>
        <div class="toast-container">
            <div class="toast-message toast-<?= $toast_type ?>">
                <span><?= htmlspecialchars($toast) ?></span>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div style="margin-bottom: 25px;">
            <h1 style="margin: 0 0 5px 0; color: #1e293b;">📍 Location Manager</h1>
            <p style="color: #64748b; margin: 0;">Manage your locations - add, edit, and control their status</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="location-stats">
            <div class="stat-card">
                <h4>Total Locations</h4>
                <div class="stat-number"><?= $total_locations ?></div>
            </div>
            <div class="stat-card">
                <h4>Active Locations</h4>
                <div class="stat-number active"><?= $active_locations ?></div>
            </div>
            <div class="stat-card">
                <h4>Inactive Locations</h4>
                <div class="stat-number inactive"><?= $inactive_locations ?></div>
            </div>
        </div>
        
        <!-- Search and Filter Bar with Enhanced Dropdown -->
        <div class="search-filter-bar">
            <form method="GET" id="searchForm" class="search-form">
                <div class="search-group">
                    <label for="locationSearch">🔍 Search Locations</label>
                    <div class="search-input-wrapper">
                        <input type="text" 
                               id="locationSearch" 
                               class="search-input-modern"
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Type to search locations..."
                               autocomplete="off">
                        <button type="button" id="clearSearchBtn" class="search-clear-btn" onclick="clearSearch()">×</button>
                        <input type="hidden" name="search" id="hiddenSearch" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="status" id="hiddenStatus" value="<?= htmlspecialchars($status_filter) ?>">
                        <div id="searchResults" class="search-dropdown-results"></div>
                    </div>
                </div>
                <div class="search-group status-group">
                    <label for="statusFilter">📊 Status Filter</label>
                    <select id="statusFilter" class="search-input-modern">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Locations</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <?php if ($search || $status_filter !== 'all'): ?>
                        <a href="locations.php" class="btn-secondary" style="margin-left: 10px;">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons-bar">
            <a href="add_location.php" class="btn-success">➕ Add New Location</a>
            <a href="../dashboard.php" class="btn-secondary">← Back to Dashboard</a>
        </div>
        
        <!-- Locations Grid -->
        <?php if (count($locations) > 0): ?>
            <div class="location-grid">
                <?php foreach ($locations as $location): ?>
                    <div class="location-card <?= !$location['is_active'] ? 'inactive' : '' ?>">
                        <div class="card-header <?= !$location['is_active'] ? 'inactive' : '' ?>">
                            <div class="location-name">
                                <?= htmlspecialchars($location['name']) ?>
                            </div>
                            <div class="status-badge">
                                <?= $location['is_active'] ? '✓ Active' : '⏸ Inactive' ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($location['short_name'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Short Name:</div>
                                    <div class="info-value">
                                        <span class="shortcode-badge"><?= htmlspecialchars($location['short_name']) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($location['address'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Address:</div>
                                    <div class="info-value"><?= htmlspecialchars($location['address']) ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($location['postcode'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Postcode:</div>
                                    <div class="info-value"><?= htmlspecialchars($location['postcode']) ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Contact Details Section -->
                            <?php if (!empty($location['contact_name']) || !empty($location['contact_phone']) || !empty($location['contact_email'])): ?>
                                <div class="contact-details">
                                    <div class="info-row" style="margin-bottom: 8px;">
                                        <div class="info-label" style="font-weight: 700;">📞 Contact:</div>
                                        <div class="info-value"></div>
                                    </div>
                                    <?php if (!empty($location['contact_name'])): ?>
                                        <div class="info-row">
                                            <div class="info-label">Name:</div>
                                            <div class="info-value"><?= htmlspecialchars($location['contact_name']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($location['contact_phone'])): ?>
                                        <div class="info-row">
                                            <div class="info-label">Phone:</div>
                                            <div class="info-value"><?= htmlspecialchars($location['contact_phone']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($location['contact_email'])): ?>
                                        <div class="info-row">
                                            <div class="info-label">Email:</div>
                                            <div class="info-value"><?= htmlspecialchars($location['contact_email']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($location['notes'])): ?>
                                <div class="info-row" style="margin-top: 8px;">
                                    <div class="info-label">Notes:</div>
                                    <div class="info-value">
                                        <small style="color: #64748b;"><?= htmlspecialchars(substr($location['notes'], 0, 100)) ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-actions">
                            <a href="edit_location.php?id=<?= $location['id'] ?>" class="btn-sm btn-edit">✏️ Edit</a>
                            <a href="?toggle_status=1&id=<?= $location['id'] ?>&<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>status=<?= $status_filter ?>" 
                               class="btn-sm btn-toggle"
                               onclick="return confirm('<?= $location['is_active'] ? 'Deactivate' : 'Activate' ?> this location?')">
                                <?= $location['is_active'] ? '⏸ Deactivate' : '▶ Activate' ?>
                            </a>
                            <a href="?delete_id=<?= $location['id'] ?>&<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>status=<?= $status_filter ?>" 
                               class="btn-sm btn-delete"
                               onclick="return confirm('Delete this location permanently? This cannot be undone.')">
                                🗑️ Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>📍 No Locations Found</h3>
                <p>No locations match your search criteria.</p>
                <a href="add_location.php" class="btn-success" style="display: inline-block;">➕ Add Your First Location</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Location data for search dropdown
const allLocations = <?= json_encode($all_locations) ?>;

// DOM Elements
const searchInput = document.getElementById('locationSearch');
const hiddenSearch = document.getElementById('hiddenSearch');
const searchResults = document.getElementById('searchResults');
const clearBtn = document.getElementById('clearSearchBtn');
const statusFilter = document.getElementById('statusFilter');
const searchForm = document.getElementById('searchForm');

let selectedIndex = -1;
let currentResults = [];

// Function to filter and show results
function filterLocations() {
    const query = searchInput.value.toLowerCase().trim();
    let filtered = [];
    
    if (query === '') {
        filtered = allLocations.slice(0, 10); // Show first 10 when empty
    } else {
        filtered = allLocations.filter(l => {
            const name = (l.name || '').toLowerCase();
            const shortName = (l.short_name || '').toLowerCase();
            const postcode = (l.postcode || '').toLowerCase();
            return name.includes(query) || shortName.includes(query) || postcode.includes(query);
        }).slice(0, 15); // Limit to 15 results
    }
    
    currentResults = filtered;
    selectedIndex = -1;
    
    if (filtered.length === 0) {
        searchResults.innerHTML = '<div class="dropdown-result-item" style="color: #94a3b8;">No locations found</div>';
    } else {
        searchResults.innerHTML = filtered.map(l => `
            <div class="dropdown-result-item" data-id="${l.location_id}" data-name="${escapeHtml(l.name)}">
                <div>
                    <div class="result-name">${escapeHtml(l.name)}</div>
                    ${l.short_name ? `<div class="result-detail">${escapeHtml(l.short_name)}</div>` : ''}
                </div>
                ${l.postcode ? `<div class="result-postcode">${escapeHtml(l.postcode)}</div>` : ''}
            </div>
        `).join('');
    }
    
    // Add click handlers to results
    document.querySelectorAll('.dropdown-result-item').forEach((item, index) => {
        item.addEventListener('click', () => selectLocation(item.dataset.id, item.dataset.name));
        item.addEventListener('mouseenter', () => {
            document.querySelectorAll('.dropdown-result-item').forEach(i => i.classList.remove('highlighted'));
            item.classList.add('highlighted');
            selectedIndex = index;
        });
    });
}

// Function to select a location from dropdown
function selectLocation(id, name) {
    searchInput.value = name;
    hiddenSearch.value = name;
    searchResults.style.display = 'none';
    clearBtn.style.display = name ? 'flex' : 'none';
    // Submit the form to search
    searchForm.submit();
}

// Function to clear search
function clearSearch() {
    searchInput.value = '';
    hiddenSearch.value = '';
    clearBtn.style.display = 'none';
    searchResults.style.display = 'none';
    searchForm.submit();
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Handle keyboard navigation
function handleKeyNav(e) {
    const results = document.querySelectorAll('.dropdown-result-item');
    if (results.length === 0) return;
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = (selectedIndex + 1) % results.length;
        updateHighlight(results);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = (selectedIndex - 1 + results.length) % results.length;
        updateHighlight(results);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex >= 0 && currentResults[selectedIndex]) {
            const result = currentResults[selectedIndex];
            selectLocation(result.location_id, result.name);
        } else if (searchInput.value.trim()) {
            // If no selection, search with current text
            hiddenSearch.value = searchInput.value.trim();
            searchForm.submit();
        }
    } else if (e.key === 'Escape') {
        searchResults.style.display = 'none';
        selectedIndex = -1;
    }
}

function updateHighlight(results) {
    results.forEach((r, i) => {
        if (i === selectedIndex) {
            r.classList.add('highlighted');
            r.scrollIntoView({ block: 'nearest' });
        } else {
            r.classList.remove('highlighted');
        }
    });
}

// Event Listeners
searchInput.addEventListener('input', function() {
    filterLocations();
    searchResults.style.display = 'block';
    clearBtn.style.display = this.value ? 'flex' : 'none';
});

searchInput.addEventListener('focus', function() {
    filterLocations();
    searchResults.style.display = 'block';
});

searchInput.addEventListener('keydown', handleKeyNav);

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
    }
});

// Handle status filter change
statusFilter.addEventListener('change', function() {
    const hiddenStatus = document.getElementById('hiddenStatus');
    hiddenStatus.value = this.value;
    searchForm.submit();
});

// Handle form submission - update hidden fields before submit
searchForm.addEventListener('submit', function(e) {
    const hiddenSearchField = document.getElementById('hiddenSearch');
    const hiddenStatusField = document.getElementById('hiddenStatus');
    
    hiddenSearchField.value = searchInput.value.trim();
    hiddenStatusField.value = statusFilter.value;
});

// Initialize clear button visibility
if (searchInput.value) {
    clearBtn.style.display = 'flex';
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