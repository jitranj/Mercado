<?php
header('Content-Type: application/json');
session_start(); 
include 'db_connect.php';


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_role = $_SESSION['role'] ?? 'staff';
if (!in_array($current_role, ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => '⛔ Access Denied: Managers/Admins only.']);
    exit;
}

$target_dir = "uploads/";
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

$allowed_docs = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$allowed_imgs = ['jpg', 'jpeg', 'png', 'webp'];

$stall_id = $_POST['stall_id'] ?? 0;
$reservation_id = $_POST['reservation_id'] ?? 0; 
$name = $_POST['renter_name'] ?? '';
$contact = $_POST['contact_number'] ?? '';
$start_date = $_POST['start_date'] ?? date('Y-m-d');
$email = $_POST['email_address'] ?? '';
$goodwill_total = $_POST['goodwill_total'] ?? 50000;
$initial_payment = $_POST['initial_payment'] ?? 0;

if (!$stall_id || !$name) {
    echo json_encode(['success' => false, 'message' => 'Missing Name or ID']);
    exit;
}

$contract_path = null;
if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
    $ext = strtolower(pathinfo($_FILES["contract_file"]["name"], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed_docs)) {
        $filename = time() . "_contract." . $ext;
        if (move_uploaded_file($_FILES["contract_file"]["tmp_name"], $target_dir . $filename)) {
            $contract_path = $target_dir . $filename;
        }
    }
}

$profile_path = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $ext = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed_imgs)) {
        $filename = time() . "_profile." . $ext;
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $filename)) {
            $profile_path = $target_dir . $filename;
        }
    }
}

$conn->begin_transaction();
try {
    $final_renter_id = 0;

    if ($reservation_id > 0) {
      
        $sql = "UPDATE renters SET 
                renter_name = ?, 
                contact_number = ?, 
                email_address = ?, 
                start_date = ?, 
                is_reservation = 0, 
                goodwill_total = ?";
        
        $types = "ssssd";
        $params = [$name, $contact, $email, $start_date, $goodwill_total];

        if ($contract_path) { 
            $sql .= ", contract_file = ?"; 
            $types .= "s"; 
            $params[] = $contract_path;
        }
        if ($profile_path) { 
            $sql .= ", profile_image = ?"; 
            $types .= "s"; 
            $params[] = $profile_path;
        }

        $sql .= " WHERE renter_id = ?";
        $types .= "i";
        $params[] = $reservation_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $final_renter_id = $reservation_id;

        $conn->query("UPDATE stalls SET status = 'occupied' WHERE id = $stall_id");

    } else {
        $stmt = $conn->prepare("INSERT INTO renters (stall_id, renter_name, contact_number, email_address, start_date, contract_file, profile_image, goodwill_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssd", $stall_id, $name, $contact, $email, $start_date, $contract_path, $profile_path, $goodwill_total);
        $stmt->execute();
        
        $final_renter_id = $conn->insert_id;
        
        $conn->query("UPDATE stalls SET status = 'occupied' WHERE id = $stall_id");
    }

    if ($initial_payment > 0) {
        $stmt3 = $conn->prepare("INSERT INTO payments (renter_id, payment_date, amount, payment_type, month_paid_for, remarks) VALUES (?, CURDATE(), ?, 'goodwill', NULL, 'Initial Deposit')");
        $stmt3->bind_param("id", $final_renter_id, $initial_payment);
        $stmt3->execute();
    }


    if ($initial_payment >= $goodwill_total && $goodwill_total > 0) {
        $first_month = date('Y-m-01', strtotime($start_date));
        
        $dry_remarks = "Goodwill Offset"; 
        $zero_amount = 0.00; 

        $stmt_silent = $conn->prepare("INSERT INTO payments (renter_id, payment_date, amount, payment_type, month_paid_for, remarks) VALUES (?, CURDATE(), ?, 'rent', ?, ?)");
        $stmt_silent->bind_param("idss", $final_renter_id, $zero_amount, $first_month, $dry_remarks);
        $stmt_silent->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>