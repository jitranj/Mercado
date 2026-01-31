<?php
header('Content-Type: application/json');
include '../db/db_connect.php';

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]); 
    exit;
}


$sql = "SELECT s.id, s.stall_number, s.pasilyo, s.floor, r.renter_name, r.profile_image 
        FROM stalls s 
        LEFT JOIN renters r ON s.id = r.stall_id 
        WHERE (r.renter_name LIKE ? OR s.stall_number LIKE ?)
        AND s.status = 'occupied' 
        AND r.end_date IS NULL"; 

$stmt = $conn->prepare($sql);
$searchTerm = "%" . $query . "%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$matches = [];
while ($row = $result->fetch_assoc()) {
    $matches[] = [
        'id' => $row['id'],
        'label' => $row['renter_name'] ? $row['renter_name'] : "Stall " . $row['stall_number'],
        'sub' => "Floor " . $row['floor'] . " - " . $row['pasilyo'],
        'floor' => $row['floor'],
        'img' => $row['profile_image'] ?? 'style/default_avatar.png'
    ];
}

echo json_encode($matches);
?>