<?php
// feed_monitoring.php - Feed Monitoring Module
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
$filter = $_GET['filter'] ?? '7d';
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

function getFeedData($filter, $dateFrom = '', $dateTo = '', $sortBy = 'newest') {
    global $sharedData;
    
    $currentFeedLevel = $sharedData['feed_level'];
    
    // DAILY FEED CONSUMPTION: 175-250g per day (0.175 - 0.250 kg)
    // 3 feedings per day: 6AM, 12PM, 5PM
    // Each feeding: ~58-83g (total 175-250g per day)
    $feedConsumedToday = round(rand(175, 250) / 1000, 3); // in kg
    
    // Generate feed data based on filter
    switch ($filter) {
        case '24h':
            // 24-hour data with 3 feedings (6AM, 12PM, 5PM)
            $labels = ['6:00 AM', '12:00 PM', '5:00 PM'];
            // Each feeding: ~58-83g (total 175-250g per day)
            $feedings = [];
            $total = 0;
            for ($i = 0; $i < 3; $i++) {
                $amount = round(rand(58, 83) / 1000, 3);
                $feedings[] = $amount;
                $total += $amount;
            }
            // Adjust to ensure total is within 175-250g
            $targetTotal = rand(175, 250) / 1000;
            $scaleFactor = $targetTotal / $total;
            $feedData = array_map(function($val) use ($scaleFactor) {
                return round($val * $scaleFactor, 3);
            }, $feedings);
            
            // Feed remaining after each feeding
            $feedRemaining = [];
            $current = $currentFeedLevel;
            foreach ($feedData as $consumed) {
                $current = max(0, $current - ($consumed / 3 * 100));
                $feedRemaining[] = round($current, 1);
            }
            break;
            
        case '7d':
            // 7 days of data - each day 175-250g
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $labels = $days;
            $feedData = [];
            $feedRemaining = [];
            $current = $currentFeedLevel;
            
            for ($i = 0; $i < 7; $i++) {
                $dailyConsumed = round(rand(175, 250) / 1000, 3);
                $feedData[] = $dailyConsumed;
                $current = max(0, $current - ($dailyConsumed / 3 * 100));
                $feedRemaining[] = round($current, 1);
            }
            break;
            
        case 'custom':
            $labels = [];
            $feedData = [];
            $feedRemaining = [];
            $start = $dateFrom ? strtotime($dateFrom) : strtotime('-4 days');
            $end = $dateTo ? strtotime($dateTo) : time();
            $days = ceil(($end - $start) / (60 * 60 * 24));
            $days = min(max($days, 1), 7); // Limit to max 7 days
            
            $current = $currentFeedLevel;
            for ($i = 0; $i < $days; $i++) {
                $date = date('M d', strtotime("+$i days", $start));
                $labels[] = $date;
                $dailyConsumed = round(rand(175, 250) / 1000, 3);
                $feedData[] = $dailyConsumed;
                $current = max(0, $current - ($dailyConsumed / 3 * 100));
                $feedRemaining[] = round($current, 1);
            }
            break;
            
        default:
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $feedData = [];
            $feedRemaining = [];
            $current = $currentFeedLevel;
            for ($i = 0; $i < 7; $i++) {
                $dailyConsumed = round(rand(175, 250) / 1000, 3);
                $feedData[] = $dailyConsumed;
                $current = max(0, $current - ($dailyConsumed / 3 * 100));
                $feedRemaining[] = round($current, 1);
            }
    }
    
    // Calculate totals
    $totalConsumption = array_sum($feedData);
    $avgConsumption = round($totalConsumption / count($feedData), 3);
    $minConsumption = round(min($feedData), 3);
    $maxConsumption = round(max($feedData), 3);
    
    // Calculate weekly consumption (last 7 days or available data)
    $weekData = array_slice($feedData, -min(7, count($feedData)));
    $feedConsumedWeek = round(array_sum($weekData), 3);
    
    // Generate alerts
    $alerts = [];
    $feedStatus = 'normal';
    
    if ($currentFeedLevel < 80) {
        $feedStatus = 'warning';
        $alerts[] = [
            'type' => 'warning',
            'message' => "Low feed warning: {$currentFeedLevel}% remaining. Please refill soon.",
            'time' => 'Just now',
            'icon' => 'fa-utensils'
        ];
    }
    if ($currentFeedLevel < 60) {
        $feedStatus = 'danger';
        $alerts[] = [
            'type' => 'critical',
            'message' => "Critical feed level: {$currentFeedLevel}% remaining. Immediate refill required!",
            'time' => 'Just now',
            'icon' => 'fa-exclamation-triangle'
        ];
    }
    
    // Feeding schedule - 3 times daily (6AM, 12PM, 5PM)
    $currentHour = (int)date('H');
    $feedSchedule = [
        ['time' => '06:00 AM', 'amount' => '~70g', 'status' => ($currentHour >= 6) ? 'completed' : 'pending'],
        ['time' => '12:00 PM', 'amount' => '~70g', 'status' => ($currentHour >= 12) ? 'completed' : 'pending'],
        ['time' => '05:00 PM', 'amount' => '~70g', 'status' => ($currentHour >= 17) ? 'completed' : 'pending'],
    ];
    
    return [
        'current_feed_level' => $currentFeedLevel,
        'feed_status' => $feedStatus,
        'labels' => $labels,
        'feed_data' => $feedData,
        'feed_remaining' => $feedRemaining,
        'alerts' => $alerts,
        'feed_consumed_today' => $feedConsumedToday,
        'feed_consumed_week' => $feedConsumedWeek,
        'min_consumption' => $minConsumption,
        'max_consumption' => $maxConsumption,
        'avg_consumption' => $avgConsumption,
        'total_consumption' => $totalConsumption,
        'readings' => generateFeedReadings($filter, $dateFrom, $dateTo, $sortBy),
        'feed_schedule' => $feedSchedule,
        'daily_target' => '175-250g',
        'feeding_count' => 3
    ];
}

function generateFeedReadings($filter, $dateFrom, $dateTo, $sortBy) {
    // Generate readings that align with the 3 feedings per day (175-250g total)
    $baseDate = strtotime('2024-01-15');
    $readings = [];
    
    // Generate readings for 3 feedings per day for the last 3 days
    for ($day = 0; $day < 3; $day++) {
        $date = date('Y-m-d', strtotime("-$day days", $baseDate));
        $feedings = ['06:00:00', '12:00:00', '17:00:00'];
        $levels = [rand(85, 95), rand(75, 85), rand(65, 75)];
        
        foreach ($feedings as $idx => $time) {
            $status = ($levels[$idx] > 75) ? 'normal' : (($levels[$idx] > 60) ? 'warning' : 'danger');
            $consumed = round(rand(58, 83) / 1000, 3);
            $readings[] = [
                'time' => "$date $time",
                'level' => $levels[$idx],
                'consumed' => $consumed,
                'status' => $status,
                'dispenser' => ($idx % 2 == 0) ? 'Dispenser A' : 'Dispenser B'
            ];
        }
    }
    
    // Sort readings by time
    usort($readings, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    if ($sortBy === 'oldest') {
        $readings = array_reverse($readings);
    }
    
    return $readings;
}

$feedData = getFeedData($filter, $dateFrom, $dateTo, $sortBy);
$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Monitoring | BroilerGuard</title>
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
        
        /* ===== MAIN CONTENT ===== */
        .main-content { 
            margin-left: var(--sidebar-width); 
            flex: 1; 
            min-height: 100vh; 
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1); 
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
        .top-header .date-time { display: flex; flex-direction: column; gap: 0.1rem; }
        .top-header .date-time span { font-size: 0.8rem; color: var(--text-muted); }
        .top-header .date-time .time { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
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
            grid-template-columns: repeat(3, 1fr); 
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
            min-height: 280px;
            justify-content: center;
        }
        .reading-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        
        .reading-card .reading-icon { 
            font-size: 2.6rem; 
            margin-bottom: 0.8rem; 
        }
        
        /* Feed Level Card */
        .reading-card.feed-card .reading-icon { color: #E6B800; }
        
        .circular-progress { 
            position: relative; 
            width: 120px; 
            height: 120px; 
            margin: 0 auto 0.5rem; 
        }
        .circular-progress svg { transform: rotate(-90deg); }
        .circular-progress .progress-text { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            font-weight: 800; 
            font-size: 1.8rem; 
            color: var(--text-primary);
        }
        
        .reading-card .reading-label { 
            font-size: 0.9rem; 
            color: var(--text-muted); 
            font-weight: 500; 
            margin-bottom: 0.5rem;
        }
        .reading-card .reading-status { 
            display: inline-block; 
            padding: 0.35rem 1.6rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.3rem;
        }
        .status-normal { background: var(--green-light); color: var(--green); }
        .status-warning { background: var(--yellow-light); color: var(--yellow); }
        .status-danger { background: var(--red-light); color: var(--red); }
        
        /* Feeding Summary Card */
        .feeding-summary-card { border-top: 4px solid #27AE60 !important; }
        .feeding-summary-card .reading-icon { color: #27AE60 !important; }
        .consumption-main {
            font-size: 2.4rem;
            font-weight: 800;
            color: #27AE60;
            line-height: 1.2;
            margin-bottom: 0.2rem;
        }
        .consumption-main small {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .feeding-daily-target { 
            background: var(--accent-light); 
            padding: 0.6rem 1.2rem; 
            border-radius: 10px; 
            margin-top: 0.8rem; 
            font-size: 0.82rem; 
            color: var(--text-secondary); 
            width: 100%;
        }
        .feeding-daily-target strong { color: var(--text-primary); }
        .feeding-daily-target .feeding-count {
            display: block;
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }
        
        .consumption-box { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 0.6rem; 
            margin-top: 0.8rem; 
            width: 100%;
        }
        .consumption-item { 
            background: var(--bg-secondary); 
            padding: 0.7rem 0.5rem; 
            border-radius: 10px; 
        }
        .consumption-item .value { 
            font-size: 1.3rem; 
            font-weight: 700; 
            color: var(--accent-dark); 
        }
        .consumption-item .label { 
            font-size: 0.65rem; 
            color: var(--text-muted); 
        }
        
        /* Schedule Card */
        .schedule-summary-card { border-top: 4px solid #2980B9 !important; }
        .schedule-summary-card .reading-icon { color: #2980B9 !important; }
        .schedule-summary-card .reading-label { margin-bottom: 1rem; }
        
        .schedule-mini-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 0.7rem; 
            width: 100%;
        }
        .schedule-mini-item { 
            background: var(--bg-secondary); 
            padding: 0.8rem 0.5rem; 
            border-radius: 10px; 
            text-align: center;
        }
        .schedule-mini-item .sched-time { 
            font-weight: 700; 
            font-size: 0.82rem; 
            color: var(--text-primary); 
        }
        .schedule-mini-item .sched-amount {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin: 0.2rem 0;
        }
        .schedule-mini-item .sched-status { margin-top: 0.2rem; }
        .schedule-mini-item .sched-status .card-badge {
            font-size: 0.6rem;
            padding: 0.15rem 0.6rem;
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
            grid-template-columns: repeat(2, 1fr); 
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
        
        /* ===== ALERTS ===== */
        .alerts-section { margin-bottom: 1.5rem; }
        .alert-item { 
            display: flex; 
            align-items: flex-start; 
            gap: 0.8rem; 
            padding: 0.8rem 1rem; 
            background: var(--bg-card); 
            border-radius: 10px; 
            margin-bottom: 0.5rem; 
            border-left: 4px solid; 
            box-shadow: var(--shadow-sm); 
        }
        .alert-item.alert-critical { border-color: #E74C3C; }
        .alert-item.alert-warning { border-color: #F39C12; }
        .alert-item .alert-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 0.1rem; }
        .alert-item.alert-critical .alert-icon { color: #E74C3C; }
        .alert-item.alert-warning .alert-icon { color: #F39C12; }
        .alert-item .alert-content { flex: 1; }
        .alert-item .alert-message { font-size: 0.85rem; }
        .alert-item .alert-time { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        /* ===== SCHEDULE GRID ===== */
        .schedule-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 1rem; 
        }
        .schedule-item { 
            background: var(--bg-secondary); 
            border-radius: 12px; 
            padding: 1.2rem; 
            text-align: center; 
            border: 1px solid rgba(255, 214, 46, 0.12); 
            transition: all 0.2s; 
        }
        .schedule-item:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); }
        .schedule-item .schedule-time { font-weight: 700; font-size: 0.95rem; color: var(--text-primary); }
        .schedule-item .schedule-amount { font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.3rem; font-weight: 600; }
        .schedule-item .schedule-status { margin-top: 0.5rem; }
        
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
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { 
                transform: translateX(-100%);
                width: 320px;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            
            .current-readings { grid-template-columns: 1fr 1fr; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .schedule-grid { grid-template-columns: repeat(2, 1fr); }
            .schedule-mini-grid { grid-template-columns: repeat(3, 1fr); }
            .chart-wrapper { height: 220px; max-height: 220px; }
        }
        
        @media (max-width: 768px) {
            .current-readings { 
                grid-template-columns: 1fr; 
                max-width: 450px; 
                margin-left: auto; 
                margin-right: auto; 
            }
            .stats-mini-grid { grid-template-columns: 1fr 1fr; }
            .schedule-grid { grid-template-columns: 1fr; }
            .schedule-mini-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .reading-card { min-height: 220px; padding: 1.5rem; }
        }
        
        @media (max-width: 640px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-separator { display: none; }
            .stats-mini-grid { grid-template-columns: 1fr; }
            .schedule-mini-grid { grid-template-columns: 1fr; }
            .current-readings { max-width: 100%; }
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
                <a href="feed_monitoring.php" class="active"><i class="fas fa-utensils"></i> Feed Monitoring</a>
                <a href="water_monitoring.php"><i class="fas fa-water"></i> Water Monitoring</a>
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
                <div class="date-time">
                    <span id="currentDate"><?php echo $currentDate; ?></span>
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
            <h1 class="page-title"><i class="fas fa-utensils" style="color:#E6B800;"></i> Feed Monitoring</h1>
            <p class="page-subtitle">Real-time feed level monitoring and consumption tracking for broiler chickens</p>

            <?php if (!empty($feedData['alerts'])): ?>
            <div class="alerts-section">
                <?php foreach ($feedData['alerts'] as $alert): ?>
                <div class="alert-item alert-<?php echo $alert['type']; ?>">
                    <div class="alert-icon"><i class="fas <?php echo $alert['icon']; ?>"></i></div>
                    <div class="alert-content">
                        <div class="alert-message"><?php echo $alert['message']; ?></div>
                        <div class="alert-time"><?php echo $alert['time']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== IMPROVED CARDS - Each card properly spaced ===== -->
            <div class="current-readings">
                <!-- Card 1: Feed Level -->
                <div class="reading-card feed-card">
                    <div class="reading-icon"><i class="fas fa-utensils"></i></div>
                    <div class="circular-progress">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#E8E0D0" stroke-width="8"/>
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#E6B800" stroke-width="8"
                                stroke-dasharray="<?php echo $feedData['current_feed_level'] * 3.14; ?> 314"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="progress-text"><?php echo $feedData['current_feed_level']; ?>%</div>
                    </div>
                    <div class="reading-label">Current Feed Level</div>
                    <span class="reading-status status-<?php echo $feedData['feed_status']; ?>"><?php echo ucfirst($feedData['feed_status']); ?></span>
                </div>

                <!-- Card 2: Feed Consumed Today -->
                <div class="reading-card feeding-summary-card">
                    <div class="reading-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="consumption-main">
                        <?php echo number_format($feedData['feed_consumed_today'], 3); ?>
                        <small>kg</small>
                    </div>
                    <div class="reading-label">Feed Consumed Today</div>
                    <div class="feeding-daily-target">
                        <i class="fas fa-bullseye" style="color:var(--accent-dark);"></i>
                        Target: <strong><?php echo $feedData['daily_target']; ?></strong> per day
                        <span class="feeding-count">
                            <i class="fas fa-clock"></i> <?php echo $feedData['feeding_count']; ?> feedings daily (6AM, 12PM, 5PM)
                        </span>
                    </div>
                    <div class="consumption-box">
                        <div class="consumption-item">
                            <div class="value"><?php echo number_format($feedData['feed_consumed_week'], 3); ?> kg</div>
                            <div class="label">This Week</div>
                        </div>
                        <div class="consumption-item">
                            <div class="value"><?php echo $feedData['avg_consumption']; ?> kg</div>
                            <div class="label">Avg Daily</div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Today's Feeding Schedule -->
                <div class="reading-card schedule-summary-card">
                    <div class="reading-icon"><i class="fas fa-clock"></i></div>
                    <div class="reading-label">Today's Feeding Schedule</div>
                    <div class="schedule-mini-grid">
                        <?php foreach ($feedData['feed_schedule'] as $schedule): ?>
                        <div class="schedule-mini-item">
                            <div class="sched-time"><?php echo $schedule['time']; ?></div>
                            <div class="sched-amount"><?php echo $schedule['amount']; ?></div>
                            <div class="sched-status">
                                <span class="card-badge <?php echo $schedule['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($schedule['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Mini Grid -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#E6B800;"><?php echo number_format($feedData['total_consumption'], 3); ?> kg</div><div class="stat-mini-label">Total Consumption</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#27AE60;"><?php echo $feedData['avg_consumption']; ?> kg</div><div class="stat-mini-label">Average Daily</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#E67E22;"><?php echo $feedData['min_consumption']; ?> kg</div><div class="stat-mini-label">Min Daily</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:#2980B9;"><?php echo $feedData['max_consumption']; ?> kg</div><div class="stat-mini-label">Max Daily</div></div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <span style="font-weight:600;font-size:0.85rem;margin-right:0.3rem;"><i class="fas fa-filter"></i> View:</span>
                <button class="filter-btn <?php echo $filter === '24h' ? 'active' : ''; ?>" onclick="changeFilter('24h')">Today (3 Feedings)</button>
                <button class="filter-btn <?php echo $filter === '7d' ? 'active' : ''; ?>" onclick="changeFilter('7d')">Week</button>
                <span class="filter-separator"></span>
                <span style="font-size:0.75rem;color:var(--text-muted);">Custom Range:</span>
                <input type="date" class="date-input" id="dateFromInput" value="<?php echo $dateFrom; ?>" style="max-width:140px;">
                <span style="font-size:0.8rem;color:var(--text-muted);">to</span>
                <input type="date" class="date-input" id="dateToInput" value="<?php echo $dateTo; ?>" style="max-width:140px;">
                <button class="filter-btn" onclick="applyCustomRange()" style="background:var(--accent);border-color:var(--accent);"><i class="fas fa-check"></i> Apply</button>
                <span class="filter-separator"></span>
                <select class="filter-btn" onchange="changeSort(this.value)" style="cursor:pointer;background:var(--bg-secondary);">
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
                        <span class="chart-card-title"><i class="fas fa-chart-bar" style="color:#E6B800;"></i> Feed Consumption</span>
                        <span class="card-badge badge-info">kg</span>
                    </div>
                    <div class="chart-wrapper"><canvas id="feedConsumptionChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title"><i class="fas fa-chart-area" style="color:#27AE60;"></i> Feed Level Remaining</span>
                        <span class="card-badge badge-info">%</span>
                    </div>
                    <div class="chart-wrapper"><canvas id="feedLevelChart"></canvas></div>
                </div>
            </div>

            <!-- Feeding Schedule - 3 Feedings -->
            <div class="chart-card" style="margin-bottom:1.5rem;">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-calendar-check"></i> Daily Feeding Schedule</span>
                    <span class="card-badge badge-info">175-250g daily target</span>
                </div>
                <div class="schedule-grid">
                    <?php foreach ($feedData['feed_schedule'] as $schedule): ?>
                    <div class="schedule-item">
                        <div class="schedule-time"><i class="fas fa-clock"></i> <?php echo $schedule['time']; ?></div>
                        <div class="schedule-amount"><?php echo $schedule['amount']; ?></div>
                        <div class="schedule-status">
                            <span class="card-badge <?php echo $schedule['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo ucfirst($schedule['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Feed Level Readings Table -->
            <div class="chart-card" style="padding:0;overflow:hidden;">
                <div class="chart-card-header" style="padding:1.5rem 1.5rem 0 1.5rem;">
                    <span class="chart-card-title"><i class="fas fa-list"></i> Feed Level Readings</span>
                    <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo count($feedData['readings']); ?> readings</span>
                </div>
                <div class="table-container" style="padding:0 1.5rem 1.5rem 1.5rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Feed Level</th>
                                <th>Consumed</th>
                                <th>Dispenser</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedData['readings'] as $reading): ?>
                            <tr>
                                <td><?php echo $reading['time']; ?></td>
                                <td><strong><?php echo $reading['level']; ?>%</strong></td>
                                <td><?php echo number_format($reading['consumed'], 3); ?> kg</td>
                                <td><?php echo $reading['dispenser']; ?></td>
                                <td><span class="card-badge badge-<?php echo $reading['status'] === 'normal' ? 'success' : ($reading['status'] === 'warning' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($reading['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chartLabels = <?php echo json_encode($feedData['labels']); ?>;
        const feedData = <?php echo json_encode($feedData['feed_data']); ?>;
        const feedRemaining = <?php echo json_encode($feedData['feed_remaining']); ?>;
        let feedConsumptionChart, feedLevelChart;

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
            window.location.href = 'feed_monitoring.php?' + params.toString();
        }
        
        function changeSort(sort) { 
            const params = new URLSearchParams(window.location.search);
            params.set('sort_by', sort);
            window.location.href = 'feed_monitoring.php?' + params.toString();
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
            window.location.href = 'feed_monitoring.php?' + params.toString();
        }
        
        function resetFilters() {
            window.location.href = 'feed_monitoring.php';
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
            
            if (feedConsumptionChart) feedConsumptionChart.destroy();
            if (feedLevelChart) feedLevelChart.destroy();

            const consumptionCtx = document.getElementById('feedConsumptionChart');
            if (consumptionCtx) {
                feedConsumptionChart = new Chart(consumptionCtx, {
                    type: 'bar',
                    data: { 
                        labels: chartLabels, 
                        datasets: [{ 
                            label: 'Feed Consumed (kg)', 
                            data: feedData, 
                            backgroundColor: ['#E6B800', '#F1C40F', '#D4A800', '#E6B800', '#F1C40F', '#D4A800', '#E6B800'],
                            borderRadius: 6, 
                            maxBarThickness: 50 
                        }] 
                    },
                    options: {
                        ...chartOptions,
                        plugins: {
                            ...chartOptions.plugins,
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' kg';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const levelCtx = document.getElementById('feedLevelChart');
            if (levelCtx) {
                feedLevelChart = new Chart(levelCtx, {
                    type: 'line',
                    data: { 
                        labels: chartLabels, 
                        datasets: [{ 
                            label: 'Feed Level (%)', 
                            data: feedRemaining, 
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
                        },
                        plugins: {
                            ...chartOptions.plugins,
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() { 
            initCharts(); 
        });
        
        window.addEventListener('resize', function() {
            if (feedConsumptionChart) feedConsumptionChart.resize();
            if (feedLevelChart) feedLevelChart.resize();
        });
    </script>
</body>
</html>