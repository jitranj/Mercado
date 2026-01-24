<?php
session_start();
include 'db_connect.php';
include 'helpers.php';

if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$renter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($renter_id === 0) die("Invalid Renter ID");

$sql = "SELECT r.renter_name, r.contact_number, r.start_date, s.stall_number, s.pasilyo, s.floor
        FROM renters r
        JOIN stalls s ON r.stall_id = s.id
        WHERE r.renter_id = $renter_id";
$renter = $conn->query($sql)->fetch_assoc();
if (!$renter) die("Renter not found");

$sql_pay = "SELECT * FROM payments WHERE renter_id = $renter_id ORDER BY payment_date DESC, payment_id DESC";
$payments = $conn->query($sql_pay);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment History - <?php echo $renter['renter_name']; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 40px;
            color: #1e293b;
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            color: #0f172a;
        }

        .header p {
            margin: 5px 0 0;
            color: #64748b;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
        }

        .val {
            font-size: 16px;
            font-weight: 600;
            color: #334155;
            margin-top: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #1e293b;
            color: white;
            font-size: 13px;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        .tag {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .tag-rent {
            background: #dbeafe;
            color: #1e40af;
        }

        .tag-goodwill {
            background: #ffedd5;
            color: #c2410c;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                padding: 0;
            }
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
    <button class="print-btn" onclick="window.print()">🖨️ Print History</button>

    <div class="header">
        <h2>Payment Ledger</h2>
        <p>Generated on <?php echo date('F j, Y'); ?></p>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="label">TENANT</div>
            <div class="val"><?php echo htmlspecialchars($renter['renter_name']); ?></div>
            <div style="font-size:13px; margin-top:4px; color:#64748b;"><?php echo htmlspecialchars($renter['contact_number']); ?></div>
        </div>
        <div class="info-box">
            <div class="label">UNIT DETAILS</div>
            <div class="val">Stall #<?php echo $renter['stall_number']; ?></div>
            <div style="font-size:13px; margin-top:4px; color:#64748b;">
                Floor <?php echo $renter['floor']; ?> - Pasilyo <?php echo $renter['pasilyo']; ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date Paid</th>
                <th>Type</th>
                <th>OR Number</th>
                <th>Billing Period</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total = 0;
            if ($payments->num_rows > 0):
                while ($row = $payments->fetch_assoc()):
                    $total += $row['amount'];
                    $type = strtoupper($row['payment_type']);
                    $cls = ($type === 'RENT') ? 'tag-rent' : 'tag-goodwill';
            ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($row['payment_date'])); ?></td>
                        <td><span class="tag <?php echo $cls; ?>"><?php echo $type; ?></span></td>
                        <td style="font-family:monospace;"><?php echo $row['or_no'] ?: '--'; ?></td>
                        <td>
                            <?php
                            if ($type === 'RENT' && $row['month_paid_for']) {
                                echo date('F Y', strtotime($row['month_paid_for']));
                            } else {
                                echo '--';
                            }
                            ?>
                        </td>
                        <td style="text-align:right; font-weight:bold;">
                            <?php if ($row['amount'] == 0): ?>
                                <span style="color:#10b981; font-style:italic;">FREE</span>
                            <?php else: ?>
                                ₱<?php echo number_format($row['amount'], 2); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background: #1e293b; color:white;">
                <td colspan="4" style="text-align:right; padding:15px; font-weight:bold;">TOTAL PAID</td>
                <td style="text-align:right; padding:15px; font-weight:bold;">₱<?php echo number_format($total, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <footer>
        System Generated Report | &copy; 2026 Mall Monitor System | Developed by <strong>TaruProd</strong>
    </footer>

</body>

</html>