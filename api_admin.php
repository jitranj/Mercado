<?php
// api_admin.php
session_start();
if (!isset($_SESSION['user_id'])) { exit; }
include 'db_connect.php';

$role = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. CHANGE PASSWORD (requires current password verification)
if ($action === 'change_password') {
    $user_id = $_SESSION['user_id'];
    $current = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    if (!$current || !$new_pass) { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($stored_hash);
    if (!$stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }
    $stmt->close();

    if (!password_verify($current, $stored_hash)) { echo json_encode(['success' => false, 'message' => 'Current password incorrect']); exit; }

    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $u = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $u->bind_param("si", $hash, $user_id);
    if ($u->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Update failed']);
}

// 2. ADD NEW USER (Admins only)
if ($action === 'add_user') {
    if ($role !== 'admin') { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $newrole = $_POST['role'] ?? 'staff_monitor'; // avoid overwriting session role variable

    if (!$username || !$password) { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) { echo json_encode(['success' => false, 'message' => 'Username taken']); exit; }
    $check->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hash, $newrole);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Creation failed']);
}

// 3. EXPORT RED LIST
if ($action === 'export_red_list') {
    $filename = "red_list_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tenant Name', 'Stall', 'Contact', 'Months Unpaid', 'Total Due']);

    $sql = "SELECT r.renter_name, CONCAT(s.pasilyo, ' #', s.stall_number) as stall, r.contact_number,
            TIMESTAMPDIFF(MONTH, MAX(p.month_paid_for), CURRENT_DATE()) as months_due
            FROM renters r
            JOIN stalls s ON r.stall_id = s.id
            LEFT JOIN payments p ON r.renter_id = p.renter_id
            WHERE r.end_date IS NULL
            GROUP BY r.renter_id
            HAVING months_due >= 1
            ORDER BY months_due DESC";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
}

// EXPORT TENANTS CSV (Admin or Manager)
if ($action === 'export_tenants_csv') {
    if (!in_array($role, ['admin','manager'])) { echo "Unauthorized"; exit; }
    $filename = "tenants_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Renter ID','Name','Stall','Contact','Start Date','End Date']);

    $sql = "SELECT r.renter_id, r.renter_name, CONCAT(s.pasilyo, ' #', s.stall_number) as stall, r.contact_number, r.start_date, r.end_date FROM renters r LEFT JOIN stalls s ON r.stall_id = s.id";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) fputcsv($output, $row);
    fclose($output);
}

// EXPORT PAYMENTS CSV (Admin or Cashier)
if ($action === 'export_payments_csv') {
    if (!in_array($role, ['admin','staff_cashier'])) { echo "Unauthorized"; exit; }
    $filename = "payments_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payment ID','Renter','Stall','Amount','Date Paid','Month For']);

    $sql = "SELECT p.payment_id, r.renter_name, CONCAT(s.pasilyo,' #', s.stall_number) as stall, p.amount, p.date_paid, p.month_paid_for FROM payments p LEFT JOIN renters r ON p.renter_id = r.renter_id LEFT JOIN stalls s ON r.stall_id = s.id ORDER BY p.date_paid DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) fputcsv($output, $row);
    fclose($output);
}

// EXPORT FULL SQL BACKUP (Admin only)
if ($action === 'export_backup') {
    if ($role !== 'admin') { echo "Unauthorized"; exit; }
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) $tables[] = $row[0];

    $sql_script = "";
    foreach ($tables as $table) {
        $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
        $sql_script .= "\n\n" . $row2[1] . ";\n\n";
        $result = $conn->query("SELECT * FROM $table");
        $num_fields = $result->field_count;
        
        while ($row = $result->fetch_row()) {
            $sql_script .= "INSERT INTO $table VALUES(";
            for ($j=0; $j<$num_fields; $j++) {
                $row[$j] = $conn->real_escape_string($row[$j]);
                $sql_script .= (isset($row[$j])) ? '"'.$row[$j].'"' : '""';
                if ($j < ($num_fields-1)) $sql_script .= ',';
            }
            $sql_script .= ");\n";
        }
    }

    $backup_name = 'mall_backup_' . date("Y-m-d_H-i") . '.sql';
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$backup_name\"");
    echo $sql_script;
}
?>