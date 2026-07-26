<?php
// detection_history.php - AI Detection History Module
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

// Get filter parameters from URL
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'newest';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

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
    
    if ($temperature > 32) {
        $riskLevel = 'critical';
        $riskColor = '#E74C3C';
    } elseif ($temperature > 30) {
        $riskLevel = 'high';
        $riskColor = '#F39C12';
    } elseif ($temperature > 28) {
        $riskLevel = 'moderate';
        $riskColor = '#F1C40F';
    }
    
    if ($heatIndex > 45) {
        $riskLevel = 'critical';
        $riskColor = '#E74C3C';
    } elseif ($heatIndex > 40) {
        $riskLevel = 'high';
        $riskColor = '#F39C12';
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

// Function to fetch detection history
function getDetectionHistory($filter = 'all', $sortBy = 'newest', $search = '', $dateFrom = '', $dateTo = '') {
    $temp = isset($GLOBALS['sharedData']['temperature']) ? $GLOBALS['sharedData']['temperature'] : 29.5;
    $humidity = isset($GLOBALS['sharedData']['humidity']) ? $GLOBALS['sharedData']['humidity'] : 65;
    
    $allResults = [];
    $statuses = ['healthy', 'weak', 'unhealthy'];
    $activities = ['Active', 'Feeding', 'Drinking', 'Resting', 'Lethargic', 'Scratching', 'Dustbathing'];
    $chicks = ['CHK-001', 'CHK-002', 'CHK-003', 'CHK-004', 'CHK-005'];
    
    $startDate = strtotime('-30 days');
    for ($i = 0; $i < 65; $i++) {
        $timestamp = $startDate + ($i * 43200);
        $status = $statuses[array_rand($statuses)];
        $chickId = $chicks[array_rand($chicks)];
        
        if ($status === 'healthy') $confidence = 94 + (mt_rand(0, 50) / 10);
        else if ($status === 'weak') $confidence = 80 + (mt_rand(0, 90) / 10);
        else $confidence = 70 + (mt_rand(0, 120) / 10);
        $confidence = min(99.9, max(68, $confidence));
        
        $weight = $status === 'healthy' ? 0.21 + (mt_rand(0, 60) / 1000) : 
                  ($status === 'weak' ? 0.17 + (mt_rand(0, 40) / 1000) : 0.14 + (mt_rand(0, 40) / 1000));
        
        $activity = $status === 'unhealthy' ? (mt_rand(0, 10) > 6 ? 'Lethargic' : 'Inactive') : 
                    $activities[array_rand($activities)];
        
        // Get health data for this chick
        $respiratory = detectRespiratoryDisease($chickId);
        $heatStress = monitorHeatStress($temp, $humidity, $chickId);
        $assessment = enhancedHealthAssessment($chickId);
        
        $allResults[] = [
            'id' => 'DET-' . str_pad(100 + $i, 3, '0', STR_PAD_LEFT),
            'time' => date('Y-m-d H:i:s', $timestamp),
            'chick_id' => $chickId,
            'status' => $status,
            'confidence' => round($confidence, 1),
            'weight' => number_format($weight, 2) . ' kg',
            'activity' => $activity,
            'duration' => round(1.5 + (mt_rand(0, 40) / 10), 1) . 's',
            'respiratory' => $respiratory,
            'heat_stress' => $heatStress,
            'assessment' => $assessment
        ];
    }
    
    usort($allResults, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    $results = $allResults;
    
    if ($search) {
        $searchLower = strtolower($search);
        $results = array_filter($results, function($result) use ($searchLower) {
            return strpos(strtolower($result['chick_id']), $searchLower) !== false ||
                   strpos(strtolower($result['id']), $searchLower) !== false ||
                   strpos(strtolower($result['status']), $searchLower) !== false ||
                   strpos(strtolower($result['activity']), $searchLower) !== false;
        });
    }
    
    if ($filter !== 'all') {
        $results = array_filter($results, function($result) use ($filter) {
            return $result['status'] === $filter;
        });
    }
    
    if ($dateFrom || $dateTo) {
        $results = array_filter($results, function($result) use ($dateFrom, $dateTo) {
            $recordDate = strtotime($result['time']);
            if ($dateFrom && $recordDate < strtotime($dateFrom)) return false;
            if ($dateTo && $recordDate > strtotime($dateTo . ' 23:59:59')) return false;
            return true;
        });
    }
    
    usort($results, function($a, $b) use ($sortBy) {
        if ($sortBy === 'oldest') return strtotime($a['time']) - strtotime($b['time']);
        if ($sortBy === 'newest') return strtotime($b['time']) - strtotime($a['time']);
        if ($sortBy === 'confidence_high') return $b['confidence'] - $a['confidence'];
        if ($sortBy === 'confidence_low') return $a['confidence'] - $b['confidence'];
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    return array_values($results);
}

$detectionHistory = getDetectionHistory($filter, $sortBy, $search, $dateFrom, $dateTo);

// Calculate summary statistics
$totalDetections = count($detectionHistory);
$healthyCount = count(array_filter($detectionHistory, fn($r) => $r['status'] === 'healthy'));
$weakCount = count(array_filter($detectionHistory, fn($r) => $r['status'] === 'weak'));
$unhealthyCount = count(array_filter($detectionHistory, fn($r) => $r['status'] === 'unhealthy'));
$avgConfidence = $totalDetections > 0 ? round(array_sum(array_column($detectionHistory, 'confidence')) / $totalDetections, 1) : 0;

// Calculate respiratory and heat stress counts
$respiratoryConcerns = 0;
$heatStressWarnings = 0;
foreach ($detectionHistory as $record) {
    if ($record['respiratory']['has_respiratory_issues']) $respiratoryConcerns++;
    if ($record['heat_stress']['risk_level'] !== 'normal') $heatStressWarnings++;
}

// Calculate trend data for last 7 days
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayStart = strtotime($date);
    $dayEnd = strtotime($date . ' 23:59:59');
    
    $dayRecords = array_filter($detectionHistory, function($r) use ($dayStart, $dayEnd) {
        $recTime = strtotime($r['time']);
        return $recTime >= $dayStart && $recTime <= $dayEnd;
    });
    
    $trendData[] = [
        'date' => $date,
        'healthy' => count(array_filter($dayRecords, fn($r) => $r['status'] === 'healthy')),
        'weak' => count(array_filter($dayRecords, fn($r) => $r['status'] === 'weak')),
        'unhealthy' => count(array_filter($dayRecords, fn($r) => $r['status'] === 'unhealthy'))
    ];
}

$currentDate = date('F d, Y');
$currentTime = date('h:i:s A');

// Notification System
$unreadNotifications = isset($_SESSION['notifications']) ? count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; })) : 0;
if (!isset($_SESSION['notifications']) || empty($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [
        ['id' => 1, 'title' => 'Detection History Loaded', 'message' => 'AI detection history is ready for review', 'time' => '2 min ago', 'read' => false, 'type' => 'info'],
    ];
    $unreadNotifications = count(array_filter($_SESSION['notifications'], function($n) { return !$n['read']; }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Detection History | BroilerGuard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .sidebar-logo p { 
            font-size: 0.6rem; 
            color: var(--sidebar-muted); 
            letter-spacing: 2px; 
            text-transform: uppercase; 
            margin-top: 0.2rem; 
            opacity: 0.7;
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
        .page-content { padding: 2rem; max-width: 1440px; margin: 0 auto; }
        .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
        .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        
        /* ===== STATS ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.3rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); text-align: center; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-icon { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 800; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        .metrics-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1rem 1.2rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); display: flex; align-items: center; gap: 1rem; transition: all 0.3s; }
        .metric-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .metric-card .metric-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .metric-card .metric-icon.respiratory { background: var(--purple-light); color: var(--purple); }
        .metric-card .metric-icon.heat { background: #FDEBD0; color: var(--orange); }
        .metric-card .metric-icon.health { background: var(--green-light); color: var(--green); }
        .metric-card .metric-icon.disease { background: var(--red-light); color: var(--red); }
        .metric-card .metric-info { flex: 1; }
        .metric-card .metric-value { font-size: 1.2rem; font-weight: 700; }
        .metric-card .metric-label { font-size: 0.7rem; color: var(--text-muted); }
        .metric-card .metric-status { font-size: 0.65rem; padding: 0.1rem 0.5rem; border-radius: 10px; font-weight: 600; margin-top: 0.1rem; display: inline-block; }
        .metric-status.good { background: var(--green-light); color: var(--green); }
        .metric-status.warning { background: var(--yellow-light); color: var(--yellow); }
        .metric-status.danger { background: var(--red-light); color: var(--red); }
        
        /* ===== FILTER BAR ===== */
        .filter-bar { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.5rem; padding: 0.8rem 1.2rem; background: var(--bg-card); border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); }
        .filter-btn { padding: 0.4rem 1.2rem; border-radius: 20px; border: 1px solid rgba(255, 214, 46, 0.25); background: var(--bg-card); cursor: pointer; font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); transition: all 0.2s; font-family: 'Inter', sans-serif; white-space: nowrap; }
        .filter-btn:hover { background: var(--accent-light); border-color: var(--accent); }
        .filter-btn.active { background: #FFD62E; color: #3E2C1C; border-color: #FFD62E; font-weight: 600; }
        .search-bar-inline { display: flex; align-items: center; background: var(--bg-secondary); border-radius: 20px; padding: 0.2rem 0.7rem; gap: 0.4rem; border: 1px solid rgba(255, 214, 46, 0.15); min-width: 180px; }
        .search-bar-inline input { border: none; background: transparent; outline: none; font-size: 0.8rem; width: 100%; font-family: 'Inter', sans-serif; color: var(--text-primary); }
        .search-bar-inline input::placeholder { color: var(--text-muted); }
        .date-input { padding: 0.35rem 0.7rem; border-radius: 20px; border: 1px solid rgba(255, 214, 46, 0.25); font-family: 'Inter', sans-serif; font-size: 0.8rem; background: var(--bg-secondary); }
        .filter-separator { width: 1px; height: 24px; background: rgba(255, 214, 46, 0.2); margin: 0 0.5rem; }
        
        /* ===== CHARTS ===== */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
        .chart-card { background: var(--bg-card); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-sm); border: 1px solid rgba(255, 214, 46, 0.08); transition: box-shadow 0.2s; }
        .chart-card:hover { box-shadow: var(--shadow-md); }
        .chart-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .chart-card-title { font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .chart-wrapper { position: relative; width: 100%; height: 280px; max-height: 280px; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }
        
        /* ===== TABLE ===== */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: var(--bg-secondary); padding: 0.7rem 0.8rem; text-align: left; font-weight: 600; border-bottom: 2px solid rgba(255, 214, 46, 0.15); color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 0.7rem 0.8rem; border-bottom: 1px solid rgba(255, 214, 46, 0.06); }
        tr:hover td { background: var(--bg-secondary); }
        
        .badge-status { padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-healthy { background: var(--green-light); color: var(--green); }
        .badge-weak { background: var(--yellow-light); color: var(--yellow); }
        .badge-unhealthy { background: var(--red-light); color: var(--red); }
        .badge-info { background: var(--blue-light); color: var(--blue); }
        .badge-purple { background: var(--purple-light); color: var(--purple); }
        
        .card-badge { padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--green-light); color: var(--green); }
        .badge-warning { background: var(--yellow-light); color: var(--yellow); }
        .badge-danger { background: var(--red-light); color: var(--red); }
        
        .confidence-bar-bg { width: 100%; height: 6px; background: #E8E0D0; border-radius: 3px; overflow: hidden; }
        .confidence-fill { height: 100%; border-radius: 3px; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--green); color: white; padding: 0.8rem 1.2rem; border-radius: 12px; display: none; align-items: center; gap: 0.8rem; z-index: 2000; animation: slideIn 0.3s ease; font-size: 0.85rem; box-shadow: var(--shadow-md); }
        .toast.error { background: var(--red); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); width: 320px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-header .menu-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .metrics-overview { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .chart-wrapper { height: 220px; max-height: 220px; }
            .notification-bar { max-width: 200px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .metrics-overview { grid-template-columns: 1fr 1fr; }
            .page-content { padding: 1rem; }
            .top-header { padding: 0 1rem; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .metrics-overview { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-separator { display: none; }
            .search-bar-inline { width: 100%; }
            .notification-bar { max-width: 140px; padding: 0.2rem 0.6rem; }
            .notification-bar .notif-text { font-size: 0.65rem; }
            .chart-wrapper { height: 200px; max-height: 200px; }
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
            <p>Smart Poultry Management</p>
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
                <a href="detection_history.php" class="active"><i class="fas fa-history"></i> Detection History</a>
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

    <div class="main-content" id="mainContent">
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="date-time">
                    <span class="date" id="currentDate"><?php echo $currentDate; ?></span>
                    <span class="time" id="currentTime"><?php echo $currentTime; ?></span>
                </div>
            </div>
            <div class="header-right">
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

        <div class="page-content">
            <h1 class="page-title">
                <i class="fas fa-history" style="color:#8E44AD;"></i> 
                AI Detection History
            </h1>
            <p class="page-subtitle">Complete historical log of AI-driven health assessments including respiratory, heat stress, and disease detection</p>

            <!-- Health Metrics Overview -->
            <div class="metrics-overview">
                <div class="metric-card">
                    <div class="metric-icon respiratory"><i class="fas fa-lungs"></i></div>
                    <div class="metric-info">
                        <div class="metric-value" style="color:var(--purple);"><?php echo $respiratoryConcerns; ?></div>
                        <div class="metric-label">Respiratory Concerns</div>
                        <span class="metric-status <?php echo $respiratoryConcerns > 10 ? 'warning' : 'good'; ?>">
                            <?php echo $respiratoryConcerns > 10 ? '⚠️ Monitor' : '✓ Clear'; ?>
                        </span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon heat"><i class="fas fa-temperature-high"></i></div>
                    <div class="metric-info">
                        <div class="metric-value" style="color:var(--orange);"><?php echo $heatStressWarnings; ?></div>
                        <div class="metric-label">Heat Stress Warnings</div>
                        <span class="metric-status <?php echo $heatStressWarnings > 10 ? 'warning' : 'good'; ?>">
                            <?php echo $heatStressWarnings > 10 ? '⚠️ Monitor' : '✓ Normal'; ?>
                        </span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon health"><i class="fas fa-heartbeat"></i></div>
                    <div class="metric-info">
                        <div class="metric-value" style="color:var(--green);"><?php echo $avgConfidence; ?>%</div>
                        <div class="metric-label">Avg Confidence</div>
                        <span class="metric-status good">✓ High</span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon disease"><i class="fas fa-virus"></i></div>
                    <div class="metric-info">
                        <div class="metric-value" style="color:var(--red);"><?php echo $unhealthyCount; ?></div>
                        <div class="metric-label">Unhealthy Records</div>
                        <span class="metric-status <?php echo $unhealthyCount > 10 ? 'danger' : 'good'; ?>">
                            <?php echo $unhealthyCount > 10 ? '⚠️ Review' : '✓ Normal'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color:#2980B9;"><i class="fas fa-database"></i></div>
                    <div class="stat-value" style="color:#2980B9;"><?php echo $totalDetections; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:#27AE60;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value" style="color:#27AE60;"><?php echo $healthyCount; ?></div>
                    <div class="stat-label">Healthy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:#F39C12;"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-value" style="color:#F39C12;"><?php echo $weakCount; ?></div>
                    <div class="stat-label">Weak</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:#E74C3C;"><i class="fas fa-skull-crosswalk"></i></div>
                    <div class="stat-value" style="color:#E74C3C;"><?php echo $unhealthyCount; ?></div>
                    <div class="stat-label">Unhealthy</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="" class="filter-bar">
                <span style="font-weight:600;font-size:0.85rem;margin-right:0.3rem;"><i class="fas fa-filter"></i> Filter:</span>
                <button type="submit" name="filter" value="all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</button>
                <button type="submit" name="filter" value="healthy" class="filter-btn <?php echo $filter === 'healthy' ? 'active' : ''; ?>"><i class="fas fa-check-circle" style="color:#27AE60;"></i> Healthy</button>
                <button type="submit" name="filter" value="weak" class="filter-btn <?php echo $filter === 'weak' ? 'active' : ''; ?>"><i class="fas fa-exclamation-circle" style="color:#F39C12;"></i> Weak</button>
                <button type="submit" name="filter" value="unhealthy" class="filter-btn <?php echo $filter === 'unhealthy' ? 'active' : ''; ?>"><i class="fas fa-times-circle" style="color:#E74C3C;"></i> Unhealthy</button>
                
                <span class="filter-separator"></span>
                
                <div class="search-bar-inline">
                    <i class="fas fa-search" style="color:var(--text-muted);"></i>
                    <input type="text" name="search" placeholder="Search Chick ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <span class="filter-separator"></span>
                
                <input type="date" name="date_from" class="date-input" value="<?php echo $dateFrom; ?>">
                <span style="font-size:0.8rem;color:var(--text-muted);">to</span>
                <input type="date" name="date_to" class="date-input" value="<?php echo $dateTo; ?>">
                
                <select name="sort_by" class="filter-btn" style="cursor:pointer;background:var(--bg-secondary);">
                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="confidence_high" <?php echo $sortBy === 'confidence_high' ? 'selected' : ''; ?>>Highest Confidence</option>
                    <option value="confidence_low" <?php echo $sortBy === 'confidence_low' ? 'selected' : ''; ?>>Lowest Confidence</option>
                </select>
                
                <button type="submit" class="filter-btn" style="background:var(--accent-light);border-color:var(--accent);"><i class="fas fa-sync-alt"></i> Apply</button>
                <a href="detection_history.php" class="filter-btn" style="text-decoration:none;">Reset</a>
            </form>

            <!-- Trend Charts -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title"><i class="fas fa-chart-line" style="color:var(--blue);"></i> Detection Trends (Last 7 days)</span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title"><i class="fas fa-chart-pie" style="color:var(--purple);"></i> Distribution Overview</span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="distributionPieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="chart-card" style="padding:0;overflow:hidden;">
                <div class="chart-card-header" style="padding:1.5rem 1.5rem 0 1.5rem;">
                    <span class="chart-card-title"><i class="fas fa-table-list"></i> Historical Detection Logs</span>
                    <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo $totalDetections; ?> entries</span>
                </div>
                <div class="table-container" style="padding:0 1.5rem 1.5rem 1.5rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Detection ID</th>
                                <th>Timestamp</th>
                                <th>Chick ID</th>
                                <th>Status</th>
                                <th>Confidence</th>
                                <th>Respiratory</th>
                                <th>Heat Stress</th>
                                <th>Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($detectionHistory)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">
                                    No detection history found matching your filters.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($detectionHistory as $result): 
                                    $resp = $result['respiratory'];
                                    $heat = $result['heat_stress'];
                                    $respStatus = $resp['has_respiratory_issues'] ? $resp['severity'] : 'Clear';
                                    $respBadge = $resp['has_respiratory_issues'] ? 'badge-warning' : 'badge-success';
                                    $heatBadge = $heat['risk_level'] === 'normal' ? 'badge-success' : ($heat['risk_level'] === 'moderate' ? 'badge-warning' : 'badge-danger');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($result['id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($result['time']); ?></td>
                                    <td><?php echo htmlspecialchars($result['chick_id']); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $result['status']; ?>">
                                            <?php echo ucfirst($result['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.5rem;">
                                            <span style="font-weight:600;font-size:0.75rem;"><?php echo $result['confidence']; ?>%</span>
                                            <div class="confidence-bar-bg" style="flex:1;">
                                                <div class="confidence-fill" style="width:<?php echo $result['confidence']; ?>%; background:<?php echo $result['confidence'] >= 95 ? '#27AE60' : ($result['confidence'] >= 85 ? '#F39C12' : '#E74C3C'); ?>;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="card-badge <?php echo $respBadge; ?>">
                                            <?php echo ucfirst($respStatus); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="card-badge <?php echo $heatBadge; ?>">
                                            <?php echo ucfirst($heat['risk_level']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['activity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"><i class="fas fa-check-circle"></i><span id="toastMessage"></span></div>

    <script>
        const trendData = <?php echo json_encode($trendData); ?>;
        const healthyCount = <?php echo $healthyCount; ?>;
        const weakCount = <?php echo $weakCount; ?>;
        const unhealthyCount = <?php echo $unhealthyCount; ?>;
        
        let trendChart, pieChart;

        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        function dismissNotification() {
            const bar = document.getElementById('notificationBar');
            bar.style.transition = 'all 0.3s ease';
            bar.style.opacity = '0';
            bar.style.transform = 'translateX(20px)';
            setTimeout(() => { bar.style.display = 'none'; }, 300);
        }

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
        
        function initCharts() {
            const ctxTrend = document.getElementById('trendChart').getContext('2d');
            const labels = trendData.map(d => d.date.slice(5));
            const healthyData = trendData.map(d => d.healthy);
            const weakData = trendData.map(d => d.weak);
            const unhealthyData = trendData.map(d => d.unhealthy);
            
            if (trendChart) trendChart.destroy();
            trendChart = new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Healthy', data: healthyData, borderColor: '#27AE60', backgroundColor: 'rgba(39,174,96,0.1)', fill: true, tension: 0.3, pointBackgroundColor: '#27AE60', borderWidth: 2.5 },
                        { label: 'Weak', data: weakData, borderColor: '#F39C12', backgroundColor: 'rgba(243,156,18,0.1)', fill: true, tension: 0.3, pointBackgroundColor: '#F39C12', borderWidth: 2.5 },
                        { label: 'Unhealthy', data: unhealthyData, borderColor: '#E74C3C', backgroundColor: 'rgba(231,76,60,0.1)', fill: true, tension: 0.3, pointBackgroundColor: '#E74C3C', borderWidth: 2.5 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            position: 'top', 
                            labels: { boxWidth: 12, padding: 10, font: { size: 10, weight: '500' } } 
                        } 
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(139,115,85,0.08)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
            
            const ctxPie = document.getElementById('distributionPieChart').getContext('2d');
            if (pieChart) pieChart.destroy();
            pieChart = new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ['Healthy', 'Weak', 'Unhealthy'],
                    datasets: [{
                        data: [healthyCount, weakCount, unhealthyCount],
                        backgroundColor: ['#27AE60', '#F39C12', '#E74C3C'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            position: 'bottom', 
                            labels: { boxWidth: 12, padding: 10, font: { size: 10, weight: '500' } } 
                        } 
                    },
                    cutout: '65%'
                }
            });
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);
        initCharts();

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