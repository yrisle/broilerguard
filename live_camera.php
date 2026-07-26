<?php
// live_camera.php - Live Camera Feed Module with Health Detection
// Includes: Respiratory Disease Detection, Heat Stress Monitoring, Sick vs Healthy Detection
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

// Shared sensor data cache
function getSharedSensorData() {
    $cacheFile = 'sensor_data_cache.json';
    $cacheTime = 30;
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $data = [
        'temperature' => round(rand(300, 350) / 10, 1),
        'humidity' => rand(55, 80),
        'feed_level' => rand(75, 98),
        'water_level' => rand(80, 99),
        'fan_status' => (rand(0, 10) > 2) ? 'ON' : 'OFF',
        'water_pump' => (rand(0, 10) > 5) ? 'ON' : 'OFF',
        'timestamp' => time()
    ];
    
    file_put_contents($cacheFile, json_encode($data));
    return $data;
}

$sharedData = getSharedSensorData();

// --- Respiratory Disease Detection ---
function detectRespiratoryDisease($chickId) {
    $symptoms = [
        'coughing' => rand(0, 10) > 7,
        'wheezing' => rand(0, 10) > 8,
        'nasal_discharge' => rand(0, 10) > 8,
        'labored_breathing' => rand(0, 10) > 8,
        'sneezing' => rand(0, 10) > 7
    ];
    
    $symptomCount = array_sum(array_map('intval', $symptoms));
    $severity = $symptomCount >= 4 ? 'severe' : ($symptomCount >= 2 ? 'moderate' : 'none');
    
    return [
        'has_respiratory_issues' => $symptomCount >= 2,
        'severity' => $severity,
        'symptoms' => $symptoms,
        'symptom_count' => $symptomCount,
        'confidence' => 85 + rand(0, 14),
        'recommendation' => $symptomCount >= 4 ? 'Immediate veterinary intervention required' : 
                           ($symptomCount >= 2 ? 'Monitor closely, consider veterinary check' : 'No respiratory issues detected')
    ];
}

// --- Heat Stress Monitoring ---
function monitorHeatStress($temperature, $humidity, $chickId) {
    $heatIndex = $temperature + (0.1 * $humidity) + (0.01 * $temperature * $humidity) - 15;
    $heatIndex = round($heatIndex, 1);
    
    $riskLevel = 'normal';
    $riskColor = '#27AE60';
    $alertMessage = 'All conditions normal';
    $earlyIndicators = [];
    
    if ($temperature > 32) {
        $riskLevel = 'critical';
        $riskColor = '#E74C3C';
        $alertMessage = '⚠️ CRITICAL: Temperature exceeds safe threshold!';
        $earlyIndicators[] = 'Temperature above 32°C - Critical heat stress risk';
    } elseif ($temperature > 30) {
        $riskLevel = 'high';
        $riskColor = '#F39C12';
        $alertMessage = '⚠️ HIGH: Elevated temperature detected. Monitor closely.';
        $earlyIndicators[] = 'Temperature above 30°C - High heat stress risk';
    } elseif ($temperature > 28) {
        $riskLevel = 'moderate';
        $riskColor = '#F1C40F';
        $alertMessage = '⚠️ MODERATE: Slightly elevated temperature.';
        $earlyIndicators[] = 'Temperature above 28°C - Moderate heat stress risk';
    }
    
    if ($humidity > 80) {
        $earlyIndicators[] = 'High humidity (>80%) - Reduced cooling efficiency';
    }
    
    if ($heatIndex > 45) {
        $riskLevel = 'critical';
        $riskColor = '#E74C3C';
        $alertMessage = '⚠️ CRITICAL: Heat Index above 45°C! Emergency cooling required!';
    } elseif ($heatIndex > 40) {
        $riskLevel = 'high';
        $riskColor = '#F39C12';
        $alertMessage = '⚠️ HIGH: Heat Index above 40°C. Active cooling needed.';
    }
    
    $diseaseIndicators = [
        'rapid_breathing' => rand(0, 10) > 8,
        'lethargy' => rand(0, 10) > 7,
        'reduced_feed_intake' => rand(0, 10) > 7,
        'panting' => rand(0, 10) > 8,
        'spread_wings' => rand(0, 10) > 8
    ];
    
    $indicatorCount = array_sum(array_map('intval', $diseaseIndicators));
    $diseaseRisk = $indicatorCount >= 3 ? 'high' : ($indicatorCount >= 2 ? 'moderate' : 'low');
    
    return [
        'heat_index' => $heatIndex,
        'risk_level' => $riskLevel,
        'risk_color' => $riskColor,
        'alert_message' => $alertMessage,
        'early_indicators' => $earlyIndicators,
        'disease_indicators' => $diseaseIndicators,
        'indicator_count' => $indicatorCount,
        'disease_risk' => $diseaseRisk,
        'temperature' => $temperature,
        'humidity' => $humidity
    ];
}

// --- Enhanced Sick vs Healthy Detection ---
function enhancedHealthAssessment($chickId) {
    $metrics = [
        'appetite' => rand(60, 100),
        'activity_level' => rand(50, 100),
        'feather_condition' => rand(60, 100),
        'weight_trend' => rand(-5, 10),
        'social_interaction' => rand(60, 100),
        'posture' => rand(60, 100),
        'eye_condition' => rand(60, 100),
        'comb_color' => rand(60, 100)
    ];
    
    $overallScore = round(array_sum($metrics) / count($metrics), 1);
    
    $status = 'healthy';
    $confidence = 85 + rand(0, 14);
    $statusColor = '#27AE60';
    
    if ($overallScore < 60) {
        $status = 'unhealthy';
        $confidence = 75 + rand(0, 19);
        $statusColor = '#E74C3C';
    } elseif ($overallScore < 75) {
        $status = 'weak';
        $confidence = 80 + rand(0, 14);
        $statusColor = '#F39C12';
    }
    
    return [
        'status' => $status,
        'status_color' => $statusColor,
        'overall_score' => $overallScore,
        'confidence' => $confidence,
        'recommendation' => $status === 'unhealthy' ? 'Immediate medical attention required' :
                           ($status === 'weak' ? 'Nutritional support and monitoring recommended' :
                           'Continue current care regimen')
    ];
}

// Get health data for each detected chick
$temp = $sharedData['temperature'];
$humidity = $sharedData['humidity'];

$chickStatuses = [
    'CHK-001' => ['status' => 'healthy', 'status_color' => '#27AE60'],
    'CHK-002' => ['status' => 'healthy', 'status_color' => '#27AE60'],
    'CHK-003' => ['status' => 'healthy', 'status_color' => '#27AE60'],
    'CHK-004' => ['status' => 'weak', 'status_color' => '#F39C12'],
    'CHK-005' => ['status' => 'unhealthy', 'status_color' => '#E74C3C']
];

$chickHealthData = [];
foreach ($chickStatuses as $id => $info) {
    $chickHealthData[$id] = [
        'respiratory' => detectRespiratoryDisease($id),
        'heat_stress' => monitorHeatStress($temp, $humidity, $id),
        'assessment' => enhancedHealthAssessment($id),
        'status' => $info['status'],
        'status_color' => $info['status_color']
    ];
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');

// Notification System
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
if (!isset($_SESSION['notifications']) || empty($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [
        ['id' => 1, 'title' => 'Camera Feed Active', 'message' => 'Live camera streaming at 1080p 30fps', 'time' => '2 min ago', 'read' => false, 'type' => 'info'],
    ];
    $unreadNotifications = count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; }));
}

// Camera settings
$cameraStatus = 'online';
$streamQuality = '1080p';
$fps = 30;
$detectionActive = true;
$lastDetection = '2 seconds ago';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Camera Feed | BroilerGuard</title>
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
            --purple: #8E44AD;
            --purple-light: #F4ECF7;
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
        
        /* ===== SIDEBAR - NO SCROLLBAR (SAME AS FEED_MONITORING) ===== */
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
        /* Hide scrollbar completely */
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
        .sidebar-nav a .badge-sidebar { 
            margin-left: auto; 
            background: var(--red); 
            color: white; 
            font-size: 0.6rem; 
            padding: 0.1rem 0.5rem; 
            border-radius: 20px; 
            font-weight: 600; 
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
        .top-header .date-time .date { font-size: 0.8rem; color: var(--text-muted); }
        .top-header .date-time .time { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        
        /* ===== NOTIFICATION BAR ===== */
        .notification-bar { 
            display: flex; 
            align-items: center; 
            background: var(--bg-secondary); 
            border-radius: 12px; 
            padding: 0.3rem 1rem; 
            gap: 0.8rem; 
            border: 1px solid rgba(255, 214, 46, 0.2); 
            max-width: 420px; 
            overflow: hidden; 
        }
        .notification-bar .notif-icon { color: var(--yellow); font-size: 0.9rem; }
        .notification-bar .notif-text { font-size: 0.78rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
        .notification-bar .notif-text strong { color: var(--text-primary); }
        .notification-bar .notif-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.8rem; padding: 0.2rem; }
        .notification-bar .notif-close:hover { color: var(--red); }
        
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
        .page-content { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; }
        .page-title .title-icon { font-size: 1.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .badge-live { display: inline-block; background: var(--red); color: white; font-size: 0.6rem; font-weight: 700; padding: 0.2rem 0.7rem; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; animation: pulse-live 1.5s infinite; }
        @keyframes pulse-live { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        
        /* ===== CAMERA GRID ===== */
        .camera-grid { display: grid; grid-template-columns: 2.2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .camera-feed { background: #0a0a0a; border-radius: var(--border-radius); overflow: hidden; position: relative; min-height: 500px; box-shadow: var(--shadow-md); border: 2px solid rgba(255, 214, 46, 0.12); }
        .camera-feed .feed-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 500px; color: #666; background: radial-gradient(ellipse at center, #1a1a1a 0%, #0a0a0a 100%); }
        .camera-feed .feed-placeholder i { font-size: 5rem; margin-bottom: 1rem; color: #444; animation: pulse-camera 2s infinite; }
        .camera-feed .feed-placeholder p { font-size: 1.1rem; color: #888; }
        .camera-feed .feed-placeholder .sub-text { font-size: 0.8rem; color: #555; margin-top: 0.4rem; }
        @keyframes pulse-camera { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }
        
        .camera-feed .feed-overlay { position: absolute; top: 1rem; left: 1rem; display: flex; gap: 0.4rem; flex-wrap: wrap; z-index: 10; }
        .camera-feed .feed-overlay .overlay-badge { background: rgba(0,0,0,0.8); color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.65rem; font-weight: 500; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.05); }
        .camera-feed .feed-overlay .overlay-badge.live { background: #E74C3C; animation: pulse-live 1.5s infinite; }
        .camera-feed .feed-overlay .overlay-badge .dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 4px; background: white; }
        .camera-feed .feed-overlay .overlay-badge.ai-active { background: rgba(46, 204, 113, 0.8); }
        
        .camera-feed .detection-boxes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5; pointer-events: none; }
        .camera-feed .detection-box { position: absolute; border: 2.5px solid #27AE60; border-radius: 6px; background: rgba(39, 174, 96, 0.08); animation: box-glow 2s ease-in-out infinite; }
        .camera-feed .detection-box.weak { border-color: #F39C12; background: rgba(243, 156, 18, 0.08); }
        .camera-feed .detection-box.unhealthy { border-color: #E74C3C; background: rgba(231, 76, 60, 0.08); }
        .camera-feed .detection-box .box-label { position: absolute; bottom: -22px; left: -2px; background: #27AE60; color: white; font-size: 0.55rem; padding: 0.1rem 0.4rem; border-radius: 4px; white-space: nowrap; font-weight: 600; }
        .camera-feed .detection-box.weak .box-label { background: #F39C12; }
        .camera-feed .detection-box.unhealthy .box-label { background: #E74C3C; }
        .camera-feed .detection-box .box-confidence { position: absolute; top: -18px; right: -2px; background: rgba(0,0,0,0.8); color: #27AE60; font-size: 0.5rem; padding: 0.05rem 0.35rem; border-radius: 4px; font-weight: 600; }
        .camera-feed .detection-box.weak .box-confidence { color: #F39C12; }
        .camera-feed .detection-box.unhealthy .box-confidence { color: #E74C3C; }
        .camera-feed .detection-box .box-respiratory { position: absolute; top: -18px; left: -2px; background: rgba(0,0,0,0.7); color: #27AE60; font-size: 0.45rem; padding: 0.05rem 0.35rem; border-radius: 4px; font-weight: 600; }
        .camera-feed .detection-box .box-respiratory.warning { color: #F39C12; }
        .camera-feed .detection-box .box-respiratory.danger { color: #E74C3C; }
        .camera-feed .detection-box .box-heat { position: absolute; top: -32px; left: -2px; background: rgba(0,0,0,0.7); color: #F39C12; font-size: 0.45rem; padding: 0.05rem 0.35rem; border-radius: 4px; font-weight: 600; }
        .camera-feed .detection-box .box-heat.critical { color: #E74C3C; }
        @keyframes box-glow { 0%, 100% { opacity: 1; } 50% { opacity: 0.85; } }
        
        .camera-feed .feed-controls { position: absolute; bottom: 1.2rem; left: 50%; transform: translateX(-50%); display: flex; gap: 0.5rem; z-index: 10; flex-wrap: wrap; justify-content: center; }
        .camera-feed .feed-controls button { background: rgba(0,0,0,0.75); color: white; border: none; padding: 0.4rem 1rem; border-radius: 25px; cursor: pointer; font-size: 0.75rem; font-family: 'Inter', sans-serif; transition: all 0.3s; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.05); }
        .camera-feed .feed-controls button:hover { background: rgba(255, 214, 46, 0.2); transform: scale(1.05); }
        .camera-feed .feed-controls button.active { background: #E74C3C; border-color: #E74C3C; }
        .camera-feed .feed-controls button i { margin-right: 0.25rem; }
        .camera-feed .feed-controls button.capture-btn { background: rgba(39, 174, 96, 0.7); }
        .camera-feed .feed-controls button.capture-btn:hover { background: #27AE60; }
        
        /* ===== CAMERA INFO PANEL ===== */
        .camera-info-panel { display: flex; flex-direction: column; gap: 1rem; }
        .info-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.2rem 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); transition: all 0.3s; }
        .info-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .info-card .info-header { font-weight: 700; font-size: 0.85rem; margin-bottom: 0.7rem; display: flex; align-items: center; gap: 0.5rem; }
        .info-card .info-row { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid rgba(255, 214, 46, 0.05); font-size: 0.78rem; }
        .info-card .info-row:last-child { border-bottom: none; }
        .info-card .info-row .info-label { color: var(--text-muted); }
        .info-card .info-row .info-value { font-weight: 600; }
        .info-value.online { color: #27AE60; }
        
        /* ===== DETECTION PREVIEW ===== */
        .detection-preview { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .detection-item { background: var(--bg-card); border-radius: 12px; padding: 1.2rem; text-align: center; border: 1px solid rgba(255, 214, 46, 0.08); transition: all 0.3s; cursor: pointer; }
        .detection-item:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--accent); }
        .detection-item .detection-icon { font-size: 1.8rem; margin-bottom: 0.3rem; }
        .detection-item .detection-value { font-size: 1.8rem; font-weight: 800; }
        .detection-item .detection-label { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; }
        .detection-item .detection-change { font-size: 0.6rem; margin-top: 0.3rem; display: inline-block; padding: 0.1rem 0.6rem; border-radius: 12px; }
        .detection-item .detection-change.up { background: var(--green-light); color: var(--green); }
        .detection-item .detection-change.down { background: var(--red-light); color: var(--red); }
        .detection-item.healthy { border-top: 3px solid #27AE60; }
        .detection-item.weak { border-top: 3px solid #F39C12; }
        .detection-item.unhealthy { border-top: 3px solid #E74C3C; }
        
        .section-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin-bottom: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .section-label .badge-count { font-size: 0.65rem; background: var(--bg-secondary); padding: 0.15rem 0.6rem; border-radius: 12px; font-weight: 600; }
        
        .card-badge { padding: 0.2rem 0.7rem; border-radius: 15px; font-size: 0.65rem; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }
        .badge-purple { background: var(--purple-light); color: var(--purple); }
        
        /* ===== SNAPSHOT GRID ===== */
        .snapshot-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .snapshot-card { background: var(--bg-card); border-radius: 12px; padding: 0.8rem; text-align: center; border: 1px solid rgba(255, 214, 46, 0.08); transition: all 0.3s; cursor: pointer; }
        .snapshot-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--accent); }
        .snapshot-card .snapshot-img { width: 100%; height: 80px; background: #1a1a1a; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.7rem; margin-bottom: 0.5rem; position: relative; overflow: hidden; }
        .snapshot-card .snapshot-img .capture-badge { position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.7); color: white; font-size: 0.5rem; padding: 0.05rem 0.4rem; border-radius: 4px; }
        .snapshot-card .snapshot-time { font-size: 0.7rem; color: var(--text-muted); }
        .snapshot-card .snapshot-delete { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.7rem; margin-top: 0.2rem; transition: color 0.2s; }
        .snapshot-card .snapshot-delete:hover { color: #E74C3C; }
        .snapshot-card.empty-snapshot .snapshot-img { background: var(--bg-secondary); border: 2px dashed rgba(255, 214, 46, 0.3); color: var(--text-muted); }
        .snapshot-card.empty-snapshot .snapshot-img i { font-size: 1.5rem; color: #ccc; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; box-shadow: var(--shadow-md); }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            .camera-grid { grid-template-columns: 1fr; }
            .camera-feed { min-height: 380px; }
            .camera-feed .feed-placeholder { min-height: 380px; }
            .detection-preview { grid-template-columns: repeat(3, 1fr); }
            .snapshot-grid { grid-template-columns: repeat(2, 1fr); }
            .notification-bar { max-width: 200px; }
        }
        @media (max-width: 768px) {
            .detection-preview { grid-template-columns: 1fr 1fr; }
            .snapshot-grid { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
        }
        @media (max-width: 640px) {
            .detection-preview { grid-template-columns: 1fr; }
            .snapshot-grid { grid-template-columns: 1fr; }
            .camera-feed .feed-controls button { font-size: 0.65rem; padding: 0.35rem 0.7rem; }
            .notification-bar { max-width: 140px; padding: 0.2rem 0.6rem; }
            .notification-bar .notif-text { font-size: 0.65rem; }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
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
                <a href="live_camera.php" class="active"><i class="fas fa-camera"></i> Live Camera Feed</a>
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
                <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <span class="badge-sidebar"><?php echo $unreadNotifications; ?></span></a>
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

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">
        <!-- TOP HEADER -->
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="date-time">
                    <span class="date" id="currentDate"><?php echo $currentDate; ?></span>
                    <span class="time" id="currentTime"><?php echo $currentTime; ?></span>
                </div>
            </div>
            <div class="header-right">
                <!-- NOTIFICATION BAR -->
                <div class="notification-bar" id="notificationBar">
                    <i class="fas fa-bell notif-icon"></i>
                    <span class="notif-text" id="notifText">
                        <?php
                        $latestNotif = !empty($_SESSION['notifications']) ? $_SESSION['notifications'][0] : null;
                        if ($latestNotif) {
                            echo '<strong>' . htmlspecialchars($latestNotif['title']) . ':</strong> ' . htmlspecialchars($latestNotif['message']);
                        } else {
                            echo 'No new notifications';
                        }
                        ?>
                    </span>
                    <button class="notif-close" onclick="dismissNotification()"><i class="fas fa-times"></i></button>
                </div>
                <a href="notifications.php" class="notification-bell" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifications > 0): ?>
                    <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <div class="page-content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <h1 class="page-title">
                    <span class="title-icon"><i class="fas fa-camera" style="color:var(--blue);"></i></span>
                    Live Camera Feed
                    <span class="badge-live">Live</span>
                </h1>
                <p class="page-subtitle">
                    <span>Real-time AI-powered monitoring with health detection</span>
                    <span style="font-size:0.75rem;color:var(--text-muted);">
                        <i class="fas fa-thermometer-half"></i> <?php echo $sharedData['temperature']; ?>°C
                        <i class="fas fa-tint" style="margin-left:0.8rem;"></i> <?php echo $sharedData['humidity']; ?>%
                    </span>
                </p>
            </div>

            <!-- CAMERA GRID -->
            <div class="camera-grid">
                <div class="camera-feed" id="cameraFeed">
                    <div class="feed-overlay">
                        <span class="overlay-badge live"><span class="dot"></span> LIVE</span>
                        <span class="overlay-badge">Camera 1 - Coop A</span>
                        <span class="overlay-badge ai-active"><i class="fas fa-microchip"></i> AI Active</span>
                        <span class="overlay-badge"><?php echo $streamQuality; ?> @ <?php echo $fps; ?> FPS</span>
                    </div>
                    <div class="feed-placeholder" id="feedPlaceholder">
                        <i class="fas fa-video"></i>
                        <p>Live Camera Feed</p>
                        <p class="sub-text">AI Detection Active • <?php echo $streamQuality; ?> • <?php echo $fps; ?> FPS</p>
                    </div>
                    <div class="detection-boxes" id="detectionBoxes">
                        <?php
                        $boxes = [
                            ['id' => 'CHK-001', 'status' => 'healthy', 'top' => '12%', 'left' => '8%', 'width' => '100px', 'height' => '100px', 'conf' => '98%'],
                            ['id' => 'CHK-002', 'status' => 'healthy', 'top' => '18%', 'left' => '35%', 'width' => '95px', 'height' => '95px', 'conf' => '96%'],
                            ['id' => 'CHK-004', 'status' => 'weak', 'top' => '15%', 'left' => '58%', 'width' => '90px', 'height' => '90px', 'conf' => '87%'],
                            ['id' => 'CHK-003', 'status' => 'healthy', 'top' => '48%', 'left' => '12%', 'width' => '100px', 'height' => '100px', 'conf' => '99%'],
                            ['id' => 'CHK-005', 'status' => 'unhealthy', 'top' => '52%', 'left' => '55%', 'width' => '95px', 'height' => '95px', 'conf' => '92%'],
                        ];
                        foreach ($boxes as $box):
                            $resp = $chickHealthData[$box['id']]['respiratory'];
                            $heat = $chickHealthData[$box['id']]['heat_stress'];
                            $respStatus = $resp['has_respiratory_issues'] ? 'warning' : '';
                            $heatStatus = $heat['risk_level'] !== 'normal' ? $heat['risk_level'] : '';
                        ?>
                        <div class="detection-box <?php echo $box['status'] === 'weak' ? 'weak' : ($box['status'] === 'unhealthy' ? 'unhealthy' : ''); ?>" 
                             style="top:<?php echo $box['top']; ?>;left:<?php echo $box['left']; ?>;width:<?php echo $box['width']; ?>;height:<?php echo $box['height']; ?>;">
                            <span class="box-label"><?php echo $box['id']; ?> • <?php echo ucfirst($box['status']); ?></span>
                            <span class="box-confidence"><?php echo $box['conf']; ?></span>
                            <?php if ($resp['has_respiratory_issues']): ?>
                            <span class="box-respiratory warning"><i class="fas fa-lungs"></i> <?php echo ucfirst($resp['severity']); ?></span>
                            <?php endif; ?>
                            <?php if ($heat['risk_level'] !== 'normal'): ?>
                            <span class="box-heat <?php echo $heat['risk_level'] === 'critical' ? 'critical' : ''; ?>">
                                <i class="fas fa-thermometer-half"></i> <?php echo ucfirst($heat['risk_level']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="feed-controls">
                        <button onclick="toggleDetection()" class="active" id="btnDetection"><i class="fas fa-brain"></i> AI</button>
                        <button onclick="captureSnapshot()" class="capture-btn" id="captureBtn"><i class="fas fa-camera"></i> Capture</button>
                        <button onclick="toggleFullscreen()"><i class="fas fa-expand"></i> Fullscreen</button>
                    </div>
                </div>

                <div class="camera-info-panel">
                    <div class="info-card">
                        <div class="info-header"><i class="fas fa-info-circle" style="color:var(--blue);"></i> Camera Status</div>
                        <div class="info-row"><span class="info-label">Status</span><span class="info-value online"><i class="fas fa-circle" style="font-size:0.5rem;vertical-align:middle;color:var(--green);"></i> Online</span></div>
                        <div class="info-row"><span class="info-label">Resolution</span><span class="info-value"><?php echo $streamQuality; ?></span></div>
                        <div class="info-row"><span class="info-label">Frame Rate</span><span class="info-value"><?php echo $fps; ?> FPS</span></div>
                        <div class="info-row"><span class="info-label">AI Detection</span><span class="info-value" style="color:var(--green);"><i class="fas fa-check-circle"></i> Active</span></div>
                        <div class="info-row"><span class="info-label">Last Detection</span><span class="info-value"><?php echo $lastDetection; ?></span></div>
                    </div>
                    <div class="info-card">
                        <div class="info-header"><i class="fas fa-chart-bar" style="color:var(--green);"></i> Detection Summary</div>
                        <div class="info-row"><span class="info-label">Total Chicks</span><span class="info-value">5</span></div>
                        <div class="info-row"><span class="info-label">Healthy</span><span class="info-value" style="color:var(--green);">3</span></div>
                        <div class="info-row"><span class="info-label">Weak</span><span class="info-value" style="color:var(--yellow);">1</span></div>
                        <div class="info-row"><span class="info-label">Unhealthy</span><span class="info-value" style="color:var(--red);">1</span></div>
                        <div class="info-row"><span class="info-label">Avg Confidence</span><span class="info-value">94.8%</span></div>
                    </div>
                    <div class="info-card">
                        <div class="info-header"><i class="fas fa-thermometer-half" style="color:var(--orange);"></i> Environment</div>
                        <div class="info-row"><span class="info-label">Temperature</span><span class="info-value"><?php echo $sharedData['temperature']; ?>°C</span></div>
                        <div class="info-row"><span class="info-label">Humidity</span><span class="info-value"><?php echo $sharedData['humidity']; ?>%</span></div>
                        <div class="info-row"><span class="info-label">Heat Index</span><span class="info-value">
                            <?php 
                            $avgHeatIndex = 0;
                            foreach ($chickHealthData as $data) { $avgHeatIndex += $data['heat_stress']['heat_index']; }
                            echo round($avgHeatIndex / count($chickHealthData), 1); ?>°C
                        </span></div>
                    </div>
                </div>
            </div>

            <!-- AI Detection Preview -->
            <div class="section-label">
                <span><i class="fas fa-robot"></i> AI Detection Preview</span>
                <span class="badge-count">Click card for details</span>
            </div>
            <div class="detection-preview">
                <div class="detection-item healthy" onclick="showToast('Healthy chicks: 3 detected - All respiratory clear, normal heat stress')">
                    <div class="detection-icon" style="color:#27AE60;"><i class="fas fa-check-circle"></i></div>
                    <div class="detection-value" style="color:#27AE60;">3</div>
                    <div class="detection-label">Healthy Detected</div>
                    <span class="detection-change up"><i class="fas fa-arrow-up"></i> +0</span>
                </div>
                <div class="detection-item weak" onclick="showToast('Weak chicks: 1 detected - Respiratory symptoms present, monitor closely')">
                    <div class="detection-icon" style="color:#F39C12;"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="detection-value" style="color:#F39C12;">1</div>
                    <div class="detection-label">Weak Detected</div>
                    <span class="detection-change up"><i class="fas fa-arrow-up"></i> +0</span>
                </div>
                <div class="detection-item unhealthy" onclick="showToast('Unhealthy chicks: 1 detected - Immediate action required! Heat stress + respiratory issues')">
                    <div class="detection-icon" style="color:#E74C3C;"><i class="fas fa-times-circle"></i></div>
                    <div class="detection-value" style="color:#E74C3C;">1</div>
                    <div class="detection-label">Unhealthy Detected</div>
                    <span class="detection-change down"><i class="fas fa-arrow-down"></i> -0</span>
                </div>
            </div>

            <!-- Snapshot Grid -->
            <div class="section-label">
                <span><i class="fas fa-images"></i> Recent Snapshots</span>
                <span style="font-size:0.65rem;color:var(--text-muted);">Click to view • Hover to preview</span>
            </div>
            <div class="snapshot-grid" id="snapshotGrid">
                <div class="snapshot-card" onclick="showToast('Viewing snapshot - 14:30:25')">
                    <div class="snapshot-img"><i class="fas fa-image"></i><span class="capture-badge">Manual</span></div>
                    <div class="snapshot-time">14:30:25</div>
                    <button class="snapshot-delete" onclick="event.stopPropagation(); deleteSnapshot(this)"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="snapshot-card" onclick="showToast('Viewing snapshot - 14:25:10')">
                    <div class="snapshot-img"><i class="fas fa-image"></i><span class="capture-badge">Manual</span></div>
                    <div class="snapshot-time">14:25:10</div>
                    <button class="snapshot-delete" onclick="event.stopPropagation(); deleteSnapshot(this)"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="snapshot-card" onclick="showToast('Viewing snapshot - 14:20:45')">
                    <div class="snapshot-img"><i class="fas fa-image"></i><span class="capture-badge">Manual</span></div>
                    <div class="snapshot-time">14:20:45</div>
                    <button class="snapshot-delete" onclick="event.stopPropagation(); deleteSnapshot(this)"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="snapshot-card empty-snapshot" id="emptySnapshot" onclick="captureSnapshot()">
                    <div class="snapshot-img"><i class="fas fa-plus-circle"></i></div>
                    <div class="snapshot-time">Capture new</div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);

        // ===== SIDEBAR TOGGLE WITH BURGER MENU =====
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

        // Close sidebar when clicking a link (mobile)
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMessage').textContent = message;
            toast.className = 'toast' + (isError ? ' error' : '');
            toast.style.display = 'flex';
            clearTimeout(toast._timeout);
            toast._timeout = setTimeout(() => { toast.style.display = 'none'; }, 3000);
        }

        // --- NOTIFICATION DISMISS ---
        function dismissNotification() {
            const bar = document.getElementById('notificationBar');
            bar.style.transition = 'all 0.3s ease';
            bar.style.opacity = '0';
            bar.style.transform = 'translateX(20px)';
            setTimeout(() => { bar.style.display = 'none'; }, 300);
        }

        let detectionVisible = true;
        function toggleDetection() {
            const btn = document.getElementById('btnDetection');
            const boxes = document.getElementById('detectionBoxes');
            detectionVisible = !detectionVisible;
            if (detectionVisible) {
                boxes.style.display = 'block';
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-brain"></i> AI';
                showToast('AI Detection activated');
            } else {
                boxes.style.display = 'none';
                btn.classList.remove('active');
                btn.innerHTML = '<i class="fas fa-brain"></i> Off';
                showToast('AI Detection deactivated', true);
            }
        }

        let snapshotCount = 3;
        function captureSnapshot() {
            const btn = document.getElementById('captureBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
            btn.style.background = '#2980B9';
            
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Captured!';
                btn.style.background = '#27AE60';
                
                const grid = document.getElementById('snapshotGrid');
                const empty = document.getElementById('emptySnapshot');
                const now = new Date();
                const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                
                const newCard = document.createElement('div');
                newCard.className = 'snapshot-card';
                newCard.setAttribute('onclick', "showToast('Viewing snapshot - " + timeStr + "')");
                newCard.innerHTML = `
                    <div class="snapshot-img"><i class="fas fa-image"></i><span class="capture-badge">Manual</span></div>
                    <div class="snapshot-time">${timeStr}</div>
                    <button class="snapshot-delete" onclick="event.stopPropagation(); deleteSnapshot(this)"><i class="fas fa-trash-alt"></i></button>
                `;
                
                grid.insertBefore(newCard, empty);
                snapshotCount++;
                
                if (snapshotCount > 6) {
                    const firstCard = grid.querySelector('.snapshot-card:not(.empty-snapshot)');
                    if (firstCard) firstCard.remove();
                    snapshotCount--;
                }
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 1500);
                
                showToast('Snapshot captured successfully!');
            }, 800);
        }

        function deleteSnapshot(element) {
            const card = element.closest('.snapshot-card');
            if (card) {
                card.style.transition = 'all 0.3s';
                card.style.transform = 'scale(0.8)';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    snapshotCount--;
                    showToast('Snapshot deleted');
                }, 300);
            }
        }

        function toggleFullscreen() {
            const feed = document.getElementById('cameraFeed');
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                feed.requestFullscreen();
            }
        }

        // Animate detection boxes
        function animateDetectionBoxes() {
            const boxes = document.querySelectorAll('.detection-box');
            boxes.forEach(box => {
                const currentTop = parseFloat(box.style.top);
                const currentLeft = parseFloat(box.style.left);
                const newTop = currentTop + (Math.random() - 0.5) * 1.2;
                const newLeft = currentLeft + (Math.random() - 0.5) * 1.2;
                box.style.top = Math.max(5, Math.min(75, newTop)) + '%';
                box.style.left = Math.max(3, Math.min(78, newLeft)) + '%';
                box.style.transition = 'all 1.5s ease-in-out';
            });
        }

        setInterval(animateDetectionBoxes, 2500);

        // Auto-dismiss notification after 8 seconds
        setTimeout(function() {
            const bar = document.getElementById('notificationBar');
            if (bar && bar.style.display !== 'none') {
                bar.style.transition = 'all 0.5s ease';
                bar.style.opacity = '0';
                setTimeout(() => { bar.style.display = 'none'; }, 500);
            }
        }, 8000);
    </script>
</body>
</html>