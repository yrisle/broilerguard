import pandas as pd
import pickle
from sklearn.preprocessing import LabelEncoder

# Basahin ang CSV
df = pd.read_csv("train_data.csv")

# Kunin ang labels
labels = df["label"]

# Gumawa ng LabelEncoder
encoder = LabelEncoder()
encoder.fit(labels)

# I-save
with open("label_encoder.pkl", "wb") as f:
    pickle.dump(encoder, f)

print("Label encoder saved successfully!")
print("Classes:", encoder.classes_)