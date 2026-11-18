<?php
// /sts-app/admin/rota/ajax/update-status.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth-check.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

$allowedStatus = ['scheduled', 'assigned', 'completed', 'cancelled'];

if (!$job_id || !in_array($status, $allowedStatus)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE rota_jobs SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $job_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>