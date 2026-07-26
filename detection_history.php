<?php
// detection_history.php - AI Detection History Module
session_start();

// Isama ang database connection (PDO) at weather functions
require_once 'db_connect.php';        // nagbibigay ng $pdo object
require_once 'weather_functions.php'; // nagbibigay ng getWeatherData() at getWeatherIcon()

// Kunin ang weather data
$weather = getWeatherData();

// Authentication Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get filter parameters from URL
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// ----------------------------------------------------------------------
// FUNCTION: Kumuha ng detection history mula sa database (gamit ang PDO)
// ----------------------------------------------------------------------
function getDetectionHistory($filter = 'all', $sortBy = 'newest', $search = '', $dateFrom = '', $dateTo = '') {
    global $pdo; // gamitin ang PDO connection mula sa db_connect.php

    // Base query
    $sql = "SELECT id, chick_id, status, confidence, respiratory_severity, heat_stress_level, 
                   activity, image_url, timestamp 
            FROM detection_logs 
            WHERE 1=1";

    $params = [];

    // Filter by status
    if ($filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $filter;
    }

    // Search: chick_id or activity
    if (!empty($search)) {
        $sql .= " AND (chick_id LIKE ? OR activity LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }

    // Date range
    if (!empty($dateFrom)) {
        $sql .= " AND DATE(timestamp) >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $sql .= " AND DATE(timestamp) <= ?";
        $params[] = $dateTo;
    }

    // Sorting
    switch ($sortBy) {
        case 'oldest':
            $sql .= " ORDER BY timestamp ASC";
            break;
        case 'confidence_high':
            $sql .= " ORDER BY confidence DESC";
            break;
        case 'confidence_low':
            $sql .= " ORDER BY confidence ASC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY timestamp DESC";
            break;
    }

    // Prepare and execute (PDO)
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'id'         => $row['id'],
            'time'       => $row['timestamp'],
            'chick_id'   => $row['chick_id'],
            'status'     => $row['status'],
            'confidence' => round($row['confidence'], 1),
            'weight'     => 'N/A', // wala sa table na ito
            'activity'   => $row['activity'] ?? 'Unknown',
            'duration'   => 'N/A',
            'image_url'  => $row['image_url']
        ];
    }

    return $records;
}

// Kunin ang data gamit ang filters
$detectionHistory = getDetectionHistory($filter, $sortBy, $search, $dateFrom, $dateTo);

// Compute statistics (gamit ang regular anonymous function para sa PHP <7.4)
$healthyCount = count(array_filter($detectionHistory, function($r) {
    return $r['status'] === 'healthy';
}));
$weakCount = count(array_filter($detectionHistory, function($r) {
    return $r['status'] === 'weak';
}));
$unhealthyCount = count(array_filter($detectionHistory, function($r) {
    return $r['status'] === 'unhealthy';
}));

$totalDetections = count($detectionHistory);
$avgConfidence = $totalDetections > 0 ? round(array_sum(array_column($detectionHistory, 'confidence')) / $totalDetections, 1) : 0;

// Trend data for last 7 days (mula sa database)
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayStart = $date . ' 00:00:00';
    $dayEnd = $date . ' 23:59:59';

    // Query direct para sa trend (PDO)
    global $pdo;
    $sqlTrend = "SELECT status, COUNT(*) as count 
                 FROM detection_logs 
                 WHERE timestamp BETWEEN ? AND ? 
                 GROUP BY status";
    $stmt = $pdo->prepare($sqlTrend);
    $stmt->execute([$dayStart, $dayEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['healthy' => 0, 'weak' => 0, 'unhealthy' => 0];
    foreach ($rows as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['count'];
        }
    }

    $trendData[] = [
        'date'      => $date,
        'healthy'   => $counts['healthy'],
        'weak'      => $counts['weak'],
        'unhealthy' => $counts['unhealthy']
    ];
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0; // maaari mong kunin sa database kung gusto

// Weather icon function (nasa weather_functions.php)
// getWeatherIcon() ay available na
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detection History | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- SHARED CSS - bagong color palette (kung may assets/css/style.css) -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* ============================================
           DIRECT CSS VARIABLES (FALLBACK) - GARANTISADONG MAY KULAY
           ============================================ */
        :root {
            /* NEW COLOR PALETTE */
            --bg-primary: #F5F5F5;
            --bg-secondary: #E8F0E8;
            --bg-card: #FFFFFF;
            --text-primary: #2C3E2C;
            --text-secondary: #4D724D;
            --text-muted: #6B8A6B;
            --accent: #8DB48E;
            --accent-dark: #4D724D;
            --accent-light: #D4E8D4;
            
            /* SIDEBAR */
            --sidebar-bg: #3A5C3A;
            --sidebar-text: #F5F5F5;
            --sidebar-muted: #A8C8A8;
            
            /* STATUS COLORS */
            --green: #4D724D;
            --green-light: #D4E8D4;
            --yellow: #C8A24A;
            --yellow-light: #F4EEDC;
            --red: #A44A3F;
            --red-light: #F6E9E7;
            --blue: #4F6C7A;
            --blue-light: #EAF0F3;
            --orange: #B9772A;
            --orange-light: #F9EFE5;
            --purple: #8E44AD;
            
            /* LAYOUT */
            --sidebar-width: 280px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
        }

        /* ============================================
           MGA NATATANGING STYLE PARA SA PAGE NA ITO
           ============================================ */
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR - GAMIT ANG BAGONG KULAY */
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

        .sidebar-logo {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: center;
        }
        .sidebar-logo h2 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #FFFFFF);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .sidebar-logo .logo-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

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
        .sidebar-user .avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #FFFFFF;
            font-size: 1.1rem;
        }
        .sidebar-user .user-info .name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--sidebar-text);
        }
        .sidebar-user .user-info .role {
            font-size: 0.7rem;
            color: var(--sidebar-muted);
        }

        .sidebar-nav {
            flex: 1;
            padding: 0.8rem 0;
            overflow-y: auto;
        }
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar-nav .nav-section {
            padding: 0.3rem 1rem;
            margin-top: 0.3rem;
        }
        .sidebar-nav .nav-section-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--sidebar-muted);
            margin-bottom: 0.6rem;
            font-weight: 700;
            padding-left: 0.8rem;
        }
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
        .sidebar-nav a:hover {
            background: rgba(141, 180, 142, 0.25);
            color: #FFFFFF;
            transform: translateX(4px);
        }
        .sidebar-nav a.active {
            background: rgba(141, 180, 142, 0.30);
            color: #FFFFFF;
            font-weight: 600;
            border-left: 3px solid var(--accent);
        }
        .sidebar-nav a i { width: 22px; text-align: center; font-size: 1rem; }

        .sidebar-footer {
            padding: 1rem 1.2rem;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: var(--sidebar-muted);
            text-decoration: none;
            padding: 0.6rem 0.8rem;
            font-size: 0.88rem;
            transition: all 0.2s;
            border-radius: 10px;
        }
        .sidebar-footer a:hover {
            color: #FFFFFF;
            background: rgba(141, 180, 142, 0.20);
            transform: translateX(4px);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

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
        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--text-primary);
        }
        .date-time-container { display: flex; flex-direction: column; }
        .date-time-container .date {
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .date-time-container .time {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-primary);
        }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        
        /* Weather Widget - gaya ng dashboard */
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
        .weather-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(77, 114, 77, 0.35);
        }
        .weather-widget i { font-size: 1.1rem; }
        .weather-widget .weather-temp { font-weight: 700; font-size: 1rem; }

        /* Notification Bell */
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
        .notification-bell:hover {
            background: var(--accent-light);
            transform: scale(1.05);
        }
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

        /* Modal para sa weather (gaya ng dashboard) */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .weather-modal {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            position: relative;
        }
        .weather-modal .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .weather-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .weather-detail-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        .weather-detail-item i {
            font-size: 1.5rem;
            color: var(--accent-dark);
            margin-bottom: 0.5rem;
        }
        .weather-detail-item .label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .weather-detail-item .value {
            font-size: 1.1rem;
            font-weight: 700;
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
        .back-btn:hover {
            background: var(--accent-light);
            border-color: var(--accent);
        }

        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--text-primary);
        }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* STATS CARDS - UPDATED */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.3rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.15);
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
        }
        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 800; }
        .stat-label { font-size: 0.8rem; color: var(--text-muted); }

        /* FILTER BAR - UPDATED */
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
        .filter-btn:hover {
            background: var(--accent-light);
            border-color: var(--accent);
        }
        .filter-btn.active {
            background: rgba(141, 180, 142, 0.15);
            color: var(--accent-dark);
            border-color: var(--accent);
            font-weight: 600;
        }
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
        .search-bar-inline input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.78rem;
            width: 100%;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
        }
        .date-input {
            padding: 0.35rem 0.7rem;
            border-radius: 8px;
            border: 1px solid rgba(77, 114, 77, 0.12);
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .filter-separator {
            width: 1px;
            height: 24px;
            background: rgba(141, 180, 142, 0.3);
            margin: 0 0.3rem;
        }

        /* CHARTS - UPDATED */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.10);
            transition: all 0.3s ease;
        }
        .chart-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
        }
        .chart-card-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 280px;
            max-height: 280px;
        }
        canvas { width: 100% !important; height: 100% !important; }

        /* TABLE - UPDATED */
        .table-container { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th {
            background: var(--bg-secondary);
            padding: 0.7rem 0.8rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(141, 180, 142, 0.25);
            color: var(--text-secondary);
        }
        td {
            padding: 0.7rem 0.8rem;
            border-bottom: 1px solid rgba(141, 180, 142, 0.10);
            color: var(--text-primary);
        }
        tr:hover td { background: var(--bg-secondary); }

        /* BADGES - UPDATED */
        .badge-status {
            padding: 0.25rem 0.7rem;
            border-radius: 15px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-healthy { background: var(--green-light); color: var(--green); }
        .badge-weak { background: var(--yellow-light); color: var(--yellow); }
        .badge-unhealthy { background: var(--red-light); color: var(--red); }

        .confidence-bar-bg {
            width: 100%;
            height: 6px;
            background: #E8E0D0;
            border-radius: 3px;
            overflow: hidden;
        }
        .confidence-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-separator { display: none; }
            .search-bar-inline { min-width: auto; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .date-time-container .time { font-size: 0.85rem; }
            .back-btn { font-size: 0.75rem; padding: 0.3rem 0.7rem; }
        }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-feather-alt"></i></div>
        <h2>BroilerGuard</h2>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Monitoring</div>
            <a href="temperature.php"><i class="fas fa-thermometer-half"></i> Temperature & Humidity</a>
            <a href="feed_monitoring.php"><i class="fas fa-utensils"></i> Feed Monitoring</a>
            <a href="water_monitoring.php"><i class="fas fa-water"></i> Water Monitoring</a>
            <a href="chicken_status.php"><i class="fas fa-chicken"></i> Chicken Status</a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">AI Detection</div>
            <a href="live_camera.php"><i class="fas fa-camera"></i> Live Camera Feed</a>
            <a href="detection_results.php"><i class="fas fa-brain"></i> Detection Results</a>
            <a href="detection_history.php" class="active"><i class="fas fa-history"></i> Detection History</a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Automation</div>
            <a href="fan_control.php"><i class="fas fa-fan"></i> Fan Control</a>
            <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="light_control.php" class="active"><i class="fas fa-lightbulb"></i> Light Control</a>
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">System</div>
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
    <div class="sidebar-footer">
        <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
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
            <!-- Notification Bell -->
            <div class="notification-bell" onclick="window.location.href='notifications.php'">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </div>
            <!-- Weather Widget -->
            <button class="weather-widget" onclick="openWeatherModal()" title="Click for detailed weather">
                <i class="fas <?php echo getWeatherIcon($weather['condition']); ?>"></i>
                <span class="weather-temp"><?php echo $weather['temp']; ?>°C</span>
            </button>
            <!-- Back to Dashboard -->
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </header>

    <!-- Weather Modal -->
    <div class="modal-overlay" id="weatherModal">
        <div class="weather-modal">
            <button class="close-btn" onclick="closeWeatherModal()">&times;</button>
            <h2><i class="fas <?php echo getWeatherIcon($weather['condition']); ?>"></i> <?php echo $weather['city']; ?>, <?php echo $weather['country']; ?></h2>
            <div style="text-align:center;font-size:3rem;font-weight:800;"><?php echo $weather['temp']; ?>°C</div>
            <div style="text-align:center;color:var(--text-muted);"><?php echo ucfirst($weather['condition']); ?></div>
            <div class="weather-details-grid">
                <div class="weather-detail-item"><i class="fas fa-temperature-high"></i><div class="label">Feels Like</div><div class="value"><?php echo $weather['feels_like']; ?>°C</div></div>
                <div class="weather-detail-item"><i class="fas fa-thermometer-half"></i><div class="label">Min / Max</div><div class="value"><?php echo $weather['temp_min']; ?>° / <?php echo $weather['temp_max']; ?>°</div></div>
                <div class="weather-detail-item"><i class="fas fa-tint"></i><div class="label">Humidity</div><div class="value"><?php echo $weather['humidity']; ?>%</div></div>
                <div class="weather-detail-item"><i class="fas fa-compress-alt"></i><div class="label">Pressure</div><div class="value"><?php echo $weather['pressure']; ?> hPa</div></div>
                <div class="weather-detail-item"><i class="fas fa-wind"></i><div class="label">Wind Speed</div><div class="value"><?php echo $weather['wind_speed']; ?> km/h</div></div>
                <div class="weather-detail-item"><i class="fas fa-sun"></i><div class="label">Sunrise / Sunset</div><div class="value"><?php echo $weather['sunrise']; ?> / <?php echo $weather['sunset']; ?></div></div>
            </div>
            <button class="weather-refresh" onclick="refreshWeather()" style="display:block;margin:1rem auto 0;padding:0.5rem 1rem;background:var(--accent);border:none;border-radius:20px;cursor:pointer;font-weight:600;color:#fff;"><i class="fas fa-sync-alt"></i> Refresh Weather</button>
        </div>
    </div>

    <div class="page-content">
        <h1 class="page-title">
            <i class="fas fa-history" style="color:var(--purple);"></i> 
            AI Detection History
        </h1>
        <p class="page-subtitle">Complete historical log of AI-driven health assessments, trends & captured events</p>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--blue);"><i class="fas fa-database"></i></div>
                <div class="stat-value" style="color:var(--blue);"><?php echo $totalDetections; ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--green);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value" style="color:var(--green);"><?php echo $healthyCount; ?></div>
                <div class="stat-label">Healthy</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--yellow);"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value" style="color:var(--yellow);"><?php echo $weakCount; ?></div>
                <div class="stat-label">Weak</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--red);"><i class="fas fa-skull-crosswalk"></i></div>
                <div class="stat-value" style="color:var(--red);"><?php echo $unhealthyCount; ?></div>
                <div class="stat-label">Unhealthy</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <span style="font-weight:600;font-size:0.85rem;color:var(--text-secondary);"><i class="fas fa-filter"></i> Filter:</span>
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
            <span style="font-size:0.8rem;color:var(--text-muted);">to</span>
            <input type="date" name="date_to" class="date-input" value="<?php echo $dateTo; ?>">
            
            <select name="sort_by" class="filter-btn" style="cursor:pointer;">
                <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="confidence_high" <?php echo $sortBy === 'confidence_high' ? 'selected' : ''; ?>>Highest Confidence</option>
                <option value="confidence_low" <?php echo $sortBy === 'confidence_low' ? 'selected' : ''; ?>>Lowest Confidence</option>
            </select>
            
            <button type="submit" class="filter-btn" style="background:var(--accent-light);font-weight:600;"><i class="fas fa-sync-alt"></i> Apply</button>
            <a href="detection_history.php" class="filter-btn" style="text-decoration:none;">Reset</a>
        </form>

        <!-- Trend Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <span><i class="fas fa-chart-line"></i> Detection Trends (Last 7 days)</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <span><i class="fas fa-chart-pie"></i> Distribution Overview</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="distributionPieChart"></canvas>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="chart-card" style="padding:0;overflow:hidden;">
            <div class="chart-card-header" style="padding:1.5rem 1.5rem 0 1.5rem;">
                <span><i class="fas fa-table-list"></i> Historical Detection Logs</span>
                <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo $totalDetections; ?> entries</span>
            </div>
            <div class="table-container" style="padding:0 1.5rem 1.5rem 1.5rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Detection ID</th>
                            <th>Timestamp</th>
                            <th>Chick ID</th>
                            <th>Status</th>
                            <th>Confidence</th>
                            <th>Weight</th>
                            <th>Activity</th>
                            <th>Duration</th>
                            <th>Preview</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detectionHistory)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted);">
                                No detection history found matching your filters.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($detectionHistory as $result): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($result['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($result['time']); ?></td>
                                <td><?php echo htmlspecialchars($result['chick_id']); ?></td>
                                <td>
                                    <span class="badge-status badge-<?php echo $result['status']; ?>">
                                        <?php echo ucfirst($result['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:0.5rem;">
                                        <span style="font-weight:600;font-size:0.8rem;"><?php echo $result['confidence']; ?>%</span>
                                        <div class="confidence-bar-bg" style="flex:1;">
                                            <div class="confidence-fill" style="width:<?php echo $result['confidence']; ?>%; background:<?php echo $result['confidence'] >= 95 ? 'var(--green)' : ($result['confidence'] >= 85 ? 'var(--yellow)' : 'var(--red)'); ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($result['weight']); ?></td>
                                <td><?php echo htmlspecialchars($result['activity']); ?></td>
                                <td><?php echo htmlspecialchars($result['duration']); ?></td>
                                <td>
                                    <?php if (!empty($result['image_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($result['image_url']); ?>" target="_blank">
                                            <i class="fas fa-camera" style="color:var(--blue);"></i> view
                                        </a>
                                    <?php else: ?>
                                        <i class="fas fa-camera" style="color:var(--text-muted);"></i> N/A
                                    <?php endif; ?>
                                </td>
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
    // Data para sa charts (mula sa PHP)
    const trendData = <?php echo json_encode($trendData); ?>;
    const healthyCount = <?php echo $healthyCount; ?>;
    const weakCount = <?php echo $weakCount; ?>;
    const unhealthyCount = <?php echo $unhealthyCount; ?>;
    let trendChart, pieChart;

    // ----- Clock update -----
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', minute: '2-digit', second: '2-digit' 
        });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
        });
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // ----- Sidebar toggle -----
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
    });

    // ----- Weather modal -----
    function openWeatherModal() {
        document.getElementById('weatherModal').classList.add('active');
    }
    function closeWeatherModal() {
        document.getElementById('weatherModal').classList.remove('active');
    }
    function refreshWeather() {
        window.location.href = 'detection_history.php?refresh_weather=1';
    }
    document.getElementById('weatherModal').addEventListener('click', function(e) {
        if (e.target === this) closeWeatherModal();
    });

    // ----- Charts -----
    function initCharts() {
        // Trend Chart
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        const labels = trendData.map(d => d.date.slice(5)); // "MM-DD"
        const healthyData = trendData.map(d => d.healthy);
        const weakData = trendData.map(d => d.weak);
        const unhealthyData = trendData.map(d => d.unhealthy);

        trendChart = new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Healthy', data: healthyData, borderColor: '#4D724D', backgroundColor: 'transparent', tension: 0.2, fill: false, pointBackgroundColor: '#4D724D' },
                    { label: 'Weak', data: weakData, borderColor: '#C8A24A', backgroundColor: 'transparent', tension: 0.2, fill: false, pointBackgroundColor: '#C8A24A' },
                    { label: 'Unhealthy', data: unhealthyData, borderColor: '#A44A3F', backgroundColor: 'transparent', tension: 0.2, fill: false, pointBackgroundColor: '#A44A3F' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'top' } }
            }
        });

        // Pie Chart
        const ctxPie = document.getElementById('distributionPieChart').getContext('2d');
        pieChart = new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Healthy', 'Weak', 'Unhealthy'],
                datasets: [{
                    data: [healthyCount, weakCount, unhealthyCount],
                    backgroundColor: ['#4D724D', '#C8A24A', '#A44A3F'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // Initialize charts kapag nag-load na ang page
    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        // I-scroll sa active menu item
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) {
            activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
        }
    });

    // I-resize ang charts kapag nag-resize ang window
    window.addEventListener('resize', function() {
        if (trendChart) trendChart.resize();
        if (pieChart) pieChart.resize();
    });
</script>

</body>
</html>