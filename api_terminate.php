<?php
// 1. SAFETY: Turn on Output Buffering immediately
// This traps any accidental whitespace or PHP warnings
ob_start();

session_start();
include 'db_connect.php';

// Define header but don't send output yet
header('Content-Type: application/json');

// Helper to send clean JSON
function sendJson($success, $message = '') {
    ob_clean(); // Delete any garbage (warnings/spaces) in the buffer
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// 2. Auth Check
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

// 3. Verify Password
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

// 4. Perform Termination
$conn->begin_transaction();
try {
    // Archive Renter
    $stmt1 = $conn->prepare("UPDATE renters SET end_date = CURDATE() WHERE renter_id = ?");
    $stmt1->bind_param("i", $renter_id);
    $stmt1->execute();

    // Free Stall
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