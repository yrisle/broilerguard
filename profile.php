<?php
// profile.php - Admin Profile Management (Accessible via sidebar user click)
session_start();

require_once 'db_connect.php';        // PDO connection
require_once 'weather_functions.php'; // Weather API

$weather = getWeatherData();

// Authentication Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$userId = $_SESSION['admin_id'] ?? 1;

// ============================================================
// FETCH USER DATA
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $user = [
        'id' => 1,
        'username' => 'admin',
        'email' => 'admin@broilerguard.com',
        'full_name' => 'System Administrator',
        'role' => 'admin',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// ============================================================
// AJAX HANDLERS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($fullName) || empty($email) || empty($username)) {
            $response = ['success' => false, 'message' => 'All fields are required'];
            echo json_encode($response);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = ['success' => false, 'message' => 'Invalid email format'];
            echo json_encode($response);
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $userId]);
        if ($checkStmt->fetch()) {
            $response = ['success' => false, 'message' => 'Username already taken'];
            echo json_encode($response);
            exit;
        }

        $updateStmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ?");
        $updateStmt->execute([$fullName, $email, $username, $userId]);
        
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_full_name'] = $fullName;
        
        $response = ['success' => true, 'message' => 'Profile updated successfully'];
    }
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 6) {
            $response = ['success' => false, 'message' => 'Password must be at least 6 characters'];
            echo json_encode($response);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $response = ['success' => false, 'message' => 'Passwords do not match'];
            echo json_encode($response);
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);

        $response = ['success' => true, 'message' => 'Password changed successfully'];
    }

    echo json_encode($response);
    exit;
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;

$notifStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0");
$notifStmt->execute([$userId]);
$unreadNotifications = (int)$notifStmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --bg-primary: #F5F5F5;
            --bg-secondary: #E8F0E8;
            --bg-card: #FFFFFF;
            --text-primary: #2C3E2C;
            --text-secondary: #4D724D;
            --text-muted: #6B8A6B;
            --accent: #8DB48E;
            --accent-dark: #4D724D;
            --accent-light: #D4E8D4;
            --sidebar-bg: #3A5C3A;
            --sidebar-text: #F5F5F5;
            --sidebar-muted: #A8C8A8;
            --green: #4D724D;
            --green-light: #D4E8D4;
            --yellow: #C8A24A;
            --yellow-light: #F4EEDC;
            --red: #A44A3F;
            --red-light: #F6E9E7;
            --blue: #4F6C7A;
            --blue-light: #EAF0F3;
            --orange: #B9772A;
            --orange-light: #F9EFE5;
            --purple: #8E44AD;
            --sidebar-width: 280px;
            --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
        }

        ::-webkit-scrollbar { width: 0; height: 0; background: transparent; }
        * { scrollbar-width: none; -ms-overflow-style: none; }

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

        /* ===== SIDEBAR USER - CLICKABLE BUTTON ===== */
        .sidebar-user {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-top: auto;
            background: rgba(0,0,0,0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--sidebar-text);
        }
        .sidebar-user:hover {
            background: rgba(141, 180, 142, 0.25);
            transform: translateX(4px);
        }
        .sidebar-user .avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #FFFFFF;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .sidebar-user .user-info { flex: 1; }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.9rem; color: var(--sidebar-text); }
        .sidebar-user .user-info .role { font-size: 0.7rem; color: var(--sidebar-muted); }
        .sidebar-user:hover .fa-chevron-right {
            opacity: 1 !important;
            transform: translateX(4px);
        }

        .sidebar-nav { flex: 1; padding: 0.8rem 0; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav .nav-section { padding: 0.3rem 1rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--sidebar-muted); margin-bottom: 0.6rem; font-weight: 700; padding-left: 0.8rem; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 1rem;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 0.2rem;
            transition: all 0.2s;
            font-size: 0.88rem;
            font-weight: 500;
        }
        .sidebar-nav a:hover { background: rgba(141,180,142,0.25); color: #FFFFFF; transform: translateX(4px); }
        .sidebar-nav a.active { background: rgba(141,180,142,0.30); color: #FFFFFF; font-weight: 600; border-left: 3px solid var(--accent); }
        .sidebar-nav a i { width: 22px; text-align: center; font-size: 1rem; }
        .sidebar-footer { padding: 1rem 1.2rem; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-footer a { display: flex; align-items: center; gap: 0.7rem; color: var(--sidebar-muted); text-decoration: none; padding: 0.6rem 0.8rem; font-size: 0.88rem; transition: all 0.2s; border-radius: 10px; }
        .sidebar-footer a:hover { color: #FFFFFF; background: rgba(141,180,142,0.20); transform: translateX(4px); }

        /* ===== MAIN CONTENT ===== */
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; transition: margin-left 0.3s ease; }
        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid rgba(141,180,142,0.25);
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
        .weather-widget:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(77,114,77,0.35); }
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
            border: 1px solid rgba(77,114,77,0.15);
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
            border: 1px solid rgba(141,180,142,0.3);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .back-btn:hover { background: var(--accent-light); border-color: var(--accent); }

        .page-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; color: var(--text-primary); }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }

        .profile-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; }
        .profile-sidebar {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(141,180,142,0.15);
            box-shadow: var(--shadow-sm);
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: white;
            border: 4px solid var(--accent-light);
        }
        .profile-name { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); }
        .profile-role {
            font-size: 0.8rem;
            color: var(--text-muted);
            background: var(--accent-light);
            padding: 0.2rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.3rem;
        }
        .profile-meta {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(141,180,142,0.10);
            text-align: left;
        }
        .profile-meta .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(141,180,142,0.05);
            font-size: 0.85rem;
        }
        .profile-meta .meta-item .label { color: var(--text-muted); }
        .profile-meta .meta-item .value { font-weight: 600; }

        .profile-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid rgba(141,180,142,0.15);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        .profile-card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(141,180,142,0.10);
        }
        .profile-card .form-group { margin-bottom: 1rem; }
        .profile-card .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.3rem;
        }
        .profile-card .form-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid rgba(141,180,142,0.2);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.2s;
        }
        .profile-card .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(141,180,142,0.15);
        }

        .btn-save {
            background: linear-gradient(105deg, var(--accent-dark), #3A5C3A);
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(141,180,142,0.2); }
        .btn-secondary {
            background: var(--bg-secondary);
            border: 1px solid rgba(141,180,142,0.2);
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-secondary:hover { background: var(--accent-light); }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--green);
            color: white;
            padding: 0.7rem 1.2rem;
            border-radius: 12px;
            display: none;
            align-items: center;
            gap: 0.8rem;
            z-index: 2000;
            animation: slideIn 0.3s ease;
            font-size: 0.8rem;
            box-shadow: var(--shadow-md);
        }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .weather-modal {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            position: relative;
        }
        .weather-modal .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .weather-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .weather-detail-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        .weather-detail-item i { font-size: 1.5rem; color: var(--accent-dark); margin-bottom: 0.5rem; }
        .weather-detail-item .label { font-size: 0.75rem; color: var(--text-muted); }
        .weather-detail-item .value { font-size: 1.1rem; font-weight: 700; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .profile-grid { grid-template-columns: 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
        }
        @media (max-width: 640px) {
            .profile-sidebar { padding: 1rem; }
            .profile-avatar { width: 80px; height: 80px; font-size: 2rem; }
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
            <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a href="settings.php"><i class="fas fa-sliders-h"></i> Settings</a>
        </div>
    </nav>

    <!-- ===== SIDEBAR USER - CLICKABLE ===== -->
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

    <div class="sidebar-footer">
        <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
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
    <div class="modal-overlay" id="weatherModal">
        <div class="weather-modal">
            <button class="close-btn" onclick="closeWeatherModal()">&times;</button>
            <h2><i class="fas <?php echo getWeatherIcon($weather['condition']); ?>"></i> <?php echo $weather['city']; ?>, <?php echo $weather['country']; ?></h2>
            <div style="text-align:center;font-size:3rem;font-weight:800;"><?php echo $weather['temp']; ?>°C</div>
            <div style="text-align:center;color:var(--text-muted);"><?php echo ucfirst($weather['condition']); ?></div>
            <div class="weather-details-grid">
                <div class="weather-detail-item"><i class="fas fa-temperature-high"></i><div class="label">Feels Like</div><div class="value"><?php echo $weather['feels_like']; ?>°C</div></div>
                <div class="weather-detail-item"><i class="fas fa-thermometer-half"></i><div class="label">Min / Max</div><div class="value"><?php echo $weather['temp_min']; ?>° / <?php echo $weather['temp_max']; ?>°</div></div>
                <div class="weather-detail-item"><i class="fas fa-tint"></i><div class="label">Humidity</div><div class="value"><?php echo $weather['humidity']; ?>%</div></div>
                <div class="weather-detail-item"><i class="fas fa-compress-alt"></i><div class="label">Pressure</div><div class="value"><?php echo $weather['pressure']; ?> hPa</div></div>
                <div class="weather-detail-item"><i class="fas fa-wind"></i><div class="label">Wind Speed</div><div class="value"><?php echo $weather['wind_speed']; ?> km/h</div></div>
                <div class="weather-detail-item"><i class="fas fa-sun"></i><div class="label">Sunrise / Sunset</div><div class="value"><?php echo $weather['sunrise']; ?> / <?php echo $weather['sunset']; ?></div></div>
            </div>
            <button class="weather-refresh" onclick="refreshWeather()" style="display:block;margin:1rem auto 0;padding:0.5rem 1rem;background:var(--accent);border:none;border-radius:20px;cursor:pointer;font-weight:600;color:#fff;"><i class="fas fa-sync-alt"></i> Refresh Weather</button>
        </div>
    </div>

    <div class="page-content">
        <h1 class="page-title"><i class="fas fa-user-cog" style="color:var(--accent-dark);"></i> Account Settings</h1>
        <p class="page-subtitle">Manage your personal information and account settings</p>

        <div class="profile-grid">
            <!-- Left Column - Profile Info -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php 
                        $initial = strtoupper(substr($user['full_name'] ?? $_SESSION['admin_username'] ?? 'A', 0, 1));
                        echo $initial;
                    ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['full_name'] ?? $_SESSION['admin_username'] ?? 'Admin'); ?></div>
                <div class="profile-role"><?php echo ucfirst($user['role'] ?? 'Administrator'); ?></div>
                
                <div class="profile-meta">
                    <div class="meta-item">
                        <span class="label"><i class="fas fa-user"></i> Username</span>
                        <span class="value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="label"><i class="fas fa-envelope"></i> Email</span>
                        <span class="value"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="label"><i class="fas fa-calendar-alt"></i> Joined</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="label"><i class="fas fa-shield-alt"></i> Role</span>
                        <span class="value"><?php echo ucfirst($user['role'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Column - Edit Forms -->
            <div>
                <!-- Update Profile Form -->
                <div class="profile-card">
                    <h3><i class="fas fa-edit" style="color:var(--accent-dark);"></i> Edit Profile</h3>
                    <form id="profileForm">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email">
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" placeholder="Enter username">
                        </div>
                        <button type="submit" class="btn-save" id="updateProfileBtn"><i class="fas fa-save"></i> Update Profile</button>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="profile-card">
                    <h3><i class="fas fa-key" style="color:var(--orange);"></i> Change Password</h3>
                    <form id="passwordForm">
                        <div class="form-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="current_password" placeholder="Enter current password">
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="new_password" placeholder="Enter new password (min 6 characters)">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm new password">
                        </div>
                        <button type="submit" class="btn-secondary" id="changePasswordBtn"><i class="fas fa-key"></i> Change Password</button>
                    </form>
                </div>
            </div>
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

    // ===== UPDATE PROFILE =====
    document.getElementById('profileForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('updateProfileBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Saving...';
        btn.disabled = true;

        const formData = new FormData(this);
        formData.append('action', 'update_profile');

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error updating profile', true);
        } finally {
            btn.innerHTML = '<i class="fas fa-save"></i> Update Profile';
            btn.disabled = false;
        }
    });

    // ===== CHANGE PASSWORD =====
    document.getElementById('passwordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('changePasswordBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Changing...';
        btn.disabled = true;

        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (newPassword !== confirmPassword) {
            showToast('New passwords do not match', true);
            btn.innerHTML = '<i class="fas fa-key"></i> Change Password';
            btn.disabled = false;
            return;
        }

        if (newPassword.length < 6) {
            showToast('Password must be at least 6 characters', true);
            btn.innerHTML = '<i class="fas fa-key"></i> Change Password';
            btn.disabled = false;
            return;
        }

        try {
            const response = await fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=change_password&current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmPassword)}`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error changing password', true);
        } finally {
            btn.innerHTML = '<i class="fas fa-key"></i> Change Password';
            btn.disabled = false;
        }
    });

    // ===== CLOCK =====
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('open');
    });

    function openWeatherModal() { document.getElementById('weatherModal').classList.add('active'); }
    function closeWeatherModal() { document.getElementById('weatherModal').classList.remove('active'); }
    function refreshWeather() { window.location.href = 'profile.php?refresh_weather=1'; }
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