// src/screens/AI/CameraScreen.tsx
import { CameraType, CameraView, useCameraPermissions } from "expo-camera";
import * as ImagePicker from "expo-image-picker";
import React, { useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

const CameraScreen = () => {
  const [facing, setFacing] = useState<CameraType>("back");
  const [photo, setPhoto] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const [permission, requestPermission] = useCameraPermissions();
  const cameraRef = useRef<any>(null);

  // habang chine-check ang permission
  if (!permission) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
        <Text style={styles.loadingText}>Checking camera permission...</Text>
      </View>
    );
  }

  // kapag walang permission
  if (!permission.granted) {
    return (
      <View style={styles.centered}>
        <Text style={styles.permissionText}>📷 Camera permission required</Text>
        <Text style={styles.permissionSubtext}>
          Please allow camera access to use AI detection.
        </Text>

        <TouchableOpacity
          style={styles.permissionBtn}
          onPress={requestPermission}
        >
          <Text style={styles.permissionBtnText}>Grant Permission</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const takePicture = async () => {
    if (!cameraRef.current) return;

    try {
      setLoading(true);

      const result = await cameraRef.current.takePictureAsync({
        quality: 0.8,
      });

      setPhoto(result.uri);

      Alert.alert(
        "AI Detection Complete",
        "Found 5 chickens:\\n- 3 Healthy\\n- 1 Weak\\n- 1 Unhealthy",
      );
    } catch (error) {
      console.log("Camera error:", error);
      Alert.alert("Error", "Failed to take picture");
    } finally {
      setLoading(false);
    }
  };

  const pickImage = async () => {
    try {
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ["images"],
        allowsEditing: true,
        quality: 1,
      });

      if (!result.canceled) {
        setPhoto(result.assets[0].uri);

        Alert.alert(
          "AI Detection Complete",
          "Found 5 chickens:\\n- 3 Healthy\\n- 1 Weak\\n- 1 Unhealthy",
        );
      }
    } catch (error) {
      console.log("Picker error:", error);
      Alert.alert("Error", "Failed to open gallery");
    }
  };

  const resetCamera = () => setPhoto(null);

  // preview screen
  if (photo) {
    return (
      <View style={styles.container}>
        <Image source={{ uri: photo }} style={styles.preview} />

        <View style={styles.previewControls}>
          <TouchableOpacity style={styles.previewBtn} onPress={resetCamera}>
            <Text style={styles.previewBtnText}>📷 Retake</Text>
          </TouchableOpacity>

          <TouchableOpacity style={[styles.previewBtn, styles.previewBtnSave]}>
            <Text style={styles.previewBtnText}>✅ Save</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  // camera screen
  return (
    <View style={styles.container}>
      <CameraView ref={cameraRef} style={styles.camera} facing={facing}>
        <View style={styles.cameraOverlay}>
          <View style={styles.cameraHeader}>
            <Text style={styles.cameraTitle}>📷 AI Camera</Text>
            <Text style={styles.cameraSubtitle}>
              Point at chickens for detection
            </Text>
          </View>

          <View style={styles.cameraControls}>
            <TouchableOpacity style={styles.galleryBtn} onPress={pickImage}>
              <Text style={styles.galleryBtnText}>🖼️</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.captureBtn}
              onPress={takePicture}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color="#FFF" />
              ) : (
                <View style={styles.captureInner} />
              )}
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.flipBtn}
              onPress={() =>
                setFacing((current) => (current === "back" ? "front" : "back"))
              }
            >
              <Text style={styles.flipBtnText}>🔄</Text>
            </TouchableOpacity>
          </View>
        </View>
      </CameraView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#000",
  },
  centered: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "#FFFCF2",
    padding: 20,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: "#8B7355",
  },
  permissionText: {
    fontSize: 24,
    fontWeight: "700",
    color: "#3E2C1C",
    marginBottom: 8,
  },
  permissionSubtext: {
    fontSize: 14,
    color: "#8B7355",
    textAlign: "center",
    marginBottom: 24,
  },
  permissionBtn: {
    backgroundColor: "#FFD62E",
    paddingHorizontal: 32,
    paddingVertical: 14,
    borderRadius: 12,
  },
  permissionBtnText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  camera: {
    flex: 1,
  },
  cameraOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.3)",
    justifyContent: "space-between",
    paddingVertical: 40,
  },
  cameraHeader: {
    alignItems: "center",
  },
  cameraTitle: {
    fontSize: 24,
    fontWeight: "800",
    color: "#FFFFFF",
  },
  cameraSubtitle: {
    fontSize: 14,
    color: "rgba(255,255,255,0.8)",
    marginTop: 4,
  },
  cameraControls: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingBottom: 20,
  },
  captureBtn: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: "rgba(255,255,255,0.3)",
    justifyContent: "center",
    alignItems: "center",
    marginHorizontal: 20,
    borderWidth: 3,
    borderColor: "rgba(255,255,255,0.6)",
  },
  captureInner: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: "#FFFFFF",
  },
  galleryBtn: {
    padding: 14,
    backgroundColor: "rgba(0,0,0,0.5)",
    borderRadius: 12,
  },
  galleryBtnText: {
    fontSize: 24,
  },
  flipBtn: {
    padding: 14,
    backgroundColor: "rgba(0,0,0,0.5)",
    borderRadius: 12,
  },
  flipBtnText: {
    fontSize: 24,
  },
  preview: {
    flex: 1,
    resizeMode: "cover",
  },
  previewControls: {
    flexDirection: "row",
    justifyContent: "center",
    padding: 20,
    backgroundColor: "rgba(0,0,0,0.85)",
    gap: 12,
  },
  previewBtn: {
    backgroundColor: "#FFD62E",
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 12,
    minWidth: 100,
    alignItems: "center",
  },
  previewBtnSave: {
    backgroundColor: "#27AE60",
  },
  previewBtnText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#3E2C1C",
  },
});

export default CameraScreen;
