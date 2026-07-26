<?php
// feed_inventory.php - Feed Inventory Management with Database (PDO)
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

$userId = 1; // In production, get from session

// ===== FETCH INVENTORY FROM DATABASE =====
global $pdo;
$invStmt = $pdo->prepare("SELECT * FROM feed_inventory WHERE user_id = ?");
$invStmt->execute([$userId]);
$inventory = $invStmt->fetch(PDO::FETCH_ASSOC);

if (!$inventory) {
    $pdo->prepare("INSERT INTO feed_inventory (user_id, current_level, capacity, unit, alert_threshold, critical_threshold, supplier, feed_type) VALUES (?, 100.0, 200.0, 'kg', 20.0, 10.0, 'Local Feed Supply', 'Broiler Starter')")->execute([$userId]);
    $invStmt->execute([$userId]);
    $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
}

$currentLevel = (float)$inventory['current_level'];
$capacity = (float)$inventory['capacity'];
$alertThreshold = (float)$inventory['alert_threshold'];
$criticalThreshold = (float)$inventory['critical_threshold'];
$supplier = $inventory['supplier'];
$feedType = $inventory['feed_type'];
$lastRefill = $inventory['last_refill'];

// ===== FETCH TRANSACTIONS =====
$transStmt = $pdo->prepare("SELECT * FROM feed_transactions WHERE user_id = ? ORDER BY timestamp DESC LIMIT 100");
$transStmt->execute([$userId]);
$transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'update_settings') {
        $capacity = floatval($_POST['capacity'] ?? 200);
        $alertThreshold = floatval($_POST['alert_threshold'] ?? 20);
        $criticalThreshold = floatval($_POST['critical_threshold'] ?? 10);
        $supplier = $_POST['supplier'] ?? 'Local Feed Supply';
        $feedType = $_POST['feed_type'] ?? 'Broiler Starter';
        $update = $pdo->prepare("UPDATE feed_inventory SET capacity = ?, alert_threshold = ?, critical_threshold = ?, supplier = ?, feed_type = ? WHERE user_id = ?");
        $update->execute([$capacity, $alertThreshold, $criticalThreshold, $supplier, $feedType, $userId]);
        $response = ['success' => true, 'message' => 'Settings saved'];
    }
    elseif ($action === 'add_refill') {
        $amount = floatval($_POST['amount'] ?? 0);
        $cost = floatval($_POST['cost'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        if ($amount <= 0) {
            $response = ['success' => false, 'message' => 'Invalid amount'];
            echo json_encode($response); exit;
        }
        $newLevel = min($capacity, $currentLevel + $amount);
        $update = $pdo->prepare("UPDATE feed_inventory SET current_level = ?, last_refill = NOW() WHERE user_id = ?");
        $update->execute([$newLevel, $userId]);
        $log = $pdo->prepare("INSERT INTO feed_transactions (user_id, type, amount, source, notes, new_level, timestamp) VALUES (?, 'refill', ?, 'manual', ?, ?, NOW())");
        $log->execute([$userId, $amount, $notes, $newLevel]);
        $response = ['success' => true, 'message' => "Refilled {$amount}kg", 'new_level' => $newLevel];
    }
    elseif ($action === 'manual_deduct') {
        $amount = floatval($_POST['amount'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        if ($amount <= 0 || $currentLevel - $amount < 0) {
            $response = ['success' => false, 'message' => 'Invalid amount or insufficient level'];
            echo json_encode($response); exit;
        }
        $newLevel = $currentLevel - $amount;
        $update = $pdo->prepare("UPDATE feed_inventory SET current_level = ? WHERE user_id = ?");
        $update->execute([$newLevel, $userId]);
        $log = $pdo->prepare("INSERT INTO feed_transactions (user_id, type, amount, source, notes, remaining, timestamp) VALUES (?, 'consumption', ?, 'manual', ?, ?, NOW())");
        $log->execute([$userId, $amount, $notes, $newLevel]);
        $response = ['success' => true, 'message' => "Deducted {$amount}kg", 'remaining' => $newLevel];
    }
    elseif ($action === 'delete_transaction') {
        $id = $_POST['id'] ?? 0;
        // We need to reverse the transaction effect: if refill, subtract; if consumption, add
        $get = $pdo->prepare("SELECT * FROM feed_transactions WHERE id = ? AND user_id = ?");
        $get->execute([$id, $userId]);
        $trans = $get->fetch(PDO::FETCH_ASSOC);
        if ($trans) {
            if ($trans['type'] === 'refill') {
                $newLevel = $currentLevel - $trans['amount'];
            } else {
                $newLevel = $currentLevel + $trans['amount'];
            }
            $update = $pdo->prepare("UPDATE feed_inventory SET current_level = ? WHERE user_id = ?");
            $update->execute([max(0, $newLevel), $userId]);
            $pdo->prepare("DELETE FROM feed_transactions WHERE id = ?")->execute([$id]);
            $response = ['success' => true, 'message' => 'Transaction deleted'];
        } else {
            $response = ['success' => false, 'message' => 'Transaction not found'];
        }
    }
    elseif ($action === 'clear_all') {
        $pdo->prepare("DELETE FROM feed_transactions WHERE user_id = ?")->execute([$userId]);
        $response = ['success' => true, 'message' => 'All transactions cleared'];
    }
    elseif ($action === 'get_status') {
        $response = ['success' => true, 'current_level' => $currentLevel, 'capacity' => $capacity, 'percentage' => ($capacity > 0) ? ($currentLevel / $capacity) * 100 : 0];
    }
    echo json_encode($response);
    exit;
}

// ===== COMPUTE STATS =====
$todayConsumption = 0;
$weeklyConsumption = 0;
$monthlyConsumption = 0;
$autoDispensed = 0;
$manualDispensed = 0;
$totalRefills = 0;
$totalCost = 0;
$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$monthAgo = date('Y-m-d', strtotime('-30 days'));

foreach ($transactions as $t) {
    if ($t['type'] === 'consumption') {
        $transDate = date('Y-m-d', strtotime($t['timestamp']));
        if ($transDate === $today) $todayConsumption += $t['amount'];
        if ($transDate >= $weekAgo) $weeklyConsumption += $t['amount'];
        if ($transDate >= $monthAgo) $monthlyConsumption += $t['amount'];
        if ($t['source'] === 'auto_dispenser') $autoDispensed += $t['amount'];
        if ($t['source'] === 'manual') $manualDispensed += $t['amount'];
    }
    if ($t['type'] === 'refill') {
        $totalRefills += $t['amount'];
        if (isset($t['cost'])) $totalCost += $t['cost'];
    }
}

$avgDailyConsumption = 5;
$consumptionDays = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'consumption' && date('Y-m-d', strtotime($t['timestamp'])) >= $weekAgo) {
        $consumptionDays++;
    }
}
if ($consumptionDays > 0 && $weeklyConsumption > 0) {
    $avgDailyConsumption = $weeklyConsumption / $consumptionDays;
}
$daysRemaining = $avgDailyConsumption > 0 ? floor($currentLevel / $avgDailyConsumption) : 0;
$percentage = ($capacity > 0) ? ($currentLevel / $capacity) * 100 : 0;
$isLow = $currentLevel <= $alertThreshold;
$isCritical = $currentLevel <= $criticalThreshold;

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Inventory | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ===== DIRECT CSS VARIABLES (FALLBACK) ===== */
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
        .notification-bell { position: relative; background: var(--bg-secondary); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; border: 1px solid rgba(77, 114, 77, 0.15); }
        .notification-bell:hover { background: var(--accent-light); transform: scale(1.05); }
        .notification-bell i { font-size: 1.2rem; color: var(--text-secondary); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--red); color: white; font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 50%; min-width: 18px; text-align: center; }
        .back-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid rgba(141, 180, 142, 0.3); border-radius: 10px; color: var(--text-primary); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
        .back-btn:hover { background: var(--accent-light); border-color: var(--accent); }

        /* ===== PAGE CONTENT ===== */
        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; color: var(--text-primary); }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1rem 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.15); display: flex; align-items: center; justify-content: space-between; }
        .stat-info .stat-value { font-size: 1.4rem; font-weight: 800; }
        .stat-info .stat-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        .stat-icon { font-size: 1.8rem; opacity: 0.6; color: var(--accent); }

        .inventory-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(141,180,142,0.10); box-shadow: var(--shadow-sm); }
        .level-container { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .level-gauge { flex: 2; min-width: 200px; }
        .level-info { flex: 1; min-width: 180px; text-align: center; }
        .progress-bar-bg { width: 100%; height: 35px; background: #E0D5C0; border-radius: 20px; overflow: hidden; margin: 0.5rem 0; }
        .progress-fill { height: 100%; border-radius: 20px; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: white; }
        .level-value { font-size: 2rem; font-weight: 800; }
        .level-unit { font-size: 0.8rem; color: var(--text-muted); }

        .action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
        .btn-primary { background: var(--accent); border: none; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: white; }
        .btn-primary:hover { background: var(--accent-dark); }
        .btn-secondary { background: var(--bg-secondary); border: 1px solid var(--accent); padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .btn-secondary:hover { background: var(--accent-light); }
        .btn-danger { background: var(--red-light); border: 1px solid var(--red); padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: var(--red); }
        .btn-info { background: var(--blue-light); border: 1px solid var(--blue); padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: var(--blue); }

        .warning-alert { background: var(--orange-light); border-left: 4px solid var(--orange); padding: 0.8rem 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; }
        .warning-alert.critical { background: var(--red-light); border-left-color: var(--red); }

        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .setting-field { display: flex; flex-direction: column; gap: 0.3rem; }
        .setting-field label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .setting-field input, .setting-field select { padding: 0.6rem; border: 1px solid #E0D5C0; border-radius: 10px; background: var(--bg-secondary); font-family: 'Inter', sans-serif; font-size: 0.85rem; }

        .transactions-table { width: 100%; border-collapse: collapse; }
        .transactions-table th { text-align: left; padding: 0.8rem; background: var(--bg-secondary); font-weight: 600; font-size: 0.75rem; color: var(--text-muted); }
        .transactions-table td { padding: 0.8rem; border-bottom: 1px solid rgba(141,180,142,0.08); font-size: 0.8rem; }
        .badge-refill { background: var(--green-light); color: var(--green); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-consumption { background: var(--blue-light); color: var(--blue); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-auto { background: var(--purple-light); color: var(--purple); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-manual { background: var(--orange-light); color: var(--orange); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 20px; padding: 1.5rem; max-width: 400px; width: 90%; }
        .modal-content h3 { margin-bottom: 1rem; font-size: 1.1rem; }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: flex-end; }
        .modal-confirm { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; color: #FFFFFF; font-family: 'Inter', sans-serif; }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }

        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .integration-note { background: linear-gradient(135deg, #E8F5E9, #C8E6C9); border-radius: var(--border-radius); padding: 0.8rem 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem; font-size: 0.8rem; color: #2E7D32; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .level-container { flex-direction: column; text-align: center; }
            .level-gauge { width: 100%; }
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
        <div class="nav-section"><div class="nav-section-title">Inventory</div>
            <a href="feed_inventory.php" class="active"><i class="fas fa-utensils"></i> Feed Inventory</a>
            <a href="water_inventory.php"><i class="fas fa-water"></i> Water Inventory</a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Monitoring</div>
            <a href="temperature.php"><i class="fas fa-thermometer-half"></i> Temperature & Humidity</a>
            <a href="feed_monitoring.php"><i class="fas fa-chart-line"></i> Feed Monitoring</a>
            <a href="water_monitoring.php"><i class="fas fa-chart-line"></i> Water Monitoring</a>
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
            <a href="automation_settings.php"><i class="fas fa-cog"></i> Automation Settings</a>
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
    </div>
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
        <h1 class="page-title"><i class="fas fa-utensils" style="color:var(--accent);"></i> Feed Inventory Management</h1>
        <p class="page-subtitle">Track feed levels, manage refills, and monitor consumption</p>

        <div class="integration-note">
            <i class="fas fa-sync-alt"></i>
            <span><strong>Integrated with Feed Dispenser</strong> — Every time feed is dispensed, inventory is automatically updated.</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--green);"><?php echo number_format($currentLevel, 1); ?> kg</div><div class="stat-label">Current Level</div></div><div class="stat-icon"><i class="fas fa-box"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--accent-dark);"><?php echo number_format($todayConsumption, 1); ?> kg</div><div class="stat-label">Today's Consumption</div></div><div class="stat-icon"><i class="fas fa-chart-line"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--blue);"><?php echo number_format($weeklyConsumption, 1); ?> kg</div><div class="stat-label">This Week</div></div><div class="stat-icon"><i class="fas fa-calendar-week"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--purple);"><?php echo number_format($autoDispensed, 1); ?> kg</div><div class="stat-label">Auto Dispensed</div></div><div class="stat-icon"><i class="fas fa-robot"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--orange);"><?php echo $daysRemaining; ?> days</div><div class="stat-label">Est. Days Left</div></div><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div></div>
        </div>

        <?php if ($isLow || $isCritical): ?>
        <div class="warning-alert <?php echo $isCritical ? 'critical' : ''; ?>">
            <i class="fas <?php echo $isCritical ? 'fa-skull-crossbones' : 'fa-exclamation-triangle'; ?>"></i>
            <span><?php echo $isCritical ? 'CRITICAL: Feed level extremely low! Refill immediately.' : 'Warning: Feed level is low. Consider refilling soon.'; ?></span>
            <button onclick="openRefillModal()" style="margin-left:auto; background:var(--accent); border:none; padding:0.3rem 1rem; border-radius:20px; cursor:pointer; color:white;">Refill Now</button>
        </div>
        <?php endif; ?>

        <!-- Current Level -->
        <div class="inventory-card">
            <h3 style="margin-bottom:1rem;"><i class="fas fa-chart-simple"></i> Current Feed Level</h3>
            <div class="level-container">
                <div class="level-gauge">
                    <div class="progress-bar-bg">
                        <div class="progress-fill" style="width:<?php echo max(0, min(100, $percentage)); ?>%; background:<?php echo $isCritical ? '#A44A3F' : ($isLow ? '#B9772A' : '#4D724D'); ?>;">
                            <?php echo round($percentage, 1); ?>%
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:var(--text-muted);">
                        <span>0 kg</span>
                        <span>Alert: <?php echo $alertThreshold; ?> kg</span>
                        <span>Capacity: <?php echo $capacity; ?> kg</span>
                    </div>
                </div>
                <div class="level-info">
                    <div class="level-value"><?php echo number_format($currentLevel, 1); ?></div>
                    <div class="level-unit">kilograms remaining</div>
                    <div style="margin-top:0.5rem; font-size:0.7rem;">Last refill: <?php echo date('M d, Y', strtotime($lastRefill)); ?></div>
                </div>
            </div>
            <div class="action-buttons">
                <button class="btn-primary" onclick="openRefillModal()"><i class="fas fa-plus-circle"></i> Add Refill</button>
                <button class="btn-secondary" onclick="openDeductModal()"><i class="fas fa-minus-circle"></i> Manual Deduction</button>
                <button class="btn-info" onclick="checkInventoryStatus()"><i class="fas fa-chart-line"></i> Check Status</button>
            </div>
        </div>

        <!-- Settings -->
        <div class="inventory-card">
            <h3 style="margin-bottom:1rem;"><i class="fas fa-cog"></i> Inventory Settings</h3>
            <div class="settings-grid">
                <div class="setting-field">
                    <label>Storage Capacity (kg)</label>
                    <input type="number" id="capacity" step="10" value="<?php echo $capacity; ?>">
                </div>
                <div class="setting-field">
                    <label>Alert Threshold (kg)</label>
                    <input type="number" id="alertThreshold" step="1" value="<?php echo $alertThreshold; ?>">
                </div>
                <div class="setting-field">
                    <label>Critical Threshold (kg)</label>
                    <input type="number" id="criticalThreshold" step="1" value="<?php echo $criticalThreshold; ?>">
                </div>
                <div class="setting-field">
                    <label>Feed Type</label>
                    <select id="feedType">
                        <option value="Broiler Starter" <?php echo $feedType == 'Broiler Starter' ? 'selected' : ''; ?>>Broiler Starter</option>
                        <option value="Broiler Grower" <?php echo $feedType == 'Broiler Grower' ? 'selected' : ''; ?>>Broiler Grower</option>
                        <option value="Broiler Finisher" <?php echo $feedType == 'Broiler Finisher' ? 'selected' : ''; ?>>Broiler Finisher</option>
                        <option value="Layer Mash" <?php echo $feedType == 'Layer Mash' ? 'selected' : ''; ?>>Layer Mash</option>
                    </select>
                </div>
                <div class="setting-field">
                    <label>Supplier</label>
                    <input type="text" id="supplier" value="<?php echo htmlspecialchars($supplier); ?>">
                </div>
            </div>
            <button class="btn-secondary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button>
        </div>

        <!-- Transactions -->
        <div class="inventory-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3><i class="fas fa-history"></i> Transaction History</h3>
                <div>
                    <button class="btn-danger" style="padding:0.3rem 0.8rem; font-size:0.7rem; margin-right:0.5rem;" onclick="clearAllTransactions()">Clear All</button>
                    <span style="font-size:0.7rem; color:var(--text-muted);"><?php echo count($transactions); ?> records</span>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <?php if (empty($transactions)): ?>
                    <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                        <i class="fas fa-inbox" style="font-size:2rem; margin-bottom:0.5rem; display:block;"></i>
                        No transactions yet. Add a refill or dispense feed to get started.
                    </div>
                <?php else: ?>
                <table class="transactions-table">
                    <thead><tr><th>Date & Time</th><th>Type</th><th>Amount</th><th>Source</th><th>Notes</th><th>Remaining</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td style="font-size:0.7rem;"><?php echo date('M d, h:i A', strtotime($trans['timestamp'])); ?></td>
                            <td><span class="badge-<?php echo $trans['type'] === 'refill' ? 'refill' : 'consumption'; ?>"><?php echo $trans['type'] === 'refill' ? '➕ Refill' : '🍽️ Consumption'; ?></span></td>
                            <td style="font-weight:500;"><?php echo number_format($trans['amount'], 1); ?> kg</td>
                            <td><span class="badge-<?php echo $trans['source'] === 'auto_dispenser' ? 'auto' : 'manual'; ?>"><?php echo $trans['source'] === 'auto_dispenser' ? '🤖 Auto Dispenser' : '✋ Manual'; ?></span></td>
                            <td style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($trans['notes'] ?? '—'); ?></td>
                            <td style="font-size:0.7rem;"><?php echo isset($trans['remaining']) ? number_format($trans['remaining'], 1) . ' kg' : '—'; ?></td>
                            <td><button class="btn-danger" style="padding:0.2rem 0.5rem; font-size:0.7rem;" onclick="deleteTransaction('<?php echo $trans['id']; ?>')"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Refill Modal -->
<div id="refillModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-plus-circle"></i> Add Feed Refill</h3>
        <div class="setting-field" style="margin-bottom:1rem;">
            <label>Amount (kg)</label>
            <input type="number" id="refillAmount" step="1" placeholder="Enter amount in kg">
        </div>
        <div class="setting-field" style="margin-bottom:1rem;">
            <label>Cost (PHP)</label>
            <input type="number" id="refillCost" step="10" placeholder="Total cost (optional)">
        </div>
        <div class="setting-field" style="margin-bottom:1rem;">
            <label>Notes</label>
            <input type="text" id="refillNotes" placeholder="Additional notes">
        </div>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeRefillModal()">Cancel</button>
            <button class="modal-confirm" onclick="confirmRefill()">Add Refill</button>
        </div>
    </div>
</div>

<!-- Deduct Modal -->
<div id="deductModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-minus-circle"></i> Manual Feed Deduction</h3>
        <div class="setting-field" style="margin-bottom:1rem;">
            <label>Amount to Deduct (kg)</label>
            <input type="number" id="deductAmount" step="0.5" placeholder="Enter amount">
        </div>
        <div class="setting-field" style="margin-bottom:1rem;">
            <label>Reason / Notes</label>
            <input type="text" id="deductNotes" placeholder="Reason for deduction">
        </div>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeDeductModal()">Cancel</button>
            <button class="modal-confirm" onclick="confirmDeduct()">Deduct Feed</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    function showToast(message, isError) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    function openRefillModal() { document.getElementById('refillModal').style.display = 'flex'; }
    function closeRefillModal() { document.getElementById('refillModal').style.display = 'none'; }
    function openDeductModal() { document.getElementById('deductModal').style.display = 'flex'; }
    function closeDeductModal() { document.getElementById('deductModal').style.display = 'none'; }

    async function confirmRefill() {
        const amount = parseFloat(document.getElementById('refillAmount').value);
        const cost = parseFloat(document.getElementById('refillCost').value) || 0;
        const notes = document.getElementById('refillNotes').value;
        if (isNaN(amount) || amount <= 0) { showToast('Please enter a valid amount', true); return; }
        try {
            const response = await fetch('feed_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=add_refill&amount=${amount}&cost=${cost}&notes=${encodeURIComponent(notes)}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error adding refill', true); }
        closeRefillModal();
    }

    async function confirmDeduct() {
        const amount = parseFloat(document.getElementById('deductAmount').value);
        const notes = document.getElementById('deductNotes').value;
        if (isNaN(amount) || amount <= 0) { showToast('Please enter a valid amount', true); return; }
        try {
            const response = await fetch('feed_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=manual_deduct&amount=${amount}&notes=${encodeURIComponent(notes)}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error deducting feed', true); }
        closeDeductModal();
    }

    async function saveSettings() {
        const capacity = document.getElementById('capacity').value;
        const alertThreshold = document.getElementById('alertThreshold').value;
        const criticalThreshold = document.getElementById('criticalThreshold').value;
        const feedType = document.getElementById('feedType').value;
        const supplier = document.getElementById('supplier').value;
        try {
            const response = await fetch('feed_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_settings&capacity=${capacity}&alert_threshold=${alertThreshold}&critical_threshold=${criticalThreshold}&feed_type=${encodeURIComponent(feedType)}&supplier=${encodeURIComponent(supplier)}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error saving settings', true); }
    }

    async function checkInventoryStatus() {
        try {
            const response = await fetch('feed_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=get_status'
            });
            const data = await response.json();
            if (data.success) showToast('Current level: ' + data.current_level + ' kg (' + data.percentage.toFixed(1) + '%)');
        } catch (error) { showToast('Error checking status', true); }
    }

    async function deleteTransaction(id) {
        if (!id || !confirm('Delete this transaction? This will adjust inventory levels accordingly.')) return;
        try {
            const response = await fetch('feed_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=delete_transaction&id=${id}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error deleting transaction', true); }
    }

    async function clearAllTransactions() {
        if (!confirm('Clear all transaction history? This cannot be undone.')) return;
        try {
            const response = await fetch('feed_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=clear_all'
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error clearing transactions', true); }
    }

    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }

    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    window.onclick = function(e) {
        if (e.target == document.getElementById('refillModal')) closeRefillModal();
        if (e.target == document.getElementById('deductModal')) closeDeductModal();
    };
    function openWeatherModal() { document.getElementById('weatherModal').style.display = 'flex'; }
    function closeWeatherModal() { document.getElementById('weatherModal').style.display = 'none'; }
    function refreshWeather() { window.location.href = 'feed_inventory.php?refresh_weather=1'; }
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
