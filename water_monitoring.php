<?php
// water_monitoring.php - Water Monitoring Module
session_start();

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

// Get filter parameters
$filter = $_GET['filter'] ?? '24h';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';

// Shared sensor data cache - SAME AS DASHBOARD
function getSharedSensorData() {
    $cacheFile = 'sensor_data_cache.json';
    $cacheTime = 30;
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $data = [
        'temperature' => round(rand(300, 350) / 10, 1),
        'humidity' => rand(55, 80),
        'feed_level' => rand(75, 98),
        'water_level' => rand(80, 99),
        'fan_status' => (rand(0, 10) > 2) ? 'ON' : 'OFF',
        'water_pump' => (rand(0, 10) > 5) ? 'ON' : 'OFF',
        'timestamp' => time()
    ];
    
    file_put_contents($cacheFile, json_encode($data));
    return $data;
}

$sharedData = getSharedSensorData();

function getWaterData($filter, $dateFrom = '', $dateTo = '', $sortBy = 'newest') {
    global $sharedData;
    
    $currentWaterLevel = $sharedData['water_level'];
    $waterPumpStatus = $sharedData['water_pump'];
    $waterConsumedToday = rand(25, 35); // ml per day (1000ml = 1L total daily)
    $waterConsumedWeek = rand(200, 280); // ml for 4 days
    
    // 3x daily feeding schedule: 6AM, 12PM, 7PM
    switch ($filter) {
        case '24h':
            $labels = ['6:00 AM', '12:00 PM', '7:00 PM'];
            // Each feeding session ~300-350ml, total ~1000ml (1L) per day
            $waterData = [350, 300, 350]; // ml per session
            $waterRemaining = [65, 35, 0];
            $flowRate = [0.35, 0.30, 0.35];
            break;
        case '4d':
            $labels = ['Day 1', 'Day 2', 'Day 3', 'Day 4'];
            $waterData = [950, 1020, 980, 1000]; // ml per day
            $waterRemaining = [95, 98, 96, 100];
            $flowRate = [0.95, 1.02, 0.98, 1.00];
            break;
        case 'custom':
            $labels = [];
            $waterData = [];
            $waterRemaining = [];
            $flowRate = [];
            $start = $dateFrom ? strtotime($dateFrom) : strtotime('-4 days');
            $end = $dateTo ? strtotime($dateTo) : time();
            $interval = ($end - $start) / 10;
            for ($i = 0; $i <= 10; $i++) {
                $labels[] = date('M d', $start + ($i * $interval));
                $consumed = round(rand(950, 1050) / 1000, 2);
                $waterData[] = $consumed;
                $waterRemaining[] = rand(90, 100);
                $flowRate[] = round(rand(30, 80) / 100, 1);
            }
            break;
        default:
            $labels = ['6:00 AM', '12:00 PM', '7:00 PM'];
            $waterData = [350, 300, 350];
            $waterRemaining = [65, 35, 0];
            $flowRate = [0.35, 0.30, 0.35];
    }
    
    // Generate alerts - DISABLED / REMOVED
    $alerts = [];
    $waterStatus = 'normal';
    $pumpStatusText = $waterPumpStatus === 'ON' ? 'Running' : 'Stopped';
    
    return [
        'current_water_level' => $currentWaterLevel,
        'water_status' => $waterStatus,
        'water_pump_status' => $waterPumpStatus,
        'pump_status_text' => $pumpStatusText,
        'labels' => $labels,
        'water_data' => $waterData,
        'water_remaining' => $waterRemaining,
        'flow_rate' => $flowRate,
        'alerts' => $alerts,
        'water_consumed_today' => $waterConsumedToday,
        'water_consumed_week' => $waterConsumedWeek,
        'min_consumption' => min($waterData),
        'max_consumption' => max($waterData),
        'avg_consumption' => round(array_sum($waterData) / count($waterData), 1),
        'total_consumption' => round(array_sum($waterData), 1),
        'avg_flow_rate' => round(array_sum($flowRate) / count($flowRate), 1),
        'readings' => generateWaterReadings($filter, $dateFrom, $dateTo, $sortBy)
    ];
}

function generateWaterReadings($filter, $dateFrom, $dateTo, $sortBy) {
    $readings = [
        ['time' => date('Y-m-d 19:00:00'), 'level' => 0, 'consumed' => 350, 'flow_rate' => 0.35, 'status' => 'normal'],
        ['time' => date('Y-m-d 12:00:00'), 'level' => 35, 'consumed' => 300, 'flow_rate' => 0.30, 'status' => 'normal'],
        ['time' => date('Y-m-d 06:00:00'), 'level' => 65, 'consumed' => 350, 'flow_rate' => 0.35, 'status' => 'normal'],
    ];
    
    if ($sortBy === 'oldest') {
        $readings = array_reverse($readings);
    }
    
    return $readings;
}

$waterData = getWaterData($filter, $dateFrom, $dateTo, $sortBy);
$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Monitoring | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-primary: #FFFCF2;
            --bg-secondary: #FFF8E0;
            --bg-card: #FFFFFF;
            --text-primary: #3E2C1C;
            --text-secondary: #5C4A1E;
            --text-muted: #8B7355;
            --accent: #FFD62E;
            --accent-dark: #E6B800;
            --accent-light: #FFF3CC;
            --sidebar-bg: #5C3D2E;
            --sidebar-text: #E8D5C4;
            --sidebar-muted: #B8977A;
            --green: #27AE60;
            --green-light: #E8F5E9;
            --yellow: #F39C12;
            --yellow-light: #FFF8E1;
            --red: #E74C3C;
            --red-light: #FDEDEC;
            --blue: #2980B9;
            --blue-light: #EBF5FB;
            --orange: #E67E22;
            --sidebar-width: 300px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(139, 115, 30, 0.06);
            --shadow-md: 0 8px 24px rgba(139, 115, 30, 0.1);
            --shadow-lg: 0 12px 40px rgba(139, 115, 30, 0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; }
        
        /* ===== SIDEBAR - NO SCROLLBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #6B4226 0%, #5C3D2E 40%, #4A2F1F 100%);
            color: var(--sidebar-text);
            z-index: 1000;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 4px 0 30px rgba(0,0,0,0.25);
        }
        /* Hide scrollbar completely */
        .sidebar::-webkit-scrollbar { display: none; width: 0; height: 0; }
        .sidebar { scrollbar-width: none; -ms-overflow-style: none; }
        
        .sidebar-logo { 
            padding: 2rem 1.8rem; 
            border-bottom: 1px solid rgba(232, 213, 196, 0.12); 
            text-align: center;
            flex-shrink: 0;
        }
        .sidebar-logo h2 { 
            font-size: 1.7rem; 
            font-weight: 800; 
            background: linear-gradient(135deg, #FFD62E, #FFE699); 
            -webkit-background-clip: text; 
            background-clip: text; 
            color: transparent; 
            letter-spacing: -0.5px;
        }
        .sidebar-logo .logo-icon { 
            font-size: 2.4rem; 
            color: #FFD62E; 
            margin-bottom: 0.5rem; 
            display: block; 
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 1rem 1rem 1rem 1.2rem;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar-nav::-webkit-scrollbar { display: none; width: 0; height: 0; }
        
        .sidebar-nav .nav-section { padding: 0.2rem 0.3rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { 
            font-size: 0.6rem; 
            text-transform: uppercase; 
            letter-spacing: 1.8px; 
            color: var(--sidebar-muted); 
            margin-bottom: 0.5rem; 
            font-weight: 700; 
            padding-left: 0.5rem; 
            opacity: 0.8;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.7rem 1rem;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 0.1rem;
            transition: all 0.25s ease;
            font-size: 0.88rem;
            font-weight: 500;
            position: relative;
        }
        .sidebar-nav a:hover { 
            background: rgba(255, 214, 46, 0.08); 
            color: #FFD62E; 
            transform: translateX(4px); 
        }
        .sidebar-nav a.active { 
            background: rgba(255, 214, 46, 0.12); 
            color: #FFD62E; 
            font-weight: 600; 
        }
        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 50%;
            background: #FFD62E;
            border-radius: 4px;
        }
        .sidebar-nav a i { 
            width: 24px; 
            text-align: center; 
            font-size: 1rem; 
            flex-shrink: 0;
        }
        .sidebar-nav a .badge-sidebar { 
            margin-left: auto; 
            background: var(--red); 
            color: white; 
            font-size: 0.6rem; 
            padding: 0.1rem 0.5rem; 
            border-radius: 20px; 
            font-weight: 600; 
        }
        
        .sidebar-user { 
            padding: 1rem 1.8rem; 
            border-top: 1px solid rgba(232, 213, 196, 0.12); 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            flex-shrink: 0;
            background: rgba(0,0,0,0.15);
        }
        .sidebar-user .avatar { 
            width: 46px; 
            height: 46px; 
            border-radius: 14px; 
            background: #FFD62E; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700; 
            color: #3E2C1C; 
            font-size: 1.2rem; 
            flex-shrink: 0; 
        }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.95rem; color: var(--sidebar-text); }
        .sidebar-user .user-info .role { font-size: 0.7rem; color: var(--sidebar-muted); }
        
        .sidebar-footer { 
            padding: 0.5rem 1.5rem 1.2rem; 
            border-top: 1px solid rgba(232, 213, 196, 0.08); 
            flex-shrink: 0;
        }
        .sidebar-footer a { 
            display: flex; 
            align-items: center; 
            gap: 0.8rem; 
            color: var(--sidebar-muted); 
            text-decoration: none; 
            padding: 0.5rem 0.8rem; 
            font-size: 0.85rem; 
            transition: all 0.2s; 
            border-radius: 12px; 
        }
        .sidebar-footer a:hover { color: #FFD62E; background: rgba(255, 214, 46, 0.05); transform: translateX(4px); }
        
        /* ===== SIDEBAR OVERLAY ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(4px);
        }
        .sidebar-overlay.active { display: block; }
        
        /* ===== MAIN CONTENT ===== */
        .main-content { 
            margin-left: var(--sidebar-width); 
            flex: 1; 
            min-height: 100vh; 
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1); 
            overflow-x: hidden;
        }
        
        /* ===== TOP HEADER ===== */
        .top-header { 
            height: var(--header-height); 
            background: var(--bg-card); 
            border-bottom: 1px solid rgba(255, 214, 46, 0.15); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 2rem; 
            position: sticky; 
            top: 0; 
            z-index: 999; 
            box-shadow: var(--shadow-sm); 
        }
        .top-header .header-left { display: flex; align-items: center; gap: 1.5rem; }
        .top-header .menu-toggle { 
            display: none; 
            font-size: 1.6rem; 
            cursor: pointer; 
            color: var(--text-primary); 
            background: none; 
            border: none; 
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .top-header .menu-toggle:hover { background: var(--bg-secondary); }
        .top-header .date-time-container { display: flex; flex-direction: column; gap: 0.1rem; }
        .top-header .date-time-container .date { font-size: 0.8rem; color: var(--text-muted); }
        .top-header .date-time-container .time { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        
        .notification-bell { 
            position: relative; 
            background: var(--bg-secondary); 
            border-radius: 50%; 
            width: 44px; 
            height: 44px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            transition: all 0.2s; 
            border: 1px solid rgba(255, 214, 46, 0.25); 
            text-decoration: none; 
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
            min-width: 20px; 
            text-align: center; 
        }
        
        /* ===== PAGE CONTENT ===== */
        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        
        /* ===== CURRENT READINGS - IMPROVED LAYOUT ===== */
        .current-readings { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        
        .reading-card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            padding: 2rem 1.5rem; 
            box-shadow: var(--shadow-md); 
            border: 1px solid rgba(255, 214, 46, 0.08); 
            transition: transform 0.25s ease, box-shadow 0.25s ease; 
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-height: 250px;
            justify-content: center;
        }
        .reading-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        
        .reading-card .reading-icon { 
            font-size: 2.6rem; 
            margin-bottom: 0.8rem; 
        }
        
        /* Water Level Card */
        .reading-card.water-card { border-top: 4px solid #2980B9 !important; }
        .reading-card.water-card .reading-icon { color: #2980B9; }
        
        .tank-wrapper { 
            display: flex; 
            align-items: center; 
            gap: 2rem; 
            width: 100%; 
            justify-content: center;
            padding: 0.5rem 0;
        }
        .tank-container { 
            position: relative; 
            width: 80px; 
            height: 130px; 
            border: 4px solid #8B7355; 
            border-radius: 10px 10px 20px 20px; 
            overflow: hidden; 
            background: #E8E0D0; 
            flex-shrink: 0; 
            box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
        }
        .tank-fill { 
            position: absolute; 
            bottom: 0; 
            width: 100%; 
            background: linear-gradient(180deg, #3498DB, #1a5276); 
            transition: height 0.5s ease; 
        }
        .tank-label { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            font-weight: 800; 
            font-size: 1.4rem; 
            color: white; 
            text-shadow: 0 2px 8px rgba(0,0,0,0.6); 
            z-index: 1; 
        }
        .tank-info { 
            text-align: left; 
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .tank-info .level-value { 
            font-size: 2.8rem; 
            font-weight: 800; 
            color: #2980B9; 
            line-height: 1; 
        }
        .tank-info .level-label { 
            font-size: 0.9rem; 
            color: var(--text-muted); 
            font-weight: 500;
        }
        .tank-info .reading-status { 
            display: inline-block; 
            padding: 0.35rem 1.6rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.2rem;
            align-self: flex-start;
        }
        .status-normal { background: var(--green-light); color: var(--green); }
        .status-warning { background: var(--yellow-light); color: var(--yellow); }
        .status-danger { background: var(--red-light); color: var(--red); }
        .status-on { background: #E8F5E9; color: #27AE60; }
        .status-off { background: #FDEDEC; color: #E74C3C; }
        
        /* Water Pump Card */
        .reading-card.pump-card { border-top: 4px solid #27AE60 !important; }
        .reading-card.pump-card .reading-icon { color: #27AE60; }
        
        .pump-status-wrapper { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 0.5rem; 
            width: 100%; 
        }
        .pump-status-wrapper .pump-icon { 
            font-size: 3.5rem; 
            color: #27AE60; 
        }
        .pump-status-wrapper .pump-icon.off { color: #95A5A6; }
        .pump-status-wrapper .reading-value { 
            font-size: 2.2rem; 
            font-weight: 800; 
            color: var(--text-primary);
            line-height: 1;
        }
        .pump-status-wrapper .reading-label { 
            font-size: 0.9rem; 
            color: var(--text-muted); 
            font-weight: 500;
        }
        
        .consumption-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 0.6rem; 
            margin-top: 0.8rem; 
            width: 100%;
        }
        .consumption-item { 
            background: var(--bg-secondary); 
            padding: 0.6rem 0.5rem; 
            border-radius: 10px; 
        }
        .consumption-item .value { 
            font-size: 1.1rem; 
            font-weight: 700; 
            color: var(--accent-dark); 
        }
        .consumption-item .label { 
            font-size: 0.65rem; 
            color: var(--text-muted); 
        }
        
        /* ===== STATS MINI GRID ===== */
        .stats-mini-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 1rem; 
            margin-bottom: 1.5rem; 
        }
        .stat-mini-card { 
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 1.2rem; 
            text-align: center; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.08); 
            transition: transform 0.2s; 
        }
        .stat-mini-card:hover { transform: translateY(-3px); }
        .stat-mini-card .stat-mini-value { font-size: 1.6rem; font-weight: 700; }
        .stat-mini-card .stat-mini-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        /* ===== CHARTS ===== */
        .charts-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1.5rem; 
            margin-bottom: 1.5rem; 
        }
        .chart-card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            padding: 1.5rem; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.08); 
            transition: box-shadow 0.2s; 
        }
        .chart-card:hover { box-shadow: var(--shadow-md); }
        .chart-card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1rem; 
        }
        .chart-card-title { 
            font-weight: 700; 
            font-size: 1rem; 
            display: flex; 
            align-items: center; 
            gap: 0.5rem; 
        }
        .chart-card-title i { font-size: 1.1rem; }
        .chart-wrapper { position: relative; width: 100%; height: 280px; max-height: 280px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }
        
        .chart-card.flow-chart { margin-bottom: 1.5rem; }
        .chart-card.flow-chart .chart-wrapper { height: 200px; max-height: 200px; }
        
        /* ===== FILTER BAR ===== */
        .filter-bar { 
            display: flex; 
            gap: 0.5rem; 
            flex-wrap: wrap; 
            align-items: center; 
            margin-bottom: 1.5rem; 
            padding: 0.8rem 1.2rem; 
            background: var(--bg-card); 
            border-radius: 12px; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.08); 
        }
        .filter-btn { 
            padding: 0.4rem 1.2rem; 
            border-radius: 20px; 
            border: 1px solid rgba(255, 214, 46, 0.25); 
            background: var(--bg-card); 
            cursor: pointer; 
            font-size: 0.8rem; 
            font-weight: 500; 
            color: var(--text-secondary); 
            transition: all 0.2s; 
            font-family: 'Inter', sans-serif; 
            white-space: nowrap; 
        }
        .filter-btn:hover { background: var(--accent-light); border-color: var(--accent); }
        .filter-btn.active { background: #FFD62E; color: #3E2C1C; border-color: #FFD62E; font-weight: 600; }
        .filter-separator { width: 1px; height: 24px; background: rgba(255, 214, 46, 0.2); margin: 0 0.5rem; }
        .date-input { 
            padding: 0.35rem 0.7rem; 
            border-radius: 20px; 
            border: 1px solid rgba(255, 214, 46, 0.25); 
            font-family: 'Inter', sans-serif; 
            font-size: 0.8rem; 
            background: var(--bg-secondary); 
        }
        .filter-select { 
            padding: 0.35rem 0.8rem; 
            border-radius: 20px; 
            border: 1px solid rgba(255, 214, 46, 0.25); 
            font-family: 'Inter', sans-serif; 
            font-size: 0.8rem; 
            background: var(--bg-secondary); 
            cursor: pointer; 
        }
        
        /* ===== TABLE ===== */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: var(--bg-secondary); padding: 0.7rem 0.8rem; text-align: left; font-weight: 600; border-bottom: 2px solid rgba(255, 214, 46, 0.15); }
        td { padding: 0.7rem 0.8rem; border-bottom: 1px solid rgba(255, 214, 46, 0.06); }
        tr:hover td { background: var(--bg-secondary); }
        .card-badge { 
            padding: 0.25rem 0.7rem; 
            border-radius: 15px; 
            font-size: 0.7rem; 
            font-weight: 600; 
            display: inline-block; 
        }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }
        
        /* ===== ALERTS SECTION HIDDEN ===== */
        .alerts-section { display: none; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { 
                transform: translateX(-100%);
                width: 320px;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            
            .current-readings { grid-template-columns: 1fr; max-width: 500px; margin-left: auto; margin-right: auto; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .chart-wrapper { height: 220px; max-height: 220px; }
            .chart-card.flow-chart .chart-wrapper { height: 180px; max-height: 180px; }
        }
        
        @media (max-width: 768px) {
            .stats-mini-grid { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .reading-card { min-height: 200px; padding: 1.5rem; }
            .tank-wrapper { gap: 1rem; }
            .tank-container { width: 60px; height: 100px; }
            .tank-info .level-value { font-size: 2rem; }
        }
        
        @media (max-width: 640px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-separator { display: none; }
            .stats-mini-grid { grid-template-columns: 1fr; }
            .current-readings { max-width: 100%; }
            .tank-wrapper { flex-direction: column; gap: 0.8rem; }
            .tank-info { text-align: center; align-items: center; }
            .tank-info .reading-status { align-self: center; }
            .consumption-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <span class="logo-icon"><i class="fas fa-feather-alt"></i></span>
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
                <a href="water_monitoring.php" class="active"><i class="fas fa-water"></i> Water Monitoring</a>
                <a href="chicken_status.php"><i class="fas fa-chicken"></i> Chicken Status</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">AI Detection</div>
                <a href="live_camera.php"><i class="fas fa-camera"></i> Live Camera Feed</a>
                <a href="detection_results.php"><i class="fas fa-brain"></i> Detection Results</a>
                <a href="detection_history.php"><i class="fas fa-history"></i> Detection History</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Automation</div>
                <a href="fan_control.php"><i class="fas fa-fan"></i> Fan Control</a>
                <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
                <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
                <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Inventory</div>
                <a href="feed_inventory.php"><i class="fas fa-utensils"></i> Feed Inventory</a>
                <a href="water_inventory.php"><i class="fas fa-water"></i> Water Inventory</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <span class="badge-sidebar"><?php echo $waterData['alerts'] ? count($waterData['alerts']) : 0; ?></span></a>
                <a href="settings.php"><i class="fas fa-sliders-h"></i> Settings</a>
            </div>
        </nav>
        <div class="sidebar-user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?></div>
            <div class="user-info">
                <div class="name"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></div>
                <div class="role">Farm Administrator</div>
            </div>
        </div>
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
                <div class="notification-bell" onclick="window.location.href='notifications.php'">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                    <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="page-content">
            <h1 class="page-title"><i class="fas fa-water" style="color:#2980B9;"></i> Water Monitoring</h1>
            <p class="page-subtitle">Real-time water level monitoring and consumption tracking with scheduled watering at 6:00 AM, 12:00 PM, and 7:00 PM</p>

            <!-- Alerts Section - Hidden -->
            <div class="alerts-section"></div>

            <!-- ===== IMPROVED CARDS ===== -->
            <div class="current-readings">
                <!-- Card 1: Water Level -->
                <div class="reading-card water-card">
                    <div class="reading-icon"><i class="fas fa-water"></i></div>
                    <div class="tank-wrapper">
                        <div class="tank-container">
                            <div class="tank-fill" style="height:<?php echo $waterData['current_water_level']; ?>%;"></div>
                            <div class="tank-label"><?php echo $waterData['current_water_level']; ?>%</div>
                        </div>
                        <div class="tank-info">
                            <div class="level-value"><?php echo $waterData['current_water_level']; ?>%</div>
                            <div class="level-label">Current Water Level</div>
                            <span class="reading-status status-<?php echo $waterData['water_status']; ?>"><?php echo ucfirst($waterData['water_status']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Water Pump Status -->
                <div class="reading-card pump-card">
                    <div class="reading-icon"><i class="fas fa-hand-holding-water"></i></div>
                    <div class="pump-status-wrapper">
                        <i class="fas fa-water-pump pump-icon <?php echo $waterData['water_pump_status'] === 'ON' ? '' : 'off'; ?>"></i>
                        <div class="reading-value"><?php echo $waterData['water_pump_status']; ?></div>
                        <div class="reading-label">Water Pump Status</div>
                        <span class="reading-status <?php echo $waterData['water_pump_status'] === 'ON' ? 'status-on' : 'status-off'; ?>"><?php echo $waterData['pump_status_text']; ?></span>
                    </div>
                    <div class="consumption-grid">
                        <div class="consumption-item">
                            <div class="value"><?php echo $waterData['water_consumed_today']; ?> ml</div>
                            <div class="label">Today</div>
                        </div>
                        <div class="consumption-item">
                            <div class="value"><?php echo $waterData['water_consumed_week']; ?> ml</div>
                            <div class="label">4 Days</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Mini Grid -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#2980B9;"><?php echo $waterData['total_consumption']; ?> ml</div><div class="stat-mini-label">Total Consumption</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#27AE60;"><?php echo $waterData['avg_consumption']; ?> ml</div><div class="stat-mini-label">Average Daily</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#E67E22;"><?php echo $waterData['avg_flow_rate']; ?> L/h</div><div class="stat-mini-label">Avg Flow Rate</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#8B7355;"><?php echo count($waterData['readings']); ?></div><div class="stat-mini-label">Total Readings</div></div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <span style="font-weight:600;font-size:0.85rem;margin-right:0.3rem;"><i class="fas fa-filter"></i> View:</span>
                <button class="filter-btn <?php echo $filter === '24h' ? 'active' : ''; ?>" onclick="changeFilter('24h')"><i class="fas fa-clock"></i> 24 Hours</button>
                <button class="filter-btn <?php echo $filter === '4d' ? 'active' : ''; ?>" onclick="changeFilter('4d')"><i class="fas fa-calendar-alt"></i> 4 Days</button>
                <span class="filter-separator"></span>
                <span style="font-size:0.75rem;color:var(--text-muted);">Custom Range:</span>
                <input type="date" class="date-input" id="dateFromInput" value="<?php echo $dateFrom; ?>" style="max-width:140px;">
                <span style="font-size:0.8rem;color:var(--text-muted);">to</span>
                <input type="date" class="date-input" id="dateToInput" value="<?php echo $dateTo; ?>" style="max-width:140px;">
                <button class="filter-btn" onclick="applyCustomRange()" style="background:var(--accent);border-color:var(--accent);"><i class="fas fa-check"></i> Apply</button>
                <span class="filter-separator"></span>
                <select class="filter-select" onchange="changeSort(this.value)" style="cursor:pointer;">
                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
                <?php if ($filter === 'custom' || $dateFrom || $dateTo): ?>
                <button class="filter-btn" onclick="resetFilters()" style="background:var(--red-light);color:var(--red);border-color:var(--red-light);"><i class="fas fa-times"></i> Reset</button>
                <?php endif; ?>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title"><i class="fas fa-chart-bar" style="color:#2980B9;"></i> Water Consumption</span>
                        <span class="card-badge badge-info">ml</span>
                    </div>
                    <div class="chart-wrapper"><canvas id="waterConsumptionChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title"><i class="fas fa-chart-line" style="color:#27AE60;"></i> Water Level</span>
                        <span class="card-badge badge-info">%</span>
                    </div>
                    <div class="chart-wrapper"><canvas id="waterLevelChart"></canvas></div>
                </div>
            </div>

            <!-- Flow Rate Chart -->
            <div class="chart-card flow-chart">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-tachometer-alt"></i> Flow Rate</span>
                    <span class="card-badge badge-info">L/h</span>
                </div>
                <div class="chart-wrapper"><canvas id="flowRateChart"></canvas></div>
            </div>

            <!-- Water Level Readings Table -->
            <div class="chart-card" style="padding:0;overflow:hidden;">
                <div class="chart-card-header" style="padding:1.5rem 1.5rem 0 1.5rem;">
                    <span class="chart-card-title"><i class="fas fa-list"></i> Water Level Readings</span>
                    <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo count($waterData['readings']); ?> readings</span>
                </div>
                <div class="table-container" style="padding:0 1.5rem 1.5rem 1.5rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Level</th>
                                <th>Consumed</th>
                                <th>Flow Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waterData['readings'] as $reading): ?>
                            <tr>
                                <td><?php echo $reading['time']; ?></td>
                                <td><strong><?php echo $reading['level']; ?>%</strong></td>
                                <td><?php echo $reading['consumed']; ?> ml</td>
                                <td><?php echo $reading['flow_rate']; ?> L/h</td>
                                <td><span class="card-badge badge-<?php echo $reading['status'] === 'normal' ? 'success' : 'warning'; ?>"><?php echo ucfirst($reading['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chartLabels = <?php echo json_encode($waterData['labels']); ?>;
        const waterData = <?php echo json_encode($waterData['water_data']); ?>;
        const waterRemaining = <?php echo json_encode($waterData['water_remaining']); ?>;
        const flowRate = <?php echo json_encode($waterData['flow_rate']); ?>;
        let waterConsumptionChart, waterLevelChart, flowRateChart;

        function updateClock() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);

        // ===== SIDEBAR TOGGLE WITH BURGER MENU =====
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking a link (mobile)
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });

        function changeFilter(filter) { 
            const params = new URLSearchParams(window.location.search);
            params.set('filter', filter);
            params.delete('date_from');
            params.delete('date_to');
            window.location.href = 'water_monitoring.php?' + params.toString();
        }
        
        function changeSort(sort) { 
            const params = new URLSearchParams(window.location.search);
            params.set('sort_by', sort);
            window.location.href = 'water_monitoring.php?' + params.toString();
        }
        
        function applyCustomRange() {
            const dateFrom = document.getElementById('dateFromInput').value;
            const dateTo = document.getElementById('dateToInput').value;
            if (!dateFrom && !dateTo) {
                alert('Please select a date range.');
                return;
            }
            const params = new URLSearchParams(window.location.search);
            params.set('filter', 'custom');
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            window.location.href = 'water_monitoring.php?' + params.toString();
        }
        
        function resetFilters() {
            window.location.href = 'water_monitoring.php';
        }

        function initCharts() {
            const chartOptions = { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { 
                        display: true, 
                        position: 'bottom', 
                        labels: { 
                            boxWidth: 12, 
                            padding: 10, 
                            font: { size: 10 } 
                        } 
                    } 
                }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(139,115,85,0.1)' },
                        ticks: { font: { size: 10 } }
                    }, 
                    x: { 
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    } 
                } 
            };
            
            if (waterConsumptionChart) waterConsumptionChart.destroy();
            if (waterLevelChart) waterLevelChart.destroy();
            if (flowRateChart) flowRateChart.destroy();

            const consumptionCtx = document.getElementById('waterConsumptionChart');
            if (consumptionCtx) {
                waterConsumptionChart = new Chart(consumptionCtx, {
                    type: 'bar',
                    data: { 
                        labels: chartLabels, 
                        datasets: [{ 
                            label: 'Water Consumed (ml)', 
                            data: waterData, 
                            backgroundColor: '#2980B9',
                            borderRadius: 6, 
                            maxBarThickness: 50 
                        }] 
                    },
                    options: chartOptions
                });
            }

            const levelCtx = document.getElementById('waterLevelChart');
            if (levelCtx) {
                waterLevelChart = new Chart(levelCtx, {
                    type: 'line',
                    data: { 
                        labels: chartLabels, 
                        datasets: [{ 
                            label: 'Water Level (%)', 
                            data: waterRemaining, 
                            borderColor: '#27AE60', 
                            backgroundColor: 'rgba(39,174,96,0.15)', 
                            fill: true, 
                            tension: 0.3, 
                            pointRadius: 4, 
                            pointBackgroundColor: '#27AE60',
                            borderWidth: 2 
                        }] 
                    },
                    options: {
                        ...chartOptions, 
                        scales: {
                            ...chartOptions.scales, 
                            y: { 
                                beginAtZero: false, 
                                max: 100, 
                                grid: { color: 'rgba(139,115,85,0.1)' },
                                ticks: { 
                                    font: { size: 10 },
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const flowCtx = document.getElementById('flowRateChart');
            if (flowCtx) {
                flowRateChart = new Chart(flowCtx, {
                    type: 'line',
                    data: { 
                        labels: chartLabels, 
                        datasets: [{ 
                            label: 'Flow Rate (L/h)', 
                            data: flowRate, 
                            borderColor: '#8B7355', 
                            backgroundColor: 'rgba(139,115,85,0.08)', 
                            fill: true, 
                            tension: 0.3, 
                            pointRadius: 4, 
                            pointBackgroundColor: '#8B7355',
                            borderWidth: 2 
                        }] 
                    },
                    options: chartOptions
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() { 
            initCharts(); 
        });
        
        window.addEventListener('resize', function() {
            if (waterConsumptionChart) waterConsumptionChart.resize();
            if (waterLevelChart) waterLevelChart.resize();
            if (flowRateChart) flowRateChart.resize();
        });
    </script>
</body>
</html>