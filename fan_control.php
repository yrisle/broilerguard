<?php
// fan_control.php - Fan Control Automation Interface (No Database)
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

// ==================== INITIALIZE ALL SESSION VARIABLES ====================
// Initialize fan settings if not exists
if (!isset($_SESSION['fan_settings'])) {
    $_SESSION['fan_settings'] = [
        'auto_mode' => true,
        'temp_on' => 32.0,
        'temp_off' => 28.0,
        'last_activation' => null,
        'last_deactivation' => null,
        'total_run_time' => 0,
        'current_run_start' => null,
        'updated_at' => date('Y-m-d H:i:s')
    ];
} else {
    // Ensure all required keys exist even if session was partially initialized
    if (!isset($_SESSION['fan_settings']['total_run_time'])) {
        $_SESSION['fan_settings']['total_run_time'] = 0;
    }
    if (!isset($_SESSION['fan_settings']['temp_off'])) {
        $_SESSION['fan_settings']['temp_off'] = 28.0;
    }
    if (!isset($_SESSION['fan_settings']['temp_on'])) {
        $_SESSION['fan_settings']['temp_on'] = 32.0;
    }
    if (!isset($_SESSION['fan_settings']['auto_mode'])) {
        $_SESSION['fan_settings']['auto_mode'] = true;
    }
    if (!isset($_SESSION['fan_settings']['last_activation'])) {
        $_SESSION['fan_settings']['last_activation'] = null;
    }
    if (!isset($_SESSION['fan_settings']['last_deactivation'])) {
        $_SESSION['fan_settings']['last_deactivation'] = null;
    }
    if (!isset($_SESSION['fan_settings']['current_run_start'])) {
        $_SESSION['fan_settings']['current_run_start'] = null;
    }
    if (!isset($_SESSION['fan_settings']['updated_at'])) {
        $_SESSION['fan_settings']['updated_at'] = date('Y-m-d H:i:s');
    }
}

// Initialize fan logs if not exists
if (!isset($_SESSION['fan_logs'])) {
    $_SESSION['fan_logs'] = [];
}

// Initialize fan status
if (!isset($_SESSION['fan_status'])) {
    $_SESSION['fan_status'] = 'OFF';
}

// Initialize auto fan state
if (!isset($_SESSION['auto_fan_state'])) {
    $_SESSION['auto_fan_state'] = 'OFF';
}

// Initialize manual override
if (!isset($_SESSION['manual_override'])) {
    $_SESSION['manual_override'] = false;
}

// Initialize shared sensor data
if (!isset($_SESSION['shared_sensor_data'])) {
    $hour = (int)date('H');
    $baseTemp = 28;
    $tempVariation = sin(($hour - 14) * M_PI / 12) * 4;
    $currentTemp = $baseTemp + $tempVariation + mt_rand(-3, 3) / 10;
    
    $_SESSION['shared_sensor_data'] = [
        'temperature' => round($currentTemp, 1),
        'humidity' => round(55 + sin($hour * M_PI / 12) * 15 + mt_rand(-5, 5), 1),
        'timestamp' => time()
    ];
}

// Initialize admin username if not exists
if (!isset($_SESSION['admin_username'])) {
    $_SESSION['admin_username'] = 'Admin';
}

// ==================== HELPER FUNCTIONS ====================
function getCurrentTemperature() {
    return $_SESSION['shared_sensor_data']['temperature'];
}

function getCurrentHumidity() {
    return $_SESSION['shared_sensor_data']['humidity'];
}

function updateSensorData() {
    $hour = (int)date('H');
    $baseTemp = 28;
    $tempVariation = sin(($hour - 14) * M_PI / 12) * 4;
    $currentTemp = $baseTemp + $tempVariation + mt_rand(-3, 3) / 10;
    
    $_SESSION['shared_sensor_data'] = [
        'temperature' => round($currentTemp, 1),
        'humidity' => round(55 + sin($hour * M_PI / 12) * 15 + mt_rand(-5, 5), 1),
        'timestamp' => time()
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action === 'toggle_fan') {
        $status = $_POST['status'] ?? 'OFF';
        $currentTemp = getCurrentTemperature();
        
        // Update fan status
        $previousStatus = $_SESSION['fan_status'];
        $_SESSION['fan_status'] = $status;
        $_SESSION['manual_override'] = true;
        
        // Update runtime tracking
        if ($status === 'ON' && $previousStatus === 'OFF') {
            $_SESSION['fan_settings']['current_run_start'] = time();
            $_SESSION['fan_settings']['last_activation'] = date('Y-m-d H:i:s');
        } elseif ($status === 'OFF' && $previousStatus === 'ON') {
            if ($_SESSION['fan_settings']['current_run_start'] !== null) {
                $runDuration = time() - $_SESSION['fan_settings']['current_run_start'];
                $_SESSION['fan_settings']['total_run_time'] += $runDuration;
                $_SESSION['fan_settings']['current_run_start'] = null;
            }
            $_SESSION['fan_settings']['last_deactivation'] = date('Y-m-d H:i:s');
        }
        
        // Log the action
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $status,
            'trigger' => 'manual',
            'temperature' => $currentTemp
        ];
        array_unshift($_SESSION['fan_logs'], $logEntry);
        if (count($_SESSION['fan_logs']) > 100) {
            array_pop($_SESSION['fan_logs']);
        }
        
        $response = ['success' => true, 'message' => "Fan turned $status successfully"];
        
    } elseif ($action === 'update_settings') {
        $autoMode = $_POST['auto_mode'] === 'true';
        $tempOn = floatval($_POST['temp_on'] ?? 32);
        $tempOff = floatval($_POST['temp_off'] ?? 28);
        
        if ($tempOn <= $tempOff) {
            $response = ['success' => false, 'message' => 'ON temperature must be higher than OFF temperature'];
            echo json_encode($response);
            exit;
        }
        
        $_SESSION['fan_settings']['auto_mode'] = $autoMode;
        $_SESSION['fan_settings']['temp_on'] = $tempOn;
        $_SESSION['fan_settings']['temp_off'] = $tempOff;
        $_SESSION['fan_settings']['updated_at'] = date('Y-m-d H:i:s');
        
        if ($autoMode) {
            $_SESSION['manual_override'] = false;
        }
        
        $response = ['success' => true, 'message' => 'Settings updated successfully'];
        
    } elseif ($action === 'reset_override') {
        $_SESSION['manual_override'] = false;
        $response = ['success' => true, 'message' => 'Auto mode restored'];
        
    } elseif ($action === 'clear_logs') {
        $_SESSION['fan_logs'] = [];
        $response = ['success' => true, 'message' => 'Activity logs cleared'];
    }
    
    echo json_encode($response);
    exit;
}

// ==================== AUTO FAN LOGIC ====================
function getAutoFanStatus() {
    $currentTemp = getCurrentTemperature();
    $tempOn = $_SESSION['fan_settings']['temp_on'];
    $tempOff = $_SESSION['fan_settings']['temp_off'];
    $previousState = $_SESSION['auto_fan_state'];
    
    if ($previousState === 'OFF' && $currentTemp >= $tempOn) {
        $_SESSION['auto_fan_state'] = 'ON';
        return 'ON';
    } elseif ($previousState === 'ON' && $currentTemp <= $tempOff) {
        $_SESSION['auto_fan_state'] = 'OFF';
        return 'OFF';
    }
    
    return $previousState;
}

function getFanStatus() {
    if ($_SESSION['manual_override'] === true) {
        return $_SESSION['fan_status'];
    }
    
    if ($_SESSION['fan_settings']['auto_mode'] === true) {
        return getAutoFanStatus();
    }
    
    return 'OFF';
}

function updateAutoFanActions() {
    $previousStatus = $_SESSION['fan_status'];
    $currentStatus = getFanStatus();
    
    if ($_SESSION['manual_override'] !== true && $_SESSION['fan_settings']['auto_mode'] === true) {
        if ($previousStatus !== $currentStatus) {
            $currentTemp = getCurrentTemperature();
            
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $currentStatus,
                'trigger' => 'auto',
                'temperature' => $currentTemp
            ];
            array_unshift($_SESSION['fan_logs'], $logEntry);
            if (count($_SESSION['fan_logs']) > 100) {
                array_pop($_SESSION['fan_logs']);
            }
            
            if ($currentStatus === 'ON' && $previousStatus === 'OFF') {
                $_SESSION['fan_settings']['current_run_start'] = time();
                $_SESSION['fan_settings']['last_activation'] = date('Y-m-d H:i:s');
            } elseif ($currentStatus === 'OFF' && $previousStatus === 'ON') {
                if ($_SESSION['fan_settings']['current_run_start'] !== null) {
                    $runDuration = time() - $_SESSION['fan_settings']['current_run_start'];
                    $_SESSION['fan_settings']['total_run_time'] += $runDuration;
                    $_SESSION['fan_settings']['current_run_start'] = null;
                }
                $_SESSION['fan_settings']['last_deactivation'] = date('Y-m-d H:i:s');
            }
            
            $_SESSION['fan_status'] = $currentStatus;
        }
    } else {
        $_SESSION['fan_status'] = $currentStatus;
    }
}

updateSensorData();
updateAutoFanActions();

// ==================== GET DATA FOR DISPLAY ====================
$fanSettings = $_SESSION['fan_settings'];
$fanLogs = $_SESSION['fan_logs'];
$currentTemp = getCurrentTemperature();
$currentHumidity = getCurrentHumidity();
$fanStatus = getFanStatus();
$isManualOverride = $_SESSION['manual_override'];
$autoMode = $fanSettings['auto_mode'];

$totalRunTimeHours = isset($fanSettings['total_run_time']) ? round($fanSettings['total_run_time'] / 3600, 1) : 0;
$currentRunMinutes = 0;
if ($fanStatus === 'ON' && isset($fanSettings['current_run_start']) && $fanSettings['current_run_start'] !== null) {
    $currentRunMinutes = round((time() - $fanSettings['current_run_start']) / 60);
}

$autoReason = '';
if ($autoMode && !$isManualOverride) {
    if ($fanStatus === 'ON') {
        $autoReason = "{$currentTemp}°C ≥ {$fanSettings['temp_on']}°C";
    } else {
        $autoReason = "{$currentTemp}°C ≤ {$fanSettings['temp_off']}°C";
    }
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fan Control Automation | BroilerGuard</title>
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
            --border-radius: 14px;
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
        
        .page-content { padding: 1.5rem 2rem 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 0.2rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }
        
        /* Current Readings - 3 Column Layout */
        .current-readings { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.2rem; margin-bottom: 1.5rem; }
        .reading-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.2rem 1rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); transition: transform 0.2s, box-shadow 0.2s; text-align: center; }
        .reading-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        
        .reading-card .card-icon { font-size: 1.6rem; margin-bottom: 0.3rem; display: block; }
        .reading-card .card-value { font-size: 1.6rem; font-weight: 800; line-height: 1.2; margin-bottom: 0.1rem; }
        .reading-card .card-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        
        /* Fan Status Card */
        .fan-status-card { border-top: 3px solid #E6B800; }
        .fan-status-card .card-icon { color: #E6B800; }
        .fan-status-card .card-value { color: #E6B800; }
        
        .fan-icon-display { font-size: 2.8rem; color: #E6B800; display: block; margin: 0.1rem 0; transition: all 0.3s; }
        .fan-icon-display.spin { animation: spin 2s linear infinite; }
        .fan-icon-display.off { color: #B0A890; animation: none; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        
        .status-badge { display: inline-block; padding: 0.2rem 0.9rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin: 0.2rem 0; }
        .status-badge.on { background: var(--green-light); color: var(--green); }
        .status-badge.off { background: var(--red-light); color: var(--red); }
        
        .toggle-btn { padding: 0.4rem 1.5rem; border: none; border-radius: 50px; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.3rem; font-family: 'Inter', sans-serif; }
        .toggle-btn.on { background: #E74C3C; color: white; }
        .toggle-btn.off { background: #27AE60; color: white; }
        .toggle-btn.on:hover { background: #C0392B; }
        .toggle-btn.off:hover { background: #1B5E20; }
        
        .override-label { display: inline-block; background: #FF9800; color: #3E2C1C; padding: 0.1rem 0.6rem; border-radius: 20px; font-size: 0.6rem; font-weight: 600; margin-top: 0.1rem; }
        
        /* Temperature Card */
        .temp-card { border-top: 3px solid #E67E22; }
        .temp-card .card-icon { color: #E67E22; }
        .temp-card .card-value { color: #E67E22; }
        
        .temp-detail { font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.1rem; }
        .temp-detail i { color: #2980B9; }
        
        .auto-reason { background: var(--blue-light); border-radius: 8px; padding: 0.3rem 0.6rem; margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-secondary); display: inline-block; }
        .auto-reason i { color: var(--blue); margin-right: 0.2rem; }
        
        .threshold-info { font-size: 0.65rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        .manual-control-box { background: var(--yellow-light); border-radius: 8px; padding: 0.3rem 0.6rem; margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-secondary); display: inline-block; }
        .manual-control-box i { color: var(--yellow); margin-right: 0.2rem; }
        
        .reset-link { background: none; border: none; color: var(--blue); cursor: pointer; text-decoration: underline; font-family: 'Inter', sans-serif; font-size: 0.7rem; }
        .reset-link:hover { color: var(--accent-dark); }
        
        /* Mode Card */
        .mode-card { border-top: 3px solid #2980B9; }
        .mode-card .card-icon { color: #2980B9; }
        .mode-card .card-value { color: #2980B9; font-size: 1.4rem; }
        
        .mode-badge { display: inline-block; padding: 0.2rem 0.9rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin: 0.2rem 0; }
        .mode-badge.auto { background: var(--blue-light); color: var(--blue); }
        .mode-badge.manual { background: var(--yellow-light); color: var(--yellow); }
        
        .update-time { font-size: 0.6rem; color: var(--text-muted); margin-top: 0.1rem; }
        
        /* Stats Mini Grid */
        .stats-mini-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .stat-mini { background: var(--bg-card); border-radius: 10px; padding: 0.7rem 0.8rem; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); transition: transform 0.2s; }
        .stat-mini:hover { transform: translateY(-1px); }
        .stat-mini .stat-value { font-size: 1.1rem; font-weight: 700; }
        .stat-mini .stat-label { font-size: 0.65rem; color: var(--text-muted); margin-top: 0.1rem; }
        
        /* Settings Panel */
        .settings-panel { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; margin-bottom: 1.2rem; border: 1px solid rgba(255, 214, 46, 0.08); box-shadow: var(--shadow-sm); }
        .settings-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .settings-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        
        .mode-selector { display: flex; gap: 0.8rem; margin-bottom: 1rem; }
        .mode-btn { flex: 1; padding: 0.5rem; border: 2px solid rgba(255, 214, 46, 0.2); background: white; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s; text-align: center; color: var(--text-secondary); font-family: 'Inter', sans-serif; font-size: 0.8rem; }
        .mode-btn.active { border-color: var(--accent); background: var(--accent-light); color: var(--accent-dark); }
        .mode-btn:hover { border-color: var(--accent); }
        
        .threshold-sliders { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1rem; }
        .threshold-item { background: var(--bg-secondary); padding: 0.8rem 1rem; border-radius: 10px; }
        .threshold-label { display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.8rem; }
        .threshold-label span:last-child { font-weight: 700; color: var(--accent-dark); }
        
        input[type="range"] { width: 100%; height: 5px; -webkit-appearance: none; background: #E0D5C0; border-radius: 5px; outline: none; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; background: var(--accent); border-radius: 50%; cursor: pointer; }
        
        .threshold-hint { font-size: 0.7rem; color: var(--text-muted); padding: 0.2rem 0; }
        .threshold-hint i { margin-right: 0.3rem; color: var(--blue); }
        
        .save-btn { background: linear-gradient(105deg, #E6B800, #FFD62E); border: none; padding: 0.6rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; width: 100%; font-size: 0.85rem; color: #3E2C1C; font-family: 'Inter', sans-serif; }
        .save-btn:hover { background: linear-gradient(105deg, #D4A017, #E6B800); }
        
        /* Activity Log */
        .log-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; border: 1px solid rgba(255, 214, 46, 0.08); box-shadow: var(--shadow-sm); }
        .log-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem; }
        .log-list { max-height: 280px; overflow-y: auto; }
        .log-list::-webkit-scrollbar { width: 4px; }
        .log-list::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
        .log-list::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }
        .log-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid rgba(255, 214, 46, 0.06); flex-wrap: wrap; gap: 0.2rem; }
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
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: #27AE60; color: white; padding: 0.6rem 1.2rem; border-radius: 10px; display: none; align-items: center; gap: 0.6rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.8rem; box-shadow: var(--shadow-lg); }
        .toast.error { background: #E74C3C; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .empty-state { text-align: center; padding: 1.5rem; color: var(--text-muted); }
        .empty-state i { font-size: 1.5rem; display: block; margin-bottom: 0.3rem; }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            .current-readings { grid-template-columns: 1fr 1fr; }
            .stats-mini-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .current-readings { grid-template-columns: 1fr; }
            .stats-mini-grid { grid-template-columns: 1fr 1fr; }
            .mode-selector { flex-direction: column; }
        }
        @media (max-width: 640px) {
            .stats-mini-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
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
                <a href="fan_control.php" class="active"><i class="fas fa-fan"></i> Fan Control</a>
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
            <h1 class="page-title"><i class="fas fa-fan" style="color:var(--accent);"></i> Fan Control</h1>
            <p class="page-subtitle">Temperature-based ventilation automation with manual override</p>

            <!-- Current Readings - 3 Column Layout -->
            <div class="current-readings">
                <!-- Fan Status Card -->
                <div class="reading-card fan-status-card">
                    <span class="card-icon"><i class="fas fa-fan"></i></span>
                    <i class="fas fa-fan fan-icon-display <?php echo $fanStatus === 'ON' ? 'spin' : 'off'; ?>" id="fanIcon"></i>
                    <div class="card-value"><?php echo $fanStatus === 'ON' ? 'RUNNING' : 'OFF'; ?></div>
                    <div class="card-label">Exhaust Fan</div>
                    <span class="status-badge <?php echo strtolower($fanStatus); ?>">
                        <i class="fas <?php echo $fanStatus === 'ON' ? 'fa-play' : 'fa-stop'; ?>"></i> <?php echo $fanStatus === 'ON' ? 'Active' : 'Inactive'; ?>
                    </span>
                    <?php if ($isManualOverride): ?>
                    <div class="override-label"><i class="fas fa-hand-paper"></i> Manual Override</div>
                    <?php endif; ?>
                    <div>
                        <button class="toggle-btn <?php echo strtolower($fanStatus); ?>" id="toggleFanBtn" onclick="toggleFan('<?php echo $fanStatus; ?>')">
                            <i class="fas <?php echo $fanStatus === 'ON' ? 'fa-power-off' : 'fa-play'; ?>"></i>
                            <?php echo $fanStatus === 'ON' ? 'Turn OFF' : 'Turn ON'; ?>
                        </button>
                    </div>
                </div>

                <!-- Temperature Card -->
                <div class="reading-card temp-card">
                    <span class="card-icon"><i class="fas fa-thermometer-half"></i></span>
                    <div class="card-value"><?php echo $currentTemp; ?>°C</div>
                    <div class="card-label">Current Temperature</div>
                    <div class="temp-detail"><i class="fas fa-tint"></i> Humidity: <?php echo $currentHumidity; ?>%</div>
                    
                    <?php if ($autoMode && !$isManualOverride): ?>
                    <div class="auto-reason">
                        <i class="fas fa-robot"></i> <?php echo $autoReason; ?>
                    </div>
                    <div class="threshold-info">
                        ON ≥ <?php echo $fanSettings['temp_on']; ?>°C · OFF ≤ <?php echo $fanSettings['temp_off']; ?>°C
                    </div>
                    <?php elseif ($isManualOverride): ?>
                    <div class="manual-control-box">
                        <i class="fas fa-hand-paper"></i> Manual control
                        <br><button class="reset-link" onclick="resetToAuto()">Reset to Auto</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Operation Mode Card -->
                <div class="reading-card mode-card">
                    <span class="card-icon"><i class="fas fa-microchip"></i></span>
                    <div class="card-value"><?php echo $autoMode ? 'AUTO' : 'MANUAL'; ?></div>
                    <div class="card-label">Operation Mode</div>
                    <span class="mode-badge <?php echo $autoMode ? 'auto' : 'manual'; ?>">
                        <i class="fas <?php echo $autoMode ? 'fa-robot' : 'fa-hand-paper'; ?>"></i>
                        <?php echo $autoMode ? 'Automatic' : 'Manual'; ?>
                    </span>
                    <div class="update-time">Updated: <?php echo isset($fanSettings['updated_at']) ? date('M d, h:i A', strtotime($fanSettings['updated_at'])) : '—'; ?></div>
                </div>
            </div>

            <!-- Stats Mini Grid -->
            <div class="stats-mini-grid">
                <div class="stat-mini">
                    <div class="stat-value" style="color:var(--accent-dark);"><?php echo $totalRunTimeHours; ?> hrs</div>
                    <div class="stat-label">Total Runtime</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-value" style="color:var(--green);"><?php echo $currentRunMinutes > 0 ? $currentRunMinutes . ' min' : '—'; ?></div>
                    <div class="stat-label">Current Run</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-value" style="color:var(--blue);"><?php echo isset($fanSettings['last_activation']) && $fanSettings['last_activation'] ? date('h:i A', strtotime($fanSettings['last_activation'])) : '—'; ?></div>
                    <div class="stat-label">Last Activated</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-value" style="color:var(--red);"><?php echo isset($fanSettings['last_deactivation']) && $fanSettings['last_deactivation'] ? date('h:i A', strtotime($fanSettings['last_deactivation'])) : '—'; ?></div>
                    <div class="stat-label">Last Deactivated</div>
                </div>
            </div>

            <!-- Automation Settings Panel -->
            <div class="settings-panel">
                <div class="settings-header">
                    <div class="settings-title"><i class="fas fa-sliders-h"></i> Temperature Automation</div>
                </div>
                
                <div class="mode-selector">
                    <button class="mode-btn <?php echo $autoMode ? 'active' : ''; ?>" onclick="selectMode(true)">Automatic</button>
                    <button class="mode-btn <?php echo !$autoMode ? 'active' : ''; ?>" onclick="selectMode(false)">Manual</button>
                </div>

                <div class="threshold-sliders" id="thresholdSliders">
                    <div class="threshold-item">
                        <div class="threshold-label">
                            <span><i class="fas fa-power-off" style="color: var(--green);"></i> Turn ON when temperature reaches</span>
                            <span id="tempOnValue"><?php echo $fanSettings['temp_on']; ?>°C</span>
                        </div>
                        <input type="range" id="tempOn" min="20" max="45" step="0.5" value="<?php echo $fanSettings['temp_on']; ?>" oninput="updateTempValues()">
                    </div>
                    <div class="threshold-item">
                        <div class="threshold-label">
                            <span><i class="fas fa-stop-circle" style="color: var(--red);"></i> Turn OFF when temperature drops to</span>
                            <span id="tempOffValue"><?php echo $fanSettings['temp_off']; ?>°C</span>
                        </div>
                        <input type="range" id="tempOff" min="18" max="43" step="0.5" value="<?php echo $fanSettings['temp_off']; ?>" oninput="updateTempValues()">
                    </div>
                    <div class="threshold-hint">
                        <i class="fas fa-info-circle"></i> ON should be 2-4°C higher than OFF to prevent rapid cycling
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
                    <?php if (empty($fanLogs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        No activity recorded
                    </div>
                    <?php else: ?>
                        <?php foreach ($fanLogs as $log): ?>
                        <div class="log-entry">
                            <span class="log-time"><?php echo date('M d, h:i:s A', strtotime($log['timestamp'])); ?></span>
                            <span>
                                <span class="log-badge log-<?php echo strtolower($log['action']); ?>">
                                    <i class="fas <?php echo $log['action'] === 'ON' ? 'fa-play' : 'fa-stop'; ?>"></i> <?php echo $log['action']; ?>
                                </span>
                                <span class="log-badge log-<?php echo $log['trigger']; ?>">
                                    <i class="fas <?php echo $log['trigger'] === 'auto' ? 'fa-robot' : 'fa-hand-paper'; ?>"></i> <?php echo ucfirst($log['trigger']); ?>
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

    <div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

    <script>
        let selectedAutoMode = <?php echo $autoMode ? 'true' : 'false'; ?>;
        
        function showToast(message, isError) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMessage').textContent = message;
            toast.className = 'toast' + (isError ? ' error' : '');
            toast.style.display = 'flex';
            setTimeout(() => toast.style.display = 'none', 3000);
        }
        
        function updateTempValues() {
            let onVal = document.getElementById('tempOn').value;
            let offVal = document.getElementById('tempOff').value;
            document.getElementById('tempOnValue').innerText = onVal + '°C';
            document.getElementById('tempOffValue').innerText = offVal + '°C';
            
            if (parseFloat(onVal) <= parseFloat(offVal)) {
                document.getElementById('tempOnValue').style.color = '#E74C3C';
                document.getElementById('tempOffValue').style.color = '#E74C3C';
            } else {
                document.getElementById('tempOnValue').style.color = '';
                document.getElementById('tempOffValue').style.color = '';
            }
        }
        
        function selectMode(autoMode) {
            selectedAutoMode = autoMode;
            document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
            if (autoMode) {
                document.querySelector('.mode-btn:first-child').classList.add('active');
            } else {
                document.querySelector('.mode-btn:last-child').classList.add('active');
            }
        }
        
        async function saveSettings() {
            const tempOn = document.getElementById('tempOn').value;
            const tempOff = document.getElementById('tempOff').value;
            
            if (parseFloat(tempOn) <= parseFloat(tempOff)) {
                showToast('ON temperature must be higher than OFF temperature', true);
                return;
            }
            
            try {
                const response = await fetch('fan_control.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=update_settings&auto_mode=${selectedAutoMode}&temp_on=${tempOn}&temp_off=${tempOff}`
                });
                const data = await response.json();
                if (data.success) {
                    showToast(data.message, false);
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
                    showToast(data.message, false);
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
                    showToast(data.message, false);
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
                    showToast(data.message, false);
                    setTimeout(() => location.reload(), 500);
                }
            } catch (error) {
                showToast('Error clearing logs', true);
            }
        }
        
        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
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
        
        setInterval(updateDateTime, 1000);
        updateDateTime();
        updateTempValues();
    </script>
</body>
</html>