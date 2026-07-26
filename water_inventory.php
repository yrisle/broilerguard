<?php
// water_inventory.php - Water Inventory Management Module (No Database - Error Free)
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

// Initialize water inventory if not exists
if (!isset($_SESSION['water_inventory'])) {
    $_SESSION['water_inventory'] = [
        'current_level' => 1500.0,
        'capacity' => 2000.0,
        'unit' => 'liters',
        'last_refill' => date('Y-m-d H:i:s'),
        'alert_threshold' => 300.0,
        'critical_threshold' => 150.0,
        'supplier' => 'Local Water District',
        'water_type' => 'Clean Water',
        'flow_rate' => 2.0
    ];
}

// Initialize water transactions if not exists
if (!isset($_SESSION['water_transactions'])) {
    $_SESSION['water_transactions'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    if ($action === 'update_settings') {
        $_SESSION['water_inventory']['capacity'] = floatval($_POST['capacity'] ?? 2000);
        $_SESSION['water_inventory']['alert_threshold'] = floatval($_POST['alert_threshold'] ?? 300);
        $_SESSION['water_inventory']['critical_threshold'] = floatval($_POST['critical_threshold'] ?? 150);
        $_SESSION['water_inventory']['supplier'] = $_POST['supplier'] ?? 'Local Water District';
        $_SESSION['water_inventory']['water_type'] = $_POST['water_type'] ?? 'Clean Water';
        $_SESSION['water_inventory']['flow_rate'] = floatval($_POST['flow_rate'] ?? 2.0);
        
        $response = ['success' => true, 'message' => 'Settings saved successfully'];
        
    } elseif ($action === 'add_refill') {
        $amount = floatval($_POST['amount'] ?? 0);
        $cost = floatval($_POST['cost'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        if ($amount <= 0) {
            $response = ['success' => false, 'message' => 'Please enter a valid amount'];
            echo json_encode($response);
            exit;
        }
        
        $capacity = $_SESSION['water_inventory']['capacity'];
        $newLevel = min($capacity, $_SESSION['water_inventory']['current_level'] + $amount);
        $_SESSION['water_inventory']['current_level'] = $newLevel;
        $_SESSION['water_inventory']['last_refill'] = date('Y-m-d H:i:s');
        
        $transaction = [
            'id' => uniqid(),
            'type' => 'refill',
            'amount' => $amount,
            'cost' => $cost,
            'notes' => $notes,
            'timestamp' => date('Y-m-d H:i:s'),
            'new_level' => $newLevel
        ];
        array_unshift($_SESSION['water_transactions'], $transaction);
        
        $response = ['success' => true, 'message' => "Added {$amount} liters of water", 'new_level' => $newLevel];
        
    } elseif ($action === 'manual_deduct') {
        $amount = floatval($_POST['amount'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        if ($amount <= 0) {
            $response = ['success' => false, 'message' => 'Please enter a valid amount'];
            echo json_encode($response);
            exit;
        }
        
        if ($_SESSION['water_inventory']['current_level'] - $amount < 0) {
            $response = ['success' => false, 'message' => 'Insufficient water level'];
            echo json_encode($response);
            exit;
        }
        
        $_SESSION['water_inventory']['current_level'] -= $amount;
        
        $transaction = [
            'id' => uniqid(),
            'type' => 'consumption',
            'amount' => $amount,
            'source' => 'manual',
            'notes' => $notes,
            'timestamp' => date('Y-m-d H:i:s'),
            'remaining' => $_SESSION['water_inventory']['current_level']
        ];
        array_unshift($_SESSION['water_transactions'], $transaction);
        
        $response = ['success' => true, 'message' => "Deducted {$amount} liters of water", 'new_level' => $_SESSION['water_inventory']['current_level']];
        
    } elseif ($action === 'delete_transaction') {
        $transactionId = $_POST['id'] ?? '';
        foreach ($_SESSION['water_transactions'] as $key => $trans) {
            if ($trans['id'] == $transactionId) {
                if ($trans['type'] === 'refill') {
                    $_SESSION['water_inventory']['current_level'] -= $trans['amount'];
                } elseif ($trans['type'] === 'consumption') {
                    $_SESSION['water_inventory']['current_level'] += $trans['amount'];
                }
                unset($_SESSION['water_transactions'][$key]);
                $_SESSION['water_transactions'] = array_values($_SESSION['water_transactions']);
                $response = ['success' => true, 'message' => 'Transaction deleted'];
                break;
            }
        }
    } elseif ($action === 'clear_all') {
        $_SESSION['water_transactions'] = [];
        $response = ['success' => true, 'message' => 'All transactions cleared'];
    } elseif ($action === 'get_status') {
        $response = [
            'success' => true,
            'current_level' => $_SESSION['water_inventory']['current_level'],
            'capacity' => $_SESSION['water_inventory']['capacity'],
            'percentage' => ($_SESSION['water_inventory']['current_level'] / $_SESSION['water_inventory']['capacity']) * 100,
            'alert_threshold' => $_SESSION['water_inventory']['alert_threshold'],
            'critical_threshold' => $_SESSION['water_inventory']['critical_threshold']
        ];
    }
    
    echo json_encode($response);
    exit;
}

// Calculate statistics - with safe defaults
$inventory = $_SESSION['water_inventory'];
$transactions = $_SESSION['water_transactions'];
$currentLevel = $inventory['current_level'];
$capacity = $inventory['capacity'];
$percentage = ($capacity > 0) ? ($currentLevel / $capacity) * 100 : 0;
$alertThreshold = $inventory['alert_threshold'];
$criticalThreshold = $inventory['critical_threshold'];
$isLow = $currentLevel <= $alertThreshold;
$isCritical = $currentLevel <= $criticalThreshold;
$supplier = $inventory['supplier'];
$waterType = $inventory['water_type'];
$flowRate = $inventory['flow_rate'];
$lastRefill = $inventory['last_refill'];

// Calculate consumption stats
$todayConsumption = 0;
$weeklyConsumption = 0;
$autoDispensed = 0;
$manualDispensed = 0;

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));

if (!empty($transactions)) {
    foreach ($transactions as $trans) {
        if (isset($trans['timestamp'])) {
            $transDate = date('Y-m-d', strtotime($trans['timestamp']));
            
            if ($trans['type'] === 'consumption') {
                if ($transDate === $today) $todayConsumption += $trans['amount'];
                if ($transDate >= $weekAgo) $weeklyConsumption += $trans['amount'];
                
                if (isset($trans['source'])) {
                    if ($trans['source'] === 'auto_pump') $autoDispensed += $trans['amount'];
                    if ($trans['source'] === 'manual') $manualDispensed += $trans['amount'];
                }
            }
        }
    }
}

// Calculate average daily consumption
$avgDailyConsumption = 50;
$consumptionDays = 0;
if (!empty($transactions)) {
    foreach ($transactions as $trans) {
        if (isset($trans['type']) && $trans['type'] === 'consumption' && isset($trans['timestamp'])) {
            if (date('Y-m-d', strtotime($trans['timestamp'])) >= $weekAgo) {
                $consumptionDays++;
            }
        }
    }
}
if ($consumptionDays > 0 && $weeklyConsumption > 0) {
    $avgDailyConsumption = $weeklyConsumption / $consumptionDays;
}
$daysRemaining = $avgDailyConsumption > 0 ? floor($currentLevel / $avgDailyConsumption) : 0;

// Calculate estimated runtime based on flow rate
$estimatedRuntimeMin = $flowRate > 0 ? floor($currentLevel / $flowRate) : 0;

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Water Inventory | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F7F3E8; color: #2C2418; display: flex; min-height: 100vh; }
        
        :root {
            --sidebar-width: 280px; --header-height: 70px; --bg-card: #FFFFFF;
            --bg-secondary: #FEF9E6; --accent: #D4A017; --accent-dark: #B8860B;
            --accent-light: #FEF0C0; --text-primary: #2C2418; --text-muted: #7F6B4A;
            --green: #2E7D32; --green-light: #E8F5E9;
            --red: #C62828; --red-light: #FFEBEE;
            --blue: #1565C0; --blue-light: #E3F2FD;
            --orange: #F57C00; --orange-light: #FFF3E0;
            --cyan: #00838F; --cyan-light: #E0F7FA;
            --border-radius: 16px; --shadow-sm: 0 2px 8px rgba(0,0,0,0.04);
        }

        .sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed; left: 0; top: 0;
            background: linear-gradient(180deg, #4A3520 0%, #3D2B1A 100%);
            color: #E6DFD3; z-index: 1000; display: flex; flex-direction: column; transition: transform 0.3s ease;
        }
        .sidebar-logo { padding: 1.5rem; border-bottom: 1px solid rgba(230,223,211,0.1); text-align: center; }
        .sidebar-logo h2 { font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #D4A017, #F5C542); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-logo .logo-icon { font-size: 2rem; color: #D4A017; margin-bottom: 0.5rem; }
        .sidebar-user { padding: 1rem 1.5rem; border-bottom: 1px solid rgba(230,223,211,0.1); display: flex; align-items: center; gap: 0.8rem; }
        .sidebar-user .avatar { width: 42px; height: 42px; border-radius: 12px; background: #D4A017; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #2C2418; }
        .sidebar-user .user-info .name { font-weight: 600; font-size: 0.9rem; }
        .sidebar-user .user-info .role { font-size: 0.75rem; color: #B89A6E; }
        .sidebar-nav { flex: 1; padding: 0.8rem 0; overflow-y: auto; }
        .sidebar-nav .nav-section { padding: 0.3rem 1.2rem; margin-top: 0.3rem; }
        .sidebar-nav .nav-section-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1.2px; color: #B89A6E; margin-bottom: 0.5rem; font-weight: 700; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.7rem; padding: 0.65rem 0.8rem; color: #E6DFD3; text-decoration: none; border-radius: 10px; margin-bottom: 0.15rem; font-size: 0.88rem; font-weight: 500; transition: all 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(212,160,23,0.15); color: #D4A017; font-weight: 600; }
        .sidebar-footer { padding: 1rem 1.2rem; border-top: 1px solid rgba(230,223,211,0.1); margin-top: auto; }
        .sidebar-footer a { display: flex; align-items: center; gap: 0.7rem; color: #B89A6E; text-decoration: none; padding: 0.55rem 0.5rem; font-size: 0.88rem; }
        .sidebar-footer a:hover { color: #D4A017; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; transition: margin-left 0.3s ease; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid rgba(212,160,23,0.15); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 999; }
        .menu-toggle { display: none; font-size: 1.5rem; cursor: pointer; background: none; border: none; color: var(--text-primary); }
        .date-time span { font-size: 0.8rem; color: var(--text-muted); }
        .date-time .time { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
        .back-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid rgba(212,160,23,0.2); border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 500; color: var(--text-primary); }

        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; border-left: 3px solid var(--accent); padding-left: 0.8rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1rem 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between; }
        .stat-info .stat-value { font-size: 1.4rem; font-weight: 800; }
        .stat-info .stat-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        .stat-icon { font-size: 1.8rem; opacity: 0.6; color: var(--accent); }

        .inventory-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(212,160,23,0.1);
        }
        .level-container {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .level-gauge { flex: 2; min-width: 200px; }
        .level-info { flex: 1; min-width: 180px; text-align: center; }
        .progress-bar-bg {
            width: 100%;
            height: 35px;
            background: #E0D5C0;
            border-radius: 20px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        .progress-fill {
            height: 100%;
            border-radius: 20px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
        }
        .level-value { font-size: 2rem; font-weight: 800; }
        .level-unit { font-size: 0.8rem; color: var(--text-muted); }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .btn-primary { background: var(--accent); border: none; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: white; }
        .btn-primary:hover { background: var(--accent-dark); }
        .btn-secondary { background: var(--bg-secondary); border: 1px solid var(--accent); padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .btn-secondary:hover { background: var(--accent-light); }
        .btn-danger { background: var(--red-light); border: 1px solid var(--red); padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: var(--red); }
        .btn-info { background: var(--cyan-light); border: 1px solid var(--cyan); padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; cursor: pointer; color: var(--cyan); }

        .warning-alert {
            background: var(--orange-light);
            border-left: 4px solid var(--orange);
            padding: 0.8rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .warning-alert.critical { background: var(--red-light); border-left-color: var(--red); }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .setting-field {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .setting-field label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .setting-field input, .setting-field select {
            padding: 0.6rem;
            border: 1px solid #E0D5C0;
            border-radius: 10px;
            background: var(--bg-secondary);
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }
        .transactions-table th {
            text-align: left;
            padding: 0.8rem;
            background: #FCF8F0;
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .transactions-table td {
            padding: 0.8rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 0.8rem;
        }
        .badge-refill { background: var(--green-light); color: var(--green); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .badge-consumption { background: var(--blue-light); color: var(--blue); padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 1.5rem;
            max-width: 400px;
            width: 90%;
        }
        .modal-content h3 { margin-bottom: 1rem; font-size: 1.1rem; }
        .modal-buttons { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: flex-end; }
        .modal-confirm { background: var(--accent); border: none; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 600; cursor: pointer; }
        .modal-cancel { background: #E0E0E0; border: none; padding: 0.5rem 1.5rem; border-radius: 30px; font-weight: 600; cursor: pointer; }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2E7D32;
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
        .toast.error { background: #C62828; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .integration-note {
            background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
            border-radius: var(--border-radius);
            padding: 0.8rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.8rem;
            color: #2E7D32;
        }

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
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><div class="logo-icon"><i class="fas fa-feather-alt"></i></div><h2>BroilerGuard</h2></div>
    <nav class="sidebar-nav">
        <div class="nav-section"><div class="nav-section-title">Main</div><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></div>
        <div class="nav-section"><div class="nav-section-title">Inventory</div>
            <a href="feed_inventory.php"><i class="fas fa-utensils"></i> Feed Inventory</a>
            <a href="water_inventory.php" class="active"><i class="fas fa-water"></i> Water Inventory</a>
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
        <h1 class="page-title"><i class="fas fa-water" style="color:var(--accent);"></i> Water Inventory Management</h1>
        <p class="page-subtitle">Track water levels, manage refills, and monitor consumption</p>

        <div class="integration-note">
            <i class="fas fa-sync-alt"></i>
            <span><strong>Integrated with Water Pump</strong> — Every time water is released, inventory is automatically updated.</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:#2E7D32;"><?php echo number_format($currentLevel, 0); ?> L</div><div class="stat-label">Current Level</div></div><div class="stat-icon"><i class="fas fa-tint"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:#D4A017;"><?php echo number_format($todayConsumption, 0); ?> L</div><div class="stat-label">Today's Usage</div></div><div class="stat-icon"><i class="fas fa-chart-line"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:#1565C0;"><?php echo number_format($weeklyConsumption, 0); ?> L</div><div class="stat-label">This Week</div></div><div class="stat-icon"><i class="fas fa-calendar-week"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:#00838F;"><?php echo $estimatedRuntimeMin; ?> min</div><div class="stat-label">Est. Runtime</div></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-value" style="color:#F57C00;"><?php echo $daysRemaining; ?> days</div><div class="stat-label">Est. Days Left</div></div><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div></div>
        </div>

        <?php if ($isLow || $isCritical): ?>
        <div class="warning-alert <?php echo $isCritical ? 'critical' : ''; ?>">
            <i class="fas <?php echo $isCritical ? 'fa-skull-crossbones' : 'fa-exclamation-triangle'; ?>"></i>
            <span><?php echo $isCritical ? 'CRITICAL: Water level extremely low! Refill immediately.' : 'Warning: Water level is low. Consider refilling soon.'; ?></span>
            <button onclick="openRefillModal()" style="margin-left: auto; background: var(--accent); border: none; padding: 0.3rem 1rem; border-radius: 20px; cursor: pointer; color: white;">Refill Now</button>
        </div>
        <?php endif; ?>

        <div class="inventory-card">
            <h3 style="margin-bottom: 1rem;"><i class="fas fa-chart-simple"></i> Current Water Level</h3>
            <div class="level-container">
                <div class="level-gauge">
                    <div class="progress-bar-bg">
                        <div class="progress-fill" style="width: <?php echo max(0, min(100, $percentage)); ?>%; background: <?php echo $isCritical ? '#C62828' : ($isLow ? '#F57C00' : '#2E7D32'); ?>;">
                            <?php echo round($percentage, 1); ?>%
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-muted);">
                        <span>0 L</span>
                        <span>Alert: <?php echo number_format($alertThreshold, 0); ?> L</span>
                        <span>Capacity: <?php echo number_format($capacity, 0); ?> L</span>
                    </div>
                </div>
                <div class="level-info">
                    <div class="level-value"><?php echo number_format($currentLevel, 0); ?></div>
                    <div class="level-unit">liters remaining</div>
                    <div style="margin-top: 0.5rem; font-size: 0.7rem;">Last refill: <?php echo date('M d, Y', strtotime($lastRefill)); ?></div>
                </div>
            </div>
            <div class="action-buttons">
                <button class="btn-primary" onclick="openRefillModal()"><i class="fas fa-plus-circle"></i> Add Refill</button>
                <button class="btn-secondary" onclick="openDeductModal()"><i class="fas fa-minus-circle"></i> Manual Deduction</button>
                <button class="btn-info" onclick="checkInventoryStatus()"><i class="fas fa-chart-line"></i> Check Status</button>
            </div>
        </div>

        <div class="inventory-card">
            <h3 style="margin-bottom: 1rem;"><i class="fas fa-cog"></i> Inventory Settings</h3>
            <div class="settings-grid">
                <div class="setting-field">
                    <label>Storage Capacity (liters)</label>
                    <input type="number" id="capacity" step="100" value="<?php echo $capacity; ?>">
                </div>
                <div class="setting-field">
                    <label>Alert Threshold (liters)</label>
                    <input type="number" id="alertThreshold" step="50" value="<?php echo $alertThreshold; ?>">
                </div>
                <div class="setting-field">
                    <label>Critical Threshold (liters)</label>
                    <input type="number" id="criticalThreshold" step="50" value="<?php echo $criticalThreshold; ?>">
                </div>
                <div class="setting-field">
                    <label>Water Type</label>
                    <select id="waterType">
                        <option value="Clean Water" <?php echo $waterType == 'Clean Water' ? 'selected' : ''; ?>>Clean Water</option>
                        <option value="Filtered Water" <?php echo $waterType == 'Filtered Water' ? 'selected' : ''; ?>>Filtered Water</option>
                        <option value="Well Water" <?php echo $waterType == 'Well Water' ? 'selected' : ''; ?>>Well Water</option>
                        <option value="Municipal" <?php echo $waterType == 'Municipal' ? 'selected' : ''; ?>>Municipal</option>
                    </select>
                </div>
                <div class="setting-field">
                    <label>Flow Rate (L/sec)</label>
                    <input type="number" id="flowRate" step="0.5" value="<?php echo $flowRate; ?>">
                </div>
                <div class="setting-field">
                    <label>Supplier</label>
                    <input type="text" id="supplier" value="<?php echo htmlspecialchars($supplier); ?>">
                </div>
            </div>
            <button class="btn-secondary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button>
        </div>

        <div class="inventory-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3><i class="fas fa-history"></i> Transaction History</h3>
                <div>
                    <button class="btn-danger" style="padding: 0.3rem 0.8rem; font-size: 0.7rem; margin-right: 0.5rem;" onclick="clearAllTransactions()">Clear All</button>
                    <span style="font-size: 0.7rem; color: var(--text-muted);"><?php echo count($transactions); ?> records</span>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <?php if (empty($transactions)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        No transactions yet. Add a refill or release water to get started.
                    </div>
                <?php else: ?>
                <table class="transactions-table">
                    <thead>
                        <tr><th>Date & Time</th><th>Type</th><th>Amount</th><th>Source</th><th>Notes</th><th>Remaining</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td style="font-size: 0.7rem;"><?php echo isset($trans['timestamp']) ? date('M d, h:i A', strtotime($trans['timestamp'])) : '—'; ?></td>
                            <td>
                                <span class="badge-<?php echo $trans['type']; ?>">
                                    <?php echo $trans['type'] === 'refill' ? '➕ Refill' : '💧 Consumption'; ?>
                                </span>
                            </td>
                            <td style="font-weight: 500;"><?php echo number_format($trans['amount'], 0); ?> L</td>
                            <td>
                                <?php if (isset($trans['source'])): ?>
                                    <span class="badge-<?php echo $trans['source'] === 'auto_pump' ? 'auto' : 'manual'; ?>">
                                        <?php echo $trans['source'] === 'auto_pump' ? '🤖 Auto Pump' : '✋ Manual'; ?>
                                    </span>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($trans['notes'] ?? '—'); ?></td>
                            <td style="font-size: 0.7rem;"><?php echo isset($trans['remaining']) ? number_format($trans['remaining'], 0) . ' L' : '—'; ?></td>
                            <td><button class="btn-danger" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;" onclick="deleteTransaction('<?php echo $trans['id']; ?>')"><i class="fas fa-trash"></i></button></td>
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
        <h3><i class="fas fa-plus-circle"></i> Add Water Refill</h3>
        <div class="setting-field" style="margin-bottom: 1rem;">
            <label>Amount (liters)</label>
            <input type="number" id="refillAmount" step="100" placeholder="Enter amount in liters">
        </div>
        <div class="setting-field" style="margin-bottom: 1rem;">
            <label>Cost (PHP)</label>
            <input type="number" id="refillCost" step="50" placeholder="Total cost (optional)">
        </div>
        <div class="setting-field" style="margin-bottom: 1rem;">
            <label>Notes</label>
            <input type="text" id="refillNotes" placeholder="Additional notes">
        </div>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeRefillModal()">Cancel</button>
            <button class="modal-confirm" onclick="confirmRefill()">Add Refill</button>
        </div>
    </div>
</div>

<!-- Manual Deduct Modal -->
<div id="deductModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-minus-circle"></i> Manual Water Deduction</h3>
        <div class="setting-field" style="margin-bottom: 1rem;">
            <label>Amount to Deduct (liters)</label>
            <input type="number" id="deductAmount" step="50" placeholder="Enter amount">
        </div>
        <div class="setting-field" style="margin-bottom: 1rem;">
            <label>Reason / Notes</label>
            <input type="text" id="deductNotes" placeholder="Reason for deduction">
        </div>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeDeductModal()">Cancel</button>
            <button class="modal-confirm" onclick="confirmDeduct()">Deduct Water</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

<script>
    function showToast(message, isError) {
        var toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.style.display = 'flex';
        setTimeout(function() { toast.style.display = 'none'; }, 3000);
    }
    
    function openRefillModal() { document.getElementById('refillModal').style.display = 'flex'; }
    function closeRefillModal() { document.getElementById('refillModal').style.display = 'none'; }
    function openDeductModal() { document.getElementById('deductModal').style.display = 'flex'; }
    function closeDeductModal() { document.getElementById('deductModal').style.display = 'none'; }
    
    async function confirmRefill() {
        var amount = parseFloat(document.getElementById('refillAmount').value);
        var cost = parseFloat(document.getElementById('refillCost').value) || 0;
        var notes = document.getElementById('refillNotes').value;
        
        if (isNaN(amount) || amount <= 0) {
            showToast('Please enter a valid amount', true);
            return;
        }
        
        try {
            var response = await fetch('water_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=add_refill&amount=' + amount + '&cost=' + cost + '&notes=' + encodeURIComponent(notes)
            });
            var data = await response.json();
            if (data.success) {
                showToast(data.message, false);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.message, true);
            }
        } catch(error) {
            showToast('Error adding refill', true);
        }
        closeRefillModal();
    }
    
    async function confirmDeduct() {
        var amount = parseFloat(document.getElementById('deductAmount').value);
        var notes = document.getElementById('deductNotes').value;
        
        if (isNaN(amount) || amount <= 0) {
            showToast('Please enter a valid amount', true);
            return;
        }
        
        try {
            var response = await fetch('water_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=manual_deduct&amount=' + amount + '&notes=' + encodeURIComponent(notes)
            });
            var data = await response.json();
            if (data.success) {
                showToast(data.message, false);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.message, true);
            }
        } catch(error) {
            showToast('Error deducting water', true);
        }
        closeDeductModal();
    }
    
    async function saveSettings() {
        var capacity = document.getElementById('capacity').value;
        var alertThreshold = document.getElementById('alertThreshold').value;
        var criticalThreshold = document.getElementById('criticalThreshold').value;
        var waterType = document.getElementById('waterType').value;
        var flowRate = document.getElementById('flowRate').value;
        var supplier = document.getElementById('supplier').value;
        
        try {
            var response = await fetch('water_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=update_settings&capacity=' + capacity + '&alert_threshold=' + alertThreshold + '&critical_threshold=' + criticalThreshold + '&water_type=' + encodeURIComponent(waterType) + '&flow_rate=' + flowRate + '&supplier=' + encodeURIComponent(supplier)
            });
            var data = await response.json();
            if (data.success) {
                showToast(data.message, false);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.message, true);
            }
        } catch(error) {
            showToast('Error saving settings', true);
        }
    }
    
    async function checkInventoryStatus() {
        try {
            var response = await fetch('water_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=get_status'
            });
            var data = await response.json();
            if (data.success) {
                showToast('Current level: ' + data.current_level + ' L (' + data.percentage.toFixed(1) + '%)', false);
            }
        } catch(error) {
            showToast('Error checking status', true);
        }
    }
    
    async function deleteTransaction(id) {
        if (!id) return;
        if (!confirm('Delete this transaction? This will adjust inventory levels accordingly.')) return;
        
        try {
            var response = await fetch('water_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete_transaction&id=' + id
            });
            var data = await response.json();
            if (data.success) {
                showToast(data.message, false);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.message, true);
            }
        } catch(error) {
            showToast('Error deleting transaction', true);
        }
    }
    
    async function clearAllTransactions() {
        if (!confirm('Clear all transaction history? This cannot be undone.')) return;
        
        try {
            var response = await fetch('water_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=clear_all'
            });
            var data = await response.json();
            if (data.success) {
                showToast(data.message, false);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.message, true);
            }
        } catch(error) {
            showToast('Error clearing transactions', true);
        }
    }
    
    function updateDateTime() {
        var now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('currentDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    
    document.getElementById('menuToggle').addEventListener('click', function() { document.getElementById('sidebar').classList.toggle('open'); });
    window.onclick = function(e) {
        if (e.target == document.getElementById('refillModal')) closeRefillModal();
        if (e.target == document.getElementById('deductModal')) closeDeductModal();
    };
    
    setInterval(updateDateTime, 1000);
    updateDateTime();
</script>
</body>
</html>