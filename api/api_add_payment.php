<?php
header('Content-Type: application/json');
session_start();
include '../db/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}


$allowed_roles = ['admin', 'manager', 'staff_billing', 'staff_cashier'];
$current_role = $_SESSION['role'] ?? 'staff_monitor';

if (!in_array($current_role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => '⛔ Unauthorized: Monitors cannot record payments.']);
    exit;
}

$renter_id = $_POST['renter_id'] ?? 0;
$date_paid = $_POST['date_paid'] ?? date('Y-m-d');
$type = $_POST['payment_type'] ?? 'rent';
$or_no = $_POST['or_no'] ?? ''; 


if(!$renter_id) {
    echo json_encode(['success' => false, 'message' => 'Missing Renter ID']);
    exit;
}

if (!empty($or_no)) {
    $check = $conn->prepare("SELECT payment_id FROM payments WHERE or_no = ?");
    $check->bind_param("s", $or_no);
    $check->execute();
    if($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Error: OR Number used!']);
        exit;
    }
}

$amount = 0;
$month_for = NULL;

if ($type === 'rent') {

    if (empty($or_no)) {
        echo json_encode(['success' => false, 'message' => 'OR Number is required for Rent!']);
        exit;
    }

    $rate_sql = "SELECT s.monthly_rate, r.start_date FROM renters r JOIN stalls s ON r.stall_id = s.id WHERE r.renter_id = ?";
    $stmt_rate = $conn->prepare($rate_sql);
    $stmt_rate->bind_param("i", $renter_id);
    $stmt_rate->execute();
    $res = $stmt_rate->get_result()->fetch_assoc();
    $amount = $res['monthly_rate'];
    $start_date = $res['start_date'];

    $last_pay_sql = "SELECT MAX(month_paid_for) FROM payments WHERE renter_id = ? AND payment_type = 'rent'";
    $stmt_last = $conn->prepare($last_pay_sql);
    $stmt_last->bind_param("i", $renter_id);
    $stmt_last->execute();
    $last_pay = $stmt_last->get_result()->fetch_row()[0];

    if ($last_pay) {
        $month_for = date('Y-m-d', strtotime($last_pay . ' +1 month'));
    } else {
        $month_for = date('Y-m-01', strtotime($start_date));
    }


    if (date('Y-m', strtotime($month_for)) > date('Y-m')) {
        echo json_encode(['success' => false, 'message' => 'Tenant is fully paid up to date!']);
        exit;
    }

} else {

    $amount = $_POST['amount'] ?? 0;
    if($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }
    if(empty($or_no)) $or_no = null;
}

$stmt = $conn->prepare("INSERT INTO payments (renter_id, payment_date, amount, payment_type, month_paid_for, or_no) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isdsss", $renter_id, $date_paid, $amount, $type, $month_for, $or_no);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>