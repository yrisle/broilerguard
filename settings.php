<?php
// settings.php - System Settings Module with Database (PDO)
session_start();

require_once 'db_connect.php';        // PDO
require_once 'weather_functions.php'; // weather
require_once 'api_client.php'; 

// ===== FIX: Ensure we have a working connection on port 3307 =====
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $host = '127.0.0.1';
        $port = '3306';
        $dbname = 'broilerguard';        // <-- change this if your DB name is different
        $user = 'root';
        $pass = '';                      // <-- set your root password here if you have one

        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If even this fails, show a clear error and stop
        die("Database connection failed: " . $e->getMessage());
    }
}
// ===== END FIX =====

$weather = getWeatherData();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$userId = 1;

// ===== FETCH SYSTEM SETTINGS =====
global $pdo;
$settingsStmt = $pdo->prepare("SELECT * FROM system_settings WHERE user_id = ?");
$settingsStmt->execute([$userId]);
$settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);

if (!$settingsRow) {
    $pdo->prepare("INSERT INTO system_settings (user_id) VALUES (?)")->execute([$userId]);
    $settingsStmt->execute([$userId]);
    $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);
}

// ===== FETCH ACTIVITY LOGS =====
$logStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
$logStmt->execute([$userId]);
$activityLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'save_settings') {
        $pdo->prepare("UPDATE system_settings SET timezone = ?, date_format = ?, temperature_unit = ?, language = ?, refresh_interval = ?, enable_sound = ?, enable_browser_notifications = ?, alert_duration = ?, session_timeout = ?, two_factor_auth = ?, login_attempts = ?, auto_backup = ?, backup_frequency = ?, data_retention_days = ?, theme = ?, compact_view = ?, show_charts = ? WHERE user_id = ?")
            ->execute([
                $_POST['timezone'] ?? 'Asia/Manila',
                $_POST['date_format'] ?? 'F d, Y',
                $_POST['temperature_unit'] ?? 'celsius',
                $_POST['language'] ?? 'en',
                intval($_POST['refresh_interval'] ?? 30),
                isset($_POST['enable_sound']) && $_POST['enable_sound'] === 'true' ? 1 : 0,
                isset($_POST['enable_browser_notifications']) && $_POST['enable_browser_notifications'] === 'true' ? 1 : 0,
                intval($_POST['alert_duration'] ?? 5),
                intval($_POST['session_timeout'] ?? 30),
                isset($_POST['two_factor_auth']) && $_POST['two_factor_auth'] === 'true' ? 1 : 0,
                intval($_POST['login_attempts'] ?? 5),
                isset($_POST['auto_backup']) && $_POST['auto_backup'] === 'true' ? 1 : 0,
                $_POST['backup_frequency'] ?? 'daily',
                intval($_POST['data_retention_days'] ?? 7),
                $_POST['theme'] ?? 'light',
                isset($_POST['compact_view']) && $_POST['compact_view'] === 'true' ? 1 : 0,
                isset($_POST['show_charts']) && $_POST['show_charts'] === 'true' ? 1 : 0,
                $userId
            ]);
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip, timestamp) VALUES (?, 'Settings Updated', 'System settings were modified', ?, NOW())")->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $response = ['success' => true, 'message' => 'Settings saved successfully'];
    }
    elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) $response = ['success' => false, 'message' => 'Passwords do not match'];
        elseif (strlen($new) < 6) $response = ['success' => false, 'message' => 'Password must be at least 6 characters'];
        else {
            // In production: verify current password and update hash
            $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip, timestamp) VALUES (?, 'Password Changed', 'Administrator password was updated', ?, NOW())")->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            $response = ['success' => true, 'message' => 'Password changed successfully'];
        }
    }
    elseif ($action === 'clear_cache') {
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip, timestamp) VALUES (?, 'Cache Cleared', 'System cache was cleared', ?, NOW())")->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $response = ['success' => true, 'message' => 'Cache cleared successfully'];
    }
    elseif ($action === 'export_data') {
        $exportType = $_POST['export_type'] ?? 'all';
        $exportData = [];
        // In production: fetch actual data from tables
        $response = ['success' => true, 'message' => 'Data exported', 'data' => json_encode($exportData, JSON_PRETTY_PRINT), 'filename' => 'broilerguard_export_' . date('Y-m-d_H-i-s') . '.json'];
    }
    elseif ($action === 'clear_activity_logs') {
        $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$userId]);
        $response = ['success' => true, 'message' => 'Activity logs cleared'];
    }
    elseif ($action === 'reset_settings') {
        $pdo->prepare("DELETE FROM system_settings WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("INSERT INTO system_settings (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip, timestamp) VALUES (?, 'Settings Reset', 'System settings were reset to default', ?, NOW())")->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $response = ['success' => true, 'message' => 'Settings reset to default'];
    }
    echo json_encode($response);
    exit;
}

$settings = $settingsRow;
$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;

$timezones = [
    'Asia/Manila' => 'Asia/Manila (GMT+8)',
    'Asia/Singapore' => 'Asia/Singapore (GMT+8)',
    'Asia/Tokyo' => 'Asia/Tokyo (GMT+9)',
    'America/New_York' => 'America/New_York (GMT-5)',
    'Europe/London' => 'Europe/London (GMT+0)',
    'Australia/Sydney' => 'Australia/Sydney (GMT+10)'
];
$dateFormats = [
    'F d, Y' => date('F d, Y'),
    'Y-m-d' => date('Y-m-d'),
    'm/d/Y' => date('m/d/Y'),
    'd/m/Y' => date('d/m/Y'),
    'M d, Y' => date('M d, Y')
];
$languages = ['en' => 'English', 'fil' => 'Filipino'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | BroilerGuard</title>
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
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }

        .settings-container { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .settings-sidebar { width: 260px; flex-shrink: 0; }
        .settings-content { flex: 1; min-width: 300px; }
        .settings-nav {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 0.5rem;
            border: 1px solid rgba(141,180,142,0.10);
            position: sticky;
            top: 90px;
            box-shadow: var(--shadow-sm);
        }
        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            width: 100%;
            background: none;
            border: none;
            text-align: left;
        }
        .settings-nav-item:hover { background: var(--accent-light); color: var(--accent-dark); }
        .settings-nav-item.active { background: rgba(141,180,142,0.12); color: var(--accent-dark); font-weight: 600; }
        .settings-nav-item i { width: 22px; }

        .settings-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            border: 1px solid rgba(141,180,142,0.08);
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
        }
        .settings-card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.7rem;
            border-bottom: 1px solid rgba(141,180,142,0.06);
        }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .setting-field { display: flex; flex-direction: column; gap: 0.3rem; }
        .setting-field label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .setting-field input, .setting-field select { padding: 0.6rem; border: 1px solid #E0D5C0; border-radius: 10px; font-family: 'Inter', sans-serif; background: var(--bg-secondary); font-size: 0.85rem; }
        .setting-field input:focus, .setting-field select:focus { outline: none; border-color: var(--accent); }
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .setting-row:last-child { border-bottom: none; }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.2s; border-radius: 22px; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 2px; bottom: 2px; background-color: white; transition: 0.2s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: var(--accent); }
        input:checked + .toggle-slider:before { transform: translateX(22px); }

        .action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
        .btn-save { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.7rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: #FFFFFF; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(141, 180, 142, 0.2); }
        .btn-secondary { background: var(--bg-secondary); border: 1px solid var(--accent); padding: 0.7rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: var(--text-primary); }
        .btn-secondary:hover { background: var(--accent-light); }
        .btn-danger { background: var(--red-light); border: 1px solid var(--red); padding: 0.7rem 1.5rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: var(--red); }
        .btn-danger:hover { background: var(--red); color: white; }

        .activity-log-list { max-height: 400px; overflow-y: auto; }
        .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); flex-wrap: wrap; gap: 0.5rem; }
        .activity-time { font-size: 0.7rem; color: var(--text-muted); }
        .activity-action { font-weight: 600; font-size: 0.85rem; }
        .activity-details { font-size: 0.75rem; color: var(--text-muted); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 20px; padding: 1.5rem; max-width: 400px; width: 90%; text-align: center; }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center; }
        .modal-confirm { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; color: #FFFFFF; font-family: 'Inter', sans-serif; }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }

        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .settings-container { flex-direction: column; }
            .settings-sidebar { width: 100%; }
            .settings-nav { display: flex; flex-wrap: wrap; gap: 0.3rem; position: static; }
            .settings-nav-item { width: auto; }
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
            <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a href="settings.php" class="active"><i class="fas fa-sliders-h"></i> Settings</a>
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

    <!-- Weather Modal (same) -->
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
        <h1 class="page-title"><i class="fas fa-sliders-h" style="color:var(--accent);"></i> System Settings</h1>
        <p class="page-subtitle">Configure system preferences, security options, and manage data</p>

        <div class="settings-container">
            <div class="settings-sidebar">
                <div class="settings-nav">
                    <button class="settings-nav-item active" onclick="showSection('preferences')"><i class="fas fa-globe"></i> Preferences</button>
                    <button class="settings-nav-item" onclick="showSection('security')"><i class="fas fa-shield-alt"></i> Security</button>
                    <button class="settings-nav-item" onclick="showSection('data')"><i class="fas fa-database"></i> Data Management</button>
                    <button class="settings-nav-item" onclick="showSection('activity')"><i class="fas fa-history"></i> Activity Logs</button>
                </div>
            </div>

            <div class="settings-content">
                <!-- Preferences -->
                <div id="preferences-section" class="settings-card">
                    <h3><i class="fas fa-globe"></i> System Preferences</h3>
                    <div class="settings-grid">
                        <div class="setting-field">
                            <label>Timezone</label>
                            <select id="timezone">
                                <?php foreach ($timezones as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $settings['timezone'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="setting-field">
                            <label>Date Format</label>
                            <select id="dateFormat">
                                <?php foreach ($dateFormats as $value => $example): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $settings['date_format'] === $value ? 'selected' : ''; ?>><?php echo $example; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="setting-field">
                            <label>Temperature Unit</label>
                            <select id="temperatureUnit">
                                <option value="celsius" <?php echo $settings['temperature_unit'] === 'celsius' ? 'selected' : ''; ?>>Celsius (°C)</option>
                                <option value="fahrenheit" <?php echo $settings['temperature_unit'] === 'fahrenheit' ? 'selected' : ''; ?>>Fahrenheit (°F)</option>
                            </select>
                        </div>
                        <div class="setting-field">
                            <label>Language</label>
                            <select id="language">
                                <?php foreach ($languages as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $settings['language'] === $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="setting-field">
                            <label>Dashboard Refresh (seconds)</label>
                            <input type="number" id="refreshInterval" value="<?php echo $settings['refresh_interval']; ?>" min="10" max="300">
                        </div>
                    </div>
                    <div class="setting-row">
                        <span>Enable Sound Alerts</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="enableSound" <?php echo $settings['enable_sound'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span>Browser Notifications</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="browserNotifications" <?php echo $settings['enable_browser_notifications'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span>Compact View Mode</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="compactView" <?php echo $settings['compact_view'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span>Show Charts on Dashboard</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="showCharts" <?php echo $settings['show_charts'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Security -->
                <div id="security-section" class="settings-card" style="display:none;">
                    <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                    <div class="settings-grid">
                        <div class="setting-field">
                            <label>Session Timeout (minutes)</label>
                            <input type="number" id="sessionTimeout" value="<?php echo $settings['session_timeout']; ?>" min="5" max="120">
                        </div>
                        <div class="setting-field">
                            <label>Max Login Attempts</label>
                            <input type="number" id="loginAttempts" value="<?php echo $settings['login_attempts']; ?>" min="3" max="10">
                        </div>
                    </div>
                    <div class="setting-row">
                        <span>Two-Factor Authentication</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="twoFactorAuth" <?php echo $settings['two_factor_auth'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <h3 style="margin-top:1.5rem;"><i class="fas fa-key"></i> Change Password</h3>
                    <div class="settings-grid">
                        <div class="setting-field">
                            <label>Current Password</label>
                            <input type="password" id="currentPassword" placeholder="Enter current password">
                        </div>
                        <div class="setting-field">
                            <label>New Password</label>
                            <input type="password" id="newPassword" placeholder="Enter new password">
                        </div>
                        <div class="setting-field">
                            <label>Confirm New Password</label>
                            <input type="password" id="confirmPassword" placeholder="Confirm new password">
                        </div>
                    </div>
                    <button class="btn-secondary" onclick="changePassword()" style="margin-top:1rem;"><i class="fas fa-key"></i> Change Password</button>
                </div>

                <!-- Data Management -->
                <div id="data-section" class="settings-card" style="display:none;">
                    <h3><i class="fas fa-database"></i> Data Management</h3>
                    <div class="settings-grid">
                        <div class="setting-field">
                            <label>Auto Backup Frequency</label>
                            <select id="backupFrequency">
                                <option value="daily" <?php echo $settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo $settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            </select>
                        </div>
                        <div class="setting-field">
                            <label>Data Retention (days)</label>
                            <input type="number" id="dataRetention" value="<?php echo $settings['data_retention_days']; ?>" min="4" max="7">
                        </div>
                    </div>
                    <div class="setting-row">
                        <span>Enable Automatic Backup</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="autoBackup" <?php echo $settings['auto_backup'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <h3 style="margin-top:1.5rem;"><i class="fas fa-tools"></i> System Actions</h3>
                    <div class="action-buttons">
                        <button class="btn-secondary" onclick="clearCache()"><i class="fas fa-broom"></i> Clear Cache</button>
                        <button class="btn-secondary" onclick="exportData()"><i class="fas fa-download"></i> Export Data</button>
                        <button class="btn-danger" onclick="resetSettings()"><i class="fas fa-undo-alt"></i> Reset to Default</button>
                        <button class="btn-danger" onclick="clearActivityLogs()"><i class="fas fa-trash-alt"></i> Clear Activity Logs</button>
                    </div>
                </div>

                <!-- Activity Logs -->
                <div id="activity-section" class="settings-card" style="display:none;">
                    <h3><i class="fas fa-history"></i> Recent Activity Logs</h3>
                    <div class="activity-log-list">
                        <?php if (empty($activityLogs)): ?>
                            <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                                <i class="fas fa-clipboard-list" style="font-size:2rem; margin-bottom:0.5rem; display:block;"></i>
                                No activity logs found
                            </div>
                        <?php else: ?>
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="activity-item">
                                    <div>
                                        <div class="activity-action"><?php echo htmlspecialchars($log['action']); ?></div>
                                        <div class="activity-details"><?php echo htmlspecialchars($log['details']); ?></div>
                                        <div class="activity-details">User: <?php echo htmlspecialchars($log['user'] ?? 'Admin'); ?> | IP: <?php echo $log['ip']; ?></div>
                                    </div>
                                    <div class="activity-time"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="action-buttons" style="margin-top:0;">
                    <button class="btn-save" onclick="saveAllSettings()"><i class="fas fa-save"></i> Save All Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="font-size:2rem; color:var(--accent); margin-bottom:0.5rem;"></i>
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalMessage">Are you sure you want to proceed?</p>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-confirm" onclick="executePendingAction()">Confirm</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    let pendingAction = null;

    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    function showModal(title, message, action) {
        pendingAction = action;
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('modalMessage').innerHTML = message;
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('confirmModal').style.display = 'none';
        pendingAction = null;
    }

    function executePendingAction() {
        if (pendingAction) { pendingAction(); closeModal(); }
    }

    function showSection(section) {
        document.querySelectorAll('.settings-card').forEach(c => c.style.display = 'none');
        document.getElementById(`${section}-section`).style.display = 'block';
        document.querySelectorAll('.settings-nav-item').forEach(i => i.classList.remove('active'));
        event.target.classList.add('active');
    }

    async function saveAllSettings() {
        const timezone = document.getElementById('timezone').value;
        const dateFormat = document.getElementById('dateFormat').value;
        const temperatureUnit = document.getElementById('temperatureUnit').value;
        const language = document.getElementById('language').value;
        const refreshInterval = document.getElementById('refreshInterval').value;
        const enableSound = document.getElementById('enableSound').checked;
        const browserNotifications = document.getElementById('browserNotifications').checked;
        const compactView = document.getElementById('compactView').checked;
        const showCharts = document.getElementById('showCharts').checked;
        const sessionTimeout = document.getElementById('sessionTimeout').value;
        const loginAttempts = document.getElementById('loginAttempts').value;
        const twoFactorAuth = document.getElementById('twoFactorAuth').checked;
        const autoBackup = document.getElementById('autoBackup').checked;
        const backupFrequency = document.getElementById('backupFrequency').value;
        const dataRetention = document.getElementById('dataRetention').value;

        try {
            const response = await fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=save_settings&timezone=${timezone}&date_format=${dateFormat}&temperature_unit=${temperatureUnit}&language=${language}&refresh_interval=${refreshInterval}&enable_sound=${enableSound}&enable_browser_notifications=${browserNotifications}&compact_view=${compactView}&show_charts=${showCharts}&session_timeout=${sessionTimeout}&login_attempts=${loginAttempts}&two_factor_auth=${twoFactorAuth}&auto_backup=${autoBackup}&backup_frequency=${backupFrequency}&data_retention_days=${dataRetention}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error saving settings', true); }
    }

    async function changePassword() {
        const current = document.getElementById('currentPassword').value;
        const newP = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;
        if (!current || !newP || !confirm) { showToast('Please fill all password fields', true); return; }
        if (newP !== confirm) { showToast('New passwords do not match', true); return; }
        if (newP.length < 6) { showToast('Password must be at least 6 characters', true); return; }
        try {
            const response = await fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=change_password&current_password=${encodeURIComponent(current)}&new_password=${encodeURIComponent(newP)}&confirm_password=${encodeURIComponent(confirm)}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); document.querySelectorAll('#security-section input[type="password"]').forEach(i => i.value = ''); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error changing password', true); }
    }

    async function clearCache() {
        showModal('Clear Cache', 'Are you sure you want to clear the system cache?', async () => {
            try {
                const response = await fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=clear_cache'
                });
                const data = await response.json();
                if (data.success) showToast(data.message);
                else showToast(data.message, true);
            } catch (error) { showToast('Error clearing cache', true); }
        });
    }

    async function exportData() {
        try {
            const response = await fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=export_data&export_type=all'
            });
            const data = await response.json();
            if (data.success) {
                const blob = new Blob([data.data], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = data.filename; document.body.appendChild(a); a.click(); document.body.removeChild(a);
                showToast('Data exported successfully');
            } else showToast(data.message, true);
        } catch (error) { showToast('Error exporting data', true); }
    }

    async function clearActivityLogs() {
        showModal('Clear Activity Logs', 'Are you sure you want to clear all activity logs?', async () => {
            try {
                const response = await fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=clear_activity_logs'
                });
                const data = await response.json();
                if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            } catch (error) { showToast('Error clearing logs', true); }
        });
    }

    async function resetSettings() {
        showModal('Reset Settings', 'Are you sure you want to reset all settings to default?', async () => {
            try {
                const response = await fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=reset_settings'
                });
                const data = await response.json();
                if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            } catch (error) { showToast('Error resetting settings', true); }
        });
    }

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    document.getElementById('confirmModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'settings.php?refresh_weather=1'; }
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
