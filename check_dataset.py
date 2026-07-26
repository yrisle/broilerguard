# check_dataset.py - Check dataset integrity
import os
import pandas as pd

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CSV_FILE = os.path.join(SCRIPT_DIR, "train_data.csv")

if not os.path.exists(CSV_FILE):
    print(f"❌ CSV not found: {CSV_FILE}")
    exit(1)

df = pd.read_csv(CSV_FILE)
print(f"📊 CSV has {len(df)} entries")

# Check first few images
found = 0
missing = 0
missing_list = []

for i, row in df.iterrows():
    image_name = row["images"]
    image_path = os.path.join(SCRIPT_DIR, image_name)
    
    # Try different extensions
    found_image = False
    for ext in ['', '.jpg', '.jpeg', '.png']:
        if os.path.exists(image_path + ext):
            found_image = True
            found += 1
            break
    
    if not found_image:
        missing += 1
        if len(missing_list) < 10:
            missing_list.append(image_name)

print(f"✅ Found: {found} images")
print(f"❌ Missing: {missing} images")

if missing_list:
    print("\n📝 Missing images (first 10):")
    for f in missing_list:
        print(f"  - {f}")

if missing == 0:
    print("\n✅ All images found! You can run: python train_model.py")
else:
    print("\n⚠️ Please download the missing images to this folder.")
    print("   The images should be in the same folder as train_data.csv")