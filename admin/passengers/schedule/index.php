<?php
// /sts-app/admin/passengers/schedule/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth-check.php';
require_once __DIR__ . '/../../../includes/header.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['toast'] = "❌ Access denied. Admin only.";
    $_SESSION['toast_type'] = 'error';
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit();
}

// Include sidebar
include __DIR__ . '/../../../includes/sidebar.php';

// Toast messages
$toast = $_SESSION['toast'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast'], $_SESSION['toast_type']);

$passenger_id = isset($_GET['passenger_id']) ? intval($_GET['passenger_id']) : 0;
$error_message = '';

if ($passenger_id === 0) {
    $error_message = "No passenger ID provided.";
} else {
    $passengerStmt = $db->prepare("SELECT * FROM passengers WHERE passenger_id = ?");
    $passengerStmt->execute([$passenger_id]);
    $passenger = $passengerStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$passenger) {
        $error_message = "Passenger not found.";
    }
}

if ($error_message) {
    echo '<div class="main-content"><div style="padding:20px;">';
    echo '<div style="background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 8px; border-left: 4px solid #dc2626;">';
    echo '<strong>❌ ' . htmlspecialchars($error_message) . '</strong>';
    echo '</div>';
    echo '<div style="margin-top: 20px;"><a href="../passengers.php" class="btn-back">← Back to Passengers</a></div>';
    echo '</div></div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Fetch destinations
$destinationsStmt = $db->query("SELECT location_id, name, short_name FROM locations WHERE is_active = 1 ORDER BY name");
$destinations = $destinationsStmt->fetchAll(PDO::FETCH_ASSOC);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$sessions = ['AM', 'PM', 'EVE', 'EVE2'];

// Handle DELETE
if (isset($_GET['delete_id'])) {
    try {
        $deleteStmt = $db->prepare("DELETE FROM passenger_regular_schedules WHERE id = ? AND passenger_id = ?");
        $deleteStmt->execute([$_GET['delete_id'], $passenger_id]);
        $_SESSION['toast'] = "🗑️ Schedule entry deleted.";
        $_SESSION['toast_type'] = 'info';
        session_write_close();
        header("Location: index.php?passenger_id=$passenger_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error deleting.";
        $_SESSION['toast_type'] = 'error';
        session_write_close();
        header("Location: index.php?passenger_id=$passenger_id");
        exit;
    }
}

// Handle DUPLICATE
if (isset($_GET['duplicate_id'])) {
    try {
        $dupStmt = $db->prepare("
            INSERT INTO passenger_regular_schedules 
            (passenger_id, day_of_week, session, pickup_time, destination_id, notes, requires_wheelchair, frequency_note, include_in_rota)
            SELECT passenger_id, day_of_week, session, pickup_time, destination_id, notes, requires_wheelchair, frequency_note, include_in_rota
            FROM passenger_regular_schedules WHERE id = ? AND passenger_id = ?
        ");
        $dupStmt->execute([$_GET['duplicate_id'], $passenger_id]);
        $_SESSION['toast'] = "📋 Entry duplicated.";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: index.php?passenger_id=$passenger_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error duplicating.";
        $_SESSION['toast_type'] = 'error';
        session_write_close();
        header("Location: index.php?passenger_id=$passenger_id");
        exit;
    }
}

// Handle AJAX inline update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    try {
        $field = $_POST['field'];
        $value = $_POST['value'];
        $entry_id = $_POST['entry_id'];
        
        $allowedFields = ['day_of_week', 'session', 'pickup_time', 'destination_id', 'notes', 'requires_wheelchair', 'frequency_note', 'include_in_rota'];
        
        if (!in_array($field, $allowedFields)) {
            throw new Exception("Invalid field");
        }
        
        $updateStmt = $db->prepare("
            UPDATE passenger_regular_schedules 
            SET $field = ?, updated_at = NOW()
            WHERE id = ? AND passenger_id = ?
        ");
        $updateStmt->execute([$value, $entry_id, $passenger_id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle ADD NEW ENTRY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    try {
        $insertStmt = $db->prepare("
            INSERT INTO passenger_regular_schedules 
            (passenger_id, day_of_week, session, pickup_time, destination_id, notes, requires_wheelchair, frequency_note, include_in_rota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertStmt->execute([
            $passenger_id,
            $_POST['day_of_week'],
            $_POST['session'],
            $_POST['pickup_time'],
            $_POST['destination_id'],
            $_POST['notes'] ?? '',
            isset($_POST['requires_wheelchair']) ? 1 : 0,
            $_POST['frequency_note'] ?? 'Weekly',
            0
        ]);
        
        $_SESSION['toast'] = "✅ Schedule entry added!";
        $_SESSION['toast_type'] = 'success';
        session_write_close();
        header("Location: index.php?passenger_id=$passenger_id");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['toast'] = "❌ Error adding entry.";
        $_SESSION['toast_type'] = 'error';
        session_write_close();
        header("Location: index.php?passenger_id=$passenger_id");
        exit;
    }
}

// Fetch existing schedule entries
$scheduleStmt = $db->prepare("
    SELECT s.*, l.name AS destination_name, l.short_name
    FROM passenger_regular_schedules s
    LEFT JOIN locations l ON s.destination_id = l.location_id
    WHERE s.passenger_id = ? AND s.is_active = 1
    ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
             FIELD(s.session, 'AM', 'PM', 'EVE', 'EVE2'),
             s.pickup_time
");
$scheduleStmt->execute([$passenger_id]);
$scheduleItems = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
* { box-sizing: border-box; }
.schedule-container { max-width: 100%; margin: 0; padding: 4px 100px; overflow-x: auto; }
.breadcrumb { font-size: 12px; margin-bottom: 8px; color: #64748b; }
.breadcrumb a { color: #3b82f6; text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }
.page-header { margin-bottom: 8px; }
.page-header h1 { font-size: 22px; margin: 0 0 5px 0; }
.entry-count { background: #e2e8f0; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
.schedule-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; font-size: 12px; }
.schedule-table th { background: #f1f5f9; padding: 8px 6px; text-align: left; font-size: 11px; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
.schedule-table td { padding: 6px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.schedule-table tr.updated { background: #d1fae5; transition: background 0.3s; }
.day-separator td { background: #e2e8f0 !important; padding: 6px 10px !important; font-weight: 700 !important; font-size: 12px !important; color: #1e293b !important; border-bottom: 1px solid #cbd5e1 !important; }
.inline-select, .inline-input { padding: 5px 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px; background: white; }
.inline-select { cursor: pointer; }
.inline-input:focus, .inline-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
.day-select { width: 75px; }
.time-input { width: 85px; }
.notes-input { width: 180px; }
.dest-search-wrapper { position: relative; min-width: 260px; }
.dest-search-input { width: 100%; padding: 5px 25px 5px 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px; background: white; }
.dest-search-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
.search-clear-btn { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; color: #94a3b8; display: none; padding: 0; width: 16px; height: 16px; border-radius: 50%; align-items: center; justify-content: center; }
.search-clear-btn:hover { color: #ef4444; background: #f1f5f9; }
.dest-search-results { display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #cbd5e1; border-radius: 4px; max-height: 180px; overflow-y: auto; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.dest-search-result { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; transition: background 0.15s; }
.dest-search-result:hover { background: #e2e8f0; }
.dest-search-result.highlighted { background: #e2e8f0; }
.dest-search-result .dest-name { font-weight: 500; }
.dest-search-result .dest-short { font-size: 10px; color: #64748b; margin-left: 5px; }
.wc-checkbox, .rota-checkbox { width: 16px; height: 16px; cursor: pointer; margin: 0; }
.action-btn { padding: 3px 6px; border-radius: 4px; font-size: 11px; text-decoration: none; display: inline-block; margin-right: 3px; }
.btn-duplicate { background: #d1fae5; color: #047857; }
.btn-delete { background: #fee2e2; color: #dc2626; }
.add-section { background: #f0fdf4; border: 2px solid #86efac; border-radius: 10px; padding: 15px; margin-top: 10px; }
.add-section h3 { margin: 0 0 12px 0; font-size: 15px; color: #166534; }
.add-form-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-bottom: 10px; }
.form-group { display: flex; flex-direction: column; gap: 3px; }
.form-group label { font-size: 10px; font-weight: 600; color: #475569; text-transform: uppercase; }
.form-group input, .form-group select { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 12px; background: white; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.2); }
.checkbox-group { display: flex; align-items: center; gap: 6px; padding: 5px 0; }
.checkbox-group label { margin: 0; font-size: 12px; color: #dc2626; }
.btn-add { background: #22c55e; color: white; padding: 7px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 600; }
.btn-add:hover { background: #16a34a; }
.notes-group { flex: 2; min-width: 200px; }
.notes-group input { width: 100%; }
.btn-group { display: flex; align-items: flex-end; }
.btn-back { background: #64748b; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 12px; margin-top: 8px; }
.toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; background: #10b981; color: white; padding: 10px 16px; border-radius: 8px; font-size: 13px; animation: slideDown 0.3s ease; }
@keyframes slideDown { from { transform: translate(-50%, -20px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }
@media (max-width: 1000px) { .add-form-row { flex-direction: column; align-items: stretch; } .add-form-row .form-group { width: 100%; } .notes-group { width: 100%; } .btn-group { width: 100%; } .btn-add { width: 100%; } .schedule-table { font-size: 11px; display: block; overflow-x: auto; } .schedule-table td, .schedule-table th { padding: 5px 3px; } .dest-search-wrapper { min-width: 180px; } .notes-input { width: 120px; } }
</style>

<div class="main-content">
    <div class="schedule-container">
        
        <?php if ($toast): ?>
        <div class="toast-container"><?= htmlspecialchars($toast) ?></div>
        <?php endif; ?>
        
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>admin/dashboard.php">Home</a> / 
            <a href="../passengers.php">Passengers</a> / 
            <a href="../edit_passenger.php?id=<?= $passenger_id ?>"><?= htmlspecialchars($passenger['full_name']) ?></a> / 
            <strong>Weekly Schedule</strong>
        </div>
        
        <div class="page-header">
            <h1>📅 Weekly Schedule: <?= htmlspecialchars($passenger['display_name'] ?? $passenger['full_name']) ?></h1>
            <div class="entry-count"><?= count($scheduleItems) ?> entries</div>
        </div>

        <?php if (empty($scheduleItems)): ?>
            <div style="text-align:center; padding:30px; background:white; border-radius:8px;">
                <p>📭 No schedule entries yet. Add one below!</p>
            </div>
        <?php else: ?>
            <table class="schedule-table">
                <thead>
                    <tr><th style="width:45px;">Day</th><th style="width:55px;">Session</th><th style="width:80px;">Time</th><th style="min-width:280px;">Destination</th><th style="width:75px;">Frequency</th><th style="width:40px;">WC</th><th style="width:45px;">Rota</th><th style="min-width:160px;">Notes</th><th style="width:75px;">Actions</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $currentDay = '';
                    foreach ($scheduleItems as $item): 
                        $isNewDay = ($currentDay !== $item['day_of_week']);
                        $currentDay = $item['day_of_week'];
                    ?>
                        <?php if ($isNewDay): ?>
                            <tr class="day-separator"><td colspan="9"><strong><?= $item['day_of_week'] ?></strong></td></tr>
                        <?php endif; ?>
                        <tr data-id="<?= $item['id'] ?>">
                            <td><select class="inline-select day-select" data-id="<?= $item['id'] ?>" data-field="day_of_week" style="width:75px;"><?php foreach ($days as $d): ?><option value="<?= $d ?>" <?php if($item['day_of_week'] == $d) echo 'selected'; ?>><?= substr($d, 0, 3) ?></option><?php endforeach; ?></select></td>
                            <td><select class="inline-select" data-id="<?= $item['id'] ?>" data-field="session" style="width:55px;"><?php foreach ($sessions as $s): ?><option value="<?= $s ?>" <?php if($item['session'] == $s) echo 'selected'; ?>><?= $s ?></option><?php endforeach; ?></select></td>
                            <td><input type="time" class="inline-input time-input" data-id="<?= $item['id'] ?>" data-field="pickup_time" value="<?= date('H:i', strtotime($item['pickup_time'])) ?>"></td>
                            <td style="min-width:260px;"><div class="dest-search-wrapper"><input type="text" class="dest-search-input" id="destSearch_<?= $item['id'] ?>" value="<?= htmlspecialchars($item['destination_name'] ?? '') ?>" placeholder="🔍 Type destination..." autocomplete="off" data-id="<?= $item['id'] ?>"><button type="button" class="search-clear-btn" id="clearDest_<?= $item['id'] ?>">×</button><input type="hidden" id="destId_<?= $item['id'] ?>" value="<?= $item['destination_id'] ?>"><div class="dest-search-results" id="destResults_<?= $item['id'] ?>"></div></div></td>
                            <td><select class="inline-select" data-id="<?= $item['id'] ?>" data-field="frequency_note" style="width:75px;"><option value="Weekly" <?php if(($item['frequency_note'] ?? 'Weekly') == 'Weekly') echo 'selected'; ?>>Weekly</option><option value="1st week" <?php if(($item['frequency_note'] ?? '') == '1st week') echo 'selected'; ?>>1st wk</option><option value="2nd week" <?php if(($item['frequency_note'] ?? '') == '2nd week') echo 'selected'; ?>>2nd wk</option><option value="3rd week" <?php if(($item['frequency_note'] ?? '') == '3rd week') echo 'selected'; ?>>3rd wk</option><option value="4th week" <?php if(($item['frequency_note'] ?? '') == '4th week') echo 'selected'; ?>>4th wk</option><option value="Bi-weekly" <?php if(($item['frequency_note'] ?? '') == 'Bi-weekly') echo 'selected'; ?>>Bi-wk</option><option value="Monthly" <?php if(($item['frequency_note'] ?? '') == 'Monthly') echo 'selected'; ?>>Monthly</option></select></td>
                            <td style="text-align:center;"><input type="checkbox" class="wc-checkbox" data-id="<?= $item['id'] ?>" data-field="requires_wheelchair" <?php if($item['requires_wheelchair']) echo 'checked'; ?>></td>
                            <td style="text-align:center;"><input type="checkbox" class="rota-checkbox" data-id="<?= $item['id'] ?>" data-field="include_in_rota" <?php if($item['include_in_rota']) echo 'checked'; ?>></td>
                            <td><input type="text" class="inline-input notes-input" data-id="<?= $item['id'] ?>" data-field="notes" value="<?= htmlspecialchars($item['notes'] ?? '') ?>" placeholder="Notes"></td>
                            <td><a href="?passenger_id=<?= $passenger_id ?>&duplicate_id=<?= $item['id'] ?>" class="action-btn btn-duplicate" title="Duplicate">📋 Copy</a> <a href="?passenger_id=<?= $passenger_id ?>&delete_id=<?= $item['id'] ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Delete this entry?')">🗑️ Del</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Add New Entry Section -->
        <div class="add-section">
            <h3>➕ Add New Schedule Entry</h3>
            <form method="POST" id="addEntryForm">
                <div class="add-form-row">
                    <div class="form-group"><label>Day</label><select name="day_of_week" required style="width:90px;"><?php foreach ($days as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Session</label><select name="session" required style="width:65px;"><?php foreach ($sessions as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Time</label><input type="time" name="pickup_time" required style="width:85px;"></div>
                    <div class="form-group" style="min-width:260px;"><label>Destination <span style="color:#22c55e;">(Searchable)</span></label><div class="dest-search-wrapper"><input type="text" class="dest-search-input" id="destSearchNewAdd" placeholder="🔍 Type to search..." autocomplete="off"><button type="button" class="search-clear-btn" id="clearDestNewAdd">×</button><input type="hidden" name="destination_id" id="destIdNewAdd" required><div class="dest-search-results" id="destResultsNewAdd"></div></div></div>
                    <div class="form-group"><label>Frequency</label><select name="frequency_note" style="width:85px;"><option value="Weekly">Weekly</option><option value="1st week">1st wk</option><option value="2nd week">2nd wk</option><option value="3rd week">3rd wk</option><option value="4th week">4th wk</option><option value="Bi-weekly">Bi-wk</option><option value="Monthly">Monthly</option></select></div>
                    <div class="checkbox-group"><input type="checkbox" name="requires_wheelchair" value="1" id="addWC"><label for="addWC">WC</label></div>
                </div>
                <div class="add-form-row">
                    <div class="form-group notes-group"><label>Notes</label><input type="text" name="notes" placeholder="Optional notes..."></div>
                    <div class="btn-group"><button type="submit" name="add_entry" class="btn-add">+ Add Entry</button></div>
                </div>
            </form>
        </div>
        
        <div>
            <a href="../edit_passenger.php?id=<?= $passenger_id ?>" class="btn-back">← Back to Passenger Profile</a>
        </div>

    </div> <!-- END schedule-container -->
</div> <!-- END main-content -->

<script>
var destinations = <?= json_encode($destinations); ?>;

function saveField(entryId, field, value) {
    var row = document.querySelector('tr[data-id="' + entryId + '"]');
    if (!row) return;
    row.style.opacity = '0.5';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    row.classList.add('updated');
                    setTimeout(function() { row.classList.remove('updated'); }, 1000);
                }
            } catch(e) {}
        }
        row.style.opacity = '1';
    };
    xhr.onerror = function() { row.style.opacity = '1'; };
    xhr.send('ajax_update=1&field=' + encodeURIComponent(field) + '&value=' + encodeURIComponent(value) + '&entry_id=' + encodeURIComponent(entryId));
}

function initDestSearch(itemId) {
    var input = document.getElementById('destSearch_' + itemId);
    var hidden = document.getElementById('destId_' + itemId);
    var results = document.getElementById('destResults_' + itemId);
    var clearBtn = document.getElementById('clearDest_' + itemId);
    if (!input) return;
    function toggleClearBtn() { if (clearBtn) { clearBtn.style.display = input.value ? 'flex' : 'none'; } }
    if (clearBtn) {
        clearBtn.onclick = function(e) {
            e.preventDefault(); e.stopPropagation();
            input.value = ''; hidden.value = ''; results.style.display = 'none';
            toggleClearBtn(); input.focus();
            saveField(itemId, 'destination_id', '');
        };
    }
    function showResults() {
        var query = input.value.toLowerCase().trim();
        var html = ''; var matchCount = 0;
        for (var i = 0; i < destinations.length; i++) {
            var dest = destinations[i];
            var name = dest.name || '';
            if (query === '' || name.toLowerCase().indexOf(query) !== -1) {
                var highlightClass = (matchCount === 0) ? 'highlighted' : '';
                html += '<div class="dest-search-result ' + highlightClass + '" data-id="' + dest.location_id + '" data-name="' + escapeHtml(name) + '">';
                html += '<span class="dest-name">📍 ' + escapeHtml(name) + '</span>';
                if (dest.short_name) html += '<span class="dest-short">(' + escapeHtml(dest.short_name) + ')</span>';
                html += '</div>';
                matchCount++;
            }
        }
        if (matchCount === 0) html = '<div class="dest-search-result" style="color:#94a3b8;">No destinations found</div>';
        results.innerHTML = html;
        results.style.display = 'block';
    }
    input.onfocus = function() { showResults(); this.select(); };
    input.oninput = function() { showResults(); toggleClearBtn(); };
    input.onkeydown = function(e) {
        var items = results.querySelectorAll('.dest-search-result');
        if (items.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            var highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.nextSibling) {
                highlighted.classList.remove('highlighted');
                highlighted.nextSibling.classList.add('highlighted');
                highlighted.nextSibling.scrollIntoView({ block: 'nearest' });
            } else if (!highlighted && items[0]) { items[0].classList.add('highlighted'); }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.previousSibling) {
                highlighted.classList.remove('highlighted');
                highlighted.previousSibling.classList.add('highlighted');
                highlighted.previousSibling.scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            var highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.dataset.id) {
                input.value = highlighted.dataset.name;
                hidden.value = highlighted.dataset.id;
                results.style.display = 'none';
                saveField(itemId, 'destination_id', highlighted.dataset.id);
                toggleClearBtn();
            } else if (items[0] && items[0].dataset.id) {
                input.value = items[0].dataset.name;
                hidden.value = items[0].dataset.id;
                results.style.display = 'none';
                saveField(itemId, 'destination_id', items[0].dataset.id);
                toggleClearBtn();
            }
        } else if (e.key === 'Escape') { results.style.display = 'none'; }
    };
    results.onclick = function(e) {
        var item = e.target.closest('.dest-search-result');
        if (item && item.dataset.id) {
            input.value = item.dataset.name;
            hidden.value = item.dataset.id;
            results.style.display = 'none';
            saveField(itemId, 'destination_id', item.dataset.id);
            toggleClearBtn();
        }
    };
    results.onmouseenter = function(e) {
        var items = results.querySelectorAll('.dest-search-result');
        for (var i = 0; i < items.length; i++) items[i].classList.remove('highlighted');
        if (e.target.classList && e.target.classList.contains('dest-search-result')) e.target.classList.add('highlighted');
    };
    toggleClearBtn();
}

function initAddDestSearch() {
    var input = document.getElementById('destSearchNewAdd');
    var hidden = document.getElementById('destIdNewAdd');
    var results = document.getElementById('destResultsNewAdd');
    var clearBtn = document.getElementById('clearDestNewAdd');
    if (!input) return;
    function toggleClearBtn() { if (clearBtn) { clearBtn.style.display = input.value ? 'flex' : 'none'; } }
    if (clearBtn) {
        clearBtn.onclick = function(e) {
            e.preventDefault(); e.stopPropagation();
            input.value = ''; hidden.value = ''; results.style.display = 'none';
            toggleClearBtn(); input.focus();
        };
    }
    function showResults() {
        var query = input.value.toLowerCase().trim();
        var html = ''; var matchCount = 0;
        for (var i = 0; i < destinations.length; i++) {
            var dest = destinations[i];
            var name = dest.name || '';
            if (query === '' || name.toLowerCase().indexOf(query) !== -1) {
                var highlightClass = (matchCount === 0) ? 'highlighted' : '';
                html += '<div class="dest-search-result ' + highlightClass + '" data-id="' + dest.location_id + '" data-name="' + escapeHtml(name) + '">';
                html += '<span class="dest-name">📍 ' + escapeHtml(name) + '</span>';
                if (dest.short_name) html += '<span class="dest-short">(' + escapeHtml(dest.short_name) + ')</span>';
                html += '</div>';
                matchCount++;
            }
        }
        if (matchCount === 0) html = '<div class="dest-search-result" style="color:#94a3b8;">No destinations found</div>';
        results.innerHTML = html;
        results.style.display = 'block';
    }
    input.onfocus = function() { showResults(); this.select(); };
    input.oninput = function() { showResults(); toggleClearBtn(); };
    input.onkeydown = function(e) {
        var items = results.querySelectorAll('.dest-search-result');
        if (items.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            var highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.nextSibling) {
                highlighted.classList.remove('highlighted');
                highlighted.nextSibling.classList.add('highlighted');
                highlighted.nextSibling.scrollIntoView({ block: 'nearest' });
            } else if (!highlighted && items[0]) { items[0].classList.add('highlighted'); }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.previousSibling) {
                highlighted.classList.remove('highlighted');
                highlighted.previousSibling.classList.add('highlighted');
                highlighted.previousSibling.scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            var highlighted = results.querySelector('.highlighted');
            if (highlighted && highlighted.dataset.id) {
                input.value = highlighted.dataset.name;
                hidden.value = highlighted.dataset.id;
                results.style.display = 'none';
                toggleClearBtn();
            } else if (items[0] && items[0].dataset.id) {
                input.value = items[0].dataset.name;
                hidden.value = items[0].dataset.id;
                results.style.display = 'none';
                toggleClearBtn();
            }
        } else if (e.key === 'Escape') { results.style.display = 'none'; }
    };
    results.onclick = function(e) {
        var item = e.target.closest('.dest-search-result');
        if (item && item.dataset.id) {
            input.value = item.dataset.name;
            hidden.value = item.dataset.id;
            results.style.display = 'none';
            toggleClearBtn();
        }
    };
    results.onmouseenter = function(e) {
        var items = results.querySelectorAll('.dest-search-result');
        for (var i = 0; i < items.length; i++) items[i].classList.remove('highlighted');
        if (e.target.classList && e.target.classList.contains('dest-search-result')) e.target.classList.add('highlighted');
    };
    toggleClearBtn();
}

function escapeHtml(text) { var div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.day-select, .session-select, .frequency-select').forEach(function(select) {
        select.addEventListener('change', function() { saveField(this.getAttribute('data-id'), this.getAttribute('data-field'), this.value); });
    });
    document.querySelectorAll('.time-input, .notes-input').forEach(function(input) {
        input.addEventListener('change', function() { saveField(this.getAttribute('data-id'), this.getAttribute('data-field'), this.value); });
    });
    document.querySelectorAll('.wc-checkbox, .rota-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() { saveField(this.getAttribute('data-id'), this.getAttribute('data-field'), this.checked ? 1 : 0); });
    });
    <?php foreach ($scheduleItems as $item): ?>initDestSearch(<?= $item['id'] ?>);<?php endforeach; ?>
    initAddDestSearch();
    var addForm = document.getElementById('addEntryForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            var destId = document.getElementById('destIdNewAdd').value;
            if (!destId) {
                e.preventDefault();
                alert('Please select a destination from the dropdown');
                document.getElementById('destSearchNewAdd').focus();
                return false;
            }
        });
    }
});

setTimeout(function() {
    var toast = document.querySelector('.toast-container');
    if (toast) { toast.style.opacity = '0'; setTimeout(function() { if(toast) toast.remove(); }, 500); }
}, 4000);
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>