<?php
// dashboard.php - Main Dashboard File (with Database Connection)
session_start();

require_once 'db_connect.php';        // PDO connection
require_once 'weather_functions.php'; // Weather API

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

// Get current tab and search query
$tab = $_GET['tab'] ?? 'dashboard';
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';
$chartPeriod = $_GET['chart_period'] ?? 'week';

// Get weather data
$weather = getWeatherData();

$userId = 1; // In production, get from session

// ============================================================
// FUNCTION: Get latest sensor reading from database
// ============================================================
function getLatestSensorData() {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("SELECT temperature, humidity, timestamp FROM sensor_readings WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        return [
            'temperature' => (float)$row['temperature'],
            'humidity' => (float)$row['humidity']
        ];
    }
    
    // Fallback if no data
    return [
        'temperature' => 29.5,
        'humidity' => 65.0
    ];
}

// ============================================================
// FUNCTION: Get feed inventory from database
// ============================================================
function getFeedInventory() {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("SELECT current_level, capacity, alert_threshold, critical_threshold FROM feed_inventory WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        return [
            'level' => (float)$row['current_level'],
            'capacity' => (float)$row['capacity'],
            'alert_threshold' => (float)$row['alert_threshold'],
            'critical_threshold' => (float)$row['critical_threshold'],
            'percentage' => $row['capacity'] > 0 ? round(($row['current_level'] / $row['capacity']) * 100) : 0
        ];
    }
    
    return ['level' => 0, 'capacity' => 200, 'percentage' => 0];
}

// ============================================================
// FUNCTION: Get water inventory from database
// ============================================================
function getWaterInventory() {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("SELECT current_level, capacity, alert_threshold, critical_threshold FROM water_inventory WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        return [
            'level' => (float)$row['current_level'],
            'capacity' => (float)$row['capacity'],
            'alert_threshold' => (float)$row['alert_threshold'],
            'critical_threshold' => (float)$row['critical_threshold'],
            'percentage' => $row['capacity'] > 0 ? round(($row['current_level'] / $row['capacity']) * 100) : 0
        ];
    }
    
    return ['level' => 0, 'capacity' => 2000, 'percentage' => 0];
}

// ============================================================
// FUNCTION: Get automation status from database
// ============================================================
function getAutomationStatus() {
    global $pdo, $userId;
    
    // Fan status - use backticks for reserved keywords
    $fanStmt = $pdo->prepare("SELECT action, `trigger`, temperature FROM fan_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
    $fanStmt->execute([$userId]);
    $fanRow = $fanStmt->fetch(PDO::FETCH_ASSOC);
    
    // Water pump status
    $pumpStmt = $pdo->prepare("SELECT source, amount FROM water_transactions WHERE user_id = ? AND type = 'consumption' ORDER BY timestamp DESC LIMIT 1");
    $pumpStmt->execute([$userId]);
    $pumpRow = $pumpStmt->fetch(PDO::FETCH_ASSOC);
    
    // Feed dispenser status (from feed_transactions)
    $feedStmt = $pdo->prepare("SELECT source, amount FROM feed_transactions WHERE user_id = ? AND type = 'consumption' ORDER BY timestamp DESC LIMIT 1");
    $feedStmt->execute([$userId]);
    $feedRow = $feedStmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'fan_status' => $fanRow ? $fanRow['action'] : 'OFF',
        'fan_mode' => $fanRow ? ($fanRow['trigger'] === 'auto' ? 'Auto' : 'Manual') : 'Auto',
        'water_pump' => $pumpRow ? 'ON' : 'OFF',
        'water_pump_mode' => $pumpRow ? 'Auto' : 'Auto',
        'feed_dispenser' => $feedRow ? 'ON' : 'OFF',
        'feed_schedule' => '06:00 AM'
    ];
}

// ============================================================
// FUNCTION: Get chart data from database
// ============================================================
function getChartData($period) {
    global $pdo, $userId;
    
    $labels = [];
    $tempData = [];
    $humidityData = [];
    $feedData = [];
    $waterData = [];
    
    if ($period === 'day') {
        // Get last 24 hours data grouped by hour
        $stmt = $pdo->prepare("SELECT 
                                DATE_FORMAT(timestamp, '%H:00') as hour,
                                AVG(temperature) as avg_temp,
                                AVG(humidity) as avg_humidity
                              FROM sensor_readings 
                              WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                              GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H:00')
                              ORDER BY timestamp ASC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $labels[] = $row['hour'];
            $tempData[] = round((float)$row['avg_temp'], 1);
            $humidityData[] = round((float)$row['avg_humidity']);
        }
        
        // Get feed and water consumption for last 24 hours
        $feedStmt = $pdo->prepare("SELECT DATE_FORMAT(timestamp, '%H:00') as hour, SUM(amount) as total 
                                   FROM feed_transactions 
                                   WHERE user_id = ? AND type = 'consumption' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                   GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H:00')");
        $feedStmt->execute([$userId]);
        $feedRows = $feedStmt->fetchAll(PDO::FETCH_ASSOC);
        $feedMap = [];
        foreach ($feedRows as $r) {
            $feedMap[$r['hour']] = (float)$r['total'];
        }
        
        $waterStmt = $pdo->prepare("SELECT DATE_FORMAT(timestamp, '%H:00') as hour, SUM(amount) as total 
                                    FROM water_transactions 
                                    WHERE user_id = ? AND type = 'consumption' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                    GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H:00')");
        $waterStmt->execute([$userId]);
        $waterRows = $waterStmt->fetchAll(PDO::FETCH_ASSOC);
        $waterMap = [];
        foreach ($waterRows as $r) {
            $waterMap[$r['hour']] = (float)$r['total'];
        }
        
        foreach ($labels as $label) {
            $feedData[] = $feedMap[$label] ?? 0;
            $waterData[] = $waterMap[$label] ?? 0;
        }
        
    } else {
        // Week - get last 7 days
        $stmt = $pdo->prepare("SELECT 
                                DATE(timestamp) as day,
                                AVG(temperature) as avg_temp,
                                AVG(humidity) as avg_humidity
                              FROM sensor_readings 
                              WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                              GROUP BY DATE(timestamp)
                              ORDER BY timestamp ASC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $labels[] = date('M d', strtotime($row['day']));
            $tempData[] = round((float)$row['avg_temp'], 1);
            $humidityData[] = round((float)$row['avg_humidity']);
        }
        
        // Get feed and water consumption for last 7 days
        $feedStmt = $pdo->prepare("SELECT DATE(timestamp) as day, SUM(amount) as total 
                                   FROM feed_transactions 
                                   WHERE user_id = ? AND type = 'consumption' AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                   GROUP BY DATE(timestamp)");
        $feedStmt->execute([$userId]);
        $feedRows = $feedStmt->fetchAll(PDO::FETCH_ASSOC);
        $feedMap = [];
        foreach ($feedRows as $r) {
            $feedMap[$r['day']] = (float)$r['total'];
        }
        
        $waterStmt = $pdo->prepare("SELECT DATE(timestamp) as day, SUM(amount) as total 
                                    FROM water_transactions 
                                    WHERE user_id = ? AND type = 'consumption' AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    GROUP BY DATE(timestamp)");
        $waterStmt->execute([$userId]);
        $waterRows = $waterStmt->fetchAll(PDO::FETCH_ASSOC);
        $waterMap = [];
        foreach ($waterRows as $r) {
            $waterMap[$r['day']] = (float)$r['total'];
        }
        
        foreach ($labels as $label) {
            $feedData[] = $feedMap[date('Y-m-d', strtotime($label))] ?? 0;
            $waterData[] = $waterMap[date('Y-m-d', strtotime($label))] ?? 0;
        }
    }
    
    // If no data, return default
    if (empty($labels)) {
        return [
            'labels' => ['No Data'],
            'temp' => [0],
            'humidity' => [0],
            'feed' => [0],
            'water' => [0]
        ];
    }
    
    return [
        'labels' => $labels,
        'temp' => $tempData,
        'humidity' => $humidityData,
        'feed' => $feedData,
        'water' => $waterData
    ];
}

// ============================================================
// FUNCTION: Get recent activity records from database
// ============================================================
function getActivityRecords($filter = 'all', $dateFrom = '', $dateTo = '', $sortBy = 'newest', $search = '') {
    global $pdo, $userId;
    
    $sql = "SELECT 'sensor' as source, timestamp, CONCAT('Temperature: ', temperature, '°C, Humidity: ', humidity, '%') as description, 
                   CASE 
                       WHEN temperature > 35 OR humidity > 80 THEN 'warning'
                       WHEN temperature < 28 OR humidity < 50 THEN 'warning'
                       ELSE 'normal'
                   END as status,
                   'environment' as category,
                   CONCAT(temperature, '°C / ', humidity, '%') as value
            FROM sensor_readings 
            WHERE user_id = ?
            
            UNION ALL
            
            SELECT 'feed' as source, timestamp, CONCAT('Dispensed ', amount, ' kg of feed') as description,
                   'normal' as status,
                   'inventory' as category,
                   CONCAT(amount, ' kg') as value
            FROM feed_transactions 
            WHERE user_id = ? AND type = 'consumption'
            
            UNION ALL
            
            SELECT 'water' as source, timestamp, CONCAT('Released ', amount, ' L of water') as description,
                   'normal' as status,
                   'inventory' as category,
                   CONCAT(amount, ' L') as value
            FROM water_transactions 
            WHERE user_id = ? AND type = 'consumption'
            
            UNION ALL
            
            SELECT 'detection' as source, timestamp, CONCAT('Detected chick ', chick_id, ' as ', status) as description,
                   status as status,
                   'chickens' as category,
                   CONCAT(confidence, '% confidence') as value
            FROM detection_logs 
            WHERE user_id = ?
            
            ORDER BY timestamp DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $records = $allRecords;
    
    // Apply search filter
    if ($search) {
        $searchLower = strtolower($search);
        $records = array_filter($records, function($record) use ($searchLower) {
            return strpos(strtolower($record['category']), $searchLower) !== false ||
                   strpos(strtolower($record['description']), $searchLower) !== false ||
                   strpos(strtolower($record['status']), $searchLower) !== false;
        });
    }
    
    // Apply category filter
    if ($filter !== 'all') {
        $records = array_filter($records, function($record) use ($filter) {
            return $record['category'] === $filter;
        });
    }
    
    // Apply date filter
    if ($dateFrom || $dateTo) {
        $records = array_filter($records, function($record) use ($dateFrom, $dateTo) {
            $recordDate = strtotime($record['timestamp']);
            if ($dateFrom && $recordDate < strtotime($dateFrom)) return false;
            if ($dateTo && $recordDate > strtotime($dateTo . ' 23:59:59')) return false;
            return true;
        });
    }
    
    // Apply sorting
    if ($sortBy === 'oldest') {
        $records = array_reverse($records);
    }
    
    return array_values(array_slice($records, 0, 20));
}

// ============================================================
// FUNCTION: Get chicken status from database
// ============================================================
function getChickenStatus() {
    global $pdo, $userId;
    
    // Get latest detection per chick
    $stmt = $pdo->prepare("SELECT chick_id, status, confidence, timestamp 
                           FROM detection_logs 
                           WHERE user_id = ? 
                           ORDER BY timestamp DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $latestStatus = [];
    foreach ($rows as $row) {
        if (!isset($latestStatus[$row['chick_id']])) {
            $latestStatus[$row['chick_id']] = $row;
        }
    }
    
    $healthy = 0;
    $weak = 0;
    $unhealthy = 0;
    
    foreach ($latestStatus as $status) {
        if ($status['status'] === 'healthy') $healthy++;
        elseif ($status['status'] === 'weak') $weak++;
        else $unhealthy++;
    }
    
    return [
        'healthy' => $healthy,
        'weak' => $weak,
        'unhealthy' => $unhealthy,
        'total' => $healthy + $weak + $unhealthy
    ];
}

// ============================================================
// FUNCTION: Get notification count from database
// ============================================================
function getUnreadNotificationCount() {
    global $pdo, $userId;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int)$row['count'];
}

// ============================================================
// GET ALL DATA
// ============================================================
$sensorData = getLatestSensorData();
$feedInventory = getFeedInventory();
$waterInventory = getWaterInventory();
$automation = getAutomationStatus();
$chartData = getChartData($chartPeriod);
$records = getActivityRecords($filter, $dateFrom, $dateTo, $sortBy, $search);
$chickenStatus = getChickenStatus();
$unreadNotifications = getUnreadNotificationCount();

// Compute stats
$feedPercentage = $feedInventory['percentage'];
$waterPercentage = $waterInventory['percentage'];
$totalChicks = $chickenStatus['total'];
$healthyChicks = $chickenStatus['healthy'];
$weakChicks = $chickenStatus['weak'];
$unhealthyChicks = $chickenStatus['unhealthy'];

// Get today's consumption
$today = date('Y-m-d');
$feedToday = 0;
$waterToday = 0;

$feedStmt = $pdo->prepare("SELECT SUM(amount) as total FROM feed_transactions WHERE user_id = ? AND type = 'consumption' AND DATE(timestamp) = ?");
$feedStmt->execute([$userId, $today]);
$feedRow = $feedStmt->fetch(PDO::FETCH_ASSOC);
$feedToday = $feedRow ? (float)$feedRow['total'] : 0;

$waterStmt = $pdo->prepare("SELECT SUM(amount) as total FROM water_transactions WHERE user_id = ? AND type = 'consumption' AND DATE(timestamp) = ?");
$waterStmt->execute([$userId, $today]);
$waterRow = $waterStmt->fetch(PDO::FETCH_ASSOC);
$waterToday = $waterRow ? (float)$waterRow['total'] : 0;

// Active alerts count
$alertCount = 0;
if ($feedPercentage < $feedInventory['alert_threshold']) $alertCount++;
if ($waterPercentage < $waterInventory['alert_threshold']) $alertCount++;
if ($unhealthyChicks > 0) $alertCount++;
$alertCount += $unreadNotifications;

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BroilerGuard | Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-primary: #F5F5F5;
            --bg-secondary: #E8F0E8;
            --bg-card: #FFFFFF;
            --text-primary: #2C3E2C;
            --text-secondary: #4D724D;
            --text-muted: #6B8A6B;
            --accent: #8DB48E;
            --accent-dark: #4D724D;
            --accent-light: #D4E8D4;
            --sidebar-bg: #3A5C3A;
            --sidebar-text: #F5F5F5;
            --sidebar-muted: #A8C8A8;
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
            --sidebar-width: 280px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; }
        
        /* ============================================ */
        /* SIDEBAR / NAVBAR - UPDATED COLORS */
        /* ============================================ */
        .sidebar { 
            width: var(--sidebar-width); 
            height: 100vh; 
            position: fixed; 
            left: 0; 
            top: 0; 
            background: linear-gradient(180deg, #4D724D 0%, #3A5C3A 100%); 
            color: var(--sidebar-text); 
            z-index: 1000; 
            transition: transform 0.3s ease; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden;
        }
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav {
            flex: 1; 
            overflow-y: auto; 
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 0.8rem 0;
        }
        .sidebar-logo { 
            padding: 1.5rem; 
            border-bottom: 1px solid rgba(255,255,255,0.08); 
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        .sidebar-logo h2 { 
            font-size: 1.5rem; 
            font-weight: 700; 
            background: linear-gradient(135deg, #8DB48E, #FFFFFF); 
            -webkit-background-clip: text; 
            background-clip: text; 
            color: transparent; 
        }
        .sidebar-logo .logo-icon { 
            font-size: 2rem; 
            color: #8DB48E;
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
            background: #8DB48E; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700; 
            color: #FFFFFF; 
            font-size: 1.1rem; 
            flex-shrink: 0; 
        }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.9rem; color: #F5F5F5; }
        .sidebar-user .user-info .role { font-size: 0.7rem; color: var(--sidebar-muted); }
        .sidebar-nav .nav-section { padding: 0.3rem 1rem; margin-top: 0.3rem; }
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
            border-left: 3px solid #8DB48E;
        }
        .sidebar-nav a i { width: 22px; text-align: center; font-size: 1rem; }
        .sidebar-nav a .badge-sidebar { 
            margin-left: auto; 
            background: var(--red); 
            color: white; 
            font-size: 0.65rem; 
            padding: 0.15rem 0.5rem; 
            border-radius: 20px; 
            font-weight: 600; 
        }
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
        
        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; transition: margin-left 0.3s ease; overflow-x: hidden; }
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
        .top-header .header-left { display: flex; align-items: center; gap: 2rem; }
        .menu-toggle { display: none; font-size: 1.5rem; cursor: pointer; color: var(--text-primary); background: none; border: none; }
        .date-time-container { display: flex; flex-direction: column; }
        .date-time-container .date { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px; }
        .date-time-container .time { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        
        /* Weather Widget - Updated with new green */
        .weather-widget { 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
            background: linear-gradient(135deg, #4D724D, #8DB48E); 
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
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 2000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .weather-modal { background: white; border-radius: 20px; padding: 2rem; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.25); position: relative; }
        .weather-modal .close-btn { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .weather-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem; }
        .weather-detail-item { background: var(--bg-secondary); padding: 1rem; border-radius: 10px; text-align: center; }
        .weather-detail-item i { font-size: 1.5rem; color: var(--accent-dark); margin-bottom: 0.5rem; }
        .weather-detail-item .label { font-size: 0.75rem; color: var(--text-muted); }
        .weather-detail-item .value { font-size: 1.1rem; font-weight: 700; }
        
        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; color: var(--text-primary); }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        .section-label { 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            color: var(--text-muted); 
            margin-bottom: 1rem; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            flex-wrap: wrap; 
            gap: 1rem; 
        }
        
        /* Search & Filter Bar */
        .search-filter-bar { background: var(--bg-card); border-radius: 10px; padding: 0.6rem 0.9rem; border: 1px solid rgba(77, 114, 77, 0.12); display: flex; flex-wrap: wrap; align-items: center; gap: 0.8rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); }
        .search-input-group { flex: 2; min-width: 200px; display: flex; align-items: center; gap: 0.5rem; background: var(--bg-secondary); padding: 0.5rem 0.8rem; border-radius: 8px; }
        .search-input-group i { color: var(--text-muted); font-size: 0.9rem; }
        .search-input-group input { border: none; background: none; outline: none; flex: 1; font-family: 'Inter', sans-serif; font-size: 0.85rem; color: var(--text-primary); }
        .search-input-group input::placeholder { color: var(--text-muted); }
        .filter-group { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .filter-select, .date-input { padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid rgba(77, 114, 77, 0.12); background: var(--bg-secondary); font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--text-primary); cursor: pointer; }
        .apply-btn { 
            background: linear-gradient(105deg, #4D724D, #3A5C3A); 
            border: none; 
            padding: 0.5rem 0.9rem; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 0.8rem; 
            cursor: pointer; 
            color: #FFFFFF; 
            transition: all 0.2s; 
            font-family: 'Inter', sans-serif; 
        }
        .apply-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .reset-filters-btn { background: var(--red-light); border: none; padding: 0.5rem 0.9rem; border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer; color: var(--red); transition: all 0.2s; font-family: 'Inter', sans-serif; }
        .reset-filters-btn:hover { background: var(--red); color: white; }
        
        /* Cards */
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .card { 
            background: var(--bg-card); 
            border-radius: var(--border-radius); 
            padding: 1.5rem; 
            box-shadow: var(--shadow-sm); 
            border: 1px solid rgba(77, 114, 77, 0.06); 
            transition: all 0.3s ease; 
            cursor: pointer; 
            text-decoration: none; 
            display: block; 
            color: inherit; 
        }
        .card:hover { box-shadow: var(--shadow-md); transform: translateY(-4px); border-color: var(--accent); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .card-title { font-weight: 700; font-size: 1rem; }
        .card-badge { padding: 0.25rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }
        
        /* Resource Levels */
        .resource-card { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .progress-container { flex: 1; }
        .progress-bar-bg { background: #E8E8E8; border-radius: 20px; height: 12px; overflow: hidden; margin: 0.5rem 0; }
        .progress-fill { height: 100%; border-radius: 20px; transition: width 0.5s ease; }
        .progress-fill.feed { background: linear-gradient(90deg, #4D724D, #8DB48E); width: <?php echo $feedPercentage; ?>%; }
        .progress-fill.water { background: linear-gradient(90deg, #4F6C7A, #8DB48E); width: <?php echo $waterPercentage; ?>%; }
        .resource-stats { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); }
        .resource-value { font-size: 1.8rem; font-weight: 800; line-height: 1; }
        
        /* Automation */
        .automation-card { display: flex; align-items: center; gap: 1rem; padding: 0.8rem; background: var(--bg-secondary); border-radius: 12px; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; }
        .status-on { background: var(--green); box-shadow: 0 0 8px rgba(77, 114, 77, 0.5); animation: pulse 1.5s infinite; }
        .status-off { background: #95A5A6; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        
        /* Charts */
        .chart-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; justify-content: space-between; align-items: center; }
        .period-buttons { display: flex; gap: 0.5rem; }
        .period-btn, .chart-type-btn { 
            padding: 0.4rem 0.8rem; 
            border-radius: 8px; 
            border: 1px solid rgba(77, 114, 77, 0.12); 
            background: var(--bg-card); 
            cursor: pointer; 
            font-size: 0.75rem; 
            font-weight: 500; 
            color: var(--text-secondary); 
            transition: all 0.2s; 
            font-family: 'Inter', sans-serif; 
        }
        .period-btn.active, .chart-type-btn.active { 
            background: rgba(141, 180, 142, 0.15); 
            color: var(--accent-dark); 
            border-color: var(--accent); 
            font-weight: 600; 
        }
        .chart-wrapper { position: relative; width: 100%; height: 280px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }
        
        /* Table */
        .table-container { overflow-x: auto; border-radius: var(--border-radius); }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: var(--bg-secondary); padding: 1rem; text-align: left; font-weight: 600; border-bottom: 2px solid rgba(77, 114, 77, 0.08); color: var(--text-secondary); }
        td { padding: 1rem; border-bottom: 1px solid rgba(77, 114, 77, 0.05); }
        tr:hover td { background: var(--bg-secondary); }
        .category-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; }
        
        .active-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center; }
        .active-filter-tag { background: var(--accent-light); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; border: none; font-family: 'Inter', sans-serif; color: var(--text-secondary); }
        .clear-all-tag { background: var(--red-light) !important; color: var(--red) !important; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        body::-webkit-scrollbar { width: 6px; }
        body::-webkit-scrollbar-track { background: var(--bg-secondary); }
        body::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 3px; }
        
        button, .btn, .btn-save, .btn-login, .filter-btn, .period-btn, .chart-type-btn, .back-btn, .action-btn, .apply-btn, .reset-filters-btn, input, select, textarea {
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .card, .settings-card, .reading-card, .chart-card, .stat-card, .inventory-card, .table-card {
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
        }
        .card:hover, .settings-card:hover, .reading-card:hover, .chart-card:hover, .stat-card:hover, .inventory-card:hover, .table-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .grid-2 { grid-template-columns: 1fr; }
            .search-filter-bar { flex-direction: column; border-radius: 20px; }
            .search-input-group { width: 100%; }
            .filter-group { width: 100%; justify-content: center; }
            .chart-wrapper { height: 220px; }
        }
        @media (max-width: 640px) {
            .grid-3 { grid-template-columns: 1fr; }
            .chart-filters { flex-direction: column; align-items: stretch; }
            .period-buttons { justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- SIDEBAR / NAVBAR -->
<!-- ============================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-feather-alt"></i></div>
        <h2>BroilerGuard</h2>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section"><div class="nav-section-title">Main</div><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></div>
        <div class="nav-section"><div class="nav-section-title">Monitoring</div>
            <a href="temperature.php"><i class="fas fa-thermometer-half"></i> Temperature & Humidity</a>
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
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <span class="badge-sidebar"><?php echo $alertCount; ?></span></a>
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
        <h1 class="page-title">Farm Overview Dashboard</h1>
        <p class="page-subtitle">Real-time monitoring of your broiler chicks farm operations</p>

        <!-- Active Filters Display -->
        <?php if ($filter !== 'all' || $search || $dateFrom || $dateTo): ?>
        <div class="active-filters">
            <span style="font-size:0.7rem;color:var(--text-muted);font-weight:600;">Active Filters:</span>
            <?php if ($search): ?><button class="active-filter-tag" onclick="clearFilter('search')">Search: "<?php echo htmlspecialchars($search); ?>" <i class="fas fa-times"></i></button><?php endif; ?>
            <?php if ($filter !== 'all'): ?><button class="active-filter-tag" onclick="clearFilter('filter')">Category: <?php echo ucfirst($filter); ?> <i class="fas fa-times"></i></button><?php endif; ?>
            <?php if ($dateFrom): ?><button class="active-filter-tag" onclick="clearFilter('date_from')">From: <?php echo $dateFrom; ?> <i class="fas fa-times"></i></button><?php endif; ?>
            <?php if ($dateTo): ?><button class="active-filter-tag" onclick="clearFilter('date_to')">To: <?php echo $dateTo; ?> <i class="fas fa-times"></i></button><?php endif; ?>
            <button class="active-filter-tag clear-all-tag" onclick="clearAllFilters()">Clear All <i class="fas fa-times"></i></button>
        </div>
        <?php endif; ?>

        <!-- Environmental Conditions -->
        <div class="section-label">
            <span><i class="fas fa-thermometer-half"></i> Environmental Conditions</span>
        </div>
        <div class="grid-2">
            <div class="card" onclick="window.location.href='temperature.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-temperature-high"></i> Temperature</span><span class="card-badge <?php echo $sensorData['temperature'] > 35 ? 'badge-danger' : ($sensorData['temperature'] < 28 ? 'badge-warning' : 'badge-success'); ?>"><?php echo $sensorData['temperature'] > 35 ? 'High' : ($sensorData['temperature'] < 28 ? 'Low' : 'Normal'); ?></span></div>
                <div style="display:flex;align-items:center;gap:1.5rem;">
                    <div style="font-size:2.5rem;font-weight:800;color:<?php echo $sensorData['temperature'] > 35 ? 'var(--red)' : ($sensorData['temperature'] < 28 ? 'var(--yellow)' : 'var(--orange)'); ?>;"><?php echo $sensorData['temperature']; ?>°C</div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Ideal Range<br>30°C - 35°C</div>
                </div>
            </div>
            <div class="card" onclick="window.location.href='temperature.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-tint"></i> Humidity</span><span class="card-badge <?php echo $sensorData['humidity'] > 80 ? 'badge-danger' : ($sensorData['humidity'] < 55 ? 'badge-warning' : 'badge-success'); ?>"><?php echo $sensorData['humidity'] > 80 ? 'High' : ($sensorData['humidity'] < 55 ? 'Low' : 'Optimal'); ?></span></div>
                <div style="display:flex;align-items:center;gap:1.5rem;">
                    <div style="font-size:2.5rem;font-weight:800;color:<?php echo $sensorData['humidity'] > 80 ? 'var(--red)' : ($sensorData['humidity'] < 55 ? 'var(--yellow)' : 'var(--blue)'); ?>;"><?php echo $sensorData['humidity']; ?>%</div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Ideal Range<br>55% - 80%</div>
                </div>
            </div>
        </div>

        <!-- Resource Levels -->
        <div class="section-label">
            <span><i class="fas fa-utensils"></i> Resource Levels</span>
        </div>
        <div class="grid-2">
            <div class="card" onclick="window.location.href='feed_inventory.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-utensils"></i> Feed Level</span><span class="card-badge <?php echo $feedPercentage < $feedInventory['critical_threshold'] ? 'badge-danger' : ($feedPercentage < $feedInventory['alert_threshold'] ? 'badge-warning' : 'badge-success'); ?>"><?php echo $feedPercentage < $feedInventory['critical_threshold'] ? 'Critical' : ($feedPercentage < $feedInventory['alert_threshold'] ? 'Low' : 'Sufficient'); ?></span></div>
                <div class="resource-card">
                    <div class="progress-container">
                        <div class="resource-value"><?php echo $feedPercentage; ?>%</div>
                        <div class="progress-bar-bg"><div class="progress-fill feed"></div></div>
                        <div class="resource-stats"><span>Remaining</span><span><?php echo number_format($feedInventory['level'], 1); ?> kg today</span></div>
                    </div>
                </div>
            </div>
            <div class="card" onclick="window.location.href='water_inventory.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-water"></i> Water Level</span><span class="card-badge <?php echo $waterPercentage < $waterInventory['critical_threshold'] ? 'badge-danger' : ($waterPercentage < $waterInventory['alert_threshold'] ? 'badge-warning' : 'badge-success'); ?>"><?php echo $waterPercentage < $waterInventory['critical_threshold'] ? 'Critical' : ($waterPercentage < $waterInventory['alert_threshold'] ? 'Low' : 'Adequate'); ?></span></div>
                <div class="resource-card">
                    <div class="progress-container">
                        <div class="resource-value"><?php echo $waterPercentage; ?>%</div>
                        <div class="progress-bar-bg"><div class="progress-fill water"></div></div>
                        <div class="resource-stats"><span>Remaining</span><span><?php echo number_format($waterInventory['level'], 0); ?> L today</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chicken Status -->
        <div class="section-label">
            <span><i class="fas fa-chicken"></i> Chicken Health Status</span>
            <span style="font-size:0.7rem;color:var(--text-muted);">Total: <?php echo $totalChicks; ?> chicks</span>
        </div>
        <div class="grid-3">
            <div class="card" style="border-top: 3px solid var(--green);" onclick="window.location.href='chicken_status.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-check-circle" style="color:var(--green);"></i> Healthy</span><span class="card-badge badge-success"><?php echo $healthyChicks; ?></span></div>
                <div style="font-size:2.5rem;font-weight:800;color:var(--green);"><?php echo $healthyChicks; ?></div>
                <div style="font-size:0.8rem;color:var(--text-muted);">Chicks</div>
            </div>
            <div class="card" style="border-top: 3px solid var(--yellow);" onclick="window.location.href='chicken_status.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-exclamation-circle" style="color:var(--yellow);"></i> Weak</span><span class="card-badge badge-warning"><?php echo $weakChicks; ?></span></div>
                <div style="font-size:2.5rem;font-weight:800;color:var(--yellow);"><?php echo $weakChicks; ?></div>
                <div style="font-size:0.8rem;color:var(--text-muted);">Chicks</div>
            </div>
            <div class="card" style="border-top: 3px solid var(--red);" onclick="window.location.href='chicken_status.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-times-circle" style="color:var(--red);"></i> Unhealthy</span><span class="card-badge badge-danger"><?php echo $unhealthyChicks; ?></span></div>
                <div style="font-size:2.5rem;font-weight:800;color:var(--red);"><?php echo $unhealthyChicks; ?></div>
                <div style="font-size:0.8rem;color:var(--text-muted);">Chicks</div>
            </div>
        </div>

        <!-- Automation Status -->
        <div class="section-label">
            <span><i class="fas fa-cog"></i> Automation Status</span>
            <select class="filter-select" onchange="filterAutomation(this.value)" style="font-size:0.7rem;">
                <option value="all">All Devices</option><option value="active">Active Only</option><option value="inactive">Inactive Only</option>
            </select>
        </div>
        <div class="grid-3" id="automationGrid">
            <div class="card automation-item" data-status="<?php echo $automation['fan_status']; ?>" onclick="window.location.href='fan_control.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-fan"></i> Fan Status</span><span class="card-badge <?php echo $automation['fan_status'] === 'ON' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $automation['fan_status']; ?></span></div>
                <div class="automation-card"><div class="status-indicator <?php echo $automation['fan_status'] === 'ON' ? 'status-on' : 'status-off'; ?>"></div><div><strong><?php echo $automation['fan_status']; ?></strong><br><small style="color:var(--text-muted);">Mode: <?php echo $automation['fan_mode']; ?></small></div></div>
            </div>
            <div class="card automation-item" data-status="<?php echo $automation['water_pump']; ?>" onclick="window.location.href='water_pump.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-hand-holding-water"></i> Water Pump</span><span class="card-badge <?php echo $automation['water_pump'] === 'ON' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $automation['water_pump']; ?></span></div>
                <div class="automation-card"><div class="status-indicator <?php echo $automation['water_pump'] === 'ON' ? 'status-on' : 'status-off'; ?>"></div><div><strong><?php echo $automation['water_pump']; ?></strong><br><small style="color:var(--text-muted);">Mode: <?php echo $automation['water_pump_mode']; ?></small></div></div>
            </div>
            <div class="card automation-item" data-status="<?php echo $automation['feed_dispenser']; ?>" onclick="window.location.href='feed_dispenser.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</span><span class="card-badge badge-warning"><?php echo $automation['feed_dispenser']; ?></span></div>
                <div class="automation-card"><div class="status-indicator status-off"></div><div><strong><?php echo $automation['feed_dispenser']; ?></strong><br><small style="color:var(--text-muted);">Next: <?php echo $automation['feed_schedule']; ?></small></div></div>
            </div>
        </div>

        <!-- Data Charts -->
        <div class="section-label"><span><i class="fas fa-chart-line"></i> Data Charts</span></div>
        
        <div class="chart-filters">
            <div class="period-buttons">
                <button class="period-btn <?php echo $chartPeriod === 'day' ? 'active' : ''; ?>" onclick="changeChartPeriod('day')"><i class="fas fa-calendar-day"></i> Day</button>
                <button class="period-btn <?php echo $chartPeriod === 'week' ? 'active' : ''; ?>" onclick="changeChartPeriod('week')"><i class="fas fa-calendar-week"></i> Week (7 Days)</button>
            </div>
            <div class="chart-type-buttons">
                <button class="chart-type-btn active" onclick="filterCharts('all', this)">All</button>
                <button class="chart-type-btn" onclick="filterCharts('temperature', this)">Temp</button>
                <button class="chart-type-btn" onclick="filterCharts('humidity', this)">Humidity</button>
                <button class="chart-type-btn" onclick="filterCharts('feed', this)">Feed</button>
                <button class="chart-type-btn" onclick="filterCharts('water', this)">Water</button>
            </div>
        </div>

        <div class="grid-2" id="chartsContainer">
            <div class="card chart-item" data-category="temperature"><div class="card-header"><span class="card-title"><i class="fas fa-temperature-high"></i> Temperature Trend</span></div><div class="chart-wrapper"><canvas id="tempTrendChart"></canvas></div></div>
            <div class="card chart-item" data-category="humidity"><div class="card-header"><span class="card-title"><i class="fas fa-tint"></i> Humidity Trend</span></div><div class="chart-wrapper"><canvas id="humTrendChart"></canvas></div></div>
            <div class="card chart-item" data-category="feed"><div class="card-header"><span class="card-title"><i class="fas fa-utensils"></i> Feed Consumption</span></div><div class="chart-wrapper"><canvas id="feedChart"></canvas></div></div>
            <div class="card chart-item" data-category="water"><div class="card-header"><span class="card-title"><i class="fas fa-water"></i> Water Consumption</span></div><div class="chart-wrapper"><canvas id="waterChart"></canvas></div></div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="section-label"><span><i class="fas fa-history"></i> Recent Activity Records</span><span style="font-size:0.7rem;color:var(--text-muted);"><?php echo count($records); ?> records found</span></div>
        
        <div class="search-filter-bar">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" id="envSearch" placeholder="Search by category, status, or description..." value="<?php echo htmlspecialchars($search); ?>">
                <button onclick="performEnvSearch()" style="background: none; border: none; cursor: pointer;"><i class="fas fa-arrow-right" style="color: var(--accent-dark);"></i></button>
            </div>
            <div class="filter-group">
                <select class="filter-select" id="filterSelect">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="environment" <?php echo $filter === 'environment' ? 'selected' : ''; ?>>Environment</option>
                    <option value="chickens" <?php echo $filter === 'chickens' ? 'selected' : ''; ?>>Chickens</option>
                    <option value="automation" <?php echo $filter === 'automation' ? 'selected' : ''; ?>>Automation</option>
                    <option value="inventory" <?php echo $filter === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                </select>
                <input type="date" class="date-input" id="dateFromInput" value="<?php echo $dateFrom; ?>" placeholder="From">
                <input type="date" class="date-input" id="dateToInput" value="<?php echo $dateTo; ?>" placeholder="To">
                <select class="filter-select" id="sortSelect">
                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
                <button class="apply-btn" onclick="applyAllFilters()"><i class="fas fa-filter"></i> Apply</button>
                <button class="reset-filters-btn" onclick="resetAllFilters()"><i class="fas fa-undo-alt"></i> Reset</button>
            </div>
        </div>

        <!-- Records Table -->
        <div class="card" style="padding:0;overflow:hidden;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Date & Time</th><th>Category</th><th>Value</th><th>Status</th><th>Description</th>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo date('M d, Y h:i A', strtotime($record['timestamp'])); ?></td>
                            <td><span class="category-badge" style="background: var(--blue-light); color: var(--blue);"><?php echo ucfirst($record['category']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($record['value']); ?></strong></td>
                            <td><span class="card-badge <?php echo in_array($record['status'], ['normal', 'success', 'active']) ? 'badge-success' : 'badge-warning'; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($record['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($records)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fas fa-search" style="font-size:2.5rem;display:block;margin-bottom:1rem;opacity:0.5;"></i>No records found. Try adjusting your filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    const chartLabels = <?php echo json_encode($chartData['labels']); ?>;
    const chartTemp = <?php echo json_encode($chartData['temp']); ?>;
    const chartHumidity = <?php echo json_encode($chartData['humidity']); ?>;
    const chartFeed = <?php echo json_encode($chartData['feed']); ?>;
    const chartWater = <?php echo json_encode($chartData['water']); ?>;
    let tempChart, humChart, feedChart, waterChart;

    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    function updateClock() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    setInterval(updateClock, 1000);

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    
    function openWeatherModal() { document.getElementById('weatherModal').classList.add('active'); }
    function closeWeatherModal() { document.getElementById('weatherModal').classList.remove('active'); }
    function refreshWeather() { window.location.href = 'dashboard.php?refresh_weather=1'; }
    document.getElementById('weatherModal').addEventListener('click', function(e) { if (e.target === this) closeWeatherModal(); });

    function performEnvSearch() { applyAllFilters(); }
    document.getElementById('envSearch')?.addEventListener('keypress', function(e) { if (e.key === 'Enter') performEnvSearch(); });

    function applyAllFilters() {
        const search = document.getElementById('envSearch').value;
        const filter = document.getElementById('filterSelect').value;
        const dateFrom = document.getElementById('dateFromInput').value;
        const dateTo = document.getElementById('dateToInput').value;
        const sortBy = document.getElementById('sortSelect').value;
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (filter !== 'all') params.set('filter', filter);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (sortBy !== 'newest') params.set('sort_by', sortBy);
        window.location.href = 'dashboard.php?' + params.toString();
    }

    function resetAllFilters() { window.location.href = 'dashboard.php'; }
    function clearFilter(param) { const params = new URLSearchParams(window.location.search); params.delete(param); window.location.href = 'dashboard.php?' + params.toString(); }
    function clearAllFilters() { window.location.href = 'dashboard.php'; }
    function changeChartPeriod(period) { const params = new URLSearchParams(window.location.search); params.set('chart_period', period); window.location.href = 'dashboard.php?' + params.toString(); }

    function filterAutomation(status) {
        document.querySelectorAll('.automation-item').forEach(item => {
            if (status === 'all') item.style.display = 'block';
            else if (status === 'active') item.style.display = item.dataset.status === 'ON' ? 'block' : 'none';
            else item.style.display = item.dataset.status === 'OFF' ? 'block' : 'none';
        });
    }

    function filterCharts(category, btn) {
        document.querySelectorAll('.chart-type-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        document.querySelectorAll('.chart-item').forEach(item => {
            item.style.display = (category === 'all' || item.dataset.category === category) ? 'block' : 'none';
        });
        setTimeout(() => {
            if (tempChart) tempChart.resize();
            if (humChart) humChart.resize();
            if (feedChart) feedChart.resize();
            if (waterChart) waterChart.resize();
        }, 100);
    }

    function initCharts() {
        const chartOptions = { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } 
            }, 
            scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } } } 
        };
        
        [tempChart, humChart, feedChart, waterChart].forEach(chart => { if (chart) chart.destroy(); });

        const tempCtx = document.getElementById('tempTrendChart');
        if (tempCtx) tempChart = new Chart(tempCtx, { type: 'line', data: { labels: chartLabels, datasets: [{ label: 'Temperature (°C)', data: chartTemp, borderColor: '#E67E22', backgroundColor: 'rgba(230,126,34,0.1)', fill: true, tension: 0.3, pointRadius: 4, pointHoverRadius: 6 }] }, options: chartOptions });

        const humCtx = document.getElementById('humTrendChart');
        if (humCtx) humChart = new Chart(humCtx, { type: 'line', data: { labels: chartLabels, datasets: [{ label: 'Humidity (%)', data: chartHumidity, borderColor: '#2980B9', backgroundColor: 'rgba(41,128,185,0.1)', fill: true, tension: 0.3, pointRadius: 4, pointHoverRadius: 6 }] }, options: chartOptions });

        const feedCtx = document.getElementById('feedChart');
        if (feedCtx) feedChart = new Chart(feedCtx, { type: 'bar', data: { labels: chartLabels, datasets: [{ label: 'Feed (kg)', data: chartFeed, backgroundColor: '#4D724D', borderRadius: 6, maxBarThickness: 50 }] }, options: { ...chartOptions, scales: { y: { beginAtZero: true } } } });

        const waterCtx = document.getElementById('waterChart');
        if (waterCtx) waterChart = new Chart(waterCtx, { type: 'bar', data: { labels: chartLabels, datasets: [{ label: 'Water (L)', data: chartWater, backgroundColor: '#4F6C7A', borderRadius: 6, maxBarThickness: 50 }] }, options: { ...chartOptions, scales: { y: { beginAtZero: true } } } });
    }

    window.addEventListener('load', () => {
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) {
            activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
        }
        updateClock();
    });

    document.addEventListener('DOMContentLoaded', initCharts);
    window.addEventListener('resize', () => { if (tempChart) tempChart.resize(); if (humChart) humChart.resize(); if (feedChart) feedChart.resize(); if (waterChart) waterChart.resize(); });
</script>
</body>
</html>