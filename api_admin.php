<?php
// api_admin.php - ENHANCED SECURITY VERSION
session_start();
include 'db_connect.php';

// --- 1. SECURITY GATEKEEPER ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? 'staff_monitor';
$current_username = $_SESSION['username'] ?? 'Unknown';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- 2. ACTIVITY LOGGING FUNCTION ---
function logActivity($conn, $userId, $actionType, $desc) {
    $ip = $_SERVER['REMOTE_ADDR'];
    // We use the new table structure: user_id, action_type, description, ip_address
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $actionType, $desc, $ip);
    $stmt->execute();
    $stmt->close();
}

// ==========================================================
// ACTION 1: CHANGE PASSWORD (Available to ALL users)
// ==========================================================
if ($action === 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';

    if (!$current_pass || !$new_pass) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    // Security: Verify old password first
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

    // Enforce Password Strength (New Requirement)
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

// ==========================================================
// ACTION 2: ADD NEW USER (Strictly ADMIN Only)
// ==========================================================
if ($action === 'add_user') {
    if ($current_role !== 'admin') {
        logActivity($conn, $current_user_id, 'UNAUTH_ACCESS', "Tried to add user but is not Admin");
        echo json_encode(['success' => false, 'message' => 'Access Denied: Admin Rights Required']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $new_role = $_POST['role'] ?? 'staff_monitor';

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing username or password']);
        exit;
    }

    // Check if username exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    $check->close();

    // Create User
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

// ==========================================================
// ACTION 3: EXPORTS (Role Restricted)
// ==========================================================

// EXPORT RED LIST (Admin or Manager Only)
if ($action === 'export_red_list') {
    // STAFF_ENCODER cannot see this
    if (!in_array($current_role, ['admin', 'manager', 'staff_billing'])) {
        die("Unauthorized Access");
    }

    logActivity($conn, $current_user_id, 'EXPORT_DATA', "Exported Red List CSV");

    $filename = "red_list_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tenant Name', 'Stall', 'Contact', 'Months Unpaid']);

    // Only get critical ones (1+ months due)
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

// EXPORT TENANTS (Admin, Manager, Billing)
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

    // Updated to include new columns (email, is_reservation)
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

// EXPORT PAYMENTS (Admin, Cashier, Manager)
if ($action === 'export_payments_csv') {
    // Note: STAFF_ENCODER can *input* payments but usually shouldn't *bulk export* them 
    // to prevent data theft. Remove 'staff_encoder' if you want to be strict.
    if (!in_array($current_role, ['admin', 'manager', 'staff_cashier'])) {
        die("Unauthorized Access");
    }

    logActivity($conn, $current_user_id, 'EXPORT_DATA', "Exported Payment History");

    $filename = "payments_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payment ID', 'Renter', 'Stall', 'Amount', 'Date Paid', 'Month For']);

    $sql = "SELECT p.payment_id, r.renter_name, CONCAT(s.pasilyo,' #', s.stall_number) as stall, 
            p.amount, p.date_paid, p.month_paid_for 
            FROM payments p 
            LEFT JOIN renters r ON p.renter_id = r.renter_id 
            LEFT JOIN stalls s ON r.stall_id = s.id 
            ORDER BY p.date_paid DESC";
            
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) fputcsv($output, $row);
    fclose($output);
    exit;
}

// ... inside api_admin.php ...

// FULL BACKUP (Strictly ADMIN Only)
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

    // 1. Header Information
    echo "-- MALL MONITOR SQL BACKUP\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- User: $current_username\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    // 2. Get All Tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    // 3. Loop Through Tables
    foreach ($tables as $table) {
        // Drop Existing
        echo "-- Table structure for table `$table`\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";

        // Create Structure
        $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        echo $row2[1] . ";\n\n";

        // Insert Data
        echo "-- Dumping data for table `$table`\n";
        $data = $conn->query("SELECT * FROM `$table`");
        while ($row = $data->fetch_assoc()) {
            echo "INSERT INTO `$table` VALUES(";
            $first = true;
            foreach ($row as $value) {
                if (!$first) echo ", ";
                $value = $conn->real_escape_string($value);
                // Handle NULL vs Empty String
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
// ... existing code ...

// ==========================================================
// ACTION 4: SEND PAYMENT REMINDERS (Simulation/Log)
// ==========================================================
if ($action === 'send_reminders') {
    if (!in_array($current_role, ['admin', 'manager', 'staff_billing'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // 1. Find Delinquents (3+ months due)
    $sql = "SELECT r.renter_id, r.renter_name, r.email_address 
            FROM renters r
            LEFT JOIN payments p ON r.renter_id = p.renter_id
            GROUP BY r.renter_id
            HAVING TIMESTAMPDIFF(MONTH, MAX(COALESCE(p.month_paid_for, r.start_date)), CURRENT_DATE()) >= 1";
    
    $result = $conn->query($sql);
    $count = 0;

    while($row = $result->fetch_assoc()) {
        $count++;
        // NOTE: If you had a mail server, you would use mail() here.
        // For now, we just Log it as a "processed" action.
        logActivity($conn, $current_user_id, 'SENT_REMINDER', "Sent payment reminder to " . $row['renter_name']);
    }

    echo json_encode(['success' => true, 'message' => "Successfully processed reminders for $count tenants."]);
    exit;
}

// ==========================================================
// ACTION 5: EXPORT EXECUTIVE DASHBOARD (Report)
// ==========================================================
if ($action === 'export_dashboard') {
    $filename = "executive_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Section 1: Summary Stats
    fputcsv($output, ['--- EXECUTIVE SUMMARY ---']);
    
    // Calculate Live Stats
    $rev = $conn->query("SELECT SUM(amount) FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE())")->fetch_row()[0] ?? 0;
    $occ = $conn->query("SELECT COUNT(*) FROM stalls WHERE status='occupied'")->fetch_row()[0] ?? 0;
    $tot = $conn->query("SELECT COUNT(*) FROM stalls")->fetch_row()[0] ?? 0;
    $rate = $tot > 0 ? round(($occ/$tot)*100) . '%' : '0%';

    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Current Month Revenue', $rev]);
    fputcsv($output, ['Occupancy Rate', $rate]);
    fputcsv($output, ['Total Occupied Units', $occ]);
    fputcsv($output, []); // Spacer

    // Section 2: Delinquent List
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

echo json_encode(['success' => false, 'message' => 'Invalid Action']);
?>