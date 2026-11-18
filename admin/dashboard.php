<?php
// /sts-app/admin/dashboard.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ==============================
   GET SYSTEM STATS
============================== */

try {

    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'");
    $totalAdmins = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role='driver'");
    $totalDrivers = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role='driver' AND status='active'");
    $activeDrivers = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM passengers WHERE is_active=1");
    $passengers = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM locations WHERE is_active=1");
    $locations = $stmt->fetchColumn();

} catch (Exception $e) {

    $totalUsers = 0;
    $totalAdmins = 0;
    $totalDrivers = 0;
    $activeDrivers = 0;
    $passengers = 0;
    $locations = 0;

}

?>

<?php include '../includes/sidebar.php'; ?>

<div class="main-content">

<div class="stats-grid">

    <div class="stat-card">
        <h3>Total Users</h3>
        <div class="stat-number"><?php echo $totalUsers; ?></div>
    </div>

<a href="../admin/users/users.php" class="stat-card-link">
    <div class="stat-card">
        <h3>Admins</h3>
        <div class="stat-number"><?php echo $totalAdmins; ?></div>
    </div>
</a>

<a href="../admin/drivers/drivers.php" class="stat-card-link">
    <div class="stat-card">
        <h3>Drivers</h3>
        <div class="stat-number"><?php echo $totalDrivers; ?></div>
    </div>
</a>

<a href="../admin/passengers/passengers.php" class="stat-card-link">
    <div class="stat-card">
        <h3>Passengers</h3>
        <div class="stat-number"><?php echo $passengers; ?></div>
    </div>
</a>

<a href="../admin/locations/locations.php" class="stat-card-link">
    <div class="stat-card">
        <h3>Locations</h3>
        <div class="stat-number"><?php echo $locations; ?></div>
    </div>
</a>

</div>


<div class="quick-actions">

<h2>Quick Actions</h2>

<div class="action-buttons">

<a href="/sts-app/admin/passengers/passengers.php" class="action-link">Manage Passengers</a>

<a href="../admin/locations/locations.php" class="action-link">Manage Locations</a>

<a href="../admin/drivers/drivers.php" class="action-link">Manage Drivers</a>

<a href="#" class="action-link">View Reports</a>

</div>

</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>