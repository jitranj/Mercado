<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db_connect.php';
include 'helpers.php'; 

// Pass PHP Role to JS securely
$current_role = $_SESSION['role'] ?? 'staff_monitor'; 

// 1. DATA ENGINE
// 1. DATA ENGINE (FIXED LOGIC)
$sql = "
    SELECT 
        s.id, s.stall_number, s.pasilyo, s.floor, s.status, 
        r.renter_name, r.renter_id, r.contact_number, r.contract_file,
        r.profile_image, r.start_date,
        
        /* INTELLIGENT DUE DATE CALCULATION */
        CASE 
            /* Case 1: Has payment history -> Count months since last payment */
            WHEN MAX(p.month_paid_for) IS NOT NULL 
                THEN TIMESTAMPDIFF(MONTH, MAX(p.month_paid_for), CURRENT_DATE())
            
            /* Case 2: No payments yet -> Count months since Contract Start Date */
            WHEN r.start_date IS NOT NULL 
                THEN TIMESTAMPDIFF(MONTH, r.start_date, CURRENT_DATE())
            
            /* Fallback */
            ELSE 0 
        END AS months_unpaid

    FROM stalls s
    LEFT JOIN renters r ON s.id = r.stall_id AND s.status = 'occupied' AND r.end_date IS NULL
    LEFT JOIN payments p ON r.renter_id = p.renter_id
    GROUP BY s.id
    ORDER BY s.floor, s.pasilyo, s.stall_number;
";

$result = $conn->query($sql);
$stalls_data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $floor = $row['floor'] ? $row['floor'] : 1; 
        $stalls_data[$floor][$row['pasilyo']][$row['stall_number']] = $row;
    }
}

function get_stall_sequence($max_num) {
    $sequence = [];
    for ($i = 1; $i <= $max_num; $i++) {
        if ($i == 12) {
            $sequence[] = '12A';
        } 
        elseif ($i == 13) {
            $sequence[] = '12B';
        } 
        else {
            $sequence[] = $i;
        }
    }
    return $sequence;
}

function render_stall($floor, $pasilyo, $stall_label, $data) {
    $stall = $data[$floor][$pasilyo][$stall_label] ?? null;
    $status = 'available'; 
    $name = 'Vacant'; 
    $months_due = 0; 
    $extra = '';
    $img = 'default_avatar.png'; 
    $contact = ''; 
    $id = '';

    if ($stall) {
        $status = strtolower($stall['status']); 
        
        $name = xss($stall['renter_name'] ?? 'Vacant');
        $img = xss($stall['profile_image'] ?? 'default_avatar.png');
        $contact = xss($stall['contact_number']);
        
        $months_due = (int)$stall['months_unpaid'];
        $id = (int)$stall['id'];

        if ($status === 'occupied') {
            if ($months_due >= 3) $extra .= ' pay-crit'; 
            elseif ($months_due >= 1) $extra .= ' pay-warn';
            else $extra .= ' pay-good';
        }
    }

    return "<div class='stall $status $extra' 
            onclick='openModal($id)' 
            data-id='$id' data-renter='$name' data-status='$status' 
            data-due='$months_due' data-contact='$contact' data-image='$img'>
            <span class='stall-id'>$stall_label</span>
            </div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mall Command Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="style.css">
    
    <script>const USER_ROLE = '<?php echo xss($current_role); ?>';</script>
</head>
<body>

<header class="top-bar">
    <div class="brand">Mall Monitor <small style="font-size:10px; opacity:0.6;"><?php echo strtoupper(str_replace('_', ' ', $current_role)); ?></small></div>
    <div class="controls-area">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search..." onkeyup="liveSearch()">
            <div id="searchResults" style="display:none; position:absolute; top:40px; left:0; width:100%; background:white; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:200; overflow:hidden;"></div>
        </div>
        
        <?php if($current_role === 'admin' || $current_role === 'manager'): ?>
        <button class="btn-icon" onclick="openAnalytics()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
            Reports
        </button>
        <?php endif; ?>

        <?php if($current_role === 'admin'): ?>
        <button class="btn-icon" onclick="openSettings()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </button>
        <?php endif; ?>

        <div class="toggle-group">
            <button class="toggle-btn active" onclick="toggleMode(this, 'occupancy')">Map</button>
            <button class="toggle-btn" onclick="toggleMode(this, 'payment')">Status</button>
        </div>
        <div class="toggle-group">
            <button id="btn-f1" class="toggle-btn active" onclick="switchFloor(1)">1F</button>
            <button id="btn-f2" class="toggle-btn" onclick="switchFloor(2)">2F</button>
        </div>
        <a href="logout.php" style="color:#ef4444; text-decoration:none; font-size:12px; font-weight:700; margin-left:5px;">&times;</a>
    </div>
</header>

<div class="dashboard-container">
<div id="floor-1" class="floor-view active">
        
        <div class="panel col-left">
            <div class="panel-header">West Wing</div>
            <div class="panel-body">
                <div class="zone-label">Pasilyo 1D</div>
                <div class="grid" style="grid-template-columns: 1fr;">
                    <?php for ($i=1; $i<=9; $i++) echo render_stall(1, '1D', $i, $stalls_data); ?>
                </div>
                
                <div class="zone-label" style="margin-top:15px;">Handicrafts</div>
                <div class="grid" style="grid-template-columns: 1fr;">
                    <?php for ($i=1; $i<=2; $i++) echo render_stall(1, 'Handicrafts', $i, $stalls_data); ?>
                </div>
            </div>
        </div>

        <div class="panel col-center">
            <div class="panel-header">Main Atrium</div>
            <div class="panel-body">
                <div style="display:flex; gap:20px; height:100%;">
                    
                    <div style="flex:1; display:flex; flex-direction:column; gap:20px;">
                        
                        <div style="flex:1; display:flex; flex-direction:column;">
                            <div class="zone-label" style="font-size:12px; margin-bottom:10px;">Pasilyo 1A (43 Units)</div>
                            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); align-content: start; gap: 8px;">
                                <?php foreach(get_stall_sequence(42) as $l) echo render_stall(1, '1A', $l, $stalls_data); ?>
                            </div>
                        </div>

                        <div style="flex:1; display:flex; flex-direction:column;">
                            <div class="zone-label" style="font-size:12px; margin-bottom:10px;">Pasilyo 1B (41 Units)</div>
                            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); align-content: start; gap: 8px;">
                                <?php foreach(get_stall_sequence(40) as $l) echo render_stall(1, '1B', $l, $stalls_data); ?>
                            </div>
                        </div>

                    </div>

                    <div style="flex:1; display:flex; flex-direction:column; gap:20px;">
                        
                        <div style="flex:1; display:flex; flex-direction:column;">
                            <div class="zone-label" style="font-size:12px; margin-bottom:10px;">Pasilyo 1C (41 Units)</div>
                            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); align-content: start; gap: 8px;">
                                <?php foreach(get_stall_sequence(40) as $l) echo render_stall(1, '1C', $l, $stalls_data); ?>
                            </div>
                        </div>

                        <div style="flex:1; display:flex; flex-direction:column;">
                            <div class="zone-label" style="font-size:12px; margin-bottom:10px;">Pasilyo 1E (41 Units)</div>
                            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); align-content: start; gap: 8px;">
                                <?php foreach(get_stall_sequence(40) as $l) echo render_stall(1, '1E', $l, $stalls_data); ?>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <div class="panel col-right">
            <div class="panel-header">East Wing</div>
            <div class="panel-body">
                <div class="zone-label">Pasilyo 1F</div>
                <div class="grid" style="grid-template-columns: 1fr;">
                    <?php for ($i=1; $i<=10; $i++) echo render_stall(1, '1F', $i, $stalls_data); ?>
                </div>

                <div class="zone-label" style="margin-top:15px;">Ornamental</div>
                <div class="grid" style="grid-template-columns: 1fr;">
                    <?php for ($i=1; $i<=2; $i++) echo render_stall(1, 'Ornamental', $i, $stalls_data); ?>
                </div>
            </div>
        </div>

    </div>

<div id="floor-2" class="floor-view">
        
        <div class="panel col-left">
            <div class="panel-header">West Wing</div>
            <div class="panel-body">
                <div class="zone-label">Pasilyo 2A (16 Units)</div>
                <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php foreach(get_stall_sequence(16) as $l) echo render_stall(2, '2A', $l, $stalls_data); ?>
                </div>

                <div class="zone-label" style="margin-top:10px;">2A-ANNEX</div>
                <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php for($i=1; $i<=10; $i++) echo render_stall(2, '2A-ANNEX', $i, $stalls_data); ?>
                </div>
            </div>
        </div>

        <div class="panel col-center">
            <div class="panel-header">Center Hall</div>
            <div class="panel-body">
                
                <div style="display:flex; gap:6px; flex:1; min-height:0;">
                    <div style="flex:1">
                        <div class="zone-label">Pasilyo 2C (67 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr);">
                            <?php foreach(get_stall_sequence(66) as $l) echo render_stall(2, '2C', $l, $stalls_data); ?>
                        </div>
                    </div>
                    <div style="flex:1">
                        <div class="zone-label">Pasilyo 2D (71 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr);">
                            <?php foreach(get_stall_sequence(70) as $l) echo render_stall(2, '2D', $l, $stalls_data); ?>
                        </div>
                    </div>
                </div>

                <div style="height:1px; background:rgba(255,255,255,0.05); margin:4px 0;"></div>

                <div style="display:flex; gap:6px; flex:1; min-height:0;">
                    <div style="flex:1">
                        <div class="zone-label">Pasilyo 2E (36 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr);">
                            <?php foreach(get_stall_sequence(36) as $l) echo render_stall(2, '2E', $l, $stalls_data); ?>
                        </div>
                    </div>
                    <div style="flex:1">
                        <div class="zone-label">Pasilyo 2G (38 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr);">
                            <?php foreach(get_stall_sequence(38) as $l) echo render_stall(2, '2G', $l, $stalls_data); ?>
                        </div>
                    </div>
                </div>

                <div style="height:1px; background:rgba(255,255,255,0.05); margin:4px 0;"></div>

                <div style="display:flex; gap:6px; flex:0 0 auto;">
                    <div style="flex:1">
                        <div class="zone-label">Pasilyo 2B</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr);">
                            <?php for($i=1; $i<=3; $i++) echo render_stall(2, '2B', $i, $stalls_data); ?>
                        </div>
                    </div>
                    <div style="flex:1">
                        <div class="zone-label">Pasilyo 2F</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr);">
                            <?php for($i=1; $i<=6; $i++) echo render_stall(2, '2F', $i, $stalls_data); ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="panel col-right">
            <div class="panel-header">East Wing</div>
            <div class="panel-body">
                <div class="zone-label">Pasilyo 2H</div>
                <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php foreach(get_stall_sequence(15) as $l) echo render_stall(2, '2H', $l, $stalls_data); ?>
                </div>

                <div class="zone-label" style="margin-top:10px; color:#f59e0b;">Food Court</div>
                <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php foreach(get_stall_sequence(15) as $l) echo render_stall(2, 'FC', $l, $stalls_data); ?>
                </div>
            </div>
        </div>

    </div>

<div id="analyticsModal" class="modal-fullscreen" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; overflow-y: auto;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-radius: 16px; padding: 20px; border: 1px solid rgba(255,255,255,0.2);">
            <div>
                <h2 style="font-size: 28px; color: white; margin: 0; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Executive Dashboard</h2>
                <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0; font-size: 14px;">Real-time insights and analytics</p>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <select id="reportPeriod" style="padding: 8px 12px; border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; background: rgba(255,255,255,0.1); color: white; font-size: 14px; backdrop-filter: blur(5px);" onchange="updateAnalytics()">
                    <option value="current_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="last_3_months">Last 3 Months</option>
                    <option value="last_6_months">Last 6 Months</option>
                    <option value="year_to_date">Year to Date</option>
                </select>
                <button onclick="exportReport()" style="padding: 8px 16px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; color: white; cursor: pointer; font-size: 14px; transition: all 0.2s; backdrop-filter: blur(5px);" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                    📊 Export Report
                </button>
                <button onclick="document.getElementById('analyticsModal').style.display='none'" style="border: none; background: rgba(255,255,255,0.2); font-size: 24px; cursor: pointer; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: white; transition: all 0.2s; backdrop-filter: blur(5px);" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">&times;</button>
            </div>
        </div>

        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 40px rgba(0,0,0,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 32px rgba(0,0,0,0.1)'">
                <div style="position: absolute; top: -20px; right: -20px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">💰</div>
                    <div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Monthly Revenue</div>
                        <div class="stat-val" id="kpiRevenue" style="color: white; font-size: 28px; font-weight: 800; margin: 0;">₱0</div>
                        <div style="color: rgba(255,255,255,0.6); font-size: 11px; margin-top: 4px;">+12% from last month</div>
                    </div>
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 40px rgba(0,0,0,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 32px rgba(0,0,0,0.1)'">
                <div style="position: absolute; top: -20px; right: -20px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏢</div>
                    <div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Occupancy Rate</div>
                        <div class="stat-val" id="kpiOccupancy" style="color: white; font-size: 28px; font-weight: 800; margin: 0;">0%</div>
                        <small id="kpiOccDetail" style="color: rgba(255,255,255,0.6); font-size: 11px; display: block; margin-top: 4px;">0/0 Units</small>
                    </div>
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 40px rgba(0,0,0,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 32px rgba(0,0,0,0.1)'">
                <div style="position: absolute; top: -20px; right: -20px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">⚠️</div>
                    <div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Critical Tenants</div>
                        <div class="stat-val" id="kpiCritical" style="color: white; font-size: 28px; font-weight: 800; margin: 0;">0</div>
                        <small style="color: rgba(255,255,255,0.6); font-size: 11px; display: block; margin-top: 4px;">> 3 Months Due</small>
                    </div>
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 40px rgba(0,0,0,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 32px rgba(0,0,0,0.1)'">
                <div style="position: absolute; top: -20px; right: -20px; width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏠</div>
                    <div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Vacant Units</div>
                        <div class="stat-val" id="kpiVacant" style="color: white; font-size: 28px; font-weight: 800; margin: 0;">0</div>
                        <small style="color: rgba(255,255,255,0.6); font-size: 11px; display: block; margin-top: 4px;">Available for rent</small>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <div class="stat-card" style="background: rgba(255,255,255,0.95); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); backdrop-filter: blur(10px);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <div class="stat-label" style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Revenue Trend</div>
                        <div style="color: #1e293b; font-size: 18px; font-weight: 700;">Monthly Income Analysis</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button onclick="toggleChartType('bar')" id="chartTypeBar" style="padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">Bar</button>
                        <button onclick="toggleChartType('line')" id="chartTypeLine" style="padding: 6px 12px; background: #e2e8f0; color: #64748b; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">Line</button>
                    </div>
                </div>
                <div style="position: relative; height: 350px; width: 100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="stat-card" style="background: rgba(255,255,255,0.95); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); backdrop-filter: blur(10px); flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <div class="stat-label" style="color: #ef4444; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🚨 Red List</div>
                            <div style="color: #1e293b; font-size: 16px; font-weight: 700;">Top Delinquents</div>
                        </div>
                        <button onclick="viewAllDelinquents()" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">View All</button>
                    </div>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid #e2e8f0;">
                                    <th style="text-align: left; padding: 8px; color: #64748b; font-weight: 600;">Tenant</th>
                                    <th style="text-align: right; padding: 8px; color: #64748b; font-weight: 600;">Due</th>
                                </tr>
                            </thead>
                            <tbody id="redListBody">
                                </tbody>
                        </table>
                    </div>
                </div>

                <div class="stat-card" style="background: rgba(255,255,255,0.95); border-radius: 16px; padding: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); backdrop-filter: blur(10px);">
                    <div class="stat-label" style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px;">Quick Actions</div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button onclick="generateSOAReport()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">📄 Generate SOA Report</button>
                        <button onclick="sendPaymentReminders()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #f093fb, #f5576c); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">📧 Send Payment Reminders</button>
                        <button onclick="exportTenantData()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">📊 Export Tenant Data</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="settingsModal" style="display:none; position:fixed; z-index:20000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);">
    <div style="background:#ffffff; margin:5vh auto; width:90%; max-width:800px; border-radius:16px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.1); animation:modalFadeIn 0.3s ease-out;">
        <div style="padding:24px 28px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #f8fafc, #e2e8f0);">
            <h2 style="margin:0; font-size:20px; font-weight:700; color:#1e293b;">Admin Settings</h2>
            <span onclick="document.getElementById('settingsModal').style.display='none'" style="cursor:pointer; font-size:28px; color:#64748b; transition:color 0.2s; line-height:1;">&times;</span>
        </div>
        <div style="padding:28px;">
            <div class="settings-tabs" style="display:flex; gap:4px; margin-bottom:20px; border-bottom:1px solid #e2e8f0;">
                <div class="s-tab active" style="padding:12px 20px; background:#1e293b; color:white; border-radius:8px 8px 0 0; cursor:pointer; font-weight:600; transition:all 0.2s; position:relative;" onclick="switchTab('tabExports', this)">Data Exports</div>
                <div class="s-tab" style="padding:12px 20px; background:transparent; color:#64748b; border-radius:8px 8px 0 0; cursor:pointer; font-weight:600; transition:all 0.2s;" onclick="switchTab('tabPassword', this)">Change Password</div>
                <div class="s-tab" style="padding:12px 20px; background:transparent; color:#64748b; border-radius:8px 8px 0 0; cursor:pointer; font-weight:600; transition:all 0.2s;" onclick="switchTab('tabAddUser', this)">Add Staff Account</div>
            </div>

            <div id="tabExports" class="settings-content active" style="display:block; transition:opacity 0.3s ease;">
                <div style="background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                    <p style="font-size:14px; color:#64748b; margin-bottom:16px; font-weight:500;">Download reports for offline use.</p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button onclick="location.href='api_admin.php?action=export_red_list'" style="padding:12px 16px; background:#10b981; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:transform 0.2s, box-shadow 0.2s; box-shadow:0 2px 4px rgba(16,185,129,0.2);">Delinquent List (CSV)</button>
                        <button onclick="location.href='api_admin.php?action=export_payments_csv'" style="padding:12px 16px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:transform 0.2s, box-shadow 0.2s; box-shadow:0 2px 4px rgba(59,130,246,0.2);">Payments (CSV)</button>
                        <button onclick="location.href='api_admin.php?action=export_tenants_csv'" style="padding:12px 16px; background:#8b5cf6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:transform 0.2s, box-shadow 0.2s; box-shadow:0 2px 4px rgba(139,92,246,0.2);">Tenants (CSV)</button>
                        <button onclick="location.href='api_admin.php?action=export_backup'" style="padding:12px 16px; background:#374151; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:transform 0.2s, box-shadow 0.2s; box-shadow:0 2px 4px rgba(55,65,81,0.2);">Full SQL Backup</button>
                    </div>
                </div>
            </div>

            <div id="tabPassword" class="settings-content" style="display:none; transition:opacity 0.3s ease;">
                <div style="background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                    <form id="passForm" style="margin-bottom:0;">
                        <input type="hidden" name="action" value="change_password">
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; transition:border-color 0.2s;">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">New Password</label>
                            <input type="password" name="new_password" placeholder="Enter new password" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; transition:border-color 0.2s;">
                        </div>
                        <button type="button" onclick="changePass()" style="width:100%; padding:12px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:background 0.2s, transform 0.2s;">Update Password</button>
                    </form>
                </div>
            </div>

            <div id="tabAddUser" class="settings-content" style="display:none; transition:opacity 0.3s ease;">
                <div style="background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                    <form id="userForm">
                        <input type="hidden" name="action" value="add_user">
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Username</label>
                            <input type="text" name="username" placeholder="Enter username" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; transition:border-color 0.2s;">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Password</label>
                            <input type="password" name="password" placeholder="Enter password" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; transition:border-color 0.2s;">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Role</label>
                            <select name="role" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; background:white; font-size:14px; transition:border-color 0.2s;">
                                <option value="staff_monitor">Monitor (View Only)</option>
                                <option value="staff_cashier">Cashier (Payments)</option>
                                <option value="staff_billing">Billing (SOA)</option>
                                <option value="manager">Manager (Reports)</option>
                                <option value="admin">Admin (Full Access)</option>
                            </select>
                        </div>
                        <button type="button" onclick="addUser()" style="width:100%; padding:12px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; transition:background 0.2s, transform 0.2s;">Create User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="stallModal" style="display:none; position:fixed; z-index:20000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(3px);">
    <div style="background:white; margin:5vh auto; width:90%; max-width:700px; border-radius:12px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between;">
            <h2 id="modalTitle" style="margin:0; font-size:18px;">Stall Info</h2>
            <span class="close-btn" onclick="closeModal()" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div style="display:flex; height:500px;">
            <div style="flex:1; padding:20px; border-right:1px solid #f1f5f9; background:#fff; overflow-y:auto;">
                <div id="modalProfilePic" style="text-align:center; margin-bottom:20px; display:none;">
                    <img id="mImage" src="default_avatar.png" style="width:140px; height:140px; border-radius:50%; object-fit:cover; border:4px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                </div>
                <div id="renterDetails">
                    <h3 id="rName" style="text-align:center; margin:0 0 5px;">--</h3>
                    <p id="rContact" style="text-align:center; font-size:13px; color:#64748b; margin:0 0 20px;">--</p>
                    
                    <button id="btnRecordPay" onclick="togglePayForm()" style="width:100%; padding:10px; background:#3b82f6; color:white; border:none; border-radius:6px; cursor:pointer; display:none;">Record Payment</button>
                    <button id="btnGenerateSOA" style="width:100%; margin-top:10px; padding:10px; background:white; border:1px solid #10b981; color:#10b981; border-radius:6px; cursor:pointer; display:none;">Generate SOA</button>
                    
                    <button id="btnViewContract" style="width:100%; margin-top:10px; padding:10px; background:white; border:1px solid #3b82f6; color:#3b82f6; border-radius:6px; cursor:pointer; display:none;">View Contract</button>
                    
                    <div id="paymentForm" style="display:none; margin-top:10px; background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                        <form id="payForm">
                            <input type="hidden" id="payRenterId" name="renter_id">
                            <input type="number" name="amount" placeholder="Amount" style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <input type="date" name="date_paid" value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <input type="month" name="month_for" style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <button type="button" onclick="submitPayment()" style="width:100%; padding:8px; background:#10b981; color:white; border:none; border-radius:4px; cursor:pointer;">Save</button>
                        </form>
                    </div>
                    
                    <button id="btnTerminate" onclick="terminateContract()" style="width:100%; margin-top:10px; padding:10px; background:white; border:1px solid #fecaca; color:#ef4444; border-radius:6px; cursor:pointer; display:none;">Terminate</button>
                </div>
                <div id="emptyState" style="display:none; text-align:center; padding-top:20px;">
                    <p style="color:#94a3b8;">Vacant Unit</p>
                    <button id="btnAssign" onclick="toggleTenantForm()" style="width:100%; padding:10px; background:#10b981; color:white; border:none; border-radius:6px; cursor:pointer; display:none;">Assign Tenant</button>
                    <div id="newTenantForm" style="display:none; margin-top:15px; text-align:left;">
                        <form id="tenantForm" enctype="multipart/form-data">
                            <input type="hidden" id="wizardStallId" name="stall_id">
                            <input type="text" name="renter_name" placeholder="Name" required style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <input type="text" name="contact_number" placeholder="Contact" style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <input type="date" name="start_date" style="width:100%; padding:8px; margin-bottom:5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <label style="font-size:11px;">Contract</label>
                            <input type="file" name="contract_file" style="width:100%; font-size:12px; margin-bottom:5px;">
                            <label style="font-size:11px;">Photo</label>
                            <input type="file" name="profile_image" style="width:100%; font-size:12px; margin-bottom:10px;">
                            <button type="button" onclick="submitNewTenant()" style="width:100%; padding:8px; background:#3b82f6; color:white; border:none; border-radius:4px; cursor:pointer;">Confirm</button>
                        </form>
                    </div>
                </div>
            </div>
            <div style="flex:1; background:#f8fafc; padding:20px; overflow-y:auto;">
                <h4 style="margin-top:0; color:#64748b;">History</h4>
                <table style="width:100%; font-size:13px; text-align:left;">
                    <thead><tr><th>Month</th><th>Amount</th></tr></thead>
                    <tbody id="historyList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="hoverCard" style="display:none; position:fixed; z-index:20001; width:220px; background:white; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,0.2); pointer-events:none; overflow:hidden;">
    <div style="height:40px; background:#1e293b; display:flex; align-items:center; justify-content:center;">
        <span id="hoverStatus" style="color:white; font-size:10px; font-weight:700; background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:4px;">OCCUPIED</span>
    </div>
    <div style="padding:15px; display:flex; gap:12px; align-items:center;">
        <img id="hoverImg" src="default_avatar.png" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
        <div>
            <div id="hoverName" style="font-size:13px; font-weight:700; color:#0f172a;">Name</div>
            <div id="hoverContact" style="font-size:11px; color:#64748b;">Contact</div>
            <div id="hoverDue" style="font-size:10px; font-weight:700; color:#10b981;">Paid</div>
        </div>
    </div>
</div>

<script src="script.js"></script>
<div class="mobile-nav">
    <div class="nav-item active" onclick="switchFloor(1); document.querySelectorAll('.nav-item').forEach(e=>e.classList.remove('active')); this.classList.add('active');">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
        <span>Floor 1</span>
    </div>
    <div class="nav-item" onclick="switchFloor(2); document.querySelectorAll('.nav-item').forEach(e=>e.classList.remove('active')); this.classList.add('active');">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5"></path></svg>
        <span>Floor 2</span>
    </div>
    <div class="nav-item" onclick="toggleMode(this, 'payment')">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>Payments</span>
    </div>
    <div class="nav-item" onclick="openSettings()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        <span>Menu</span>
    </div>
</div>
</body>
</html>