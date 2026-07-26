# find_images.py - Find where your images are
import os

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
print(f"📁 Current directory: {SCRIPT_DIR}")

# Check all subfolders
print("\n🔍 Searching for image folders...")
print("="*50)

folders_to_check = [
    SCRIPT_DIR,
    os.path.join(SCRIPT_DIR, "train"),
    os.path.join(SCRIPT_DIR, "images"),
    os.path.join(SCRIPT_DIR, "dataset"),
    os.path.join(SCRIPT_DIR, "data"),
    os.path.join(SCRIPT_DIR, "archive"),
    os.path.join(SCRIPT_DIR, "..", "archive"),
]

image_folders = []
for folder in folders_to_check:
    if os.path.exists(folder):
        jpg_files = [f for f in os.listdir(folder) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
        if len(jpg_files) > 0:
            image_folders.append((folder, len(jpg_files)))
            print(f"✅ {folder}: {len(jpg_files)} images")
        else:
            print(f"⬜ {folder}: No images (but folder exists)")

if image_folders:
    print("\n" + "="*50)
    print("📸 Image folders found!")
    for folder, count in image_folders:
        print(f"  - {folder} ({count} images)")
    
    print("\n💡 To use these images, update IMAGE_FOLDER in train_model.py to:")
    print(f"   IMAGE_FOLDER = r'{image_folders[0][0]}'")
else:
    print("\n❌ No image folders found!")
    print("Please make sure your dataset images are in one of these locations.")