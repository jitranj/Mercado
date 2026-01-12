<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db_connect.php';
include 'helpers.php'; // <--- SECURITY TOOL INCLUDED HERE

// Pass PHP Role to JS securely
$current_role = $_SESSION['role'] ?? 'staff_monitor'; 

// 1. DATA ENGINE
$sql = "
    SELECT 
        s.id, s.stall_number, s.pasilyo, s.floor, s.status, 
        r.renter_name, r.renter_id, r.contact_number, r.contract_file,
        r.profile_image, 
        COALESCE(TIMESTAMPDIFF(MONTH, MAX(p.month_paid_for), CURRENT_DATE()), 0) AS months_unpaid
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

// --- SECURITY UPGRADE APPLIED TO THIS FUNCTION ---
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
        $status = strtolower($stall['status']); // Internal status, usually safe, but good to normalize
        
        // SECURE INPUTS: We use xss() to prevent script injection
        $name = xss($stall['renter_name'] ?? 'Vacant');
        $img = xss($stall['profile_image'] ?? 'default_avatar.png');
        $contact = xss($stall['contact_number']);
        
        $months_due = (int)$stall['months_unpaid']; // Cast to integer for safety
        $id = (int)$stall['id'];

        if ($status === 'occupied') {
            if ($months_due >= 3) $extra .= ' pay-crit'; 
            elseif ($months_due >= 1) $extra .= ' pay-warn';
            else $extra .= ' pay-good';
        }
    }

    // Now safe to print because $name, $contact, etc. are sanitized
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
    
    <script>const USER_ROLE = '<?php echo xss($current_role); ?>';</script>

<style>
/* FIXED DASHBOARD THEME */
:root {
    --bg-body: #0f172a; 
    --bg-panel: #1e293b; 
    --txt-primary: #f8fafc; 
    --txt-sec: #94a3b8; 
    --accent: #3b82f6; 
    
    /* STALL COLORS */
    --st-vacant: #020617; 
    --st-vacant-bd: #334155; 
    --st-vacant-txt: #cbd5e1; 
    
    --st-occ-good: #10b981; 
    --st-occ-warn: #f59e0b; 
    --st-occ-crit: #ef4444;
}

* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body { margin: 0; padding: 0; height: 100vh; background: var(--bg-body); font-family: 'Inter', sans-serif; overflow: hidden; color: var(--txt-primary); }

/* ANIMATIONS */
@keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* MODAL ENHANCEMENTS */
#settingsModal .s-tab:hover { background: #f1f5f9 !important; color: #1e293b !important; }
#settingsModal button:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important; }
#settingsModal input:focus, #settingsModal select:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
#settingsModal span:hover { color: #ef4444; }

/* HEADER FIXES */
.top-bar { 
    height: 60px; 
    background: rgba(15, 23, 42, 0.95); /* Less transparent to prevent hiding */
    border-bottom: 1px solid rgba(255,255,255,0.1); 
    display: flex; align-items: center; justify-content: space-between; 
    padding: 0 20px; z-index: 100; position: relative; 
    white-space: nowrap; /* Prevent stacking */
}
.brand { 
    font-size: 18px; font-weight: 800; letter-spacing: -0.5px; 
    background: linear-gradient(to right, #60a5fa, #a78bfa); 
    background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
    display: flex; align-items: center; gap: 8px; /* Space for the Role Text */
}
/* RESTORED ADMIN TEXT */
.brand small {
    font-size: 10px; opacity: 0.8; font-weight: 700; 
    background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px;
    -webkit-text-fill-color: #cbd5e1; /* Make it visible solid color */
    letter-spacing: 0.5px;
}

/* CONTROLS AREA FIX */
.controls-area { display: flex; gap: 10px; align-items: center; }

/* SEARCH BOX RESTORED */
.search-box { display: block !important; position: relative; }
.search-box input { 
    background: #0f172a; border: 1px solid #334155; color: white; 
    transition: 0.3s; padding: 6px 12px; border-radius: 6px; width: 180px; font-size: 13px;
}
.search-box input:focus { border-color: var(--accent); outline: none; }

.btn-icon { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding: 6px 10px; color: var(--txt-primary); cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 12px; }

/* TOGGLE BUTTONS FIXED (No Stacking) */
.toggle-group { 
    background: #0f172a; border: 1px solid #334155; padding: 2px; border-radius: 6px; 
    display: flex; flex-direction: row; /* Force Row */
}
.toggle-btn { 
    color: #94a3b8; border: none; background: transparent; 
    padding: 6px 10px; font-size: 11px; font-weight: 600; cursor: pointer; border-radius: 4px; 
    white-space: nowrap; /* Never wrap text */
}
.toggle-btn.active { background: #1e293b; color: white; border: 1px solid #334155; }

/* DASHBOARD LAYOUT */
.dashboard-container { height: calc(100vh - 60px); padding: 12px; display: flex; gap: 12px; overflow: hidden; }
.floor-view { display: none; width: 100%; height: 100%; gap: 12px; animation: slideIn 0.3s ease-out; }
.floor-view.active { display: flex; }

.panel {
    background: var(--bg-panel);
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.4), 0 8px 16px -8px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    transition: all 0.3s ease;
}
.panel:hover {
    transform: translateY(-2px);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 12px 24px -8px rgba(0, 0, 0, 0.4);
}

.panel-header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    background: linear-gradient(135deg, rgba(0,0,0,0.3), rgba(0,0,0,0.1));
    font-size: 12px;
    font-weight: 700;
    color: var(--txt-sec);
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
}
.panel-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--accent), transparent);
}

.panel-body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 12px; }
.col-left { flex: 1; } .col-center { flex: 2.2; } .col-right { flex: 1; }

.zone-label {
    font-size: 10px;
    font-weight: 700;
    color: #94a3b8;
    margin: 8px 0 6px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    opacity: 0.8;
}

.grid {
    display: grid;
    gap: 6px;
    flex: 1;
}

.stall {
    height: 32px;
    background: var(--st-vacant);
    border: 1px solid var(--st-vacant-bd);
    color: var(--st-vacant-txt);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 11px;
    font-weight: 700;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
    position: relative;
    overflow: hidden;
}
.stall::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}
.stall:hover {
    border-color: var(--accent);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3), inset 0 1px 2px rgba(0,0,0,0.3);
}
.stall:hover::before {
    left: 100%;
}

.stall.occupied {
    border: none;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.stall.occupied.pay-good {
    background: linear-gradient(135deg, #10b981, #059669);
}
.stall.occupied.pay-warn {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}
.stall.occupied.pay-crit {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}
body:not(.mode-payment) .stall.occupied {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

/* MOBILE RESPONSIVENESS */
@media (max-width: 1024px) {
    body { height: auto; overflow-y: auto; padding-bottom: 80px; }
    .top-bar { position: sticky; top: 0; }
    .dashboard-container { height: auto; padding: 10px; }
    .floor-view.active { flex-direction: column; gap: 15px; }
    .panel { height: auto; max-height: none; overflow: visible; }
    .grid { gap: 10px; }
    .stall { height: 50px; font-size: 14px; }
    .top-bar .toggle-group { display: none; } /* Hide toggles on mobile, use bottom nav */
    .mobile-nav { display: flex !important; position: fixed; bottom: 0; left: 0; width: 100%; height: 65px; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); border-top: 1px solid rgba(255,255,255,0.1); z-index: 1000; justify-content: space-around; align-items: center; }
    .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--txt-sec); font-size: 10px; font-weight: 600; text-decoration: none; }
    .nav-item.active { color: var(--accent); }
    .nav-item svg { width: 24px; height: 24px; }
}
.mobile-nav { display: none; }

/* MODAL FIXES - RESTORING TEXT VISIBILITY */
.modal-fullscreen { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:var(--bg-body); z-index:5000; overflow-y:auto; padding:30px; }
.stats-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:30px; }
.stat-card { background:var(--bg-panel); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); }
.stat-val { font-size:24px; font-weight:800; color:white; margin:5px 0 0; }
.stat-label { font-size:12px; font-weight:600; color:var(--txt-sec); text-transform:uppercase; }

/* SETTINGS & STALL MODAL TEXT COLOR FIX */
#settingsModal > div, #stallModal > div {
    color: #0f172a; /* Force Dark Text on White Modal Card */
}
/* Ensure the left panel of stall modal stays readable */
#stallModal h3, #stallModal p, #stallModal label {
    color: #0f172a; 
}
/* Ensure the history table text is readable */
#historyList td, #historyList th {
    color: #334155; 
}
.close-btn { color: #64748b; }
.close-btn:hover { color: #ef4444; }

/* Select options visibility fix */
option {
    color: #0f172a !important;
    background: white !important;
}

/* Search results visibility fix */
#searchResults, #searchResults b, #searchResults div {
    color: #0f172a !important;
}
</style>
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
                <div class="zone-label">Pasilyo 1D (9 Units)</div>
                <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
                    <?php for ($i=1; $i<=9; $i++) echo render_stall(1, '1D', $i, $stalls_data); ?>
                </div>
                <div class="zone-label">Handicrafts</div>
                <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php for ($i=1; $i<=2; $i++) echo render_stall(1, 'Handicrafts', $i, $stalls_data); ?>
                </div>
            </div>
        </div>
        <div class="panel col-center">
            <div class="panel-header">Main Atrium</div>
            <div class="panel-body">
                <div style="display:flex; gap:8px; flex:1;">
                    <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
                        <div class="zone-label">1A (43 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(5, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(42) as $l) echo render_stall(1, '1A', $l, $stalls_data); ?>
                        </div>
                        <div class="zone-label">1B (41 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(5, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(40) as $l) echo render_stall(1, '1B', $l, $stalls_data); ?>
                        </div>
                    </div>
                    <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
                        <div class="zone-label">1C (41 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(5, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(40) as $l) echo render_stall(1, '1C', $l, $stalls_data); ?>
                        </div>
                        <div class="zone-label">1E (41 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(5, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(40) as $l) echo render_stall(1, '1E', $l, $stalls_data); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel col-right">
            <div class="panel-header">East Wing</div>
            <div class="panel-body">
                <div class="zone-label">1F (10 Units)</div>
                <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
                    <?php for ($i=1; $i<=10; $i++) echo render_stall(1, '1F', $i, $stalls_data); ?>
                </div>
                <div class="zone-label">Ornamentals</div>
                <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
                    <?php for ($i=1; $i<=2; $i++) echo render_stall(1, 'Ornamental', $i, $stalls_data); ?>
                </div>
            </div>
        </div>
    </div>

    <div id="floor-2" class="floor-view">
        <div class="panel col-left">
            <div class="panel-header">West Wing</div>
            <div class="panel-body">
                <div class="zone-label">2A (16 Units)</div>
                <div class="grid" style="grid-template-columns: repeat(4, 1fr);">
                    <?php foreach(get_stall_sequence(16) as $l) echo render_stall(2, '2A', $l, $stalls_data); ?>
                </div>
                <div class="zone-label">2A-ANNEX</div>
                <div class="grid" style="grid-template-columns: repeat(4, 1fr);">
                    <?php for($i=1; $i<=10; $i++) echo render_stall(2, '2A-ANNEX', $i, $stalls_data); ?>
                </div>
                <div style="display:flex; gap:6px; margin-top:8px;">
                    <div style="flex:1"><div class="zone-label">2B</div><div class="grid" style="grid-template-columns:repeat(3,1fr)"><?php for($i=1;$i<=3;$i++) echo render_stall(2, '2B', $i, $stalls_data); ?></div></div>
                    <div style="flex:1"><div class="zone-label">2F</div><div class="grid" style="grid-template-columns:repeat(3,1fr)"><?php for($i=1;$i<=6;$i++) echo render_stall(2, '2F', $i, $stalls_data); ?></div></div>
                </div>
            </div>
        </div>
        <div class="panel col-center">
            <div class="panel-header">Center Hall</div>
            <div class="panel-body">
                <div style="display:flex; gap:6px; flex:1;">
                    <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
                        <div class="zone-label">2C (67 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(66) as $l) echo render_stall(2, '2C', $l, $stalls_data); ?>
                        </div>
                        <div class="zone-label">2D (71 Units)</div>
                        <div class="grid" style="grid-template-columns: repeat(8, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(70) as $l) echo render_stall(2, '2D', $l, $stalls_data); ?>
                        </div>
                    </div>
                    <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
                        <div class="zone-label">Pasilyo 2H</div>
                        <div class="grid" style="grid-template-columns: repeat(4, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(15) as $l) echo render_stall(2, '2H', $l, $stalls_data); ?>
                        </div>
                        <div class="zone-label">Food Court</div>
                        <div class="grid" style="grid-template-columns: repeat(4, 1fr); flex:1;">
                            <?php foreach(get_stall_sequence(15) as $l) echo render_stall(2, 'FC', $l, $stalls_data); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel col-right">
            <div class="panel-header">East Wing</div>
            <div class="panel-body">
                <div class="zone-label">2E (36 Units)</div>
                <div class="grid" style="grid-template-columns: repeat(5, 1fr);">
                    <?php foreach(get_stall_sequence(36) as $l) echo render_stall(2, '2E', $l, $stalls_data); ?>
                </div>
                <div class="zone-label">2G (38 Units)</div>
                <div class="grid" style="grid-template-columns: repeat(5, 1fr);">
                    <?php foreach(get_stall_sequence(38) as $l) echo render_stall(2, '2G', $l, $stalls_data); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="analyticsModal" class="modal-fullscreen" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; overflow-y: auto;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Header with Controls -->
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

        <!-- KPI Cards Grid -->
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

        <!-- Charts and Tables Section -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Revenue Chart -->
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

            <!-- Red List and Additional Insights -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Red List -->
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
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
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

<script>
    function switchFloor(f) {
        document.querySelectorAll('.floor-view').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.toggle-btn[id^="btn-f"]').forEach(el => el.classList.remove('active'));
        document.getElementById('floor-'+f).classList.add('active');
        document.getElementById('btn-f'+f).classList.add('active');
    }
    function toggleMode(btn, mode) {
        if(mode === 'payment') document.body.classList.add('mode-payment');
        else document.body.classList.remove('mode-payment');
        btn.parentElement.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    let chartInstance = null;
    let currentChartType = 'bar';

    function openAnalytics() {
        document.getElementById('analyticsModal').style.display = 'block';
        updateAnalytics();
    }

    function updateAnalytics() {
        const period = document.getElementById('reportPeriod').value;
        fetch(`api_analytics.php?period=${period}`)
            .then(r => r.json())
            .then(data => {
                // Animate KPI updates
                animateValue('kpiRevenue', 0, parseInt(data.revenue_month), 1000, '₱');
                animateValue('kpiOccupancy', 0, parseFloat(data.occupancy.rate), 1000, '%');
                document.getElementById('kpiOccDetail').innerText = `${data.occupancy.occupied}/${data.occupancy.total} Units`;
                animateValue('kpiVacant', 0, data.occupancy.vacant, 1000, '');
                animateValue('kpiCritical', 0, data.red_list.length, 1000, '');

                const redList = document.getElementById('redListBody');
                redList.innerHTML = '';
                if(data.red_list.length > 0) {
                    data.red_list.forEach((item, index) => {
                        const row = document.createElement('tr');
                        row.style.borderBottom = '1px solid #f1f5f9';
                        row.style.transition = 'all 0.2s';
                        row.style.cursor = 'pointer';
                        row.onmouseover = () => row.style.background = '#f8fafc';
                        row.onmouseout = () => row.style.background = 'transparent';
                        row.onclick = () => openModal(item.stall_id);
                        row.innerHTML = `
                            <td style="padding:12px 8px;">
                                <div style="font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                                    <span style="width:8px; height:8px; background:#ef4444; border-radius:50%;"></span>
                                    ${item.renter_name}
                                </div>
                                <div style="color:#64748b; font-size:11px; margin-top:2px;">${item.pasilyo} #${item.stall_number}</div>
                            </td>
                            <td style="color:#ef4444; font-weight:700; text-align:right; padding:12px 8px;">
                                ${item.months_due} Mos
                            </td>
                        `;
                        redList.appendChild(row);
                    });
                } else {
                    redList.innerHTML = '<tr><td colspan="2" style="padding:40px; text-align:center; color:#10b981; font-style:italic; font-size:14px;">🎉 All tenants are in good standing!</td></tr>';
                }

                updateChart(data.revenue_trend);
            })
            .catch(err => console.error("Analytics Error:", err));
    }

    function updateChart(revenueData) {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        if(chartInstance) chartInstance.destroy();

        const labels = revenueData.map(d => d.month);
        const values = revenueData.map(d => parseInt(d.amount) || 0);

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.1)');

        chartInstance = new Chart(ctx, {
            type: currentChartType,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monthly Income',
                    data: values,
                    backgroundColor: currentChartType === 'bar' ? gradient : 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    fill: currentChartType === 'line',
                    tension: 0.4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        callbacks: {
                            label: function(context) {
                                return ' ₱ ' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            callback: function(value) {
                                return '₱' + (value/1000).toFixed(0) + 'k';
                            },
                            font: { size: 11 }
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }

    function toggleChartType(type) {
        currentChartType = type;
        document.getElementById('chartTypeBar').style.background = type === 'bar' ? '#3b82f6' : '#e2e8f0';
        document.getElementById('chartTypeBar').style.color = type === 'bar' ? 'white' : '#64748b';
        document.getElementById('chartTypeLine').style.background = type === 'line' ? '#3b82f6' : '#e2e8f0';
        document.getElementById('chartTypeLine').style.color = type === 'line' ? 'white' : '#64748b';
        updateAnalytics();
    }

    function animateValue(id, start, end, duration, prefix = '', suffix = '') {
        const obj = document.getElementById(id);
        const range = end - start;
        const minTimer = 50;
        const stepTime = Math.abs(Math.floor(duration / range));
        const timer = stepTime < minTimer ? minTimer : stepTime;

        const startTime = new Date().getTime();
        const endTime = startTime + duration;

        function run() {
            const now = new Date().getTime();
            const remaining = Math.max((endTime - now) / duration, 0);
            const value = Math.round(end - (remaining * range));
            obj.innerText = prefix + value.toLocaleString() + suffix;
            if (value == end) {
                clearInterval(timerId);
            }
        }

        const timerId = setInterval(run, timer);
        run();
    }

    function exportReport() {
        const period = document.getElementById('reportPeriod').value;
        window.open(`api_analytics.php?action=export&period=${period}`, '_blank');
    }

    function viewAllDelinquents() {
        // Could open a detailed delinquents modal or redirect to a page
        alert('Opening detailed delinquents report...');
    }

    function generateSOAReport() {
        window.open('soa_print.php?bulk=true', '_blank');
    }

    function sendPaymentReminders() {
        if(confirm('Send payment reminders to all delinquent tenants?')) {
            fetch('api_admin.php?action=send_reminders', { method: 'POST' })
                .then(r => r.json())
                .then(d => alert(d.message || 'Reminders sent successfully!'));
        }
    }

    function exportTenantData() {
        window.open('api_admin.php?action=export_tenants_csv', '_blank');
    }

    function openSettings() {
        document.getElementById('settingsModal').style.display = 'block';
    }

    function switchTab(tabId, btn) {
        document.querySelectorAll('.settings-content').forEach(c => { c.classList.remove('active'); c.style.display = 'none'; });
        document.querySelectorAll('.s-tab').forEach(t => { t.classList.remove('active'); t.style.background = 'transparent'; t.style.color = '#334155'; });
        const el = document.getElementById(tabId);
        if (el) { el.classList.add('active'); el.style.display = 'block'; }
        if (btn) { btn.classList.add('active'); btn.style.background = '#1e293b'; btn.style.color = 'white'; }
    }

    function changePass() {
        const formData = new FormData(document.getElementById('passForm'));
        fetch('api_admin.php', { method: 'POST', body: formData }).then(r=>r.json()).then(d => {
            if(d.success) { alert("Password Updated"); document.getElementById('settingsModal').style.display='none'; }
            else alert("Error");
        });
    }

    function addUser() {
        const formData = new FormData(document.getElementById('userForm'));
        fetch('api_admin.php', { method: 'POST', body: formData }).then(r=>r.json()).then(d => {
            if(d.success) { alert("User Created"); document.getElementById('userForm').reset(); }
            else alert("Error: " + d.message);
        });
    }

    function openModal(id) {
        if(!id) return;
        document.getElementById('stallModal').style.display = 'block';
        fetch(`api_stall_details.php?id=${id}`).then(r=>r.json()).then(d => {
            window.currentStallId = id;
            window.currentRenterId = d.renter ? d.renter.id : null;
            document.getElementById('modalTitle').innerText = "Stall " + d.stall.number;

            // DYNAMIC BUTTON VISIBILITY
            const btnAssign = document.getElementById('btnAssign');
            const btnPay = document.getElementById('btnRecordPay');
            const btnTerm = document.getElementById('btnTerminate');
            const btnContract = document.getElementById('btnViewContract');
            const btnSOA = document.getElementById('btnGenerateSOA'); // NEW

            if(d.stall.status === 'occupied' && d.renter) {
                document.getElementById('renterDetails').style.display = 'block';
                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('rName').innerText = d.renter.name;
                document.getElementById('rContact').innerText = d.renter.contact;
                
                let img = (d.renter.image && d.renter.image !== 'null') ? d.renter.image : `https://ui-avatars.com/api/?name=${d.renter.name}&background=random`;
                document.getElementById('mImage').src = img;
                document.getElementById('modalProfilePic').style.display = 'block';

                // Contract logic
                if(d.renter.contract && d.renter.contract !== 'null') {
                    btnContract.style.display = 'block';
                    btnContract.onclick = () => window.open(d.renter.contract, '_blank');
                } else {
                    btnContract.style.display = 'none';
                }

                // PERMISSION CHECKS (Occupied)
                // Payment: Admin OR Cashier
                btnPay.style.display = (USER_ROLE === 'admin' || USER_ROLE === 'staff_cashier') ? 'block' : 'none';
                // Terminate: Admin Only
                btnTerm.style.display = (USER_ROLE === 'admin') ? 'block' : 'none';
                // SOA: Admin OR Billing
                if(USER_ROLE === 'admin' || USER_ROLE === 'staff_billing') {
                    btnSOA.style.display = 'block';
                    btnSOA.onclick = () => window.open(`soa_print.php?id=${d.renter.id}`, '_blank');
                } else {
                    btnSOA.style.display = 'none';
                }

                let hHtml = '';
                d.history.forEach(h => { hHtml += `<tr><td style="padding:5px 0;">${h.month_paid_for}</td><td>₱${h.amount}</td></tr>`; });
                document.getElementById('historyList').innerHTML = hHtml || '<tr><td colspan="2">No records</td></tr>';
            } else {
                document.getElementById('renterDetails').style.display = 'none';
                document.getElementById('modalProfilePic').style.display = 'none';
                document.getElementById('emptyState').style.display = 'block';
                document.getElementById('historyList').innerHTML = '';

                // PERMISSION CHECKS (Vacant)
                btnAssign.style.display = (USER_ROLE === 'admin' || USER_ROLE === 'staff_cashier') ? 'block' : 'none';
            }
        }).catch(e => console.error(e));
    }

    function closeModal() { document.getElementById('stallModal').style.display = 'none'; }
    window.onclick = e => { 
        if(e.target == document.getElementById('stallModal')) closeModal(); 
        if(e.target == document.getElementById('settingsModal')) document.getElementById('settingsModal').style.display='none';
    };

    function togglePayForm() { 
        const f = document.getElementById('paymentForm'); 
        f.style.display = f.style.display==='none' ? 'block' : 'none'; 
        if(f.style.display==='block') document.getElementById('payRenterId').value = window.currentRenterId;
    }
    function toggleTenantForm() { 
        const f = document.getElementById('newTenantForm'); 
        f.style.display = f.style.display==='none' ? 'block' : 'none'; 
        if(f.style.display==='block') document.getElementById('wizardStallId').value = window.currentStallId;
    }

    function submitPayment() { fetch('api_add_payment.php', { method:'POST', body:new FormData(document.getElementById('payForm')) }).then(r=>r.json()).then(d=> { if(d.success) { alert("Saved"); location.reload(); } }); }
    function submitNewTenant() { fetch('api_assign_tenant.php', { method:'POST', body:new FormData(document.getElementById('tenantForm')) }).then(r=>r.json()).then(d=> { if(d.success) { location.reload(); } else { alert(d.message); } }); }
    function terminateContract() { 
        if(confirm("Terminate?")) {
            const fd = new FormData();
            fd.append('renter_id', window.currentRenterId);
            fd.append('stall_id', window.currentStallId);
            fetch('api_terminate.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=> { location.reload(); }); 
        }
    }

    let sTimer;
    function liveSearch() {
        const q = document.getElementById('searchInput').value;
        const res = document.getElementById('searchResults');
        clearTimeout(sTimer);
        if(q.length < 2) { res.style.display='none'; return; }
        sTimer = setTimeout(() => {
            fetch(`api_search.php?q=${q}`).then(r=>r.json()).then(d => {
                res.innerHTML = '';
                if(d.length > 0) {
                    res.style.display = 'block';
                    d.forEach(i => {
                        const div = document.createElement('div');
                        div.style.cssText = "padding:10px; cursor:pointer; border-bottom:1px solid #0b0a0a; font-size:12px; display:flex; gap:10px; align-items:center;";
                        div.innerHTML = `<img src="${i.img||'default_avatar.png'}" style="width:24px; height:24px; border-radius:50%;"> <b>${i.label}</b>`;
                        div.onclick = () => { switchFloor(i.floor); setTimeout(() => openModal(i.id), 300); res.style.display='none'; };
                        res.appendChild(div);
                    });
                } else res.style.display = 'none';
            });
        }, 300);
    }

    const hoverCard = document.getElementById('hoverCard');
    const hoverName = document.getElementById('hoverName');
    const hoverContact = document.getElementById('hoverContact');
    const hoverImg = document.getElementById('hoverImg');
    const hoverDue = document.getElementById('hoverDue');

    document.querySelectorAll('.stall').forEach(stall => {
        stall.addEventListener('mouseenter', (e) => {
            if (stall.getAttribute('data-status') === 'occupied') {
                hoverName.innerText = stall.getAttribute('data-renter');
                hoverContact.innerText = stall.getAttribute('data-contact') || 'No Info';
                let img = stall.getAttribute('data-image');
                hoverImg.src = (img && img !== 'null') ? img : `https://ui-avatars.com/api/?name=${hoverName.innerText}&background=random`;
                let due = parseInt(stall.getAttribute('data-due'));
                hoverDue.innerText = due > 0 ? `${due} Mos Due` : "Paid";
                hoverDue.style.color = due > 0 ? "#ef4444" : "#10b981";
                hoverCard.style.display = 'block';
            }
        });
        stall.addEventListener('mousemove', (e) => {
            let x = e.clientX + 15; let y = e.clientY + 15;
            if(x + 230 > window.innerWidth) x -= 245;
            hoverCard.style.left = x + 'px'; hoverCard.style.top = y + 'px';
        });
        stall.addEventListener('mouseleave', () => { hoverCard.style.display = 'none'; });
    });
</script>
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