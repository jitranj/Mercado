<?php
header('Content-Type: application/json');
include 'db_connect.php';

$response = [];

// 1. OCCUPANCY RATE
$total = $conn->query("SELECT COUNT(*) as c FROM stalls")->fetch_assoc()['c'];
$occupied = $conn->query("SELECT COUNT(*) as c FROM stalls WHERE status = 'occupied'")->fetch_assoc()['c'];

$response['occupancy'] = [
    'rate' => $total > 0 ? round(($occupied / $total) * 100) : 0,
    'total' => $total,
    'occupied' => $occupied,
    'vacant' => $total - $occupied
];

// 2. REVENUE (Current Month)
$month_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments 
              WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
              AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
$response['revenue_month'] = $conn->query($month_sql)->fetch_assoc()['total'];

// 3. REVENUE TREND (Last 6 Months)
$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime("first day of -$i months");
    $db_date = date("Y-m", $timestamp); 
    $label = date("M", $timestamp);     

    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$db_date'";
    
    $trend[] = [
        'month' => $label,
        'amount' => $conn->query($sql)->fetch_assoc()['total']
    ];
}
$response['revenue_trend'] = $trend;

// 4. RED LIST (Fix: Handle tenants with ZERO payments)
$delinquent_sql = "
    SELECT r.renter_id, r.renter_name, s.stall_number, s.pasilyo,
           CASE 
               WHEN MAX(p.month_paid_for) IS NOT NULL 
               THEN TIMESTAMPDIFF(MONTH, MAX(p.month_paid_for), CURRENT_DATE())
               ELSE TIMESTAMPDIFF(MONTH, r.start_date, CURRENT_DATE())
           END as months_due
    FROM renters r
    JOIN stalls s ON r.stall_id = s.id
    LEFT JOIN payments p ON r.renter_id = p.renter_id
    WHERE r.end_date IS NULL
    GROUP BY r.renter_id
    HAVING months_due >= 3
    ORDER BY months_due DESC
    LIMIT 10
";

$result = $conn->query($delinquent_sql);
$red_list = [];
while($row = $result->fetch_assoc()) {
    $red_list[] = $row;
}
$response['red_list'] = $red_list;

echo json_encode($response);
?>