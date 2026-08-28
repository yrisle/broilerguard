// src/screens/AI/CameraScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useCameraPermissions } from "expo-camera";
import * as ImagePicker from "expo-image-picker";
import React, { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  StyleSheet,
  Text,
  TouchableOpacity,
  View
} from "react-native";
import { useTheme } from "../../hooks/useTheme";

// ============================================
// CORRECT IP - Where RTSP service runs
// ============================================
const STREAM_IP = "192.168.1.8";
const STREAM_PORT = "8080";
const API_PORT = "5000";

const STORAGE_KEYS = {
  CAMERA_IP: "@camera_ip",
  STREAM_URL: "@stream_url",
  API_URL: "@api_url",
  DISCOVERY_TIME: "@discovery_time",
};

const CameraScreen = () => {
  const { colors } = useTheme();
  const [photo, setPhoto] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [streamActive, setStreamActive] = useState(false);
  const [detectionResult, setDetectionResult] = useState<any>(null);
  const [isDetecting, setIsDetecting] = useState(false);
  const [snapshots, setSnapshots] = useState<any[]>([]);
  const [isDiscovering, setIsDiscovering] = useState(false);
  const [cameraIp, setCameraIp] = useState<string>(STREAM_IP);
  const [streamUrl, setStreamUrl] = useState<string>(
    `http://${STREAM_IP}:${STREAM_PORT}/frame`,
  );
  const [apiUrl, setApiUrl] = useState<string>(
    `http://${STREAM_IP}:${API_PORT}`,
  );
  const [connectionStatus, setConnectionStatus] =
    useState<string>("Connecting...");
  const [imageKey, setImageKey] = useState<number>(Date.now());
  const [streamError, setStreamError] = useState<boolean>(false);
  const [imageLoadAttempts, setImageLoadAttempts] = useState<number>(0);

  const [permission, requestPermission] = useCameraPermissions();
  const imageRef = useRef<Image>(null);
  const streamInterval = useRef<ReturnType<typeof setInterval> | null>(null);
  const detectionInterval = useRef<ReturnType<typeof setInterval> | null>(null);
  const discoveryInterval = useRef<ReturnType<typeof setInterval> | null>(null);

  const connectToCamera = async (): Promise<boolean> => {
    try {
      setIsDiscovering(true);
      setConnectionStatus("🔍 Connecting...");
      setStreamError(false);

      console.log(`📡 Connecting to ${STREAM_IP}:${STREAM_PORT}...`);

      const testUrl = `http://${STREAM_IP}:${STREAM_PORT}/status`;
      console.log(`🔗 Testing: ${testUrl}`);

      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 5000);

      const response = await fetch(testUrl, {
        method: "GET",
        signal: controller.signal,
        headers: {
          Accept: "application/json",
        },
      });

      clearTimeout(timeoutId);

      console.log(`📊 Response status: ${response.status}`);

      if (response.ok) {
        const data = await response.json();
        console.log("✅ RTSP Service connected:", data);

        setCameraIp(STREAM_IP);
        setStreamUrl(`http://${STREAM_IP}:${STREAM_PORT}/frame`);
        setApiUrl(`http://${STREAM_IP}:${API_PORT}`);

        await AsyncStorage.setItem(STORAGE_KEYS.CAMERA_IP, STREAM_IP);
        await AsyncStorage.setItem(
          STORAGE_KEYS.STREAM_URL,
          `http://${STREAM_IP}:${STREAM_PORT}/frame`,
        );
        await AsyncStorage.setItem(
          STORAGE_KEYS.API_URL,
          `http://${STREAM_IP}:${API_PORT}`,
        );
        await AsyncStorage.setItem(
          STORAGE_KEYS.DISCOVERY_TIME,
          Date.now().toString(),
        );

        if (data.connected) {
          setConnectionStatus("✅ Connected");
        } else {
          setConnectionStatus("⚠️ Camera offline");
        }

        setIsDiscovering(false);
        return true;
      } else {
        console.log("❌ RTSP Service not responding");
        setConnectionStatus("❌ Service offline");
        setIsDiscovering(false);
        return false;
      }
    } catch (error) {
      console.error("❌ Connection error:", error);
      setConnectionStatus("❌ Connection failed");
      setIsDiscovering(false);
      return false;
    }
  };

  const startStream = () => {
    if (!streamUrl) {
      console.log("⚠️ No stream URL");
      return;
    }

    console.log(`📹 Starting stream: ${streamUrl}`);
    setStreamActive(true);
    setConnectionStatus("📡 Streaming");
    setStreamError(false);
    setImageLoadAttempts(0);

    if (streamInterval.current) clearInterval(streamInterval.current);

    // Force immediate refresh
    setImageKey(Date.now());

    // Refresh every 50ms (20fps) for smooth video
    streamInterval.current = setInterval(() => {
      if (streamActive) {
        setImageKey(Date.now());
      }
    }, 50);
  };

  const stopStream = () => {
    if (streamInterval.current) {
      clearInterval(streamInterval.current);
      streamInterval.current = null;
    }
    if (detectionInterval.current) {
      clearInterval(detectionInterval.current);
      detectionInterval.current = null;
    }
    setStreamActive(false);
    setConnectionStatus("⏸️ Paused");
  };

  const toggleStream = () => {
    if (streamActive) {
      stopStream();
    } else {
      connectToCamera().then((connected) => {
        if (connected) {
          startStream();
        }
      });
    }
  };

  const reconnectCamera = async () => {
    console.log("🔄 Reconnecting...");
    setConnectionStatus("🔄 Reconnecting...");
    stopStream();
    const connected = await connectToCamera();
    if (connected) {
      startStream();
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
        stopStream();
      }
    } catch (error) {
      console.log("Picker error:", error);
      Alert.alert("Error", "Failed to open gallery");
    }
  };

  const handleImageError = () => {
    console.log("❌ Stream image error");
    setImageLoadAttempts((prev) => prev + 1);
    setStreamError(true);
    setConnectionStatus("⚠️ Stream error");

    // Try to reload after 1 second
    setTimeout(() => {
      if (streamActive && !photo) {
        console.log("🔄 Attempting to recover stream...");
        setImageKey(Date.now());
        setStreamError(false);
        setConnectionStatus("📡 Reconnecting...");
      }
    }, 1000);
  };

  useEffect(() => {
    const init = async () => {
      console.log("📱 Initializing CameraScreen...");
      console.log(`📡 Target: ${STREAM_IP}:${STREAM_PORT}`);

      // Clear storage to remove old config (temporary)
      try {
        // await AsyncStorage.clear(); // Uncomment to clear
        console.log("📱 Using IP:", STREAM_IP);
      } catch (error) {
        console.error("Error:", error);
      }

      const connected = await connectToCamera();

      if (connected && !photo) {
        startStream();
      }
    };

    init();

    return () => {
      if (streamInterval.current) clearInterval(streamInterval.current);
      if (detectionInterval.current) clearInterval(detectionInterval.current);
      if (discoveryInterval.current) clearInterval(discoveryInterval.current);
    };
  }, []);

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

  if (photo) {
    return (
      <View style={styles.container}>
        <Image source={{ uri: photo }} style={styles.preview} />
        <View
          style={[styles.previewControls, { backgroundColor: colors.card }]}
        >
          <TouchableOpacity
            style={[styles.previewBtn, { backgroundColor: colors.primary }]}
            onPress={() => {
              setPhoto(null);
              setDetectionResult(null);
              connectToCamera().then(() => {
                startStream();
              });
            }}
          >
            <Ionicons name="camera-outline" size={20} color={colors.text} />
            <Text style={[styles.previewBtnText, { color: colors.text }]}>
              Back to Camera
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.camera}>
        {streamActive ? (
          <Image
            key={imageKey} // ← Force re-render
            ref={imageRef}
            source={{
              uri: `${streamUrl}?t=${imageKey}`,
              cache: "reload", // ← Force reload
              headers: {
                "Cache-Control": "no-cache, no-store, must-revalidate",
                Pragma: "no-cache",
                Expires: "0",
              },
            }}
            style={styles.camera}
            resizeMode="cover"
            onError={handleImageError}
            onLoad={() => {
              console.log("✅ Image loaded successfully");
              if (streamError) {
                setStreamError(false);
                setConnectionStatus("✅ Streaming");
              }
            }}
            onLoadStart={() => {
              console.log("🔄 Loading image...");
            }}
          />
        ) : (
          <View
            style={[
              styles.placeholderContainer,
              { backgroundColor: "#1a1a1a" },
            ]}
          >
            <FontAwesome5 name="video-slash" size={60} color="#666" />
            <Text style={styles.placeholderText}>Camera Off</Text>
            <Text style={styles.placeholderSubtext}>
              Tap play to start streaming
            </Text>
          </View>
        )}

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

            <View style={{ marginTop: 4, alignItems: "center" }}>
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  marginBottom: 2,
                }}
              >
                <View
                  style={[
                    styles.statusDot,
                    {
                      backgroundColor:
                        streamActive && !streamError
                          ? "#4CAF50"
                          : isDiscovering
                            ? "#FFA500"
                            : "#FF6B6B",
                    },
                  ]}
                />
                <Text style={[styles.cameraSubtitle, { marginRight: 12 }]}>
                  {connectionStatus}
                </Text>
              </View>
              {cameraIp && (
                <Text
                  style={[
                    styles.cameraSubtitle,
                    { fontSize: 10, opacity: 0.6 },
                  ]}
                >
                  📡 {cameraIp}:{STREAM_PORT}
                </Text>
              )}
              <Text
                style={[
                  styles.cameraSubtitle,
                  { fontSize: 9, opacity: 0.4, marginTop: 2 },
                ]}
              >
                Key: {imageKey}
              </Text>
            </View>
          </View>

          <View style={styles.cameraControls}>
            <TouchableOpacity
              style={[
                styles.controlBtn,
                { backgroundColor: "rgba(0,0,0,0.6)" },
              ]}
              onPress={pickImage}
            >
              <Ionicons name="images-outline" size={28} color="#FFFFFF" />
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.controlBtn,
                { backgroundColor: "rgba(0,0,0,0.6)" },
              ]}
              onPress={reconnectCamera}
              disabled={isDiscovering}
            >
              <Ionicons
                name={isDiscovering ? "sync-outline" : "wifi-outline"}
                size={24}
                color="#FFFFFF"
              />
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.controlBtn,
                { backgroundColor: "rgba(0,0,0,0.6)" },
              ]}
              onPress={toggleStream}
            >
              <Ionicons
                name={
                  streamActive ? "pause-circle-outline" : "play-circle-outline"
                }
                size={32}
                color="#FFFFFF"
              />
            </TouchableOpacity>
          </View>
        </View>
      </View>
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
    backgroundColor: "#000",
    width: "100%",
    height: "100%",
  },
  placeholderContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  placeholderText: {
    color: "#FFFFFF",
    fontSize: 20,
    fontWeight: "600",
    marginTop: 16,
  },
  placeholderSubtext: {
    color: "#888",
    fontSize: 14,
    marginTop: 8,
  },
  cameraOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0,0,0,0.2)",
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
    textShadowColor: "rgba(0,0,0,0.8)",
    textShadowOffset: { width: 0, height: 2 },
    textShadowRadius: 4,
  },
  cameraSubtitle: {
    fontSize: 12,
    color: "rgba(255,255,255,0.9)",
    textShadowColor: "rgba(0,0,0,0.8)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 2,
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 4,
  },
  cameraControls: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingBottom: 20,
  },
  controlBtn: {
    padding: 12,
    borderRadius: 12,
    marginHorizontal: 8,
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
  previewBtnText: {
    fontSize: 16,
    fontWeight: "600",
  },
});

export default CameraScreen;
