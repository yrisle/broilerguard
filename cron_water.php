<?php
// cron_water.php - Run every minute to check watering schedules
define('ESP32_BASE_URL', 'http://192.168.1.100');

function sendCommand($endpoint) {
    $url = ESP32_BASE_URL . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode == 200);
}

$scheduleFile = __DIR__ . '/water_schedules.json';
if (!file_exists($scheduleFile)) {
    // Default schedules
    $schedules = [
        ['id' => 1, 'time' => '06:00', 'duration' => 30, 'enabled' => true, 'label' => 'Morning Watering'],
        ['id' => 2, 'time' => '12:00', 'duration' => 25, 'enabled' => true, 'label' => 'Afternoon Watering'],
        ['id' => 3, 'time' => '18:00', 'duration' => 30, 'enabled' => true, 'label' => 'Evening Watering']
    ];
    file_put_contents($scheduleFile, json_encode($schedules));
} else {
    $schedules = json_decode(file_get_contents($scheduleFile), true);
}

$now = date('H:i');
$today = date('Y-m-d');
$lastRunFile = __DIR__ . '/last_water_run.json';
$lastRun = [];
if (file_exists($lastRunFile)) {
    $lastRun = json_decode(file_get_contents($lastRunFile), true);
}
if (!isset($lastRun[$today])) $lastRun[$today] = [];

foreach ($schedules as $schedule) {
    if (!$schedule['enabled']) continue;
    if ($schedule['time'] === $now) {
        if (in_array($schedule['id'], $lastRun[$today])) continue;
        if (sendCommand('/pump_on')) {
            // Schedule off after duration
            $pendingOffFile = __DIR__ . '/pending_off.json';
            $pending = [];
            if (file_exists($pendingOffFile)) {
                $pending = json_decode(file_get_contents($pendingOffFile), true);
            }
            $pending[] = [
                'time' => time() + $schedule['duration'],
                'duration' => $schedule['duration'],
                'schedule_id' => $schedule['id']
            ];
            file_put_contents($pendingOffFile, json_encode($pending));
            $lastRun[$today][] = $schedule['id'];
            file_put_contents($lastRunFile, json_encode($lastRun));
            file_put_contents(__DIR__ . '/water_log.txt', date('Y-m-d H:i:s') . " - Schedule {$schedule['label']} started\n", FILE_APPEND);
        }
    }
}

// Check pending off tasks
$pendingOffFile = __DIR__ . '/pending_off.json';
if (file_exists($pendingOffFile)) {
    $pending = json_decode(file_get_contents($pendingOffFile), true);
    $nowTime = time();
    $remaining = [];
    foreach ($pending as $task) {
        if ($task['time'] <= $nowTime) {
            sendCommand('/pump_off');
            file_put_contents(__DIR__ . '/water_log.txt', date('Y-m-d H:i:s') . " - Schedule ID {$task['schedule_id']} stopped\n", FILE_APPEND);
        } else {
            $remaining[] = $task;
        }
    }
    file_put_contents($pendingOffFile, json_encode($remaining));
}
?>