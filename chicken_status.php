<?php
// chicken_status.php - Chicken Health Status Module (with Database - PDO)
session_start();

require_once 'db_connect.php';        // PDO
require_once 'weather_functions.php'; // weather
require_once 'api_client.php'; 

$weather = getWeatherData();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$userId = 1;

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';

// ===== FETCH DETECTION LOGS FROM DATABASE =====
global $pdo;

// Get all detections for this user
$detStmt = $pdo->prepare("SELECT * FROM detection_logs WHERE user_id = ? ORDER BY timestamp DESC");
$detStmt->execute([$userId]);
$allDetections = $detStmt->fetchAll(PDO::FETCH_ASSOC);

// If no data, create sample data for demo (but this is DB now)
if (empty($allDetections)) {
    $sampleStatus = ['healthy', 'weak', 'unhealthy'];
    $chickIds = ['CHK-001', 'CHK-002', 'CHK-003', 'CHK-004', 'CHK-005'];
    for ($i = 0; $i < 20; $i++) {
        $status = $sampleStatus[array_rand($sampleStatus)];
        $confidence = $status === 'healthy' ? 94 + rand(0, 50)/10 : ($status === 'weak' ? 80 + rand(0, 90)/10 : 70 + rand(0, 120)/10);
        $pdo->prepare("INSERT INTO detection_logs (user_id, chick_id, status, confidence, activity, timestamp) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))")
            ->execute([$userId, $chickIds[array_rand($chickIds)], $status, min(99.9, $confidence), ['Active','Feeding','Resting','Lethargic','Scratching'][array_rand(['Active','Feeding','Resting','Lethargic','Scratching'])], rand(0, 48)]);
    }
    $detStmt->execute([$userId]);
    $allDetections = $detStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== FILTER DATA =====
$filteredDetections = [];
foreach ($allDetections as $det) {
    if ($filter !== 'all' && $det['status'] !== $filter) continue;
    if ($search && stripos($det['chick_id'], $search) === false) continue;
    $filteredDetections[] = $det;
}

// Sort
if ($sortBy === 'oldest') {
    $filteredDetections = array_reverse($filteredDetections);
}

// ===== COMPUTE STATISTICS =====
$totalChicks = 5; // fixed for demo
$healthyCount = 0;
$weakCount = 0;
$unhealthyCount = 0;

// Get latest status per chick
$latestStatus = [];
foreach ($allDetections as $det) {
    $chickId = $det['chick_id'];
    if (!isset($latestStatus[$chickId]) || strtotime($det['timestamp']) > strtotime($latestStatus[$chickId]['timestamp'])) {
        $latestStatus[$chickId] = $det;
    }
}

foreach ($latestStatus as $status) {
    if ($status['status'] === 'healthy') $healthyCount++;
    elseif ($status['status'] === 'weak') $weakCount++;
    else $unhealthyCount++;
}

// ===== CHICK DETAILS (from latest detections) =====
$chickDetails = [];
foreach ($latestStatus as $chickId => $data) {
    $chickDetails[] = [
        'id' => $chickId,
        'status' => $data['status'],
        'weight' => 'N/A',
        'age' => '21 days',
        'last_detection' => date('h:i A', strtotime($data['timestamp'])) . ' ago'
    ];
}
usort($chickDetails, function($a, $b) { return strcmp($a['id'], $b['id']); });

// ===== TREND DATA (last 7 days) =====
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayStart = $date . ' 00:00:00';
    $dayEnd = $date . ' 23:59:59';
    $trendStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM detection_logs WHERE user_id = ? AND timestamp BETWEEN ? AND ? GROUP BY status");
    $trendStmt->execute([$userId, $dayStart, $dayEnd]);
    $rows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
    $counts = ['healthy' => 0, 'weak' => 0, 'unhealthy' => 0];
    foreach ($rows as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['count'];
    }
    $trendData[$date] = $counts;
}

$labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$healthyData = [];
$weakData = [];
$unhealthyData = [];
$i = 0;
foreach ($trendData as $data) {
    $healthyData[] = $data['healthy'] ?? 0;
    $weakData[] = $data['weak'] ?? 0;
    $unhealthyData[] = $data['unhealthy'] ?? 0;
    $i++;
}

// ===== ALERTS =====
$alerts = [];
if ($unhealthyCount > 0) {
    $alerts[] = ['type' => 'warning', 'message' => "{$unhealthyCount} unhealthy chick(s) detected. Immediate attention required!", 'time' => 'Just now', 'icon' => 'fa-exclamation-triangle'];
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chicken Status | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bg-primary: #F5F5F5; --bg-secondary: #E8F0E8; --bg-card: #FFFFFF;
            --text-primary: #2C3E2C; --text-secondary: #4D724D; --text-muted: #6B8A6B;
            --accent: #8DB48E; --accent-dark: #4D724D; --accent-light: #D4E8D4;
            --sidebar-bg: #3A5C3A; --sidebar-text: #F5F5F5; --sidebar-muted: #A8C8A8;
            --green: #4D724D; --green-light: #D4E8D4;
            --yellow: #C8A24A; --yellow-light: #F4EEDC;
            --red: #A44A3F; --red-light: #F6E9E7;
            --blue: #4F6C7A; --blue-light: #EAF0F3;
            --orange: #B9772A; --orange-light: #F9EFE5;
            --purple: #8E44AD;
            --sidebar-width: 280px; --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--accent-dark) 0%, #3A5C3A 100%);
            color: var(--sidebar-text);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .sidebar-logo { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar-logo h2 { font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, var(--accent), #FFFFFF); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-logo .logo-icon { font-size: 2rem; color: var(--accent); margin-bottom: 0.5rem; }
        .sidebar-user { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 0.8rem; margin-top: auto; background: rgba(0,0,0,0.15); }
        .sidebar-user .avatar { width: 42px; height: 42px; border-radius: 12px; background: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #FFFFFF; font-size: 1.1rem; }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.9rem; color: var(--sidebar-text); }
        .sidebar-user .user-info .role { font-size: 0.7rem; color: var(--sidebar-muted); }
        .sidebar-nav { flex: 1; padding: 0.8rem 0; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav .nav-section { padding: 0.3rem 1rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sidebar-muted); margin-bottom: 0.6rem; font-weight: 700; padding-left: 0.8rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.7rem 1rem; color: var(--sidebar-text); text-decoration: none; border-radius: 12px; margin-bottom: 0.2rem; transition: all 0.2s; font-size: 0.88rem; font-weight: 500; }
        .sidebar-nav a:hover { background: rgba(141, 180, 142, 0.25); color: #FFFFFF; transform: translateX(4px); }
        .sidebar-nav a.active { background: rgba(141, 180, 142, 0.30); color: #FFFFFF; font-weight: 600; border-left: 3px solid var(--accent); }
        .sidebar-nav a i { width: 22px; text-align: center; font-size: 1rem; }
        .sidebar-footer { padding: 1rem 1.2rem; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-footer a { display: flex; align-items: center; gap: 0.7rem; color: var(--sidebar-muted); text-decoration: none; padding: 0.6rem 0.8rem; font-size: 0.88rem; transition: all 0.2s; border-radius: 10px; }
        .sidebar-footer a:hover { color: #FFFFFF; background: rgba(141, 180, 142, 0.20); transform: translateX(4px); }

        /* ===== MAIN CONTENT ===== */
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; transition: margin-left 0.3s ease; }
        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid rgba(141, 180, 142, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: var(--shadow-sm);
        }
        .header-left { display: flex; align-items: center; gap: 2rem; }
        .menu-toggle { display: none; font-size: 1.5rem; cursor: pointer; background: none; border: none; color: var(--text-primary); }
        .date-time-container { display: flex; flex-direction: column; }
        .date-time-container .date { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px; }
        .date-time-container .time { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .header-right { display: flex; align-items: center; gap: 1rem; }

        .weather-widget {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .weather-widget:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(77, 114, 77, 0.35); }
        .weather-widget i { font-size: 1.1rem; }
        .weather-widget .weather-temp { font-weight: 700; font-size: 1rem; }

        .notification-bell {
            position: relative;
            background: var(--bg-secondary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid rgba(77, 114, 77, 0.15);
        }
        .notification-bell:hover { background: var(--accent-light); transform: scale(1.05); }
        .notification-bell i { font-size: 1.2rem; color: var(--text-secondary); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--red); color: white; font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 50%; min-width: 18px; text-align: center; }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid rgba(141, 180, 142, 0.3);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .back-btn:hover { background: var(--accent-light); border-color: var(--accent); }

        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; color: var(--text-primary); }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }

        .status-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .status-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-md); text-align: center; border: 1px solid rgba(141,180,142,0.15); cursor: pointer; transition: all 0.3s ease; }
        .status-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--accent); }
        .status-card.healthy { border-top: 4px solid var(--green); }
        .status-card.weak { border-top: 4px solid var(--yellow); }
        .status-card.unhealthy { border-top: 4px solid var(--red); }
        .status-card .status-icon { font-size: 2.5rem; margin-bottom: 0.8rem; }
        .status-card.healthy .status-icon { color: var(--green); }
        .status-card.weak .status-icon { color: var(--yellow); }
        .status-card.unhealthy .status-icon { color: var(--red); }
        .status-card .status-value { font-size: 3rem; font-weight: 800; line-height: 1; }
        .status-card.healthy .status-value { color: var(--green); }
        .status-card.weak .status-value { color: var(--yellow); }
        .status-card.unhealthy .status-value { color: var(--red); }
        .status-card .status-label { font-size: 1rem; color: var(--text-muted); font-weight: 500; margin-top: 0.5rem; }

        .chick-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .chick-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.10); text-align: center; transition: all 0.3s; }
        .chick-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--accent); }
        .chick-card .chick-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .chick-card .chick-id { font-weight: 700; font-size: 0.9rem; }
        .chick-card .chick-weight { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }
        .chick-card .chick-status { display: inline-block; margin-top: 0.5rem; padding: 0.2rem 0.8rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; }

        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .chart-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.10); }
        .chart-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .chart-card-title { font-weight: 700; font-size: 1rem; }
        .chart-wrapper { position: relative; width: 100%; height: 280px; max-height: 280px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }

        .filter-bar { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.5rem; padding: 0.8rem 1rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.10); }
        .filter-btn { padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid rgba(77,114,77,0.12); background: var(--bg-card); cursor: pointer; font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); transition: all 0.2s; font-family: 'Inter', sans-serif; white-space: nowrap; }
        .filter-btn.active { background: rgba(141,180,142,0.12); color: var(--accent-dark); border-color: var(--accent); font-weight: 600; }
        .search-bar-inline { display: flex; align-items: center; background: var(--bg-secondary); border-radius: 8px; padding: 0.35rem 0.7rem; gap: 0.4rem; border: 1px solid rgba(77,114,77,0.10); min-width: 180px; }
        .search-bar-inline input { border: none; background: transparent; outline: none; font-family: 'Inter', sans-serif; font-size: 0.78rem; color: var(--text-primary); width: 100%; }
        .filter-separator { width: 1px; height: 24px; background: rgba(141,180,142,0.3); margin: 0 0.5rem; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: var(--bg-secondary); padding: 0.7rem 0.8rem; text-align: left; font-weight: 600; border-bottom: 2px solid rgba(141,180,142,0.2); }
        td { padding: 0.7rem 0.8rem; border-bottom: 1px solid rgba(141,180,142,0.08); }
        tr:hover td { background: var(--bg-secondary); }
        .card-badge { padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }

        .alerts-section { margin-bottom: 1.5rem; }
        .alert-item { display: flex; align-items: flex-start; gap: 0.8rem; padding: 0.8rem 1rem; background: var(--bg-card); border-radius: 10px; margin-bottom: 0.5rem; border-left: 4px solid; box-shadow: var(--shadow-sm); }
        .alert-item.alert-warning { border-color: var(--yellow); }
        .alert-item .alert-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 0.1rem; color: var(--yellow); }
        .alert-item .alert-content { flex: 1; }
        .alert-item .alert-message { font-size: 0.85rem; }
        .alert-item .alert-time { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .status-cards { grid-template-columns: 1fr; }
            .chick-grid { grid-template-columns: repeat(3, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .chart-wrapper { height: 220px; max-height: 220px; }
        }
        @media (max-width: 640px) {
            .chick-grid { grid-template-columns: repeat(2, 1fr); }
        }
        ::-webkit-scrollbar { width: 0; height: 0; background: transparent; }
        * { scrollbar-width: none; -ms-overflow-style: none; }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-feather-alt"></i></div>
        <h2>BroilerGuard</h2>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section"><div class="nav-section-title">Main</div><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></div>
        <div class="nav-section"><div class="nav-section-title">Monitoring</div>
            <a href="temperature.php"><i class="fas fa-thermometer-half"></i> Temperature & Humidity</a>
            <a href="feed_monitoring.php"><i class="fas fa-utensils"></i> Feed Monitoring</a>
            <a href="water_monitoring.php"><i class="fas fa-water"></i> Water Monitoring</a>
            <a href="chicken_status.php" class="active"><i class="fas fa-chicken"></i> Chicken Status</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">AI Detection</div>
            <a href="live_camera.php"><i class="fas fa-camera"></i> Live Camera Feed</a>
            <a href="detection_results.php"><i class="fas fa-brain"></i> Detection Results</a>
            <a href="detection_history.php"><i class="fas fa-history"></i> Detection History</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Automation</div>
            <a href="fan_control.php"><i class="fas fa-fan"></i> Fan Control</a>
            <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="light_control.php" class=""><i class="fas fa-lightbulb"></i> Light Control</a>
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a href="settings.php"><i class="fas fa-sliders-h"></i> Settings</a>
            
        </div>
    </nav>
    <a href="profile.php" class="sidebar-user" id="sidebarUserBtn">
        <div class="avatar">
            <?php echo strtoupper(substr($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'A', 0, 1)); ?>
        </div>
        <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Admin'); ?></div>
            <div class="role">Farm Administrator</div>
        </div>
        <i class="fas fa-chevron-right" style="font-size:0.7rem; opacity:0.5; transition:all 0.3s;"></i>
    </a>
    <div class="sidebar-footer"><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <div class="date-time-container">
                <span class="date" id="currentDate"><?php echo $currentDate; ?></span>
                <span class="time" id="currentTime"><?php echo $currentTime; ?></span>
            </div>
        </div>
                <div class="header-right">
            
            <div class="notification-bell" onclick="window.location.href='notifications.php'">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </div>
            <button class="weather-widget" onclick="openWeatherModal()" title="Click for detailed weather">
                <i class="fas <?php echo getWeatherIcon($weather['condition']); ?>"></i>
                <span class="weather-temp"><?php echo $weather['temp']; ?>°C</span>
            </button>
        </div>
    </header>

    <!-- Weather Modal -->
    <div class="modal-overlay" id="weatherModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:2000; justify-content:center; align-items:center;">
        <div class="weather-modal" style="background:white; border-radius:20px; padding:2rem; max-width:500px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.25); position:relative;">
            <button class="close-btn" onclick="closeWeatherModal()" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
            <h2><i class="fas <?php echo getWeatherIcon($weather['condition']); ?>"></i> <?php echo $weather['city']; ?>, <?php echo $weather['country']; ?></h2>
            <div style="text-align:center;font-size:3rem;font-weight:800;"><?php echo $weather['temp']; ?>°C</div>
            <div style="text-align:center;color:var(--text-muted);"><?php echo ucfirst($weather['condition']); ?></div>
            <div class="weather-details-grid" style="display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; margin-top:1rem;">
                <div class="weather-detail-item" style="background:var(--bg-secondary); padding:1rem; border-radius:10px; text-align:center;">
                    <i class="fas fa-temperature-high" style="font-size:1.5rem; color:var(--accent-dark); margin-bottom:0.5rem;"></i>
                    <div class="label" style="font-size:0.75rem; color:var(--text-muted);">Feels Like</div>
                    <div class="value" style="font-size:1.1rem; font-weight:700;"><?php echo $weather['feels_like']; ?>°C</div>
                </div>
                <div class="weather-detail-item" style="background:var(--bg-secondary); padding:1rem; border-radius:10px; text-align:center;">
                    <i class="fas fa-thermometer-half" style="font-size:1.5rem; color:var(--accent-dark); margin-bottom:0.5rem;"></i>
                    <div class="label" style="font-size:0.75rem; color:var(--text-muted);">Min / Max</div>
                    <div class="value" style="font-size:1.1rem; font-weight:700;"><?php echo $weather['temp_min']; ?>° / <?php echo $weather['temp_max']; ?>°</div>
                </div>
                <div class="weather-detail-item" style="background:var(--bg-secondary); padding:1rem; border-radius:10px; text-align:center;">
                    <i class="fas fa-tint" style="font-size:1.5rem; color:var(--accent-dark); margin-bottom:0.5rem;"></i>
                    <div class="label" style="font-size:0.75rem; color:var(--text-muted);">Humidity</div>
                    <div class="value" style="font-size:1.1rem; font-weight:700;"><?php echo $weather['humidity']; ?>%</div>
                </div>
                <div class="weather-detail-item" style="background:var(--bg-secondary); padding:1rem; border-radius:10px; text-align:center;">
                    <i class="fas fa-compress-alt" style="font-size:1.5rem; color:var(--accent-dark); margin-bottom:0.5rem;"></i>
                    <div class="label" style="font-size:0.75rem; color:var(--text-muted);">Pressure</div>
                    <div class="value" style="font-size:1.1rem; font-weight:700;"><?php echo $weather['pressure']; ?> hPa</div>
                </div>
                <div class="weather-detail-item" style="background:var(--bg-secondary); padding:1rem; border-radius:10px; text-align:center;">
                    <i class="fas fa-wind" style="font-size:1.5rem; color:var(--accent-dark); margin-bottom:0.5rem;"></i>
                    <div class="label" style="font-size:0.75rem; color:var(--text-muted);">Wind Speed</div>
                    <div class="value" style="font-size:1.1rem; font-weight:700;"><?php echo $weather['wind_speed']; ?> km/h</div>
                </div>
                <div class="weather-detail-item" style="background:var(--bg-secondary); padding:1rem; border-radius:10px; text-align:center;">
                    <i class="fas fa-sun" style="font-size:1.5rem; color:var(--accent-dark); margin-bottom:0.5rem;"></i>
                    <div class="label" style="font-size:0.75rem; color:var(--text-muted);">Sunrise / Sunset</div>
                    <div class="value" style="font-size:1.1rem; font-weight:700;"><?php echo $weather['sunrise']; ?> / <?php echo $weather['sunset']; ?></div>
                </div>
            </div>
            <button class="weather-refresh" onclick="refreshWeather()" style="display:block; margin:1rem auto 0; padding:0.5rem 1rem; background:var(--accent); border:none; border-radius:20px; cursor:pointer; font-weight:600; color:#fff;"><i class="fas fa-sync-alt"></i> Refresh Weather</button>
        </div>
    </div>

    <div class="page-content">
        <h1 class="page-title"><i class="fas fa-chicken" style="color:var(--green);"></i> Chicken Health Status</h1>
        <p class="page-subtitle">AI-powered detection for broiler chicks health monitoring</p>

        <?php if (!empty($alerts)): ?>
        <div class="alerts-section">
            <?php foreach ($alerts as $alert): ?>
            <div class="alert-item alert-<?php echo $alert['type']; ?>">
                <div class="alert-icon"><i class="fas <?php echo $alert['icon']; ?>"></i></div>
                <div class="alert-content"><div class="alert-message"><?php echo $alert['message']; ?></div><div class="alert-time"><?php echo $alert['time']; ?></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="status-cards">
            <div class="status-card healthy" onclick="filterByStatus('healthy')">
                <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                <div class="status-value"><?php echo $healthyCount; ?></div>
                <div class="status-label">Healthy</div>
            </div>
            <div class="status-card weak" onclick="filterByStatus('weak')">
                <div class="status-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="status-value"><?php echo $weakCount; ?></div>
                <div class="status-label">Weak</div>
            </div>
            <div class="status-card unhealthy" onclick="filterByStatus('unhealthy')">
                <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                <div class="status-value"><?php echo $unhealthyCount; ?></div>
                <div class="status-label">Unhealthy</div>
            </div>
        </div>

        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.8rem; font-weight:700;">
            <span><i class="fas fa-camera"></i> Individual Chick Status</span>
            <span style="font-size:0.7rem;">Total: <?php echo $totalChicks; ?> chicks</span>
        </div>

        <div class="chick-grid">
            <?php foreach ($chickDetails as $chick): ?>
            <div class="chick-card">
                <div class="chick-icon" style="color:<?php echo $chick['status'] === 'healthy' ? 'var(--green)' : ($chick['status'] === 'weak' ? 'var(--yellow)' : 'var(--red)'); ?>;">
                    <i class="fas fa-<?php echo $chick['status'] === 'healthy' ? 'chicken' : 'kiwi-bird'; ?>"></i>
                </div>
                <div class="chick-id"><?php echo $chick['id']; ?></div>
                <div class="chick-weight"><?php echo $chick['weight']; ?> | <?php echo $chick['age']; ?></div>
                <span class="chick-status" style="background:<?php echo $chick['status'] === 'healthy' ? 'var(--green-light)' : ($chick['status'] === 'weak' ? 'var(--yellow-light)' : 'var(--red-light)'); ?>;color:<?php echo $chick['status'] === 'healthy' ? 'var(--green)' : ($chick['status'] === 'weak' ? 'var(--yellow)' : 'var(--red)'); ?>;">
                    <?php echo ucfirst($chick['status']); ?>
                </span>
                <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;"><?php echo $chick['last_detection']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-bar">
            <span style="font-weight:600;font-size:0.85rem;margin-right:0.5rem;color:var(--text-secondary);"><i class="fas fa-filter"></i> Filter:</span>
            <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="changeFilter('all')">All</button>
            <button class="filter-btn <?php echo $filter === 'healthy' ? 'active' : ''; ?>" onclick="changeFilter('healthy')"><i class="fas fa-check-circle" style="color:var(--green);"></i> Healthy</button>
            <button class="filter-btn <?php echo $filter === 'weak' ? 'active' : ''; ?>" onclick="changeFilter('weak')"><i class="fas fa-exclamation-circle" style="color:var(--yellow);"></i> Weak</button>
            <button class="filter-btn <?php echo $filter === 'unhealthy' ? 'active' : ''; ?>" onclick="changeFilter('unhealthy')"><i class="fas fa-times-circle" style="color:var(--red);"></i> Unhealthy</button>
            <span class="filter-separator"></span>
            <div class="search-bar-inline">
                <i class="fas fa-search" style="color:var(--text-muted);"></i>
                <input type="text" id="chickSearch" placeholder="Search chick ID..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button class="filter-btn" onclick="performSearch()"><i class="fas fa-arrow-right"></i></button>
            <span class="filter-separator"></span>
            <select class="filter-btn" onchange="changeSort(this.value)" style="cursor:pointer;">
                <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
            </select>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header"><span class="chart-card-title"><i class="fas fa-chart-line" style="color:var(--green);"></i> Health Trend (7 Days)</span></div>
                <div class="chart-wrapper"><canvas id="healthTrendChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header"><span class="chart-card-title"><i class="fas fa-chart-pie"></i> Current Distribution</span></div>
                <div class="chart-wrapper"><canvas id="distributionChart"></canvas></div>
            </div>
        </div>

        <div class="chart-card" style="padding:0;overflow:hidden;">
            <div class="chart-card-header" style="padding:1.5rem 1.5rem 0 1.5rem;">
                <span class="chart-card-title"><i class="fas fa-list"></i> Detection History</span>
                <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo count($filteredDetections); ?> records</span>
            </div>
            <div class="table-container" style="padding:0 1.5rem 1.5rem 1.5rem;">
                <table>
                    <thead><tr><th>Time</th><th>Chick ID</th><th>Status</th><th>Confidence</th><th>Activity</th></tr></thead>
                    <tbody>
                        <?php if (empty($filteredDetections)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($filteredDetections, 0, 20) as $det): ?>
                            <tr>
                                <td><?php echo date('M d, h:i A', strtotime($det['timestamp'])); ?></td>
                                <td><strong><?php echo $det['chick_id']; ?></strong></td>
                                <td><span class="card-badge badge-<?php echo $det['status'] === 'healthy' ? 'success' : ($det['status'] === 'weak' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($det['status']); ?></span></td>
                                <td><?php echo round($det['confidence'], 1); ?>%</td>
                                <td><?php echo htmlspecialchars($det['activity'] ?? 'Unknown'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const healthyData = <?php echo json_encode($healthyData); ?>;
    const weakData = <?php echo json_encode($weakData); ?>;
    const unhealthyData = <?php echo json_encode($unhealthyData); ?>;
    let healthTrendChart, distributionChart;

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }
    setInterval(updateDateTime, 1000);

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });

    function changeFilter(filter) { updateURL({ filter: filter }); }
    function changeSort(sort) { updateURL({ sort_by: sort }); }
    function filterByStatus(status) { updateURL({ filter: status }); }
    function performSearch() { updateURL({ search: document.getElementById('chickSearch').value }); }
    document.getElementById('chickSearch')?.addEventListener('keypress', function(e) { if (e.key === 'Enter') performSearch(); });

    function updateURL(updates) {
        const params = new URLSearchParams(window.location.search);
        for (const [key, value] of Object.entries(updates)) { if (value) params.set(key, value); else params.delete(key); }
        window.location.href = 'chicken_status.php?' + params.toString();
    }

    function initCharts() {
        [healthTrendChart, distributionChart].forEach(chart => { if (chart) chart.destroy(); });

        const trendCtx = document.getElementById('healthTrendChart');
        if (trendCtx) {
            healthTrendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        { label: 'Healthy', data: healthyData, borderColor: '#4D724D', backgroundColor: 'rgba(77,114,77,0.1)', fill: true, tension: 0.4, pointRadius: 3 },
                        { label: 'Weak', data: weakData, borderColor: '#C8A24A', backgroundColor: 'rgba(200,162,74,0.1)', fill: true, tension: 0.4, pointRadius: 3 },
                        { label: 'Unhealthy', data: unhealthyData, borderColor: '#A44A3F', backgroundColor: 'rgba(164,74,63,0.1)', fill: true, tension: 0.4, pointRadius: 3 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 10 } } } },
                    scales: { y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
                }
            });
        }

        const distCtx = document.getElementById('distributionChart');
        if (distCtx) {
            distributionChart = new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Healthy', 'Weak', 'Unhealthy'],
                    datasets: [{ data: [<?php echo $healthyCount; ?>, <?php echo $weakCount; ?>, <?php echo $unhealthyCount; ?>], backgroundColor: ['#4D724D', '#C8A24A', '#A44A3F'], borderWidth: 0 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 10 } } } } }
            });
        }
    }

    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'chicken_status.php?refresh_weather=1'; }
    document.getElementById('weatherModal').addEventListener('click', function(e) { if (e.target === this) closeWeatherModal(); });

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
        updateDateTime();
    });
</script>
</body>
</html>
