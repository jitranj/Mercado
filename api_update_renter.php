<?php
header('Content-Type: application/json');
session_start();
include 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$renter_id = $_POST['renter_id'] ?? 0;
$admin_pass = $_POST['admin_password'] ?? '';

// 2. Verify Admin Password (THE SECURITY FEATURE)
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

// 3. Prepare Data for Update
$name = $_POST['renter_name'];
$contact = $_POST['contact_number'];
$email = $_POST['email_address'];
$start_date = $_POST['start_date'];

// Handle File Uploads (Optional updates)
$contract_sql = "";
$profile_sql = "";
$types = "ssssi"; // name, contact, email, start, renter_id
$params = [$name, $contact, $email, $start_date, $renter_id];

// Check Contract
if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
    $target_dir = "uploads/";
    $ext = pathinfo($_FILES["contract_file"]["name"], PATHINFO_EXTENSION);
    $filename = time() . "_contract." . $ext;
    if(move_uploaded_file($_FILES["contract_file"]["tmp_name"], $target_dir . $filename)) {
        $contract_sql = ", contract_file = ?";
        // Insert into params array at correct position (before renter_id)
        array_splice($params, 4, 0, $target_dir . $filename);
        $types = "sssssi"; // Added one string
    }
}

// Check Profile Pic
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $target_dir = "uploads/";
    $ext = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
    $filename = time() . "_profile." . $ext;
    if(move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $filename)) {
        $profile_sql = ", profile_image = ?";
        // Insert into params array at correct position (before renter_id)
        $pos = ($contract_sql) ? 5 : 4; 
        array_splice($params, $pos, 0, $target_dir . $filename);
        $types .= "s";
    }
}

// 4. Perform Update
$sql = "UPDATE renters SET renter_name=?, contact_number=?, email_address=?, start_date=? $contract_sql $profile_sql WHERE renter_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => '✅ Tenant details updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>