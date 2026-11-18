<?php
// /sts-app/driver/dashboard.php

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

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get driver's full name
try {
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    $driver_name = $driver['first_name'] . ' ' . $driver['last_name'];
} catch (Exception $e) {
    $driver_name = $username;
}

// Get today's date
$today_display = date('l, jS F Y');

// Simple stats - count how many drivers exist (placeholder)
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'driver'");
    $total_drivers = $stmt->fetchColumn();
} catch (Exception $e) {
    $total_drivers = 0;
}
?>

<style>
/* Driver Dashboard Styles - Mobile First */
.driver-dashboard {
    max-width: 800px;
    margin: 0 auto;
    padding: 15px;
}

/* Welcome Section */
.welcome-card {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.welcome-card h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.welcome-card p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.date-badge {
    background: rgba(255,255,255,0.2);
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-top: 10px;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    background: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #3b82f6;
}

.stat-label {
    font-size: 12px;
    color: #64748b;
    margin-top: 5px;
}

/* Section Headers */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0 15px 0;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    color: #1e293b;
}

.section-link {
    font-size: 13px;
    color: #3b82f6;
    text-decoration: none;
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.info-card p {
    margin: 10px 0;
    color: #64748b;
}

.empty-state {
    background: #f8fafc;
    text-align: center;
    padding: 40px;
    border-radius: 10px;
    color: #64748b;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.action-btn {
    background: #3b82f6;
    color: white;
    padding: 10px 16px;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    flex: 1;
    transition: background 0.2s;
}

.action-btn:hover {
    background: #2563eb;
}

.action-btn.secondary {
    background: #64748b;
}

.action-btn.secondary:hover {
    background: #475569;
}

.action-btn.warning {
    background: #f59e0b;
}

.action-btn.warning:hover {
    background: #d97706;
}

/* Mobile Responsive */
@media (max-width: 640px) {
    .driver-dashboard {
        padding: 10px;
    }
    
    .stats-row {
        gap: 10px;
    }
    
    .stat-box {
        padding: 12px;
    }
    
    .stat-number {
        font-size: 24px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        text-align: center;
    }
}

/* Desktop improvements */
@media (min-width: 768px) {
    .driver-dashboard {
        padding: 20px;
    }
}
</style>

<div class="main-content">
    <div class="driver-dashboard">
        
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Hello, <?= htmlspecialchars($driver_name) ?>! 👋</h2>
            <p>Welcome to your driver dashboard</p>
            <div class="date-badge">📅 <?= $today_display ?></div>
        </div>
        
        <!-- Quick Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-number">0</div>
                <div class="stat-label">Today's Jobs</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">0</div>
                <div class="stat-label">Completed This Week</div>
            </div>
        </div>
        
        <!-- Info Section -->
        <div class="section-header">
            <h3>📍 Your Schedule</h3>
            <a href="weekly.php" class="section-link">View Week →</a>
        </div>
        
        <div class="info-card">
            <p>✨ Your schedule will appear here once jobs are assigned.</p>
            <p style="font-size: 13px;">Check back later or contact your administrator.</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="weekly.php" class="action-btn">📅 View Weekly Rota</a>
            <a href="account.php#password" class="action-btn warning">🔐 Change Password</a>
        </div>
        
    </div>
</div>

<script>
function toggleSidebar() {
    document.body.classList.toggle("sidebar-collapsed");
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>