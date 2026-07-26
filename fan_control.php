<?php
// fan_control.php - Fan Control with Database (PDO)
session_start();

require_once 'db_connect.php';        // PDO
require_once 'weather_functions.php'; // weather

$weather = getWeatherData();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

// ===== GET USER ID (assuming admin = 1) =====
$userId = 1; // In production, get from session

// ===== GET REAL SENSOR DATA (from ESP32 via config.php) =====
// If you have espGetSensorData() function, use it; otherwise generate fake data
function getSensorData() {
    // In production, call espGetSensorData() from config.php
    // For now, generate realistic data
    $hour = (int)date('H');
    $baseTemp = 28;
    $tempVariation = sin(($hour - 14) * M_PI / 12) * 4;
    $currentTemp = $baseTemp + $tempVariation + mt_rand(-2, 2) / 10;
    $humidity = 55 + sin($hour * M_PI / 12) * 15 + mt_rand(-3, 3);
    return [
        'temperature' => round($currentTemp, 1),
        'humidity' => round($humidity, 1),
        'fan' => 0, // we'll manage fan status ourselves
        'pump' => 0,
        'light' => 0,
        'gate' => 0
    ];
}
$sensorData = getSensorData();
$currentTemp = $sensorData['temperature'];
$currentHumidity = $sensorData['humidity'];

// ===== FETCH FAN SETTINGS FROM DATABASE =====
global $pdo;
$settingsStmt = $pdo->prepare("SELECT * FROM fan_settings WHERE user_id = ?");
$settingsStmt->execute([$userId]);
$settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);

if (!$settingsRow) {
    // Insert default settings
    $pdo->prepare("INSERT INTO fan_settings (user_id, auto_mode, temp_threshold, humidity_threshold, schedule_start, schedule_end, fan_speed) VALUES (?, 'auto', 32.0, 75.0, '08:00:00', '20:00:00', 80)")->execute([$userId]);
    $settingsRow = [
        'auto_mode' => 'auto',
        'temp_threshold' => 32.0,
        'humidity_threshold' => 75.0,
        'schedule_start' => '08:00:00',
        'schedule_end' => '20:00:00',
        'fan_speed' => 80
    ];
}

$autoMode = ($settingsRow['auto_mode'] === 'auto');
$tempOn = (float)$settingsRow['temp_threshold'];
$humidityThreshold = (float)$settingsRow['humidity_threshold'];
$scheduleStart = $settingsRow['schedule_start'];
$scheduleEnd = $settingsRow['schedule_end'];
$fanSpeed = (int)$settingsRow['fan_speed'];

// ===== DETERMINE FAN STATUS (AUTO LOGIC) =====
$currentStatus = 'OFF'; // will be fetched from ESP32 or last known state
// For demo, we'll use session to store fan status and logs
if (!isset($_SESSION['fan_status'])) $_SESSION['fan_status'] = 'OFF';
if (!isset($_SESSION['fan_logs'])) $_SESSION['fan_logs'] = [];
if (!isset($_SESSION['manual_override'])) $_SESSION['manual_override'] = false;
if (!isset($_SESSION['fan_settings'])) $_SESSION['fan_settings'] = [
    'total_run_time' => 0,
    'current_run_start' => null,
    'last_activation' => null,
    'last_deactivation' => null,
    'updated_at' => date('Y-m-d H:i:s')
];

// Auto logic
function getAutoFanStatus($temp, $tempOn, $prevStatus) {
    if ($prevStatus === 'OFF' && $temp >= $tempOn) return 'ON';
    if ($prevStatus === 'ON' && $temp < ($tempOn - 2)) return 'OFF'; // hysteresis
    return $prevStatus;
}

$newStatus = $_SESSION['fan_status'];
if ($autoMode && !$_SESSION['manual_override']) {
    $newStatus = getAutoFanStatus($currentTemp, $tempOn, $_SESSION['fan_status']);
}

// If status changed, log to database
if ($newStatus !== $_SESSION['fan_status']) {
    // Log to fan_logs table
    $logStmt = $pdo->prepare("INSERT INTO fan_logs (user_id, action, trigger, temperature) VALUES (?, ?, ?, ?)");
    $trigger = $_SESSION['manual_override'] ? 'manual' : 'auto';
    $logStmt->execute([$userId, $newStatus, $trigger, $currentTemp]);

    // Update session
    $_SESSION['fan_status'] = $newStatus;
    array_unshift($_SESSION['fan_logs'], [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $newStatus,
        'trigger' => $trigger,
        'temperature' => $currentTemp
    ]);
    if (count($_SESSION['fan_logs']) > 100) array_pop($_SESSION['fan_logs']);

    // Update runtime stats
    if ($newStatus === 'ON') {
        $_SESSION['fan_settings']['current_run_start'] = time();
        $_SESSION['fan_settings']['last_activation'] = date('Y-m-d H:i:s');
    } else {
        if ($_SESSION['fan_settings']['current_run_start']) {
            $dur = time() - $_SESSION['fan_settings']['current_run_start'];
            $_SESSION['fan_settings']['total_run_time'] += $dur;
            $_SESSION['fan_settings']['current_run_start'] = null;
        }
        $_SESSION['fan_settings']['last_deactivation'] = date('Y-m-d H:i:s');
    }
    $_SESSION['fan_settings']['updated_at'] = date('Y-m-d H:i:s');
}

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'toggle_fan') {
        $status = $_POST['status'] ?? 'OFF';
        // Update database log
        $logStmt = $pdo->prepare("INSERT INTO fan_logs (user_id, action, trigger, temperature) VALUES (?, ?, 'manual', ?)");
        $logStmt->execute([$userId, $status, $currentTemp]);
        $_SESSION['fan_status'] = $status;
        $_SESSION['manual_override'] = true;
        array_unshift($_SESSION['fan_logs'], [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $status,
            'trigger' => 'manual',
            'temperature' => $currentTemp
        ]);
        $response = ['success' => true, 'message' => "Fan turned $status"];
    }
    elseif ($action === 'update_settings') {
        $autoMode = $_POST['auto_mode'] === 'true';
        $tempOn = floatval($_POST['temp_on'] ?? 32);
        $humidityThreshold = floatval($_POST['humidity_threshold'] ?? 75);
        $fanSpeed = intval($_POST['fan_speed'] ?? 80);
        $scheduleStart = $_POST['schedule_start'] ?? '08:00:00';
        $scheduleEnd = $_POST['schedule_end'] ?? '20:00:00';

        $updateStmt = $pdo->prepare("UPDATE fan_settings SET auto_mode = ?, temp_threshold = ?, humidity_threshold = ?, schedule_start = ?, schedule_end = ?, fan_speed = ? WHERE user_id = ?");
        $updateStmt->execute([$autoMode ? 'auto' : 'manual', $tempOn, $humidityThreshold, $scheduleStart, $scheduleEnd, $fanSpeed, $userId]);
        $_SESSION['manual_override'] = false;
        $response = ['success' => true, 'message' => 'Settings saved'];
    }
    elseif ($action === 'reset_override') {
        $_SESSION['manual_override'] = false;
        $response = ['success' => true, 'message' => 'Auto mode restored'];
    }
    elseif ($action === 'clear_logs') {
        $pdo->prepare("DELETE FROM fan_logs WHERE user_id = ?")->execute([$userId]);
        $_SESSION['fan_logs'] = [];
        $response = ['success' => true, 'message' => 'Logs cleared'];
    }
    echo json_encode($response);
    exit;
}

// ===== FETCH LOGS FROM DATABASE (for display) =====
$logStmt = $pdo->prepare("SELECT * FROM fan_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 100");
$logStmt->execute([$userId]);
$dbLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
// Merge with session logs (if any) - but we'll use session logs for display consistency
$logs = $_SESSION['fan_logs']; // or $dbLogs

// ===== VARIABLES FOR VIEW =====
$fanStatus = $_SESSION['fan_status'];
$isManualOverride = $_SESSION['manual_override'];
$settings = $_SESSION['fan_settings'];
$totalRunTimeHours = round($settings['total_run_time'] / 3600, 1);
$currentRunMinutes = ($fanStatus === 'ON' && $settings['current_run_start']) ? round((time() - $settings['current_run_start']) / 60) : 0;
$autoReason = ($autoMode && !$isManualOverride) ? ($fanStatus === 'ON' ? "{$currentTemp}°C ≥ {$tempOn}°C" : "{$currentTemp}°C < {$tempOn}°C") : '';

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fan Control | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Shared CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ===== DIRECT CSS VARIABLES (FALLBACK) ===== */
        :root {
            --bg-primary: #F5F5F5; --bg-secondary: #E8F0E8; --bg-card: #FFFFFF;
            --text-primary: #2C3E2C; --text-secondary: #4D724D; --text-muted: #6B8A6B;
            --accent: #8DB48E; --accent-dark: #4D724D; --accent-light: #D4E8D4;
            --sidebar-bg: #3A5C3A; --sidebar-text: #F5F5F5; --sidebar-muted: #A8C8A8;
            --green: #4D724D; --green-light: #D4E8D4;
            --yellow: #C8A24A; --yellow-light: #F4EEDC;
            --red: #A44A3F; --red-light: #F6E9E7;
            --blue: #4F6C7A; --blue-light: #EAF0F3;
            --purple: #8E44AD;
            --sidebar-width: 280px; --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; }

        /* ===== SIDEBAR (gaya ng detection_history) ===== */
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

        .current-readings { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-bottom: 1.5rem; }
        .reading-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.15); text-align: center; transition: all 0.3s; }
        .reading-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .card-icon { font-size: 1.8rem; margin-bottom: 0.3rem; }
        .card-value { font-size: 1.6rem; font-weight: 800; }
        .card-label { font-size: 0.75rem; color: var(--text-muted); }
        .status-badge { display: inline-block; padding: 0.2rem 0.9rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin: 0.3rem 0; }
        .status-badge.on { background: var(--green-light); color: var(--green); }
        .status-badge.off { background: var(--red-light); color: var(--red); }
        .fan-icon-display { font-size: 2.8rem; display: block; margin: 0.1rem 0; transition: all 0.3s; }
        .fan-icon-display.spin { animation: spin 2s linear infinite; }
        .fan-icon-display.off { color: #B0A890; animation: none; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .toggle-btn {
            padding: 0.4rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.3rem;
            font-family: 'Inter', sans-serif;
        }
        .toggle-btn.on { background: var(--red); color: white; }
        .toggle-btn.off { background: var(--green); color: white; }
        .toggle-btn.on:hover { background: #A44A3F; }
        .toggle-btn.off:hover { background: #3A5C3A; }
        .mode-badge { display: inline-block; padding: 0.2rem 0.9rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin: 0.2rem 0; }
        .mode-badge.auto { background: var(--blue-light); color: var(--blue); }
        .mode-badge.manual { background: var(--yellow-light); color: var(--yellow); }

        .stats-mini-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .stat-mini { background: var(--bg-card); border-radius: 10px; padding: 0.7rem 0.8rem; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.10); }
        .stat-mini .stat-value { font-size: 1.1rem; font-weight: 700; }
        .stat-mini .stat-label { font-size: 0.65rem; color: var(--text-muted); margin-top: 0.1rem; }

        .settings-panel { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; margin-bottom: 1.2rem; border: 1px solid rgba(141,180,142,0.10); box-shadow: var(--shadow-sm); }
        .settings-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .mode-selector { display: flex; gap: 0.8rem; margin-bottom: 1rem; }
        .mode-btn { flex: 1; padding: 0.5rem; border: 2px solid rgba(141,180,142,0.2); background: white; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s; text-align: center; color: var(--text-secondary); font-family: 'Inter', sans-serif; font-size: 0.8rem; }
        .mode-btn.active { border-color: var(--accent); background: var(--accent-light); color: var(--accent-dark); }
        .threshold-sliders { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem; }
        .threshold-item { background: var(--bg-secondary); padding: 0.8rem 1rem; border-radius: 10px; }
        .threshold-label { display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.8rem; }
        input[type="range"] { width: 100%; height: 5px; -webkit-appearance: none; background: #E0D5C0; border-radius: 5px; outline: none; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; background: var(--accent); border-radius: 50%; cursor: pointer; }
        .save-btn { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.6rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; width: 100%; font-size: 0.85rem; color: #FFFFFF; font-family: 'Inter', sans-serif; }
        .save-btn:hover { background: linear-gradient(105deg, #3A5C3A, var(--accent-dark)); }

        .log-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; border: 1px solid rgba(141,180,142,0.10); box-shadow: var(--shadow-sm); }
        .log-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
        .log-list { max-height: 280px; overflow-y: auto; }
        .log-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid rgba(141,180,142,0.06); flex-wrap: wrap; gap: 0.2rem; }
        .log-entry:last-child { border-bottom: none; }
        .log-time { font-size: 0.65rem; color: var(--text-muted); }
        .log-badge { padding: 0.1rem 0.5rem; border-radius: 20px; font-size: 0.6rem; font-weight: 600; display: inline-block; }
        .log-auto { background: var(--blue-light); color: var(--blue); }
        .log-manual { background: var(--accent-light); color: var(--accent-dark); }
        .log-on { background: var(--green-light); color: var(--green); }
        .log-off { background: var(--red-light); color: var(--red); }
        .log-temp { font-size: 0.65rem; color: var(--text-muted); }
        .clear-btn { background: none; border: 1px solid var(--red); color: var(--red); padding: 0.2rem 0.8rem; border-radius: 30px; cursor: pointer; font-size: 0.65rem; font-family: 'Inter', sans-serif; transition: all 0.2s; }
        .clear-btn:hover { background: var(--red-light); }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .current-readings { grid-template-columns: 1fr 1fr; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .current-readings { grid-template-columns: 1fr; }
            .stats-mini-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
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
            <a href="detection_results.php"><i class="fas fa-brain"></i> Detection Results</a>
            <a href="detection_history.php"><i class="fas fa-history"></i> Detection History</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Automation</div>
            <a href="fan_control.php" class="active"><i class="fas fa-fan"></i> Fan Control</a>
            <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
             <a href="light_control.php" class="active"><i class="fas fa-lightbulb"></i> Light Control</a>
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
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
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
        <h1 class="page-title"><i class="fas fa-fan" style="color:var(--accent);"></i> Fan Control</h1>
        <p class="page-subtitle">Temperature-based ventilation automation with manual override</p>

        <!-- Current Readings -->
        <div class="current-readings">
            <div class="reading-card">
                <i class="fas fa-fan fan-icon-display <?php echo $fanStatus === 'ON' ? 'spin' : 'off'; ?>" style="font-size:2.8rem; display:block; margin:0 auto;"></i>
                <div class="card-value"><?php echo $fanStatus === 'ON' ? 'RUNNING' : 'OFF'; ?></div>
                <div class="card-label">Exhaust Fan</div>
                <span class="status-badge <?php echo strtolower($fanStatus); ?>">
                    <i class="fas <?php echo $fanStatus === 'ON' ? 'fa-play' : 'fa-stop'; ?>"></i> <?php echo $fanStatus === 'ON' ? 'Active' : 'Inactive'; ?>
                </span>
                <?php if ($isManualOverride): ?>
                    <div style="margin-top:0.3rem; font-size:0.7rem; color:var(--yellow);"><i class="fas fa-hand-paper"></i> Manual Override</div>
                <?php endif; ?>
                <div>
                    <button class="toggle-btn <?php echo strtolower($fanStatus); ?>" onclick="toggleFan('<?php echo $fanStatus; ?>')">
                        <i class="fas <?php echo $fanStatus === 'ON' ? 'fa-power-off' : 'fa-play'; ?>"></i>
                        <?php echo $fanStatus === 'ON' ? 'Turn OFF' : 'Turn ON'; ?>
                    </button>
                </div>
            </div>

            <div class="reading-card" style="border-top: 3px solid #E67E22;">
                <i class="fas fa-thermometer-half card-icon" style="color:#E67E22;"></i>
                <div class="card-value" style="color:#E67E22;"><?php echo $currentTemp; ?>°C</div>
                <div class="card-label">Current Temperature</div>
                <div style="font-size:0.75rem; color:var(--text-secondary);"><i class="fas fa-tint"></i> Humidity: <?php echo $currentHumidity; ?>%</div>
                <?php if ($autoMode && !$isManualOverride): ?>
                    <div style="margin-top:0.5rem; background:var(--blue-light); border-radius:8px; padding:0.3rem 0.6rem; font-size:0.7rem; color:var(--text-secondary);">
                        <i class="fas fa-robot"></i> <?php echo $autoReason; ?>
                    </div>
                    <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.2rem;">
                        ON ≥ <?php echo $tempOn; ?>°C · OFF < <?php echo $tempOn; ?>°C
                    </div>
                <?php elseif ($isManualOverride): ?>
                    <div style="margin-top:0.5rem; background:var(--yellow-light); border-radius:8px; padding:0.3rem 0.6rem; font-size:0.7rem; color:var(--text-secondary);">
                        <i class="fas fa-hand-paper"></i> Manual control
                        <br><button class="reset-link" onclick="resetToAuto()" style="background:none; border:none; color:var(--blue); cursor:pointer; text-decoration:underline; font-size:0.7rem; font-family:'Inter', sans-serif;">Reset to Auto</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="reading-card" style="border-top: 3px solid var(--blue);">
                <i class="fas fa-microchip card-icon" style="color:var(--blue);"></i>
                <div class="card-value" style="color:var(--blue);"><?php echo $autoMode ? 'AUTO' : 'MANUAL'; ?></div>
                <div class="card-label">Operation Mode</div>
                <span class="mode-badge <?php echo $autoMode ? 'auto' : 'manual'; ?>">
                    <i class="fas <?php echo $autoMode ? 'fa-robot' : 'fa-hand-paper'; ?>"></i>
                    <?php echo $autoMode ? 'Automatic' : 'Manual'; ?>
                </span>
                <div style="font-size:0.6rem; color:var(--text-muted); margin-top:0.2rem;">Updated: <?php echo date('M d, h:i A', strtotime($settings['updated_at'] ?? 'now')); ?></div>
            </div>
        </div>

        <!-- Stats Mini -->
        <div class="stats-mini-grid">
            <div class="stat-mini"><div class="stat-value" style="color:var(--accent-dark);"><?php echo $totalRunTimeHours; ?> hrs</div><div class="stat-label">Total Runtime</div></div>
            <div class="stat-mini"><div class="stat-value" style="color:var(--green);"><?php echo $currentRunMinutes > 0 ? $currentRunMinutes . ' min' : '—'; ?></div><div class="stat-label">Current Run</div></div>
            <div class="stat-mini"><div class="stat-value" style="color:var(--blue);"><?php echo isset($settings['last_activation']) ? date('h:i A', strtotime($settings['last_activation'])) : '—'; ?></div><div class="stat-label">Last Activated</div></div>
            <div class="stat-mini"><div class="stat-value" style="color:var(--red);"><?php echo isset($settings['last_deactivation']) ? date('h:i A', strtotime($settings['last_deactivation'])) : '—'; ?></div><div class="stat-label">Last Deactivated</div></div>
        </div>

        <!-- Settings Panel -->
        <div class="settings-panel">
            <div class="settings-title"><i class="fas fa-sliders-h"></i> Temperature Automation</div>
            <div class="mode-selector">
                <button class="mode-btn <?php echo $autoMode ? 'active' : ''; ?>" onclick="selectMode(true)">Automatic</button>
                <button class="mode-btn <?php echo !$autoMode ? 'active' : ''; ?>" onclick="selectMode(false)">Manual</button>
            </div>
            <div class="threshold-sliders">
                <div class="threshold-item">
                    <div class="threshold-label"><span><i class="fas fa-power-off" style="color:var(--green);"></i> Turn ON when temperature reaches</span><span id="tempOnValue"><?php echo $tempOn; ?>°C</span></div>
                    <input type="range" id="tempOn" min="20" max="45" step="0.5" value="<?php echo $tempOn; ?>" oninput="updateTempValues()">
                </div>
                <div class="threshold-item">
                    <div class="threshold-label"><span><i class="fas fa-stop-circle" style="color:var(--red);"></i> Turn OFF when temperature drops below</span><span id="tempOffValue"><?php echo $tempOn - 2; ?>°C</span></div>
                    <input type="range" id="tempOff" min="18" max="43" step="0.5" value="<?php echo $tempOn - 2; ?>" oninput="updateTempValues()">
                </div>
                <div class="threshold-item">
                    <div class="threshold-label"><span><i class="fas fa-tint"></i> Humidity Threshold</span><span id="humidityValue"><?php echo $humidityThreshold; ?>%</span></div>
                    <input type="range" id="humidityThreshold" min="50" max="90" step="1" value="<?php echo $humidityThreshold; ?>" oninput="document.getElementById('humidityValue').innerText = this.value + '%'">
                </div>
                <div class="threshold-item">
                    <div class="threshold-label"><span><i class="fas fa-clock"></i> Schedule (Start / End)</span></div>
                    <div style="display:flex; gap:1rem; align-items:center;">
                        <input type="time" id="scheduleStart" value="<?php echo $scheduleStart; ?>" style="padding:0.3rem; border-radius:8px; border:1px solid #E0D5C0; background:var(--bg-secondary); font-family:'Inter', sans-serif;">
                        <span>to</span>
                        <input type="time" id="scheduleEnd" value="<?php echo $scheduleEnd; ?>" style="padding:0.3rem; border-radius:8px; border:1px solid #E0D5C0; background:var(--bg-secondary); font-family:'Inter', sans-serif;">
                    </div>
                </div>
                <div class="threshold-item">
                    <div class="threshold-label"><span><i class="fas fa-gauge-high"></i> Fan Speed</span><span id="speedValue"><?php echo $fanSpeed; ?>%</span></div>
                    <input type="range" id="fanSpeed" min="30" max="100" step="5" value="<?php echo $fanSpeed; ?>" oninput="document.getElementById('speedValue').innerText = this.value + '%'">
                </div>
            </div>
            <button class="save-btn" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button>
        </div>

        <!-- Activity Log -->
        <div class="log-card">
            <div class="log-header">
                <div class="settings-title"><i class="fas fa-history"></i> Activity Log</div>
                <div>
                    <button class="clear-btn" onclick="clearLogs()"><i class="fas fa-trash-alt"></i> Clear</button>
                    <span style="font-size:0.6rem;color:var(--text-muted);margin-left:0.4rem;">Last 100</span>
                </div>
            </div>
            <div class="log-list">
                <?php if (empty($logs)): ?>
                    <div style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                        <i class="fas fa-inbox" style="font-size:1.5rem; display:block; margin-bottom:0.3rem;"></i>
                        No activity recorded
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <span class="log-time"><?php echo isset($log['timestamp']) ? date('M d, h:i:s A', strtotime($log['timestamp'])) : '—'; ?></span>
                        <span>
                            <span class="log-badge log-<?php echo strtolower($log['action'] ?? ''); ?>">
                                <i class="fas <?php echo ($log['action'] ?? '') === 'ON' ? 'fa-play' : 'fa-stop'; ?>"></i> <?php echo $log['action'] ?? '—'; ?>
                            </span>
                            <span class="log-badge log-<?php echo $log['trigger'] ?? 'auto'; ?>">
                                <i class="fas <?php echo ($log['trigger'] ?? '') === 'auto' ? 'fa-robot' : 'fa-hand-paper'; ?>"></i> <?php echo ucfirst($log['trigger'] ?? 'auto'); ?>
                            </span>
                        </span>
                        <span class="log-temp"><?php echo isset($log['temperature']) ? $log['temperature'] : '—'; ?>°C</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    let selectedAutoMode = <?php echo $autoMode ? 'true' : 'false'; ?>;
    let tempOn = <?php echo $tempOn; ?>;
    let tempOff = <?php echo $tempOn - 2; ?>;

    function updateTempValues() {
        let onVal = parseFloat(document.getElementById('tempOn').value);
        let offVal = parseFloat(document.getElementById('tempOff').value);
        document.getElementById('tempOnValue').innerText = onVal + '°C';
        document.getElementById('tempOffValue').innerText = offVal + '°C';
        if (onVal <= offVal) {
            document.getElementById('tempOnValue').style.color = '#A44A3F';
            document.getElementById('tempOffValue').style.color = '#A44A3F';
        } else {
            document.getElementById('tempOnValue').style.color = '';
            document.getElementById('tempOffValue').style.color = '';
        }
        tempOn = onVal;
        tempOff = offVal;
    }

    function selectMode(autoMode) {
        selectedAutoMode = autoMode;
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        if (autoMode) document.querySelector('.mode-btn:first-child').classList.add('active');
        else document.querySelector('.mode-btn:last-child').classList.add('active');
    }

    async function saveSettings() {
        const onVal = document.getElementById('tempOn').value;
        const offVal = document.getElementById('tempOff').value;
        if (parseFloat(onVal) <= parseFloat(offVal)) {
            alert('ON temperature must be higher than OFF temperature.');
            return;
        }
        const humidity = document.getElementById('humidityThreshold').value;
        const speed = document.getElementById('fanSpeed').value;
        const start = document.getElementById('scheduleStart').value;
        const end = document.getElementById('scheduleEnd').value;

        try {
            const response = await fetch('fan_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_settings&auto_mode=${selectedAutoMode}&temp_on=${onVal}&temp_off=${offVal}&humidity_threshold=${humidity}&fan_speed=${speed}&schedule_start=${start}&schedule_end=${end}`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error saving settings', true);
        }
    }

    async function toggleFan(currentStatus) {
        const newStatus = currentStatus === 'ON' ? 'OFF' : 'ON';
        try {
            const response = await fetch('fan_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=toggle_fan&status=${newStatus}`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error controlling fan', true);
        }
    }

    async function resetToAuto() {
        try {
            const response = await fetch('fan_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=reset_override`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 500);
            }
        } catch (error) {
            showToast('Error resetting to auto mode', true);
        }
    }

    async function clearLogs() {
        if (!confirm('Clear all activity logs?')) return;
        try {
            const response = await fetch('fan_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=clear_logs`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 500);
            }
        } catch (error) {
            showToast('Error clearing logs', true);
        }
    }

    function showToast(message, isError) {
        const toast = document.getElementById('toast');
        if (!toast) {
            const div = document.createElement('div');
            div.id = 'toast';
            div.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#4D724D; color:white; padding:0.6rem 1.2rem; border-radius:10px; display:flex; align-items:center; gap:0.6rem; z-index:2000; animation:slideIn 0.3s ease; font-size:0.8rem; box-shadow:0 8px 24px rgba(0,0,0,0.15);';
            if (isError) div.style.background = '#A44A3F';
            div.innerHTML = `<i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'}"></i><span>${message}</span>`;
            document.body.appendChild(div);
            setTimeout(() => div.remove(), 3000);
        } else {
            toast.innerHTML = `<i class="fas fa-${isError ? 'exclamation-circle' : 'check-circle'}"></i><span>${message}</span>`;
            toast.style.display = 'flex';
            if (isError) toast.style.background = '#A44A3F';
            else toast.style.background = '#4D724D';
            setTimeout(() => toast.style.display = 'none', 3000);
        }
    }

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'fan_control.php?refresh_weather=1'; }
    document.getElementById('weatherModal').addEventListener('click', function(e) { if (e.target === this) closeWeatherModal(); });

    setInterval(updateDateTime, 1000);
    updateDateTime();
    updateTempValues();
    window.addEventListener('load', function() {
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
    });
</script>
</body>
</html>