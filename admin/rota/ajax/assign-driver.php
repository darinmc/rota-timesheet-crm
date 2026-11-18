<?php
// /sts-app/admin/rota/ajax/assign-driver.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
$driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : null;

if (!$job_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE rota_jobs 
        SET driver_id = ?, status = IF(? IS NOT NULL, 'assigned', 'scheduled'), updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$driver_id, $driver_id, $job_id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>