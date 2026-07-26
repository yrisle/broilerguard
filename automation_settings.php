<?php
// automation_settings.php - Automation Settings Module
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

// Initialize settings if not exists
if (!isset($_SESSION['fan_settings'])) {
    $_SESSION['fan_settings'] = [
        'auto_mode' => 'auto',
        'temp_threshold' => 32.0,
        'humidity_threshold' => 75,
        'schedule_start' => '08:00',
        'schedule_end' => '20:00',
        'fan_speed' => 80,
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

if (!isset($_SESSION['feed_settings'])) {
    $_SESSION['feed_settings'] = [
        'auto_mode' => 'schedule',
        'schedule_interval' => 4,
        'dispense_amount' => 0.5,
        'low_level_threshold' => 5,
        'schedule_times' => '08:00,12:00,16:00,20:00',
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

if (!isset($_SESSION['pump_settings'])) {
    $_SESSION['pump_settings'] = [
        'auto_mode' => 'auto',
        'low_level_threshold' => 25,
        'high_level_threshold' => 95,
        'pump_duration' => 45,
        'schedule_interval' => 3,
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action === 'save_all_settings') {
        // Save Fan Settings
        $_SESSION['fan_settings'] = [
            'auto_mode' => $_POST['fan_auto_mode'] ?? 'auto',
            'temp_threshold' => floatval($_POST['temp_threshold'] ?? 32),
            'humidity_threshold' => floatval($_POST['humidity_threshold'] ?? 75),
            'schedule_start' => $_POST['fan_schedule_start'] ?? '08:00',
            'schedule_end' => $_POST['fan_schedule_end'] ?? '20:00',
            'fan_speed' => intval($_POST['fan_speed'] ?? 80),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save Feed Dispenser Settings
        $_SESSION['feed_settings'] = [
            'auto_mode' => $_POST['feed_auto_mode'] ?? 'schedule',
            'schedule_interval' => intval($_POST['feed_interval'] ?? 4),
            'dispense_amount' => floatval($_POST['dispense_amount'] ?? 0.5),
            'low_level_threshold' => floatval($_POST['feed_low_threshold'] ?? 5),
            'schedule_times' => $_POST['feed_schedule_times'] ?? '08:00,12:00,16:00,20:00',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save Water Pump Settings
        $_SESSION['pump_settings'] = [
            'auto_mode' => $_POST['pump_auto_mode'] ?? 'auto',
            'low_level_threshold' => intval($_POST['pump_low_threshold'] ?? 25),
            'high_level_threshold' => intval($_POST['pump_high_threshold'] ?? 95),
            'pump_duration' => intval($_POST['pump_duration'] ?? 45),
            'schedule_interval' => intval($_POST['pump_schedule_interval'] ?? 3),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $response = ['success' => true, 'message' => 'All settings saved successfully!'];
        
    } elseif ($action === 'reset_defaults') {
        // Reset to default settings
        $_SESSION['fan_settings'] = [
            'auto_mode' => 'auto', 'temp_threshold' => 32, 'humidity_threshold' => 75,
            'schedule_start' => '08:00', 'schedule_end' => '20:00', 'fan_speed' => 80,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $_SESSION['feed_settings'] = [
            'auto_mode' => 'schedule', 'schedule_interval' => 4, 'dispense_amount' => 0.5,
            'low_level_threshold' => 5, 'schedule_times' => '08:00,12:00,16:00,20:00',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $_SESSION['pump_settings'] = [
            'auto_mode' => 'auto', 'low_level_threshold' => 25, 'high_level_threshold' => 95,
            'pump_duration' => 45, 'schedule_interval' => 3, 'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $response = ['success' => true, 'message' => 'Settings reset to defaults!'];
    }
    
    echo json_encode($response);
    exit;
}

// Get settings with defaults
function getFanSettings() {
    return $_SESSION['fan_settings'] ?? [
        'auto_mode' => 'auto', 'temp_threshold' => 32, 'humidity_threshold' => 75,
        'schedule_start' => '08:00', 'schedule_end' => '20:00', 'fan_speed' => 80,
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

function getFeedSettings() {
    return $_SESSION['feed_settings'] ?? [
        'auto_mode' => 'schedule', 'schedule_interval' => 4, 'dispense_amount' => 0.5,
        'low_level_threshold' => 5, 'schedule_times' => '08:00,12:00,16:00,20:00',
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

function getPumpSettings() {
    return $_SESSION['pump_settings'] ?? [
        'auto_mode' => 'auto', 'low_level_threshold' => 25, 'high_level_threshold' => 95,
        'pump_duration' => 45, 'schedule_interval' => 3, 'updated_at' => date('Y-m-d H:i:s')
    ];
}

$fanSettings = getFanSettings();
$feedSettings = getFeedSettings();
$pumpSettings = getPumpSettings();

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Automation Settings | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #FFFCF2;
            color: #3E2C1C;
            display: flex;
            min-height: 100vh;
        }

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
            --sidebar-hover: #7A5542;
            --sidebar-active: #8B6348;
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
            --sidebar-width: 280px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(139, 115, 30, 0.06);
            --shadow-md: 0 8px 24px rgba(139, 115, 30, 0.1);
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #6B4226 0%, #5C3D2E 40%, #4A2F1F 100%);
            color: var(--sidebar-text);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .sidebar-logo { padding: 1.5rem; border-bottom: 1px solid rgba(232, 213, 196, 0.15); text-align: center; }
        .sidebar-logo h2 { font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #FFD62E, #FFE699); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-logo .logo-icon { font-size: 2rem; color: #FFD62E; margin-bottom: 0.5rem; }

        .sidebar-user { padding: 1rem 1.5rem; border-bottom: 1px solid rgba(232, 213, 196, 0.15); display: flex; align-items: center; gap: 0.8rem; }
        .sidebar-user .avatar { width: 42px; height: 42px; border-radius: 12px; background: #FFD62E; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #3E2C1C; }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.9rem; color: var(--sidebar-text); }
        .sidebar-user .user-info .role { font-size: 0.75rem; color: var(--sidebar-muted); }

        .sidebar-nav { flex: 1; padding: 0.8rem 0; overflow-y: auto; }
        .sidebar-nav .nav-section { padding: 0.3rem 1.2rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1.2px; color: var(--sidebar-muted); margin-bottom: 0.5rem; font-weight: 700; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.7rem; padding: 0.65rem 0.8rem; color: var(--sidebar-text); text-decoration: none; border-radius: 10px; margin-bottom: 0.15rem; font-size: 0.88rem; font-weight: 500; transition: all 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255, 214, 46, 0.12); color: #FFD62E; font-weight: 600; }
        .sidebar-nav a i { width: 20px; text-align: center; }
        .sidebar-footer { padding: 1rem 1.2rem; border-top: 1px solid rgba(232, 213, 196, 0.15); margin-top: auto; }
        .sidebar-footer a { display: flex; align-items: center; gap: 0.7rem; color: var(--sidebar-muted); text-decoration: none; padding: 0.55rem 0.5rem; font-size: 0.88rem; }
        .sidebar-footer a:hover { color: #FFD62E; }

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; transition: margin-left 0.3s ease; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid rgba(255, 214, 46, 0.2); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 999; }
        .menu-toggle { display: none; font-size: 1.5rem; cursor: pointer; background: none; border: none; color: var(--text-primary); }
        .date-time span { font-size: 0.8rem; color: var(--text-muted); }
        .date-time .time { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
        .back-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid rgba(255, 214, 46, 0.3); border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 500; color: var(--text-primary); }
        .back-btn:hover { background: var(--accent-light); }

        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }

        /* Settings Grid */
        .settings-grid-main {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 1.5rem;
        }

        .settings-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(255, 214, 46, 0.2);
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
        }
        .settings-card-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding-bottom: 1rem;
            margin-bottom: 1.2rem;
            border-bottom: 2px solid rgba(255, 214, 46, 0.2);
        }
        .settings-card-header i {
            font-size: 1.5rem;
            color: var(--accent-dark);
        }
        .settings-card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .settings-card-header p {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-left: auto;
        }

        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 214, 46, 0.1);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .setting-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .setting-label i {
            width: 24px;
            color: var(--accent-dark);
            margin-right: 0.3rem;
        }
        .setting-control select, .setting-control input {
            padding: 0.5rem 0.8rem;
            border: 1px solid rgba(255, 214, 46, 0.3);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        .setting-control input:focus, .setting-control select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(255, 214, 46, 0.2);
        }

        .schedule-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .schedule-tag {
            background: var(--accent-light);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .btn-save {
            background: linear-gradient(105deg, #E6B800, #FFD62E);
            border: none;
            color: #3E2C1C;
            font-weight: 700;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .btn-save:hover {
            background: linear-gradient(105deg, #D4A017, #E6B800);
            transform: translateY(-2px);
        }
        .btn-reset {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 0.8rem 1.5rem;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-reset:hover {
            background: var(--accent-light);
        }

        .last-updated {
            text-align: right;
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 214, 46, 0.2);
        }

        input[type="range"] {
            width: 120px;
            vertical-align: middle;
        }
        input[type="range"] + output {
            margin-left: 8px;
            font-weight: 600;
            min-width: 35px;
            display: inline-block;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--green);
            color: white;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            display: none;
            align-items: center;
            gap: 0.8rem;
            z-index: 2000;
            animation: slideIn 0.3s ease;
            font-size: 0.85rem;
        }
        .toast.error { background: var(--red); }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .settings-grid-main { grid-template-columns: 1fr; }
            .setting-row { flex-direction: column; align-items: flex-start; }
        }
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
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="automation_settings.php" class="active"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a href="settings.php"><i class="fas fa-sliders-h"></i> Settings</a>
        </div>
    </nav>
    <div class="sidebar-user">
        <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?></div>
        <div class="user-info"><div class="name"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></div><div class="role">Farm Administrator</div></div>
    </div>
    <div class="sidebar-footer"><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <div class="date-time"><span id="currentDate"><?php echo $currentDate; ?></span><span class="time" id="currentTime"><?php echo $currentTime; ?></span></div>
        </div>
        <div class="header-right"><a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
    </header>

    <div class="page-content">
        <h1 class="page-title"><i class="fas fa-cog" style="color:var(--accent);"></i> Automation Settings</h1>
        <p class="page-subtitle">Configure all automation rules and thresholds for fans, feed dispenser, and water pump</p>

        <form id="settingsForm">
            <div class="settings-grid-main">
                
                <!-- Fan Control Settings -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="fas fa-fan"></i>
                        <h3>Fan Control Settings</h3>
                        <p>Updated: <?php echo date('M d, h:i A', strtotime($fanSettings['updated_at'])); ?></p>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-robot"></i> Operation Mode</div>
                        <div class="setting-control">
                            <select name="fan_auto_mode" id="fanAutoMode">
                                <option value="auto" <?php echo $fanSettings['auto_mode'] === 'auto' ? 'selected' : ''; ?>>Automatic (Sensor-based)</option>
                                <option value="manual" <?php echo $fanSettings['auto_mode'] === 'manual' ? 'selected' : ''; ?>>Manual Only</option>
                                <option value="schedule" <?php echo $fanSettings['auto_mode'] === 'schedule' ? 'selected' : ''; ?>>Schedule-based</option>
                            </select>
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-thermometer-half"></i> Temperature Threshold</div>
                        <div class="setting-control">
                            <input type="number" step="0.5" name="temp_threshold" value="<?php echo $fanSettings['temp_threshold']; ?>"> °C
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-tint"></i> Humidity Threshold</div>
                        <div class="setting-control">
                            <input type="number" step="5" name="humidity_threshold" value="<?php echo $fanSettings['humidity_threshold']; ?>"> %
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-clock"></i> Schedule Time</div>
                        <div class="setting-control">
                            <input type="time" name="fan_schedule_start" value="<?php echo $fanSettings['schedule_start']; ?>"> - 
                            <input type="time" name="fan_schedule_end" value="<?php echo $fanSettings['schedule_end']; ?>">
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-tachometer-alt"></i> Fan Speed</div>
                        <div class="setting-control">
                            <input type="range" name="fan_speed" min="0" max="100" value="<?php echo $fanSettings['fan_speed']; ?>" oninput="this.nextElementSibling.value=this.value">
                            <output><?php echo $fanSettings['fan_speed']; ?></output>%
                        </div>
                    </div>
                </div>

                <!-- Feed Dispenser Settings -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="fas fa-drumstick-bite"></i>
                        <h3>Feed Dispenser Settings</h3>
                        <p>Updated: <?php echo date('M d, h:i A', strtotime($feedSettings['updated_at'])); ?></p>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-robot"></i> Operation Mode</div>
                        <div class="setting-control">
                            <select name="feed_auto_mode">
                                <option value="manual" <?php echo $feedSettings['auto_mode'] === 'manual' ? 'selected' : ''; ?>>Manual Only</option>
                                <option value="schedule" <?php echo $feedSettings['auto_mode'] === 'schedule' ? 'selected' : ''; ?>>Schedule-based</option>
                                <option value="auto" <?php echo $feedSettings['auto_mode'] === 'auto' ? 'selected' : ''; ?>>Auto (Low-level trigger)</option>
                            </select>
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-weight-hanging"></i> Dispense Amount</div>
                        <div class="setting-control">
                            <input type="number" step="0.1" name="dispense_amount" value="<?php echo $feedSettings['dispense_amount']; ?>"> kg
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-exclamation-triangle"></i> Low Level Threshold</div>
                        <div class="setting-control">
                            <input type="number" step="0.5" name="feed_low_threshold" value="<?php echo $feedSettings['low_level_threshold']; ?>"> kg
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-calendar-alt"></i> Schedule Interval</div>
                        <div class="setting-control">
                            <input type="number" name="feed_interval" value="<?php echo $feedSettings['schedule_interval']; ?>"> hours
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-clock"></i> Feeding Times</div>
                        <div class="setting-control">
                            <input type="text" name="feed_schedule_times" value="<?php echo $feedSettings['schedule_times']; ?>" placeholder="08:00,12:00,16:00,20:00" style="width: 200px;">
                        </div>
                    </div>
                    <div class="schedule-tags">
                        <span class="schedule-tag"><i class="fas fa-sun"></i> Morning: 08:00</span>
                        <span class="schedule-tag"><i class="fas fa-cloud-sun"></i> Noon: 12:00</span>
                        <span class="schedule-tag"><i class="fas fa-cloud"></i> Afternoon: 16:00</span>
                        <span class="schedule-tag"><i class="fas fa-moon"></i> Evening: 20:00</span>
                    </div>
                </div>

                <!-- Water Pump Settings -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="fas fa-hand-holding-water"></i>
                        <h3>Water Pump Settings</h3>
                        <p>Updated: <?php echo date('M d, h:i A', strtotime($pumpSettings['updated_at'])); ?></p>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-robot"></i> Operation Mode</div>
                        <div class="setting-control">
                            <select name="pump_auto_mode">
                                <option value="auto" <?php echo $pumpSettings['auto_mode'] === 'auto' ? 'selected' : ''; ?>>Automatic (Level-based)</option>
                                <option value="manual" <?php echo $pumpSettings['auto_mode'] === 'manual' ? 'selected' : ''; ?>>Manual Only</option>
                                <option value="schedule" <?php echo $pumpSettings['auto_mode'] === 'schedule' ? 'selected' : ''; ?>>Schedule-based</option>
                            </select>
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-arrow-down"></i> Low Level Threshold</div>
                        <div class="setting-control">
                            <input type="number" step="5" name="pump_low_threshold" value="<?php echo $pumpSettings['low_level_threshold']; ?>"> %
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-arrow-up"></i> High Level Threshold</div>
                        <div class="setting-control">
                            <input type="number" step="5" name="pump_high_threshold" value="<?php echo $pumpSettings['high_level_threshold']; ?>"> %
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-hourglass-half"></i> Pump Duration</div>
                        <div class="setting-control">
                            <input type="number" step="5" name="pump_duration" value="<?php echo $pumpSettings['pump_duration']; ?>"> seconds
                        </div>
                    </div>
                    <div class="setting-row">
                        <div class="setting-label"><i class="fas fa-calendar-alt"></i> Schedule Interval</div>
                        <div class="setting-control">
                            <input type="number" name="pump_schedule_interval" value="<?php echo $pumpSettings['schedule_interval']; ?>"> hours
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="button" class="btn-reset" id="resetBtn"><i class="fas fa-undo-alt"></i> Reset to Defaults</button>
                <button type="submit" class="btn-save" id="saveBtn"><i class="fas fa-save"></i> Save All Settings</button>
            </div>
        </form>

        <div class="last-updated">
            <i class="fas fa-info-circle"></i> Changes take effect immediately. Settings are saved to your profile.
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        toastMessage.textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    document.getElementById('settingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_all_settings');
        
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Saving...';
        saveBtn.disabled = true;
        
        try {
            const response = await fetch('automation_settings.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error saving settings', true);
        } finally {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save All Settings';
            saveBtn.disabled = false;
        }
    });

    document.getElementById('resetBtn').addEventListener('click', async function() {
        if (!confirm('Reset all settings to default values? This action cannot be undone.')) return;
        
        const resetBtn = this;
        resetBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Resetting...';
        resetBtn.disabled = true;
        
        try {
            const response = await fetch('automation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=reset_defaults'
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error resetting settings', true);
        } finally {
            resetBtn.innerHTML = '<i class="fas fa-undo-alt"></i> Reset to Defaults';
            resetBtn.disabled = false;
        }
    });

    // Fan speed range display
    document.querySelectorAll('input[type="range"]').forEach(range => {
        range.addEventListener('input', function() {
            this.nextElementSibling.value = this.value;
        });
    });

    // Sidebar toggle
    document.getElementById('menuToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
    
    // Date/Time update
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();
</script>
</body>
</html>