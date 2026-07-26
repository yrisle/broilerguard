# train_model.py - Updated to search for images in subfolders
import os
import pandas as pd
import numpy as np
import cv2
import pickle

from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.utils import class_weight

from tensorflow.keras.utils import to_categorical
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import Conv2D, MaxPooling2D, Flatten, Dense, Dropout, BatchNormalization
from tensorflow.keras.preprocessing.image import ImageDataGenerator
from tensorflow.keras.callbacks import EarlyStopping, ReduceLROnPlateau

# ==========================
# PATHS - Search multiple locations
# ==========================

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
print(f"📁 Working directory: {SCRIPT_DIR}")

# Try multiple possible image locations
POSSIBLE_IMAGE_FOLDERS = [
    SCRIPT_DIR,  # Current directory
    os.path.join(SCRIPT_DIR, "train"),  # train subfolder
    os.path.join(SCRIPT_DIR, "images"),  # images subfolder
    os.path.join(SCRIPT_DIR, "dataset"),  # dataset subfolder
    os.path.join(SCRIPT_DIR, "data"),  # data subfolder
    os.path.join(SCRIPT_DIR, "archive"),  # archive subfolder
    os.path.join(SCRIPT_DIR, "..", "archive"),  # parent archive
    r"C:\Users\Admin\Downloads\archive\train",  # Original path
]

CSV_FILE = os.path.join(SCRIPT_DIR, "train_data.csv")

# ==========================
# CHECK IF FILES EXIST
# ==========================

print("\n🔍 Checking for required files...")
print("="*50)

if not os.path.exists(CSV_FILE):
    print(f"❌ CSV file not found: {CSV_FILE}")
    print("Please make sure train_data.csv is in the current directory.")
    exit(1)
else:
    print(f"✅ CSV file found: {CSV_FILE}")

# Find which folder has the images
IMAGE_FOLDER = os.path.join(SCRIPT_DIR, "train")
for folder in POSSIBLE_IMAGE_FOLDERS:
    if os.path.exists(folder):
        # Check if this folder has any jpg files
        jpg_files = [f for f in os.listdir(folder) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
        if len(jpg_files) > 0:
            IMAGE_FOLDER = folder
            print(f"✅ Found {len(jpg_files)} images in: {folder}")
            break

if IMAGE_FOLDER is None:
    print("\n❌ No image folder found! Please check your dataset.")
    print("Looking in these locations:")
    for folder in POSSIBLE_IMAGE_FOLDERS:
        print(f"  - {folder}")
    exit(1)

print("="*50)

# ==========================
# LOAD CSV
# ==========================

print("\n📊 Loading CSV data...")
df = pd.read_csv(CSV_FILE)
print(f"✅ Loaded {len(df)} records")
print(df.head())

# ==========================
# LOAD IMAGES
# ==========================

print("\n🖼️ Loading images...")
print(f"📂 Looking for images in: {IMAGE_FOLDER}")

images = []
labels = []
skipped = 0
missing_list = []
found_count = 0

IMAGE_SIZE = 128

# Get list of all image files in the folder
image_files = set(os.listdir(IMAGE_FOLDER))

for index, row in df.iterrows():
    image_name = row["images"]
    label = row["label"]
    
    # Try different variations of the filename
    possible_names = [
        image_name,
        image_name.lower(),
        image_name.replace('.jpg', '.jpeg'),
        image_name.replace('.jpg', '.png'),
        image_name.replace('.jpeg', '.jpg'),
    ]
    
    found = False
    for name in possible_names:
        image_path = os.path.join(IMAGE_FOLDER, name)
        if os.path.exists(image_path):
            img = cv2.imread(image_path)
            if img is not None:
                img = cv2.resize(img, (IMAGE_SIZE, IMAGE_SIZE))
                img = img / 255.0
                images.append(img)
                labels.append(label)
                found_count += 1
                found = True
                break
    
    if not found:
        skipped += 1
        if len(missing_list) < 10:
            missing_list.append(image_name)

if skipped > 0:
    print(f"⚠️ Skipped {skipped} images (not found)")

images = np.array(images, dtype=np.float32)
labels = np.array(labels)

print(f"✅ Loaded {len(images)} images successfully")
print(f"📊 Image shape: {images.shape}")

if len(images) == 0:
    print("\n❌ No images loaded!")
    print("\n📋 First 10 missing images:")
    for f in missing_list[:10]:
        print(f"  - {f}")
    print(f"\n📁 Images found in folder: {IMAGE_FOLDER}")
    print(f"📁 Total files in folder: {len(os.listdir(IMAGE_FOLDER))}")
    print(f"📁 JPG files in folder: {len([f for f in os.listdir(IMAGE_FOLDER) if f.lower().endswith('.jpg')])}")
    print("\n💡 Make sure the image filenames in train_data.csv match the actual filenames.")
    print("   The CSV expects filenames like: cocci.1048.jpg")
    print("   Check if your images have the same naming pattern.")
    exit(1)

# ==========================
# LABEL ENCODER
# ==========================

print("\n🏷️ Encoding labels...")
encoder = LabelEncoder()
labels_encoded = encoder.fit_transform(labels)
labels_categorical = to_categorical(labels_encoded)

print(f"📋 Classes: {encoder.classes_}")

# Save label encoder
with open("label_encoder.pkl", "wb") as f:
    pickle.dump(encoder, f)
print("✅ Label encoder saved to label_encoder.pkl")

# ==========================
# TRAIN TEST SPLIT
# ==========================

print("\n📊 Splitting data...")
X_train, X_test, y_train, y_test = train_test_split(
    images,
    labels_categorical,
    test_size=0.2,
    random_state=42,
    stratify=labels_encoded
)

print(f"✅ Training set: {X_train.shape[0]} images")
print(f"✅ Test set: {X_test.shape[0]} images")

# ==========================
# DATA AUGMENTATION
# ==========================

print("\n🔄 Setting up data augmentation...")
datagen = ImageDataGenerator(
    rotation_range=20,
    width_shift_range=0.2,
    height_shift_range=0.2,
    horizontal_flip=True,
    zoom_range=0.2,
    fill_mode='nearest'
)

# ==========================
# CNN MODEL
# ==========================

print("\n🧠 Building CNN model...")
model = Sequential()

# First Conv Block
model.add(Conv2D(32, (3, 3), activation="relu", input_shape=(128, 128, 3)))
model.add(BatchNormalization())
model.add(MaxPooling2D(pool_size=(2, 2)))
model.add(Dropout(0.25))

# Second Conv Block
model.add(Conv2D(64, (3, 3), activation="relu"))
model.add(BatchNormalization())
model.add(MaxPooling2D(pool_size=(2, 2)))
model.add(Dropout(0.25))

# Third Conv Block
model.add(Conv2D(128, (3, 3), activation="relu"))
model.add(BatchNormalization())
model.add(MaxPooling2D(pool_size=(2, 2)))
model.add(Dropout(0.25))

# Fourth Conv Block
model.add(Conv2D(256, (3, 3), activation="relu"))
model.add(BatchNormalization())
model.add(MaxPooling2D(pool_size=(2, 2)))
model.add(Dropout(0.25))

# Flatten and Dense Layers
model.add(Flatten())
model.add(Dense(512, activation="relu"))
model.add(BatchNormalization())
model.add(Dropout(0.5))
model.add(Dense(256, activation="relu"))
model.add(BatchNormalization())
model.add(Dropout(0.5))

# Output Layer (4 classes)
model.add(Dense(len(encoder.classes_), activation="softmax"))

model.compile(
    optimizer="adam",
    loss="categorical_crossentropy",
    metrics=["accuracy"]
)

model.summary()

# ==========================
# CALLBACKS
# ==========================

callbacks = [
    EarlyStopping(patience=10, restore_best_weights=True),
    ReduceLROnPlateau(factor=0.5, patience=5, min_lr=1e-6)
]

# ==========================
# TRAIN
# ==========================

print("\n🚀 Starting training...")
print("="*50)

history = model.fit(
    datagen.flow(X_train, y_train, batch_size=32),
    validation_data=(X_test, y_test),
    epochs=30,
    callbacks=callbacks,
    verbose=1
)

print("\n" + "="*50)
print("✅ Training complete!")

# ==========================
# EVALUATE
# ==========================

print("\n📊 Evaluating model...")
test_loss, test_acc = model.evaluate(X_test, y_test, verbose=0)
print(f"✅ Test Accuracy: {test_acc:.4f}")
print(f"✅ Test Loss: {test_loss:.4f}")

# ==========================
# SAVE MODEL
# ==========================

model.save("chicken_disease_model.keras")
print("\n✅ Model saved to chicken_disease_model.keras")

print("\n" + "="*50)
print("🎉 All done! You can now run: python disease_api.py")
print("="*50)