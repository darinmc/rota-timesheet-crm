<?php
// /sts-app/driver/today.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

// Check if user is driver
if ($_SESSION['role'] !== 'driver') {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get today's jobs
$stmt = $db->prepare("
    SELECT j.*, p.display_name as passenger_name, p.full_name as passenger_full,
           p.address as passenger_address, p.phone as passenger_phone,
           l.name as location_name, l.short_name as location_short,
           l.address as location_address, l.postcode as location_postcode,
           l.contact_name, l.contact_phone
    FROM taxi_jobs_driver j
    LEFT JOIN passengers p ON j.passenger_id = p.passenger_id
    LEFT JOIN locations l ON j.location_id = l.location_id
    WHERE j.driver_id = ? AND j.schedule_date = ?
    ORDER BY j.pickup_time, j.am_pm
");
$stmt->execute([$user_id, $today]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Same styles as dashboard, plus additional */
.page-header {
    margin-bottom: 20px;
}

.page-header h1 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.page-header p {
    margin: 0;
    color: #64748b;
}

.back-link {
    display: inline-block;
    margin-bottom: 15px;
    color: #3b82f6;
    text-decoration: none;
}

.back-link:hover {
    text-decoration: underline;
}
</style>

<div class="main-content">
    <div class="driver-dashboard">
        
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>📍 Today's Jobs</h1>
            <p><?= date('l, jS F Y') ?></p>
        </div>
        
        <?php if (count($jobs) > 0): ?>
            <?php 
            $current_session = '';
            foreach ($jobs as $job): 
                $is_new_session = $current_session !== $job['am_pm'];
                $current_session = $job['am_pm'];
            ?>
                <?php if ($is_new_session): ?>
                    <div style="margin: 20px 0 10px 0; padding: 8px 12px; background: #f1f5f9; border-radius: 8px;">
                        <strong><?= $job['am_pm'] ?> Session</strong>
                    </div>
                <?php endif; ?>
                
                <div class="job-card">
                    <div class="job-time">
                        <span>⏰ <?= date('H:i', strtotime($job['pickup_time'])) ?></span>
                        <span class="status-badge status-<?= $job['overall_status'] ?>">
                            <?= ucfirst($job['overall_status']) ?>
                        </span>
                    </div>
                    <div class="job-details">
                        <div class="job-passenger">
                            <i>👤</i> <?= htmlspecialchars($job['passenger_name'] ?? 'Unknown') ?>
                            <?php if (!empty($job['passenger_phone'])): ?>
                                <span style="font-size: 12px; color: #10b981;">📞 <?= htmlspecialchars($job['passenger_phone']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="job-location">
                            <i>📍</i> <?= htmlspecialchars($job['location_name'] ?? 'Location') ?>
                            <?php if (!empty($job['contact_phone'])): ?>
                                <span style="font-size: 12px; color: #64748b;">📞 <?= htmlspecialchars($job['contact_phone']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($job['location_address'])): ?>
                            <div class="job-address">
                                📍 <?= htmlspecialchars($job['location_address']) ?>
                                <?php if (!empty($job['location_postcode'])): ?>
                                    <br><?= htmlspecialchars($job['location_postcode']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($job['notes'])): ?>
                            <div class="job-notes">
                                📝 Notes: <?= htmlspecialchars($job['notes']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($job['overall_status'] === 'scheduled'): ?>
                            <button class="start-job-btn" onclick="startJob(<?= $job['id'] ?>)">
                                🚗 Start This Trip
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>✨ No jobs scheduled for today</p>
                <p>Enjoy your day off!</p>
                <a href="dashboard.php" class="action-btn" style="display: inline-block; margin-top: 15px;">Back to Dashboard</a>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
function startJob(jobId) {
    if (confirm('Start this job? This will open the job details page where you can record the trip.')) {
        window.location.href = 'job-details.php?id=' + jobId;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>