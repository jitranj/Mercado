<?php
header('Content-Type: application/json');
include 'db_connect.php';

$action = $_POST['action'] ?? ''; // 'approve' or 'cancel'
$stall_id = $_POST['stall_id'] ?? 0;
$renter_id = $_POST['renter_id'] ?? 0;

if (!$stall_id || !$renter_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$conn->begin_transaction();
try {
    if ($action === 'approve') {
        // 1. Make Stall Occupied
        $conn->query("UPDATE stalls SET status = 'occupied' WHERE id = $stall_id");
        
        // 2. Make Renter Official (is_reservation = 0) & Set Official Start Date
        $conn->query("UPDATE renters SET is_reservation = 0, start_date = CURDATE() WHERE renter_id = $renter_id");
        
        $msg = "Reservation Approved! Tenant is now active.";
    } 
    elseif ($action === 'cancel') {
        // 1. Make Stall Available
        $conn->query("UPDATE stalls SET status = 'available' WHERE id = $stall_id");
        
        // 2. Archive/Remove Renter (Set end_date so they disappear from active list)
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