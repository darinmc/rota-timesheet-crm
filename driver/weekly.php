<?php
// /sts-app/driver/weekly.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SESSION['role'] !== 'driver') {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current week
$week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('monday this week'));
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($week_start)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start)));

// Get jobs for the week
$stmt = $db->prepare("
    SELECT j.*, p.display_name as passenger_name,
           l.name as location_name, l.short_name as location_short
    FROM taxi_jobs_driver j
    LEFT JOIN passengers p ON j.passenger_id = p.passenger_id
    LEFT JOIN locations l ON j.location_id = l.location_id
    WHERE j.driver_id = ? AND j.schedule_date BETWEEN ? AND ?
    ORDER BY j.schedule_date, j.pickup_time
");
$stmt->execute([$user_id, $week_start, $week_end]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group jobs by day
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$jobs_by_day = [];
foreach ($days as $day) {
    $jobs_by_day[$day] = [];
}
foreach ($jobs as $job) {
    $day_name = date('l', strtotime($job['schedule_date']));
    $jobs_by_day[$day_name][] = $job;
}

// Navigation
$prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));
?>

<style>
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
    text-align: center;
    font-weight: 600;
    color: #1e293b;
}

.day-section {
    margin-bottom: 25px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.day-header {
    background: #f1f5f9;
    padding: 12px 15px;
    font-weight: 600;
    border-left: 4px solid #3b82f6;
}

.day-date {
    font-size: 12px;
    color: #64748b;
    font-weight: normal;
}

.no-jobs {
    padding: 20px;
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
}

.week-job {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.week-job-time {
    font-weight: 600;
    min-width: 70px;
    color: #3b82f6;
}

.week-job-details {
    flex: 1;
}

.week-job-passenger {
    font-weight: 500;
}

.week-job-location {
    font-size: 13px;
    color: #64748b;
}

.week-job-status {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
}

@media (max-width: 640px) {
    .week-job {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .week-job-time {
        margin-bottom: 5px;
    }
}
</style>

<div class="main-content">
    <div class="driver-dashboard">
        
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="week-navigation">
            <a href="?week=<?= $prev_week ?>" class="week-nav-btn">← Previous Week</a>
            <div class="week-title">
                Week of <?= date('j M', strtotime($week_start)) ?> - <?= date('j M Y', strtotime($week_end)) ?>
            </div>
            <a href="?week=<?= $next_week ?>" class="week-nav-btn">Next Week →</a>
        </div>
        
        <?php foreach ($days as $day): 
            $day_date = date('Y-m-d', strtotime($day . ' this week', strtotime($week_start)));
            $jobs_for_day = $jobs_by_day[$day];
        ?>
            <div class="day-section">
                <div class="day-header">
                    <?= $day ?> 
                    <span class="day-date"><?= date('j M', strtotime($day_date)) ?></span>
                </div>
                
                <?php if (count($jobs_for_day) > 0): ?>
                    <?php foreach ($jobs_for_day as $job): ?>
                        <div class="week-job">
                            <div class="week-job-time">
                                <?= date('H:i', strtotime($job['pickup_time'])) ?>
                                <span style="font-size: 10px;">(<?= $job['am_pm'] ?>)</span>
                            </div>
                            <div class="week-job-details">
                                <div class="week-job-passenger">
                                    👤 <?= htmlspecialchars($job['passenger_name'] ?? 'Unknown') ?>
                                </div>
                                <div class="week-job-location">
                                    📍 <?= htmlspecialchars($job['location_name'] ?? 'Location') ?>
                                </div>
                            </div>
                            <div class="week-job-status status-<?= $job['overall_status'] ?>">
                                <?= ucfirst($job['overall_status']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-jobs">
                        ✨ No jobs scheduled
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>