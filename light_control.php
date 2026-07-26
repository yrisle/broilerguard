<?php
// light_control.php - Light/Bulb Control Module for Broiler Chicks
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

// Initialize light settings if not exists
if (!isset($_SESSION['light_settings'])) {
    $_SESSION['light_settings'] = [
        'status' => 'OFF',
        'mode' => 'manual', // manual, schedule, auto
        'brightness' => 100,
        'schedule_on' => '06:00',
        'schedule_off' => '18:00',
        'auto_temp_threshold' => 30,
        'last_changed' => date('Y-m-d H:i:s')
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action === 'toggle_light') {
        $currentStatus = $_SESSION['light_settings']['status'];
        $_SESSION['light_settings']['status'] = $currentStatus === 'ON' ? 'OFF' : 'ON';
        $_SESSION['light_settings']['last_changed'] = date('Y-m-d H:i:s');
        
        // Log activity
        logLightActivity('Light Toggled', 'Light turned ' . $_SESSION['light_settings']['status']);
        
        $response = [
            'success' => true, 
            'message' => 'Light ' . $_SESSION['light_settings']['status'],
            'status' => $_SESSION['light_settings']['status']
        ];
        
    } elseif ($action === 'update_settings') {
        $_SESSION['light_settings']['mode'] = $_POST['mode'] ?? 'manual';
        $_SESSION['light_settings']['brightness'] = intval($_POST['brightness'] ?? 100);
        $_SESSION['light_settings']['schedule_on'] = $_POST['schedule_on'] ?? '06:00';
        $_SESSION['light_settings']['schedule_off'] = $_POST['schedule_off'] ?? '18:00';
        $_SESSION['light_settings']['auto_temp_threshold'] = floatval($_POST['auto_temp_threshold'] ?? 30);
        $_SESSION['light_settings']['last_changed'] = date('Y-m-d H:i:s');
        
        logLightActivity('Settings Updated', 'Light settings were modified');
        $response = ['success' => true, 'message' => 'Settings saved successfully'];
        
    } elseif ($action === 'get_status') {
        $response = [
            'success' => true,
            'status' => $_SESSION['light_settings']['status'],
            'mode' => $_SESSION['light_settings']['mode'],
            'brightness' => $_SESSION['light_settings']['brightness']
        ];
    }
    
    echo json_encode($response);
    exit;
}

function logLightActivity($action, $details) {
    if (!isset($_SESSION['light_logs'])) {
        $_SESSION['light_logs'] = [];
    }
    
    $log = [
        'id' => uniqid(),
        'action' => $action,
        'details' => $details,
        'user' => $_SESSION['admin_username'] ?? 'Admin',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    array_unshift($_SESSION['light_logs'], $log);
    
    if (count($_SESSION['light_logs']) > 50) {
        $_SESSION['light_logs'] = array_slice($_SESSION['light_logs'], 0, 50);
    }
}

$settings = $_SESSION['light_settings'];
$logs = $_SESSION['light_logs'] ?? [];
$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;

// Get temperature for auto mode
$temp = 29.5;
if (isset($_SESSION['shared_sensor_data'])) {
    $temp = $_SESSION['shared_sensor_data']['temperature'] ?? 29.5;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Light Control | BroilerGuard</title>
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
            --purple: #8E44AD;
            --purple-light: #F4ECF7;
            --sidebar-width: 300px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(139, 115, 30, 0.06);
            --shadow-md: 0 8px 24px rgba(139, 115, 30, 0.1);
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
            background: linear-gradient(180deg, #6B4226 0%, #5C3D2E 40%, #4A2F1F 100%);
            color: var(--sidebar-text);
            z-index: 1000;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 4px 0 30px rgba(0,0,0,0.25);
        }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar { scrollbar-width: none; -ms-overflow-style: none; }
        
        .sidebar-logo { padding: 2rem 1.8rem; border-bottom: 1px solid rgba(232, 213, 196, 0.12); text-align: center; flex-shrink: 0; }
        .sidebar-logo h2 { font-size: 1.7rem; font-weight: 800; background: linear-gradient(135deg, #FFD62E, #FFE699); -webkit-background-clip: text; background-clip: text; color: transparent; letter-spacing: -0.5px; }
        .sidebar-logo .logo-icon { font-size: 2.4rem; color: #FFD62E; margin-bottom: 0.5rem; display: block; }
        
        .sidebar-nav { flex: 1; padding: 1rem 1rem 1rem 1.2rem; overflow-y: auto; overflow-x: hidden; scrollbar-width: none; -ms-overflow-style: none; }
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav .nav-section { padding: 0.2rem 0.3rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 1.8px; color: var(--sidebar-muted); margin-bottom: 0.5rem; font-weight: 700; padding-left: 0.5rem; opacity: 0.8; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.9rem; padding: 0.7rem 1rem; color: var(--sidebar-text); text-decoration: none; border-radius: 12px; margin-bottom: 0.1rem; transition: all 0.25s ease; font-size: 0.88rem; font-weight: 500; position: relative; }
        .sidebar-nav a:hover { background: rgba(255, 214, 46, 0.08); color: #FFD62E; transform: translateX(4px); }
        .sidebar-nav a.active { background: rgba(255, 214, 46, 0.12); color: #FFD62E; font-weight: 600; }
        .sidebar-nav a.active::before { content: ''; position: absolute; left: -0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 50%; background: #FFD62E; border-radius: 4px; }
        .sidebar-nav a i { width: 24px; text-align: center; font-size: 1rem; flex-shrink: 0; }
        .sidebar-nav a .badge-sidebar { margin-left: auto; background: var(--red); color: white; font-size: 0.6rem; padding: 0.1rem 0.5rem; border-radius: 12px; font-weight: 600; }
        
        .sidebar-user { padding: 1rem 1.8rem; border-top: 1px solid rgba(232, 213, 196, 0.12); display: flex; align-items: center; gap: 1rem; flex-shrink: 0; background: rgba(0,0,0,0.15); }
        .sidebar-user .avatar { width: 46px; height: 46px; border-radius: 14px; background: #FFD62E; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #3E2C1C; font-size: 1.2rem; flex-shrink: 0; }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.95rem; color: var(--sidebar-text); }
        .sidebar-user .user-info .role { font-size: 0.7rem; color: var(--sidebar-muted); }
        
        .sidebar-footer { padding: 0.5rem 1.5rem 1.2rem; border-top: 1px solid rgba(232, 213, 196, 0.08); flex-shrink: 0; }
        .sidebar-footer a { display: flex; align-items: center; gap: 0.8rem; color: var(--sidebar-muted); text-decoration: none; padding: 0.5rem 0.8rem; font-size: 0.85rem; transition: all 0.2s; border-radius: 12px; }
        .sidebar-footer a:hover { color: #FFD62E; background: rgba(255, 214, 46, 0.05); transform: translateX(4px); }
        
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; backdrop-filter: blur(4px); }
        .sidebar-overlay.active { display: block; }
        
        /* ===== MAIN CONTENT ===== */
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1); overflow-x: hidden; }
        
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid rgba(255, 214, 46, 0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 999; box-shadow: var(--shadow-sm); }
        .top-header .header-left { display: flex; align-items: center; gap: 1.5rem; }
        .top-header .menu-toggle { display: none; font-size: 1.6rem; cursor: pointer; color: var(--text-primary); background: none; border: none; padding: 0.4rem 0.8rem; border-radius: 10px; transition: background 0.2s; }
        .top-header .menu-toggle:hover { background: var(--bg-secondary); }
        .top-header .date-time { display: flex; flex-direction: column; gap: 0.05rem; }
        .top-header .date-time .date { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px; }
        .top-header .date-time .time { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        
        .notification-bell { position: relative; background: var(--bg-secondary); border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; border: 1px solid rgba(255, 214, 46, 0.25); text-decoration: none; }
        .notification-bell:hover { background: var(--accent-light); transform: scale(1.05); }
        .notification-bell i { font-size: 1.2rem; color: var(--text-secondary); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--red); color: white; font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 50%; min-width: 20px; text-align: center; }
        
        .page-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }
        
        /* ===== LIGHT CONTROL CARD ===== */
        .light-control-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .control-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-md); border: 1px solid rgba(255, 214, 46, 0.1); text-align: center; transition: all 0.3s; }
        .control-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        
        .light-bulb { font-size: 5rem; margin-bottom: 1rem; transition: all 0.3s; }
        .light-bulb.on { color: #FFD62E; text-shadow: 0 0 40px rgba(255, 214, 46, 0.5), 0 0 80px rgba(255, 214, 46, 0.2); }
        .light-bulb.off { color: #95A5A6; }
        
        .light-status { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .light-status.on { color: var(--green); }
        .light-status.off { color: var(--red); }
        
        .toggle-btn { 
            padding: 0.8rem 2.5rem; 
            border: none; 
            border-radius: 30px; 
            font-size: 1rem; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            margin-top: 0.5rem;
        }
        .toggle-btn.on { background: linear-gradient(135deg, #FFD62E, #E6B800); color: #3E2C1C; }
        .toggle-btn.on:hover { transform: scale(1.05); box-shadow: 0 4px 20px rgba(255, 214, 46, 0.4); }
        .toggle-btn.off { background: #95A5A6; color: white; }
        .toggle-btn.off:hover { transform: scale(1.05); }
        
        .brightness-control { margin-top: 1rem; }
        .brightness-control input[type="range"] { width: 100%; max-width: 300px; accent-color: #FFD62E; }
        .brightness-label { font-size: 0.8rem; color: var(--text-muted); }
        
        /* ===== MODE CARDS ===== */
        .mode-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .mode-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem; box-shadow: var(--shadow-sm); border: 2px solid transparent; transition: all 0.3s; cursor: pointer; text-align: center; }
        .mode-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .mode-card.active { border-color: var(--accent); background: var(--accent-light); }
        .mode-card .mode-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .mode-card .mode-name { font-weight: 700; font-size: 0.9rem; }
        .mode-card .mode-desc { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        /* ===== SCHEDULE SETTINGS ===== */
        .settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
        .settings-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.1); }
        .settings-card h3 { font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.04); }
        .setting-row:last-child { border-bottom: none; }
        .setting-label { font-size: 0.85rem; color: var(--text-secondary); }
        .setting-input { padding: 0.3rem 0.6rem; border: 1px solid rgba(255, 214, 46, 0.3); border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.8rem; background: var(--bg-secondary); width: 120px; }
        .setting-input:focus { outline: none; border-color: var(--accent); }
        
        .save-btn { background: linear-gradient(105deg, #E6B800, #FFD62E); border: none; padding: 0.6rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: #3E2C1C; font-family: 'Inter', sans-serif; font-size: 0.9rem; transition: all 0.2s; width: 100%; margin-top: 0.5rem; }
        .save-btn:hover { background: linear-gradient(105deg, #D4A017, #E6B800); transform: translateY(-2px); }
        
        /* ===== LOGS ===== */
        .log-list { max-height: 200px; overflow-y: auto; }
        .log-item { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid rgba(0,0,0,0.04); font-size: 0.8rem; }
        .log-item .log-time { color: var(--text-muted); font-size: 0.7rem; }
        
        /* ===== TOAST ===== */
        .toast { position: fixed; bottom: 20px; right: 20px; background: #27AE60; color: white; padding: 0.7rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.8rem; box-shadow: var(--shadow-lg); }
        .toast.error { background: #E74C3C; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            .light-control-grid { grid-template-columns: 1fr; }
            .mode-grid { grid-template-columns: 1fr; }
            .settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .setting-row { flex-direction: column; align-items: flex-start; gap: 0.3rem; }
            .setting-input { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon"><i class="fas fa-feather-alt"></i></span>
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
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="light_control.php" class="active"><i class="fas fa-lightbulb"></i> Light Control</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <span class="badge-sidebar"><?php echo $unreadNotifications; ?></span></a>
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

<!-- MAIN CONTENT -->
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
        <h1 class="page-title"><i class="fas fa-lightbulb" style="color:var(--accent-dark);"></i> Light Control</h1>
        <p class="page-subtitle">Manage lighting system for broiler chicks - essential for growth and development</p>

        <!-- Light Control Main -->
        <div class="light-control-grid">
            <div class="control-card">
                <div class="light-bulb <?php echo $settings['status'] === 'ON' ? 'on' : 'off'; ?>">
                    <i class="fas fa-<?php echo $settings['status'] === 'ON' ? 'lightbulb' : 'lightbulb'; ?>"></i>
                </div>
                <div class="light-status <?php echo strtolower($settings['status']); ?>">
                    <?php echo $settings['status']; ?>
                </div>
                <button class="toggle-btn <?php echo strtolower($settings['status']); ?>" onclick="toggleLight()">
                    <i class="fas fa-<?php echo $settings['status'] === 'ON' ? 'power-off' : 'play'; ?>"></i>
                    Turn <?php echo $settings['status'] === 'ON' ? 'OFF' : 'ON'; ?>
                </button>
                
                <div class="brightness-control">
                    <div class="brightness-label">
                        <i class="fas fa-circle" style="color:#95A5A6;"></i>
                        Brightness: <span id="brightnessDisplay"><?php echo $settings['brightness']; ?>%</span>
                        <i class="fas fa-circle" style="color:#FFD62E;"></i>
                    </div>
                    <input type="range" min="10" max="100" value="<?php echo $settings['brightness']; ?>" 
                           oninput="updateBrightness(this.value)" id="brightnessSlider">
                </div>
            </div>

            <div class="control-card" style="background: linear-gradient(135deg, #FFF8E0, #FFFCF2);">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">
                    <i class="fas fa-thermometer-half" style="color:var(--orange);"></i>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--orange);"><?php echo $temp; ?>°C</div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">Current Temperature</div>
                <div style="margin-top: 0.8rem; font-size: 0.8rem; color: var(--text-muted);">
                    <i class="fas fa-clock"></i> Last changed: <?php echo $settings['last_changed']; ?>
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.75rem; background: var(--accent-light); padding: 0.5rem; border-radius: 8px;">
                    <i class="fas fa-info-circle" style="color:var(--accent-dark);"></i>
                    Optimal lighting: 18-24 hours for broiler chicks
                </div>
            </div>
        </div>

        <!-- Mode Selection -->
        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin-bottom: 0.8rem; font-weight: 700;">
            <i class="fas fa-cog"></i> Control Mode
        </div>
        <div class="mode-grid">
            <div class="mode-card <?php echo $settings['mode'] === 'manual' ? 'active' : ''; ?>" onclick="setMode('manual')">
                <div class="mode-icon"><i class="fas fa-hand"></i></div>
                <div class="mode-name">Manual</div>
                <div class="mode-desc">Manually toggle on/off</div>
            </div>
            <div class="mode-card <?php echo $settings['mode'] === 'schedule' ? 'active' : ''; ?>" onclick="setMode('schedule')">
                <div class="mode-icon"><i class="fas fa-clock"></i></div>
                <div class="mode-name">Schedule</div>
                <div class="mode-desc">Auto on/off at set times</div>
            </div>
            <div class="mode-card <?php echo $settings['mode'] === 'auto' ? 'active' : ''; ?>" onclick="setMode('auto')">
                <div class="mode-icon"><i class="fas fa-robot"></i></div>
                <div class="mode-name">Auto (Temp-based)</div>
                <div class="mode-desc">Activates based on temperature</div>
            </div>
        </div>

        <!-- Settings -->
        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin-bottom: 0.8rem; font-weight: 700;">
            <i class="fas fa-sliders-h"></i> Light Settings
        </div>
        <div class="settings-grid">
            <div class="settings-card">
                <h3><i class="fas fa-clock" style="color:var(--blue);"></i> Schedule Settings</h3>
                <div class="setting-row">
                    <span class="setting-label">Turn ON Time</span>
                    <input type="time" class="setting-input" id="scheduleOn" value="<?php echo $settings['schedule_on']; ?>">
                </div>
                <div class="setting-row">
                    <span class="setting-label">Turn OFF Time</span>
                    <input type="time" class="setting-input" id="scheduleOff" value="<?php echo $settings['schedule_off']; ?>">
                </div>
                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Recommended: 6:00 AM - 6:00 PM (12 hours)
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-thermometer-half" style="color:var(--orange);"></i> Auto Mode Settings</h3>
                <div class="setting-row">
                    <span class="setting-label">Temp Threshold (°C)</span>
                    <input type="number" step="0.5" class="setting-input" id="tempThreshold" value="<?php echo $settings['auto_temp_threshold']; ?>">
                </div>
                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Light turns ON when temp exceeds threshold
                </div>
            </div>
        </div>

        <button class="save-btn" onclick="saveSettings()"><i class="fas fa-save"></i> Save All Settings</button>

        <!-- Activity Logs -->
        <div class="settings-card" style="margin-top: 1.5rem;">
            <h3><i class="fas fa-history" style="color:var(--purple);"></i> Light Activity Logs</h3>
            <div class="log-list">
                <?php if (empty($logs)): ?>
                    <div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.8rem;">No logs available</div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <div class="log-item">
                        <span><?php echo htmlspecialchars($log['action']); ?> - <?php echo htmlspecialchars($log['details']); ?></span>
                        <span class="log-time"><?php echo $log['timestamp']; ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    // ===== CLOCK =====
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

    // ===== TOAST =====
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    // ===== LIGHT CONTROL =====
    async function toggleLight() {
        try {
            const response = await fetch('light_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=toggle_light'
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 500);
            }
        } catch (error) {
            showToast('Error toggling light', true);
        }
    }

    function updateBrightness(value) {
        document.getElementById('brightnessDisplay').textContent = value + '%';
    }

    async function saveSettings() {
        const mode = document.querySelector('.mode-card.active')?.dataset?.mode || 'manual';
        const brightness = document.getElementById('brightnessSlider').value;
        const scheduleOn = document.getElementById('scheduleOn').value;
        const scheduleOff = document.getElementById('scheduleOff').value;
        const tempThreshold = document.getElementById('tempThreshold').value;

        try {
            const response = await fetch('light_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_settings&mode=${mode}&brightness=${brightness}&schedule_on=${scheduleOn}&schedule_off=${scheduleOff}&auto_temp_threshold=${tempThreshold}`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error saving settings', true);
        }
    }

    function setMode(mode) {
        document.querySelectorAll('.mode-card').forEach(card => {
            card.classList.remove('active');
        });
        document.querySelector(`.mode-card[onclick="setMode('${mode}')"]`).classList.add('active');
    }

    // Auto-save mode selection
    document.querySelectorAll('.mode-card').forEach(card => {
        card.addEventListener('click', function() {
            const mode = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            // Save mode immediately
            const brightness = document.getElementById('brightnessSlider').value;
            const scheduleOn = document.getElementById('scheduleOn').value;
            const scheduleOff = document.getElementById('scheduleOff').value;
            const tempThreshold = document.getElementById('tempThreshold').value;

            fetch('light_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_settings&mode=${mode}&brightness=${brightness}&schedule_on=${scheduleOn}&schedule_off=${scheduleOff}&auto_temp_threshold=${tempThreshold}`
            });
        });
    });
</script>
</body>
</html>