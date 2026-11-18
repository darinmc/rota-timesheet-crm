<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? 'driver';
$is_admin = ($user_role === 'admin');
$is_driver = ($user_role === 'driver');

?>

<aside class="sidebar">

<div class="sidebar-logo">
🚕 STS Transport
</div>

<nav class="sidebar-nav">

<!-- Admin Menu - Only visible to admins -->
<?php if ($is_admin): ?>

<a href="/sts-app/admin/dashboard.php" data-tooltip="Dashboard">🏠 <span>Dashboard</span></a>
<a href="/sts-app/admin/users/users.php" data-tooltip="Users">👥  <span>Users</span></a>
<a href="/sts-app/admin/passengers/passengers.php" data-tooltip="Passengers">🧍  <span>Passengers</span></a>
<a href="/sts-app/admin/locations/locations.php" data-tooltip="Locations">📍  <span>Locations</span></a>
<a href="/sts-app/admin/drivers/drivers.php" data-tooltip="Drivers">🚗 <span>Drivers</span></a>
<a href="/sts-app/admin/reports.php" data-tooltip="Reports">📊  <span>Reports</span></a>
<a href="/sts-app/admin/rota.php" data-tooltip="Driver Rota">📅  <span>Driver Rota</span></a>

<?php endif; ?>

<!-- Driver Menu - Only visible to drivers -->
<?php if ($is_driver): ?>

<a href="/sts-app/driver/dashboard.php" data-tooltip="Dashboard">🏠  <span>Dashboart</span></a>
<a href="/sts-app/driver/account.php" data-tooltip="Account">👤  <span>My Account</span></a>
<a href="/sts-app/driver/today.php" data-tooltip="Today's Jobs">📍  <span>Today's Jobs</span></a>
<a href="/sts-app/driver/weekly.php" data-tooltip="Weekly Rota">📅  <span>Weekly Rota</span></a>
<a href="/sts-app/driver/history.php" data-tooltip="Job History">📋 <span>Job History</span></a>
<a href="/sts-app/driver/account.php#password" data-tooltip="Change Password">🔐 <span>Change Password</span></a>

<?php endif; ?>

<a href="/sts-app/auth/logout.php" class="logout-link" data-tooltip="Logout">🔓  <span>Logout</span></a>

</nav>

</aside>