<?php
// /sts-app/admin/passengers/schedule/bulk-update.php
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

$passenger_id = isset($_POST['passenger_id']) ? intval($_POST['passenger_id']) : 0;
$include_in_rota = isset($_POST['include_in_rota']) ? intval($_POST['include_in_rota']) : 0;

if (!$passenger_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid passenger ID']);
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE passenger_regular_schedules 
        SET include_in_rota = ?, updated_at = NOW()
        WHERE passenger_id = ? AND is_active = 1
    ");
    $stmt->execute([$include_in_rota, $passenger_id]);
    $count = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'count' => $count]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>