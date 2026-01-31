<?php
include '../db/db_connect.php';

function renderSOA($conn, $renter_id) {
    $sql = "SELECT r.*, s.stall_number, s.pasilyo, s.monthly_rate 
            FROM renters r 
            JOIN stalls s ON r.stall_id = s.id 
            WHERE r.renter_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $renter_id);
    $stmt->execute();
    $renter = $stmt->get_result()->fetch_assoc();

    if (!$renter) return;

    $pay_sql = "SELECT MAX(month_paid_for) as last_payment FROM payments WHERE renter_id = ? AND payment_type = 'rent'";
    $p_stmt = $conn->prepare($pay_sql);
    $p_stmt->bind_param("i", $renter_id);
    $p_stmt->execute();
    $last_pay = $p_stmt->get_result()->fetch_assoc()['last_payment'];

    $start_calc = $last_pay ? date('Y-m-01', strtotime($last_pay . ' +1 month')) : date('Y-m-01', strtotime($renter['start_date']));
    $current_date = date('Y-m-01');

    $unpaid_months = [];
    $total_due = 0;
    $monthly_rate = $renter['monthly_rate'];

    $gw_total = isset($renter['goodwill_total']) ? floatval($renter['goodwill_total']) : 50000.00;
    $g_sql = "SELECT SUM(amount) FROM payments WHERE renter_id = ? AND payment_type = 'goodwill'";
    $g_stmt = $conn->prepare($g_sql);
    $g_stmt->bind_param("i", $renter_id);
    $g_stmt->execute();
    $result_g = $g_stmt->get_result()->fetch_row();
    $gw_paid = $result_g[0] ?? 0;

    $goodwill_due = $gw_total - $gw_paid;
    if($goodwill_due < 0) $goodwill_due = 0;

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
    <div class="page-container">
        <div class="header-grid">
            <div class="logo-box">
                <img src="../uploads/logo_lgu.png" alt="LGU Logo" onerror="this.style.display='none'">
            </div>
            <div class="header-text">
                <div class="republic">Republic of the Philippines</div>
                <div class="province">Province of Bulacan</div>
                <div class="municipality">Municipality of Calumpit</div>
                <div class="office">Office of the Calumpit Public Market</div>
                <h1 class="market-name">EL MERCADO DE CALUMPIT</h1>
            </div>
            <div class="logo-box">
                <img src="../uploads/logo_market.png" alt="Market Logo" onerror="this.style.display='none'">
            </div>
        </div>

        <div class="soa-title">STATEMENT OF ACCOUNT</div>

        <div class="recipient-box">
            <table class="info-table">
                <tr>
                    <td class="label">Customer Name:</td>
                    <td class="value"><strong><?php echo strtoupper($renter['renter_name']); ?></strong></td>
                </tr>
                <tr>
                    <td class="label">Stall Location:</td>
                    <td class="value">PASILYO <?php echo $renter['pasilyo']; ?> - STALL <?php echo $renter['stall_number']; ?></td>
                </tr>
                <tr>
                    <td class="label">Date Issued:</td>
                    <td class="value"><?php echo date('F d, Y'); ?></td>
                </tr>
                <tr>
                    <td class="label">Subject:</td>
                    <td class="value">BILLING STATEMENT (<?php echo date('F Y'); ?>)</td>
                </tr>
            </table>
        </div>

        <p class="intro-text">
            We are writing to provide you with a statement of your account for the period covering 
            <strong><?php echo $period_string; ?></strong>. Please find the details below:
        </p>

        <table class="breakdown">
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th>PERIOD / DETAILS</th>
                    <th style="text-align:right;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php if($goodwill_due > 0): ?>
                <tr>
                    <td><strong>GOODWILL / RIGHTS FEE</strong></td>
                    <td>
                        Total Agreement: ₱<?php echo number_format($gw_total, 2); ?><br>
                        <small>Less: Paid (₱<?php echo number_format($gw_paid, 2); ?>)</small>
                    </td>
                    <td class="amount-col">₱ <?php echo number_format($goodwill_due, 2); ?></td>
                </tr>
                <?php endif; ?>

                <?php if($total_due > 0): ?>
                <tr>
                    <td style="vertical-align:top;"><strong>RENTAL FEE</strong></td>
                    <td>
                        <?php 
                            $count = 0;
                            foreach($unpaid_months as $m) {
                                if($count < 5) echo $m . "<br>";
                                $count++;
                            }
                            if($count >= 5) echo "<i>...and " . ($count-5) . " more months</i>";
                        ?>
                    </td>
                    <td class="amount-col" style="vertical-align:top;">₱ <?php echo number_format($total_due, 2); ?></td>
                </tr>
                <?php endif; ?>

                <tr class="total-row">
                    <td colspan="2" style="text-align:right; padding-right:15px;">TOTAL AMOUNT DUE:</td>
                    <td class="amount-col">₱ <?php echo number_format($grand_total, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="bank-details">
            <h4>BANK DETAILS FOR PAYMENT:</h4>
            <table>
                <tr>
                    <td width="150"><strong>ACCOUNT NAME:</strong></td>
                    <td>MUNICIPALITY OF CALUMPIT GEN. FUND LOCAL ECONOMIC ENTERPRISE</td>
                </tr>
                <tr>
                    <td><strong>ACCOUNT NUMBER:</strong></td>
                    <td>(SAVINGS ACCOUNT) 2792-1066-28</td>
                </tr>
                <tr>
                    <td><strong>BANK NAME:</strong></td>
                    <td>LANDBANK OF THE PHILIPPINES</td>
                </tr>
            </table>
        </div>

        <div class="footer-grid">
            <div class="sign-box">
                <div class="role">Prepared By:</div>
                <br><br>
                <div class="name">MARY GRACE DOMINGO CARIÑO</div>
                <div class="title">Market Supervisor Assistant</div>
            </div>
            <div class="sign-box">
                <div class="role">Noted By:</div>
                <br><br>
                <div class="name">BERNADETTE B. CRUZ, J.D.</div>
                <div class="title">Municipal Administrator, LGU Calumpit</div>
            </div>
        </div>

        <div class="notes-section">
            <strong>NOTE:</strong>
            <ol>
                <li>For proof of payment, please visit El Mercado de Calumpit Admin Office.</li>
                <li>This billing statement is valid for 14 days without contest.</li>
                <li style="color:red; font-weight:bold;">Failure to pay on or before the 3rd day of the month will result in a penalty fee of 25%.</li>
            </ol>
<div class="notes-section">
            <div style="text-align:center; margin: 15px 0; font-weight:bold; font-size:13px; text-transform:uppercase;">
                Please make payment of the total balance on or before 28th of the month.
            </div>

        </div>
    </div>
        </div>
    </div>
    <div class="page-break"></div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account</title>
    <style>
        body { font-family: "Arial", sans-serif; color: #000; margin: 0; padding: 20px; background: #525659; }
        
        .page-container { 
            background: white; 
            padding: 40px 50px; 
            width: 8.5in; 
            min-height: 11in; 
            margin: 0 auto 20px; 
            box-sizing: border-box;
            position: relative;
        }

        .header-grid { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .logo-box { width: 100px; height: 100px; text-align: center; }
        .logo-box img { max-width: 100%; max-height: 100%; }
        .header-text { text-align: center; flex: 1; }
        .republic { font-size: 12px; }
        .province { font-size: 12px; }
        .municipality { font-size: 14px; font-weight: bold; }
        .office { font-size: 12px; font-weight: bold; margin-bottom: 5px; }
        .market-name { font-size: 20px; font-weight: 900; margin: 0; color: #b91c1c; text-transform: uppercase; }

        .soa-title { 
            text-align: center; 
            font-weight: bold; 
            font-size: 18px; 
            margin: 20px 0; 
            text-decoration: underline; 
            letter-spacing: 1px;
        }

        .recipient-box { margin-bottom: 20px; border: 1px solid #000; padding: 10px; }
        .info-table { width: 100%; font-size: 12px; }
        .info-table td { padding: 3px; }
        .info-table .label { width: 120px; font-weight: bold; }
        
        .intro-text { font-size: 12px; margin-bottom: 15px; }

        .breakdown { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
        .breakdown th, .breakdown td { border: 1px solid #000; padding: 8px; }
        .breakdown th { background: #f3f4f6; text-align: center; }
        .amount-col { text-align: right; font-weight: bold; width: 120px; }
        .total-row { background: #e5e7eb; font-weight: bold; }

        .bank-details { margin-bottom: 30px; border: 1px dashed #000; padding: 10px; font-size: 11px; }
        .bank-details h4 { margin: 0 0 5px; text-decoration: underline; }
        .bank-details table { width: 100%; }
        .bank-details td { padding: 2px; }

        .footer-grid { display: flex; justify-content: space-between; margin-top: 40px; margin-bottom: 30px; }
        .sign-box { width: 45%; text-align: center; }
        .sign-box .role { text-align: left; font-size: 12px; margin-bottom: 30px; }
        .sign-box .name { font-weight: bold; text-decoration: underline; font-size: 13px; text-transform: uppercase; }
        .sign-box .title { font-size: 11px; }

        .notes-section { font-size: 10px; border-top: 2px solid #000; padding-top: 10px; }
        .notes-section ol { padding-left: 20px; margin: 5px 0; }

        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        
        @media print { 
            body { background: white; padding: 0; }
            .page-container { margin: 0; width: 100%; padding: 30px; box-shadow: none; border: none; }
            .print-btn { display: none; }
            .page-break { page-break-after: always; }
        }
    </style>

    <style>

    @media print {
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            padding: 10px;
            border-top: 1px solid #e2e8f0;
        }
    }
    footer {
        margin-top: 50px;
        text-align: center;
        font-size: 11px;
        color: #94a3b8;
        padding: 20px 0;
    }

    @media print {
        @page {
            margin: 0; 
            size: auto;
        }

        body {
            margin: 1.25cm; 
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            background: white; 
        }
    }
</style>

</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ PRINT REPORTS</button>

    <?php
    if (isset($_GET['bulk']) && $_GET['bulk'] == 'true') {
        $ids = [];
        $result = $conn->query("SELECT renter_id FROM renters WHERE end_date IS NULL");
        while($row = $result->fetch_assoc()) $ids[] = $row['renter_id'];
        
        if(count($ids) > 0) {
            foreach ($ids as $rid) renderSOA($conn, $rid);
        } else {
            echo "<div style='text-align:center; padding:50px;'>No active tenants.</div>";
        }
    } else {
        $id = $_GET['id'] ?? 0;
        if($id) renderSOA($conn, $id);
        else echo "<div style='text-align:center; padding:50px;'>Tenant not found.</div>";
    }
    ?>

    <footer>
    System Generated Report | &copy; 2026 Mall Monitor System | Developed by <strong>TaruProd</strong>
</footer>
</body>
</html>