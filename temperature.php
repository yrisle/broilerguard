<?php
// temperature.php - Temperature & Humidity Monitoring Module
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

// Shared sensor data cache - DHT11 sensor data
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

function getTemperatureData($filter, $dateFrom = '', $dateTo = '', $sortBy = 'newest') {
    global $sharedData;
    
    $currentTemp = $sharedData['temperature'];
    $currentHumidity = $sharedData['humidity'];
    
    // 4 days testing period based on thesis (monitoring period)
    switch ($filter) {
        case '24h':
            $labels = ['12AM', '2AM', '4AM', '6AM', '8AM', '10AM', '12PM', '2PM', '4PM', '6PM', '8PM', '10PM'];
            $tempData = [30.1, 30.5, 31.0, 31.5, 32.0, 32.8, 33.5, 34.0, 33.8, 32.5, 31.5, 30.8];
            $humidityData = [72, 74, 75, 76, 73, 70, 65, 63, 65, 68, 70, 72];
            break;
        case '7d':
            $labels = ['Day 1', 'Day 2', 'Day 3', 'Day 4']; // 4 days testing period
            $tempData = [31.2, 32.1, 31.8, 32.5];
            $humidityData = [68, 70, 72, 69];
            break;
        case 'custom':
            $labels = [];
            $tempData = [];
            $humidityData = [];
            $start = $dateFrom ? strtotime($dateFrom) : strtotime('-4 days');
            $end = $dateTo ? strtotime($dateTo) : time();
            $interval = ($end - $start) / 10;
            for ($i = 0; $i <= 10; $i++) {
                $labels[] = date('M d', $start + ($i * $interval));
                $tempData[] = round(rand(300, 340) / 10, 1);
                $humidityData[] = rand(55, 80);
            }
            break;
        default:
            $labels = ['12AM', '2AM', '4AM', '6AM', '8AM', '10AM', '12PM', '2PM', '4PM', '6PM', '8PM', '10PM'];
            $tempData = [30.1, 30.5, 31.0, 31.5, 32.0, 32.8, 33.5, 34.0, 33.8, 32.5, 31.5, 30.8];
            $humidityData = [72, 74, 75, 76, 73, 70, 65, 63, 65, 68, 70, 72];
    }
    
    // Generate alerts based on current values - thesis thresholds
    $alerts = [];
    $tempStatus = 'normal';
    $humidityStatus = 'normal';
    
    // Broiler optimal temperature: 29°C - 35°C (based on thesis)
    if ($currentTemp > 35) {
        $tempStatus = 'danger';
        $alerts[] = ['type' => 'critical', 'message' => "High temperature alert: {$currentTemp}°C exceeds maximum threshold of 35°C - Immediate ventilation required", 'time' => 'Just now', 'icon' => 'fa-temperature-high'];
    } elseif ($currentTemp < 29) {
        $tempStatus = 'warning';
        $alerts[] = ['type' => 'warning', 'message' => "Low temperature warning: {$currentTemp}°C below minimum threshold of 29°C - Heating may be needed", 'time' => 'Just now', 'icon' => 'fa-temperature-low'];
    }
    
    // Broiler optimal humidity: 60% - 70% (based on thesis)
    if ($currentHumidity > 70) {
        $humidityStatus = 'danger';
        $alerts[] = ['type' => 'critical', 'message' => "High humidity alert: {$currentHumidity}% exceeds maximum threshold of 70% - Ventilation adjustment recommended", 'time' => 'Just now', 'icon' => 'fa-tint'];
    } elseif ($currentHumidity < 55) {
        $humidityStatus = 'warning';
        $alerts[] = ['type' => 'warning', 'message' => "Low humidity warning: {$currentHumidity}% below minimum threshold of 55% - Moisture supplementation needed", 'time' => 'Just now', 'icon' => 'fa-tint-slash'];
    }
    
    return [
        'current_temp' => $currentTemp,
        'current_humidity' => $currentHumidity,
        'temp_status' => $tempStatus,
        'humidity_status' => $humidityStatus,
        'labels' => $labels,
        'temp_data' => $tempData,
        'humidity_data' => $humidityData,
        'alerts' => $alerts,
        'min_temp' => min($tempData),
        'max_temp' => max($tempData),
        'avg_temp' => round(array_sum($tempData) / count($tempData), 1),
        'min_humidity' => min($humidityData),
        'max_humidity' => max($humidityData),
        'avg_humidity' => round(array_sum($humidityData) / count($humidityData)),
        'readings' => generateTemperatureReadings($filter, $dateFrom, $dateTo, $sortBy)
    ];
}

function generateTemperatureReadings($filter, $dateFrom, $dateTo, $sortBy) {
    // Sensor readings from DHT11 - single sensor implementation
    $readings = [
        ['time' => date('Y-m-d H:i:s', strtotime('now')), 'temp' => 33.5, 'humidity' => 65, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-30 minutes')), 'temp' => 33.8, 'humidity' => 63, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'temp' => 34.2, 'humidity' => 61, 'status' => 'warning'],
        ['time' => date('Y-m-d H:i:s', strtotime('-1.5 hours')), 'temp' => 34.0, 'humidity' => 62, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'temp' => 33.5, 'humidity' => 64, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-2.5 hours')), 'temp' => 35.2, 'humidity' => 60, 'status' => 'danger'],
        ['time' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'temp' => 33.1, 'humidity' => 66, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-3.5 hours')), 'temp' => 32.8, 'humidity' => 68, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-4 hours')), 'temp' => 32.5, 'humidity' => 70, 'status' => 'normal'],
        ['time' => date('Y-m-d H:i:s', strtotime('-4.5 hours')), 'temp' => 32.1, 'humidity' => 72, 'status' => 'normal'],
    ];
    
    if ($sortBy === 'oldest') {
        $readings = array_reverse($readings);
    }
    
    return $readings;
}

$tempData = getTemperatureData($filter, $dateFrom, $dateTo, $sortBy);
$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temperature & Humidity Monitoring | BroilerGuard</title>
    <meta name="description" content="Real-time temperature and humidity monitoring for broiler chickens in small-scale tunnel-ventilated houses using DHT11 sensor.">
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
            --orange-light: #FDF2E9;
            --sidebar-width: 300px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(139, 115, 30, 0.06);
            --shadow-md: 0 8px 24px rgba(139, 115, 30, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; }
        
        /* ============================================ */
        /* SIDEBAR - FEED MONITORING STYLE */
        /* ============================================ */
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
        
        /* ============================================ */
        /* SIDEBAR OVERLAY */
        /* ============================================ */
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
        
        /* ============================================ */
        /* MAIN CONTENT */
        /* ============================================ */
        .main-content { 
            margin-left: var(--sidebar-width); 
            flex: 1; 
            min-height: 100vh; 
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1); 
            overflow-x: hidden;
        }
        
        /* ============================================ */
        /* TOP HEADER */
        /* ============================================ */
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
        .top-header .date-time-container { display: flex; flex-direction: column; gap: 0.05rem; }
        .top-header .date-time-container .date { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px; }
        .top-header .date-time-container .time { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
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
        
        /* ============================================ */
        /* PAGE CONTENT */
        /* ============================================ */
        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }
        
        /* ============================================ */
        /* CURRENT READINGS CARDS */
        /* ============================================ */
        .current-readings { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 1.5rem; 
            margin-bottom: 1.5rem; 
        }
        .reading-card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            padding: 1.5rem; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.1); 
            transition: all 0.3s ease; 
        }
        .reading-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .reading-card.temp-card { border-top: 4px solid var(--orange); }
        .reading-card.humidity-card { border-top: 4px solid var(--blue); }
        .reading-card .reading-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; }
        .reading-card .reading-icon { font-size: 2rem; }
        .reading-card.temp-card .reading-icon { color: var(--orange); }
        .reading-card.humidity-card .reading-icon { color: var(--blue); }
        .reading-card .reading-value { font-size: 3rem; font-weight: 800; line-height: 1; margin-bottom: 0.3rem; }
        .reading-card.temp-card .reading-value { color: var(--orange); }
        .reading-card.humidity-card .reading-value { color: var(--blue); }
        .reading-card .reading-label { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }
        .reading-card .reading-status { 
            display: inline-block; 
            margin-top: 0.8rem; 
            padding: 0.3rem 1rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
        }
        .status-normal { background: var(--green-light); color: var(--green); }
        .status-warning { background: var(--yellow-light); color: var(--yellow); }
        .status-danger { background: var(--red-light); color: var(--red); }
        .reading-range { 
            margin-top: 0.8rem; 
            font-size: 0.75rem; 
            color: var(--text-muted); 
            display: flex; 
            justify-content: space-between;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(255, 214, 46, 0.1);
        }
        .reading-range span i { margin-right: 0.3rem; }
        
        /* ============================================ */
        /* STATS MINI GRID */
        /* ============================================ */
        .stats-mini-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-mini-card { 
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 1rem; 
            text-align: center; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.1);
            transition: all 0.2s;
        }
        .stat-mini-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-mini-card .stat-mini-value { font-size: 1.5rem; font-weight: 700; }
        .stat-mini-card .stat-mini-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        /* ============================================ */
        /* FILTER BAR */
        /* ============================================ */
        .filter-bar { 
            display: flex; 
            gap: 0.5rem; 
            flex-wrap: wrap; 
            align-items: center; 
            margin-bottom: 1.5rem; 
            padding: 0.6rem 1rem; 
            background: var(--bg-card); 
            border-radius: 12px; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.1); 
        }
        .filter-btn { 
            padding: 0.35rem 0.8rem; 
            border-radius: 20px; 
            border: 1px solid rgba(255, 214, 46, 0.3); 
            background: var(--bg-card); 
            cursor: pointer; 
            font-size: 0.75rem; 
            font-weight: 500; 
            color: var(--text-secondary); 
            transition: all 0.2s; 
        }
        .filter-btn.active { background: var(--accent); color: #3E2C1C; border-color: var(--accent); font-weight: 600; }
        .filter-btn:hover:not(.active) { background: var(--accent-light); }
        .filter-separator { width: 1px; height: 20px; background: rgba(255, 214, 46, 0.3); margin: 0 0.3rem; }
        .date-input { padding: 0.35rem 0.7rem; border-radius: 20px; border: 1px solid rgba(255, 214, 46, 0.3); font-family: 'Inter', sans-serif; font-size: 0.75rem; background: var(--bg-secondary); }
        
        /* ============================================ */
        /* CHARTS */
        /* ============================================ */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .chart-card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            padding: 1.2rem; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.1); 
        }
        .chart-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; }
        .chart-card-title { font-weight: 600; font-size: 0.9rem; }
        .chart-wrapper { position: relative; width: 100%; height: 250px; max-height: 250px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }
        
        /* ============================================ */
        /* COMBINED CHART */
        /* ============================================ */
        .combined-chart-card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            padding: 1.2rem; 
            margin-bottom: 1.5rem; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(255, 214, 46, 0.1); 
        }
        
        /* ============================================ */
        /* ALERTS */
        /* ============================================ */
        .alerts-section { margin-bottom: 1.5rem; }
        .alert-item { 
            display: flex; 
            align-items: center; 
            gap: 0.8rem; 
            padding: 0.8rem 1rem; 
            background: var(--bg-card); 
            border-radius: 10px; 
            margin-bottom: 0.5rem; 
            border-left: 4px solid; 
            box-shadow: var(--shadow-sm); 
        }
        .alert-item.alert-critical { border-color: var(--red); background: var(--red-light); }
        .alert-item.alert-warning { border-color: var(--yellow); background: var(--yellow-light); }
        .alert-item .alert-icon { font-size: 1rem; }
        .alert-item.alert-critical .alert-icon { color: var(--red); }
        .alert-item.alert-warning .alert-icon { color: var(--yellow); }
        .alert-item .alert-message { font-size: 0.8rem; flex: 1; }
        .alert-item .alert-time { font-size: 0.65rem; color: var(--text-muted); }
        
        /* ============================================ */
        /* TABLE */
        /* ============================================ */
        .table-card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            overflow: hidden; 
            border: 1px solid rgba(255, 214, 46, 0.1); 
        }
        .table-card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 1rem 1.5rem 0.5rem 1.5rem; 
        }
        .table-container { overflow-x: auto; padding: 0 1.5rem 1.5rem 1.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { 
            background: var(--bg-secondary); 
            padding: 0.7rem 0.8rem; 
            text-align: left; 
            font-weight: 600; 
            border-bottom: 2px solid rgba(255, 214, 46, 0.2); 
            color: var(--text-secondary); 
            font-size: 0.8rem;
        }
        td { padding: 0.7rem 0.8rem; border-bottom: 1px solid rgba(255, 214, 46, 0.08); }
        tr:hover td { background: var(--bg-secondary); }
        .card-badge { 
            padding: 0.25rem 0.7rem; 
            border-radius: 15px; 
            font-size: 0.65rem; 
            font-weight: 600; 
            display: inline-block; 
        }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }
        
        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .current-readings { grid-template-columns: 1fr; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .chart-wrapper { height: 200px; }
        }
        @media (max-width: 480px) {
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .stats-mini-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { padding: 0.5rem; gap: 0.3rem; }
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
        <div class="nav-section"><div class="nav-section-title">Main</div><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></div>
        <div class="nav-section"><div class="nav-section-title">Monitoring</div>
            <a href="temperature.php" class="active"><i class="fas fa-thermometer-half"></i> Temperature & Humidity</a>
            <a href="feed_monitoring.php"><i class="fas fa-utensils"></i> Feed Monitoring</a>
            <a href="water_monitoring.php"><i class="fas fa-water"></i> Water Monitoring</a>
            <a href="chicken_status.php"><i class="fas fa-chicken"></i> Chicken Status</a>
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
            <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
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
            <a href="notifications.php" class="notification-bell" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <div class="page-content">
        <h1 class="page-title"><i class="fas fa-thermometer-half" style="color:var(--orange);"></i> Environmental Monitoring</h1>
        <p class="page-subtitle">Real-time temperature and humidity monitoring using DHT11 sensor for broiler chickens in tunnel-ventilated house</p>

        <!-- Alerts -->
        <?php if (!empty($tempData['alerts'])): ?>
        <div class="alerts-section">
            <?php foreach ($tempData['alerts'] as $alert): ?>
            <div class="alert-item alert-<?php echo $alert['type']; ?>">
                <div class="alert-icon"><i class="fas <?php echo $alert['icon']; ?>"></i></div>
                <div class="alert-message"><?php echo $alert['message']; ?></div>
                <div class="alert-time"><?php echo $alert['time']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Current Readings -->
        <div class="current-readings">
            <div class="reading-card temp-card">
                <div class="reading-header">
                    <div>
                        <div class="reading-icon"><i class="fas fa-temperature-high"></i></div>
                    </div>
                    <span class="reading-status status-<?php echo $tempData['temp_status']; ?>"><?php echo ucfirst($tempData['temp_status']); ?></span>
                </div>
                <div class="reading-value"><?php echo $tempData['current_temp']; ?>°C</div>
                <div class="reading-label">Current Temperature</div>
                <div class="reading-range">
                    <span><i class="fas fa-arrow-down"></i> Min: <?php echo $tempData['min_temp']; ?>°C</span>
                    <span><i class="fas fa-arrow-up"></i> Max: <?php echo $tempData['max_temp']; ?>°C</span>
                    <span><i class="fas fa-chart-line"></i> Avg: <?php echo $tempData['avg_temp']; ?>°C</span>
                </div>
            </div>
            <div class="reading-card humidity-card">
                <div class="reading-header">
                    <div>
                        <div class="reading-icon"><i class="fas fa-tint"></i></div>
                    </div>
                    <span class="reading-status status-<?php echo $tempData['humidity_status']; ?>"><?php echo ucfirst($tempData['humidity_status']); ?></span>
                </div>
                <div class="reading-value"><?php echo $tempData['current_humidity']; ?>%</div>
                <div class="reading-label">Current Humidity</div>
                <div class="reading-range">
                    <span><i class="fas fa-arrow-down"></i> Min: <?php echo $tempData['min_humidity']; ?>%</span>
                    <span><i class="fas fa-arrow-up"></i> Max: <?php echo $tempData['max_humidity']; ?>%</span>
                    <span><i class="fas fa-chart-line"></i> Avg: <?php echo $tempData['avg_humidity']; ?>%</span>
                </div>
            </div>
        </div>

        <!-- Stats Mini -->
        <div class="stats-mini-grid">
            <div class="stat-mini-card"><div class="stat-mini-value"><?php echo $tempData['avg_temp']; ?>°C</div><div class="stat-mini-label">Average Temperature</div></div>
            <div class="stat-mini-card"><div class="stat-mini-value"><?php echo $tempData['avg_humidity']; ?>%</div><div class="stat-mini-label">Average Humidity</div></div>
            <div class="stat-mini-card"><div class="stat-mini-value"><?php echo count($tempData['readings']); ?></div><div class="stat-mini-label">Total Readings</div></div>
            <div class="stat-mini-card"><div class="stat-mini-value"><?php echo count($tempData['alerts']); ?></div><div class="stat-mini-label">Active Alerts</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span style="font-weight:600;font-size:0.75rem;"><i class="fas fa-filter"></i> Period:</span>
            <button class="filter-btn <?php echo $filter === '24h' ? 'active' : ''; ?>" onclick="changeFilter('24h')">Last 24H</button>
            <button class="filter-btn <?php echo $filter === '7d' ? 'active' : ''; ?>" onclick="changeFilter('7d')">4 Days</button>
            <span class="filter-separator"></span>
            <input type="date" class="date-input" id="dateFromInput" value="<?php echo $dateFrom; ?>">
            <span style="font-size:0.7rem;">to</span>
            <input type="date" class="date-input" id="dateToInput" value="<?php echo $dateTo; ?>">
            <button class="filter-btn" onclick="applyCustomRange()">Apply</button>
            <span class="filter-separator"></span>
            <select class="filter-btn" onchange="changeSort(this.value)" style="cursor:pointer;">
                <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
            </select>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header"><span class="chart-card-title"><i class="fas fa-temperature-high" style="color:var(--orange);"></i> Temperature Trend</span><span class="card-badge badge-info">°C</span></div>
                <div class="chart-wrapper"><canvas id="temperatureChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header"><span class="chart-card-title"><i class="fas fa-tint" style="color:var(--blue);"></i> Humidity Trend</span><span class="card-badge badge-info">%</span></div>
                <div class="chart-wrapper"><canvas id="humidityChart"></canvas></div>
            </div>
        </div>

        <!-- Combined Chart -->
        <div class="combined-chart-card">
            <div class="chart-card-header"><span class="chart-card-title"><i class="fas fa-chart-line" style="color:var(--accent-dark);"></i> Temperature & Humidity Combined</span></div>
            <div class="chart-wrapper" style="height: 280px;"><canvas id="combinedChart"></canvas></div>
        </div>

        <!-- Sensor Readings Table -->
        <div class="table-card">
            <div class="table-card-header">
                <span class="chart-card-title"><i class="fas fa-list"></i> Sensor Readings</span>
                <span style="font-size:0.7rem;color:var(--text-muted);"><?php echo count($tempData['readings']); ?> records</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Date & Time</th><th>Temperature</th><th>Humidity</th><th>Status</th>
                    </thead>
                    <tbody>
                        <?php foreach ($tempData['readings'] as $reading): ?>
                        <tr>
                            <td><?php echo $reading['time']; ?></td>
                            <td><strong><?php echo $reading['temp']; ?>°C</strong></td>
                            <td><?php echo $reading['humidity']; ?>%</td>
                            <td><span class="card-badge badge-<?php echo $reading['status'] === 'normal' ? 'success' : ($reading['status'] === 'warning' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($reading['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Thesis Reference Note -->
        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid rgba(255, 214, 46, 0.2); text-align: center;">
            <p style="font-size: 0.75rem; color: var(--text-muted);">
                <i class="fas fa-book" style="color: var(--accent-dark);"></i> 
                Based on the study: "Development of an IoT-Based Environmental Monitoring and Automation System for Broiler Chickens in a Small-Scale Tunnel-Ventilated House" — Batangas State University - ARASOF
            </p>
        </div>
    </div>
</div>

<script>
    const chartLabels = <?php echo json_encode($tempData['labels']); ?>;
    const tempDataArr = <?php echo json_encode($tempData['temp_data']); ?>;
    const humidityDataArr = <?php echo json_encode($tempData['humidity_data']); ?>;
    let tempChart, humChart, combinedChart;

    function updateClock() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    setInterval(updateClock, 1000);

    // ===== SIDEBAR TOGGLE =====
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

    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });

    function changeFilter(filter) { updateURL({ filter: filter, date_from: '', date_to: '' }); }
    function changeSort(sort) { updateURL({ sort_by: sort }); }
    function applyCustomRange() { updateURL({ filter: 'custom', date_from: document.getElementById('dateFromInput').value, date_to: document.getElementById('dateToInput').value }); }

    function updateURL(updates) {
        const params = new URLSearchParams(window.location.search);
        for (const [key, value] of Object.entries(updates)) { if (value) { params.set(key, value); } else { params.delete(key); } }
        window.location.href = 'temperature.php?' + params.toString();
    }

    function initCharts() {
        const chartOptions = { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 10 } } } 
            }, 
            scales: { y: { beginAtZero: false, grid: { color: 'rgba(139,115,85,0.08)' } }, x: { grid: { display: false } } } 
        };
        
        [tempChart, humChart, combinedChart].forEach(chart => { if (chart) chart.destroy(); });

        const tempCtx = document.getElementById('temperatureChart');
        if (tempCtx) tempChart = new Chart(tempCtx, { 
            type: 'line', 
            data: { labels: chartLabels, datasets: [{ label: 'Temperature (°C)', data: tempDataArr, borderColor: '#E67E22', backgroundColor: 'rgba(230,126,34,0.1)', fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2 }] }, 
            options: chartOptions 
        });

        const humCtx = document.getElementById('humidityChart');
        if (humCtx) humChart = new Chart(humCtx, { 
            type: 'line', 
            data: { labels: chartLabels, datasets: [{ label: 'Humidity (%)', data: humidityDataArr, borderColor: '#2980B9', backgroundColor: 'rgba(41,128,185,0.1)', fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2 }] }, 
            options: chartOptions 
        });

        const combinedCtx = document.getElementById('combinedChart');
        if (combinedCtx) {
            combinedChart = new Chart(combinedCtx, {
                type: 'line',
                data: { 
                    labels: chartLabels, 
                    datasets: [
                        { label: 'Temperature (°C)', data: tempDataArr, borderColor: '#E67E22', backgroundColor: 'rgba(230,126,34,0.05)', fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2, yAxisID: 'y' },
                        { label: 'Humidity (%)', data: humidityDataArr, borderColor: '#2980B9', backgroundColor: 'rgba(41,128,185,0.05)', fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2, yAxisID: 'y1' }
                    ] 
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 10 } } } },
                    scales: {
                        y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Temperature (°C)', color: '#E67E22', font: { size: 10 } }, grid: { color: 'rgba(139,115,85,0.08)' } },
                        y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Humidity (%)', color: '#2980B9', font: { size: 10 } }, grid: { drawOnChartArea: false } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initCharts);
</script>
</body>
</html>