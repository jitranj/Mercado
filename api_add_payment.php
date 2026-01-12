<?php
header('Content-Type: application/json');
include 'db_connect.php';

$renter_id = $_POST['renter_id'] ?? 0;
$amount = $_POST['amount'] ?? 0;
$date_paid = $_POST['date_paid'] ?? date('Y-m-d');
$month_for = $_POST['month_for'] ?? date('Y-m-d');

if(!$renter_id || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO payments (renter_id, payment_date, amount, month_paid_for) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isds", $renter_id, $date_paid, $amount, $month_for);

if ($stmt->execute()) {

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>