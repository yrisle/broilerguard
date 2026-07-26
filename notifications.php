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

// Initialize notifications array if not exists
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}

// Initialize notification settings if not exists
if (!isset($_SESSION['notification_settings'])) {
    $_SESSION['notification_settings'] = [
        'browser_enabled' => true,
        'sound_enabled' => true,
        'alert_temp_high' => true,
        'temp_high_threshold' => 35.0,
        'alert_temp_low' => true,
        'temp_low_threshold' => 20.0,
        'alert_humidity' => true,
        'humidity_threshold' => 80.0,
        'alert_feed_low' => true,
        'feed_low_threshold' => 10.0,
        'alert_water_low' => true,
        'water_low_threshold' => 20
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action === 'mark_read') {
        $notificationId = $_POST['id'] ?? null;
        if ($notificationId && isset($_SESSION['notifications'][$notificationId])) {
            $_SESSION['notifications'][$notificationId]['read'] = true;
            $response = ['success' => true, 'message' => 'Marked as read'];
        } else {
            $response = ['success' => false, 'message' => 'Notification not found'];
        }
        
    } elseif ($action === 'mark_all_read') {
        foreach ($_SESSION['notifications'] as $key => $notif) {
            $_SESSION['notifications'][$key]['read'] = true;
        }
        $response = ['success' => true, 'message' => 'All notifications marked as read'];
        
    } elseif ($action === 'delete_notification') {
        $notificationId = $_POST['id'] ?? null;
        if ($notificationId && isset($_SESSION['notifications'][$notificationId])) {
            unset($_SESSION['notifications'][$notificationId]);
            $response = ['success' => true, 'message' => 'Notification deleted'];
        } else {
            $response = ['success' => false, 'message' => 'Notification not found'];
        }
        
    } elseif ($action === 'delete_all') {
        $_SESSION['notifications'] = [];
        $response = ['success' => true, 'message' => 'All notifications cleared'];
        
    } elseif ($action === 'update_settings') {
        $_SESSION['notification_settings'] = [
            'browser_enabled' => isset($_POST['browser_enabled']) && $_POST['browser_enabled'] === 'true',
            'sound_enabled' => isset($_POST['sound_enabled']) && $_POST['sound_enabled'] === 'true',
            'alert_temp_high' => isset($_POST['alert_temp_high']) && $_POST['alert_temp_high'] === 'true',
            'temp_high_threshold' => floatval($_POST['temp_high_threshold'] ?? 35),
            'alert_temp_low' => isset($_POST['alert_temp_low']) && $_POST['alert_temp_low'] === 'true',
            'temp_low_threshold' => floatval($_POST['temp_low_threshold'] ?? 20),
            'alert_humidity' => isset($_POST['alert_humidity']) && $_POST['alert_humidity'] === 'true',
            'humidity_threshold' => floatval($_POST['humidity_threshold'] ?? 80),
            'alert_feed_low' => isset($_POST['alert_feed_low']) && $_POST['alert_feed_low'] === 'true',
            'feed_low_threshold' => floatval($_POST['feed_low_threshold'] ?? 10),
            'alert_water_low' => isset($_POST['alert_water_low']) && $_POST['alert_water_low'] === 'true',
            'water_low_threshold' => intval($_POST['water_low_threshold'] ?? 20)
        ];
        $response = ['success' => true, 'message' => 'Notification settings updated'];
        
    } elseif ($action === 'test_notification') {
        $type = $_POST['type'] ?? 'info';
        addNotification('Test Notification', 'This is a test notification from BroilerGuard. Your notification system is working properly!', $type);
        $response = ['success' => true, 'message' => 'Test notification sent'];
    }
    
    echo json_encode($response);
    exit;
}

// Function to add a notification
function addNotification($title, $message, $type = 'info', $link = null) {
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    $notification = [
        'id' => uniqid(),
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link,
        'timestamp' => date('Y-m-d H:i:s'),
        'read' => false
    ];
    
    $_SESSION['notifications'] = array_merge([$notification], $_SESSION['notifications']);
    
    if (count($_SESSION['notifications']) > 100) {
        $_SESSION['notifications'] = array_slice($_SESSION['notifications'], 0, 100);
    }
    
    return $notification;
}

// Get notification settings
function getNotificationSettings() {
    return $_SESSION['notification_settings'] ?? [
        'browser_enabled' => true,
        'sound_enabled' => true,
        'alert_temp_high' => true,
        'temp_high_threshold' => 35.0,
        'alert_temp_low' => true,
        'temp_low_threshold' => 20.0,
        'alert_humidity' => true,
        'humidity_threshold' => 80.0,
        'alert_feed_low' => true,
        'feed_low_threshold' => 10.0,
        'alert_water_low' => true,
        'water_low_threshold' => 20
    ];
}

// Get all notifications
function getNotifications($limit = 50) {
    if (!isset($_SESSION['notifications']) || empty($_SESSION['notifications'])) {
        $demoNotifications = [];
        $demoData = [
            ['title' => 'Welcome to BroilerGuard', 'message' => 'Your farm management system is now ready. Configure your alerts to get started.', 'type' => 'success', 'hoursAgo' => 0],
            ['title' => 'Temperature Check', 'message' => 'Current temperature is 32.5°C. Normal range is 20-35°C.', 'type' => 'info', 'hoursAgo' => 2],
            ['title' => 'Feed Level Alert', 'message' => 'Feed level is at 15.2 kg. Consider refilling soon.', 'type' => 'warning', 'hoursAgo' => 5],
            ['title' => 'System Update', 'message' => 'BroilerGuard has been updated to the latest version.', 'type' => 'success', 'hoursAgo' => 12],
            ['title' => 'High Humidity Detected', 'message' => 'Humidity level reached 82%. Check ventilation system.', 'type' => 'warning', 'hoursAgo' => 18],
            ['title' => 'Water Level Critical', 'message' => 'Water level is at 18%. Immediate refill recommended.', 'type' => 'danger', 'hoursAgo' => 24],
            ['title' => 'Schedule Activated', 'message' => 'Morning feeding schedule has been executed successfully.', 'type' => 'info', 'hoursAgo' => 30],
            ['title' => 'Fan Auto Mode', 'message' => 'Exhaust fan automatically turned ON due to high temperature.', 'type' => 'success', 'hoursAgo' => 36]
        ];
        
        foreach ($demoData as $data) {
            $demoNotifications[] = [
                'id' => uniqid(),
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'link' => null,
                'timestamp' => date('Y-m-d H:i:s', strtotime("-{$data['hoursAgo']} hours")),
                'read' => $data['hoursAgo'] > 12
            ];
        }
        $_SESSION['notifications'] = $demoNotifications;
    }
    
    return array_slice($_SESSION['notifications'], 0, $limit);
}

// Get unread count
function getUnreadCount() {
    if (!isset($_SESSION['notifications'])) return 0;
    $count = 0;
    foreach ($_SESSION['notifications'] as $notif) {
        if (!$notif['read']) $count++;
    }
    return $count;
}

// Get current sensor data
function getCurrentSensorData() {
    $temp = 32.5;
    $humidity = 65;
    $feedLevel = 39.2;
    $waterLevel = 75;
    
    if (isset($_SESSION['shared_sensor_data'])) {
        $temp = $_SESSION['shared_sensor_data']['temperature'] ?? 32.5;
        $humidity = $_SESSION['shared_sensor_data']['humidity'] ?? 65;
    }
    if (isset($_SESSION['feed_inventory'])) {
        $feedLevel = $_SESSION['feed_inventory']['current_level'] ?? 39.2;
    }
    if (isset($_SESSION['water_status'])) {
        $waterLevel = $_SESSION['water_status']['level'] ?? 75;
    }
    
    return [
        'temperature' => $temp,
        'humidity' => $humidity,
        'feed_level' => $feedLevel,
        'water_level' => $waterLevel
    ];
}

$notifications = getNotifications(50);
$unreadCount = getUnreadCount();
$settings = getNotificationSettings();
$sensorData = getCurrentSensorData();

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');

$typeConfig = [
    'success' => ['icon' => 'fa-check-circle', 'color' => '#27AE60', 'bg' => '#E8F5E9'],
    'warning' => ['icon' => 'fa-exclamation-triangle', 'color' => '#F39C12', 'bg' => '#FFF8E1'],
    'danger' => ['icon' => 'fa-skull-crossbones', 'color' => '#E74C3C', 'bg' => '#FDEDEC'],
    'info' => ['icon' => 'fa-info-circle', 'color' => '#2980B9', 'bg' => '#EBF5FB']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center | BroilerGuard</title>
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
        
        /* ===== TOP HEADER ===== */
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
        .top-header .date-time span { font-size: 0.8rem; color: var(--text-muted); }
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
        
        /* ===== PAGE CONTENT ===== */
        .page-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; justify-content: space-between; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }
        
        /* ===== STATUS CARDS ===== */
        .status-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.2rem; margin-bottom: 1.5rem; }
        .status-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.1); display: flex; align-items: center; gap: 1.2rem; transition: transform 0.2s, box-shadow 0.2s; }
        .status-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        
        .status-card .card-icon { font-size: 2.2rem; flex-shrink: 0; }
        .status-card .card-icon.gold { color: #E6B800; }
        .status-card .card-icon.green { color: #27AE60; }
        .status-card .card-icon.blue { color: #2980B9; }
        .status-card .card-icon.orange { color: #E67E22; }
        
        .status-card .card-info { flex: 1; }
        .status-card .card-info .value { font-size: 1.8rem; font-weight: 800; line-height: 1.2; }
        .status-card .card-info .value.gold { color: #E6B800; }
        .status-card .card-info .value.green { color: #27AE60; }
        .status-card .card-info .value.blue { color: #2980B9; }
        .status-card .card-info .value.orange { color: #E67E22; }
        .status-card .card-info .label { font-size: 0.8rem; color: var(--text-muted); }
        .status-card .card-info .sub { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        /* ===== SENSOR BANNER ===== */
        .sensor-banner {
            background: linear-gradient(135deg, #6B4226, #4A2F1F);
            border-radius: var(--border-radius);
            padding: 0.8rem 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        .sensor-item { display: flex; align-items: center; gap: 0.8rem; }
        .sensor-item .sensor-icon { font-size: 1.5rem; opacity: 0.8; }
        .sensor-item .sensor-info h4 { font-size: 0.65rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; }
        .sensor-item .sensor-info .sensor-value { font-size: 1.2rem; font-weight: 700; }
        
        /* ===== TABS ===== */
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid rgba(255, 214, 46, 0.3); }
        .tab-btn { background: none; border: none; padding: 0.7rem 1.5rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text-muted); transition: all 0.2s; position: relative; font-family: 'Inter', sans-serif; }
        .tab-btn.active { color: var(--accent-dark); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: var(--accent); }
        .tab-btn:hover { color: var(--text-primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* ===== NOTIFICATIONS ===== */
        .notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }
        .notif-header .notif-count { font-size: 0.8rem; color: var(--text-muted); }
        .notif-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .action-btn {
            background: var(--bg-secondary);
            border: 1px solid rgba(255, 214, 46, 0.3);
            padding: 0.3rem 0.9rem;
            border-radius: 30px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-secondary);
            font-family: 'Inter', sans-serif;
        }
        .action-btn:hover { background: var(--accent-light); border-color: var(--accent); }
        .action-btn.danger { border-color: var(--red); color: var(--red); }
        .action-btn.danger:hover { background: var(--red-light); }
        
        .notif-list { max-height: 500px; overflow-y: auto; }
        .notif-list::-webkit-scrollbar { width: 4px; }
        .notif-list::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
        .notif-list::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }
        
        .notif-item {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            margin-bottom: 0.6rem;
            border: 1px solid rgba(255, 214, 46, 0.1);
            transition: all 0.2s;
            display: flex;
            gap: 0.8rem;
            align-items: flex-start;
        }
        .notif-item:hover { transform: translateX(3px); box-shadow: var(--shadow-sm); }
        .notif-item.unread { border-left: 3px solid var(--accent); background: #FFFDF5; }
        
        .notif-item .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .notif-item .notif-body { flex: 1; cursor: pointer; }
        .notif-item .notif-body .notif-title { font-weight: 700; font-size: 0.85rem; color: var(--text-primary); }
        .notif-item .notif-body .notif-msg { font-size: 0.75rem; color: var(--text-muted); margin: 0.1rem 0; }
        .notif-item .notif-body .notif-time { font-size: 0.6rem; color: var(--text-muted); }
        .notif-item .notif-actions-drop { display: flex; gap: 0.2rem; flex-shrink: 0; }
        .notif-item .notif-actions-drop button {
            background: none;
            border: none;
            padding: 0.2rem 0.4rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.65rem;
            color: var(--text-muted);
            transition: 0.2s;
        }
        .notif-item .notif-actions-drop button:hover { background: var(--bg-secondary); color: var(--red); }
        
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; opacity: 0.4; }
        .empty-state p { font-size: 0.9rem; }
        .empty-state small { font-size: 0.75rem; }
        
        /* ===== SETTINGS ===== */
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.2rem; }
        .settings-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            border: 1px solid rgba(255, 214, 46, 0.1);
            box-shadow: var(--shadow-sm);
        }
        .settings-section h3 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.04); }
        .setting-row:last-child { border-bottom: none; }
        .setting-label { font-size: 0.8rem; color: var(--text-secondary); }
        .setting-input { width: 80px; padding: 0.3rem; border: 1px solid rgba(255, 214, 46, 0.3); border-radius: 8px; font-size: 0.8rem; font-family: 'Inter', sans-serif; background: var(--bg-secondary); }
        .setting-input:focus { outline: none; border-color: var(--accent); }
        
        .toggle-switch { position: relative; display: inline-block; width: 40px; height: 20px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.2s; border-radius: 20px; }
        .toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: 0.2s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: var(--accent); }
        input:checked + .toggle-slider:before { transform: translateX(20px); }
        
        .save-btn {
            background: linear-gradient(105deg, #E6B800, #FFD62E);
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            width: 100%;
            color: #3E2C1C;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .save-btn:hover { background: linear-gradient(105deg, #D4A017, #E6B800); transform: translateY(-2px); }
        
        /* ===== MODAL ===== */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 20px; padding: 2rem; max-width: 380px; width: 90%; text-align: center; border: 1px solid rgba(255, 214, 46, 0.2); }
        .modal-content .modal-icon { font-size: 2.5rem; color: var(--accent); margin-bottom: 0.5rem; }
        .modal-content h3 { font-size: 1.1rem; margin-bottom: 0.5rem; }
        .modal-content p { font-size: 0.9rem; color: var(--text-secondary); }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center; }
        .modal-confirm { background: linear-gradient(105deg, #E6B800, #FFD62E); border: none; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 600; cursor: pointer; color: #3E2C1C; font-family: 'Inter', sans-serif; }
        .modal-confirm:hover { background: linear-gradient(105deg, #D4A017, #E6B800); }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }
        .modal-cancel:hover { background: #D0D0D0; }
        
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
            .status-row { grid-template-columns: 1fr 1fr; }
            .sensor-banner { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .status-row { grid-template-columns: 1fr; }
            .sensor-banner { grid-template-columns: 1fr 1fr; }
            .settings-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .notif-header { flex-direction: column; align-items: stretch; }
            .notif-actions { justify-content: stretch; }
            .notif-actions .action-btn { flex: 1; text-align: center; }
            .sensor-banner { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 640px) {
            .status-row { grid-template-columns: 1fr; }
            .sensor-banner { grid-template-columns: 1fr; }
            .settings-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
            .notif-header { flex-direction: column; align-items: stretch; }
            .notif-actions { justify-content: stretch; }
            .notif-actions .action-btn { flex: 1; text-align: center; }
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
                <a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a>
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
                    <span id="currentDate"><?php echo $currentDate; ?></span>
                    <span class="time" id="currentTime"><?php echo $currentTime; ?></span>
                </div>
            </div>
            <div class="header-right">
                <a href="notifications.php" class="notification-bell" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="page-content">
            <h1 class="page-title">
                <span><i class="fas fa-bell" style="color:var(--accent);"></i> Notifications</span>
                <?php if ($unreadCount > 0): ?>
                    <span style="background: var(--red); color: white; padding: 0.2rem 0.8rem; border-radius: 30px; font-size: 0.8rem;"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">Manage alerts, view notification history, and configure alert preferences</p>

            <!-- Status Cards -->
            <div class="status-row">
                <div class="status-card">
                    <div class="card-icon gold"><i class="fas fa-bell"></i></div>
                    <div class="card-info">
                        <div class="value gold"><?php echo count($notifications); ?></div>
                        <div class="label">Total Notifications</div>
                    </div>
                </div>
                <div class="status-card">
                    <div class="card-icon orange"><i class="fas fa-envelope"></i></div>
                    <div class="card-info">
                        <div class="value orange"><?php echo $unreadCount; ?></div>
                        <div class="label">Unread</div>
                    </div>
                </div>
                <div class="status-card">
                    <div class="card-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="card-info">
                        <div class="value green"><?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'success'; })); ?></div>
                        <div class="label">Success</div>
                    </div>
                </div>
                <div class="status-card">
                    <div class="card-icon blue"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="card-info">
                        <div class="value blue"><?php echo count(array_filter($notifications, function($n) { return $n['type'] === 'danger' || $n['type'] === 'warning'; })); ?></div>
                        <div class="label">Active Alerts</div>
                    </div>
                </div>
            </div>

            <!-- Sensor Data Banner -->
            <div class="sensor-banner">
                <div class="sensor-item">
                    <div class="sensor-icon"><i class="fas fa-thermometer-half"></i></div>
                    <div class="sensor-info">
                        <h4>Temperature</h4>
                        <div class="sensor-value"><?php echo $sensorData['temperature']; ?>°C</div>
                    </div>
                </div>
                <div class="sensor-item">
                    <div class="sensor-icon"><i class="fas fa-tint"></i></div>
                    <div class="sensor-info">
                        <h4>Humidity</h4>
                        <div class="sensor-value"><?php echo $sensorData['humidity']; ?>%</div>
                    </div>
                </div>
                <div class="sensor-item">
                    <div class="sensor-icon"><i class="fas fa-drumstick-bite"></i></div>
                    <div class="sensor-info">
                        <h4>Feed Level</h4>
                        <div class="sensor-value"><?php echo $sensorData['feed_level']; ?> kg</div>
                    </div>
                </div>
                <div class="sensor-item">
                    <div class="sensor-icon"><i class="fas fa-water"></i></div>
                    <div class="sensor-info">
                        <h4>Water Level</h4>
                        <div class="sensor-value"><?php echo $sensorData['water_level']; ?>%</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('notifications')"><i class="fas fa-bell"></i> Notifications</button>
                <button class="tab-btn" onclick="switchTab('settings')"><i class="fas fa-cog"></i> Alert Settings</button>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" class="tab-content active">
                <div class="notif-header">
                    <span class="notif-count"><?php echo count($notifications); ?> notification(s)</span>
                    <div class="notif-actions">
                        <button class="action-btn" onclick="markAllRead()"><i class="fas fa-check-double"></i> Mark all read</button>
                        <button class="action-btn danger" onclick="deleteAllNotifications()"><i class="fas fa-trash-alt"></i> Clear all</button>
                        <button class="action-btn" onclick="testNotification()"><i class="fas fa-flask"></i> Test</button>
                    </div>
                </div>
                
                <div class="notif-list" id="notifList">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications</p>
                            <small>When there are alerts, they will appear here</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <?php
                            $timestamp = isset($notif['timestamp']) ? $notif['timestamp'] : date('Y-m-d H:i:s');
                            ?>
                            <div class="notif-item <?php echo !$notif['read'] ? 'unread' : ''; ?>" data-id="<?php echo $notif['id']; ?>">
                                <div class="notif-icon" style="background: <?php echo $typeConfig[$notif['type']]['bg']; ?>; color: <?php echo $typeConfig[$notif['type']]['color']; ?>;">
                                    <i class="fas <?php echo $typeConfig[$notif['type']]['icon']; ?>"></i>
                                </div>
                                <div class="notif-body" onclick="markAsRead('<?php echo $notif['id']; ?>')">
                                    <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notif-msg"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    <div class="notif-time"><i class="far fa-clock"></i> <?php echo date('M d, h:i A', strtotime($timestamp)); ?></div>
                                </div>
                                <div class="notif-actions-drop">
                                    <button onclick="deleteNotification('<?php echo $notif['id']; ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings-tab" class="tab-content">
                <div class="settings-grid">
                    <div class="settings-section">
                        <h3><i class="fas fa-desktop"></i> Browser Notifications</h3>
                        <div class="setting-row">
                            <span class="setting-label">Enable Browser Alerts</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="browserEnabled" <?php echo $settings['browser_enabled'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Play Sound for Alerts</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="soundEnabled" <?php echo $settings['sound_enabled'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3><i class="fas fa-thermometer-half"></i> Temperature Alerts</h3>
                        <div class="setting-row">
                            <span class="setting-label">High Temperature Alert</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="alertTempHigh" <?php echo $settings['alert_temp_high'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">High Threshold (°C)</span>
                            <input type="number" step="0.5" class="setting-input" id="tempHighThreshold" value="<?php echo $settings['temp_high_threshold']; ?>">
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Low Temperature Alert</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="alertTempLow" <?php echo $settings['alert_temp_low'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Low Threshold (°C)</span>
                            <input type="number" step="0.5" class="setting-input" id="tempLowThreshold" value="<?php echo $settings['temp_low_threshold']; ?>">
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3><i class="fas fa-tint"></i> Humidity & Resources</h3>
                        <div class="setting-row">
                            <span class="setting-label">High Humidity Alert</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="alertHumidity" <?php echo $settings['alert_humidity'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Humidity Threshold (%)</span>
                            <input type="number" step="5" class="setting-input" id="humidityThreshold" value="<?php echo $settings['humidity_threshold']; ?>">
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Low Feed Alert</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="alertFeedLow" <?php echo $settings['alert_feed_low'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Feed Threshold (kg)</span>
                            <input type="number" step="1" class="setting-input" id="feedLowThreshold" value="<?php echo $settings['feed_low_threshold']; ?>">
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Low Water Alert</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="alertWaterLow" <?php echo $settings['alert_water_low'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-row">
                            <span class="setting-label">Water Threshold (%)</span>
                            <input type="number" step="5" class="setting-input" id="waterLowThreshold" value="<?php echo $settings['water_low_threshold']; ?>">
                        </div>
                    </div>
                </div>
                <button class="save-btn" onclick="saveSettings()"><i class="fas fa-save"></i> Save All Settings</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
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

        // ===== CLOCK =====
        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }

        setInterval(updateDateTime, 1000);
        updateDateTime();

        // ===== TOAST =====
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMessage').textContent = message;
            toast.className = 'toast' + (isError ? ' error' : '');
            toast.style.display = 'flex';
            setTimeout(() => toast.style.display = 'none', 3000);
        }

        // ===== MODAL =====
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
            if (pendingAction) {
                pendingAction();
                closeModal();
            }
        }

        document.getElementById('confirmModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('confirmModal')) closeModal();
        });

        // ===== TABS =====
        function switchTab(tab) {
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(btn => btn.classList.remove('active'));
            contents.forEach(content => content.classList.remove('active'));
            
            if (tab === 'notifications') {
                tabs[0].classList.add('active');
                document.getElementById('notifications-tab').classList.add('active');
            } else {
                tabs[1].classList.add('active');
                document.getElementById('settings-tab').classList.add('active');
            }
        }

        // ===== NOTIFICATION FUNCTIONS =====
        async function markAsRead(id) {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=mark_read&id=' + id
                });
                const data = await response.json();
                if (data.success) {
                    const notifElement = document.querySelector(`.notif-item[data-id="${id}"]`);
                    if (notifElement) {
                        notifElement.classList.remove('unread');
                    }
                    showToast(data.message);
                }
            } catch (error) {
                showToast('Error marking as read', true);
            }
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
                    document.querySelectorAll('.notif-item').forEach(item => {
                        item.classList.remove('unread');
                    });
                    showToast(data.message);
                }
            } catch (error) {
                showToast('Error marking all as read', true);
            }
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
                        const notifElement = document.querySelector(`.notif-item[data-id="${id}"]`);
                        if (notifElement) notifElement.remove();
                        showToast(data.message);
                    }
                } catch (error) {
                    showToast('Error deleting notification', true);
                }
            });
        }

        function deleteAllNotifications() {
            showModal('Clear All Notifications', 'Are you sure you want to delete all notifications? This cannot be undone.', async () => {
                try {
                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: 'action=delete_all'
                    });
                    const data = await response.json();
                    if (data.success) {
                        document.getElementById('notifList').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications</p>
                                <small>When there are alerts, they will appear here</small>
                            </div>
                        `;
                        showToast(data.message);
                    }
                } catch (error) {
                    showToast('Error clearing notifications', true);
                }
            });
        }

        function testNotification() {
            showModal('Test Notification', 'Send a test notification to verify your settings?', async () => {
                try {
                    const response = await fetch('notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: 'action=test_notification&type=info'
                    });
                    const data = await response.json();
                    if (data.success) {
                        showToast('Test notification sent!');
                        setTimeout(() => location.reload(), 800);
                    }
                } catch (error) {
                    showToast('Error sending test notification', true);
                }
            });
        }

        // ===== SETTINGS FUNCTIONS =====
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
                if (data.success) {
                    showToast(data.message);
                } else {
                    showToast(data.message, true);
                }
            } catch (error) {
                showToast('Error saving settings', true);
            }
        }
    </script>
</body>
</html>
