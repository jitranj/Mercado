<?php
header('Content-Type: application/json');
session_start();
include '../db/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_role = $_SESSION['role'] ?? 'staff';
if (!in_array($current_role, ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => '⛔ Access Denied: Managers/Admins only.']);
    exit;
}

$action = $_POST['action'] ?? '';
$stall_id = $_POST['stall_id'] ?? 0;
$renter_id = $_POST['renter_id'] ?? 0;

if (!$stall_id || !$renter_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$conn->begin_transaction();
try {
    if ($action === 'approve') {
        $conn->query("UPDATE stalls SET status = 'occupied' WHERE id = $stall_id");
        $conn->query("UPDATE renters SET is_reservation = 0, start_date = CURDATE() WHERE renter_id = $renter_id");
        $msg = "Reservation Approved! Tenant is now active.";
    } 
    elseif ($action === 'cancel') {
        $conn->query("UPDATE stalls SET status = 'available' WHERE id = $stall_id");
        $conn->query("UPDATE renters SET end_date = CURDATE() WHERE renter_id = $renter_id");
        $msg = "Reservation Cancelled. Stall is now vacant.";
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>