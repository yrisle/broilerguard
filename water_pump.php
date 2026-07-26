<?php
// water_pump.php - Water Pump Control with Database (PDO)
session_start();

require_once 'db_connect.php';        // PDO
require_once 'weather_functions.php'; // weather

$weather = getWeatherData();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$userId = 1;

// ===== FETCH PUMP SETTINGS =====
global $pdo;
$settingsStmt = $pdo->prepare("SELECT * FROM pump_settings WHERE user_id = ?");
$settingsStmt->execute([$userId]);
$settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);

if (!$settingsRow) {
    $pdo->prepare("INSERT INTO pump_settings (user_id, auto_mode, low_level_threshold, high_level_threshold, pump_duration, schedule_interval) VALUES (?, 'auto', 25, 95, 45, 3)")->execute([$userId]);
    $settingsStmt->execute([$userId]);
    $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);
}

$autoMode = ($settingsRow['auto_mode'] === 'auto');
$lowThreshold = (int)$settingsRow['low_level_threshold'];
$highThreshold = (int)$settingsRow['high_level_threshold'];
$pumpDuration = (int)$settingsRow['pump_duration'];
$scheduleInterval = (int)$settingsRow['schedule_interval'];

// ===== FETCH WATER INVENTORY =====
$invStmt = $pdo->prepare("SELECT current_level, capacity FROM water_inventory WHERE user_id = ?");
$invStmt->execute([$userId]);
$inv = $invStmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
    $pdo->prepare("INSERT INTO water_inventory (user_id, current_level, capacity) VALUES (?, 1500, 2000)")->execute([$userId]);
    $invStmt->execute([$userId]);
    $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
}
$currentLevel = (float)$inv['current_level'];
$capacity = (float)$inv['capacity'];
$waterPercentage = ($capacity > 0) ? ($currentLevel / $capacity) * 100 : 0;

// ===== FETCH WATER SCHEDULES =====
$schStmt = $pdo->prepare("SELECT * FROM water_schedules WHERE user_id = ? ORDER BY time");
$schStmt->execute([$userId]);
$schedules = $schStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($schedules)) {
    $defaults = [
        ['06:00:00', 30, 'Morning Watering'],
        ['12:00:00', 25, 'Afternoon Watering'],
        ['18:00:00', 30, 'Evening Watering']
    ];
    foreach ($defaults as $d) {
        $pdo->prepare("INSERT INTO water_schedules (user_id, time, duration, label) VALUES (?, ?, ?, ?)")->execute([$userId, $d[0], $d[1], $d[2]]);
    }
    $schStmt->execute([$userId]);
    $schedules = $schStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== FETCH WATER TRANSACTIONS (logs) =====
$logStmt = $pdo->prepare("SELECT * FROM water_transactions WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
$logStmt->execute([$userId]);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== SESSION INIT FOR PUMP STATUS =====
if (!isset($_SESSION['pump_status'])) $_SESSION['pump_status'] = 'OFF';
if (!isset($_SESSION['manual_override'])) $_SESSION['manual_override'] = false;
if (!isset($_SESSION['auto_watering_enabled'])) $_SESSION['auto_watering_enabled'] = true;

// ===== AUTO LOGIC =====
function getAutoPumpStatus($waterLevel, $lowThreshold, $highThreshold, $prevStatus) {
    if ($prevStatus === 'OFF' && $waterLevel < $lowThreshold) return 'ON';
    if ($prevStatus === 'ON' && $waterLevel > $highThreshold) return 'OFF';
    return $prevStatus;
}

$newStatus = $_SESSION['pump_status'];
if ($autoMode && !$_SESSION['manual_override'] && $_SESSION['auto_watering_enabled']) {
    $newStatus = getAutoPumpStatus($waterPercentage, $lowThreshold, $highThreshold, $_SESSION['pump_status']);
}

// If status changed, log to database
if ($newStatus !== $_SESSION['pump_status']) {
    $trigger = $_SESSION['manual_override'] ? 'manual' : 'auto';
    // Log to water_transactions
    $logStmt = $pdo->prepare("INSERT INTO water_transactions (user_id, type, amount, source, notes, timestamp) VALUES (?, 'consumption', ?, ?, ?, NOW())");
    $logStmt->execute([$userId, 0, $trigger, 'Pump ' . $newStatus]);
    $_SESSION['pump_status'] = $newStatus;
}

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'toggle_pump') {
        $status = $_POST['status'] ?? 'OFF';
        $_SESSION['pump_status'] = $status;
        $_SESSION['manual_override'] = true;
        $pdo->prepare("INSERT INTO water_transactions (user_id, type, amount, source, notes, timestamp) VALUES (?, 'consumption', ?, 'manual', ?, NOW())")->execute([$userId, 0, 'Pump toggled ' . $status]);
        $response = ['success' => true, 'message' => "Pump turned $status"];
    }
    elseif ($action === 'manual_water') {
        $duration = intval($_POST['duration'] ?? 30);
        $waterAmount = round($duration * 0.5, 1);
        $newLevel = max(0, $currentLevel - $waterAmount);
        $pdo->prepare("UPDATE water_inventory SET current_level = ? WHERE user_id = ?")->execute([$newLevel, $userId]);
        $pdo->prepare("INSERT INTO water_transactions (user_id, type, amount, source, notes, remaining, timestamp) VALUES (?, 'consumption', ?, 'manual', 'Manual release', ?, NOW())")->execute([$userId, $waterAmount, $newLevel]);
        $_SESSION['pump_status'] = 'ON';
        $_SESSION['manual_override'] = true;
        $response = ['success' => true, 'message' => "Released {$waterAmount} L of water", 'water_amount' => $waterAmount];
    }
    elseif ($action === 'pump_off') {
        $duration = intval($_POST['duration'] ?? 0);
        $waterAmount = round($duration * 0.5, 1);
        if ($waterAmount > 0) {
            $newLevel = max(0, $currentLevel - $waterAmount);
            $pdo->prepare("UPDATE water_inventory SET current_level = ? WHERE user_id = ?")->execute([$newLevel, $userId]);
        }
        $_SESSION['pump_status'] = 'OFF';
        $response = ['success' => true, 'message' => 'Pump turned off'];
    }
    elseif ($action === 'update_schedule') {
        $schedulesData = json_decode($_POST['schedules'] ?? '[]', true);
        $pdo->prepare("DELETE FROM water_schedules WHERE user_id = ?")->execute([$userId]);
        foreach ($schedulesData as $sch) {
            if (isset($sch['time']) && isset($sch['duration']) && isset($sch['label'])) {
                $ins = $pdo->prepare("INSERT INTO water_schedules (user_id, time, duration, label) VALUES (?, ?, ?, ?)");
                $ins->execute([$userId, $sch['time'], $sch['duration'], $sch['label']]);
            }
        }
        $response = ['success' => true, 'message' => 'Schedules updated'];
    }
    elseif ($action === 'toggle_auto') {
        $enabled = $_POST['enabled'] === 'true';
        $_SESSION['auto_watering_enabled'] = $enabled;
        if (!$enabled) $_SESSION['manual_override'] = true;
        else $_SESSION['manual_override'] = false;
        $response = ['success' => true, 'message' => $enabled ? 'Auto enabled' : 'Auto disabled'];
    }
    elseif ($action === 'reset_override') {
        $_SESSION['manual_override'] = false;
        $response = ['success' => true, 'message' => 'Auto mode restored'];
    }
    echo json_encode($response);
    exit;
}

// ===== COMPUTE STATS =====
$todayUsage = 0;
$totalUsage = 0;
$scheduleCount = 0;
$manualCount = 0;

foreach ($logs as $log) {
    if (date('Y-m-d', strtotime($log['timestamp'])) === date('Y-m-d')) $todayUsage += $log['amount'];
    $totalUsage += $log['amount'];
    if ($log['source'] === 'auto_pump') $scheduleCount++;
    if ($log['source'] === 'manual') $manualCount++;
}

$pumpStatus = $_SESSION['pump_status'];
$autoEnabled = $_SESSION['auto_watering_enabled'];
$isManualOverride = $_SESSION['manual_override'];

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Pump Control | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .status-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-bottom: 1.5rem; }
        .status-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.15); display: flex; align-items: center; gap: 1.2rem; transition: all 0.3s; }
        .status-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--accent); }
        .status-card .card-icon { font-size: 2.2rem; flex-shrink: 0; }
        .status-card .card-icon.gold { color: var(--accent); }
        .status-card .card-icon.green { color: var(--green); }
        .status-card .card-icon.blue { color: var(--blue); }
        .status-card .card-info { flex: 1; }
        .status-card .card-info .value { font-size: 1.8rem; font-weight: 800; line-height: 1.2; }
        .status-card .card-info .value.gold { color: var(--accent); }
        .status-card .card-info .value.green { color: var(--green); }
        .status-card .card-info .value.blue { color: var(--blue); }
        .status-card .card-info .label { font-size: 0.8rem; color: var(--text-muted); }

        .status-badge { display: inline-block; padding: 0.15rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.running { background: var(--green-light); color: var(--green); }
        .status-badge.idle { background: #E0E0E0; color: #757575; }
        .status-badge.auto { background: var(--blue-light); color: var(--blue); }
        .status-badge.manual { background: var(--yellow-light); color: var(--yellow); }

        .stats-mini-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-mini-card { background: var(--bg-card); border-radius: 12px; padding: 0.8rem 1rem; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.10); transition: all 0.3s; }
        .stat-mini-card:hover { transform: translateY(-2px); }
        .stat-mini-card .stat-mini-value { font-size: 1.3rem; font-weight: 700; }
        .stat-mini-card .stat-mini-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.1rem; }

        .auto-banner {
            background: linear-gradient(135deg, var(--accent-dark), #3A5C3A);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .auto-banner .banner-left { display: flex; align-items: center; gap: 1rem; }
        .auto-banner .banner-icon { font-size: 2rem; color: var(--accent); }
        .auto-banner .banner-title { font-size: 1rem; font-weight: 600; }
        .auto-banner .banner-sub { font-size: 0.8rem; opacity: 0.8; }
        .auto-badge { display: inline-block; padding: 0.2rem 1rem; border-radius: 30px; font-size: 0.7rem; font-weight: 600; margin-top: 0.2rem; }
        .auto-badge.enabled { background: var(--green); color: white; }
        .auto-badge.disabled { background: var(--red); color: white; }
        .override-badge { display: inline-block; background: var(--orange); color: white; padding: 0.15rem 0.6rem; border-radius: 30px; font-size: 0.65rem; font-weight: 600; margin-top: 0.2rem; }

        .toggle-switch-large { position: relative; display: inline-block; width: 52px; height: 26px; flex-shrink: 0; }
        .toggle-switch-large input { opacity: 0; width: 0; height: 0; }
        .toggle-slider-large { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #5A5A5A; transition: 0.2s; border-radius: 26px; }
        .toggle-slider-large:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: 0.2s; border-radius: 50%; }
        input:checked + .toggle-slider-large { background-color: var(--accent); }
        input:checked + .toggle-slider-large:before { transform: translateX(26px); }
        .reset-auto-btn { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.5); color: white; padding: 0.2rem 0.8rem; border-radius: 30px; cursor: pointer; font-size: 0.7rem; font-family: 'Inter', sans-serif; transition: 0.2s; }
        .reset-auto-btn:hover { background: rgba(255,255,255,0.3); }

        .manual-control-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(141,180,142,0.10);
            box-shadow: var(--shadow-sm);
        }
        .manual-control-card h3 { font-size: 0.95rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }

        .water-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .water-btn {
            background: var(--bg-secondary);
            border: 1px solid rgba(141,180,142,0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: 'Inter', sans-serif;
        }
        .water-btn:hover { background: var(--accent); color: white; }
        .custom-water-btn {
            background: linear-gradient(105deg, var(--accent-dark), #3A5C3A);
            border: none;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.8rem;
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .custom-water-btn:hover { background: linear-gradient(105deg, #3A5C3A, var(--accent-dark)); }

        .pump-status-row { display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(141,180,142,0.08); }
        .pump-status-row .pump-icon { font-size: 2rem; }
        .pump-status-row .pump-icon.running { color: var(--green); animation: pulse 1.5s infinite; }
        .pump-status-row .pump-icon.idle { color: #B0A890; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .pump-status-row .pump-info .pump-label { font-size: 0.7rem; color: var(--text-muted); }
        .pump-status-row .pump-info .pump-value { font-weight: 700; font-size: 1rem; }

        .schedule-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(141,180,142,0.10);
            box-shadow: var(--shadow-sm);
        }
        .schedule-card h3 { font-size: 0.95rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }

        .schedule-form { display: flex; gap: 0.8rem; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 120px; }
        .form-group label { font-size: 0.65rem; font-weight: 600; display: block; margin-bottom: 0.2rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid rgba(141,180,142,0.2);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        .btn-add-schedule {
            background: linear-gradient(105deg, var(--accent-dark), #3A5C3A);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: #FFFFFF;
            font-size: 0.8rem;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-add-schedule:hover { background: linear-gradient(105deg, #3A5C3A, var(--accent-dark)); }

        .schedule-table-wrap {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid rgba(141,180,142,0.10);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .schedule-table { width: 100%; border-collapse: collapse; }
        .schedule-table th { text-align: left; padding: 0.6rem 1rem; background: var(--bg-secondary); font-weight: 600; font-size: 0.75rem; color: var(--text-secondary); border-bottom: 2px solid rgba(141,180,142,0.15); }
        .schedule-table td { padding: 0.6rem 1rem; border-bottom: 1px solid rgba(141,180,142,0.06); vertical-align: middle; font-size: 0.85rem; }
        .schedule-table tr:last-child td { border-bottom: none; }
        .schedule-time { font-weight: 700; }
        .schedule-duration { font-weight: 600; color: var(--accent-dark); }
        .schedule-label { background: var(--accent-light); padding: 0.15rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 500; display: inline-block; }
        .schedule-actions { display: flex; gap: 0.3rem; justify-content: flex-end; }
        .btn-edit, .btn-delete { background: none; border: none; cursor: pointer; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 6px; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .btn-edit { background: var(--blue-light); color: var(--blue); }
        .btn-delete { background: var(--red-light); color: var(--red); }
        .btn-edit:hover, .btn-delete:hover { opacity: 0.8; }

        .log-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            border: 1px solid rgba(141,180,142,0.10);
            box-shadow: var(--shadow-sm);
        }
        .log-card h3 { font-size: 0.95rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        .log-list { max-height: 300px; overflow-y: auto; }
        .log-list::-webkit-scrollbar { width: 4px; }
        .log-list::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
        .log-list::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }
        .log-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(141,180,142,0.06); flex-wrap: wrap; gap: 0.3rem; }
        .log-entry:last-child { border-bottom: none; }
        .log-badge { padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.2rem; }
        .log-schedule { background: var(--blue-light); color: var(--blue); }
        .log-manual { background: var(--accent-light); color: var(--accent-dark); }
        .log-time { font-size: 0.65rem; color: var(--text-muted); }
        .log-amount { font-weight: 600; font-size: 0.8rem; }
        .log-trigger { font-size: 0.65rem; color: var(--text-muted); margin-left: 0.2rem; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 10px; padding: 2rem; max-width: 380px; width: 90%; text-align: center; border: 1px solid rgba(141,180,142,0.10); }
        .modal-content .modal-icon { font-size: 2.5rem; color: var(--accent); margin-bottom: 0.5rem; }
        .modal-content h3 { font-size: 1.1rem; margin-bottom: 0.5rem; }
        .modal-content p { font-size: 0.9rem; color: var(--text-secondary); }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center; }
        .modal-confirm { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; color: #FFFFFF; font-family: 'Inter', sans-serif; }
        .modal-confirm:hover { background: linear-gradient(105deg, #3A5C3A, var(--accent-dark)); }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }

        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.7rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.8rem; box-shadow: var(--shadow-md); }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .status-row { grid-template-columns: 1fr 1fr; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
            .schedule-form { flex-direction: column; align-items: stretch; }
            .form-group { min-width: auto; }
        }
        @media (max-width: 640px) {
            .status-row { grid-template-columns: 1fr; }
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
            <a href="fan_control.php"><i class="fas fa-fan"></i> Fan Control</a>
            <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
            <a href="water_pump.php" class="active"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Inventory</div>
            <a href="feed_inventory.php"><i class="fas fa-utensils"></i> Feed Inventory</a>
            <a href="water_inventory.php"><i class="fas fa-water"></i> Water Inventory</a>
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
        <h1 class="page-title"><i class="fas fa-hand-holding-water" style="color:var(--accent);"></i> Water Pump Control</h1>
        <p class="page-subtitle">Automated watering system with programmable schedules</p>

        <div class="status-row">
            <div class="status-card">
                <div class="card-icon gold"><i class="fas fa-tint"></i></div>
                <div class="card-info">
                    <div class="value gold"><?php echo round($waterPercentage, 1); ?>%</div>
                    <div class="label">Water Level</div>
                </div>
            </div>
            <div class="status-card">
                <div class="card-icon green"><i class="fas fa-chart-line"></i></div>
                <div class="card-info">
                    <div class="value green"><?php echo number_format($todayUsage, 1); ?> L</div>
                    <div class="label">Today's Usage</div>
                </div>
            </div>
            <div class="status-card">
                <div class="card-icon blue"><i class="fas fa-microchip"></i></div>
                <div class="card-info">
                    <div class="value blue"><?php echo $autoEnabled ? 'AUTO' : 'MANUAL'; ?></div>
                    <div class="label">Operation Mode</div>
                    <div class="sub">
                        <span class="status-badge <?php echo $autoEnabled ? 'auto' : 'manual'; ?>">
                            <i class="fas <?php echo $autoEnabled ? 'fa-check' : 'fa-times'; ?>"></i>
                            <?php echo $autoEnabled ? 'Auto Watering' : 'Manual Only'; ?>
                        </span>
                        <?php if ($isManualOverride && $autoEnabled): ?>
                        <span class="override-badge"><i class="fas fa-hand-paper"></i> Override</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-mini-grid">
            <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--accent-dark);"><?php echo count($schedules); ?></div><div class="stat-mini-label">Active Schedules</div></div>
            <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--green);"><?php echo number_format($totalUsage, 1); ?> L</div><div class="stat-mini-label">Total Usage</div></div>
            <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--blue);"><?php echo $scheduleCount; ?></div><div class="stat-mini-label">Schedule Runs</div></div>
            <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--orange);"><?php echo $manualCount; ?></div><div class="stat-mini-label">Manual Releases</div></div>
        </div>

        <div class="auto-banner">
            <div class="banner-left">
                <div class="banner-icon"><i class="fas fa-robot"></i></div>
                <div>
                    <div class="banner-title">Automation Status</div>
                    <span class="auto-badge <?php echo $autoEnabled ? 'enabled' : 'disabled'; ?>">
                        <i class="fas <?php echo $autoEnabled ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                        <?php echo $autoEnabled ? 'Auto Watering Active' : 'Manual Mode'; ?>
                    </span>
                    <div class="banner-sub"><?php echo $autoEnabled ? 'Water will be released automatically based on schedules.' : 'Manual watering only. Toggle ON for automation.'; ?></div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.8rem;flex-wrap:wrap;">
                <label class="toggle-switch-large">
                    <input type="checkbox" id="autoToggle" <?php echo $autoEnabled ? 'checked' : ''; ?> onchange="toggleAutoMode()">
                    <span class="toggle-slider-large"></span>
                </label>
                <span style="font-size:0.85rem;font-weight:500;">Auto Mode</span>
                <?php if ($isManualOverride && $autoEnabled): ?>
                    <button class="reset-auto-btn" onclick="resetToAuto()"><i class="fas fa-undo"></i> Reset</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="manual-control-card">
            <h3><i class="fas fa-hand-paper"></i> Manual Water Release</h3>
            <div class="water-buttons">
                <button class="water-btn" onclick="showWaterModal(15)">15 sec (7.5 L)</button>
                <button class="water-btn" onclick="showWaterModal(30)">30 sec (15 L)</button>
                <button class="water-btn" onclick="showWaterModal(45)">45 sec (22.5 L)</button>
                <button class="water-btn" onclick="showWaterModal(60)">60 sec (30 L)</button>
            </div>
            <button class="custom-water-btn" onclick="showCustomWaterModal()"><i class="fas fa-plus-circle"></i> Custom Duration</button>

            <div class="pump-status-row">
                <div class="pump-icon <?php echo $pumpStatus === 'ON' ? 'running' : 'idle'; ?>">
                    <i class="fas fa-water-pump"></i>
                </div>
                <div class="pump-info">
                    <div class="pump-label">Pump Status</div>
                    <div class="pump-value">
                        <span class="status-badge <?php echo $pumpStatus === 'ON' ? 'running' : 'idle'; ?>">
                            <i class="fas <?php echo $pumpStatus === 'ON' ? 'fa-play' : 'fa-stop'; ?>"></i>
                            <?php echo $pumpStatus === 'ON' ? 'RUNNING' : 'IDLE'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="schedule-card">
            <h3><i class="fas fa-plus-circle" style="color:var(--accent);"></i> Add Watering Schedule</h3>
            <div class="schedule-form">
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Time</label>
                    <input type="time" id="newScheduleTime" value="08:00">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hourglass-half"></i> Duration (sec)</label>
                    <input type="number" id="newScheduleDuration" step="5" value="30" min="5">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Label</label>
                    <select id="newScheduleLabel">
                        <option value="Morning Watering">Morning Watering</option>
                        <option value="Afternoon Watering">Afternoon Watering</option>
                        <option value="Evening Watering">Evening Watering</option>
                        <option value="Custom">Custom</option>
                    </select>
                </div>
                <button class="btn-add-schedule" onclick="addSchedule()"><i class="fas fa-save"></i> Add</button>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin:1.5rem 0 1rem;">
            <h3><i class="fas fa-calendar-alt"></i> Watering Schedules</h3>
            <span style="font-size:0.75rem; color:var(--text-muted);"><?php echo count($schedules); ?> schedule(s) configured</span>
        </div>

        <div class="schedule-table-wrap">
            <table class="schedule-table">
                <thead><tr><th>Time</th><th>Duration</th><th>Water Amount</th><th>Label</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody id="schedulesTableBody">
                    <?php if (empty($schedules)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--text-muted);">No schedules configured. Add one above.</td></tr>
                    <?php else: ?>
                    <?php foreach ($schedules as $sch): ?>
                    <tr data-id="<?php echo $sch['id']; ?>">
                        <td class="schedule-time"><?php echo date('h:i A', strtotime($sch['time'])); ?></td>
                        <td class="schedule-duration"><?php echo $sch['duration']; ?> sec</td>
                        <td><?php echo round($sch['duration'] * 0.5, 1); ?> L</td>
                        <td><span class="schedule-label"><?php echo htmlspecialchars($sch['label']); ?></span></td>
                        <td style="text-align:right;">
                            <div class="schedule-actions">
                                <button class="btn-edit" onclick="editSchedule(<?php echo $sch['id']; ?>, '<?php echo $sch['time']; ?>', <?php echo $sch['duration']; ?>, '<?php echo $sch['label']; ?>')"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete" onclick="deleteSchedule(<?php echo $sch['id']; ?>)"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="log-card">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <div class="log-list" id="logList">
                <?php if (empty($logs)): ?>
                    <div style="text-align:center;padding:1.5rem;color:var(--text-muted);">No activity recorded yet.</div>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <div class="log-entry" data-trigger="<?php echo strtolower($log['source'] ?? ''); ?>">
                    <div class="log-time"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></div>
                    <div>
                        <span class="log-badge <?php echo ($log['source'] ?? '') === 'auto_pump' ? 'log-schedule' : 'log-manual'; ?>">
                            <i class="fas fa-tint"></i> <?php echo number_format($log['amount'], 1); ?> L
                        </span>
                        <span class="log-trigger"><?php echo ucfirst($log['source'] ?? 'manual'); ?></span>
                    </div>
                    <div>
                        <span style="font-weight:600;"><?php echo htmlspecialchars($log['notes'] ?? '—'); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon"><i class="fas fa-tint"></i></div>
        <h3 id="modalTitle">Confirm Water Release</h3>
        <p id="modalMessage">Are you sure you want to release water?</p>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-confirm" onclick="confirmWater()">Confirm</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    let currentSchedules = <?php echo json_encode($schedules); ?>;
    let pendingDuration = null;

    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    function showWaterModal(duration) {
        pendingDuration = duration;
        let waterAmount = (duration * 0.5).toFixed(1);
        document.getElementById('modalTitle').innerText = 'Confirm Water Release';
        document.getElementById('modalMessage').innerHTML = `Release <strong>${waterAmount} L</strong> of water?<br><small>Pump will run for ${duration} seconds.</small>`;
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function showCustomWaterModal() {
        let duration = prompt('Enter duration in seconds:', '30');
        if (duration && !isNaN(duration) && duration > 0) showWaterModal(parseInt(duration));
        else if (duration) showToast('Invalid duration', true);
    }

    function closeModal() {
        document.getElementById('confirmModal').style.display = 'none';
        pendingDuration = null;
    }

    async function confirmWater() {
        if (pendingDuration === null) return;
        const duration = pendingDuration;
        closeModal();

        try {
            const response = await fetch('water_pump.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=manual_water&duration=${duration}`
            });
            const data = await response.json();
            if (data.success) {
                showToast(`${data.water_amount} L of water released`);
                setTimeout(() => {
                    turnOffPump(duration);
                }, duration * 1000);
                setTimeout(() => location.reload(), (duration * 1000) + 1000);
            } else showToast(data.message, true);
        } catch (error) { showToast('Error releasing water', true); }
    }

    async function turnOffPump(duration) {
        try {
            const response = await fetch('water_pump.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=pump_off&duration=${duration}`
            });
            const data = await response.json();
            if (data.success) showToast('Pump turned off');
            else showToast(data.message, true);
        } catch (error) { showToast('Error turning off pump', true); }
    }

    async function toggleAutoMode() {
        const enabled = document.getElementById('autoToggle').checked;
        try {
            const response = await fetch('water_pump.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=toggle_auto&enabled=${enabled}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error toggling auto mode', true); }
    }

    async function resetToAuto() {
        try {
            const response = await fetch('water_pump.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=reset_override`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
        } catch (error) { showToast('Error resetting to auto mode', true); }
    }

    async function addSchedule() {
        const time = document.getElementById('newScheduleTime').value;
        const duration = parseInt(document.getElementById('newScheduleDuration').value);
        let label = document.getElementById('newScheduleLabel').value;
        if (label === 'Custom') label = prompt('Enter custom label:', 'Watering Time') || 'Watering Time';
        if (!time || !duration || duration <= 0) { showToast('Please fill all fields correctly', true); return; }
        const newId = currentSchedules.length > 0 ? Math.max(...currentSchedules.map(s => s.id)) + 1 : 1;
        currentSchedules.push({ id: newId, time, duration, label });
        await saveSchedules();
    }

    function editSchedule(id, oldTime, oldDuration, oldLabel) {
        const newTime = prompt('Enter new time (HH:MM 24h format):', oldTime);
        const newDuration = prompt('Enter new duration (seconds):', oldDuration);
        const newLabel = prompt('Enter label:', oldLabel);
        if (newTime && newDuration && !isNaN(newDuration) && parseInt(newDuration) > 0 && newLabel) {
            const index = currentSchedules.findIndex(s => s.id === id);
            if (index !== -1) {
                currentSchedules[index].time = newTime;
                currentSchedules[index].duration = parseInt(newDuration);
                currentSchedules[index].label = newLabel;
                saveSchedules();
            }
        }
    }

    function deleteSchedule(id) {
        if (confirm('Delete this watering schedule?')) {
            currentSchedules = currentSchedules.filter(s => s.id !== id);
            saveSchedules();
        }
    }

    async function saveSchedules() {
        try {
            const response = await fetch('water_pump.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_schedule&schedules=${encodeURIComponent(JSON.stringify(currentSchedules))}`
            });
            const data = await response.json();
            if (data.success) { showToast('Schedules updated!'); setTimeout(() => location.reload(), 600); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error saving schedules', true); }
    }

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    document.getElementById('confirmModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }

    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'water_pump.php?refresh_weather=1'; }
    document.getElementById('weatherModal').addEventListener('click', function(e) { if (e.target === this) closeWeatherModal(); });

    setInterval(updateDateTime, 1000);
    updateDateTime();
    window.addEventListener('load', function() {
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
    });
</script>
</body>
</html>