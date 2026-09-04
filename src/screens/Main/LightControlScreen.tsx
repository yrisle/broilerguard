// src/screens/Main/LightControlScreen.tsx

import Ionicons from "@expo/vector-icons/Ionicons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { automation } from "../../api/endpoints";
import { useTheme } from "../../hooks/useTheme";

const LIGHT_AUTO_MODE_KEY = "@light_auto_mode";
const LIGHT_ON_TIME_KEY = "@light_on_time";
const LIGHT_OFF_TIME_KEY = "@light_off_time";

const LightControlScreen = () => {
  const { colors } = useTheme();
  const router = useRouter();
  const [lightStatus, setLightStatus] = useState("OFF");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [autoMode, setAutoMode] = useState(false);
  const [onTime, setOnTime] = useState("7:00 PM");
  const [offTime, setOffTime] = useState("6:00 AM");
  const [newOnTime, setNewOnTime] = useState("");
  const [newOffTime, setNewOffTime] = useState("");
  const [currentTime, setCurrentTime] = useState("");

  const intervalRef = useRef<number | null>(null);
  const timeIntervalRef = useRef<number | null>(null);

  // ============================================
  // PHILIPPINE TIME
  // ============================================
  const getPhilippineTime = (): Date => {
    const now = new Date();
    const phTime = new Date(now.getTime() + 8 * 60 * 60 * 1000);
    return phTime;
  };

  const updateCurrentTime = () => {
    const now = getPhilippineTime();
    setCurrentTime(
      now.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: true,
      }),
    );
  };

  // ============================================
  // TIME CONVERSION FUNCTIONS
  // ============================================
  const convertTo24Hour = (timeStr: string): string => {
    const parts = timeStr.split(" ");
    if (parts.length !== 2) return timeStr;

    const time = parts[0];
    const period = parts[1];
    let [hours, minutes] = time.split(":").map(Number);

    if (period === "PM" && hours !== 12) {
      hours += 12;
    } else if (period === "AM" && hours === 12) {
      hours = 0;
    }

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`;
  };

  const formatTimeDisplay = (
    hours: number,
    minutes: number,
    period: string,
  ): string => {
    const hour12 = hours % 12 || 12;
    return `${hour12}:${minutes.toString().padStart(2, "0")} ${period}`;
  };

  const parseTimeInput = (
    input: string,
  ): { hours: number; minutes: number; period: string } | null => {
    const patterns = [
      /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i,
      /^(\d{1,2}):(\d{2})(AM|PM)$/i,
      /^(\d{1,2})\s*(AM|PM)$/i,
      /^(\d{1,2})(AM|PM)$/i,
    ];

    for (const pattern of patterns) {
      const match = input.match(pattern);
      if (match) {
        if (match.length === 4) {
          return {
            hours: parseInt(match[1]),
            minutes: parseInt(match[2]),
            period: match[3].toUpperCase(),
          };
        } else if (match.length === 3) {
          return {
            hours: parseInt(match[1]),
            minutes: 0,
            period: match[2].toUpperCase(),
          };
        }
      }
    }
    return null;
  };

  const getCurrentMinutes = (): number => {
    const now = getPhilippineTime();
    return now.getHours() * 60 + now.getMinutes();
  };

  const getTimeMinutes = (timeStr: string): number => {
    const time24 = convertTo24Hour(timeStr);
    const [hours, minutes] = time24.split(":").map(Number);
    return hours * 60 + minutes;
  };

  // ============================================
  // SAVE & LOAD AUTO MODE STATE
  // ============================================
  const saveAutoModeState = async () => {
    try {
      await AsyncStorage.setItem(LIGHT_AUTO_MODE_KEY, JSON.stringify(autoMode));
      await AsyncStorage.setItem(LIGHT_ON_TIME_KEY, onTime);
      await AsyncStorage.setItem(LIGHT_OFF_TIME_KEY, offTime);
      console.log("✅ Light auto mode saved:", autoMode);
    } catch (error) {
      console.log("Error saving light auto mode:", error);
    }
  };

  const loadAutoModeState = async () => {
    try {
      const autoMode = await AsyncStorage.getItem(LIGHT_AUTO_MODE_KEY);
      const on = await AsyncStorage.getItem(LIGHT_ON_TIME_KEY);
      const off = await AsyncStorage.getItem(LIGHT_OFF_TIME_KEY);

      if (autoMode !== null) {
        const isAuto = JSON.parse(autoMode);
        setAutoMode(isAuto);
        if (on) setOnTime(on);
        if (off) setOffTime(off);
        console.log("✅ Light auto mode loaded:", isAuto);
        return isAuto;
      }
      return false;
    } catch (error) {
      console.log("Error loading light auto mode:", error);
      return false;
    }
  };

  // ============================================
  // FETCH DATA
  // ============================================
  const fetchData = async () => {
    try {
      console.log("📥 Fetching light data from ESP32...");
      const response = await automation.light.getStatus();
      const data = response.data;

      const status = data.light === 1 ? "ON" : "OFF";
      setLightStatus(status);
      console.log("📥 Light status:", status);
    } catch (error: any) {
      console.error("❌ Error fetching light data:", error);
      Alert.alert(
        "Error",
        "Failed to load light data. Please check connection to ESP32.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  // ============================================
  // INIT - Load auto mode state on mount
  // ============================================
  useEffect(() => {
    const init = async () => {
      const isAuto = await loadAutoModeState();
      await fetchData();
      updateCurrentTime();

      timeIntervalRef.current = setInterval(updateCurrentTime, 1000);

      if (isAuto) {
        console.log("🔄 Restarting light auto scheduler...");
        startTimeScheduler();
      }
    };

    init();

    return () => {
      if (timeIntervalRef.current) clearInterval(timeIntervalRef.current);
    };
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // ============================================
  // TOGGLE LIGHT
  // ============================================
  const toggleLight = async () => {
    const newStatus = lightStatus === "ON" ? "OFF" : "ON";
    try {
      console.log("🔄 Toggling light to:", newStatus);
      await automation.light.toggle(newStatus);
      setLightStatus(newStatus);
      fetchData();
    } catch (error: any) {
      console.error("❌ Toggle error:", error);
      Alert.alert("Error", "Failed to toggle light. Please try again.");
    }
  };

  // ============================================
  // TOGGLE AUTO MODE
  // ============================================
  const toggleAutoMode = async () => {
    const newMode = !autoMode;
    setAutoMode(newMode);
    await saveAutoModeState();

    if (newMode) {
      Alert.alert(
        "🤖 Auto Mode Enabled",
        `Light will turn ON at ${onTime} and OFF at ${offTime}.`,
        [{ text: "OK" }],
      );
      startTimeScheduler();
    } else {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
      Alert.alert("Auto Mode Disabled", "Manual control restored.", [
        { text: "OK" },
      ]);
    }
  };

  // ============================================
  // TIME-BASED AUTO SCHEDULER
  // ============================================
  const startTimeScheduler = () => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }

    intervalRef.current = setInterval(async () => {
      if (!autoMode) return;

      const currentMin = getCurrentMinutes();
      const onMin = getTimeMinutes(onTime);
      const offMin = getTimeMinutes(offTime);

      let shouldBeOn = false;

      if (onMin < offMin) {
        if (currentMin >= onMin && currentMin < offMin) {
          shouldBeOn = true;
        }
      } else {
        if (currentMin >= onMin || currentMin < offMin) {
          shouldBeOn = true;
        }
      }

      if (shouldBeOn && lightStatus === "OFF") {
        console.log(`💡 Turning Light ON at ${currentTime}`);
        try {
          await automation.light.toggle("ON");
          setLightStatus("ON");
          fetchData();
        } catch (error) {
          console.error("❌ Error turning light ON:", error);
        }
      } else if (!shouldBeOn && lightStatus === "ON") {
        console.log(`💡 Turning Light OFF at ${currentTime}`);
        try {
          await automation.light.toggle("OFF");
          setLightStatus("OFF");
          fetchData();
        } catch (error) {
          console.error("❌ Error turning light OFF:", error);
        }
      }
    }, 10000);
  };

  // ============================================
  // UPDATE TIMES
  // ============================================
  const updateOnTime = async () => {
    if (!newOnTime.trim()) {
      Alert.alert("Error", "Please enter a time (e.g., 7:00 PM)");
      return;
    }

    const parsed = parseTimeInput(newOnTime.trim());
    if (!parsed) {
      Alert.alert(
        "Error",
        "Invalid time format. Use: 7:00 PM, 7:00pm, 7 PM, or 7pm",
      );
      return;
    }

    const { hours, minutes, period } = parsed;
    const formattedTime = formatTimeDisplay(hours, minutes, period);
    setOnTime(formattedTime);
    setNewOnTime("");
    await saveAutoModeState();
  };

  const updateOffTime = async () => {
    if (!newOffTime.trim()) {
      Alert.alert("Error", "Please enter a time (e.g., 6:00 AM)");
      return;
    }

    const parsed = parseTimeInput(newOffTime.trim());
    if (!parsed) {
      Alert.alert(
        "Error",
        "Invalid time format. Use: 6:00 AM, 6:00am, 6 AM, or 6am",
      );
      return;
    }

    const { hours, minutes, period } = parsed;
    const formattedTime = formatTimeDisplay(hours, minutes, period);
    setOffTime(formattedTime);
    setNewOffTime("");
    await saveAutoModeState();
  };

  // Cleanup interval on unmount
  useEffect(() => {
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, []);

  if (loading) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={[styles.loadingText, { color: colors.textMuted }]}>
          Connecting to ESP32...
        </Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: colors.background }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <View style={styles.header}>
        <View style={{ flexDirection: "row", alignItems: "center" }}>
          <Ionicons
            name="bulb-outline"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Light Control
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Control the poultry house lighting
        </Text>
      </View>

      {/* Current Time Display */}
      <View style={[styles.timeDisplay, { backgroundColor: colors.card }]}>
        <Ionicons name="time-outline" size={20} color={colors.primary} />
        <Text style={[styles.timeDisplayText, { color: colors.text }]}>
          Philippine Time: {currentTime}
        </Text>
      </View>

      {/* Auto Mode Status */}
      <View
        style={[
          styles.autoStatusCard,
          {
            backgroundColor: autoMode ? colors.successLight : colors.card,
            borderColor: autoMode ? colors.success : colors.border,
          },
        ]}
      >
        <Ionicons
          name={autoMode ? "checkmark-circle" : "time-outline"}
          size={20}
          color={autoMode ? colors.success : colors.textMuted}
        />
        <Text
          style={[
            styles.autoStatusText,
            { color: autoMode ? colors.success : colors.textMuted },
          ]}
        >
          {autoMode ? "Auto Mode is ACTIVE" : "Auto Mode is OFF"}
        </Text>
        {autoMode && (
          <Text style={[styles.autoStatusSubtext, { color: colors.textMuted }]}>
            ON at {onTime} | OFF at {offTime}
          </Text>
        )}
      </View>

      {/* Light Status Card */}
      <View style={[styles.statusCard, { backgroundColor: colors.card }]}>
        <View
          style={[
            styles.lightIconContainer,
            {
              backgroundColor: colors.backgroundSecondary || colors.card + "80",
            },
          ]}
        >
          <Ionicons
            name={lightStatus === "ON" ? "bulb" : "bulb-outline"}
            size={50}
            color={lightStatus === "ON" ? colors.warning : colors.textMuted}
          />
        </View>
        <Text style={[styles.lightStatusLabel, { color: colors.textMuted }]}>
          Light is
        </Text>
        <Text
          style={[
            styles.lightStatusText,
            lightStatus === "ON"
              ? [styles.statusOn, { color: colors.success }]
              : [styles.statusOff, { color: colors.danger }],
          ]}
        >
          {lightStatus === "ON" ? "ON" : "OFF"}
        </Text>

        <TouchableOpacity
          style={[
            styles.toggleBtn,
            lightStatus === "ON"
              ? [styles.toggleOn, { backgroundColor: colors.danger }]
              : [styles.toggleOff, { backgroundColor: colors.success }],
          ]}
          onPress={toggleLight}
        >
          <Text style={styles.toggleBtnText}>
            {lightStatus === "ON" ? "Turn OFF" : "Turn ON"}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Auto Mode Section */}
      <View
        style={[
          styles.autoCard,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <View style={styles.autoRow}>
          <View>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <Ionicons
                name="rocket-outline"
                size={20}
                color={colors.text}
                style={{ marginRight: 8 }}
              />
              <Text style={[styles.autoLabel, { color: colors.text }]}>
                Auto Mode
              </Text>
            </View>
            <Text style={[styles.autoDesc, { color: colors.textMuted }]}>
              {autoMode
                ? "✅ Auto scheduling is ON"
                : "⏸️ Auto scheduling is OFF"}
            </Text>
          </View>
          <Switch
            value={autoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: colors.primary }}
            thumbColor={autoMode ? "#FFFFFF" : "#f4f3f4"}
          />
        </View>

        {/* ON Time Setting */}
        <View style={[styles.settingRow, { borderColor: colors.border }]}>
          <Text style={[styles.settingLabel, { color: colors.text }]}>
            Turn ON at
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <TextInput
              style={[
                styles.timeInput,
                {
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  color: colors.text,
                  width: 100,
                },
              ]}
              value={newOnTime}
              onChangeText={setNewOnTime}
              placeholder={onTime}
              placeholderTextColor={colors.textMuted}
              autoCapitalize="characters"
              editable={true}
            />
            <TouchableOpacity
              style={[
                styles.updateBtn,
                {
                  backgroundColor: colors.primary,
                },
              ]}
              onPress={updateOnTime}
            >
              <Text style={styles.updateBtnText}>Set</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* OFF Time Setting */}
        <View style={[styles.settingRow, { borderColor: colors.border }]}>
          <Text style={[styles.settingLabel, { color: colors.text }]}>
            Turn OFF at
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <TextInput
              style={[
                styles.timeInput,
                {
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  color: colors.text,
                  width: 100,
                },
              ]}
              value={newOffTime}
              onChangeText={setNewOffTime}
              placeholder={offTime}
              placeholderTextColor={colors.textMuted}
              autoCapitalize="characters"
              editable={true}
            />
            <TouchableOpacity
              style={[
                styles.updateBtn,
                {
                  backgroundColor: colors.primary,
                },
              ]}
              onPress={updateOffTime}
            >
              <Text style={styles.updateBtnText}>Set</Text>
            </TouchableOpacity>
          </View>
        </View>

        <Text style={[styles.autoNote, { color: colors.textMuted }]}>
          💡 Light will turn ON at {onTime} and OFF at {offTime}
        </Text>
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="flash-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Quick Actions
          </Text>
        </View>

        <View style={styles.actionRow}>
          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionOn,
              {
                backgroundColor: colors.success,
                opacity: lightStatus === "ON" ? 0.5 : 1,
              },
            ]}
            onPress={() => {
              if (lightStatus !== "ON") toggleLight();
            }}
            disabled={lightStatus === "ON"}
          >
            <Ionicons name="power" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Turn ON</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionOff,
              {
                backgroundColor: colors.danger,
                opacity: lightStatus === "OFF" ? 0.5 : 1,
              },
            ]}
            onPress={() => {
              if (lightStatus !== "OFF") toggleLight();
            }}
            disabled={lightStatus === "OFF"}
          >
            <Ionicons name="power-outline" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Turn OFF</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.footer} />
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  centered: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
  },
  header: {
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 8,
  },
  title: {
    fontSize: 24,
    fontWeight: "800",
  },
  subtitle: {
    fontSize: 14,
    marginTop: 4,
    marginLeft: 36,
  },
  timeDisplay: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    marginHorizontal: 16,
    marginTop: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 8,
    gap: 8,
  },
  timeDisplayText: {
    fontSize: 14,
    fontWeight: "600",
  },
  autoStatusCard: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    flexWrap: "wrap",
    marginHorizontal: 16,
    marginTop: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    borderWidth: 1,
    gap: 8,
  },
  autoStatusText: {
    fontSize: 14,
    fontWeight: "700",
  },
  autoStatusSubtext: {
    fontSize: 12,
  },
  statusCard: {
    borderRadius: 16,
    padding: 24,
    margin: 16,
    alignItems: "center",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  lightIconContainer: {
    width: 100,
    height: 100,
    borderRadius: 50,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  lightStatusLabel: {
    fontSize: 14,
  },
  lightStatusText: {
    fontSize: 32,
    fontWeight: "800",
    marginVertical: 4,
  },
  statusOn: {},
  statusOff: {},
  toggleBtn: {
    paddingHorizontal: 40,
    paddingVertical: 14,
    borderRadius: 30,
    marginTop: 12,
    width: "80%",
    alignItems: "center",
  },
  toggleOn: {},
  toggleOff: {},
  toggleBtnText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  autoCard: {
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 16,
    borderWidth: 1,
  },
  autoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#E0E0E0",
  },
  autoLabel: {
    fontSize: 16,
    fontWeight: "600",
  },
  autoDesc: {
    fontSize: 12,
    marginTop: 2,
  },
  settingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 10,
    borderBottomWidth: 1,
  },
  settingLabel: {
    fontSize: 14,
  },
  timeInput: {
    paddingHorizontal: 8,
    paddingVertical: 6,
    borderWidth: 1,
    borderRadius: 6,
    fontSize: 14,
    textAlign: "center",
  },
  updateBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    justifyContent: "center",
  },
  updateBtnText: {
    color: "#FFFFFF",
    fontSize: 12,
    fontWeight: "600",
  },
  autoNote: {
    fontSize: 12,
    marginTop: 8,
    textAlign: "center",
    fontStyle: "italic",
  },
  section: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
  },
  actionRow: {
    flexDirection: "row",
    gap: 12,
  },
  actionBtn: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 20,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  actionOn: {},
  actionOff: {},
  actionBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  footer: {
    height: 40,
  },
});

export default LightControlScreen;
