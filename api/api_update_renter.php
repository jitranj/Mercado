<?php
header('Content-Type: application/json');
session_start();
include '../db/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$renter_id = $_POST['renter_id'] ?? 0;
$admin_pass = $_POST['admin_password'] ?? '';

$stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($hash);
$stmt->fetch();
$stmt->close();

if (!password_verify($admin_pass, $hash)) {
    echo json_encode(['success' => false, 'message' => '❌ Incorrect Password. Changes rejected.']);
    exit;
}

$name = $_POST['renter_name'];
$contact = $_POST['contact_number'];
$email = $_POST['email_address'];
$start_date = $_POST['start_date'];

$contract_sql = "";
$profile_sql = "";
$types = "ssssi"; 
$params = [$name, $contact, $email, $start_date, $renter_id];

if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
    $target_dir = "uploads/";
    $ext = pathinfo($_FILES["contract_file"]["name"], PATHINFO_EXTENSION);
    $filename = time() . "_contract." . $ext;
    if(move_uploaded_file($_FILES["contract_file"]["tmp_name"], $target_dir . $filename)) {
        $contract_sql = ", contract_file = ?";
        array_splice($params, 4, 0, $target_dir . $filename);
        $types = "sssssi"; 
    }
}

if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $target_dir = "uploads/";
    $ext = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
    $filename = time() . "_profile." . $ext;
    if(move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $filename)) {
        $profile_sql = ", profile_image = ?";
        $pos = ($contract_sql) ? 5 : 4; 
        array_splice($params, $pos, 0, $target_dir . $filename);
        $types .= "s";
    }
}

$sql = "UPDATE renters SET renter_name=?, contact_number=?, email_address=?, start_date=? $contract_sql $profile_sql WHERE renter_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '✅ Tenant details updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>