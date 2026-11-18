<?php
// /sts-app/admin/rota/generate.php
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

// Get week start date (Monday)
$week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('monday this week'));
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($week_start)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start)));

// Navigation
$prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));

// Get filter values
$filter_passenger = $_GET['filter_passenger'] ?? '';
$filter_destination = $_GET['filter_destination'] ?? '';
$filter_day = $_GET['filter_day'] ?? '';
$filter_session = $_GET['filter_session'] ?? '';
$filter_assignment = $_GET['filter_assignment'] ?? '';
$filter_driver = $_GET['filter_driver'] ?? '';

// Handle Bulk Cancel (Sick Day)
if (isset($_POST['bulk_cancel'])) {
    $cancel_driver_id = $_POST['cancel_driver_id'] ?? 0;
    $cancel_day = $_POST['cancel_day'] ?? '';
    $cancel_reason = $_POST['cancel_reason'] ?? 'Driver unavailable';
    
    if ($cancel_driver_id) {
        $query = "UPDATE rota_jobs SET status = 'cancelled', notes = CONCAT(notes, ' [CANCELLED: ', ?, ']') WHERE driver_id = ?";
        $params = [$cancel_reason, $cancel_driver_id];
        
        if ($cancel_day) {
            $query .= " AND day_of_week = ?";
            $params[] = $cancel_day;
        }
        
        $query .= " AND week_start = ?";
        $params[] = $week_start;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        
        $_SESSION['toast'] = "✅ Cancelled $count jobs for " . ($cancel_day ? "$cancel_day " : "week");
        $_SESSION['toast_type'] = 'success';
        header("Location: generate.php?week=" . $week_start);
        exit;
    }
}

// Handle Copy from Previous Week
if (isset($_POST['copy_from_previous'])) {
    $prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
    
    try {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM rota_jobs WHERE week_start = ?");
        $checkStmt->execute([$week_start]);
        $existing = $checkStmt->fetchColumn();
        
        if ($existing > 0) {
            $_SESSION['toast'] = "⚠️ Rota already exists for this week. Please delete existing rota first.";
            $_SESSION['toast_type'] = 'warning';
        } else {
            $copyStmt = $db->prepare("
                INSERT INTO rota_jobs (schedule_id, passenger_id, destination_id, driver_id, day_of_week, pickup_time, session, week_start, status, notes)
                SELECT schedule_id, passenger_id, destination_id, driver_id, day_of_week, pickup_time, session, ?, status, notes
                FROM rota_jobs
                WHERE week_start = ?
            ");
            $copyStmt->execute([$week_start, $prev_week]);
            $copiedCount = $copyStmt->rowCount();
            
            $_SESSION['toast'] = "✅ Copied $copiedCount jobs from previous week! Driver assignments preserved.";
            $_SESSION['toast_type'] = 'success';
        }
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error copying: " . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header("Location: generate.php?week=" . $week_start);
    exit;
}

// Handle Generate Rota (with password verification)
if (isset($_POST['generate_rota'])) {
    // Verify admin password
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_id = $_SESSION['user_id'];
    
    $pwdStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $pwdStmt->execute([$admin_id]);
    $admin = $pwdStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin || !password_verify($admin_password, $admin['password_hash'])) {
        $_SESSION['toast'] = "❌ Incorrect password. Regenerate cancelled.";
        $_SESSION['toast_type'] = 'error';
        header("Location: generate.php?week=" . $week_start);
        exit;
    }
    
    try {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM rota_jobs WHERE week_start = ?");
        $checkStmt->execute([$week_start]);
        $existing = $checkStmt->fetchColumn();
        
        if ($existing > 0 && !isset($_POST['overwrite'])) {
            $_SESSION['toast'] = "⚠️ Rota already exists for this week. Check 'Overwrite' to regenerate.";
            $_SESSION['toast_type'] = 'warning';
        } else {
            if ($existing > 0 && isset($_POST['overwrite'])) {
                $deleteStmt = $db->prepare("DELETE FROM rota_jobs WHERE week_start = ?");
                $deleteStmt->execute([$week_start]);
            }
            
            $scheduleStmt = $db->prepare("
                SELECT s.*, p.full_name as passenger_name, p.display_name, p.notes as passenger_notes,
                       l.name as destination_name, l.short_name
                FROM passenger_regular_schedules s
                LEFT JOIN passengers p ON s.passenger_id = p.passenger_id
                LEFT JOIN locations l ON s.destination_id = l.location_id
                WHERE s.is_active = 1 AND s.include_in_rota = 1
                ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                         FIELD(s.session, 'AM', 'PM', 'EVE', 'EVE2'),
                         s.pickup_time
            ");
            $scheduleStmt->execute();
            $schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $insertStmt = $db->prepare("
                INSERT INTO rota_jobs 
                (schedule_id, passenger_id, destination_id, day_of_week, pickup_time, session, week_start, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
            ");
            
            foreach ($schedules as $schedule) {
                $notes = $schedule['notes'] ?? '';
                if ($schedule['passenger_notes']) {
                    $notes .= ($notes ? ' | ' : '') . 'Passenger: ' . $schedule['passenger_notes'];
                }
                $insertStmt->execute([
                    $schedule['id'],
                    $schedule['passenger_id'],
                    $schedule['destination_id'],
                    $schedule['day_of_week'],
                    $schedule['pickup_time'],
                    $schedule['session'],
                    $week_start,
                    $notes
                ]);
            }
            
            $_SESSION['toast'] = "✅ Generated jobs for week starting " . date('j M Y', strtotime($week_start));
            $_SESSION['toast_type'] = 'success';
        }
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error generating rota: " . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header("Location: generate.php?week=" . $week_start);
    exit;
}

// Build query with filters
$query = "
    SELECT r.*, 
           p.full_name as passenger_name, p.display_name, p.phone as passenger_phone,
           l.name as destination_name, l.short_name, l.address,
           u.first_name, u.last_name, u.username
    FROM rota_jobs r
    LEFT JOIN passengers p ON r.passenger_id = p.passenger_id
    LEFT JOIN locations l ON r.destination_id = l.location_id
    LEFT JOIN users u ON r.driver_id = u.id
    WHERE r.week_start = ?
";
$params = [$week_start];

if ($filter_passenger) {
    $query .= " AND (p.full_name LIKE ? OR p.display_name LIKE ?)";
    $params[] = "%$filter_passenger%";
    $params[] = "%$filter_passenger%";
}
if ($filter_destination) {
    $query .= " AND (l.name LIKE ? OR l.short_name LIKE ?)";
    $params[] = "%$filter_destination%";
    $params[] = "%$filter_destination%";
}
if ($filter_day) {
    $query .= " AND r.day_of_week = ?";
    $params[] = $filter_day;
}
if ($filter_session) {
    $query .= " AND r.session = ?";
    $params[] = $filter_session;
}
if ($filter_assignment === 'assigned') {
    $query .= " AND r.driver_id IS NOT NULL";
} elseif ($filter_assignment === 'unassigned') {
    $query .= " AND r.driver_id IS NULL";
}
if ($filter_driver) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$filter_driver%";
    $params[] = "%$filter_driver%";
    $params[] = "%$filter_driver%";
}

$query .= " ORDER BY FIELD(r.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                  FIELD(r.session, 'AM', 'PM', 'EVE', 'EVE2'),
                  r.pickup_time";

$rotaStmt = $db->prepare($query);
$rotaStmt->execute($params);
$rotaJobs = $rotaStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active drivers for dropdown
$driversStmt = $db->query("
    SELECT id, first_name, last_name, username 
    FROM users 
    WHERE role = 'driver' AND status = 'active' 
    ORDER BY first_name, last_name
");
$drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$sessions = ['AM', 'PM', 'EVE', 'EVE2'];

// Group jobs by day for display
$jobsByDay = [];
foreach ($days as $day) {
    $jobsByDay[$day] = [];
}
foreach ($rotaJobs as $job) {
    $jobsByDay[$job['day_of_week']][] = $job;
}
?>

<style>
.rota-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}
.week-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.week-nav-btn {
    background: #3b82f6;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
}
.week-nav-btn:hover {
    background: #2563eb;
}
.week-title {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}
.filter-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}
.filter-title {
    font-weight: 600;
    margin-bottom: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}
.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.filter-group label {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
}
.filter-input {
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    min-width: 140px;
}
.filter-input:focus {
    outline: none;
    border-color: #3b82f6;
}
.btn-filter {
    background: #3b82f6;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
}
.btn-filter:hover {
    background: #2563eb;
}
.btn-clear {
    background: #64748b;
}
.btn-clear:hover {
    background: #475569;
}
.bulk-section {
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}
.bulk-title {
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #92400e;
}
.bulk-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.btn-bulk {
    background: #ef4444;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
}
.btn-bulk:hover {
    background: #dc2626;
}
.rota-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    font-size: 12px;
}
.rota-table th {
    background: #e2e8f0;
    padding: 8px 6px;
    text-align: left;
    font-weight: 700;
    font-size: 11px;
    color: #1e293b;
    border-bottom: 2px solid #cbd5e1;
    border-right: 1px solid #cbd5e1;
}
.rota-table th:last-child {
    border-right: none;
}
.rota-table td {
    padding: 6px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    border-right: 1px solid #e2e8f0;
}
.rota-table td:last-child {
    border-right: none;
}
.day-header td {
    background: #f1f5f9;
    padding: 6px 8px;
    font-weight: 700;
    font-size: 12px;
    color: #1e293b;
    border-bottom: 2px solid #cbd5e1;
    border-top: 1px solid #cbd5e1;
}
.session-divider td {
    background: #f8fafc;
    padding: 4px 8px;
    font-size: 10px;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #94a3b8;
    border-top: 1px solid #e2e8f0;
}
.rota-table tbody tr:not(.day-header):not(.session-divider):hover {
    background: #f8fafc;
}
.passenger-cell {
    font-size: 12px;
    line-height: 1.3;
}
.passenger-name {
    font-weight: 600;
    color: #1e293b;
}
.destination-name {
    font-size: 10px;
    color: #64748b;
}
.time-cell {
    font-family: monospace;
    font-weight: 600;
    color: #3b82f6;
    white-space: nowrap;
    font-size: 11px;
}
.driver-wrapper {
    display: flex;
    gap: 6px;
    align-items: center;
}
.driver-search-wrapper {
    position: relative;
    flex: 1;
    min-width: 140px;
}
.driver-search-input {
    width: 100%;
    padding: 4px 22px 4px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 11px;
    background: white;
}
.driver-search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
}
.driver-clear-btn {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 12px;
    color: #94a3b8;
    display: none;
    padding: 0;
    width: 16px;
    height: 16px;
}
.save-driver-btn {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 10px;
    white-space: nowrap;
}
.save-driver-btn:hover {
    background: #2563eb;
}
.status-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    min-width: 65px;
}
.status-scheduled { background: #dbeafe; color: #1e40af; }
.status-assigned { background: #d1fae5; color: #065f46; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.status-select {
    padding: 4px 4px;
    font-size: 10px;
    border-radius: 4px;
    width: 100%;
    border: 1px solid #cbd5e1;
    background: white;
}
.notes-cell {
    font-size: 10px;
    color: #f59e0b;
    max-width: 120px;
    word-wrap: break-word;
    line-height: 1.3;
}
.generate-section {
    background: #f0fdf4;
    border: 2px solid #86efac;
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
}
.generate-title {
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}
.generate-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}
.generate-card {
    flex: 1;
    padding: 15px;
    border-radius: 8px;
}
.generate-card h4 {
    margin: 0 0 10px 0;
}
.generate-card p {
    font-size: 12px;
    margin-bottom: 10px;
}
.btn-copy {
    background: #0284c7;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.btn-copy:hover {
    background: #0369a1;
}
.btn-regenerate {
    background: #ea580c;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.btn-regenerate:hover {
    background: #c2410c;
}
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}
.empty-state {
    text-align: center;
    padding: 40px;
    color: #64748b;
}
.toast-container {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10000;
    background: #10b981;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { transform: translate(-50%, -20px); opacity: 0; }
    to { transform: translate(-50%, 0); opacity: 1; }
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10001;
    align-items: center;
    justify-content: center;
}
</style>

<div class="main-content">
    <div class="rota-container">
        
        <?php if ($toast): ?>
        <div class="toast-container"><?= htmlspecialchars($toast) ?></div>
        <?php endif; ?>
        
        <div style="margin-bottom: 20px;">
            <h1 style="margin: 0 0 5px 0;">📅 Rota Generator</h1>
            <p style="color: #64748b;">Generate weekly rota from regular passenger schedules</p>
        </div>
        
        <!-- Week Navigation -->
        <div class="week-navigation">
            <a href="?week=<?= $prev_week ?>" class="week-nav-btn">← Previous Week</a>
            <div class="week-title">
                Week of <?= date('l, jS F Y', strtotime($week_start)) ?> - <?= date('jS F Y', strtotime($week_end)) ?>
            </div>
            <a href="?week=<?= $next_week ?>" class="week-nav-btn">Next Week →</a>
        </div>
        
        <!-- Filter Section (Default Closed) -->
        <div class="filter-section">
            <div class="filter-title" onclick="toggleFilters()">
                🔍 Filters <span id="filterToggleIcon">▼</span>
            </div>
            <div id="filterContent" style="display: block;">
                <form method="GET" class="filter-row">
                    <input type="hidden" name="week" value="<?= $week_start ?>">
                    <div class="filter-group">
                        <label>Passenger</label>
                        <input type="text" name="filter_passenger" class="filter-input" placeholder="Search passenger..." value="<?= htmlspecialchars($filter_passenger) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Destination</label>
                        <input type="text" name="filter_destination" class="filter-input" placeholder="Search destination..." value="<?= htmlspecialchars($filter_destination) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Day</label>
                        <select name="filter_day" class="filter-input">
                            <option value="">All Days</option>
                            <?php foreach ($days as $day): ?>
                                <option value="<?= $day ?>" <?= $filter_day == $day ? 'selected' : '' ?>><?= $day ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Session</label>
                        <select name="filter_session" class="filter-input">
                            <option value="">All Sessions</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session ?>" <?= $filter_session == $session ? 'selected' : '' ?>><?= $session ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Assignment</label>
                        <select name="filter_assignment" class="filter-input">
                            <option value="">All</option>
                            <option value="assigned" <?= $filter_assignment == 'assigned' ? 'selected' : '' ?>>Assigned</option>
                            <option value="unassigned" <?= $filter_assignment == 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Driver</label>
                        <input type="text" name="filter_driver" class="filter-input" placeholder="Search driver..." value="<?= htmlspecialchars($filter_driver) ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn-filter">Apply Filters</button>
                        <a href="?week=<?= $week_start ?>" class="btn-filter btn-clear" style="text-decoration:none;">Clear</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Bulk Cancel Section (Default Closed) -->
        <div class="bulk-section">
            <div class="bulk-title" onclick="toggleBulk()">
                ⚠️ Bulk Actions <span id="bulkToggleIcon">▶</span>
            </div>
            <div id="bulkContent" style="display: none;">
                <form method="POST" class="bulk-row" onsubmit="return confirm('Are you sure you want to cancel these jobs? This cannot be undone.')">
                    <div class="filter-group">
                        <label>Driver (Sick Day)</label>
                        <select name="cancel_driver_id" class="filter-input" required>
                            <option value="">-- Select Driver --</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>For Day (leave blank for whole week)</label>
                        <select name="cancel_day" class="filter-input">
                            <option value="">Entire Week</option>
                            <?php foreach ($days as $day): ?>
                                <option value="<?= $day ?>"><?= $day ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Reason</label>
                        <input type="text" name="cancel_reason" class="filter-input" placeholder="e.g., Sick day" value="Driver unavailable">
                    </div>
                    <div>
                        <button type="submit" name="bulk_cancel" class="btn-bulk">🚫 Cancel Jobs</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Weekly Rota Title -->
        <h2 style="font-size: 18px; margin: 20px 0 15px 0;">📋 Weekly Rota</h2>
        
        <?php if (empty($rotaJobs)): ?>
            <div class="empty-state">
                <p>📭 No rota generated for this week yet.</p>
                <p style="font-size: 13px;">Use the options below to generate or copy a rota.</p>
            </div>
        <?php else: ?>
            <table class="rota-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Passenger & Destination</th>
                        <th style="width: 12%;">Time</th>
                        <th style="width: 22%;">Driver</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 24%;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentDay = '';
                    $currentSession = '';
                    foreach ($days as $day): 
                        $dayJobs = $jobsByDay[$day];
                        if (empty($dayJobs)) continue;
                    ?>
                        <tr class="day-header">
                            <td colspan="5"><strong><?= $day ?></strong> (<?= count($dayJobs) ?> jobs)</td>
                        </tr>
                        
                        <?php foreach ($dayJobs as $index => $job): 
                            $showSessionDivider = ($currentSession !== $job['session'] && $index > 0);
                            $currentSession = $job['session'];
                        ?>
                            <?php if ($showSessionDivider): ?>
                                <tr class="session-divider">
                                    <td colspan="5">
                                        <strong><?= $job['session'] ?> Session</strong> — 
                                        <?= $job['session'] == 'AM' ? 'Morning (before 12:00)' : ($job['session'] == 'PM' ? 'Afternoon (12:00-17:00)' : ($job['session'] == 'EVE' ? 'Evening (after 17:00)' : 'Late Evening')) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr data-job-id="<?= $job['id'] ?>">
                                <td class="passenger-cell">
                                    <div class="passenger-name">👤 <?= htmlspecialchars($job['display_name'] ?? $job['passenger_name']) ?></div>
                                    <div class="destination-name">📍 <?= htmlspecialchars($job['short_name'] ?? $job['destination_name'] ?? 'Unknown') ?></div>
                                    <?php if (!empty($job['passenger_phone'])): ?>
                                        <div class="destination-name" style="font-size: 10px; color: #10b981;">📞 <?= htmlspecialchars($job['passenger_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="time-cell"><?= date('H:i', strtotime($job['pickup_time'])) ?> (<?= $job['session'] ?>)</td>
                                <td style="min-width: 180px;">
                                    <div class="driver-wrapper">
                                        <div class="driver-search-wrapper">
                                            <input type="text" class="driver-search-input" id="driverSearch_<?= $job['id'] ?>"
                                                   value="<?= htmlspecialchars($job['first_name'] ? $job['first_name'] . ' ' . $job['last_name'] : '') ?>"
                                                   placeholder="🔍 Search driver..." autocomplete="off"
                                                   data-id="<?= $job['id'] ?>">
                                            <button type="button" class="driver-clear-btn" id="clearDriver_<?= $job['id'] ?>">×</button>
                                            <input type="hidden" id="driverId_<?= $job['id'] ?>" value="<?= $job['driver_id'] ?>">
                                            <div class="driver-search-results" id="driverResults_<?= $job['id'] ?>"></div>
                                        </div>
                                        <button class="save-driver-btn" data-id="<?= $job['id'] ?>">💾 Save</button>
                                    </div>
                                </td>
                                <td>
                                    <select class="status-select" data-id="<?= $job['id'] ?>">
                                        <option value="scheduled" <?= $job['status'] == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                        <option value="assigned" <?= $job['status'] == 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                        <option value="completed" <?= $job['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $job['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </td>
                                <td class="notes-cell"><?= htmlspecialchars(substr($job['notes'] ?? '', 0, 50)) ?><?= strlen($job['notes'] ?? '') > 50 ? '...' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Generate / Copy Rota Section (Default Closed) -->
        <div class="generate-section">
            <div class="generate-title" onclick="toggleGenerate()">
                🔄 Generate / Copy Rota <span id="generateToggleIcon">▶</span>
            </div>
            <div id="generateContent" style="display: none;">
                <div class="generate-buttons">
                    <!-- Copy from Previous Week -->
                    <div class="generate-card" style="background: #e0f2fe;">
                        <h4 style="margin: 0 0 10px 0; color: #0369a1;">📋 Copy from Previous Week</h4>
                        <p style="font-size: 12px; margin-bottom: 10px; color: #0c4a6e;">
                            Copy all jobs from the previous week.<br>
                            ✅ Driver assignments will be preserved.<br>
                            ✅ Dates will be updated to this week.
                        </p>
                        <form method="POST" onsubmit="return confirm('Copy all jobs from previous week to this week? Driver assignments will be preserved.')">
                            <input type="hidden" name="copy_from_previous" value="1">
                            <button type="submit" class="btn-copy">📋 Copy from Previous Week</button>
                        </form>
                    </div>
                    
                    <!-- Regenerate from Schedules -->
                    <div class="generate-card" style="background: #fef3c7;">
                        <h4 style="margin: 0 0 10px 0; color: #b45309;">⚠️ Regenerate from Schedules</h4>
                        <p style="font-size: 12px; margin-bottom: 10px; color: #92400e;">
                            Regenerate jobs from regular schedules.<br>
                            ❌ ALL driver assignments will be LOST!<br>
                            ❌ Manual adjustments will be DELETED!
                        </p>
                        <button type="button" class="btn-regenerate" onclick="showPasswordModal()">🔒 Regenerate (Password Required)</button>
                    </div>
                </div>
                
                <?php if (!empty($rotaJobs)): ?>
                <div class="checkbox-group">
                    <input type="checkbox" name="overwrite" id="overwrite" value="1" form="regenerateForm">
                    <label for="overwrite">⚠️ Confirm: I understand this will delete existing jobs and remove all driver assignments</label>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- Password Modal -->
<div id="passwordModal" class="modal">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%;">
        <h3 style="margin: 0 0 15px 0;">🔐 Admin Password Required</h3>
        <p style="margin-bottom: 15px; color: #dc2626; font-size: 14px;">
            ⚠️ WARNING: This will delete all jobs for this week and regenerate from regular schedules.<br>
            <strong>ALL driver assignments will be LOST!</strong>
        </p>
        <form method="POST" id="regenerateForm">
            <input type="hidden" name="generate_rota" value="1">
            <div style="margin-bottom: 15px;">
                <label for="admin_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Enter your password to confirm:</label>
                <input type="password" name="admin_password" id="admin_password" class="filter-input" style="width: 100%;" required autofocus>
            </div>
            <?php if (!empty($rotaJobs)): ?>
            <div class="checkbox-group" style="margin-bottom: 15px;">
                <input type="checkbox" name="overwrite" id="modal_overwrite" value="1">
                <label for="modal_overwrite">I understand this will delete existing jobs and remove all driver assignments</label>
            </div>
            <?php endif; ?>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closePasswordModal()" class="btn-clear" style="background: #64748b; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn-regenerate" style="background: #dc2626; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;">Confirm Regenerate</button>
            </div>
        </form>
    </div>
</div>

<script>
// Driver data
const drivers = <?= json_encode($drivers); ?>;

// ===== SEARCHABLE DRIVER DROPDOWN =====
function initDriverSearch(jobId) {
    const input = document.getElementById('driverSearch_' + jobId);
    const hidden = document.getElementById('driverId_' + jobId);
    const results = document.getElementById('driverResults_' + jobId);
    const clearBtn = document.getElementById('clearDriver_' + jobId);
    
    if (!input) return;
    
    function toggleClearBtn() {
        if (clearBtn) {
            clearBtn.style.display = input.value ? 'flex' : 'none';
        }
    }
    
    function hideResults() {
        results.style.display = 'none';
    }
    
    function showResults() {
        const query = input.value.toLowerCase().trim();
        let html = '';
        let matchCount = 0;
        
        drivers.forEach(driver => {
            const fullName = (driver.first_name + ' ' + driver.last_name).toLowerCase();
            const username = (driver.username || '').toLowerCase();
            
            if (query === '' || fullName.includes(query) || username.includes(query)) {
                const highlightClass = (matchCount === 0) ? 'highlighted' : '';
                html += `<div class="driver-search-result ${highlightClass}" data-id="${driver.id}" data-name="${escapeHtml(driver.first_name + ' ' + driver.last_name)}">
                    <strong>${escapeHtml(driver.first_name + ' ' + driver.last_name)}</strong>
                    <small style="color:#64748b;"> (@${escapeHtml(driver.username)})</small>
                </div>`;
                matchCount++;
            }
        });
        
        if (matchCount === 0) {
            html = '<div class="driver-search-result" style="color:#94a3b8;">No drivers found</div>';
        }
        
        results.innerHTML = html;
        results.style.display = 'block';
    }
    
    // X button: clear, save, close dropdown, lose focus
    if (clearBtn) {
        clearBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            hidden.value = '';
            hideResults();
            toggleClearBtn();
            saveDriver(jobId);
            input.blur();
        };
    }
    
    input.onfocus = function() {
        showResults();
        this.select();
        toggleClearBtn();
    };
    
    input.oninput = function() {
        showResults();
        toggleClearBtn();
    };
    
    input.onblur = function() {
        setTimeout(() => {
            hideResults();
            if (hidden.value) {
                const selectedDriver = drivers.find(d => d.id == hidden.value);
                if (selectedDriver) {
                    input.value = selectedDriver.first_name + ' ' + selectedDriver.last_name;
                }
            } else {
                input.value = '';
            }
            saveDriver(jobId);
        }, 200);
    };
    
    input.onkeydown = function(e) {
        const items = results.querySelectorAll('.driver-search-result');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length === 0) {
                showResults();
                return;
            }
            let highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.nextSibling) {
                highlighted.classList.remove('highlighted');
                highlighted.nextSibling.classList.add('highlighted');
                highlighted.nextSibling.scrollIntoView({ block: 'nearest' });
            } else if (!highlighted && items[0]) {
                items[0].classList.add('highlighted');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length === 0) return;
            let highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.previousSibling) {
                highlighted.classList.remove('highlighted');
                highlighted.previousSibling.classList.add('highlighted');
                highlighted.previousSibling.scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            let highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.dataset.id) {
                input.value = highlighted.dataset.name;
                hidden.value = highlighted.dataset.id;
                hideResults();
                toggleClearBtn();
                saveDriver(jobId);
                const nextSelect = input.closest('tr').querySelector('.status-select');
                if (nextSelect) {
                    nextSelect.focus();
                }
            } else {
                if (input.value.trim() === '') {
                    hidden.value = '';
                    saveDriver(jobId);
                }
                hideResults();
                const nextSelect = input.closest('tr').querySelector('.status-select');
                if (nextSelect) {
                    nextSelect.focus();
                } else {
                    input.blur();
                }
            }
        } else if (e.key === 'Escape') {
            hideResults();
            input.blur();
        }
    };
    
    results.onclick = function(e) {
        const item = e.target.closest('.driver-search-result');
        if (item && item.dataset.id) {
            input.value = item.dataset.name;
            hidden.value = item.dataset.id;
            hideResults();
            toggleClearBtn();
            saveDriver(jobId);
            const nextSelect = input.closest('tr').querySelector('.status-select');
            if (nextSelect) {
                nextSelect.focus();
            }
        }
    };
    
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            hideResults();
            if (hidden.value) {
                const selectedDriver = drivers.find(d => d.id == hidden.value);
                if (selectedDriver) {
                    input.value = selectedDriver.first_name + ' ' + selectedDriver.last_name;
                }
            }
        }
    });
    
    toggleClearBtn();
}

// ===== AUTO-SAVE FUNCTIONS =====
async function saveDriver(jobId) {
    const driverId = document.getElementById('driverId_' + jobId).value;
    const button = document.querySelector(`.save-driver-btn[data-id="${jobId}"]`);
    const originalText = button.textContent;
    
    button.textContent = 'Saving...';
    button.disabled = true;
    
    try {
        const response = await fetch('ajax/assign-driver.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `job_id=${jobId}&driver_id=${driverId || ''}`
        });
        const data = await response.json();
        if (data.success) {
            button.textContent = '✓ Saved';
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 1500);
        } else {
            button.textContent = 'Failed';
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 1500);
        }
    } catch (error) {
        button.textContent = 'Error';
        setTimeout(() => {
            button.textContent = originalText;
            button.disabled = false;
        }, 1500);
    }
}

async function saveStatus(jobId, status) {
    try {
        const response = await fetch('ajax/update-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `job_id=${jobId}&status=${status}`
        });
        const data = await response.json();
        if (data.success) {
            const statusSpan = document.querySelector(`tr[data-job-id="${jobId}"] .status-badge`);
            if (statusSpan) {
                statusSpan.className = `status-badge status-${status}`;
                statusSpan.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
        }
    } catch (error) {
        console.error('Error saving status:', error);
    }
}

// ===== INITIALIZE =====
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($rotaJobs as $job): ?>
    initDriverSearch(<?= $job['id'] ?>);
    <?php endforeach; ?>
    
    document.querySelectorAll('.save-driver-btn').forEach(button => {
        button.addEventListener('click', function() {
            const jobId = this.dataset.id;
            saveDriver(jobId);
        });
    });
    
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            const jobId = this.dataset.id;
            const status = this.value;
            saveStatus(jobId, status);
        });
    });
});

function toggleFilters() {
    const content = document.getElementById('filterContent');
    const icon = document.getElementById('filterToggleIcon');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
    } else {
        content.style.display = 'none';
        icon.textContent = '▶';
    }
}

function toggleBulk() {
    const content = document.getElementById('bulkContent');
    const icon = document.getElementById('bulkToggleIcon');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
    } else {
        content.style.display = 'none';
        icon.textContent = '▶';
    }
}

function toggleGenerate() {
    const content = document.getElementById('generateContent');
    const icon = document.getElementById('generateToggleIcon');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
    } else {
        content.style.display = 'none';
        icon.textContent = '▶';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showPasswordModal() {
    document.getElementById('passwordModal').style.display = 'flex';
    document.getElementById('admin_password').focus();
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('admin_password').value = '';
    const modalCheckbox = document.getElementById('modal_overwrite');
    if (modalCheckbox) modalCheckbox.checked = false;
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('passwordModal');
    if (e.target === modal) {
        closePasswordModal();
    }
});

setTimeout(() => {
    const toast = document.querySelector('.toast-container');
    if (toast) {
        toast.style.opacity = '0';
        setTimeout(() => toast?.remove(), 500);
    }
}, 4000);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>