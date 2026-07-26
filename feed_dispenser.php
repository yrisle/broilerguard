<?php
// feed_dispenser.php - Feed Dispenser Control with Database (PDO)
session_start();

require_once 'db_connect.php';        // PDO
require_once 'weather_functions.php'; // weather

$weather = getWeatherData();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$userId = 1; // In production, get from session

// ===== FETCH FEED INVENTORY FROM DATABASE =====
global $pdo;
$invStmt = $pdo->prepare("SELECT * FROM feed_inventory WHERE user_id = ?");
$invStmt->execute([$userId]);
$inventory = $invStmt->fetch(PDO::FETCH_ASSOC);

if (!$inventory) {
    // Insert default
    $pdo->prepare("INSERT INTO feed_inventory (user_id, current_level, capacity, unit, alert_threshold, critical_threshold, supplier, feed_type) VALUES (?, 100.0, 200.0, 'kg', 20.0, 10.0, 'Local Feed Supply', 'Broiler Starter')")->execute([$userId]);
    $inventory = [
        'current_level' => 100.0,
        'capacity' => 200.0,
        'unit' => 'kg',
        'last_refill' => date('Y-m-d H:i:s'),
        'alert_threshold' => 20.0,
        'critical_threshold' => 10.0,
        'supplier' => 'Local Feed Supply',
        'feed_type' => 'Broiler Starter'
    ];
}
$currentLevel = (float)$inventory['current_level'];
$capacity = (float)$inventory['capacity'];
$alertThreshold = (float)$inventory['alert_threshold'];
$criticalThreshold = (float)$inventory['critical_threshold'];

// ===== FETCH SCHEDULES =====
$schStmt = $pdo->prepare("SELECT * FROM feed_schedules WHERE user_id = ? ORDER BY time");
$schStmt->execute([$userId]);
$schedules = $schStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($schedules)) {
    // Insert default schedules
    $defaults = [['08:00:00', 1.0, 'Morning Feed'], ['12:00:00', 1.0, 'Afternoon Feed'], ['17:00:00', 1.0, 'Evening Feed']];
    foreach ($defaults as $d) {
        $pdo->prepare("INSERT INTO feed_schedules (user_id, time, amount, label) VALUES (?, ?, ?, ?)")->execute([$userId, $d[0], $d[1], $d[2]]);
    }
    $schStmt->execute([$userId]);
    $schedules = $schStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== FETCH TRANSACTIONS =====
$transStmt = $pdo->prepare("SELECT * FROM feed_transactions WHERE user_id = ? ORDER BY timestamp DESC LIMIT 50");
$transStmt->execute([$userId]);
$transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== HELPER FUNCTIONS (ESP32 Gate) =====
function getESP32GateStatus() {
    // Replace with real ESP32 call
    return 'CLOSED'; // or OPEN
}
function sendGateCommand($state) {
    // Replace with real ESP32 command
    return true;
}

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'dispense_feed') {
        $amount = floatval($_POST['amount'] ?? 0.5);
        $duration = max(1, round($amount * 4)); // seconds

        // Check inventory
        if ($currentLevel - $amount < 0) {
            $response = ['success' => false, 'message' => 'Insufficient feed level'];
            echo json_encode($response);
            exit;
        }

        // Open gate
        if (!sendGateCommand('OPEN')) {
            $response = ['success' => false, 'message' => 'Failed to open gate'];
            echo json_encode($response);
            exit;
        }

        sleep($duration);
        sendGateCommand('CLOSE');

        // Update inventory in database
        $newLevel = max(0, $currentLevel - $amount);
        $updateInv = $pdo->prepare("UPDATE feed_inventory SET current_level = ? WHERE user_id = ?");
        $updateInv->execute([$newLevel, $userId]);

        // Log transaction
        $logStmt = $pdo->prepare("INSERT INTO feed_transactions (user_id, type, amount, source, notes, remaining, timestamp) VALUES (?, 'consumption', ?, 'auto_dispenser', ?, ?, NOW())");
        $logStmt->execute([$userId, $amount, 'Dispensed via gate', $newLevel]);

        $response = ['success' => true, 'message' => "Dispensed {$amount}kg successfully", 'remaining' => $newLevel];
    }
    elseif ($action === 'gate_open_manual') {
        if (sendGateCommand('OPEN')) $response = ['success' => true, 'message' => 'Gate opened'];
        else $response = ['success' => false, 'message' => 'Failed to open gate'];
    }
    elseif ($action === 'gate_close_manual') {
        if (sendGateCommand('CLOSE')) $response = ['success' => true, 'message' => 'Gate closed'];
        else $response = ['success' => false, 'message' => 'Failed to close gate'];
    }
    elseif ($action === 'update_schedule') {
        $schedulesData = json_decode($_POST['schedules'] ?? '[]', true);
        // Delete existing and insert new
        $pdo->prepare("DELETE FROM feed_schedules WHERE user_id = ?")->execute([$userId]);
        foreach ($schedulesData as $sch) {
            if (isset($sch['time']) && isset($sch['amount']) && isset($sch['label'])) {
                $ins = $pdo->prepare("INSERT INTO feed_schedules (user_id, time, amount, label) VALUES (?, ?, ?, ?)");
                $ins->execute([$userId, $sch['time'], $sch['amount'], $sch['label']]);
            }
        }
        $response = ['success' => true, 'message' => 'Schedules updated!'];
    }
    elseif ($action === 'toggle_auto') {
        $enabled = $_POST['enabled'] === 'true';
        // Store in session or settings table (we'll use session for simplicity)
        $_SESSION['auto_feeding_enabled'] = $enabled;
        $response = ['success' => true, 'message' => $enabled ? 'Auto enabled' : 'Auto disabled'];
    }
    elseif ($action === 'refill_feed') {
        $amount = floatval($_POST['refill_amount'] ?? 10.0);
        $newLevel = min($capacity, $currentLevel + $amount);
        $updateInv = $pdo->prepare("UPDATE feed_inventory SET current_level = ?, last_refill = NOW() WHERE user_id = ?");
        $updateInv->execute([$newLevel, $userId]);
        $logStmt = $pdo->prepare("INSERT INTO feed_transactions (user_id, type, amount, source, notes, new_level, timestamp) VALUES (?, 'refill', ?, 'manual', ?, ?, NOW())");
        $logStmt->execute([$userId, $amount, 'Refill by admin', $newLevel]);
        $response = ['success' => true, 'message' => "Refilled {$amount}kg", 'remaining' => $newLevel];
    }

    echo json_encode($response);
    exit;
}

// ===== COMPUTE STATS =====
$todayConsumption = 0;
$today = date('Y-m-d');
foreach ($transactions as $t) {
    if ($t['type'] === 'consumption' && date('Y-m-d', strtotime($t['timestamp'])) === $today) {
        $todayConsumption += $t['amount'];
    }
}
$percentage = ($capacity > 0) ? ($currentLevel / $capacity) * 100 : 0;
$isLow = $currentLevel <= $alertThreshold;
$isCritical = $currentLevel <= $criticalThreshold;
$gateStatus = getESP32GateStatus();
$autoEnabled = $_SESSION['auto_feeding_enabled'] ?? true;

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
$unreadNotifications = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Dispenser | BroilerGuard</title>
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
            --purple: #8E44AD;
            --sidebar-width: 280px; --header-height: 70px;
            --border-radius: 16px;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); display: flex; min-height: 100vh; }

        /* ===== SIDEBAR (gaya ng detection_history) ===== */
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

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1rem 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(141,180,142,0.15); display: flex; align-items: center; justify-content: space-between; }
        .stat-info .stat-value { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
        .stat-info .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }
        .stat-icon { font-size: 2rem; opacity: 0.6; color: var(--accent); }

        .warning-alert { background: var(--yellow-light); border-left: 4px solid var(--yellow); padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem; font-size: 0.85rem; }
        .warning-alert.critical { background: var(--red-light); border-left-color: var(--red); }

        .automation-status-card { background: linear-gradient(135deg, var(--accent-dark), #3A5C3A); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; margin-bottom: 1.5rem; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .auto-status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.2rem 0.8rem; border-radius: 30px; font-size: 0.75rem; font-weight: 600; width: fit-content; margin-top: 0.3rem; }
        .status-enabled { background: var(--green); color: white; }
        .status-disabled { background: var(--red); color: white; }
        .toggle-switch-large { position: relative; display: inline-block; width: 52px; height: 26px; }
        .toggle-switch-large input { opacity: 0; width: 0; height: 0; }
        .toggle-slider-large { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #5A5A5A; transition: 0.2s; border-radius: 26px; }
        .toggle-slider-large:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: 0.2s; border-radius: 50%; }
        input:checked + .toggle-slider-large { background-color: var(--accent); }
        input:checked + .toggle-slider-large:before { transform: translateX(26px); }

        .manual-control-card, .gate-control-card, .add-schedule-card, .log-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(141,180,142,0.10); box-shadow: var(--shadow-sm); }
        .manual-control-card h3, .gate-control-card h3, .add-schedule-card .add-schedule-title, .log-card h3 { font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }

        .amount-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .amount-btn { background: var(--bg-secondary); border: 1px solid rgba(141,180,142,0.2); padding: 0.5rem 0.9rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: 0.2s; font-size: 0.85rem; font-family: 'Inter', sans-serif; color: var(--text-secondary); }
        .amount-btn:hover { background: var(--accent); color: #FFFFFF; }
        .custom-dispense-btn { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem; font-family: 'Inter', sans-serif; color: #FFFFFF; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
        .custom-dispense-btn:hover { background: linear-gradient(105deg, #3A5C3A, var(--accent-dark)); }

        .gate-status-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.8rem; padding: 0.5rem 0.8rem; background: var(--bg-secondary); border-radius: 12px; flex-wrap: wrap; }
        .gate-status-row .gate-icon { font-size: 1.8rem; }
        .gate-status-row .gate-icon.open { color: var(--green); }
        .gate-status-row .gate-icon.closed { color: var(--red); }
        .gate-status-badge { display: inline-block; padding: 0.2rem 0.9rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .gate-status-badge.open { background: var(--green-light); color: var(--green); }
        .gate-status-badge.closed { background: var(--red-light); color: var(--red); }
        .gate-btn { padding: 0.4rem 1.2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 0.8rem; font-family: 'Inter', sans-serif; }
        .gate-btn.open { background: var(--green); color: white; }
        .gate-btn.open:hover { background: #3A5C3A; }
        .gate-btn.close { background: var(--red); color: white; }
        .gate-btn.close:hover { background: #A44A3F; }

        .add-schedule-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 140px; }
        .form-group label { font-size: 0.7rem; font-weight: 600; display: block; margin-bottom: 0.3rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select { width: 100%; padding: 0.6rem; border: 1px solid rgba(141,180,142,0.3); border-radius: 10px; font-family: 'Inter', sans-serif; background: var(--bg-secondary); font-size: 0.85rem; color: var(--text-primary); }
        .btn-add-schedule { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.6rem 1.1rem; border-radius: 8px; font-weight: 600; cursor: pointer; color: #FFFFFF; font-size: 0.85rem; font-family: 'Inter', sans-serif; transition: all 0.2s; }
        .btn-add-schedule:hover { background: linear-gradient(105deg, #3A5C3A, var(--accent-dark)); }

        .schedules-table { width: 100%; border-collapse: collapse; }
        .schedules-table th { text-align: left; padding: 0.8rem 1rem; background: var(--bg-secondary); font-weight: 600; font-size: 0.8rem; color: var(--text-secondary); border-bottom: 2px solid rgba(141,180,142,0.2); }
        .schedules-table td { padding: 0.8rem 1rem; border-bottom: 1px solid rgba(141,180,142,0.08); vertical-align: middle; }
        .schedule-time { font-weight: 700; font-size: 1rem; }
        .schedule-amount { font-weight: 500; color: var(--accent-dark); }
        .schedule-label { background: var(--accent-light); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 500; display: inline-block; }
        .btn-edit-schedule, .btn-delete-schedule { background: none; border: none; cursor: pointer; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px; transition: 0.2s; }
        .btn-edit-schedule { background: var(--blue-light); color: var(--blue); }
        .btn-delete-schedule { background: var(--red-light); color: var(--red); }

        .log-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 0; border-bottom: 1px solid rgba(141,180,142,0.08); flex-wrap: wrap; gap: 0.5rem; }
        .log-badge { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; }
        .log-schedule { background: var(--blue-light); color: var(--blue); }
        .log-manual { background: var(--accent-light); color: var(--accent-dark); }
        .log-refill { background: var(--green-light); color: var(--green); }
        .log-time { font-size: 0.7rem; color: var(--text-muted); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-card); border-radius: 10px; padding: 1.5rem; max-width: 380px; width: 90%; text-align: center; }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center; }
        .modal-confirm { background: linear-gradient(105deg, var(--accent-dark), #3A5C3A); border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; color: #FFFFFF; font-family: 'Inter', sans-serif; }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }

        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; box-shadow: var(--shadow-md); }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .add-schedule-form { flex-direction: column; align-items: stretch; }
            .form-group { min-width: auto; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .log-entry { flex-direction: column; align-items: flex-start; }
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
            <a href="feed_dispenser.php" class="active"><i class="fas fa-drumstick-bite"></i> Feed Dispenser</a>
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
        <h1 class="page-title"><i class="fas fa-drumstick-bite" style="color:var(--accent);"></i> Feed Dispenser Automation</h1>
        <p class="page-subtitle">Automated feeding system with programmable schedules and real-time tracking</p>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--green);"><?php echo number_format($todayConsumption, 1); ?> kg</div><div class="stat-label">Today's Consumption</div></div><div class="stat-icon"><i class="fas fa-chart-line"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--blue);"><?php echo number_format($currentLevel, 1); ?> kg</div><div class="stat-label">Current Feed Level</div></div><div class="stat-icon"><i class="fas fa-box"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--accent-dark);"><?php echo count($schedules); ?></div><div class="stat-label">Active Schedules</div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:var(--accent-dark);"><?php echo $autoEnabled ? 'AUTO' : 'MANUAL'; ?></div><div class="stat-label">Operation Mode</div></div><div class="stat-icon"><i class="fas fa-microchip"></i></div></div>
        </div>

        <!-- Warning -->
        <?php if ($isLow): ?>
        <div class="warning-alert <?php echo $isCritical ? 'critical' : ''; ?>">
            <i class="fas <?php echo $isCritical ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?>"></i>
            <span><?php echo $isCritical ? 'CRITICAL: Feed level extremely low! Refill immediately.' : 'Warning: Feed level is low. Consider refilling soon.'; ?></span>
            <button onclick="refillFeed()" style="margin-left: auto; background:var(--accent); border:none; padding:0.2rem 0.8rem; border-radius:20px; cursor:pointer; color:#fff; font-size:0.7rem; font-weight:600;">Refill Now</button>
        </div>
        <?php endif; ?>

        <!-- Automation Status -->
        <div class="automation-status-card">
            <div>
                <h3 style="font-size:1rem;"><i class="fas fa-robot"></i> Automation Status</h3>
                <span class="auto-status-badge <?php echo $autoEnabled ? 'status-enabled' : 'status-disabled'; ?>">
                    <i class="fas <?php echo $autoEnabled ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                    <?php echo $autoEnabled ? 'Auto Feeding Active' : 'Manual Mode'; ?>
                </span>
                <p style="margin-top:0.4rem; font-size:0.75rem; opacity:0.8;"><?php echo $autoEnabled ? 'Feed will be dispensed automatically based on schedules.' : 'Manual dispensing only. Toggle ON for automation.'; ?></p>
            </div>
            <div class="auto-toggle-large">
                <label class="toggle-switch-large">
                    <input type="checkbox" id="autoToggle" <?php echo $autoEnabled ? 'checked' : ''; ?> onchange="toggleAutoMode()">
                    <span class="toggle-slider-large"></span>
                </label>
                <span style="font-size:0.85rem;">Auto Mode</span>
            </div>
        </div>

        <!-- Gate Control -->
        <div class="gate-control-card">
            <h3><i class="fas fa-door-open" style="color:var(--accent);"></i> Feed Gate Control</h3>
            <div class="gate-status-row">
                <div class="gate-icon <?php echo strtolower($gateStatus); ?>">
                    <i class="fas <?php echo $gateStatus === 'OPEN' ? 'fa-door-open' : 'fa-door-closed'; ?>"></i>
                </div>
                <span class="gate-status-badge <?php echo strtolower($gateStatus); ?>">
                    <i class="fas <?php echo $gateStatus === 'OPEN' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <?php echo $gateStatus; ?>
                </span>
                <div class="gate-buttons">
                    <button class="gate-btn open" onclick="manualGate('open')"><i class="fas fa-unlock"></i> Open Gate</button>
                    <button class="gate-btn close" onclick="manualGate('close')"><i class="fas fa-lock"></i> Close Gate</button>
                </div>
            </div>
            <p style="font-size:0.8rem; color:var(--text-muted);"><i class="fas fa-info-circle"></i> The gate opens automatically when dispensing feed. Use manual controls for testing or emergency.</p>
        </div>

        <!-- Manual Dispense -->
        <div class="manual-control-card">
            <h3><i class="fas fa-hand-paper"></i> Manual Feed Dispense</h3>
            <div class="amount-buttons">
                <button class="amount-btn" onclick="showDispenseModal(0.3)">0.3 kg</button>
                <button class="amount-btn" onclick="showDispenseModal(0.5)">0.5 kg</button>
                <button class="amount-btn" onclick="showDispenseModal(0.7)">0.7 kg</button>
                <button class="amount-btn" onclick="showDispenseModal(1.0)">1.0 kg</button>
                <button class="amount-btn" onclick="showDispenseModal(2.0)">2.0 kg</button>
            </div>
            <button class="custom-dispense-btn" onclick="showCustomDispenseModal()"><i class="fas fa-plus-circle"></i> Custom Amount</button>
        </div>

        <!-- Add Schedule -->
        <div class="add-schedule-card">
            <div class="add-schedule-title"><i class="fas fa-plus-circle" style="color:var(--accent);"></i> Add New Feeding Schedule</div>
            <div class="add-schedule-form">
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Time (24h)</label>
                    <input type="time" id="newScheduleTime" value="08:00">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-weight-hanging"></i> Amount (kg)</label>
                    <input type="number" id="newScheduleAmount" step="0.1" value="1.0" min="0.1">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Label</label>
                    <select id="newScheduleLabel">
                        <option value="Morning Feed">Morning Feed</option>
                        <option value="Afternoon Feed">Afternoon Feed</option>
                        <option value="Night Feed">Night Feed</option>
                        <option value="Custom">Custom</option>
                    </select>
                </div>
                <div>
                    <button class="btn-add-schedule" onclick="addSchedule()"><i class="fas fa-save"></i> Save Schedule</button>
                </div>
            </div>
        </div>

        <!-- Schedules Table -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin:1.5rem 0 1rem;">
            <h3><i class="fas fa-calendar-alt"></i> Feeding Schedules</h3>
            <span style="font-size:0.75rem; color:var(--text-muted);"><?php echo count($schedules); ?> schedule(s) configured</span>
        </div>
        <div style="background:var(--bg-card); border-radius:var(--border-radius); overflow:hidden; border:1px solid rgba(141,180,142,0.10); margin-bottom:1.5rem; box-shadow:var(--shadow-sm);">
            <table class="schedules-table">
                <thead><tr><th>Time</th><th>Amount</th><th>Label</th><th>Actions</th></tr></thead>
                <tbody id="schedulesTableBody">
                    <?php foreach ($schedules as $sch): ?>
                    <tr data-id="<?php echo $sch['id']; ?>">
                        <td class="schedule-time"><?php echo date('h:i A', strtotime($sch['time'])); ?></td>
                        <td class="schedule-amount"><?php echo $sch['amount']; ?> kg</td>
                        <td><span class="schedule-label"><?php echo htmlspecialchars($sch['label']); ?></span></td>
                        <td>
                            <button class="btn-edit-schedule" onclick="editSchedule(<?php echo $sch['id']; ?>, '<?php echo $sch['time']; ?>', <?php echo $sch['amount']; ?>, '<?php echo $sch['label']; ?>')"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-delete-schedule" onclick="deleteSchedule(<?php echo $sch['id']; ?>)"><i class="fas fa-trash-alt"></i> Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Activity Log -->
        <div class="log-card">
            <h3 style="margin-bottom:1rem; font-size:1rem;"><i class="fas fa-history"></i> Recent Activity</h3>
            <div class="log-list" id="logList">
                <?php foreach ($transactions as $log): ?>
                <div class="log-entry" data-trigger="<?php echo strtolower($log['source'] ?? ''); ?>">
                    <div class="log-time"><?php echo date('M d, h:i A', strtotime($log['timestamp'])); ?></div>
                    <div>
                        <span class="log-badge <?php 
                            if ($log['type'] === 'refill') echo 'log-refill';
                            elseif ($log['source'] === 'auto_dispenser') echo 'log-schedule';
                            else echo 'log-manual';
                        ?>">
                            <?php if ($log['type'] === 'refill'): ?>
                                <i class="fas fa-plus-circle"></i> Refilled <?php echo $log['amount']; ?> kg
                            <?php else: ?>
                                <i class="fas fa-drumstick-bite"></i> Dispensed <?php echo $log['amount']; ?> kg
                            <?php endif; ?>
                        </span>
                        <span class="log-trigger" style="margin-left:0.5rem; font-size:0.7rem;"><?php echo ucfirst($log['source'] ?? 'manual'); ?></span>
                    </div>
                    <div class="log-time">Remaining: <?php echo number_format($log['remaining'] ?? 0, 1); ?> kg</div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                    <div style="text-align:center; padding:1.5rem; color:var(--text-muted);">
                        <i class="fas fa-inbox" style="font-size:1.5rem; display:block; margin-bottom:0.3rem;"></i>
                        No activity recorded yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="font-size:2rem; color:var(--accent); margin-bottom:0.5rem;"></i>
        <h3 id="modalTitle">Confirm Dispense</h3>
        <p id="modalMessage">Are you sure you want to dispense feed?</p>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-confirm" onclick="confirmDispense()">Confirm</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    let currentSchedules = <?php echo json_encode($schedules); ?>;
    let pendingDispenseAmount = null;

    function showToast(message, isError) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(() => toast.style.display = 'none', 3000);
    }

    function showDispenseModal(amount) {
        if (amount > <?php echo $currentLevel; ?>) {
            showToast('Insufficient feed level! Please refill first.', true);
            return;
        }
        pendingDispenseAmount = amount;
        document.getElementById('modalTitle').innerText = 'Confirm Feed Dispense';
        document.getElementById('modalMessage').innerHTML = `Dispense <strong>${amount} kg</strong> of feed?<br><small>Gate will open for about ${Math.max(1, Math.round(amount * 4))} seconds.</small>`;
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function showCustomDispenseModal() {
        let amount = prompt('Enter amount to dispense (kg):', '0.5');
        if (amount && !isNaN(amount) && amount > 0) {
            showDispenseModal(parseFloat(amount));
        } else if (amount) {
            showToast('Invalid amount', true);
        }
    }

    function closeModal() {
        document.getElementById('confirmModal').style.display = 'none';
        pendingDispenseAmount = null;
    }

    async function confirmDispense() {
        if (pendingDispenseAmount === null) return;
        const amount = pendingDispenseAmount;
        closeModal();

        try {
            const response = await fetch('feed_dispenser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=dispense_feed&amount=${amount}`
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, true);
            }
        } catch (error) {
            showToast('Error dispensing feed', true);
        }
    }

    async function manualGate(action) {
        try {
            const response = await fetch('feed_dispenser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=gate_${action}_manual`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
            else showToast(data.message, true);
        } catch (error) {
            showToast('Error controlling gate', true);
        }
    }

    async function toggleAutoMode() {
        const enabled = document.getElementById('autoToggle').checked;
        try {
            const response = await fetch('feed_dispenser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=toggle_auto&enabled=${enabled}`
            });
            const data = await response.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error toggling auto mode', true); }
    }

    async function refillFeed() {
        let amount = prompt('Enter refill amount (kg):', '10.0');
        if (amount && !isNaN(amount) && amount > 0) {
            try {
                const response = await fetch('feed_dispenser.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=refill_feed&refill_amount=${amount}`
                });
                const data = await response.json();
                if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 800); }
                else showToast(data.message, true);
            } catch (error) { showToast('Error refilling feed', true); }
        }
    }

    async function addSchedule() {
        const time = document.getElementById('newScheduleTime').value;
        const amount = parseFloat(document.getElementById('newScheduleAmount').value);
        let label = document.getElementById('newScheduleLabel').value;
        if (label === 'Custom') label = prompt('Enter custom label:', 'Feeding Time') || 'Feeding Time';
        if (!time || !amount) { showToast('Please fill all fields', true); return; }
        const newId = currentSchedules.length > 0 ? Math.max(...currentSchedules.map(s => s.id)) + 1 : 1;
        currentSchedules.push({ id: newId, time, amount, label });
        await saveSchedules();
    }

    function editSchedule(id, oldTime, oldAmount, oldLabel) {
        const newTime = prompt('Enter new time (HH:MM 24h format):', oldTime);
        const newAmount = prompt('Enter new amount (kg):', oldAmount);
        const newLabel = prompt('Enter label:', oldLabel);
        if (newTime && newAmount && newLabel) {
            const index = currentSchedules.findIndex(s => s.id === id);
            if (index !== -1) {
                currentSchedules[index].time = newTime;
                currentSchedules[index].amount = parseFloat(newAmount);
                currentSchedules[index].label = newLabel;
                saveSchedules();
            }
        }
    }

    function deleteSchedule(id) {
        if (confirm('Delete this feeding schedule?')) {
            currentSchedules = currentSchedules.filter(s => s.id !== id);
            saveSchedules();
        }
    }

    async function saveSchedules() {
        try {
            const response = await fetch('feed_dispenser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `action=update_schedule&schedules=${encodeURIComponent(JSON.stringify(currentSchedules))}`
            });
            const data = await response.json();
            if (data.success) { showToast('Schedules updated!'); setTimeout(() => location.reload(), 600); }
            else showToast(data.message, true);
        } catch (error) { showToast('Error saving schedules', true); }
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
    function refreshWeather() { window.location.href = 'feed_dispenser.php?refresh_weather=1'; }
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