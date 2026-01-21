<?php
include 'db_connect.php';

$type = $_GET['type'] ?? 'dashboard';
$current_date = date('F d, Y');

function getStats($conn) {
    $rev = $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE())")->fetch_row()[0];
    $occ = $conn->query("SELECT COUNT(*) FROM stalls WHERE status='occupied'")->fetch_row()[0];
    $tot = $conn->query("SELECT COUNT(*) FROM stalls")->fetch_row()[0];
    $rate = $tot > 0 ? round(($occ/$tot)*100) : 0;
    return ['revenue'=>$rev, 'occupied'=>$occ, 'total'=>$tot, 'rate'=>$rate];
}

function getTenants($conn, $filterType = 'all') {
    $sql = "SELECT r.renter_name, CONCAT(s.pasilyo, ' #', s.stall_number) as stall, r.contact_number, r.start_date,
            TIMESTAMPDIFF(MONTH, MAX(COALESCE(p.month_paid_for, r.start_date)), CURRENT_DATE()) as months_due
            FROM renters r
            JOIN stalls s ON r.stall_id = s.id
            LEFT JOIN payments p ON r.renter_id = p.renter_id
            WHERE r.end_date IS NULL
            GROUP BY r.renter_id ";
    
    if ($filterType === 'red_list') {
        $sql .= "HAVING months_due >= 3 ORDER BY months_due DESC";
    } 
    elseif ($filterType === 'dashboard') {
        $sql .= "HAVING months_due >= 3 ORDER BY months_due DESC LIMIT 10";
    }
    else {
        $sql .= "ORDER BY s.pasilyo, s.stall_number";
    }
    
    $result = $conn->query($sql);
    $data = [];
    while($row = $result->fetch_assoc()) $data[] = $row;
    return $data;
}

$title = "REPORT";
$data = [];
$stats = [];

if ($type === 'dashboard') {
    $title = "EXECUTIVE DASHBOARD SUMMARY";
    $stats = getStats($conn);
    $data = getTenants($conn, 'dashboard'); 
} 
elseif ($type === 'tenants') {
    $title = "MASTER LIST OF TENANTS";
    $data = getTenants($conn, 'all');
} 
elseif ($type === 'red_list') {
    $title = "CRITICAL DELINQUENT LIST (3+ MONTHS)";
    $data = getTenants($conn, 'red_list');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <style>
        body { font-family: "Times New Roman", Times, serif; padding: 20px; background: #525659; }
        .page { background: white; width: 8.5in; min-height: 11in; margin: 0 auto; padding: 0.5in; box-shadow: 0 0 10px rgba(0,0,0,0.5); display: flex; flex-direction: column; }
        
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid black; padding-bottom: 10px; }
        .header .sub { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .header h1 { font-size: 20px; font-weight: bold; margin: 5px 0 0; text-transform: uppercase; }
        
        .report-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-weight: bold; font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }

        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .kpi-card { border: 1px solid #000; padding: 15px; text-align: center; }
        .kpi-label { font-size: 12px; text-transform: uppercase; font-weight: bold; color: #555; }
        .kpi-value { font-size: 24px; font-weight: bold; margin-top: 5px; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background: #f0f0f0; text-transform: uppercase; }
        .danger { color: red; font-weight: bold; }
        
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .print-btn:hover { background: #2563eb; }

        @media print {
            body { background: white; padding: 0; }
            .page { width: 100%; box-shadow: none; margin: 0; padding: 0; }
            .print-btn { display: none; }
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
    <button class="print-btn" onclick="window.print()">🖨️ PRINT REPORT</button>

    <div class="page">
        <div class="header">
            <div class="sub">Republic of the Philippines</div>
            <div class="sub">Province of Bulacan</div>
            <div class="sub">Municipality of Calumpit</div>
            <h1><?php echo $title; ?></h1>
        </div>

        <div class="report-info">
            <span>Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'SYSTEM'); ?></span>
            <span>Date: <?php echo $current_date; ?></span>
        </div>

        <?php if ($type === 'dashboard'): ?>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Revenue (This Month)</div>
                <div class="kpi-value">₱ <?php echo number_format($stats['revenue']); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Occupancy Rate</div>
                <div class="kpi-value"><?php echo $stats['rate']; ?>%</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Occupied</div>
                <div class="kpi-value"><?php echo $stats['occupied']; ?> / <?php echo $stats['total']; ?></div>
            </div>
        </div>
        <h3 style="border-bottom: 2px solid #000; padding-bottom: 5px; margin-top: 0;">⚠️ Critical Attention List (3+ Months Due)</h3>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Tenant Name</th>
                    <th>Stall Location</th>
                    <th>Contact</th>
                    <?php if ($type !== 'tenants'): ?>
                    <th style="text-align: right;">Status / Due</th>
                    <?php else: ?>
                    <th>Start Date</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (count($data) > 0): 
                    $count = 1;
                    foreach ($data as $row): 
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td style="font-weight: bold;"><?php echo strtoupper($row['renter_name']); ?></td>
                    <td><?php echo $row['stall']; ?></td>
                    <td><?php echo $row['contact_number']; ?></td>
                    
                    <?php if ($type !== 'tenants'): ?>
                        <td style="text-align: right;" class="danger">
                            <?php echo $row['months_due']; ?> Months Unpaid
                        </td>
                    <?php else: ?>
                        <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">No records found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: auto; padding-top: 50px; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 200px;">
                <div style="border-top: 1px solid #000; padding-top: 5px; font-weight: bold;">Prepared By</div>
                <small>Mall Staff</small>
            </div>
            <div style="text-align: center; width: 200px;">
                <div style="border-top: 1px solid #000; padding-top: 5px; font-weight: bold;">Noted By</div>
                <small>Market Administrator</small>
            </div>
        </div>
    </div>

      <footer>
    System Generated Report | &copy; 2026 Mall Monitor System | Developed by <strong>TaruProd</strong>
</footer>

</body>
</html>