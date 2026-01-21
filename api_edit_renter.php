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


$current_role = $_SESSION['role'] ?? 'staff';
if (!in_array($current_role, ['admin', 'manager'])) {
    sendJson(false, '⛔ Access Denied: Managers/Admins only.');
}

$renter_id = isset($_POST['renter_id']) ? intval($_POST['renter_id']) : 0;
if ($renter_id <= 0) {
    sendJson(false, 'Invalid Renter ID');
}

$name = $_POST['renter_name'] ?? '';
$contact = $_POST['contact_number'] ?? '';
$email = $_POST['email_address'] ?? '';
$new_start_date = $_POST['start_date'] ?? '';
$admin_pass = $_POST['admin_password'] ?? '';

$stmt_curr = $conn->prepare("SELECT start_date FROM renters WHERE renter_id = ?");
$stmt_curr->bind_param("i", $renter_id);
$stmt_curr->execute();
$res_curr = $stmt_curr->get_result();

if ($res_curr->num_rows === 0) {
    sendJson(false, 'Renter not found');
}
$current_start_date = $res_curr->fetch_assoc()['start_date'];

$sensitive_change = false;
if (!empty($new_start_date) && $new_start_date !== $current_start_date) {
    $sensitive_change = true;
}
if (!empty($_FILES['contract_file']['name'])) {
    $sensitive_change = true;
}

if ($sensitive_change) {
    $user_id = $_SESSION['user_id'];
    $stmt_u = $conn->prepare("SELECT password_hash, role FROM users WHERE user_id = ?");
    $stmt_u->bind_param("i", $user_id);
    $stmt_u->execute();
    $user = $stmt_u->get_result()->fetch_assoc();

    if (!password_verify($admin_pass, $user['password_hash'])) {
        sendJson(false, 'Incorrect Password for Sensitive Changes!');
    }
}

$sql = "UPDATE renters SET renter_name=?, contact_number=?, email_address=? WHERE renter_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $name, $contact, $email, $renter_id);

if (!$stmt->execute()) {
    sendJson(false, 'Database Update Failed: ' . $conn->error);
}

if ($sensitive_change && !empty($new_start_date) && $new_start_date !== $current_start_date) {
    $stmt_date = $conn->prepare("UPDATE renters SET start_date = ? WHERE renter_id = ?");
    $stmt_date->bind_param("si", $new_start_date, $renter_id);
    $stmt_date->execute();
}

if (!is_dir("uploads")) { @mkdir("uploads", 0755, true); }

if (!empty($_FILES['profile_image']['name'])) {
    $target = "uploads/" . time() . "_" . basename($_FILES['profile_image']['name']);
    if(@move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
        $stmt_pic = $conn->prepare("UPDATE renters SET profile_image = ? WHERE renter_id = ?");
        $stmt_pic->bind_param("si", $target, $renter_id);
        $stmt_pic->execute();
    }
}

if ($sensitive_change && !empty($_FILES['contract_file']['name'])) {
    $target = "uploads/" . time() . "_contract_" . basename($_FILES['contract_file']['name']);
    if(@move_uploaded_file($_FILES['contract_file']['tmp_name'], $target)) {
        $stmt_cont = $conn->prepare("UPDATE renters SET contract_file = ? WHERE renter_id = ?");
        $stmt_cont->bind_param("si", $target, $renter_id);
        $stmt_cont->execute();
    }
}

sendJson(true, 'Updated Successfully');
?>