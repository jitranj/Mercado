<?php
include 'db_connect.php';

$renter_id = $_GET['id'] ?? 0;

// 1. Fetch Tenant & Stall Info
$sql = "SELECT r.*, s.stall_number, s.pasilyo, s.monthly_rate 
        FROM renters r 
        JOIN stalls s ON r.stall_id = s.id 
        WHERE r.renter_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $renter_id);
$stmt->execute();
$renter = $stmt->get_result()->fetch_assoc();

if (!$renter) die("Tenant not found.");

// 2. Calculate Unpaid Months
$pay_sql = "SELECT MAX(month_paid_for) as last_payment FROM payments WHERE renter_id = ?";
$p_stmt = $conn->prepare($pay_sql);
$p_stmt->bind_param("i", $renter_id);
$p_stmt->execute();
$last_pay = $p_stmt->get_result()->fetch_assoc()['last_payment'];

$start_calc = $last_pay ? date('Y-m-01', strtotime($last_pay . ' +1 month')) : date('Y-m-01', strtotime($renter['start_date']));
$current_date = date('Y-m-01');

$unpaid_months = [];
$total_due = 0;
$monthly_rate = $renter['monthly_rate']; // From DB (Fixed by script)

// --- GOODWILL LOGIC ---
$goodwill_due = 0;
// If the recorded payment is less than 50k, we charge the difference/full amount
// Assuming strictly: 0 means unpaid.
if ($renter['goodwill_amount'] < 50000) {
    $goodwill_due = 50000 - $renter['goodwill_amount'];
}

$d1 = new DateTime($start_calc);
$d2 = new DateTime($current_date);
$d2->modify( '+1 month' ); 

$interval = DateInterval::createFromDateString('1 month');
$period = new DatePeriod($d1, $interval, $d2);

foreach ($period as $dt) {
    if($dt > new DateTime()) break;
    $unpaid_months[] = $dt->format("F Y");
    $total_due += $monthly_rate;
}

$grand_total = $total_due + $goodwill_due;

$period_string = "";
if (count($unpaid_months) > 0) {
    $first = strtoupper($unpaid_months[0]);
    $last = strtoupper(end($unpaid_months));
    $period_string = (count($unpaid_months) === 1) ? $first : "$first TO $last";
} else {
    $period_string = "NO PENDING RENT";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SOA - <?php echo htmlspecialchars($renter['renter_name']); ?></title>
    <style>
        body { font-family: "Times New Roman", Times, serif; padding: 40px; color: #000; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; }
        .header .sub { font-size: 14px; margin: 2px 0; }
        .header h2 { font-weight: bold; margin-top: 10px; font-size: 24px; text-transform: uppercase; }
        .soa-title { text-align: center; font-weight: bold; font-size: 22px; margin: 30px 0; text-decoration: underline; }
        .details-box { margin-bottom: 30px; font-size: 16px; }
        .details-row { display: flex; margin-bottom: 8px; }
        .label { width: 140px; font-weight: bold; }
        .value { border-bottom: 1px solid #000; flex: 1; padding-left: 10px; font-weight: 600; }
        .breakdown { width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #000; }
        .breakdown th, .breakdown td { border: 1px solid #000; padding: 12px; text-align: left; }
        .breakdown th { background: #f0f0f0; text-align: center; font-weight: bold; }
        .amount-col { text-align: right; width: 150px; }
        .footer { margin-top: 60px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .signatory { text-align: center; width: 220px; }
        .sign-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; font-weight: bold; }
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 5px; cursor: pointer; font-family: sans-serif; font-weight: bold; }
        @media print { .print-btn { display: none; } body { padding: 0; margin: 0; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">PRINT DOCUMENT</button>
    
    <div class="header">
        <div class="sub">Republic of the Philippines</div>
        <div class="sub">Province of Bulacan</div>
        <div class="sub">Municipality of Calumpit</div>
        <div class="sub">Office of the Calumpit Public Market</div>
        <h2>EL MERCADO DE CALUMPIT</h2>
    </div>

    <div class="soa-title">STATEMENT OF ACCOUNT</div>

    <div class="details-box">
        <div class="details-row">
            <span class="label">Customer Name:</span>
            <span class="value"><?php echo strtoupper($renter['renter_name']); ?></span>
        </div>
        <div class="details-row">
            <span class="label">Stall Location:</span>
            <span class="value">PASILYO <?php echo $renter['pasilyo']; ?> - STALL <?php echo $renter['stall_number']; ?></span>
        </div>
        <div class="details-row">
            <span class="label">Date Issued:</span>
            <span class="value"><?php echo date('F d, Y'); ?></span>
        </div>
    </div>

    <table class="breakdown">
        <thead>
            <tr>
                <th>PARTICULARS</th>
                <th>AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <?php if($goodwill_due > 0): ?>
            <tr>
                <td style="padding: 20px 15px;">
                    <strong>GOODWILL / RIGHTS FEE</strong><br>
                    <small>Balance Unpaid</small>
                </td>
                <td class="amount-col" style="vertical-align:top; padding-top:20px; font-weight:bold;">
                    ₱ <?php echo number_format($goodwill_due, 2); ?>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <td style="padding: 20px 15px; vertical-align: top;">
                    <strong>RENTAL FEE</strong><br><br>
                    <span style="text-decoration: underline;">Period Covered:</span><br>
                    <span style="font-weight:bold; font-style:italic; display:block; margin-top:5px;"><?php echo $period_string; ?></span>
                    <br>
                    <small>(Computation: <?php echo count($unpaid_months); ?> Months x ₱<?php echo number_format($monthly_rate, 2); ?>)</small>
                </td>
                <td class="amount-col" style="vertical-align:top; padding-top:20px; font-weight:bold;">
                    ₱ <?php echo number_format($total_due, 2); ?>
                </td>
            </tr>

            <tr style="background-color: #f9fafb;">
                <td style="text-align:right; font-weight:bold; padding-right: 20px;">TOTAL AMOUNT DUE:</td>
                <td class="amount-col" style="font-weight:bold; font-size:18px;">₱ <?php echo number_format($grand_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div class="signatory">
            <div class="sign-line">Prepared By</div>
            <small>Billing Staff</small>
        </div>
        <div class="signatory">
            <div class="sign-line">Approved By</div>
            <small>Market Administrator</small>
        </div>
    </div>
</body>
</html>