import cv2
import numpy as np
import tensorflow as tf
import pickle
import os
from datetime import datetime
import time
from ultralytics import YOLO
from collections import deque

# ==========================
# LOAD MODELS
# ==========================

print("🔄 Loading models...")

# Load YOLO for chicken detection
yolo_model = YOLO('yolov8n.pt')
print("✅ YOLO model loaded!")

# Load disease classification model
disease_model = tf.keras.models.load_model("chicken_disease_model.keras")
print("✅ Disease model loaded!")

with open("label_encoder.pkl", "rb") as f:
    encoder = pickle.load(f)

print(f"📋 Classes: {encoder.classes_}")

IMAGE_SIZE = 128

# Create scans folder
if not os.path.exists("scans"):
    os.makedirs("scans")

# ==========================
# CAMERA
# ==========================

cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)

if not cap.isOpened():
    print("❌ Cannot open webcam")
    exit()

print("\n" + "="*60)
print("🐔 MULTI-CHICKEN DISEASE DETECTION WITH TRACKING")
print("="*60)
print("📌 Tracks each chicken even when moving")
print("📌 Stable detection with smoothing")
print("📌 SPACE = Save Image")
print("📌 Q = Exit")
print("="*60 + "\n")

# ==========================
# TRACKING CLASS
# ==========================

class ChickenTracker:
    def __init__(self, max_history=10):
        self.tracked_chickens = {}  # id -> {box, disease, confidence, history}
        self.next_id = 0
        self.max_history = max_history
        self.lost_threshold = 15  # Frames to wait before removing
        
    def update(self, detections, frame):
        """Update tracker with new detections"""
        
        # Match detections to existing tracks
        matched_ids = set()
        
        # Simple matching: nearest box
        for det in detections:
            det_box = (det['x1'], det['y1'], det['x2'], det['y2'])
            det_center = ((det_box[0] + det_box[2]) // 2, 
                         (det_box[1] + det_box[3]) // 2)
            
            # Find closest existing track
            best_id = None
            best_distance = float('inf')
            
            for chick_id, track in self.tracked_chickens.items():
                if chick_id in matched_ids:
                    continue
                    
                track_box = track['box']
                track_center = ((track_box[0] + track_box[2]) // 2, 
                               (track_box[1] + track_box[3]) // 2)
                
                distance = np.sqrt((det_center[0] - track_center[0])**2 + 
                                  (det_center[1] - track_center[1])**2)
                
                if distance < best_distance and distance < 200:  # Max distance for matching
                    best_distance = distance
                    best_id = chick_id
            
            if best_id is not None:
                # Update existing track
                matched_ids.add(best_id)
                track = self.tracked_chickens[best_id]
                track['box'] = det_box
                track['disease'] = det['disease']
                track['confidence'] = det['confidence']
                track['lost_frames'] = 0
                track['history'].append(det_box)
                if len(track['history']) > self.max_history:
                    track['history'].popleft()
            else:
                # Create new track
                self.tracked_chickens[self.next_id] = {
                    'id': self.next_id,
                    'box': det_box,
                    'disease': det['disease'],
                    'confidence': det['confidence'],
                    'lost_frames': 0,
                    'history': deque([det_box], maxlen=self.max_history)
                }
                self.next_id += 1
        
        # Update lost frames for unmatched tracks
        for chick_id in list(self.tracked_chickens.keys()):
            if chick_id not in matched_ids:
                self.tracked_chickens[chick_id]['lost_frames'] += 1
                # Remove if lost for too long
                if self.tracked_chickens[chick_id]['lost_frames'] > self.lost_threshold:
                    del self.tracked_chickens[chick_id]
        
        return self.tracked_chickens
    
    def get_smoothed_box(self, track):
        """Get smoothed bounding box from history"""
        if len(track['history']) > 0:
            boxes = list(track['history'])
            if len(boxes) >= 3:
                # Average last 3 positions for smoothing
                avg_x1 = int(np.mean([b[0] for b in boxes[-3:]]))
                avg_y1 = int(np.mean([b[1] for b in boxes[-3:]]))
                avg_x2 = int(np.mean([b[2] for b in boxes[-3:]]))
                avg_y2 = int(np.mean([b[3] for b in boxes[-3:]]))
                return (avg_x1, avg_y1, avg_x2, avg_y2)
        return track['box']

# ==========================
# HELPER FUNCTIONS
# ==========================

def classify_chicken(roi):
    """Classify a single chicken ROI"""
    if roi.size == 0 or roi.shape[0] < 10 or roi.shape[1] < 10:
        return "No Chicken", 0
    
    try:
        img = cv2.resize(roi, (IMAGE_SIZE, IMAGE_SIZE))
        img = img.astype("float32") / 255.0
        img = np.expand_dims(img, axis=0)
        
        prediction = disease_model.predict(img, verbose=0)
        index = np.argmax(prediction)
        confidence = float(np.max(prediction)) * 100
        disease = encoder.inverse_transform([index])[0]
        
        return disease, confidence
    except Exception as e:
        return "Error", 0

def draw_chick_box(frame, track, chick_id):
    """Draw bounding box with disease info for each chick with smoothing"""
    
    # Get smoothed box
    x1, y1, x2, y2 = tracker.get_smoothed_box(track)
    disease = track['disease']
    confidence = track['confidence']
    
    # Color mapping
    disease_colors = {
        "Healthy": (0, 255, 0),
        "Coccidiosis": (0, 165, 255),
        "New Castle Disease": (255, 0, 255),
        "Salmonella": (0, 0, 255)
    }
    
    if disease in disease_colors:
        color = disease_colors[disease]
    elif disease == "No Chicken":
        color = (128, 128, 128)
    else:
        color = (0, 0, 255)
    
    # Draw box with glow effect
    cv2.rectangle(frame, (x1, y1), (x2, y2), color, 3)
    
    # Glowing effect
    for i in range(1, 3):
        cv2.rectangle(frame, (x1-i, y1-i), (x2+i, y2+i), color, 1)
    
    # Chick ID
    cv2.putText(frame, f"#{chick_id}", (x1 + 5, y1 - 35),
                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
    
    # Disease label
    label = f"{disease}"
    if confidence > 0:
        label += f" ({confidence:.1f}%)"
    
    (tw, th), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.6, 2)
    cv2.rectangle(frame, (x1, y1 - 28), (x1 + tw + 10, y1 - 5), color, -1)
    cv2.putText(frame, label, (x1 + 5, y1 - 8),
                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
    
    # Confidence bar
    bar_width = x2 - x1 - 20
    bar_x = x1 + 10
    bar_y = y2 + 5
    bar_h = 6
    
    cv2.rectangle(frame, (bar_x, bar_y), (bar_x + bar_width, bar_y + bar_h),
                  (50, 50, 50), -1)
    
    fill_w = int((confidence / 100) * bar_width)
    cv2.rectangle(frame, (bar_x, bar_y), (bar_x + fill_w, bar_y + bar_h),
                  color, -1)
    
    # Tracking indicator
    if track['lost_frames'] > 0:
        cv2.putText(frame, f"Tracking...", (x1, y2 + 25),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.4, (255, 255, 0), 1)
    
    return (x1, y1, x2, y2)  # Return smoothed box

def detect_chickens(frame, max_chicks=8):
    """Detect chickens using YOLO"""
    results = yolo_model(frame, verbose=False, conf=0.25)
    boxes = []
    
    for r in results:
        for box in r.boxes:
            class_id = int(box.cls[0])
            confidence = float(box.conf[0])
            
            # COCO class 14 = bird, 16 = chicken
            if class_id in [14, 16]:
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                
                # Filter by size
                if (x2 - x1) > 30 and (y2 - y1) > 30:
                    boxes.append({
                        'x1': x1,
                        'y1': y1,
                        'x2': x2,
                        'y2': y2,
                        'detection_confidence': confidence
                    })
                
                if len(boxes) >= max_chicks:
                    break
        if len(boxes) >= max_chicks:
            break
    
    return boxes

# ==========================
# INITIALIZE TRACKER
# ==========================

tracker = ChickenTracker(max_history=10)

# ==========================
# MAIN LOOP
# ==========================

print("🎥 Starting detection with tracking...\n")

# Variables
scan_count = 0
fps = 0
fps_start_time = time.time()
fps_frame_count = 0
frame_skip = 0
DETECTION_INTERVAL = 2  # Detect every 2 frames

while True:
    ret, frame = cap.read()
    if not ret:
        break
    
    h, w = frame.shape[:2]
    fps_frame_count += 1
    
    # FPS
    if time.time() - fps_start_time >= 1:
        fps = fps_frame_count
        fps_frame_count = 0
        fps_start_time = time.time()
    
    # ==========================
    # DETECT & TRACK CHICKENS
    # ==========================
    
    frame_skip += 1
    if frame_skip >= DETECTION_INTERVAL:
        frame_skip = 0
        
        # Detect chickens
        chicken_boxes = detect_chickens(frame, max_chicks=8)
        
        # Classify each chicken
        detections = []
        for box in chicken_boxes:
            x1, y1, x2, y2 = box['x1'], box['y1'], box['x2'], box['y2']
            
            # Expand box slightly
            padding = 10
            x1 = max(0, x1 - padding)
            y1 = max(0, y1 - padding)
            x2 = min(w, x2 + padding)
            y2 = min(h, y2 + padding)
            
            roi = frame[y1:y2, x1:x2]
            disease, confidence = classify_chicken(roi)
            
            detections.append({
                'x1': x1,
                'y1': y1,
                'x2': x2,
                'y2': y2,
                'disease': disease,
                'confidence': confidence
            })
        
        # Update tracker
        tracker.update(detections, frame)
    
    # ==========================
    # DRAW TRACKED CHICKENS
    # ==========================
    
    tracked_boxes = []
    for chick_id, track in tracker.tracked_chickens.items():
        box = draw_chick_box(frame, track, chick_id)
        tracked_boxes.append({
            'id': chick_id,
            'box': box,
            'disease': track['disease'],
            'confidence': track['confidence']
        })
    
    # ==========================
    # SUMMARY PANEL (Bottom)
    # ==========================
    
    panel_y = h - 80
    cv2.rectangle(frame, (10, panel_y), (w - 10, h - 5),
                  (0, 0, 0), -1)
    cv2.rectangle(frame, (10, panel_y), (w - 10, h - 5),
                  (100, 100, 100), 1)
    
    healthy_count = sum(1 for t in tracker.tracked_chickens.values() 
                       if t['disease'] == 'Healthy')
    sick_count = len(tracker.tracked_chickens) - healthy_count
    
    summary = f"🐔 Tracked: {len(tracker.tracked_chickens)}  |  ✅ Healthy: {healthy_count}  |  🚨 Sick: {sick_count}  |  💾 Scans: {scan_count}  |  ⚡ FPS: {fps}"
    cv2.putText(frame, summary, (20, panel_y + 35),
                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
    
    controls = "SPACE = Save  |  Q = Quit  |  Tracking Active"
    cv2.putText(frame, controls, (20, panel_y + 60),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
    
    # ==========================
    # STATUS (Top Left)
    # ==========================
    
    if len(tracker.tracked_chickens) > 0:
        if sick_count > 0:
            status_text = f"🟡 {sick_count} CHICKEN(S) WITH DISEASE"
            status_color = (0, 0, 255)
        else:
            status_text = f"🟢 ALL {len(tracker.tracked_chickens)} CHICKENS HEALTHY"
            status_color = (0, 255, 0)
    else:
        status_text = "⏳ NO CHICKEN DETECTED"
        status_color = (255, 255, 0)
    
    cv2.putText(frame, status_text, (10, 40),
                cv2.FONT_HERSHEY_SIMPLEX, 0.8, status_color, 2)
    
    # ==========================
    # LEGEND (Top Right)
    # ==========================
    
    legend_x = w - 160
    legend_y = 10
    cv2.rectangle(frame, (legend_x, legend_y), (w - 10, legend_y + 110),
                  (0, 0, 0), -1)
    cv2.rectangle(frame, (legend_x, legend_y), (w - 10, legend_y + 110),
                  (100, 100, 100), 1)
    
    cv2.putText(frame, "LEGEND", (legend_x + 5, legend_y + 20),
                cv2.FONT_HERSHEY_SIMPLEX, 0.4, (255, 255, 255), 1)
    
    disease_colors = {
        "Healthy": (0, 255, 0),
        "Coccidiosis": (0, 165, 255),
        "New Castle": (255, 0, 255),
        "Salmonella": (0, 0, 255)
    }
    
    for i, (name, color) in enumerate(disease_colors.items()):
        y_pos = legend_y + 35 + (i * 18)
        cv2.rectangle(frame, (legend_x + 5, y_pos - 3), (legend_x + 20, y_pos + 10), color, -1)
        cv2.putText(frame, name, (legend_x + 28, y_pos + 8),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.35, (200, 200, 200), 1)
    
    # Show FPS counter
    cv2.putText(frame, f"FPS: {fps}", (w - 70, h - 65),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)
    
    # ==========================
    # SHOW FRAME
    # ==========================
    
    cv2.imshow("🐔 Multi-Chicken Tracking & Detection", frame)
    
    key = cv2.waitKey(1) & 0xFF
    
    # ==========================
    # SAVE IMAGE (SPACE)
    # ==========================
    
    if key == ord(' '):
        scan_count += 1
        filename = datetime.now().strftime("%Y%m%d_%H%M%S") + ".jpg"
        
        # Save full frame
        full_path = os.path.join("scans", filename)
        cv2.imwrite(full_path, frame)
        
        # Save individual chick ROIs
        for chick_id, track in tracker.tracked_chickens.items():
            x1, y1, x2, y2 = tracker.get_smoothed_box(track)
            roi = frame[y1:y2, x1:x2]
            if roi.size > 0:
                roi_filename = f"chick_{chick_id}_{filename}"
                roi_path = os.path.join("scans", roi_filename)
                cv2.imwrite(roi_path, roi)
        
        # Console log
        print("=" * 60)
        print(f"🕐 {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"📊 Summary: {len(tracker.tracked_chickens)} chicks tracked")
        for chick_id, track in tracker.tracked_chickens.items():
            print(f"  Chick #{chick_id}: {track['disease']} ({track['confidence']:.1f}%)")
        print(f"💾 Saved: {filename}")
        print(f"📁 Total scans: {scan_count}")
        print("=" * 60)
    
    if key == ord("q"):
        break

cap.release()
cv2.destroyAllWindows()

print("\n" + "="*60)
print(f"✅ Session ended. {scan_count} scans saved in 'scans' folder.")
print("="*60)