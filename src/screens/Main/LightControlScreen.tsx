// src/screens/Main/LightControlScreen.tsx

import Ionicons from "@expo/vector-icons/Ionicons";
import AsyncStorage from "@react-native-async-storage/async-storage";
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
import { useAutomation } from "../../context/AutomationContext";
import { useTheme } from "../../hooks/useTheme";
import { automationScheduler } from "../../services/AutomationScheduler";

const LIGHT_ON_TIME_KEY = "@light_on_time";
const LIGHT_OFF_TIME_KEY = "@light_off_time";

const LightControlScreen = () => {
  const { colors } = useTheme();
  const { lightAutoMode, setLightAutoMode } = useAutomation();
  const [lightStatus, setLightStatus] = useState("OFF");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [onTime, setOnTime] = useState("7:00 PM");
  const [offTime, setOffTime] = useState("6:00 AM");
  const [newOnTime, setNewOnTime] = useState("");
  const [newOffTime, setNewOffTime] = useState("");
  const [currentTime, setCurrentTime] = useState("");

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

  // ============================================
  // SAVE & LOAD TIME SETTINGS
  // ============================================
  const saveTimeSettings = async () => {
    try {
      await AsyncStorage.setItem(LIGHT_ON_TIME_KEY, onTime);
      await AsyncStorage.setItem(LIGHT_OFF_TIME_KEY, offTime);
      console.log("✅ Light time settings saved:", { onTime, offTime });
    } catch (error) {
      console.log("Error saving light time settings:", error);
    }
  };

  const loadTimeSettings = async () => {
    try {
      const on = await AsyncStorage.getItem(LIGHT_ON_TIME_KEY);
      const off = await AsyncStorage.getItem(LIGHT_OFF_TIME_KEY);

      if (on) setOnTime(on);
      if (off) setOffTime(off);
      console.log("✅ Light time settings loaded:", {
        onTime: on,
        offTime: off,
      });
    } catch (error) {
      console.log("Error loading light time settings:", error);
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
  // INIT - Load settings on mount
  // ============================================
  useEffect(() => {
    const init = async () => {
      await loadTimeSettings();
      await fetchData();
      updateCurrentTime();

      timeIntervalRef.current = setInterval(updateCurrentTime, 1000);
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
    if (lightAutoMode) return; // Block manual control when auto mode is on

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
    const newMode = !lightAutoMode;
    await setLightAutoMode(newMode);

    if (newMode) {
      Alert.alert(
        "🤖 Auto Mode Enabled",
        `Light will turn ON at ${onTime} and OFF at ${offTime}.\n\n🔒 Manual controls are now disabled.`,
        [{ text: "OK" }],
      );
      await automationScheduler.startLightScheduler();
    } else {
      automationScheduler.stopLightScheduler();
      Alert.alert("Auto Mode Disabled", "Manual control restored.", [
        { text: "OK" },
      ]);
    }
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
    await saveTimeSettings();
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
    await saveTimeSettings();
  };

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
            backgroundColor: lightAutoMode ? colors.successLight : colors.card,
            borderColor: lightAutoMode ? colors.success : colors.border,
          },
        ]}
      >
        <Ionicons
          name={lightAutoMode ? "checkmark-circle" : "time-outline"}
          size={20}
          color={lightAutoMode ? colors.success : colors.textMuted}
        />
        <Text
          style={[
            styles.autoStatusText,
            { color: lightAutoMode ? colors.success : colors.textMuted },
          ]}
        >
          {lightAutoMode ? "Auto Mode is ACTIVE" : "Auto Mode is OFF"}
        </Text>
        {lightAutoMode && (
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
            lightAutoMode && styles.disabledBtn,
          ]}
          onPress={toggleLight}
          disabled={lightAutoMode}
        >
          <Text style={styles.toggleBtnText}>
            {lightAutoMode
              ? "🔒 Auto Mode ON"
              : lightStatus === "ON"
                ? "Turn OFF"
                : "Turn ON"}
          </Text>
        </TouchableOpacity>

        {lightAutoMode && (
          <View
            style={[
              styles.autoIndicator,
              { backgroundColor: colors.successLight },
            ]}
          >
            <Ionicons name="lock-closed" size={14} color={colors.success} />
            <Text style={[styles.autoIndicatorText, { color: colors.success }]}>
              Auto Mode Active - Manual control disabled
            </Text>
          </View>
        )}
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
              {lightAutoMode
                ? "✅ Auto scheduling is ON"
                : "⏸️ Auto scheduling is OFF"}
            </Text>
          </View>
          <Switch
            value={lightAutoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: colors.primary }}
            thumbColor={lightAutoMode ? "#FFFFFF" : "#f4f3f4"}
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
          {lightAutoMode && (
            <View
              style={[
                styles.lockBadge,
                { backgroundColor: colors.warningLight, marginLeft: 8 },
              ]}
            >
              <Ionicons name="lock-closed" size={12} color={colors.warning} />
              <Text style={[styles.lockBadgeText, { color: colors.warning }]}>
                Locked
              </Text>
            </View>
          )}
        </View>

        <View style={styles.actionRow}>
          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionOn,
              {
                backgroundColor: colors.success,
                opacity: lightStatus === "ON" || lightAutoMode ? 0.5 : 1,
              },
            ]}
            onPress={() => {
              if (lightStatus !== "ON" && !lightAutoMode) toggleLight();
            }}
            disabled={lightStatus === "ON" || lightAutoMode}
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
                opacity: lightStatus === "OFF" || lightAutoMode ? 0.5 : 1,
              },
            ]}
            onPress={() => {
              if (lightStatus !== "OFF" && !lightAutoMode) toggleLight();
            }}
            disabled={lightStatus === "OFF" || lightAutoMode}
          >
            <Ionicons name="power-outline" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Turn OFF</Text>
          </TouchableOpacity>
        </View>

        {lightAutoMode && (
          <Text style={[styles.lockedMessage, { color: colors.warning }]}>
            🔒 Manual light controls are locked while Auto Mode is ON
          </Text>
        )}
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
  disabledBtn: {
    opacity: 0.5,
  },
  autoIndicator: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    marginTop: 8,
  },
  autoIndicatorText: {
    fontSize: 12,
    fontWeight: "600",
    marginLeft: 6,
  },
  lockBadge: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 12,
  },
  lockBadgeText: {
    fontSize: 10,
    fontWeight: "600",
    marginLeft: 4,
  },
  lockedMessage: {
    fontSize: 12,
    textAlign: "center",
    marginTop: 8,
    fontStyle: "italic",
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
