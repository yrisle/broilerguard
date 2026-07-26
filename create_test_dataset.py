# create_test_dataset.py - Create synthetic test images
import os
import cv2
import numpy as np
import pandas as pd

output_dir = "C:\\xampp\\htdocs\\BG"
train_dir = os.path.join(output_dir, "train")
os.makedirs(train_dir, exist_ok=True)

# Read the CSV to know what images to create
csv_path = os.path.join(output_dir, "train_data.csv")
if os.path.exists(csv_path):
    df = pd.read_csv(csv_path)
else:
    print("❌ train_data.csv not found!")
    exit(1)

print(f"📊 Creating synthetic images for {len(df)} records...")

classes = ['Healthy', 'Coccidiosis', 'New Castle Disease', 'Salmonella']
colors = {
    'Healthy': (100, 200, 100),
    'Coccidiosis': (200, 150, 50),
    'New Castle Disease': (150, 50, 200),
    'Salmonella': (200, 50, 50)
}

created = 0
for i, row in df.iterrows():
    image_name = row['images']
    label = row['label']
    
    # Create a synthetic image
    img = np.random.randint(0, 255, (128, 128, 3), dtype=np.uint8)
    
    # Add some pattern based on class
    color = colors.get(label, (128, 128, 128))
    cv2.rectangle(img, (10, 10), (118, 118), color, -1)
    cv2.putText(img, label[:4], (30, 70), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1)
    
    # Add some noise
    noise = np.random.randint(0, 30, (128, 128, 3), dtype=np.uint8)
    img = cv2.addWeighted(img, 0.9, noise, 0.1, 0)
    
    # Save to train folder
    filepath = os.path.join(train_dir, image_name)
    cv2.imwrite(filepath, img)
    created += 1
    
    if created % 100 == 0:
        print(f"  Created {created} images...")

print(f"✅ Created {created} synthetic images in: {train_dir}")
print("Now run: python train_model.py")