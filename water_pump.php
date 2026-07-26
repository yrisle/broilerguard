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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action === 'toggle_pump') {
        $status = $_POST['status'] ?? 'OFF';
        
        $logEntry = [
            'action' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'trigger' => 'manual',
            'duration' => $_POST['duration'] ?? null
        ];
        
        if (!isset($_SESSION['pump_logs'])) {
            $_SESSION['pump_logs'] = [];
        }
        array_unshift($_SESSION['pump_logs'], $logEntry);
        if (count($_SESSION['pump_logs']) > 50) {
            array_pop($_SESSION['pump_logs']);
        }
        
        $_SESSION['pump_status'] = $status;
        $_SESSION['manual_override'] = true;
        
        $response = ['success' => true, 'message' => "Water pump turned $status successfully"];
        
    } elseif ($action === 'manual_water') {
        $duration = intval($_POST['duration'] ?? 30);
        
        $logEntry = [
            'action' => 'WATER',
            'duration' => $duration,
            'timestamp' => date('Y-m-d H:i:s'),
            'trigger' => 'manual',
            'water_amount' => round($duration * 0.5, 1)
        ];
        
        if (!isset($_SESSION['pump_logs'])) {
            $_SESSION['pump_logs'] = [];
        }
        array_unshift($_SESSION['pump_logs'], $logEntry);
        if (count($_SESSION['pump_logs']) > 50) {
            array_pop($_SESSION['pump_logs']);
        }
        
        if (!isset($_SESSION['water_usage'])) {
            $_SESSION['water_usage'] = 0;
        }
        $_SESSION['water_usage'] += round($duration * 0.5, 1);
        
        $response = ['success' => true, 'message' => "Water released for {$duration} seconds", 'water_amount' => round($duration * 0.5, 1)];
        
    } elseif ($action === 'update_schedule') {
        $schedules = json_decode($_POST['schedules'] ?? '[]', true);
        $_SESSION['water_schedules'] = $schedules;
        $response = ['success' => true, 'message' => 'Water schedules updated!'];
        
    } elseif ($action === 'toggle_auto') {
        $enabled = $_POST['enabled'] === 'true';
        $_SESSION['auto_watering_enabled'] = $enabled;
        if (!$enabled) {
            $_SESSION['manual_override'] = true;
        } else {
            $_SESSION['manual_override'] = false;
        }
        $response = ['success' => true, 'message' => $enabled ? 'Auto watering enabled' : 'Auto watering disabled'];
        
    } elseif ($action === 'reset_override') {
        $_SESSION['manual_override'] = false;
        $response = ['success' => true, 'message' => 'Auto mode restored'];
    }
    
    echo json_encode($response);
    exit;
}

function getPumpStatus() {
    if (isset($_SESSION['manual_override']) && $_SESSION['manual_override'] === true) {
        return $_SESSION['pump_status'] ?? 'OFF';
    }
    return 'OFF';
}

function getWaterSchedules() {
    return $_SESSION['water_schedules'] ?? [
        ['id' => 1, 'time' => '06:00', 'duration' => 30, 'enabled' => true, 'label' => 'Morning Watering', 'amount' => 15],
        ['id' => 2, 'time' => '12:00', 'duration' => 25, 'enabled' => true, 'label' => 'Afternoon Watering', 'amount' => 12.5],
        ['id' => 3, 'time' => '18:00', 'duration' => 30, 'enabled' => true, 'label' => 'Evening Watering', 'amount' => 15]
    ];
}

function getWaterLogs($limit = 50) {
    if (isset($_SESSION['pump_logs']) && !empty($_SESSION['pump_logs'])) {
        return array_slice($_SESSION['pump_logs'], 0, $limit);
    }
    
    $logs = [];
    $triggers = ['Schedule', 'Schedule', 'Schedule', 'manual'];
    $durations = [30, 25, 30, 20];
    
    for ($i = 0; $i < 25; $i++) {
        $duration = $durations[array_rand($durations)];
        $trigger = $triggers[array_rand($triggers)];
        $logs[] = [
            'action' => 'WATER',
            'duration' => $duration,
            'timestamp' => date('Y-m-d H:i:s', strtotime("-$i hours")),
            'trigger' => $trigger,
            'water_amount' => round($duration * 0.5, 1)
        ];
    }
    return $logs;
}

function getWaterStats($logs) {
    $today = date('Y-m-d');
    $todayUsage = 0;
    $totalUsage = 0;
    $scheduleCount = 0;
    $manualCount = 0;
    
    foreach ($logs as $log) {
        if ($log['action'] === 'WATER') {
            $amount = $log['water_amount'] ?? ($log['duration'] * 0.5);
            $totalUsage += $amount;
            if (strpos($log['timestamp'], $today) === 0) {
                $todayUsage += $amount;
            }
            if ($log['trigger'] === 'Schedule') {
                $scheduleCount++;
            } elseif ($log['trigger'] === 'manual') {
                $manualCount++;
            }
        }
    }
    
    return [
        'today_usage' => round($todayUsage, 1),
        'total_usage' => round($totalUsage, 1),
        'schedule_count' => $scheduleCount,
        'manual_count' => $manualCount
    ];
}

$pumpStatus = getPumpStatus();
$schedules = getWaterSchedules();
$logs = getWaterLogs();
$stats = getWaterStats($logs);
$autoEnabled = $_SESSION['auto_watering_enabled'] ?? true;
$isManualOverride = isset($_SESSION['manual_override']) && $_SESSION['manual_override'] === true;

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Pump Control | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        }
        
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
            flex-shrink: 0;
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
        .top-header .date-time .date { font-size: 0.8rem; color: var(--text-muted); }
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
        
        .page-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }
        
        /* Status Cards - Horizontal Row */
        .status-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; margin-bottom: 1.5rem; }
        .status-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); display: flex; align-items: center; gap: 1.2rem; transition: transform 0.2s, box-shadow 0.2s; }
        .status-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        
        .status-card .card-icon { font-size: 2.2rem; flex-shrink: 0; }
        .status-card .card-icon.gold { color: #E6B800; }
        .status-card .card-icon.green { color: #27AE60; }
        .status-card .card-icon.blue { color: #2980B9; }
        
        .status-card .card-info { flex: 1; }
        .status-card .card-info .value { font-size: 1.8rem; font-weight: 800; line-height: 1.2; }
        .status-card .card-info .value.gold { color: #E6B800; }
        .status-card .card-info .value.green { color: #27AE60; }
        .status-card .card-info .value.blue { color: #2980B9; }
        .status-card .card-info .label { font-size: 0.8rem; color: var(--text-muted); }
        .status-card .card-info .sub { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        .status-badge { display: inline-block; padding: 0.15rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.running { background: var(--green-light); color: var(--green); }
        .status-badge.idle { background: #E0E0E0; color: #757575; }
        .status-badge.auto { background: var(--blue-light); color: var(--blue); }
        .status-badge.manual { background: var(--yellow-light); color: var(--yellow); }
        
        /* Stats Mini Grid */
        .stats-mini-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-mini-card { background: var(--bg-card); border-radius: 12px; padding: 0.8rem 1rem; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); transition: transform 0.2s; }
        .stat-mini-card:hover { transform: translateY(-2px); }
        .stat-mini-card .stat-mini-value { font-size: 1.3rem; font-weight: 700; }
        .stat-mini-card .stat-mini-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.1rem; }
        
        /* Automation Banner */
        .auto-banner {
            background: linear-gradient(135deg, #6B4226, #4A2F1F);
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
        .override-badge { display: inline-block; background: #FF9800; color: #3E2C1C; padding: 0.15rem 0.6rem; border-radius: 30px; font-size: 0.65rem; font-weight: 600; margin-top: 0.2rem; }
        
        .toggle-switch-large { position: relative; display: inline-block; width: 52px; height: 26px; flex-shrink: 0; }
        .toggle-switch-large input { opacity: 0; width: 0; height: 0; }
        .toggle-slider-large { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #5A5A5A; transition: 0.2s; border-radius: 26px; }
        .toggle-slider-large:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: 0.2s; border-radius: 50%; }
        input:checked + .toggle-slider-large { background-color: #FFD62E; }
        input:checked + .toggle-slider-large:before { transform: translateX(26px); }
        .reset-auto-btn { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.5); color: white; padding: 0.2rem 0.8rem; border-radius: 30px; cursor: pointer; font-size: 0.7rem; font-family: 'Inter', sans-serif; transition: 0.2s; }
        .reset-auto-btn:hover { background: rgba(255,255,255,0.3); }
        
        /* Manual Control */
        .manual-control-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 214, 46, 0.08);
            box-shadow: var(--shadow-sm);
        }
        .manual-control-card h3 { font-size: 0.95rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }
        .water-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.8rem; }
        .water-btn {
            background: var(--bg-secondary);
            border: 1px solid rgba(255, 214, 46, 0.2);
            padding: 0.4rem 0.9rem;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: 'Inter', sans-serif;
        }
        .water-btn:hover { background: var(--accent); color: #3E2C1C; transform: translateY(-2px); }
        .custom-water-btn {
            background: linear-gradient(105deg, #E6B800, #FFD62E);
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 40px;
            font-weight: 500;
            cursor: pointer;
            font-size: 0.8rem;
            color: #3E2C1C;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .custom-water-btn:hover { background: linear-gradient(105deg, #D4A017, #E6B800); transform: translateY(-2px); }
        
        /* Pump Status Indicator */
        .pump-status-row { display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255, 214, 46, 0.08); }
        .pump-status-row .pump-icon { font-size: 2rem; }
        .pump-status-row .pump-icon.running { color: var(--green); animation: pulse 1.5s infinite; }
        .pump-status-row .pump-icon.idle { color: #B0A890; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .pump-status-row .pump-info .pump-label { font-size: 0.7rem; color: var(--text-muted); }
        .pump-status-row .pump-info .pump-value { font-weight: 700; font-size: 1rem; }
        
        /* Schedule Section */
        .schedule-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 214, 46, 0.08);
            box-shadow: var(--shadow-sm);
        }
        .schedule-card h3 { font-size: 0.95rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .schedule-form { display: flex; gap: 0.8rem; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 120px; }
        .form-group label { font-size: 0.65rem; font-weight: 600; display: block; margin-bottom: 0.2rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid rgba(255, 214, 46, 0.2);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        .btn-add-schedule {
            background: linear-gradient(105deg, #E6B800, #FFD62E);
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            color: #3E2C1C;
            font-size: 0.8rem;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .btn-add-schedule:hover { background: linear-gradient(105deg, #D4A017, #E6B800); transform: translateY(-2px); }
        
        .schedule-table-wrap {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid rgba(255, 214, 46, 0.08);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .schedule-table { width: 100%; border-collapse: collapse; }
        .schedule-table th { text-align: left; padding: 0.6rem 1rem; background: #FFF8E0; font-weight: 600; font-size: 0.75rem; color: var(--text-secondary); border-bottom: 1px solid rgba(255, 214, 46, 0.15); }
        .schedule-table td { padding: 0.6rem 1rem; border-bottom: 1px solid rgba(255, 214, 46, 0.06); vertical-align: middle; font-size: 0.85rem; }
        .schedule-table tr:last-child td { border-bottom: none; }
        .schedule-time { font-weight: 700; }
        .schedule-duration { font-weight: 600; color: var(--accent-dark); }
        .schedule-label { background: var(--accent-light); padding: 0.15rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 500; display: inline-block; }
        .schedule-actions { display: flex; gap: 0.3rem; justify-content: flex-end; }
        .btn-edit, .btn-delete { background: none; border: none; cursor: pointer; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 6px; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .btn-edit { background: var(--blue-light); color: var(--blue); }
        .btn-delete { background: var(--red-light); color: var(--red); }
        .btn-edit:hover, .btn-delete:hover { opacity: 0.8; }
        .schedule-empty { text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem; }
        
        /* Activity Log */
        .log-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            border: 1px solid rgba(255, 214, 46, 0.08);
            box-shadow: var(--shadow-sm);
        }
        .log-card h3 { font-size: 0.95rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        .log-filters { display: flex; gap: 0.8rem; margin-bottom: 0.8rem; flex-wrap: wrap; align-items: center; }
        .log-search {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-secondary);
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            border: 1px solid rgba(255, 214, 46, 0.15);
        }
        .log-search input { border: none; background: none; outline: none; flex: 1; font-family: 'Inter', sans-serif; font-size: 0.8rem; }
        .log-filter-select {
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            border: 1px solid rgba(255, 214, 46, 0.15);
            background: var(--bg-secondary);
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            color: var(--text-primary);
        }
        .log-list { max-height: 300px; overflow-y: auto; }
        .log-list::-webkit-scrollbar { width: 4px; }
        .log-list::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
        .log-list::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }
        .log-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 214, 46, 0.06); flex-wrap: wrap; gap: 0.3rem; }
        .log-entry:last-child { border-bottom: none; }
        .log-badge { padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.65rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.2rem; }
        .log-schedule { background: var(--blue-light); color: var(--blue); }
        .log-manual { background: var(--accent-light); color: var(--accent-dark); }
        .log-time { font-size: 0.65rem; color: var(--text-muted); }
        .log-amount { font-weight: 600; font-size: 0.8rem; }
        .log-trigger { font-size: 0.65rem; color: var(--text-muted); margin-left: 0.2rem; }
        
        .reset-filter-btn {
            background: none;
            border: 1px solid var(--accent);
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: 'Inter', sans-serif;
        }
        .reset-filter-btn:hover { background: var(--accent-light); }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 20px; padding: 2rem; max-width: 380px; width: 90%; text-align: center; border: 1px solid rgba(255, 214, 46, 0.08); }
        .modal-content .modal-icon { font-size: 2.5rem; color: var(--accent); margin-bottom: 0.5rem; }
        .modal-content h3 { font-size: 1.1rem; margin-bottom: 0.5rem; }
        .modal-content p { font-size: 0.9rem; color: var(--text-secondary); }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center; }
        .modal-confirm { background: linear-gradient(105deg, #E6B800, #FFD62E); border: none; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 600; cursor: pointer; color: #3E2C1C; font-family: 'Inter', sans-serif; }
        .modal-confirm:hover { background: linear-gradient(105deg, #D4A017, #E6B800); }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }
        .modal-cancel:hover { background: #D0D0D0; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: #27AE60; color: white; padding: 0.7rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.8rem; box-shadow: var(--shadow-lg); }
        .toast.error { background: #E74C3C; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
        .section-header h3 { font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem; }
        .section-header .count { font-size: 0.7rem; color: var(--text-muted); }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            .status-row { grid-template-columns: 1fr 1fr; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
            .schedule-form { flex-direction: column; align-items: stretch; }
            .form-group { min-width: auto; }
        }
        @media (max-width: 768px) {
            .status-row { grid-template-columns: 1fr; }
            .stats-mini-grid { grid-template-columns: 1fr 1fr; }
            .auto-banner { flex-direction: column; text-align: center; }
            .auto-banner .banner-left { flex-direction: column; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
        }
        @media (max-width: 640px) {
            .stats-mini-grid { grid-template-columns: 1fr; }
            .log-entry { flex-direction: column; align-items: flex-start; }
            .water-buttons { flex-direction: column; }
            .water-btn { text-align: center; }
            .custom-water-btn { text-align: center; justify-content: center; }
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
                <a href="water_pump.php" class="active"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
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
            <h1 class="page-title"><i class="fas fa-hand-holding-water" style="color:var(--accent);"></i> Water Pump</h1>
            <p class="page-subtitle">Automated watering system with programmable schedules</p>

            <!-- Status Cards - Horizontal Row -->
            <div class="status-row">
                <!-- Today's Usage -->
                <div class="status-card">
                    <div class="card-icon gold"><i class="fas fa-chart-line"></i></div>
                    <div class="card-info">
                        <div class="value gold"><?php echo $stats['today_usage']; ?> L</div>
                        <div class="label">Today's Usage</div>
                        <div class="sub"><?php echo $stats['schedule_count']; ?> scheduled waterings</div>
                    </div>
                </div>

                <!-- Total Usage -->
                <div class="status-card">
                    <div class="card-icon green"><i class="fas fa-tint"></i></div>
                    <div class="card-info">
                        <div class="value green"><?php echo $stats['total_usage']; ?> L</div>
                        <div class="label">Total Usage (7 days)</div>
                        <div class="sub"><?php echo $stats['manual_count']; ?> manual refills</div>
                    </div>
                </div>

                <!-- Operation Mode -->
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

            <!-- Stats Mini Grid -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--accent-dark);"><?php echo count($schedules); ?></div><div class="stat-mini-label">Active Schedules</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--green);">2.0 L/s</div><div class="stat-mini-label">Flow Rate</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--blue);"><?php echo isset($_SESSION['last_water_time']) ? date('M d, h:i A', strtotime($_SESSION['last_water_time'])) : '—'; ?></div><div class="stat-mini-label">Last Watering</div></div>
                <div class="stat-mini-card"><div class="stat-mini-value" style="color:var(--orange);"><?php echo $autoEnabled ? 'Automatic' : 'Manual'; ?></div><div class="stat-mini-label">Control Mode</div></div>
            </div>

            <!-- Automation Status Banner -->
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

            <!-- Manual Control -->
            <div class="manual-control-card">
                <h3><i class="fas fa-hand-paper"></i> Manual Water Release</h3>
                <div class="water-buttons">
                    <button class="water-btn" onclick="showWaterModal(15)">15 sec (7.5 L)</button>
                    <button class="water-btn" onclick="showWaterModal(30)">30 sec (15 L)</button>
                    <button class="water-btn" onclick="showWaterModal(45)">45 sec (22.5 L)</button>
                    <button class="water-btn" onclick="showWaterModal(60)">60 sec (30 L)</button>
                </div>
                <button class="custom-water-btn" onclick="showCustomWaterModal()"><i class="fas fa-plus-circle"></i> Custom Duration</button>
                
                <!-- Pump Status -->
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

            <!-- Add Schedule -->
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

            <!-- Watering Schedules Table -->
            <div class="section-header">
                <h3><i class="fas fa-calendar-alt"></i> Watering Schedules</h3>
                <span class="count"><?php echo count($schedules); ?> schedule(s) configured</span>
            </div>

            <div class="schedule-table-wrap">
                <table class="schedule-table">
                    <thead>
                        <tr><th>Time</th><th>Duration</th><th>Water Amount</th><th>Label</th><th style="text-align:right;">Actions</th></tr>
                    </thead>
                    <tbody id="schedulesTableBody">
                        <?php if (empty($schedules)): ?>
                        <tr><td colspan="5" class="schedule-empty">No schedules configured. Add one above.</td></tr>
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

            <!-- Activity Log -->
            <div class="log-card">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <div class="log-filters">
                    <div class="log-search">
                        <i class="fas fa-search" style="color: var(--text-muted);"></i>
                        <input type="text" id="logSearchInput" placeholder="Search logs..." onkeyup="filterLogs()">
                    </div>
                    <select id="logTypeFilter" class="log-filter-select" onchange="filterLogs()">
                        <option value="all">All Types</option>
                        <option value="schedule">Schedule</option>
                        <option value="manual">Manual</option>
                    </select>
                    <button class="reset-filter-btn" onclick="resetLogFilters()">Reset</button>
                </div>
                <div class="log-list" id="logList">
                    <?php foreach ($logs as $log): ?>
                    <div class="log-entry" data-trigger="<?php echo strtolower($log['trigger']); ?>">
                        <div class="log-time"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></div>
                        <div>
                            <span class="log-badge <?php echo $log['trigger'] === 'Schedule' ? 'log-schedule' : 'log-manual'; ?>">
                                <i class="fas fa-tint"></i> <?php echo $log['water_amount'] ?? round($log['duration'] * 0.5, 1); ?> L
                            </span>
                            <span class="log-trigger"><?php echo $log['trigger']; ?></span>
                        </div>
                        <div>
                            <span style="font-weight:600;"><?php echo $log['duration']; ?> sec</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
            if (duration && !isNaN(duration) && duration > 0) {
                showWaterModal(parseInt(duration));
            } else if (duration) {
                showToast('Invalid duration', true);
            }
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
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message, true);
                }
            } catch (error) {
                showToast('Error releasing water', true);
            }
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
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 500);
                } else showToast(data.message, true);
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
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 500);
                }
            } catch (error) { showToast('Error resetting to auto mode', true); }
        }
        
        async function addSchedule() {
            const time = document.getElementById('newScheduleTime').value;
            const duration = parseInt(document.getElementById('newScheduleDuration').value);
            let label = document.getElementById('newScheduleLabel').value;
            
            if (label === 'Custom') label = prompt('Enter custom label:', 'Watering Time') || 'Watering Time';
            if (!time || !duration || duration <= 0) { showToast('Please fill all fields correctly', true); return; }
            
            const newId = currentSchedules.length > 0 ? Math.max(...currentSchedules.map(s => s.id)) + 1 : 1;
            currentSchedules.push({ id: newId, time, duration, enabled: true, label, amount: duration * 0.5 });
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
        
        function filterLogs() {
            const searchTerm = document.getElementById('logSearchInput').value.toLowerCase();
            const typeFilter = document.getElementById('logTypeFilter').value;
            const logEntries = document.querySelectorAll('.log-entry');
            
            logEntries.forEach(entry => {
                const trigger = entry.getAttribute('data-trigger') || entry.innerText.toLowerCase();
                let show = true;
                
                if (typeFilter !== 'all') {
                    if (typeFilter === 'schedule' && !trigger.includes('schedule')) show = false;
                    if (typeFilter === 'manual' && !trigger.includes('manual')) show = false;
                }
                
                if (searchTerm && !entry.innerText.toLowerCase().includes(searchTerm)) show = false;
                
                entry.style.display = show ? 'flex' : 'none';
            });
        }
        
        function resetLogFilters() {
            document.getElementById('logSearchInput').value = '';
            document.getElementById('logTypeFilter').value = 'all';
            filterLogs();
        }
        
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

        // Close modal when clicking outside
        document.getElementById('confirmModal').addEventListener('click', (e) => { 
            if (e.target === document.getElementById('confirmModal')) closeModal(); 
        });
        
        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
            document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>
</body>
</html>
