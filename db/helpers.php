<?php
function xss($data) {
    if ($data === null) return "";
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function generate_billing_account_number($conn) {
    $is_unique = false;
    $ban = "";

    while (!$is_unique) {
        $ban = mt_rand(1000000000, 9999999999); 

        $check = $conn->query("SELECT renter_id FROM renters WHERE billing_account_number = '$ban'");
        
        if ($check->num_rows == 0) {
            $is_unique = true;
        }
    }
    return $ban;
}