<?php
header('Content-Type: application/json');
include 'db_connect.php';

$stall_id = $_POST['stall_id'] ?? 0;
$name = $_POST['renter_name'] ?? '';
$contact = $_POST['contact_number'] ?? '';
$date = $_POST['reservation_date'] ?? date('Y-m-d');
$fee = $_POST['reservation_fee'] ?? 0;

if (!$stall_id || !$name) {
    echo json_encode(['success' => false, 'message' => 'Missing Name or Stall ID']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Insert Renter with is_reservation = 1
    // We store the reservation fee as "Goodwill" initially so it tracks as paid money
    $stmt = $conn->prepare("INSERT INTO renters (stall_id, renter_name, contact_number, start_date, is_reservation, goodwill_total) VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->bind_param("isssd", $stall_id, $name, $contact, $date, $fee);
    $stmt->execute();
    $renter_id = $conn->insert_id;

    // 2. Update Stall Status
    $stmt2 = $conn->prepare("UPDATE stalls SET status = 'reserved' WHERE id = ?");
    $stmt2->bind_param("i", $stall_id);
    $stmt2->execute();

    // 3. Record Reservation Fee (if any)
    if ($fee > 0) {
        $stmt3 = $conn->prepare("INSERT INTO payments (renter_id, payment_date, amount, payment_type, remarks) VALUES (?, ?, ?, 'goodwill', 'Reservation Fee')");
        $stmt3->bind_param("isd", $renter_id, $date, $fee);
        $stmt3->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>