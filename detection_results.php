<?php
// detection_results.php - AI Detection Results Module (with Database & API Integration)
session_start();

require_once 'db_connect.php';        // PDO connection
require_once 'weather_functions.php'; // weather API
require_once 'api_client.php';        // Python API client

$weather = getWeatherData();

// Authentication Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$userId = 1;
$api = new DiseaseDetectionAPI('http://localhost:5000');

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// ============================================================
// FUNCTION: Get detection results from database (PDO)
// ============================================================
function getDetectionResults($filter = 'all', $sortBy = 'newest', $search = '', $dateFrom = '', $dateTo = '') {
    global $pdo, $userId;

    $sql = "SELECT id, chick_id, status, confidence, activity, timestamp 
            FROM detection_logs 
            WHERE user_id = ?
            AND 1=1";
    $params = [$userId];

    if ($filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $filter;
    }

    if (!empty($search)) {
        $sql .= " AND (chick_id LIKE ? OR activity LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($dateFrom)) {
        $sql .= " AND DATE(timestamp) >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $sql .= " AND DATE(timestamp) <= ?";
        $params[] = $dateTo;
    }

    switch ($sortBy) {
        case 'oldest':          $sql .= " ORDER BY timestamp ASC"; break;
        case 'confidence_high': $sql .= " ORDER BY confidence DESC"; break;
        case 'confidence_low':  $sql .= " ORDER BY confidence ASC"; break;
        default:                $sql .= " ORDER BY timestamp DESC"; break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'id'         => 'DET-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'time'       => $row['timestamp'],
            'chick_id'   => $row['chick_id'],
            'status'     => $row['status'],
            'confidence' => round($row['confidence'], 1),
            'weight'     => 'N/A',
            'activity'   => $row['activity'] ?? 'Unknown',
            'duration'   => 'N/A'
        ];
    }
    return $records;
}

// ============================================================
// FUNCTION: Get detection stats summary
// ============================================================
function getDetectionStats() {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("SELECT 
                           COUNT(*) as total,
                           SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as healthy,
                           SUM(CASE WHEN status = 'weak' THEN 1 ELSE 0 END) as weak,
                           SUM(CASE WHEN status = 'unhealthy' THEN 1 ELSE 0 END) as unhealthy
                           FROM detection_logs 
                           WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================================
// FUNCTION: Get status distribution (instead of disease)
// ============================================================
function getStatusDistribution() {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("SELECT 
                           status, 
                           COUNT(*) as count 
                           FROM detection_logs 
                           WHERE user_id = ? 
                           GROUP BY status 
                           ORDER BY count DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// GET ALL DATA
// ============================================================
$detectionResults = getDetectionResults($filter, $sortBy, $search, $dateFrom, $dateTo);
$stats = getDetectionStats();
$statusDist = getStatusDistribution();  // Changed from disease distribution

$totalDetections = $stats['total'] ?? 0;
$healthyCount = $stats['healthy'] ?? 0;
$weakCount = $stats['weak'] ?? 0;
$unhealthyCount = $stats['unhealthy'] ?? 0;

// Get unread notifications
try {
    $notifStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0");
    $notifStmt->execute([$userId]);
    $unreadNotifications = $notifStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $unreadNotifications = 0;
}

// Get snapshots for display
$snapStmt = $pdo->prepare("SELECT * FROM camera_snapshots WHERE user_id = ? ORDER BY timestamp DESC LIMIT 6");
$snapStmt->execute([$userId]);
$snapshots = $snapStmt->fetchAll(PDO::FETCH_ASSOC);

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');

// Check API health
$apiHealth = $api->health();
$apiAvailable = isset($apiHealth['status']) && $apiHealth['status'] === 'ok';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detection Results | BroilerGuard</title>
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
            --purple: #8E44AD; --orange: #B9772A;
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
        .sidebar-user {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-top: auto;
            background: rgba(0,0,0,0.15);
        }
        .sidebar-user .avatar { width: 42px; height: 42px; border-radius: 12px; background: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #FFFFFF; font-size: 1.1rem; }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.9rem; color: var(--sidebar-text); }
        .sidebar-user .user-info .role { font-size: 0.7rem; color: var(--sidebar-muted); }
        .sidebar-nav {
            flex: 1;
            padding: 0.8rem 0;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav .nav-section { padding: 0.3rem 1rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sidebar-muted); margin-bottom: 0.6rem; font-weight: 700; padding-left: 0.8rem; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 1rem;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 0.2rem;
            transition: all 0.2s;
            font-size: 0.88rem;
            font-weight: 500;
        }
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

        .api-status {
            display: inline-block;
            padding: 0.25rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .api-status.online { background: var(--green-light); color: var(--green); }
        .api-status.offline { background: var(--red-light); color: var(--red); }

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
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--red);
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.15rem 0.4rem;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

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

        /* ===== PAGE CONTENT ===== */
        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; color: var(--text-primary); }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.3rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.15);
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--accent); }
        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 800; }
        .stat-label { font-size: 0.8rem; color: var(--text-muted); }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 0.8rem 1rem;
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.10);
        }
        .filter-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(77, 114, 77, 0.12);
            background: var(--bg-card);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            display: inline-block;
            color: var(--text-primary);
        }
        .filter-btn:hover { background: var(--accent-light); border-color: var(--accent); }
        .filter-btn.active { background: rgba(141, 180, 142, 0.15); color: var(--accent-dark); border-color: var(--accent); font-weight: 600; }
        .search-bar-inline {
            display: flex;
            align-items: center;
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 0.35rem 0.7rem;
            gap: 0.4rem;
            border: 1px solid rgba(77, 114, 77, 0.10);
            min-width: 180px;
        }
        .search-bar-inline input { border: none; background: transparent; outline: none; font-size: 0.78rem; width: 100%; font-family: 'Inter', sans-serif; color: var(--text-primary); }
        .date-input {
            padding: 0.35rem 0.7rem;
            border-radius: 8px;
            border: 1px solid rgba(77, 114, 77, 0.12);
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .filter-separator { width: 1px; height: 24px; background: rgba(141, 180, 142, 0.3); margin: 0 0.3rem; }

        /* Charts */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .chart-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.10);
            transition: all 0.3s ease;
        }
        .chart-card:hover { box-shadow: var(--shadow-md); border-color: var(--accent); }
        .chart-card-header { display: flex; justify-content: space-between; margin-bottom: 1rem; font-weight: 700; color: var(--text-primary); }
        .chart-wrapper { position: relative; width: 100%; height: 280px; max-height: 280px; }
        canvas { width: 100% !important; height: 100% !important; }

        /* Snapshot Grid */
        .snapshot-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .snapshot-card { background: var(--bg-card); border-radius: 10px; padding: 0.8rem; text-align: center; border: 1px solid rgba(141,180,142,0.10); }
        .snapshot-card .snapshot-img { width: 100%; height: 70px; background: #1a1a1a; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.6rem; margin-bottom: 0.3rem; overflow: hidden; }
        .snapshot-card .snapshot-img img { width: 100%; height: 100%; object-fit: cover; }
        .snapshot-card .snapshot-time { font-size: 0.65rem; color: var(--text-muted); }

        /* Status Distribution */
        .status-dist { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0; }
        .status-dist .item { background: var(--bg-secondary); padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }

        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: var(--bg-secondary); padding: 0.7rem 0.8rem; text-align: left; font-weight: 600; border-bottom: 2px solid rgba(141, 180, 142, 0.25); color: var(--text-secondary); }
        td { padding: 0.7rem 0.8rem; border-bottom: 1px solid rgba(141, 180, 142, 0.10); color: var(--text-primary); }
        tr:hover td { background: var(--bg-secondary); }

        /* Badges */
        .card-badge { padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }

        .confidence-bar { width: 100%; height: 6px; background: #E8E0D0; border-radius: 3px; overflow: hidden; }
        .confidence-fill { height: 100%; border-radius: 3px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .snapshot-grid { grid-template-columns: repeat(3, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-separator { display: none; }
            .search-bar-inline { min-width: auto; }
            .chart-wrapper { height: 220px; max-height: 220px; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 1rem; }
            .snapshot-grid { grid-template-columns: repeat(2, 1fr); }
            .top-header { padding: 0 1rem; }
            .date-time-container .time { font-size: 0.85rem; }
            .back-btn { font-size: 0.75rem; padding: 0.3rem 0.7rem; }
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
            <a href="chicken_status.php"><i class="fas fa-chicken"></i> Chicken Status</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">AI Detection</div>
            <a href="live_camera.php"><i class="fas fa-camera"></i> Live Camera Feed</a>
            <a href="detection_results.php" class="active"><i class="fas fa-brain"></i> Detection Results</a>
            <a href="detection_history.php"><i class="fas fa-history"></i> Detection History</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Automation</div>
            <a href="fan_control.php"><i class="fas fa-fan"></i> Fan Control</a>
            <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
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
        <h1 class="page-title"><i class="fas fa-brain" style="color:var(--purple);"></i> AI Detection Results</h1>
        <p class="page-subtitle">Real-time AI-powered disease detection results and statistics</p>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--blue);"><i class="fas fa-robot"></i></div>
                <div class="stat-value" style="color:var(--blue);"><?php echo $totalDetections; ?></div>
                <div class="stat-label">Total Detections</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--green);">
                <div class="stat-icon" style="color:var(--green);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value" style="color:var(--green);"><?php echo $healthyCount; ?></div>
                <div class="stat-label">Healthy Detected</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--yellow);">
                <div class="stat-icon" style="color:var(--yellow);"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-value" style="color:var(--yellow);"><?php echo $weakCount; ?></div>
                <div class="stat-label">Weak Detected</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--red);">
                <div class="stat-icon" style="color:var(--red);"><i class="fas fa-times-circle"></i></div>
                <div class="stat-value" style="color:var(--red);"><?php echo $unhealthyCount; ?></div>
                <div class="stat-label">Unhealthy Detected</div>
            </div>
        </div>

        <!-- Status Distribution (instead of disease) -->
        <?php if (!empty($statusDist)): ?>
        <div style="margin-bottom:1rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.5rem; font-weight:700;">
                <i class="fas fa-chart-pie"></i> Status Distribution
            </div>
            <div class="status-dist">
                <?php foreach ($statusDist as $d): ?>
                <span class="item">
                    <span style="font-weight:600; color:<?php echo $d['status'] === 'healthy' ? 'var(--green)' : ($d['status'] === 'weak' ? 'var(--yellow)' : 'var(--red)'); ?>;">
                        <?php echo ucfirst($d['status']); ?>
                    </span>
                    <span style="background:var(--accent); color:white; padding:0.1rem 0.5rem; border-radius:10px; font-size:0.7rem;"><?php echo $d['count']; ?></span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Snapshots -->
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.8rem; font-weight:700;">
            <i class="fas fa-images"></i> Recent Snapshots
        </div>
        <div class="snapshot-grid">
            <?php if (empty($snapshots)): ?>
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="snapshot-card">
                    <div class="snapshot-img"><i class="fas fa-image"></i> No snapshot</div>
                    <div class="snapshot-time">Waiting...</div>
                </div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach (array_slice($snapshots, 0, 6) as $snap): ?>
                <div class="snapshot-card">
                    <div class="snapshot-img">
                        <?php if (!empty($snap['image_url']) && file_exists($snap['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($snap['image_url']); ?>" alt="Snapshot">
                        <?php else: ?>
                            <i class="fas fa-image"></i> No image
                        <?php endif; ?>
                    </div>
                    <div class="snapshot-time"><?php echo date('h:i A', strtotime($snap['timestamp'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Filter Bar -->
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.8rem; font-weight:700;">
            <i class="fas fa-filter"></i> Filter Results
        </div>
        <form method="GET" action="" class="filter-bar">
            <span style="font-weight:600; font-size:0.85rem; color:var(--text-secondary);">Filter:</span>
            <button type="submit" name="filter" value="all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</button>
            <button type="submit" name="filter" value="healthy" class="filter-btn <?php echo $filter === 'healthy' ? 'active' : ''; ?>"><i class="fas fa-check-circle" style="color:var(--green);"></i> Healthy</button>
            <button type="submit" name="filter" value="weak" class="filter-btn <?php echo $filter === 'weak' ? 'active' : ''; ?>"><i class="fas fa-exclamation-circle" style="color:var(--yellow);"></i> Weak</button>
            <button type="submit" name="filter" value="unhealthy" class="filter-btn <?php echo $filter === 'unhealthy' ? 'active' : ''; ?>"><i class="fas fa-times-circle" style="color:var(--red);"></i> Unhealthy</button>
            <span class="filter-separator"></span>
            <div class="search-bar-inline">
                <i class="fas fa-search" style="color:var(--text-muted);"></i>
                <input type="text" name="search" placeholder="Search Chick ID, Activity..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <span class="filter-separator"></span>
            <input type="date" name="date_from" class="date-input" value="<?php echo $dateFrom; ?>">
            <span style="font-size:0.8rem; color:var(--text-muted);">to</span>
            <input type="date" name="date_to" class="date-input" value="<?php echo $dateTo; ?>">
            <select name="sort_by" class="filter-btn" style="cursor:pointer;">
                <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="confidence_high" <?php echo $sortBy === 'confidence_high' ? 'selected' : ''; ?>>Highest Confidence</option>
                <option value="confidence_low" <?php echo $sortBy === 'confidence_low' ? 'selected' : ''; ?>>Lowest Confidence</option>
            </select>
            <button type="submit" class="filter-btn" style="background:var(--accent-light); font-weight:600;"><i class="fas fa-sync-alt"></i> Apply</button>
            <a href="detection_results.php" class="filter-btn" style="text-decoration:none;">Reset</a>
        </form>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header"><span><i class="fas fa-chart-pie"></i> Detection Distribution</span></div>
                <div class="chart-wrapper"><canvas id="distributionChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header"><span><i class="fas fa-chart-bar"></i> Confidence by Chick</span></div>
                <div class="chart-wrapper"><canvas id="confidenceChart"></canvas></div>
            </div>
        </div>

        <!-- Results Table -->
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.8rem; font-weight:700;">
            <i class="fas fa-list"></i> Detection Results <span style="font-size:0.7rem;"><?php echo count($detectionResults); ?> results</span>
        </div>
        <div class="chart-card" style="padding:0; overflow:hidden;">
            <div class="table-container" style="padding:0 1.5rem 1.5rem 1.5rem;">
                <table>
                    <thead>
                        <tr><th>Detection ID</th><th>Time</th><th>Chick ID</th><th>Status</th><th>Confidence</th><th>Weight</th><th>Activity</th><th>Duration</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detectionResults)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">No detection results found matching your filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($detectionResults as $result): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($result['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($result['time']); ?></td>
                                <td><?php echo htmlspecialchars($result['chick_id']); ?></td>
                                <td><span class="card-badge badge-<?php echo $result['status'] === 'healthy' ? 'success' : ($result['status'] === 'weak' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($result['status']); ?></span></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span style="font-weight:600; font-size:0.8rem;"><?php echo $result['confidence']; ?>%</span>
                                        <div class="confidence-bar" style="flex:1;">
                                            <div class="confidence-fill" style="width:<?php echo $result['confidence']; ?>%; background:<?php echo $result['confidence'] >= 95 ? 'var(--green)' : ($result['confidence'] >= 85 ? 'var(--yellow)' : 'var(--red)'); ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($result['weight']); ?></td>
                                <td><?php echo htmlspecialchars($result['activity']); ?></td>
                                <td><?php echo htmlspecialchars($result['duration']); ?></td>
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
    const healthyCount = <?php echo $healthyCount; ?>;
    const weakCount = <?php echo $weakCount; ?>;
    const unhealthyCount = <?php echo $unhealthyCount; ?>;
    let distributionChart, confidenceChart;

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }
    setInterval(updateDateTime, 1000);

    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
    });

    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'detection_results.php?refresh_weather=1'; }
    document.getElementById('weatherModal').addEventListener('click', function(e) { if (e.target === this) closeWeatherModal(); });

    function initCharts() {
        [distributionChart, confidenceChart].forEach(chart => { if (chart) chart.destroy(); });

        const distCtx = document.getElementById('distributionChart');
        if (distCtx) {
            distributionChart = new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Healthy', 'Weak', 'Unhealthy'],
                    datasets: [{ data: [healthyCount, weakCount, unhealthyCount], backgroundColor: ['#4D724D', '#C8A24A', '#A44A3F'], borderWidth: 0 }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: true, 
                    plugins: { 
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 10 } } } 
                    } 
                }
            });
        }

        const confCtx = document.getElementById('confidenceChart');
        if (confCtx) {
            // Get sample data from table
            const chickIds = <?php echo json_encode(array_column(array_slice($detectionResults, 0, 5), 'chick_id')); ?>;
            const confidences = <?php echo json_encode(array_column(array_slice($detectionResults, 0, 5), 'confidence')); ?>;
            const labels = chickIds.length > 0 ? chickIds : ['CHK-001', 'CHK-002', 'CHK-003', 'CHK-004', 'CHK-005'];
            const data = confidences.length > 0 ? confidences : [98.4, 95.8, 98.2, 86.6, 91.5];
            const colors = data.map(val => val >= 95 ? '#4D724D' : (val >= 85 ? '#C8A24A' : '#A44A3F'));
            
            confidenceChart = new Chart(confCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ 
                        label: 'Confidence (%)', 
                        data: data, 
                        backgroundColor: colors, 
                        borderRadius: 8, 
                        maxBarThickness: 50 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: true, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        y: { beginAtZero: false, min: 80, max: 100, grid: { color: 'rgba(0,0,0,0.05)' } }, 
                        x: { grid: { display: false } } 
                    } 
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
        updateDateTime();
    });
    window.addEventListener('resize', function() { 
        if (distributionChart) distributionChart.resize(); 
        if (confidenceChart) confidenceChart.resize(); 
    });
</script>
</body>
</html>
