<?php
// config.php - shared configuration

define('ESP32_IP', '192.168.1.12');  // PALITAN ng actual IP ng iyong ESP32
define('ESP32_PORT', 80);

// ===== GET SENSOR DATA (kasama ang pump status) =====
function espGetSensorData() {
    $url = "http://" . ESP32_IP . ":" . ESP32_PORT . "/sensor";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['temperature']) && isset($data['humidity'])) {
            return $data;  // may 'fan', 'pump', 'light', 'gate', 'dynamo' fields din
        }
    }
    return false;
}

// ===== SEND COMMAND TO ANY ENDPOINT =====
function espSendCommand($endpoint) {
    $url = "http://" . ESP32_IP . ":" . ESP32_PORT . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode == 200);
}

// ===== PUMP-SPECIFIC HELPERS =====
function espPumpOn() {
    return espSendCommand('/pump_on');
}
function espPumpOff() {
    return espSendCommand('/pump_off');
}
function espGetPumpStatus() {
    $data = espGetSensorData();
    if ($data !== false && isset($data['pump'])) {
        return $data['pump'] ? 'ON' : 'OFF';
    }
    return false;
}

// ===== GATE-SPECIFIC HELPERS =====
function espGateOpen() {
    return espSendCommand('/gate_open');
}
function espGateClose() {
    return espSendCommand('/gate_close');
}
function espGetGateStatus() {
    $data = espGetSensorData();
    if ($data !== false && isset($data['gate'])) {
        return $data['gate'] ? 'OPEN' : 'CLOSED';
    }
    return 'UNKNOWN';
}

// ===== DYNAMO-SPECIFIC HELPERS =====
function espDynamoOn() {
    return espSendCommand('/dynamo_on');
}
function espDynamoOff() {
    return espSendCommand('/dynamo_off');
}
function espGetDynamoStatus() {
    $data = espGetSensorData();
    if ($data !== false && isset($data['dynamo'])) {
        return $data['dynamo'] ? 'ON' : 'OFF';
    }
    return false;
}
?>