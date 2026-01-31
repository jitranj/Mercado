<?php
header('Content-Type: application/json');
include '../db/db_connect.php';

$stall_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($stall_id === 0) { echo json_encode(['error' => 'Invalid ID']); exit; }

$response = ['stall' => null, 'renter' => null, 'history' => []];

$sql_stall = "SELECT s.stall_number, s.pasilyo, s.status, s.floor, s.monthly_rate,
                     r.renter_id, r.renter_name, r.contact_number, r.billing_account_number, r.email_address, r.start_date, 
                     r.contract_file, r.profile_image, r.goodwill_total, r.is_reservation
              FROM stalls s 
              LEFT JOIN renters r ON s.id = r.stall_id AND r.end_date IS NULL
              WHERE s.id = $stall_id";

$result = $conn->query($sql_stall);

if ($result && $row = $result->fetch_assoc()) {
    $response['stall'] = [
        'number' => $row['stall_number'],
        'pasilyo' => $row['pasilyo'],
        'floor' => $row['floor'],
        'status' => $row['status'],
        'rate' => $row['monthly_rate']
    ];
    
    if ($row['renter_id']) {
        $rid = $row['renter_id'];
        
        $gw_total = $row['goodwill_total'] ?? 0;
        $gw_paid_result = $conn->query("SELECT SUM(amount) FROM payments WHERE renter_id = $rid AND payment_type = 'goodwill'");
        $gw_paid = $gw_paid_result ? ($gw_paid_result->fetch_row()[0] ?? 0) : 0;
        
        $last_rent_sql = "SELECT MAX(month_paid_for) FROM payments WHERE renter_id = $rid AND payment_type = 'rent'";
        $last_date_res = $conn->query($last_rent_sql);
        $last_date = $last_date_res ? $last_date_res->fetch_row()[0] : null;

        if ($last_date) {
            $next_due = date('Y-m', strtotime($last_date . ' +1 month'));
        } else {
            $start = $row['start_date'] ? $row['start_date'] : date('Y-m-d');
            $next_due = date('Y-m', strtotime($start));
        }

        $response['renter'] = [
            'id' => $rid,
            'name' => $row['renter_name'],
            'contact' => $row['contact_number'],
            'billing_account_number' => $row['billing_account_number'],
            'email' => $row['email_address'],
            'since' => $row['start_date'],
            'contract' => $row['contract_file'],
            'image' => $row['profile_image'],
            'next_due' => $next_due,
            'is_reservation' => $row['is_reservation'], 
            'goodwill' => [
                'total' => (float)$gw_total,
                'paid' => (float)$gw_paid,
                'balance' => (float)$gw_total - (float)$gw_paid
            ]
        ];

        $sql_pay = "SELECT amount, month_paid_for, payment_date, payment_type, or_no 
                    FROM payments 
                    WHERE renter_id = $rid 
                    ORDER BY payment_date DESC, payment_id DESC LIMIT 6";
        
        $pay_result = $conn->query($sql_pay);
        if ($pay_result) {
            while ($pay = $pay_result->fetch_assoc()) {
                $response['history'][] = $pay;
            }
        }
    }
}
echo json_encode($response);
?>