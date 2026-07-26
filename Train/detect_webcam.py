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

model = tf.keras.models.load_model("chicken_disease_model.keras")

with open("label_encoder.pkl", "rb") as f:
    encoder = pickle.load(f)

IMAGE_SIZE = 128

# Create scans folder
if not os.path.exists("scans"):
    os.makedirs("scans")

# ==========================
# CAMERA
# ==========================

cap = cv2.VideoCapture(0)

if not cap.isOpened():
    print("Cannot open webcam")
    exit()

print("====================================")
print(" AI Poultry Disease Detection")
print(" SPACE = Scan")
print(" Q = Exit")
print("====================================")

last_prediction = "Waiting..."
last_confidence = 0
status = "READY"

last_scan = time.time()
SCAN_INTERVAL = 3  # scan every 3 seconds

while True:

    ret, frame = cap.read()

    if not ret:
        break

    h, w = frame.shape[:2]

    # ==========================
    # GUIDE BOX
    # ==========================

    box_size = 300

    x1 = w//2 - box_size//2
    y1 = h//2 - box_size//2

    x2 = w//2 + box_size//2
    y2 = h//2 + box_size//2

    cv2.rectangle(frame, (x1,y1), (x2,y2), (0,255,0), 3)

    cv2.putText(
        frame,
        "PLACE CHICK HERE",
        (x1, y1-10),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0,255,0),
        2
    )

    # ==========================
    # RIGHT PANEL
    # ==========================

    cv2.rectangle(frame,(10,10),(360,220),(40,40,40),-1)

    now = datetime.now()

    cv2.putText(frame,
                "AI Poultry Monitoring",
                (20,40),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.8,
                (255,255,255),
                2)

    cv2.putText(frame,
                now.strftime("%Y-%m-%d"),
                (20,70),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                (255,255,0),
                2)

    cv2.putText(frame,
                now.strftime("%H:%M:%S"),
                (20,95),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                (255,255,0),
                2)

    if last_prediction == "Healthy":
        color = (0,255,0)
    elif last_prediction == "Waiting...":
        color = (255,255,255)
    else:
        color = (0,0,255)

    cv2.putText(frame,
                f"Disease : {last_prediction}",
                (20,130),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.65,
                color,
                2)

    cv2.putText(frame,
                f"Confidence : {last_confidence:.2f}%",
                (20,160),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.65,
                (255,255,255),
                2)

    cv2.putText(frame,
                f"Status : {status}",
                (20,190),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.65,
                color,
                2)

    cv2.putText(frame,
                "SPACE = Scan | Q = Exit",
                (10,h-15),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                (255,255,255),
                2)

    cv2.imshow("Chicken Disease Detection", frame)

    key = cv2.waitKey(1) & 0xFF

    # ==========================
    # SCAN
    # ==========================

    if time.time() - last_scan >= SCAN_INTERVAL:

       last_scan = time.time()

    roi = frame[y1:y2, x1:x2]

    img = cv2.resize(roi, (IMAGE_SIZE, IMAGE_SIZE))
    img = img.astype("float32") / 255.0
    img = np.expand_dims(img, axis=0)

    prediction = model.predict(img, verbose=0)

    index = np.argmax(prediction)
    confidence = float(np.max(prediction)) * 100
    disease = encoder.inverse_transform([index])[0]

    if confidence < 80:
        last_prediction = "Low Confidence"
        last_confidence = confidence
        status = "PLACE CHICK PROPERLY"
    else:
        last_prediction = disease
        last_confidence = confidence

        if disease == "Healthy":
            status = "HEALTHY"
        else:
            status = "DISEASE DETECTED"

    filename = datetime.now().strftime("%Y%m%d_%H%M%S") + ".jpg"
    cv2.imwrite(os.path.join("scans", filename), roi)

    print("=" * 40)
    print("Prediction :", last_prediction)
    print("Confidence :", round(last_confidence, 2), "%")
    print("Saved :", filename)
    print("=" * 40)

    # ==========================

    if key == ord("q"):
        break

cap.release()

cv2.destroyAllWindows()