<?php
ob_start();

session_start();
include 'db_connect.php';

header('Content-Type: application/json');

function sendJson($success, $message = '') {
    ob_clean(); 
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJson(false, 'Unauthorized');
}

$renter_id = $_POST['renter_id'] ?? 0;
$stall_id = $_POST['stall_id'] ?? 0;
$password = $_POST['admin_password'] ?? '';

if (!$renter_id || !$stall_id) {
    sendJson(false, 'Missing IDs');
}

if (empty($password)) {
    sendJson(false, 'Password Required');
}

$user_id = $_SESSION['user_id'];
$stmt_u = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$stmt_u->bind_result($hash);
$stmt_u->fetch();
$stmt_u->close();

if (!password_verify($password, $hash)) {
    sendJson(false, ' Incorrect Password. Action Denied.');
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
    sendJson(true, 'Contract Terminated Successfully');

} catch (Exception $e) {
    $conn->rollback();
    sendJson(false, $e->getMessage());
}
?>