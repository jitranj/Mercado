<?php
header('Content-Type: application/json');
include 'db_connect.php';

$renter_id = $_POST['renter_id'] ?? 0;
$stall_id = $_POST['stall_id'] ?? 0;

if (!$renter_id || !$stall_id) {
    echo json_encode(['success' => false, 'message' => 'Missing IDs']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt1 = $conn->prepare("UPDATE renters SET end_date = CURDATE() WHERE renter_id = ?");
    $stmt1->bind_param("i", $renter_id);
    $stmt1->execute();

    $stmt2 = $conn->prepare("UPDATE stalls SET status = 'available' WHERE id = ?");
    $stmt2->bind_param("i", $stall_id);
    $stmt2->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>