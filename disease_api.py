# disease_api.py - Updated with correct paths
from flask import Flask, request, jsonify
from flask_cors import CORS
import cv2
import numpy as np
import tensorflow as tf
import pickle
import os
from datetime import datetime
import base64
import json
import sqlite3

app = Flask(__name__)
CORS(app)

# ==========================
# FIND MODEL FILES
# ==========================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
print(f"📁 Script directory: {SCRIPT_DIR}")

# Try multiple locations for model files
MODEL_PATHS = [
    os.path.join(SCRIPT_DIR, "chicken_disease_model.keras"),  # BG folder
    os.path.join(SCRIPT_DIR, "..", "broilerguard-feature-yolo-cnn-disease-detection", "chicken_disease_model.keras"),  # nasa labas
    os.path.join(SCRIPT_DIR, "..", "..", "broilerguard-feature-yolo-cnn-disease-detection", "chicken_disease_model.keras"),
]

ENCODER_PATHS = [
    os.path.join(SCRIPT_DIR, "label_encoder.pkl"),
    os.path.join(SCRIPT_DIR, "..", "broilerguard-feature-yolo-cnn-disease-detection", "label_encoder.pkl"),
    os.path.join(SCRIPT_DIR, "..", "..", "broilerguard-feature-yolo-cnn-disease-detection", "label_encoder.pkl"),
]

YOLO_PATHS = [
    os.path.join(SCRIPT_DIR, "yolov8n.pt"),
    os.path.join(SCRIPT_DIR, "..", "broilerguard-feature-yolo-cnn-disease-detection", "yolov8n.pt"),
    os.path.join(SCRIPT_DIR, "..", "..", "broilerguard-feature-yolo-cnn-disease-detection", "yolov8n.pt"),
]

# Find model
MODEL_PATH = None
for path in MODEL_PATHS:
    if os.path.exists(path):
        MODEL_PATH = path
        print(f"✅ Found model: {path}")
        break

if MODEL_PATH is None:
    print("❌ Model not found! Looking in:")
    for path in MODEL_PATHS:
        print(f"  - {path}")
    print("\n💡 Please copy the model files to C:\\xampp\\htdocs\\BG\\")
    print("   Or update the paths above.")
    exit(1)

# Find encoder
ENCODER_PATH = None
for path in ENCODER_PATHS:
    if os.path.exists(path):
        ENCODER_PATH = path
        print(f"✅ Found encoder: {path}")
        break

if ENCODER_PATH is None:
    print("❌ Encoder not found!")
    exit(1)

# Find YOLO
YOLO_PATH = None
for path in YOLO_PATHS:
    if os.path.exists(path):
        YOLO_PATH = path
        print(f"✅ Found YOLO: {path}")
        break

# ==========================
# LOAD MODELS
# ==========================

print("\n🔄 Loading models...")

# Load disease model
model = tf.keras.models.load_model(MODEL_PATH)
print("✅ Disease model loaded!")

# Load label encoder
with open(ENCODER_PATH, "rb") as f:
    encoder = pickle.load(f)
print(f"📋 Classes: {encoder.classes_}")

# Load YOLO
YOLO_AVAILABLE = False
try:
    from ultralytics import YOLO
    if YOLO_PATH and os.path.exists(YOLO_PATH):
        yolo_model = YOLO(YOLO_PATH)
        print("✅ YOLO model loaded!")
        YOLO_AVAILABLE = True
    else:
        print("⚠️ YOLO not found, downloading...")
        yolo_model = YOLO('yolov8n.pt')
        YOLO_AVAILABLE = True
except Exception as e:
    print(f"⚠️ YOLO not available: {e}")

IMAGE_SIZE = 128
DISEASE_COLORS = {
    "Healthy": (0, 255, 0),
    "Coccidiosis": (0, 165, 255),
    "New Castle Disease": (255, 0, 255),
    "Salmonella": (0, 0, 255)
}

print("\n✅ All models loaded successfully!")

# ==========================
# DATABASE
# ==========================

DB_PATH = os.path.join(SCRIPT_DIR, 'disease_detections.db')

def init_database():
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS detection_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT 1,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            image_path TEXT,
            disease TEXT,
            confidence REAL,
            status TEXT,
            chick_count INTEGER DEFAULT 0,
            healthy_count INTEGER DEFAULT 0,
            weak_count INTEGER DEFAULT 0,
            unhealthy_count INTEGER DEFAULT 0,
            details TEXT
        )
    ''')
    
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS camera_snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT 1,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            image_url TEXT,
            detection_summary TEXT
        )
    ''')
    
    conn.commit()
    conn.close()
    print("✅ Database initialized")

init_database()

# ==========================
# DETECTION FUNCTIONS
# ==========================

def classify_chicken(roi):
    if roi.size == 0 or roi.shape[0] < 10 or roi.shape[1] < 10:
        return "No Chicken", 0
    
    try:
        img = cv2.resize(roi, (IMAGE_SIZE, IMAGE_SIZE))
        img = img.astype("float32") / 255.0
        img = np.expand_dims(img, axis=0)
        
        prediction = model.predict(img, verbose=0)
        index = np.argmax(prediction)
        confidence = float(np.max(prediction)) * 100
        disease = encoder.inverse_transform([index])[0]
        
        return disease, confidence
    except Exception as e:
        return "Error", 0

def detect_chickens(frame, max_chicks=10):
    if not YOLO_AVAILABLE:
        return []
    
    results = yolo_model(frame, verbose=False, conf=0.25)
    boxes = []
    
    for r in results:
        for box in r.boxes:
            class_id = int(box.cls[0])
            if class_id in [14, 16]:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                if (x2 - x1) > 30 and (y2 - y1) > 30:
                    boxes.append({'x1': x1, 'y1': y1, 'x2': x2, 'y2': y2})
                if len(boxes) >= max_chicks:
                    break
        if len(boxes) >= max_chicks:
            break
    
    return boxes

def process_frame(frame):
    h, w = frame.shape[:2]
    chicken_boxes = detect_chickens(frame)
    
    detections = []
    healthy_count = 0
    weak_count = 0
    unhealthy_count = 0
    
    for box in chicken_boxes:
        x1, y1, x2, y2 = box['x1'], box['y1'], box['x2'], box['y2']
        padding = 10
        x1 = max(0, x1 - padding)
        y1 = max(0, y1 - padding)
        x2 = min(w, x2 + padding)
        y2 = min(h, y2 + padding)
        
        roi = frame[y1:y2, x1:x2]
        disease, confidence = classify_chicken(roi)
        
        if confidence < 75:
            status = "weak"
            weak_count += 1
        elif disease == "Healthy":
            status = "healthy"
            healthy_count += 1
        else:
            status = "unhealthy"
            unhealthy_count += 1
        
        detections.append({
            'x1': x1, 'y1': y1, 'x2': x2, 'y2': y2,
            'disease': disease, 'confidence': round(confidence, 2), 'status': status
        })
    
    return {
        'detections': detections,
        'chick_count': len(detections),
        'healthy_count': healthy_count,
        'weak_count': weak_count,
        'unhealthy_count': unhealthy_count
    }

def save_detection_to_db(result, image_data=None):
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    if result['unhealthy_count'] > 0:
        overall_status = 'unhealthy'
    elif result['weak_count'] > 0:
        overall_status = 'weak'
    else:
        overall_status = 'healthy'
    
    detections_json = json.dumps(result['detections'])
    
    cursor.execute('''
        INSERT INTO detection_logs (user_id, disease, confidence, status, chick_count, healthy_count, weak_count, unhealthy_count, details)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ''', (
        1,
        result['detections'][0]['disease'] if result['detections'] else 'None',
        result['detections'][0]['confidence'] if result['detections'] else 0,
        overall_status,
        result['chick_count'],
        result['healthy_count'],
        result['weak_count'],
        result['unhealthy_count'],
        detections_json
    ))
    
    detection_id = cursor.lastrowid
    
    if image_data is not None:
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        snapshot_dir = os.path.join(SCRIPT_DIR, "scans")
        os.makedirs(snapshot_dir, exist_ok=True)
        
        image_path = os.path.join(snapshot_dir, f"snapshot_{timestamp}.jpg")
        cv2.imwrite(image_path, image_data)
        
        cursor.execute('''
            INSERT INTO camera_snapshots (user_id, image_url, detection_summary)
            VALUES (?, ?, ?)
        ''', (
            1,
            image_path,
            json.dumps({'status': overall_status, 'chick_count': result['chick_count']})
        ))
    
    conn.commit()
    conn.close()
    return detection_id

# ==========================
# API ENDPOINTS
# ==========================

@app.route('/api/detect', methods=['POST'])
def detect():
    try:
        if 'image' in request.files:
            file = request.files['image']
            img_bytes = file.read()
            nparr = np.frombuffer(img_bytes, np.uint8)
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        elif request.json and 'image_base64' in request.json:
            image_data = request.json['image_base64']
            if ',' in image_data:
                image_data = image_data.split(',')[1]
            img_bytes = base64.b64decode(image_data)
            nparr = np.frombuffer(img_bytes, np.uint8)
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        else:
            return jsonify({'error': 'No image provided'}), 400
        
        if frame is None:
            return jsonify({'error': 'Invalid image'}), 400
        
        result = process_frame(frame)
        save_detection_to_db(result, frame)
        
        return jsonify({
            'success': True,
            'timestamp': datetime.now().isoformat(),
            'chick_count': result['chick_count'],
            'healthy_count': result['healthy_count'],
            'weak_count': result['weak_count'],
            'unhealthy_count': result['unhealthy_count'],
            'detections': result['detections']
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/detect_stream', methods=['POST'])
def detect_stream():
    try:
        if 'image' in request.files:
            file = request.files['image']
            img_bytes = file.read()
            nparr = np.frombuffer(img_bytes, np.uint8)
            frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        else:
            return jsonify({'error': 'No image provided'}), 400
        
        if frame is None:
            return jsonify({'error': 'Invalid image'}), 400
        
        result = process_frame(frame)
        
        for det in result['detections']:
            x1, y1, x2, y2 = det['x1'], det['y1'], det['x2'], det['y2']
            disease = det['disease']
            confidence = det['confidence']
            
            color = DISEASE_COLORS.get(disease, (0, 0, 255))
            if confidence < 75:
                color = (255, 255, 0)
            
            cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
            label = f"{disease} {confidence:.1f}%"
            cv2.putText(frame, label, (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
        
        _, buffer = cv2.imencode('.jpg', frame)
        img_base64 = base64.b64encode(buffer).decode('utf-8')
        
        return jsonify({
            'success': True,
            'image_base64': img_base64,
            'chick_count': result['chick_count'],
            'healthy_count': result['healthy_count'],
            'weak_count': result['weak_count'],
            'unhealthy_count': result['unhealthy_count'],
            'detections': result['detections']
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/detections', methods=['GET'])
def get_detections():
    try:
        limit = int(request.args.get('limit', 20))
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        cursor.execute('SELECT * FROM detection_logs ORDER BY timestamp DESC LIMIT ?', (limit,))
        rows = cursor.fetchall()
        detections = [dict(row) for row in rows]
        conn.close()
        
        return jsonify({'success': True, 'detections': detections})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'ok',
        'models_loaded': True,
        'yolo_available': YOLO_AVAILABLE,
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    print("\n" + "="*60)
    print("🐔 DISEASE DETECTION API SERVER")
    print("="*60)
    print(f"📍 Running on: http://localhost:5000")
    print("="*60 + "\n")
    
    app.run(host='0.0.0.0', port=5000, debug=True, threaded=True)