<?php
// notifications.php - Notification Center with Database (PDO)
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

// ===== FETCH NOTIFICATIONS FROM DATABASE =====
global $pdo;
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
$notifStmt->execute([$userId]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

// If no notifications, create sample notifications
if (empty($notifications)) {
    $sampleNotifs = [
        ['title' => 'Welcome to BroilerGuard', 'message' => 'Your farm management system is now ready.', 'type' => 'success'],
        ['title' => 'Temperature Check', 'message' => 'Current temperature is 32.5°C. Normal range is 20-35°C.', 'type' => 'info'],
        ['title' => 'Feed Level Alert', 'message' => 'Feed level is at 15.2 kg. Consider refilling soon.', 'type' => 'warning'],
        ['title' => 'Water Level Critical', 'message' => 'Water level is at 18%. Immediate refill recommended.', 'type' => 'danger']
    ];
    foreach ($sampleNotifs as $s) {
        // Use backticks around `read` since it's a reserved keyword
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, timestamp, `read`) VALUES (?, ?, ?, ?, NOW(), 0)");
        $stmt->execute([$userId, $s['title'], $s['message'], $s['type']]);
    }
    $notifStmt->execute([$userId]);
    $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== FETCH UNREAD COUNT =====
$unreadStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0");
$unreadStmt->execute([$userId]);
$unreadCount = (int)$unreadStmt->fetch(PDO::FETCH_ASSOC)['count'];

// ===== FETCH NOTIFICATION SETTINGS =====
$settingsStmt = $pdo->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
$settingsStmt->execute([$userId]);
$settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);

if (!$settingsRow) {
    $pdo->prepare("INSERT INTO notification_settings (user_id, browser_enabled, sound_enabled, alert_temp_high, temp_high_threshold, alert_temp_low, temp_low_threshold, alert_humidity, humidity_threshold, alert_feed_low, feed_low_threshold, alert_water_low, water_low_threshold) VALUES (?, 1, 1, 1, 35.0, 1, 20.0, 1, 80.0, 1, 10.0, 1, 20)")->execute([$userId]);
    $settingsStmt->execute([$userId]);
    $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);
}

// ===== GET SENSOR DATA =====
function getSensorData() {
    $hour = (int)date('H');
    $baseTemp = 28;
    $tempVariation = sin(($hour - 14) * M_PI / 12) * 4;
    return [
        'temperature' => round($baseTemp + $tempVariation + mt_rand(-2, 2) / 10, 1),
        'humidity' => round(55 + sin($hour * M_PI / 12) * 15 + mt_rand(-3, 3), 1),
        'feed_level' => round(30 + mt_rand(0, 20), 1),
        'water_level' => round(50 + mt_rand(0, 30), 1)
    ];
}
$sensorData = getSensorData();

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'mark_read') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        $response = ['success' => true, 'message' => 'Marked as read'];
    }
    elseif ($action === 'mark_all_read') {
        $pdo->prepare("UPDATE notifications SET `read` = 1 WHERE user_id = ?")->execute([$userId]);
        $response = ['success' => true, 'message' => 'All marked as read'];
    }
    elseif ($action === 'delete_notification') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        $response = ['success' => true, 'message' => 'Notification deleted'];
    }
    elseif ($action === 'delete_all') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
        $response = ['success' => true, 'message' => 'All notifications cleared'];
    }
    elseif ($action === 'update_settings') {
        $pdo->prepare("UPDATE notification_settings SET browser_enabled = ?, sound_enabled = ?, alert_temp_high = ?, temp_high_threshold = ?, alert_temp_low = ?, temp_low_threshold = ?, alert_humidity = ?, humidity_threshold = ?, alert_feed_low = ?, feed_low_threshold = ?, alert_water_low = ?, water_low_threshold = ? WHERE user_id = ?")
            ->execute([
                isset($_POST['browser_enabled']) && $_POST['browser_enabled'] === 'true' ? 1 : 0,
                isset($_POST['sound_enabled']) && $_POST['sound_enabled'] === 'true' ? 1 : 0,
                isset($_POST['alert_temp_high']) && $_POST['alert_temp_high'] === 'true' ? 1 : 0,
                floatval($_POST['temp_high_threshold'] ?? 35),
                isset($_POST['alert_temp_low']) && $_POST['alert_temp_low'] === 'true' ? 1 : 0,
                floatval($_POST['temp_low_threshold'] ?? 20),
                isset($_POST['alert_humidity']) && $_POST['alert_humidity'] === 'true' ? 1 : 0,
                floatval($_POST['humidity_threshold'] ?? 80),
                isset($_POST['alert_feed_low']) && $_POST['alert_feed_low'] === 'true' ? 1 : 0,
                floatval($_POST['feed_low_threshold'] ?? 10),
                isset($_POST['alert_water_low']) && $_POST['alert_water_low'] === 'true' ? 1 : 0,
                intval($_POST['water_low_threshold'] ?? 20),
                $userId
            ]);
        $response = ['success' => true, 'message' => 'Settings updated'];
    }
    elseif ($action === 'test_notification') {
        $type = $_POST['type'] ?? 'info';
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, timestamp, `read`) VALUES (?, 'Test Notification', 'This is a test notification from BroilerGuard.', ?, NOW(), 0)");
        $stmt->execute([$userId, $type]);
        $response = ['success' => true, 'message' => 'Test notification sent'];
    }
    echo json_encode($response);
    exit;
}

$typeConfig = [
    'success' => ['icon' => 'fa-check-circle', 'color' => '#4D724D', 'bg' => '#D4E8D4'],
    'warning' => ['icon' => 'fa-exclamation-triangle', 'color' => '#C8A24A', 'bg' => '#F4EEDC'],
    'danger' => ['icon' => 'fa-skull-crossbones', 'color' => '#A44A3F', 'bg' => '#F6E9E7'],
    'info' => ['icon' => 'fa-info-circle', 'color' => '#4F6C7A', 'bg' => '#EAF0F3']
];

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center | BroilerGuard</title>
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
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; color: var(--text-primary); flex-wrap: wrap; justify-content: space-between; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1rem 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.15); display: flex; align-items: center; justify-content: space-between; }
        .stat-info .stat-value { font-size: 1.8rem; font-weight: 800; }
        .stat-info .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }
        .stat-icon { font-size: 2rem; opacity: 0.6; color: var(--accent); }

        .conditions-card {
            background: linear-gradient(135deg, var(--accent-dark), #3A5C3A);
            border-radius: var(--border-radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .condition-item { display: flex; align-items: center; gap: 0.8rem; }
        .condition-icon { font-size: 1.8rem; }
        .condition-info h4 { font-size: 0.7rem; opacity: 0.8; }
        .condition-info .value { font-size: 1.3rem; font-weight: 700; }

        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid rgba(141,180,142,0.2); }
        .tab-btn { background: none; border: none; padding: 0.8rem 1.5rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text-muted); transition: all 0.2s; position: relative; }
        .tab-btn.active { color: var(--accent-dark); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: var(--accent); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .notifications-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .notification-actions { display: flex; gap: 0.5rem; }
        .action-btn { background: var(--bg-secondary); border: 1px solid rgba(141,180,142,0.2); padding: 0.4rem 1rem; border-radius: 30px; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; color: var(--text-secondary); }
        .action-btn:hover { background: var(--accent-light); }

        .notification-list { max-height: 550px; overflow-y: auto; }
        .notification-item {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            border: 1px solid rgba(141,180,142,0.10);
            transition: all 0.2s;
            display: flex;
            gap: 1rem;
            cursor: pointer;
        }
        .notification-item:hover { transform: translateX(3px); box-shadow: var(--shadow-sm); }
        .notification-item.unread { border-left: 3px solid var(--accent); background: #FFFDF5; }
        .notification-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.2rem; color: var(--text-primary); }
        .notification-message { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem; }
        .notification-time { font-size: 0.65rem; color: var(--text-muted); }
        .notification-actions-dropdown { display: flex; gap: 0.3rem; align-items: center; }
        .notif-action { background: none; border: none; padding: 0.3rem 0.5rem; border-radius: 6px; cursor: pointer; font-size: 0.7rem; color: var(--text-muted); }
        .notif-action:hover { background: var(--bg-secondary); }

        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
        .settings-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            border: 1px solid rgba(141,180,142,0.10);
            box-shadow: var(--shadow-sm);
        }
        .settings-section h3 { font-size: 0.9rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .setting-row:last-child { border-bottom: none; }
        .setting-label { font-size: 0.8rem; color: var(--text-secondary); }
        .setting-value input[type="number"] { width: 100px; padding: 0.3rem; border: 1px solid rgba(141,180,142,0.2); border-radius: 8px; font-size: 0.8rem; background: var(--bg-secondary); }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.2s; border-radius: 22px; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 2px; bottom: 2px; background-color: white; transition: 0.2s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: var(--accent); }
        input:checked + .toggle-slider:before { transform: translateX(22px); }

        .save-settings-btn {
            background: linear-gradient(105deg, var(--accent-dark), #3A5C3A);
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            width: 100%;
            color: #FFFFFF;
        }
        .save-settings-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(141, 180, 142, 0.2); }

        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 20px; padding: 1.5rem; max-width: 380px; width: 90%; text-align: center; }
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .conditions-card { flex-direction: column; align-items: flex-start; }
            .settings-grid { grid-template-columns: 1fr; }
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
            <a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a>
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

    <!-- Weather Modal (same as others) -->
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
        <div class="page-title">
            <span><i class="fas fa-bell" style="color:var(--accent);"></i> Notification Center</span>
            <?php if ($unreadCount > 0): ?>
                <span style="background:var(--red); color:white; padding:0.2rem 0.8rem; border-radius:30px; font-size:0.8rem;"><?php echo $unreadCount; ?> unread</span>
            <?php endif; ?>
        </div>
        <p class="page-subtitle">Manage alerts, view notification history, and configure alert preferences</p>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--accent-dark);"><?php echo count($notifications); ?></div><div class="stat-label">Total Notifications</div></div><div class="stat-icon"><i class="fas fa-bell"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--orange);"><?php echo $unreadCount; ?></div><div class="stat-label">Unread</div></div><div class="stat-icon"><i class="fas fa-envelope"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--green);"><?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'success'; })); ?></div><div class="stat-label">Success</div></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--red);"><?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'danger' || $n['type'] === 'warning'; })); ?></div><div class="stat-label">Active Alerts</div></div><div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div></div>
        </div>

        <div class="conditions-card">
            <div class="condition-item"><div class="condition-icon"><i class="fas fa-thermometer-half"></i></div><div class="condition-info"><h4>Temperature</h4><div class="value"><?php echo $sensorData['temperature']; ?>°C</div></div></div>
            <div class="condition-item"><div class="condition-icon"><i class="fas fa-tint"></i></div><div class="condition-info"><h4>Humidity</h4><div class="value"><?php echo $sensorData['humidity']; ?>%</div></div></div>
            <div class="condition-item"><div class="condition-icon"><i class="fas fa-drumstick-bite"></i></div><div class="condition-info"><h4>Feed Level</h4><div class="value"><?php echo $sensorData['feed_level']; ?> kg</div></div></div>
            <div class="condition-item"><div class="condition-icon"><i class="fas fa-water"></i></div><div class="condition-info"><h4>Water Level</h4><div class="value"><?php echo $sensorData['water_level']; ?>%</div></div></div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('notifications')"><i class="fas fa-bell"></i> Notifications</button>
            <button class="tab-btn" onclick="switchTab('settings')"><i class="fas fa-cog"></i> Alert Settings</button>
        </div>

        <div id="notifications-tab" class="tab-content active">
            <div class="notifications-header">
                <span style="font-size:0.8rem; color:var(--text-muted);">Stay updated with real-time farm alerts</span>
                <div class="notification-actions">
                    <button class="action-btn" onclick="markAllRead()"><i class="fas fa-check-double"></i> Mark all read</button>
                    <button class="action-btn" onclick="deleteAllNotifications()"><i class="fas fa-trash-alt"></i> Clear all</button>
                    <button class="action-btn" onclick="testNotification()"><i class="fas fa-flask"></i> Test</button>
                </div>
            </div>
            <div class="notification-list" id="notificationList">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications yet</p>
                        <small>When there are alerts, they will appear here</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?php echo !$notif['read'] ? 'unread' : ''; ?>" data-id="<?php echo $notif['id']; ?>" onclick="markAsRead('<?php echo $notif['id']; ?>')">
                            <div class="notification-icon" style="background: <?php echo $typeConfig[$notif['type']]['bg']; ?>; color: <?php echo $typeConfig[$notif['type']]['color']; ?>;">
                                <i class="fas <?php echo $typeConfig[$notif['type']]['icon']; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <i class="far fa-clock"></i> <?php echo date('M d, h:i A', strtotime($notif['timestamp'])); ?>
                                </div>
                            </div>
                            <div class="notification-actions-dropdown" onclick="event.stopPropagation()">
                                <button class="notif-action" onclick="deleteNotification('<?php echo $notif['id']; ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="settings-tab" class="tab-content">
            <div class="settings-grid">
                <div class="settings-section">
                    <h3><i class="fas fa-desktop"></i> Browser Notifications</h3>
                    <div class="setting-row">
                        <span class="setting-label">Enable Browser Alerts</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="browserEnabled" <?php echo $settingsRow['browser_enabled'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Play Sound for Alerts</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="soundEnabled" <?php echo $settingsRow['sound_enabled'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="settings-section">
                    <h3><i class="fas fa-thermometer-half"></i> Temperature Alerts</h3>
                    <div class="setting-row">
                        <span class="setting-label">High Temperature Alert</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="alertTempHigh" <?php echo $settingsRow['alert_temp_high'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">High Threshold (°C)</span>
                        <input type="number" step="0.5" id="tempHighThreshold" value="<?php echo $settingsRow['temp_high_threshold']; ?>" style="width:80px; padding:0.3rem; border:1px solid rgba(141,180,142,0.2); border-radius:8px; background:var(--bg-secondary);">
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Low Temperature Alert</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="alertTempLow" <?php echo $settingsRow['alert_temp_low'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Low Threshold (°C)</span>
                        <input type="number" step="0.5" id="tempLowThreshold" value="<?php echo $settingsRow['temp_low_threshold']; ?>" style="width:80px; padding:0.3rem; border:1px solid rgba(141,180,142,0.2); border-radius:8px; background:var(--bg-secondary);">
                    </div>
                </div>
                <div class="settings-section">
                    <h3><i class="fas fa-tint"></i> Humidity & Resources</h3>
                    <div class="setting-row">
                        <span class="setting-label">High Humidity Alert</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="alertHumidity" <?php echo $settingsRow['alert_humidity'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Humidity Threshold (%)</span>
                        <input type="number" step="5" id="humidityThreshold" value="<?php echo $settingsRow['humidity_threshold']; ?>" style="width:80px; padding:0.3rem; border:1px solid rgba(141,180,142,0.2); border-radius:8px; background:var(--bg-secondary);">
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Low Feed Alert</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="alertFeedLow" <?php echo $settingsRow['alert_feed_low'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Feed Threshold (kg)</span>
                        <input type="number" step="1" id="feedLowThreshold" value="<?php echo $settingsRow['feed_low_threshold']; ?>" style="width:80px; padding:0.3rem; border:1px solid rgba(141,180,142,0.2); border-radius:8px; background:var(--bg-secondary);">
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Low Water Alert</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="alertWaterLow" <?php echo $settingsRow['alert_water_low'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-row">
                        <span class="setting-label">Water Threshold (%)</span>
                        <input type="number" step="5" id="waterLowThreshold" value="<?php echo $settingsRow['water_low_threshold']; ?>" style="width:80px; padding:0.3rem; border:1px solid rgba(141,180,142,0.2); border-radius:8px; background:var(--bg-secondary);">
                    </div>
                </div>
            </div>
            <button class="save-settings-btn" onclick="saveSettings()"><i class="fas fa-save"></i> Save All Settings</button>
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

    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        if (tab === 'notifications') {
            document.querySelector('.tab-btn:first-child').classList.add('active');
            document.getElementById('notifications-tab').classList.add('active');
        } else {
            document.querySelector('.tab-btn:last-child').classList.add('active');
            document.getElementById('settings-tab').classList.add('active');
        }
    }

    async function markAsRead(id) {
        try {
            const response = await fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=mark_read&id=' + id
            });
            const data = await response.json();
            if (data.success) {
                const el = document.querySelector(`.notification-item[data-id="${id}"]`);
                if (el) el.classList.remove('unread');
                showToast(data.message);
            }
        } catch (error) { showToast('Error marking as read', true); }
    }

    async function markAllRead() {
        try {
            const response = await fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=mark_all_read'
            });
            const data = await response.json();
            if (data.success) {
                document.querySelectorAll('.notification-item').forEach(el => el.classList.remove('unread'));
                showToast(data.message);
            }
        } catch (error) { showToast('Error marking all as read', true); }
    }

    function deleteNotification(id) {
        showModal('Delete Notification', 'Are you sure you want to delete this notification?', async () => {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=delete_notification&id=' + id
                });
                const data = await response.json();
                if (data.success) {
                    const el = document.querySelector(`.notification-item[data-id="${id}"]`);
                    if (el) el.remove();
                    showToast(data.message);
                }
            } catch (error) { showToast('Error deleting notification', true); }
        });
    }

    function deleteAllNotifications() {
        showModal('Clear All Notifications', 'Are you sure you want to delete all notifications?', async () => {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=delete_all'
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('notificationList').innerHTML = '<div class="empty-state"><i class="fas fa-bell-slash"></i><p>No notifications yet</p><small>When there are alerts, they will appear here</small></div>';
                    showToast(data.message);
                }
            } catch (error) { showToast('Error clearing notifications', true); }
        });
    }

    async function testNotification() {
        try {
            const response = await fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=test_notification&type=info'
            });
            const data = await response.json();
            if (data.success) { showToast('Test notification sent!'); setTimeout(() => location.reload(), 800); }
        } catch (error) { showToast('Error sending test notification', true); }
    }

    async function saveSettings() {
        const browserEnabled = document.getElementById('browserEnabled').checked;
        const soundEnabled = document.getElementById('soundEnabled').checked;
        const alertTempHigh = document.getElementById('alertTempHigh').checked;
        const tempHighThreshold = document.getElementById('tempHighThreshold').value;
        const alertTempLow = document.getElementById('alertTempLow').checked;
        const tempLowThreshold = document.getElementById('tempLowThreshold').value;
        const alertHumidity = document.getElementById('alertHumidity').checked;
        const humidityThreshold = document.getElementById('humidityThreshold').value;
        const alertFeedLow = document.getElementById('alertFeedLow').checked;
        const feedLowThreshold = document.getElementById('feedLowThreshold').value;
        const alertWaterLow = document.getElementById('alertWaterLow').checked;
        const waterLowThreshold = document.getElementById('waterLowThreshold').value;

        try {
            const response = await fetch('notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_settings&browser_enabled=${browserEnabled}&sound_enabled=${soundEnabled}&alert_temp_high=${alertTempHigh}&temp_high_threshold=${tempHighThreshold}&alert_temp_low=${alertTempLow}&temp_low_threshold=${tempLowThreshold}&alert_humidity=${alertHumidity}&humidity_threshold=${humidityThreshold}&alert_feed_low=${alertFeedLow}&feed_low_threshold=${feedLowThreshold}&alert_water_low=${alertWaterLow}&water_low_threshold=${waterLowThreshold}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error saving settings', true); }
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
    function refreshWeather() { window.location.href = 'notifications.php?refresh_weather=1'; }
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
