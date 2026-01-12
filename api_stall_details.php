<?php
header('Content-Type: application/json');
include 'db_connect.php';

$stall_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($stall_id === 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$response = [
    'stall' => null,
    'renter' => null,
    'history' => []
];


$sql_stall = "SELECT s.stall_number, s.pasilyo, s.status, s.floor, 
                     r.renter_id, r.renter_name, r.contact_number, r.start_date, 
                     r.contract_file, r.profile_image
              FROM stalls s 
              LEFT JOIN renters r ON s.id = r.stall_id AND r.end_date IS NULL
              WHERE s.id = $stall_id";

$result = $conn->query($sql_stall);

if ($row = $result->fetch_assoc()) {
    $response['stall'] = [
        'number' => $row['stall_number'],
        'pasilyo' => $row['pasilyo'],
        'floor' => $row['floor'],
        'status' => $row['status']
    ];
    
    if ($row['renter_id']) {
        $response['renter'] = [
            'id' => $row['renter_id'],
            'name' => $row['renter_name'],
            'contact' => $row['contact_number'],
            'since' => $row['start_date'],
            'contract' => $row['contract_file'],
            'image' => $row['profile_image'] 
        ];

        $renter_id = $row['renter_id'];
        $sql_pay = "SELECT amount, month_paid_for, payment_date, remarks 
                    FROM payments 
                    WHERE renter_id = $renter_id 
                    ORDER BY month_paid_for DESC LIMIT 6";
        
        $pay_result = $conn->query($sql_pay);
        while ($pay = $pay_result->fetch_assoc()) {
            $response['history'][] = $pay;
        }
    }
}

echo json_encode($response);
?>