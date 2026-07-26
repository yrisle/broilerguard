import os
import pandas as pd
import numpy as np
import cv2

from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder

from tensorflow.keras.utils import to_categorical
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import Conv2D, MaxPooling2D, Flatten, Dense, Dropout

# ==========================
# PATHS
# ==========================

DATASET_PATH = r"C:\Users\Admin\Downloads\archive"
IMAGE_FOLDER = os.path.join(DATASET_PATH, "train")
CSV_FILE = os.path.join(DATASET_PATH, "train_data.csv")

# ==========================
# LOAD CSV
# ==========================

df = pd.read_csv(CSV_FILE)

print(df.head())

# ==========================
# LOAD IMAGES
# ==========================

images = []
labels = []

IMAGE_SIZE = 128

for index, row in df.iterrows():

    image_name = row["images"]
    label = row["label"]

    image_path = os.path.join(IMAGE_FOLDER, image_name)

    img = cv2.imread(image_path)

    if img is None:
        print("Missing:", image_name)
        continue

    img = cv2.resize(img, (IMAGE_SIZE, IMAGE_SIZE))
    img = img / 255.0

    images.append(img)
    labels.append(label)

images = np.array(images)
labels = np.array(labels)

print("Images Loaded:", len(images))

# ==========================
# LABEL ENCODER
# ==========================

encoder = LabelEncoder()

labels = encoder.fit_transform(labels)

labels = to_categorical(labels)

import pickle

with open("label_encoder.pkl", "wb") as f:
    pickle.dump(encoder, f)

print(encoder.classes_)

# ==========================
# TRAIN TEST SPLIT
# ==========================

X_train, X_test, y_train, y_test = train_test_split(
    images,
    labels,
    test_size=0.2,
    random_state=42
)

# ==========================
# CNN MODEL
# ==========================

model = Sequential()

model.add(Conv2D(32,(3,3),activation="relu",input_shape=(128,128,3)))
model.add(MaxPooling2D())

model.add(Conv2D(64,(3,3),activation="relu"))
model.add(MaxPooling2D())

model.add(Conv2D(128,(3,3),activation="relu"))
model.add(MaxPooling2D())

model.add(Flatten())

model.add(Dense(128,activation="relu"))
model.add(Dropout(0.5))

model.add(Dense(4,activation="softmax"))

model.compile(
    optimizer="adam",
    loss="categorical_crossentropy",
    metrics=["accuracy"]
)

# ==========================
# TRAIN
# ==========================

history = model.fit(
    X_train,
    y_train,
    validation_data=(X_test,y_test),
    epochs=10,
    batch_size=32
)

# ==========================
# SAVE MODEL
# ==========================

model.save("chicken_disease_model.keras")

print("MODEL SAVED!")