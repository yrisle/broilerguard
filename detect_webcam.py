import cv2
import numpy as np
import tensorflow as tf
import pickle
import os
from datetime import datetime
import time

# ==========================
# LOAD MODEL
# ==========================

print("Loading model...")
model = tf.keras.models.load_model("chicken_disease_model.keras")
print("Model loaded!")

with open("label_encoder.pkl", "rb") as f:
    encoder = pickle.load(f)
print("Label encoder loaded!")
print(f"Available classes: {encoder.classes_}")

IMAGE_SIZE = 128

# Create scans folder
if not os.path.exists("scans"):
    os.makedirs("scans")

# ==========================
# CAMERA
# ==========================

cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)

if not cap.isOpened():
    print("Cannot open webcam")
    exit()

print("\n" + "="*60)
print("AI POULTRY DISEASE DETECTION SYSTEM")
print("="*60)
print("REAL-TIME DETECTION (Continuous)")
print("SPACE = Save Image")
print("Q = Exit")
print("="*60 + "\n")

# Initialize variables
last_prediction = "Waiting..."
last_confidence = 0
status = "READY"
status_color = (255, 255, 255)
scan_count = 0

# Color mapping for diseases
disease_colors = {
    "Healthy": (0, 255, 0),
    "Coccidiosis": (0, 165, 255),
    "New Castle Disease": (255, 0, 255),
    "Salmonella": (0, 0, 255)
}

# For FPS
fps_start_time = time.time()
fps_frame_count = 0
fps = 0

# For continuous detection (skip frames for performance)
frame_skip = 0
DETECTION_INTERVAL = 3  # Detect every 3 frames

while True:

    ret, frame = cap.read()

    if not ret:
        break

    h, w = frame.shape[:2]
    fps_frame_count += 1

    # Calculate FPS
    if time.time() - fps_start_time >= 1:
        fps = fps_frame_count
        fps_frame_count = 0
        fps_start_time = time.time()

    # ==========================
    # GUIDE BOX
    # ==========================

    box_size = 350
    x1 = w//2 - box_size//2
    y1 = h//2 - box_size//2
    x2 = w//2 + box_size//2
    y2 = h//2 + box_size//2

    # Light overlay outside the box (para kita pa rin ang background)
    overlay = frame.copy()
    cv2.rectangle(overlay, (0, 0), (w, y1), (0, 0, 0), -1)
    cv2.rectangle(overlay, (0, y2), (w, h), (0, 0, 0), -1)
    cv2.rectangle(overlay, (0, y1), (x1, y2), (0, 0, 0), -1)
    cv2.rectangle(overlay, (x2, y1), (w, y2), (0, 0, 0), -1)
    frame = cv2.addWeighted(overlay, 0.2, frame, 0.8, 0)

    # Green guide box
    cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 255, 0), 3)

    # Corner markers
    corner_length = 25
    cv2.line(frame, (x1, y1), (x1 + corner_length, y1), (0, 255, 0), 3)
    cv2.line(frame, (x1, y1), (x1, y1 + corner_length), (0, 255, 0), 3)
    cv2.line(frame, (x2, y1), (x2 - corner_length, y1), (0, 255, 0), 3)
    cv2.line(frame, (x2, y1), (x2, y1 + corner_length), (0, 255, 0), 3)
    cv2.line(frame, (x1, y2), (x1 + corner_length, y2), (0, 255, 0), 3)
    cv2.line(frame, (x1, y2), (x1, y2 - corner_length), (0, 255, 0), 3)
    cv2.line(frame, (x2, y2), (x2 - corner_length, y2), (0, 255, 0), 3)
    cv2.line(frame, (x2, y2), (x2, y2 - corner_length), (0, 255, 0), 3)

    # Label above box (simple lang)
    cv2.putText(frame, "PLACE CHICK HERE", 
                (x1 + 20, y1 - 15),
                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)

    # ==========================
    # CONTINUOUS DETECTION
    # ==========================

    frame_skip += 1
    if frame_skip >= DETECTION_INTERVAL:
        frame_skip = 0
        
        roi = frame[y1:y2, x1:x2]
        
        if roi.size > 0 and roi.shape[0] > 10 and roi.shape[1] > 10:
            # Preprocess
            img = cv2.resize(roi, (IMAGE_SIZE, IMAGE_SIZE))
            img = img.astype("float32") / 255.0
            img = np.expand_dims(img, axis=0)

            # Predict
            prediction = model.predict(img, verbose=0)
            index = np.argmax(prediction)
            confidence = float(np.max(prediction)) * 100
            disease = encoder.inverse_transform([index])[0]

            # Update results
            if confidence < 75:
                last_prediction = "Low Confidence"
                last_confidence = confidence
                status = "PLACE CHICK PROPERLY"
                status_color = (0, 255, 255)
            else:
                last_prediction = disease
                last_confidence = confidence
                
                if disease == "Healthy":
                    status = "HEALTHY"
                    status_color = (0, 255, 0)
                else:
                    status = f"{disease.upper()}"
                    status_color = (0, 0, 255)

    # ==========================
    # DISPLAY RESULTS - TRANSPARENT PANEL (para kita ang box)
    # ==========================

    # Small transparent panel sa bottom-left (hindi natatakpan ang box)
    panel_x, panel_y = 10, h - 180
    panel_w, panel_h = 300, 170
    
    # Transparent background
    overlay2 = frame.copy()
    cv2.rectangle(overlay2, (panel_x, panel_y), (panel_x + panel_w, panel_y + panel_h),
                  (0, 0, 0), -1)
    frame = cv2.addWeighted(overlay2, 0.6, frame, 0.4, 0)
    cv2.rectangle(frame, (panel_x, panel_y), (panel_x + panel_w, panel_y + panel_h),
                  (0, 255, 0), 2)

    # Results
    now = datetime.now()
    cv2.putText(frame, f"🐔 {now.strftime('%H:%M:%S')}", 
                (panel_x + 10, panel_y + 30),
                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1)

    # Disease name with color
    if last_prediction in disease_colors:
        color = disease_colors[last_prediction]
    elif last_prediction == "Waiting..." or last_prediction == "Low Confidence":
        color = (255, 255, 0)
    else:
        color = (0, 0, 255)

    cv2.putText(frame, f"Disease: {last_prediction}", 
                (panel_x + 10, panel_y + 60),
                cv2.FONT_HERSHEY_SIMPLEX, 0.7, color, 2)

    # Confidence bar (small)
    bar_x = panel_x + 10
    bar_y = panel_y + 75
    bar_w = panel_w - 20
    bar_h = 12

    cv2.rectangle(frame, (bar_x, bar_y), (bar_x + bar_w, bar_y + bar_h),
                  (50, 50, 50), -1)
    
    fill_w = int((last_confidence / 100) * bar_w)
    if last_confidence > 80:
        bar_color = (0, 255, 0)
    elif last_confidence > 50:
        bar_color = (0, 255, 255)
    else:
        bar_color = (0, 0, 255)
    
    cv2.rectangle(frame, (bar_x, bar_y), (bar_x + fill_w, bar_y + bar_h),
                  bar_color, -1)
    
    cv2.putText(frame, f"{last_confidence:.1f}%", 
                (bar_x + bar_w - 50, bar_y + 10),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1)

    # Status
    cv2.putText(frame, f"Status: {status}", 
                (panel_x + 10, panel_y + 110),
                cv2.FONT_HERSHEY_SIMPLEX, 0.6, status_color, 2)

    # Scans saved
    cv2.putText(frame, f"Scans: {scan_count} | FPS: {fps}", 
                (panel_x + 10, panel_y + 135),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)

    # ==========================
    # LEGEND - Top Right (maliit lang)
    # ==========================

    legend_x = w - 130
    legend_y = 10
    cv2.rectangle(frame, (legend_x, legend_y), (w - 10, legend_y + 110),
                  (0, 0, 0, 0.6), -1)
    cv2.rectangle(frame, (legend_x, legend_y), (w - 10, legend_y + 110),
                  (100, 100, 100), 1)
    
    cv2.putText(frame, "LEGEND", (legend_x + 5, legend_y + 20),
                cv2.FONT_HERSHEY_SIMPLEX, 0.4, (255, 255, 255), 1)
    
    legend_items = [
        ("Healthy", (0, 255, 0)),
        ("Coccidiosis", (0, 165, 255)),
        ("New Castle", (255, 0, 255)),
        ("Salmonella", (0, 0, 255))
    ]
    
    for i, (name, color) in enumerate(legend_items):
        y_pos = legend_y + 35 + (i * 18)
        cv2.rectangle(frame, (legend_x + 5, y_pos - 3), (legend_x + 20, y_pos + 10), color, -1)
        cv2.putText(frame, name, (legend_x + 28, y_pos + 8),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.35, (200, 200, 200), 1)

    # ==========================
    # FOOTER - Controls
    # ==========================

    cv2.rectangle(frame, (10, h - 30), (w - 10, h - 5),
                  (0, 0, 0), -1)
    cv2.rectangle(frame, (10, h - 30), (w - 10, h - 5),
                  (100, 100, 100), 1)

    controls = "SPACE = Save  |  Q = Quit  |  Live Detection"
    (tw, th), _ = cv2.getTextSize(controls, cv2.FONT_HERSHEY_SIMPLEX, 0.5, 1)
    cv2.putText(frame, controls,
                (w//2 - tw//2, h - 10),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)

    cv2.imshow("Chicken Disease Detection", frame)

    key = cv2.waitKey(1) & 0xFF

    # ==========================
    # SAVE - ONLY when SPACE is pressed
    # ==========================

    if key == ord(' '):
        scan_count += 1
        filename = datetime.now().strftime("%Y%m%d_%H%M%S") + ".jpg"
        
        # Save full frame
        full_path = os.path.join("scans", filename)
        cv2.imwrite(full_path, frame)
        
        # Save ROI separately
        roi = frame[y1:y2, x1:x2]
        if roi.size > 0:
            roi_filename = f"ROI_{filename}"
            roi_path = os.path.join("scans", roi_filename)
            cv2.imwrite(roi_path, roi)

        # Console log
        print("=" * 60)
        print(f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"Disease    : {last_prediction}")
        print(f"Confidence : {last_confidence:.2f}%")
        print(f"Status     : {status}")
        print(f"Saved      : {filename}")
        print(f"Total      : {scan_count} scans")
        print("=" * 60)

    if key == ord("q"):
        break

cap.release()
cv2.destroyAllWindows()

print("\n" + "="*60)
print(f" Session ended. {scan_count} scans saved in 'scans' folder.")
print("="*60)