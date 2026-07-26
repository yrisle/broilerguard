<?php

session_start();


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}


if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}


$tab = $_GET['tab'] ?? 'dashboard';
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';
$chartPeriod = $_GET['chart_period'] ?? 'week';

// Weather API Configuration - Using OpenWeatherMap
define('WEATHER_API_KEY', 'YOUR_API_KEY_HERE');
define('WEATHER_CITY', 'Manila');
define('WEATHER_COUNTRY', 'PH');

function getWeatherData() {
    $cacheFile = 'weather_cache.json';
    $cacheTime = 1800;
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . WEATHER_CITY . "," . WEATHER_COUNTRY . "&appid=" . WEATHER_API_KEY . "&units=metric";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        $weatherData = [
            'temp' => round($data['main']['temp']),
            'feels_like' => round($data['main']['feels_like']),
            'temp_min' => round($data['main']['temp_min']),
            'temp_max' => round($data['main']['temp_max']),
            'humidity' => $data['main']['humidity'],
            'pressure' => $data['main']['pressure'],
            'condition' => $data['weather'][0]['main'],
            'description' => $data['weather'][0]['description'],
            'icon_code' => $data['weather'][0]['icon'],
            'wind_speed' => $data['wind']['speed'],
            'wind_deg' => $data['wind']['deg'],
            'city' => $data['name'],
            'country' => $data['sys']['country'],
            'sunrise' => date('h:i A', $data['sys']['sunrise']),
            'sunset' => date('h:i A', $data['sys']['sunset']),
            'timestamp' => time(),
            'last_updated' => date('h:i A')
        ];
        
        file_put_contents($cacheFile, json_encode($weatherData));
        return $weatherData;
    }
    
    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    return [
        'temp' => 31, 'condition' => 'Partly Cloudy', 'icon_code' => '02d',
        'city' => WEATHER_CITY, 'last_updated' => date('h:i A')
    ];
}

function getWeatherIcon($iconCode) {
    $icons = [
        '01d' => 'fa-sun', '01n' => 'fa-moon',
        '02d' => 'fa-cloud-sun', '02n' => 'fa-cloud-moon',
        '03d' => 'fa-cloud', '03n' => 'fa-cloud',
        '04d' => 'fa-cloud', '04n' => 'fa-cloud',
        '09d' => 'fa-cloud-rain', '09n' => 'fa-cloud-rain',
        '10d' => 'fa-cloud-sun-rain', '10n' => 'fa-cloud-moon-rain',
        '11d' => 'fa-bolt', '11n' => 'fa-bolt',
        '13d' => 'fa-snowflake', '13n' => 'fa-snowflake',
        '50d' => 'fa-smog', '50n' => 'fa-smog'
    ];
    return $icons[$iconCode] ?? 'fa-cloud';
}

$weather = getWeatherData();

// Get real sensor data from session or generate realistic data
function getSensorData() {
    if (isset($_SESSION['shared_sensor_data'])) {
        return [
            'temperature' => $_SESSION['shared_sensor_data']['temperature'],
            'humidity' => $_SESSION['shared_sensor_data']['humidity']
        ];
    }
    
    $hour = (int)date('H');
    $baseTemp = 28;
    $tempVariation = sin(($hour - 14) * M_PI / 12) * 4;
    $currentTemp = $baseTemp + $tempVariation + mt_rand(-2, 2) / 10;
    $humidity = 55 + sin($hour * M_PI / 12) * 15 + mt_rand(-3, 3);
    
    return [
        'temperature' => round($currentTemp, 1),
        'humidity' => round($humidity, 1)
    ];
}

function getChartData($period) {
    switch ($period) {
        case 'day':
            return [
                'labels' => ['6AM', '8AM', '10AM', '12PM', '2PM', '4PM', '6PM', '8PM'],
                'temp' => [30.1, 31.2, 32.0, 33.1, 33.8, 32.9, 31.5, 30.8],
                'humidity' => [70, 72, 68, 65, 63, 67, 71, 73],
                'feed' => [5, 8, 12, 15, 18, 20, 22, 25],
                'water' => [3, 5, 8, 10, 12, 14, 16, 18]
            ];
        case 'week':
        default:
            return [
                'labels' => ['Day 1', 'Day 2', 'Day 3', 'Day 4'],
                'temp' => [31.2, 32.1, 31.8, 32.5],
                'humidity' => [68, 70, 72, 69],
                'feed' => [28, 30, 27, 32],
                'water' => [18, 20, 19, 22]
            ];
    }
}

function getDashboardStats($filter = 'all', $dateFrom = '', $dateTo = '', $sortBy = 'newest', $search = '') {
    $sensorData = getSensorData();
    
    $stats = [
        'healthy_chicks' => rand(95, 100),
        'weak_chicks' => rand(8, 20),
        'unhealthy_chicks' => rand(2, 8),
        'total_chicks' => 150,
        'active_alerts' => rand(1, 5),
        'temperature' => $sensorData['temperature'],
        'humidity' => $sensorData['humidity'],
        'feed_level' => rand(75, 98),
        'water_level' => rand(80, 99),
        'fan_status' => (rand(0, 10) > 2) ? 'ON' : 'OFF',
        'fan_mode' => 'Auto',
        'fan_hours' => rand(8, 18),
        'water_pump' => (rand(0, 10) > 5) ? 'ON' : 'OFF',
        'water_pump_mode' => 'Auto',
        'feed_dispenser' => 'OFF',
        'feed_schedule' => '06:00 AM',
        'feed_consumed_today' => rand(25, 45),
        'water_consumed_today' => rand(15, 30),
        'records' => generateMockRecords($filter, $dateFrom, $dateTo, $sortBy, $search)
    ];
    return $stats;
}

function generateMockRecords($filter, $dateFrom, $dateTo, $sortBy, $search) {
    $allRecords = [
        ['date' => '2024-01-15 10:30:00', 'category' => 'temperature', 'value' => '32.5°C', 'status' => 'normal', 'description' => 'Temperature reading from sensor A'],
        ['date' => '2024-01-15 09:15:00', 'category' => 'humidity', 'value' => '68%', 'status' => 'normal', 'description' => 'Humidity level optimal'],
        ['date' => '2024-01-14 14:20:00', 'category' => 'feed', 'value' => '85%', 'status' => 'warning', 'description' => 'Feed level below threshold'],
        ['date' => '2024-01-14 08:00:00', 'category' => 'water', 'value' => '90%', 'status' => 'normal', 'description' => 'Water level adequate'],
        ['date' => '2024-01-13 16:45:00', 'category' => 'chickens', 'value' => '145 healthy', 'status' => 'success', 'description' => 'Health check completed'],
        ['date' => '2024-01-13 11:30:00', 'category' => 'automation', 'value' => 'Fan ON', 'status' => 'active', 'description' => 'Fan activated automatically'],
        ['date' => '2024-01-12 15:00:00', 'category' => 'temperature', 'value' => '33.1°C', 'status' => 'warning', 'description' => 'Temperature rising'],
        ['date' => '2024-01-12 09:00:00', 'category' => 'inventory', 'value' => '75%', 'status' => 'warning', 'description' => 'Feed inventory running low'],
        ['date' => '2024-01-11 12:00:00', 'category' => 'chickens', 'value' => '3 weak', 'status' => 'warning', 'description' => 'Weak chicks detected'],
        ['date' => '2024-01-10 08:30:00', 'category' => 'water', 'value' => '95%', 'status' => 'normal', 'description' => 'Water pump operating normally'],
    ];
    
    $records = $allRecords;
    
    if ($search) {
        $searchLower = strtolower($search);
        $records = array_filter($records, function($record) use ($searchLower) {
            return strpos(strtolower($record['category']), $searchLower) !== false ||
                   strpos(strtolower($record['value']), $searchLower) !== false ||
                   strpos(strtolower($record['status']), $searchLower) !== false ||
                   strpos(strtolower($record['description']), $searchLower) !== false;
        });
    }
    
    if ($dateFrom || $dateTo) {
        $records = array_filter($records, function($record) use ($dateFrom, $dateTo) {
            $recordDate = strtotime($record['date']);
            if ($dateFrom && $recordDate < strtotime($dateFrom)) return false;
            if ($dateTo && $recordDate > strtotime($dateTo . ' 23:59:59')) return false;
            return true;
        });
    }
    
    if ($filter !== 'all') {
        $categoryMap = [
            'environment' => ['temperature', 'humidity'],
            'chickens' => ['chickens'],
            'automation' => ['automation'],
            'inventory' => ['feed', 'water', 'inventory']
        ];
        
        if (isset($categoryMap[$filter])) {
            $allowedCategories = $categoryMap[$filter];
            $records = array_filter($records, function($record) use ($allowedCategories) {
                return in_array($record['category'], $allowedCategories);
            });
        }
    }
    
    usort($records, function($a, $b) use ($sortBy) {
        if ($sortBy === 'oldest') {
            return strtotime($a['date']) - strtotime($b['date']);
        }
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_values($records);
}

$stats = getDashboardStats($filter, $dateFrom, $dateTo, $sortBy, $search);
$chartData = getChartData($chartPeriod);
$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
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
        /* --- GLOBAL RESET & SCROLLBAR HIDE --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Inter', sans-serif;
            background: #FFFCF2;
            color: #3E2C1C;
            display: flex;
            min-height: 100vh;
            overflow: hidden;
            height: 100%;
        }
        /* Hide scrollbar completely */
        ::-webkit-scrollbar { display: none; width: 0; height: 0; }
        * { scrollbar-width: none; -ms-overflow-style: none; }
        
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
        
        body { background: var(--bg-primary); }
        
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
        .sidebar-nav a .badge-sidebar { 
            margin-left: auto; 
            background: var(--red); 
            color: white; 
            font-size: 0.6rem; 
            padding: 0.1rem 0.5rem; 
            border-radius: 12px; 
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
        
        /* Main Content */
        .main-content { 
            margin-left: var(--sidebar-width); 
            flex: 1; 
            min-height: 100vh; 
            max-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .main-content::-webkit-scrollbar { display: none; width: 0; height: 0; }
        
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid rgba(255, 214, 46, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 999; box-shadow: var(--shadow-sm); }
        .top-header .header-left { display: flex; align-items: center; gap: 1.5rem; }
        .menu-toggle { display: none; font-size: 1.6rem; cursor: pointer; color: var(--text-primary); background: none; border: none; padding: 0.4rem 0.8rem; border-radius: 10px; transition: background 0.2s; }
        .menu-toggle:hover { background: var(--bg-secondary); }
        .date-time-container { display: flex; flex-direction: column; gap: 0.05rem; }
        .date-time-container .date { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.3px; }
        .date-time-container .time { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        
        /* Weather Widget */
        .weather-widget { display: flex; align-items: center; gap: 0.6rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 0.3rem 1rem; border-radius: 30px; color: white; cursor: pointer; border: none; transition: all 0.2s; font-size: 0.85rem; }
        .weather-widget:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .weather-widget i { font-size: 1rem; }
        .weather-widget .weather-temp { font-weight: 700; font-size: 0.95rem; }
        
        /* Notification Bell */
        .notification-bell { position: relative; background: var(--bg-secondary); border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; border: 1px solid rgba(255, 214, 46, 0.25); text-decoration: none; }
        .notification-bell:hover { background: var(--accent-light); transform: scale(1.05); }
        .notification-bell i { font-size: 1.2rem; color: var(--text-secondary); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--red); color: white; font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 50%; min-width: 20px; text-align: center; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .weather-modal { background: white; border-radius: 20px; padding: 2rem; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); position: relative; }
        .weather-modal .close-btn { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .weather-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem; }
        .weather-detail-item { background: var(--bg-secondary); padding: 1rem; border-radius: 10px; text-align: center; }
        .weather-detail-item i { font-size: 1.5rem; color: var(--accent-dark); margin-bottom: 0.5rem; }
        .weather-detail-item .label { font-size: 0.75rem; color: var(--text-muted); }
        .weather-detail-item .value { font-size: 1.1rem; font-weight: 700; }
        
        .page-content { padding: 1.5rem 2rem 2.5rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.2rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        
        /* Section Label */
        .section-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin-bottom: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        
        /* Search & Filter Bar */
        .search-filter-bar { background: var(--bg-card); border-radius: 12px; padding: 0.6rem 1rem; border: 1px solid rgba(255, 214, 46, 0.1); display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); }
        .search-input-group { flex: 2; min-width: 180px; display: flex; align-items: center; gap: 0.5rem; background: var(--bg-secondary); padding: 0.4rem 0.8rem; border-radius: 30px; }
        .search-input-group i { color: var(--text-muted); font-size: 0.85rem; }
        .search-input-group input { border: none; background: none; outline: none; flex: 1; font-family: 'Inter', sans-serif; font-size: 0.8rem; color: var(--text-primary); }
        .search-input-group input::placeholder { color: var(--text-muted); }
        .filter-group { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
        .filter-select, .date-input { padding: 0.4rem 0.8rem; border-radius: 30px; border: 1px solid rgba(255, 214, 46, 0.2); background: var(--bg-secondary); font-family: 'Inter', sans-serif; font-size: 0.75rem; color: var(--text-primary); cursor: pointer; }
        .apply-btn { background: linear-gradient(105deg, #E6B800, #FFD62E); border: none; padding: 0.4rem 1rem; border-radius: 30px; font-weight: 600; font-size: 0.75rem; cursor: pointer; color: #3E2C1C; transition: all 0.2s; }
        .apply-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .reset-filters-btn { background: var(--red-light); border: none; padding: 0.4rem 1rem; border-radius: 30px; font-weight: 600; font-size: 0.75rem; cursor: pointer; color: var(--red); transition: all 0.2s; }
        .reset-filters-btn:hover { background: var(--red); color: white; }
        
        /* Cards */
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); transition: all 0.3s ease; cursor: pointer; text-decoration: none; display: block; color: inherit; }
        .card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); border-color: var(--accent); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; }
        .card-title { font-weight: 700; font-size: 0.95rem; }
        .card-badge { padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }
        
        /* Resource Level Cards */
        .resource-card { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .progress-container { flex: 1; }
        .progress-bar-bg { background: #E8E0D0; border-radius: 20px; height: 10px; overflow: hidden; margin: 0.4rem 0; }
        .progress-fill { height: 100%; border-radius: 20px; transition: width 0.5s ease; }
        .progress-fill.feed { background: linear-gradient(90deg, #E6B800, #FFD62E); width: <?php echo $stats['feed_level']; ?>%; }
        .progress-fill.water { background: linear-gradient(90deg, #2980B9, #5DADE2); width: <?php echo $stats['water_level']; ?>%; }
        .resource-stats { display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-muted); }
        .resource-value { font-size: 1.6rem; font-weight: 800; line-height: 1; }
        
        /* Automation Cards */
        .automation-card { display: flex; align-items: center; gap: 0.8rem; padding: 0.6rem; background: var(--bg-secondary); border-radius: 10px; }
        .status-indicator { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .status-on { background: var(--green); box-shadow: 0 0 8px rgba(39, 174, 96, 0.5); animation: pulse 1.5s infinite; }
        .status-off { background: #95A5A6; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        
        /* Chart Filters */
        .chart-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; justify-content: space-between; align-items: center; }
        .period-buttons { display: flex; gap: 0.4rem; }
        .period-btn, .chart-type-btn { padding: 0.3rem 0.9rem; border-radius: 30px; border: 1px solid rgba(255, 214, 46, 0.2); background: var(--bg-card); cursor: pointer; font-size: 0.7rem; font-weight: 500; color: var(--text-secondary); transition: all 0.2s; }
        .period-btn.active, .chart-type-btn.active { background: var(--accent); color: #3E2C1C; border-color: var(--accent); font-weight: 600; }
        
        .chart-wrapper { position: relative; width: 100%; height: 240px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }
        
        /* Table */
        .table-container { overflow-x: auto; border-radius: var(--border-radius); }
        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        th { background: var(--bg-secondary); padding: 0.6rem 0.8rem; text-align: left; font-weight: 600; border-bottom: 2px solid rgba(255, 214, 46, 0.15); color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 0.6rem 0.8rem; border-bottom: 1px solid rgba(255, 214, 46, 0.06); }
        tr:hover td { background: var(--bg-secondary); }
        .category-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 15px; font-size: 0.65rem; font-weight: 600; }
        
        .active-filters { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center; }
        .active-filter-tag { background: var(--accent-light); padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.7rem; display: flex; align-items: center; gap: 0.3rem; cursor: pointer; border: none; font-family: 'Inter', sans-serif; color: var(--text-secondary); }
        .clear-all-tag { background: var(--red-light) !important; color: var(--red) !important; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.7rem 1rem; border-radius: 12px; display: none; align-items: center; gap: 0.6rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.8rem; box-shadow: var(--shadow-md); }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .grid-2 { grid-template-columns: 1fr; }
            .search-filter-bar { flex-direction: column; border-radius: 12px; }
            .search-input-group { width: 100%; }
            .filter-group { width: 100%; justify-content: center; }
            .chart-wrapper { height: 200px; }
        }
        @media (max-width: 640px) {
            .grid-3 { grid-template-columns: 1fr; }
            .chart-filters { flex-direction: column; align-items: stretch; }
            .period-buttons { justify-content: center; }
            .top-header { padding: 0 1rem; }
            .page-content { padding: 1rem; }
        }
    </style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================ -->
<!-- SIDEBAR / NAVBAR - FEED MONITORING STYLE -->
<!-- ============================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon"><i class="fas fa-feather-alt"></i></span>
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
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <span class="badge-sidebar"><?php echo $stats['active_alerts']; ?></span></a>
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
            <button class="weather-widget" onclick="openWeatherModal()" title="Click for detailed weather">
                <i class="fas <?php echo getWeatherIcon($weather['icon_code']); ?>"></i>
                <span class="weather-temp"><?php echo $weather['temp']; ?>°C</span>
            </button>
        </div>
    </header>

    <!-- Weather Modal -->
    <div class="modal-overlay" id="weatherModal">
        <div class="weather-modal">
            <button class="close-btn" onclick="closeWeatherModal()">&times;</button>
            <h2><i class="fas <?php echo getWeatherIcon($weather['icon_code']); ?>"></i> <?php echo $weather['city']; ?>, <?php echo $weather['country']; ?></h2>
            <div style="text-align:center;font-size:3rem;font-weight:800;"><?php echo $weather['temp']; ?>°C</div>
            <div style="text-align:center;color:var(--text-muted);"><?php echo ucfirst($weather['description'] ?? $weather['condition']); ?></div>
            <div class="weather-details-grid">
                <div class="weather-detail-item"><i class="fas fa-temperature-high"></i><div class="label">Feels Like</div><div class="value"><?php echo $weather['feels_like'] ?? $weather['temp']; ?>°C</div></div>
                <div class="weather-detail-item"><i class="fas fa-thermometer-half"></i><div class="label">Min / Max</div><div class="value"><?php echo $weather['temp_min'] ?? $weather['temp']; ?>° / <?php echo $weather['temp_max'] ?? $weather['temp']; ?>°</div></div>
                <div class="weather-detail-item"><i class="fas fa-tint"></i><div class="label">Humidity</div><div class="value"><?php echo $weather['humidity'] ?? 'N/A'; ?>%</div></div>
                <div class="weather-detail-item"><i class="fas fa-compress-alt"></i><div class="label">Pressure</div><div class="value"><?php echo $weather['pressure'] ?? 'N/A'; ?> hPa</div></div>
                <div class="weather-detail-item"><i class="fas fa-wind"></i><div class="label">Wind Speed</div><div class="value"><?php echo $weather['wind_speed'] ?? 'N/A'; ?> m/s</div></div>
                <div class="weather-detail-item"><i class="fas fa-sun"></i><div class="label">Sunrise / Sunset</div><div class="value"><?php echo $weather['sunrise'] ?? 'N/A'; ?> / <?php echo $weather['sunset'] ?? 'N/A'; ?></div></div>
            </div>
            <button onclick="refreshWeather()" style="display:block;margin:1rem auto 0;padding:0.4rem 1rem;background:var(--accent);border:none;border-radius:20px;cursor:pointer;font-weight:600;font-size:0.8rem;"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
    </div>

    <div class="page-content">
        <h1 class="page-title">Farm Overview Dashboard</h1>
        <p class="page-subtitle">Real-time monitoring of your broiler chicks farm operations</p>

        <!-- Active Filters Display -->
        <?php if ($filter !== 'all' || $search || $dateFrom || $dateTo): ?>
        <div class="active-filters">
            <span style="font-size:0.65rem;color:var(--text-muted);font-weight:600;">Active Filters:</span>
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
                <div class="card-header"><span class="card-title"><i class="fas fa-temperature-high"></i> Temperature</span><span class="card-badge badge-success">Normal</span></div>
                <div style="display:flex;align-items:center;gap:1.5rem;">
                    <div style="font-size:2.5rem;font-weight:800;color:var(--orange);"><?php echo $stats['temperature']; ?>°C</div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Ideal Range<br>30°C - 35°C</div>
                </div>
            </div>
            <div class="card" onclick="window.location.href='temperature.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-tint"></i> Humidity</span><span class="card-badge badge-success">Optimal</span></div>
                <div style="display:flex;align-items:center;gap:1.5rem;">
                    <div style="font-size:2.5rem;font-weight:800;color:var(--blue);"><?php echo $stats['humidity']; ?>%</div>
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
                <div class="card-header"><span class="card-title"><i class="fas fa-utensils"></i> Feed Level</span><span class="card-badge badge-success">Sufficient</span></div>
                <div class="resource-card">
                    <div class="progress-container">
                        <div class="resource-value"><?php echo $stats['feed_level']; ?>%</div>
                        <div class="progress-bar-bg"><div class="progress-fill feed"></div></div>
                        <div class="resource-stats"><span>Remaining</span><span><?php echo $stats['feed_consumed_today']; ?> kg today</span></div>
                    </div>
                </div>
            </div>
            <div class="card" onclick="window.location.href='water_inventory.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-water"></i> Water Level</span><span class="card-badge badge-success">Adequate</span></div>
                <div class="resource-card">
                    <div class="progress-container">
                        <div class="resource-value"><?php echo $stats['water_level']; ?>%</div>
                        <div class="progress-bar-bg"><div class="progress-fill water"></div></div>
                        <div class="resource-stats"><span>Remaining</span><span><?php echo $stats['water_consumed_today']; ?> L today</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Automation Status -->
        <div class="section-label">
            <span><i class="fas fa-cog"></i> Automation Status</span>
            <select class="filter-select" onchange="filterAutomation(this.value)" style="font-size:0.7rem;padding:0.2rem 0.6rem;">
                <option value="all">All Devices</option><option value="active">Active Only</option><option value="inactive">Inactive Only</option>
            </select>
        </div>
        <div class="grid-3" id="automationGrid">
            <div class="card automation-item" data-status="<?php echo $stats['fan_status']; ?>" onclick="window.location.href='fan_control.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-fan"></i> Fan Status</span><span class="card-badge <?php echo $stats['fan_status'] === 'ON' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $stats['fan_status']; ?></span></div>
                <div class="automation-card"><div class="status-indicator <?php echo $stats['fan_status'] === 'ON' ? 'status-on' : 'status-off'; ?>"></div><div><strong><?php echo $stats['fan_status']; ?></strong><br><small style="color:var(--text-muted);">Mode: <?php echo $stats['fan_mode']; ?></small></div></div>
            </div>
            <div class="card automation-item" data-status="<?php echo $stats['water_pump']; ?>" onclick="window.location.href='water_pump.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-hand-holding-water"></i> Water Pump</span><span class="card-badge <?php echo $stats['water_pump'] === 'ON' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $stats['water_pump']; ?></span></div>
                <div class="automation-card"><div class="status-indicator <?php echo $stats['water_pump'] === 'ON' ? 'status-on' : 'status-off'; ?>"></div><div><strong><?php echo $stats['water_pump']; ?></strong><br><small style="color:var(--text-muted);">Mode: <?php echo $stats['water_pump_mode']; ?></small></div></div>
            </div>
            <div class="card automation-item" data-status="<?php echo $stats['feed_dispenser']; ?>" onclick="window.location.href='feed_dispenser.php'">
                <div class="card-header"><span class="card-title"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</span><span class="card-badge badge-warning"><?php echo $stats['feed_dispenser']; ?></span></div>
                <div class="automation-card"><div class="status-indicator status-off"></div><div><strong><?php echo $stats['feed_dispenser']; ?></strong><br><small style="color:var(--text-muted);">Next: <?php echo $stats['feed_schedule']; ?></small></div></div>
            </div>
        </div>

        <!-- Data Charts -->
        <div class="section-label"><span><i class="fas fa-chart-line"></i> Data Charts</span></div>
        
        <div class="chart-filters">
            <div class="period-buttons">
                <button class="period-btn <?php echo $chartPeriod === 'day' ? 'active' : ''; ?>" onclick="changeChartPeriod('day')"><i class="fas fa-calendar-day"></i> Day</button>
                <button class="period-btn <?php echo $chartPeriod === 'week' ? 'active' : ''; ?>" onclick="changeChartPeriod('week')"><i class="fas fa-calendar-week"></i> Week (4 Days)</button>
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
        <div class="section-label"><span><i class="fas fa-history"></i> Recent Activity Records</span><span style="font-size:0.7rem;color:var(--text-muted);"><?php echo count($stats['records']); ?> records found</span></div>
        
        <div class="search-filter-bar">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" id="envSearch" placeholder="Search by category, status, or description..." value="<?php echo htmlspecialchars($search); ?>">
                <button onclick="performEnvSearch()" style="background: none; border: none; cursor: pointer; color: var(--accent-dark);"><i class="fas fa-arrow-right"></i></button>
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
                        <?php foreach ($stats['records'] as $record): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo $record['date']; ?></td>
                            <td><span class="category-badge" style="background: var(--blue-light); color: var(--blue);"><?php echo ucfirst($record['category']); ?></span></td>
                            <td><strong><?php echo $record['value']; ?></strong></td>
                            <td><span class="card-badge <?php echo in_array($record['status'], ['normal', 'success', 'active']) ? 'badge-success' : 'badge-warning'; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                            <td style="color:var(--text-muted);"><?php echo $record['description']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stats['records'])): ?>
                        <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);"><i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.5;"></i>No records found. Try adjusting your filters.</td></tr>
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
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } } 
            }, 
            scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } } } 
        };
        
        [tempChart, humChart, feedChart, waterChart].forEach(chart => { if (chart) chart.destroy(); });

        const tempCtx = document.getElementById('tempTrendChart');
        if (tempCtx) tempChart = new Chart(tempCtx, { type: 'line', data: { labels: chartLabels, datasets: [{ label: 'Temperature (°C)', data: chartTemp, borderColor: '#E67E22', backgroundColor: 'rgba(230,126,34,0.1)', fill: true, tension: 0.3, pointRadius: 4, pointHoverRadius: 6 }] }, options: chartOptions });

        const humCtx = document.getElementById('humTrendChart');
        if (humCtx) humChart = new Chart(humCtx, { type: 'line', data: { labels: chartLabels, datasets: [{ label: 'Humidity (%)', data: chartHumidity, borderColor: '#2980B9', backgroundColor: 'rgba(41,128,185,0.1)', fill: true, tension: 0.3, pointRadius: 4, pointHoverRadius: 6 }] }, options: chartOptions });

        const feedCtx = document.getElementById('feedChart');
        if (feedCtx) feedChart = new Chart(feedCtx, { type: 'bar', data: { labels: chartLabels, datasets: [{ label: 'Feed (kg)', data: chartFeed, backgroundColor: '#E6B800', borderRadius: 6, maxBarThickness: 45 }] }, options: { ...chartOptions, scales: { y: { beginAtZero: true } } } });

        const waterCtx = document.getElementById('waterChart');
        if (waterCtx) waterChart = new Chart(waterCtx, { type: 'bar', data: { labels: chartLabels, datasets: [{ label: 'Water (L)', data: chartWater, backgroundColor: '#2980B9', borderRadius: 6, maxBarThickness: 45 }] }, options: { ...chartOptions, scales: { y: { beginAtZero: true } } } });
    }

    document.addEventListener('DOMContentLoaded', initCharts);
    window.addEventListener('resize', () => { if (tempChart) tempChart.resize(); if (humChart) humChart.resize(); if (feedChart) feedChart.resize(); if (waterChart) waterChart.resize(); });
</script>
</body>
</html>
