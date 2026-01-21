<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? 'staff_monitor';
$current_username = $_SESSION['username'] ?? 'Unknown';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function logActivity($conn, $userId, $actionType, $desc) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $actionType, $desc, $ip);
    $stmt->execute();
    $stmt->close();
}


if ($action === 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    if (!$current_pass || !$new_pass) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $stmt->bind_result($stored_hash);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    $stmt->close();

    if (!password_verify($current_pass, $stored_hash)) {
        logActivity($conn, $current_user_id, 'SECURITY_FAIL', "Failed password change attempt");
        echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
        exit;
    }

    if (strlen($new_pass) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
        exit;
    }

    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $u = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $u->bind_param("si", $new_hash, $current_user_id);
    
    if ($u->execute()) {
        logActivity($conn, $current_user_id, 'PASSWORD_CHANGE', "User changed their password");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    exit;
}

if ($action === 'add_user') {
    if ($current_role !== 'admin') {
        logActivity($conn, $current_user_id, 'UNAUTH_ACCESS', "Tried to add user but is not Admin");
        echo json_encode(['success' => false, 'message' => 'Access Denied: Admin Rights Required']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $new_role = $_POST['role'] ?? 'staff_monitor';
    $admin_pass = $_POST['admin_password'] ?? ''; 

    if (!$username || !$password || !$admin_pass) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields (Username, Password, or Admin Auth)']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $stmt->bind_result($admin_hash);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($admin_pass, $admin_hash)) {
        logActivity($conn, $current_user_id, 'SECURITY_FAIL', "Admin failed password check while creating user [$username]");
        echo json_encode(['success' => false, 'message' => ' Incorrect Admin Password']);
        exit;
    }

    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    $check->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hash, $new_role);
    
    if ($stmt->execute()) {
        logActivity($conn, $current_user_id, 'USER_CREATED', "Created user [$username] with role [$new_role]");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}


if ($action === 'export_red_list') {
    if (!in_array($current_role, ['admin', 'manager', 'staff_billing'])) {
        die("Unauthorized Access");
    }

    logActivity($conn, $current_user_id, 'EXPORT_DATA', "Exported Red List CSV");

    $filename = "red_list_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tenant Name', 'Stall', 'Contact', 'Months Unpaid']);

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
    exit;
}

if ($action === 'export_tenants_csv') {
    if (!in_array($current_role, ['admin', 'manager', 'staff_billing'])) {
        die("Unauthorized Access");
    }

    logActivity($conn, $current_user_id, 'EXPORT_DATA', "Exported Tenant Masterlist");

    $filename = "tenants_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Stall', 'Contact', 'Email', 'Start Date', 'Is Reservation']);

    $sql = "SELECT r.renter_id, r.renter_name, CONCAT(s.pasilyo, ' #', s.stall_number) as stall, 
            r.contact_number, r.email_address, r.start_date, 
            IF(r.is_reservation=1, 'YES', 'NO') as reserved
            FROM renters r 
            LEFT JOIN stalls s ON r.stall_id = s.id";
            
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) fputcsv($output, $row);
    fclose($output);
    exit;
}

if ($action === 'export_payments_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['Payment ID', 'Renter', 'Stall', 'Amount', 'Date Paid', 'Month For', 'Type', 'OR No']);

    $sql = "SELECT 
                p.payment_id, 
                r.renter_name, 
                CONCAT(s.floor, '-', s.pasilyo, ' ', s.stall_number) as stall_loc, 
                p.amount, 
                p.payment_date,  
                p.month_paid_for,
                p.payment_type,
                p.or_no
            FROM payments p
            LEFT JOIN renters r ON p.renter_id = r.renter_id
            LEFT JOIN stalls s ON r.stall_id = s.id
            ORDER BY p.payment_date DESC";

    $result = $conn->query($sql);
    
    if($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}


if ($action === 'export_backup') {
    if ($current_role !== 'admin') {
        logActivity($conn, $current_user_id, 'SECURITY_ALERT', "Unauthorized Backup Attempt");
        die("Access Denied");
    }

    logActivity($conn, $current_user_id, 'SYSTEM_BACKUP', "Downloaded Full SQL Backup");

    $backup_name = 'mall_backup_' . date("Y-m-d_H-i") . '.sql';
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$backup_name\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- MALL MONITOR SQL BACKUP\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- User: $current_username\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        echo "-- Table structure for table `$table`\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";

        $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        echo $row2[1] . ";\n\n";

        echo "-- Dumping data for table `$table`\n";
        $data = $conn->query("SELECT * FROM `$table`");
        while ($row = $data->fetch_assoc()) {
            echo "INSERT INTO `$table` VALUES(";
            $first = true;
            foreach ($row as $value) {
                if (!$first) echo ", ";
                $value = $conn->real_escape_string($value);
                if ($value === null) echo "NULL";
                else echo "'" . $value . "'";
                $first = false;
            }
            echo ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

if ($action === 'send_reminders') {
    if (!in_array($current_role, ['admin', 'manager', 'staff_billing'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $sql = "SELECT r.renter_id, r.renter_name, r.email_address 
            FROM renters r
            LEFT JOIN payments p ON r.renter_id = p.renter_id
            GROUP BY r.renter_id
            HAVING TIMESTAMPDIFF(MONTH, MAX(COALESCE(p.month_paid_for, r.start_date)), CURRENT_DATE()) >= 1";
    
    $result = $conn->query($sql);
    $count = 0;

    while($row = $result->fetch_assoc()) {
        $count++;

        logActivity($conn, $current_user_id, 'SENT_REMINDER', "Sent payment reminder to " . $row['renter_name']);
    }

    echo json_encode(['success' => true, 'message' => "Successfully processed reminders for $count tenants."]);
    exit;
}

if ($action === 'export_dashboard') {
    $filename = "executive_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['--- EXECUTIVE SUMMARY ---']);
    
    $rev = $conn->query("SELECT SUM(amount) FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE())")->fetch_row()[0] ?? 0;
    $occ = $conn->query("SELECT COUNT(*) FROM stalls WHERE status='occupied'")->fetch_row()[0] ?? 0;
    $tot = $conn->query("SELECT COUNT(*) FROM stalls")->fetch_row()[0] ?? 0;
    $rate = $tot > 0 ? round(($occ/$tot)*100) . '%' : '0%';

    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Current Month Revenue', $rev]);
    fputcsv($output, ['Occupancy Rate', $rate]);
    fputcsv($output, ['Total Occupied Units', $occ]);
    fputcsv($output, []); 

    fputcsv($output, ['--- DELINQUENT TENANTS LIST ---']);
    fputcsv($output, ['Tenant Name', 'Stall', 'Contact', 'Months Due']);

    $sql = "SELECT r.renter_name, CONCAT(s.pasilyo, ' #', s.stall_number) as stall, r.contact_number,
            TIMESTAMPDIFF(MONTH, MAX(COALESCE(p.month_paid_for, r.start_date)), CURRENT_DATE()) as months_due
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
    exit;
}


if ($action === 'get_users') {
    if ($current_role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $users = [];
    $sql = "SELECT user_id, username, role FROM users ORDER BY user_id ASC";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
    exit;
}


if ($action === 'admin_update_user') {
    if ($current_role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Access Denied']);
        exit;
    }

    $target_id = $_POST['target_user_id'] ?? 0;
    $new_user = trim($_POST['username'] ?? '');
    $new_role = $_POST['role'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $admin_pass = $_POST['admin_password'] ?? ''; 

    if (!$target_id || !$new_user || !$admin_pass) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $current_user_id); 
    $stmt->execute();
    $stmt->bind_result($admin_hash);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($admin_pass, $admin_hash)) {
        logActivity($conn, $current_user_id, 'SECURITY_FAIL', "Admin failed password check while editing user $target_id");
        echo json_encode(['success' => false, 'message' => '❌ Incorrect Admin Password']);
        exit;
    }

    $chk = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $chk->bind_param("si", $new_user, $target_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit;
    }

    $updates = ["username = ?", "role = ?"];
    $types = "ss";
    $params = [$new_user, $new_role];

    if (!empty($new_pass)) {
        if (strlen($new_pass) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password too short (min 8)']);
            exit;
        }
        $updates[] = "password_hash = ?";
        $types .= "s";
        $params[] = password_hash($new_pass, PASSWORD_DEFAULT);
    }

    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
    $types .= "i";
    $params[] = $target_id;

    $update_stmt = $conn->prepare($sql);
    $update_stmt->bind_param($types, ...$params);

    if ($update_stmt->execute()) {
        logActivity($conn, $current_user_id, 'USER_EDIT', "Updated details for User ID $target_id");
        echo json_encode(['success' => true, 'message' => ' User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}


if ($action === 'delete_user') {
    if ($current_role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Access Denied']);
        exit;
    }

    $target_id = $_POST['target_user_id'] ?? 0;
    $admin_pass = $_POST['admin_password'] ?? '';

    if (!$target_id || !$admin_pass) {
        echo json_encode(['success' => false, 'message' => 'Missing ID or Password']);
        exit;
    }

    if ($target_id == $current_user_id) {
        echo json_encode(['success' => false, 'message' => '❌ You cannot delete your own account while logged in.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $stmt->bind_result($admin_hash);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($admin_pass, $admin_hash)) {
        logActivity($conn, $current_user_id, 'SECURITY_FAIL', "Admin failed password check while deleting user $target_id");
        echo json_encode(['success' => false, 'message' => ' Incorrect Admin Password']);
        exit;
    }

    $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $del->bind_param("i", $target_id);
    
    if ($del->execute()) {
        logActivity($conn, $current_user_id, 'USER_DELETED', "Deleted User ID $target_id");
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid Action']);
?>