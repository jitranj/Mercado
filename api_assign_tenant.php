<?php
header('Content-Type: application/json');
include 'db_connect.php';

$response = ['success' => false, 'message' => ''];

$target_dir = "uploads/";
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

$stall_id = $_POST['stall_id'] ?? 0;
$name = $_POST['renter_name'] ?? '';
$contact = $_POST['contact_number'] ?? '';
$start_date = $_POST['start_date'] ?? date('Y-m-d');

if (!$stall_id || !$name) {
    echo json_encode(['success' => false, 'message' => 'Missing Name or ID']);
    exit;
}

$contract_path = null;
if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
    $ext = pathinfo($_FILES["contract_file"]["name"], PATHINFO_EXTENSION);
    $filename = time() . "_contract." . $ext;
    if (move_uploaded_file($_FILES["contract_file"]["tmp_name"], $target_dir . $filename)) {
        $contract_path = $target_dir . $filename;
    }
}

$profile_path = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $ext = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
    $filename = time() . "_profile." . $ext;
    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $filename)) {
        $profile_path = $target_dir . $filename;
    }
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO renters (stall_id, renter_name, contact_number, start_date, contract_file, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $stall_id, $name, $contact, $start_date, $contract_path, $profile_path);
    $stmt->execute();
    
    $stmt2 = $conn->prepare("UPDATE stalls SET status = 'occupied' WHERE id = ?");
    $stmt2->bind_param("i", $stall_id);
    $stmt2->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>