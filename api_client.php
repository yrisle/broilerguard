<?php
// api_client.php - PHP client for Python Disease Detection API

class DiseaseDetectionAPI {
    private $base_url;
    private $timeout;
    
    public function __construct($base_url = 'http://localhost:5000') {
        $this->base_url = $base_url;
        $this->timeout = 30;
    }
    
    /**
     * Detect diseases in an image
     */
    public function detect($image_path) {
        $url = $this->base_url . '/api/detect';
        
        $post_data = array(
            'image' => new CURLFile($image_path)
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API request failed with code: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Detect with visualization (returns image with bounding boxes)
     */
    public function detectStream($image_path) {
        $url = $this->base_url . '/api/detect_stream';
        
        $post_data = array(
            'image' => new CURLFile($image_path)
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API request failed with code: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Detect from base64 image data
     */
    public function detectBase64($image_base64) {
        $url = $this->base_url . '/api/detect';
        
        $post_data = json_encode(array(
            'image_base64' => $image_base64
        ));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json'
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API request failed with code: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get recent detections
     */
    public function getDetections($limit = 20, $offset = 0) {
        $url = $this->base_url . '/api/detections?limit=' . $limit . '&offset=' . $offset;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API request failed with code: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get camera snapshots
     */
    public function getSnapshots($limit = 10) {
        $url = $this->base_url . '/api/snapshots?limit=' . $limit;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API request failed with code: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get statistics
     */
    public function getStats($period = 'today') {
        $url = $this->base_url . '/api/stats?period=' . $period;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API request failed with code: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Health check
     */
    public function health() {
        $url = $this->base_url . '/api/health';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return array('error' => 'API not available');
        }
        
        return json_decode($response, true);
    }
}

// ============================================================
// HELPER FUNCTIONS - Only declare if not already declared
// ============================================================

if (!function_exists('saveDetectionToLocalDB')) {
    function saveDetectionToLocalDB($detection_data, $user_id = 1) {
        global $pdo;
        
        if (!isset($pdo)) {
            require_once 'db_connect.php';
        }
        
        $detections = $detection_data['detections'] ?? array();
        $chick_count = $detection_data['chick_count'] ?? 0;
        $healthy_count = $detection_data['healthy_count'] ?? 0;
        $weak_count = $detection_data['weak_count'] ?? 0;
        $unhealthy_count = $detection_data['unhealthy_count'] ?? 0;
        
        if ($unhealthy_count > 0) {
            $status = 'unhealthy';
        } elseif ($weak_count > 0) {
            $status = 'weak';
        } else {
            $status = 'healthy';
        }
        
        $first_disease = !empty($detections) ? $detections[0]['disease'] : 'None';
        $first_confidence = !empty($detections) ? $detections[0]['confidence'] : 0;
        $details = json_encode($detections);
        
        $stmt = $pdo->prepare("
            INSERT INTO detection_logs 
            (user_id, disease, confidence, status, chick_count, healthy_count, weak_count, unhealthy_count, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $first_disease,
            $first_confidence,
            $status,
            $chick_count,
            $healthy_count,
            $weak_count,
            $unhealthy_count,
            $details
        ]);
        
        return $pdo->lastInsertId();
    }
}

if (!function_exists('getDetectionSummary')) {
    function getDetectionSummary($user_id = 1) {
        global $pdo;
        
        if (!isset($pdo)) {
            require_once 'db_connect.php';
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as healthy,
                SUM(CASE WHEN status = 'weak' THEN 1 ELSE 0 END) as weak,
                SUM(CASE WHEN status = 'unhealthy' THEN 1 ELSE 0 END) as unhealthy
            FROM detection_logs 
            WHERE user_id = ? AND DATE(timestamp) = DATE('now')
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: ['total' => 0, 'healthy' => 0, 'weak' => 0, 'unhealthy' => 0];
    }
}

if (!function_exists('getDiseaseDistribution')) {
    function getDiseaseDistribution($user_id = 1, $days = 7) {
        global $pdo;
        
        if (!isset($pdo)) {
            require_once 'db_connect.php';
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                disease,
                COUNT(*) as count
            FROM detection_logs 
            WHERE user_id = ? 
            AND disease != 'None'
            AND timestamp >= DATE('now', ?)
            GROUP BY disease
            ORDER BY count DESC
        ");
        $stmt->execute([$user_id, '-' . $days . ' days']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getRecentDetections')) {
    function getRecentDetections($user_id = 1, $limit = 10) {
        global $pdo;
        
        if (!isset($pdo)) {
            require_once 'db_connect.php';
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM detection_logs 
            WHERE user_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>