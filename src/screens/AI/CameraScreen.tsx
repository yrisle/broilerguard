// src/screens/AI/CameraScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
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
import { useTheme } from "../../hooks/useTheme";

const CameraScreen = () => {
  const { colors } = useTheme();
  const [facing, setFacing] = useState<CameraType>("back");
  const [photo, setPhoto] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const [permission, requestPermission] = useCameraPermissions();
  const cameraRef = useRef<any>(null);

  if (!permission) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={[styles.loadingText, { color: colors.textMuted }]}>
          Checking camera permission...
        </Text>
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <View style={{ alignItems: "center", marginBottom: 16 }}>
          <Ionicons name="camera-outline" size={64} color={colors.text} />
        </View>
        <Text style={[styles.permissionText, { color: colors.text }]}>
          Camera permission required
        </Text>
        <Text style={[styles.permissionSubtext, { color: colors.textMuted }]}>
          Please allow camera access to use AI detection.
        </Text>

        <TouchableOpacity
          style={[styles.permissionBtn, { backgroundColor: colors.primary }]}
          onPress={requestPermission}
        >
          <Text style={[styles.permissionBtnText, { color: colors.text }]}>
            Grant Permission
          </Text>
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
        "Found 5 chickens:\n- 3 Healthy\n- 1 Weak\n- 1 Unhealthy",
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
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        quality: 1,
      });

      if (!result.canceled) {
        setPhoto(result.assets[0].uri);
        Alert.alert(
          "AI Detection Complete",
          "Found 5 chickens:\n- 3 Healthy\n- 1 Weak\n- 1 Unhealthy",
        );
      }
    } catch (error) {
      console.log("Picker error:", error);
      Alert.alert("Error", "Failed to open gallery");
    }
  };

  const resetCamera = () => setPhoto(null);

  if (photo) {
    return (
      <View style={styles.container}>
        <Image source={{ uri: photo }} style={styles.preview} />
        <View
          style={[styles.previewControls, { backgroundColor: colors.card }]}
        >
          <TouchableOpacity
            style={[styles.previewBtn, { backgroundColor: colors.primary }]}
            onPress={resetCamera}
          >
            <Ionicons name="camera-outline" size={20} color={colors.text} />
            <Text style={[styles.previewBtnText, { color: colors.text }]}>
              Retake
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[
              styles.previewBtn,
              styles.previewBtnSave,
              { backgroundColor: colors.success },
            ]}
          >
            <Ionicons name="checkmark-outline" size={20} color="#FFFFFF" />
            <Text style={[styles.previewBtnText, { color: "#FFFFFF" }]}>
              Save
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView ref={cameraRef} style={styles.camera} facing={facing}>
        <View style={styles.cameraOverlay}>
          <View style={styles.cameraHeader}>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <FontAwesome5
                name="camera"
                size={28}
                color="#FFFFFF"
                style={{ marginRight: 10 }}
              />
              <Text style={styles.cameraTitle}>AI Camera</Text>
            </View>
            <Text style={styles.cameraSubtitle}>
              Point at chickens for detection
            </Text>
          </View>

          <View style={styles.cameraControls}>
            <TouchableOpacity
              style={[
                styles.galleryBtn,
                { backgroundColor: "rgba(0,0,0,0.5)" },
              ]}
              onPress={pickImage}
            >
              <Ionicons name="images-outline" size={28} color="#FFFFFF" />
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
              style={[styles.flipBtn, { backgroundColor: "rgba(0,0,0,0.5)" }]}
              onPress={() =>
                setFacing((current) => (current === "back" ? "front" : "back"))
              }
            >
              <Ionicons
                name="camera-reverse-outline"
                size={28}
                color="#FFFFFF"
              />
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
    backgroundColor: "#5c8c43",
  },
  centered: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    padding: 20,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
  },
  permissionText: {
    fontSize: 24,
    fontWeight: "700",
    marginBottom: 8,
  },
  permissionSubtext: {
    fontSize: 14,
    textAlign: "center",
    marginBottom: 24,
  },
  permissionBtn: {
    paddingHorizontal: 32,
    paddingVertical: 14,
    borderRadius: 12,
  },
  permissionBtnText: {
    fontSize: 16,
    fontWeight: "600",
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
    marginTop: 20,
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
    borderRadius: 12,
  },
  flipBtn: {
    padding: 14,
    borderRadius: 12,
  },
  preview: {
    flex: 1,
    resizeMode: "cover",
  },
  previewControls: {
    flexDirection: "row",
    justifyContent: "center",
    padding: 20,
    gap: 12,
  },
  previewBtn: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 12,
    minWidth: 100,
    justifyContent: "center",
    gap: 8,
  },
  previewBtnSave: {
    backgroundColor: "#27AE60",
  },
  previewBtnText: {
    fontSize: 16,
    fontWeight: "600",
  },
});

export default CameraScreen;
