<?php
// automation_settings.php - Automation Settings Module (with Database - PDO)
session_start();

require_once 'db_connect.php';        // PDO
require_once 'weather_functions.php'; // weather
require_once 'api_client.php'; 

$weather = getWeatherData();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$userId = 1;


// ===== FETCH SETTINGS FROM DATABASE =====
global $pdo;

// Fan Settings
$fanStmt = $pdo->prepare("SELECT * FROM fan_settings WHERE user_id = ?");
$fanStmt->execute([$userId]);
$fanSettings = $fanStmt->fetch(PDO::FETCH_ASSOC);
if (!$fanSettings) {
    $pdo->prepare("INSERT INTO fan_settings (user_id, auto_mode, temp_threshold, humidity_threshold, schedule_start, schedule_end, fan_speed) VALUES (?, 'auto', 32.0, 75.0, '08:00', '20:00', 80)")->execute([$userId]);
    $fanStmt->execute([$userId]);
    $fanSettings = $fanStmt->fetch(PDO::FETCH_ASSOC);
}

// Feed Settings
$feedStmt = $pdo->prepare("SELECT * FROM feed_settings WHERE user_id = ?");
$feedStmt->execute([$userId]);
$feedSettings = $feedStmt->fetch(PDO::FETCH_ASSOC);
if (!$feedSettings) {
    $pdo->prepare("INSERT INTO feed_settings (user_id, auto_mode, schedule_interval, dispense_amount, low_level_threshold, schedule_times) VALUES (?, 'schedule', 4, 0.5, 5.0, '08:00,12:00,16:00,20:00')")->execute([$userId]);
    $feedStmt->execute([$userId]);
    $feedSettings = $feedStmt->fetch(PDO::FETCH_ASSOC);
}

// Pump Settings
$pumpStmt = $pdo->prepare("SELECT * FROM pump_settings WHERE user_id = ?");
$pumpStmt->execute([$userId]);
$pumpSettings = $pumpStmt->fetch(PDO::FETCH_ASSOC);
if (!$pumpSettings) {
    $pdo->prepare("INSERT INTO pump_settings (user_id, auto_mode, low_level_threshold, high_level_threshold, pump_duration, schedule_interval) VALUES (?, 'auto', 25, 95, 45, 3)")->execute([$userId]);
    $pumpStmt->execute([$userId]);
    $pumpSettings = $pumpStmt->fetch(PDO::FETCH_ASSOC);
}

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'save_all_settings') {
        // Fan Settings
        $fanUpdate = $pdo->prepare("UPDATE fan_settings SET auto_mode = ?, temp_threshold = ?, humidity_threshold = ?, schedule_start = ?, schedule_end = ?, fan_speed = ?, updated_at = NOW() WHERE user_id = ?");
        $fanUpdate->execute([
            $_POST['fan_auto_mode'] ?? 'auto',
            floatval($_POST['temp_threshold'] ?? 32),
            floatval($_POST['humidity_threshold'] ?? 75),
            $_POST['fan_schedule_start'] ?? '08:00',
            $_POST['fan_schedule_end'] ?? '20:00',
            intval($_POST['fan_speed'] ?? 80),
            $userId
        ]);

        // Feed Settings
        $feedUpdate = $pdo->prepare("UPDATE feed_settings SET auto_mode = ?, schedule_interval = ?, dispense_amount = ?, low_level_threshold = ?, schedule_times = ?, updated_at = NOW() WHERE user_id = ?");
        $feedUpdate->execute([
            $_POST['feed_auto_mode'] ?? 'schedule',
            intval($_POST['feed_interval'] ?? 4),
            floatval($_POST['dispense_amount'] ?? 0.5),
            floatval($_POST['feed_low_threshold'] ?? 5),
            $_POST['feed_schedule_times'] ?? '08:00,12:00,16:00,20:00',
            $userId
        ]);

        // Pump Settings
        $pumpUpdate = $pdo->prepare("UPDATE pump_settings SET auto_mode = ?, low_level_threshold = ?, high_level_threshold = ?, pump_duration = ?, schedule_interval = ?, updated_at = NOW() WHERE user_id = ?");
        $pumpUpdate->execute([
            $_POST['pump_auto_mode'] ?? 'auto',
            intval($_POST['pump_low_threshold'] ?? 25),
            intval($_POST['pump_high_threshold'] ?? 95),
            intval($_POST['pump_duration'] ?? 45),
            intval($_POST['pump_schedule_interval'] ?? 3),
            $userId
        ]);

        $response = ['success' => true, 'message' => 'All settings saved successfully!'];
    }
    elseif ($action === 'reset_defaults') {
        $pdo->prepare("UPDATE fan_settings SET auto_mode = 'auto', temp_threshold = 32.0, humidity_threshold = 75.0, schedule_start = '08:00', schedule_end = '20:00', fan_speed = 80, updated_at = NOW() WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE feed_settings SET auto_mode = 'schedule', schedule_interval = 4, dispense_amount = 0.5, low_level_threshold = 5.0, schedule_times = '08:00,12:00,16:00,20:00', updated_at = NOW() WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE pump_settings SET auto_mode = 'auto', low_level_threshold = 25, high_level_threshold = 95, pump_duration = 45, schedule_interval = 3, updated_at = NOW() WHERE user_id = ?")->execute([$userId]);
        $response = ['success' => true, 'message' => 'Settings reset to defaults!'];
    }

    echo json_encode($response);
    exit;
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automation Settings | BroilerGuard</title>
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

        .settings-grid-main { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; }
        .settings-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.10); transition: all 0.3s ease; }
        .settings-card:hover { box-shadow: var(--shadow-md); border-color: var(--accent); }
        .settings-card-header { display: flex; align-items: center; gap: 0.8rem; padding-bottom: 1rem; margin-bottom: 1.2rem; border-bottom: 2px solid rgba(141,180,142,0.15); }
        .settings-card-header i { font-size: 1.5rem; color: var(--accent-dark); }
        .settings-card-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
        .settings-card-header p { font-size: 0.65rem; color: var(--text-muted); margin-left: auto; }

        .setting-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(141,180,142,0.06); flex-wrap: wrap; gap: 0.5rem; }
        .setting-label { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }
        .setting-label i { width: 24px; color: var(--accent-dark); margin-right: 0.3rem; }
        .setting-control select, .setting-control input { padding: 0.5rem 0.8rem; border: 1px solid rgba(141,180,142,0.2); border-radius: 8px; font-family: 'Inter', sans-serif; background: var(--bg-secondary); font-size: 0.85rem; color: var(--text-primary); }
        .setting-control input:focus, .setting-control select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px rgba(141,180,142,0.15); }

        .schedule-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .schedule-tag { background: var(--accent-light); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); }

        .action-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: flex-end; flex-wrap: wrap; }
        .btn-save { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; color: #FFFFFF; font-weight: 700; padding: 0.7rem 1.2rem; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(77, 114, 77, 0.2); }
        .btn-reset { background: transparent; border: 1px solid var(--accent); color: var(--text-secondary); font-weight: 600; padding: 0.7rem 1.2rem; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .btn-reset:hover { background: var(--accent-light); }

        .last-updated { text-align: right; font-size: 0.7rem; color: var(--text-muted); margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(141,180,142,0.10); }

        input[type="range"] { width: 120px; vertical-align: middle; accent-color: var(--accent); }
        input[type="range"] + output { margin-left: 8px; font-weight: 600; min-width: 35px; display: inline-block; }

        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .settings-grid-main { grid-template-columns: 1fr; }
            .setting-row { flex-direction: column; align-items: flex-start; }
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
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
             <a href="light_control.php" class=""><i class="fas fa-lightbulb"></i> Light Control</a>
             <a href="automation_settings.php" class="active"><i class="fas fa-cog"></i> Automation Settings</a>
            
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
        <h1 class="page-title"><i class="fas fa-cog" style="color:var(--accent);"></i> Automation Settings</h1>
        <p class="page-subtitle">Configure all automation rules and thresholds for fans, feed dispenser, and water pump</p>

        <form id="settingsForm">
            <div class="settings-grid-main">
                <!-- Fan Settings -->
                <div class="settings-card">
                    <div class="settings-card-header">
                        <i class="fas fa-fan"></i>
                        <h3>Fan Control Settings</h3>
                        <p>Updated: <?php echo date('M d, h:i A', strtotime($fanSettings['updated_at'] ?? 'now')); ?></p>
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
                        <p>Updated: <?php echo date('M d, h:i A', strtotime($feedSettings['updated_at'] ?? 'now')); ?></p>
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
                            <input type="text" name="feed_schedule_times" value="<?php echo $feedSettings['schedule_times']; ?>" placeholder="08:00,12:00,16:00,20:00" style="width:200px;">
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
                        <p>Updated: <?php echo date('M d, h:i A', strtotime($pumpSettings['updated_at'] ?? 'now')); ?></p>
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
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
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
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 1000); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error saving settings', true); }
        finally { saveBtn.innerHTML = '<i class="fas fa-save"></i> Save All Settings'; saveBtn.disabled = false; }
    });

    document.getElementById('resetBtn').addEventListener('click', async function() {
        if (!confirm('Reset all settings to default values?')) return;
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
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 1000); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error resetting settings', true); }
        finally { resetBtn.innerHTML = '<i class="fas fa-undo-alt"></i> Reset to Defaults'; resetBtn.disabled = false; }
    });

    document.querySelectorAll('input[type="range"]').forEach(range => {
        range.addEventListener('input', function() { this.nextElementSibling.value = this.value; });
    });

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }
    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'automation_settings.php?refresh_weather=1'; }
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
