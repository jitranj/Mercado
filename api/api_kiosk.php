<?php
header('Content-Type: application/json');
include '../db/db_connect.php';

$action = $_POST['action'] ?? '';

if ($action === 'check_account') {
    $ban = $_POST['account_number'] ?? '';
    
    $stmt = $conn->prepare("
        SELECT r.renter_id, r.renter_name, r.stall_id, r.start_date, r.goodwill_total, 
               s.stall_number, s.pasilyo, s.monthly_rate
        FROM renters r
        JOIN stalls s ON r.stall_id = s.id
        WHERE r.billing_account_number = ? AND r.is_reservation = 0
    ");
    $stmt->bind_param("s", $ban);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $renter_id = $row['renter_id'];

        $last_rent_q = $conn->query("SELECT MAX(month_paid_for) FROM payments WHERE renter_id = $renter_id AND payment_type = 'rent'");
        $last_date = $last_rent_q->fetch_row()[0];

        if ($last_date) {
            $next_due = date('Y-m-d', strtotime($last_date . ' +1 month'));
        } else {
            $next_due = $row['start_date'];
        }
        
        $next_due_display = date('F Y', strtotime($next_due));
        

        $current_month = date('Y-m-01');
        $is_rent_paid_up = ($next_due > date('Y-m-t')); 

        $gw_paid_q = $conn->query("SELECT SUM(amount) FROM payments WHERE renter_id = $renter_id AND payment_type = 'goodwill'");
        $gw_paid = $gw_paid_q->fetch_row()[0] ?? 0;
        $gw_balance = $row['goodwill_total'] - $gw_paid;

        echo json_encode([
            'success' => true,
            'renter_id' => $row['renter_id'],
            'name' => $row['renter_name'],
            'stall' => $row['pasilyo'] . ' #' . $row['stall_number'],
            'monthly_rate' => (float)$row['monthly_rate'],
            'next_due_date' => $next_due, 
            'next_due_display' => $next_due_display,
            'is_rent_paid_up' => $is_rent_paid_up,
            'goodwill_balance' => (float)$gw_balance,
            'is_goodwill_paid_up' => ($gw_balance <= 0)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account Number not found.']);
    }
    exit;
}

if ($action === 'pay') {
    $renter_id = $_POST['renter_id'];
    $amount = (float)$_POST['amount'];
    $type = $_POST['payment_type']; 
    $month_for = $_POST['month_for'] ?? null; 
    $user_ref = $_POST['reference_number'] ?? 'KIOSK-CASH'; 

    if ($amount < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid Amount']);
        exit;
    }

    if ($type === 'goodwill') {
        $q_check = $conn->query("SELECT goodwill_total, (SELECT SUM(amount) FROM payments WHERE renter_id = $renter_id AND payment_type='goodwill') as paid FROM renters WHERE renter_id = $renter_id");
        $d_check = $q_check->fetch_assoc();
        $real_balance = $d_check['goodwill_total'] - ($d_check['paid'] ?? 0);

        if ($amount > $real_balance) {
            echo json_encode(['success' => false, 'message' => "Overpayment! Balance is only " . number_format($real_balance,2)]);
            exit;
        }
        $month_for = null;
    }

    $stmt = $conn->prepare("INSERT INTO payments (renter_id, payment_date, amount, payment_type, month_paid_for, remarks, or_no) VALUES (?, CURDATE(), ?, ?, ?, 'Kiosk Payment', ?)");
    
    $stmt->bind_param("idsss", $renter_id, $amount, $type, $month_for, $user_ref);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'ref' => $user_ref]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Error']);
    }
    exit;
}
?>