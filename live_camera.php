<?php
// live_camera.php - Live Camera Feed Module with Database (PDO)
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

// ===== FETCH CAMERA SNAPSHOTS =====
global $pdo;
$snapStmt = $pdo->prepare("SELECT * FROM camera_snapshots WHERE user_id = ? ORDER BY timestamp DESC LIMIT 4");
$snapStmt->execute([$userId]);
$snapshots = $snapStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== FETCH RECENT DETECTIONS =====
$detStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM detection_logs WHERE user_id = ? GROUP BY status");
$detStmt->execute([$userId]);
$detCounts = $detStmt->fetchAll(PDO::FETCH_ASSOC);
$statusCounts = ['healthy' => 0, 'weak' => 0, 'unhealthy' => 0];
foreach ($detCounts as $d) {
    if (isset($statusCounts[$d['status']])) $statusCounts[$d['status']] = (int)$d['count'];
}

// ===== GET SENSOR DATA =====
function getSensorData() {
    $hour = (int)date('H');
    $baseTemp = 28;
    $tempVariation = sin(($hour - 14) * M_PI / 12) * 4;
    return [
        'temperature' => round($baseTemp + $tempVariation + mt_rand(-2, 2) / 10, 1),
        'humidity' => round(55 + sin($hour * M_PI / 12) * 15 + mt_rand(-3, 3), 1)
    ];
}
$sensorData = getSensorData();

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
$streamQuality = '1080p';
$fps = 30;
$lastDetection = '2 seconds ago';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Camera Feed | BroilerGuard</title>
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

        .camera-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .camera-feed { background: #1a1a1a; border-radius: var(--border-radius); overflow: hidden; position: relative; min-height: 500px; box-shadow: var(--shadow-md); }
        .camera-feed .feed-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #666; }
        .camera-feed .feed-placeholder i { font-size: 5rem; margin-bottom: 1rem; color: #444; }
        .camera-feed .feed-placeholder p { font-size: 1rem; }
        .camera-feed .feed-overlay { position: absolute; top: 1rem; left: 1rem; display: flex; gap: 0.5rem; }
        .camera-feed .feed-overlay .overlay-badge { background: rgba(0,0,0,0.7); color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .camera-feed .feed-overlay .overlay-badge.live { background: var(--red); animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .camera-feed .detection-boxes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .camera-feed .detection-box { position: absolute; border: 2px solid var(--green); border-radius: 4px; }
        .camera-feed .detection-box.weak { border-color: var(--yellow); }
        .camera-feed .detection-box.unhealthy { border-color: var(--red); }
        .camera-feed .detection-box .box-label { position: absolute; top: -20px; left: -2px; background: var(--green); color: white; font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 3px; white-space: nowrap; }
        .camera-feed .detection-box.weak .box-label { background: var(--yellow); }
        .camera-feed .detection-box.unhealthy .box-label { background: var(--red); }
        .camera-feed .feed-controls { position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%); display: flex; gap: 0.5rem; }
        .camera-feed .feed-controls button { background: rgba(0,0,0,0.7); color: white; border: none; padding: 0.5rem 1rem; border-radius: 20px; cursor: pointer; font-size: 0.8rem; font-family: 'Inter', sans-serif; transition: all 0.2s; }
        .camera-feed .feed-controls button:hover { background: rgba(0,0,0,0.9); }
        .camera-feed .feed-controls button.active { background: var(--red); }

        .camera-info-panel { display: flex; flex-direction: column; gap: 1rem; }
        .info-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.15); }
        .info-card .info-header { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        .info-card .info-row { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid rgba(141,180,142,0.05); font-size: 0.8rem; }
        .info-card .info-row:last-child { border-bottom: none; }
        .info-card .info-row .info-label { color: var(--text-muted); }
        .info-card .info-row .info-value { font-weight: 600; }

        .card-badge { padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }

        .detection-preview { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .detection-item { background: var(--bg-card); border-radius: 10px; padding: 1rem; text-align: center; border: 1px solid rgba(141,180,142,0.10); }
        .detection-item .detection-icon { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .detection-item .detection-value { font-size: 1.2rem; font-weight: 700; }
        .detection-item .detection-label { font-size: 0.7rem; color: var(--text-muted); }

        .snapshot-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 1.5rem; }
        .snapshot-card { background: var(--bg-card); border-radius: 10px; padding: 0.8rem; text-align: center; border: 1px solid rgba(141,180,142,0.10); }
        .snapshot-card .snapshot-img { width: 100%; height: 80px; background: #1a1a1a; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.7rem; margin-bottom: 0.5rem; }
        .snapshot-card .snapshot-time { font-size: 0.7rem; color: var(--text-muted); }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .camera-grid { grid-template-columns: 1fr; }
            .snapshot-grid { grid-template-columns: repeat(2, 1fr); }
            .camera-feed { min-height: 350px; }
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
            <a href="live_camera.php" class="active"><i class="fas fa-camera"></i> Live Camera Feed</a>
            <a href="detection_results.php"><i class="fas fa-brain"></i> Detection Results</a>
            <a href="detection_history.php"><i class="fas fa-history"></i> Detection History</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Automation</div>
            <a href="fan_control.php"><i class="fas fa-fan"></i> Fan Control</a>
            <a href="feed_dispenser.php"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
            <a href="water_pump.php"><i class="fas fa-hand-holding-water"></i> Water Pump</a>
            <a href="light_control.php"><i class="fas fa-lightbulb"></i> Light Control</a>
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
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
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
        <h1 class="page-title"><i class="fas fa-camera" style="color:var(--blue);"></i> Live Camera Feed</h1>
        <p class="page-subtitle">Real-time AI-powered monitoring with object detection</p>

        <div class="camera-grid">
            <div class="camera-feed" id="cameraFeed">
                <div class="feed-overlay">
                    <span class="overlay-badge live"><i class="fas fa-circle"></i> LIVE</span>
                    <span class="overlay-badge">Camera 1</span>
                    <span class="overlay-badge" style="background:var(--accent-dark);"><i class="fas fa-microchip"></i> AI Active</span>
                </div>
                <div class="feed-placeholder" id="feedPlaceholder">
                    <i class="fas fa-video"></i>
                    <p>Camera Feed Active</p>
                    <p style="font-size:0.8rem;margin-top:0.5rem;"><?php echo $streamQuality; ?> @ <?php echo $fps; ?> FPS</p>
                </div>
                <div class="detection-boxes" id="detectionBoxes">
                    <div class="detection-box" style="top:15%;left:10%;width:80px;height:80px;">
                        <span class="box-label">CHK-001 Healthy 98%</span>
                    </div>
                    <div class="detection-box" style="top:20%;left:35%;width:80px;height:80px;">
                        <span class="box-label">CHK-002 Healthy 96%</span>
                    </div>
                    <div class="detection-box weak" style="top:18%;left:55%;width:80px;height:80px;">
                        <span class="box-label">CHK-004 Weak 87%</span>
                    </div>
                    <div class="detection-box" style="top:50%;left:20%;width:80px;height:80px;">
                        <span class="box-label">CHK-003 Healthy 99%</span>
                    </div>
                    <div class="detection-box unhealthy" style="top:55%;left:60%;width:80px;height:80px;">
                        <span class="box-label">CHK-005 Unhealthy 92%</span>
                    </div>
                </div>
                <div class="feed-controls">
                    <button onclick="toggleDetection()" class="active" id="btnDetection"><i class="fas fa-brain"></i> AI Detection</button>
                    <button onclick="captureSnapshot()"><i class="fas fa-camera"></i> Capture</button>
                    <button onclick="toggleFullscreen()"><i class="fas fa-expand"></i> Fullscreen</button>
                </div>
            </div>

            <div class="camera-info-panel">
                <div class="info-card">
                    <div class="info-header"><i class="fas fa-info-circle" style="color:var(--blue);"></i> Camera Status</div>
                    <div class="info-row"><span class="info-label">Status</span><span class="info-value" style="color:var(--green);"><i class="fas fa-circle"></i> Online</span></div>
                    <div class="info-row"><span class="info-label">Resolution</span><span class="info-value"><?php echo $streamQuality; ?></span></div>
                    <div class="info-row"><span class="info-label">Frame Rate</span><span class="info-value"><?php echo $fps; ?> FPS</span></div>
                    <div class="info-row"><span class="info-label">AI Detection</span><span class="info-value" style="color:var(--green);">Active</span></div>
                    <div class="info-row"><span class="info-label">Last Detection</span><span class="info-value"><?php echo $lastDetection; ?></span></div>
                </div>
                <div class="info-card">
                    <div class="info-header"><i class="fas fa-chart-bar" style="color:var(--green);"></i> Detection Summary</div>
                    <div class="info-row"><span class="info-label">Total Chicks</span><span class="info-value"><?php echo array_sum($statusCounts); ?></span></div>
                    <div class="info-row"><span class="info-label">Healthy</span><span class="info-value" style="color:var(--green);"><?php echo $statusCounts['healthy']; ?></span></div>
                    <div class="info-row"><span class="info-label">Weak</span><span class="info-value" style="color:var(--yellow);"><?php echo $statusCounts['weak']; ?></span></div>
                    <div class="info-row"><span class="info-label">Unhealthy</span><span class="info-value" style="color:var(--red);"><?php echo $statusCounts['unhealthy']; ?></span></div>
                </div>
                <div class="info-card">
                    <div class="info-header"><i class="fas fa-thermometer-half" style="color:var(--orange);"></i> Environment</div>
                    <div class="info-row"><span class="info-label">Temperature</span><span class="info-value"><?php echo $sensorData['temperature']; ?>°C</span></div>
                    <div class="info-row"><span class="info-label">Humidity</span><span class="info-value"><?php echo $sensorData['humidity']; ?>%</span></div>
                </div>
            </div>
        </div>

        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.8rem; font-weight:700;">
            <span><i class="fas fa-images"></i> Recent Snapshots</span>
            <span style="font-size:0.7rem;">Auto-captured every 5 minutes</span>
        </div>
        <div class="snapshot-grid">
            <?php if (empty($snapshots)): ?>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="snapshot-card">
                    <div class="snapshot-img"><i class="fas fa-image"></i> Snapshot</div>
                    <div class="snapshot-time">No data</div>
                </div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($snapshots as $snap): ?>
                <div class="snapshot-card">
                    <div class="snapshot-img">
                        <?php if (!empty($snap['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($snap['image_url']); ?>" alt="Snapshot" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">
                        <?php else: ?>
                            <i class="fas fa-image"></i> Snapshot
                        <?php endif; ?>
                    </div>
                    <div class="snapshot-time"><?php echo date('h:i A', strtotime($snap['timestamp'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:0.8rem; font-weight:700;">
            <span><i class="fas fa-robot"></i> AI Detection Preview</span>
        </div>
        <div class="detection-preview">
            <div class="detection-item">
                <div class="detection-icon" style="color:var(--green);"><i class="fas fa-check-circle"></i></div>
                <div class="detection-value" style="color:var(--green);"><?php echo $statusCounts['healthy']; ?></div>
                <div class="detection-label">Healthy Detected</div>
            </div>
            <div class="detection-item">
                <div class="detection-icon" style="color:var(--yellow);"><i class="fas fa-exclamation-circle"></i></div>
                <div class="detection-value" style="color:var(--yellow);"><?php echo $statusCounts['weak']; ?></div>
                <div class="detection-label">Weak Detected</div>
            </div>
            <div class="detection-item">
                <div class="detection-icon" style="color:var(--red);"><i class="fas fa-times-circle"></i></div>
                <div class="detection-value" style="color:var(--red);"><?php echo $statusCounts['unhealthy']; ?></div>
                <div class="detection-label">Unhealthy Detected</div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }
    setInterval(updateDateTime, 1000);
    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'live_camera.php?refresh_weather=1'; }
    document.getElementById('weatherModal').addEventListener('click', function(e) { if (e.target === this) closeWeatherModal(); });

    function toggleDetection() {
        const btn = document.getElementById('btnDetection');
        const boxes = document.getElementById('detectionBoxes');
        btn.classList.toggle('active');
        boxes.style.display = btn.classList.contains('active') ? 'block' : 'none';
        btn.innerHTML = btn.classList.contains('active') ? '<i class="fas fa-brain"></i> AI Detection' : '<i class="fas fa-brain"></i> AI Off';
    }

    function captureSnapshot() {
        alert('Snapshot captured successfully! Saved to gallery.');
        // In production: AJAX call to save snapshot
    }

    function toggleFullscreen() {
        const feed = document.getElementById('cameraFeed');
        if (document.fullscreenElement) document.exitFullscreen();
        else feed.requestFullscreen();
    }

    function animateDetectionBoxes() {
        const boxes = document.querySelectorAll('.detection-box');
        boxes.forEach(box => {
            const currentTop = parseFloat(box.style.top);
            const currentLeft = parseFloat(box.style.left);
            const newTop = Math.max(5, Math.min(70, currentTop + (Math.random() - 0.5) * 2));
            const newLeft = Math.max(5, Math.min(75, currentLeft + (Math.random() - 0.5) * 2));
            box.style.top = newTop + '%';
            box.style.left = newLeft + '%';
            box.style.transition = 'all 0.5s ease';
        });
    }
    setInterval(animateDetectionBoxes, 1000);

    window.addEventListener('load', function() {
        const activeMenu = document.querySelector('.sidebar-nav a.active');
        if (activeMenu) activeMenu.scrollIntoView({ behavior: 'auto', block: 'center' });
        updateDateTime();
    });
</script>
</body>
</html>