// src/screens/Automation/FanControlScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
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

const FAN_TEMP_ON_KEY = "@fan_temp_on";
const FAN_TEMP_OFF_KEY = "@fan_temp_off";

function FanControlScreen() {
  const { colors } = useTheme();
  const { fanAutoMode, setFanAutoMode } = useAutomation();
  const [fanStatus, setFanStatus] = useState("OFF");
  const [settings, setSettings] = useState({
    temp_on: 32,
    temp_off: 28,
  });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [temperature, setTemperature] = useState(0);
  const [humidity, setHumidity] = useState(0);
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
  // SAVE & LOAD TEMP SETTINGS
  // ============================================
  const saveTempSettings = async () => {
    try {
      await AsyncStorage.setItem(
        FAN_TEMP_ON_KEY,
        JSON.stringify(settings.temp_on),
      );
      await AsyncStorage.setItem(
        FAN_TEMP_OFF_KEY,
        JSON.stringify(settings.temp_off),
      );
      console.log("✅ Fan temp settings saved:", settings);
    } catch (error) {
      console.log("Error saving fan temp settings:", error);
    }
  };

  const loadTempSettings = async () => {
    try {
      const tempOn = await AsyncStorage.getItem(FAN_TEMP_ON_KEY);
      const tempOff = await AsyncStorage.getItem(FAN_TEMP_OFF_KEY);

      if (tempOn || tempOff) {
        setSettings({
          temp_on: tempOn ? JSON.parse(tempOn) : 32,
          temp_off: tempOff ? JSON.parse(tempOff) : 28,
        });
        console.log("✅ Fan temp settings loaded:", {
          temp_on: tempOn ? JSON.parse(tempOn) : 32,
          temp_off: tempOff ? JSON.parse(tempOff) : 28,
        });
      }
    } catch (error) {
      console.log("Error loading fan temp settings:", error);
    }
  };

  // ============================================
  // FETCH DATA
  // ============================================
  const fetchData = async () => {
    try {
      console.log("📥 Fetching fan data from ESP32...");
      const response = await automation.fan.getStatus();
      const data = response.data;

      console.log("📥 ESP32 Data:", data);

      const fanStatusValue = data.fan === 1 ? "ON" : "OFF";
      setFanStatus(fanStatusValue);
      setTemperature(data.temperature || 0);
      setHumidity(data.humidity || 0);

      console.log("📥 Fan status:", fanStatusValue);
    } catch (error: any) {
      console.error("❌ Error fetching fan data:", error);
      Alert.alert(
        "Error",
        "Failed to load fan data. Please check connection to ESP32.",
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
      await loadTempSettings();
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
  // TOGGLE FAN
  // ============================================
  const toggleFan = async () => {
    if (fanAutoMode) return; // Block manual control when auto mode is on

    const newStatus = fanStatus === "ON" ? "OFF" : "ON";
    try {
      console.log("🔄 Toggling fan to:", newStatus);
      await automation.fan.toggle(newStatus);
      setFanStatus(newStatus);
      fetchData();
    } catch (error: any) {
      console.error("❌ Toggle error:", error);
      Alert.alert("Error", "Failed to toggle fan. Please try again.");
    }
  };

  // ============================================
  // TOGGLE AUTO MODE
  // ============================================
  const toggleAutoMode = async () => {
    const newMode = !fanAutoMode;
    await setFanAutoMode(newMode);

    if (newMode) {
      Alert.alert(
        "🤖 Auto Mode Enabled",
        `Fan will turn ON when temperature reaches ${settings.temp_on}°C and OFF when it drops below ${settings.temp_off}°C.\n\n🔒 Manual controls are now disabled.`,
        [{ text: "OK" }],
      );
      await automationScheduler.startFanScheduler();
    } else {
      automationScheduler.stopFanScheduler();
      Alert.alert("Auto Mode Disabled", "Manual control restored.", [
        { text: "OK" },
      ]);
    }
  };

  // ============================================
  // UPDATE TEMP SETTINGS
  // ============================================
  const updateTempOn = (value: string) => {
    const numValue = parseInt(value) || 0;
    if (numValue > 0 && numValue > settings.temp_off) {
      const newSettings = { ...settings, temp_on: numValue };
      setSettings(newSettings);
      saveTempSettings();
    }
  };

  const updateTempOff = (value: string) => {
    const numValue = parseInt(value) || 0;
    if (numValue > 0 && numValue < settings.temp_on) {
      const newSettings = { ...settings, temp_off: numValue };
      setSettings(newSettings);
      saveTempSettings();
    }
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
          <FontAwesome5
            name="fan"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Fan Control
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Temperature-based automatic ventilation
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
            backgroundColor: fanAutoMode ? colors.successLight : colors.card,
            borderColor: fanAutoMode ? colors.success : colors.border,
          },
        ]}
      >
        <Ionicons
          name={fanAutoMode ? "checkmark-circle" : "time-outline"}
          size={20}
          color={fanAutoMode ? colors.success : colors.textMuted}
        />
        <Text
          style={[
            styles.autoStatusText,
            { color: fanAutoMode ? colors.success : colors.textMuted },
          ]}
        >
          {fanAutoMode ? "Auto Mode is ACTIVE" : "Auto Mode is OFF"}
        </Text>
        {fanAutoMode && (
          <Text style={[styles.autoStatusSubtext, { color: colors.textMuted }]}>
            ON at {settings.temp_on}°C | OFF at {settings.temp_off}°C
          </Text>
        )}
      </View>

      {/* Fan Status Card */}
      <View style={[styles.statusCard, { backgroundColor: colors.card }]}>
        <View
          style={[
            styles.fanIconContainer,
            {
              backgroundColor: colors.backgroundSecondary || colors.card + "80",
            },
          ]}
        >
          <FontAwesome5
            name="fan"
            size={40}
            color={fanStatus === "ON" ? colors.primary : colors.textMuted}
          />
        </View>
        <Text style={[styles.fanStatusLabel, { color: colors.textMuted }]}>
          Fan is
        </Text>
        <Text
          style={[
            styles.fanStatusText,
            fanStatus === "ON"
              ? [styles.statusOn, { color: colors.success }]
              : [styles.statusOff, { color: colors.danger }],
          ]}
        >
          {fanStatus === "ON" ? "RUNNING" : "OFF"}
        </Text>

        <TouchableOpacity
          style={[
            styles.toggleBtn,
            fanStatus === "ON"
              ? [styles.toggleOn, { backgroundColor: colors.danger }]
              : [styles.toggleOff, { backgroundColor: colors.success }],
            fanAutoMode && styles.disabledBtn,
          ]}
          onPress={toggleFan}
          disabled={fanAutoMode}
        >
          <Text style={styles.toggleBtnText}>
            {fanAutoMode
              ? "🔒 Auto Mode ON"
              : fanStatus === "ON"
                ? "Turn OFF"
                : "Turn ON"}
          </Text>
        </TouchableOpacity>

        {fanAutoMode && (
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

      {/* Automation Settings */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="settings-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Automation Settings
          </Text>
        </View>
        <View
          style={[
            styles.settingCard,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <Ionicons
                name="rocket-outline"
                size={18}
                color={colors.text}
                style={{ marginRight: 8 }}
              />
              <Text style={[styles.settingLabel, { color: colors.text }]}>
                Auto Mode
              </Text>
            </View>
            <Switch
              value={fanAutoMode}
              onValueChange={toggleAutoMode}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={fanAutoMode ? "#FFFFFF" : "#f4f3f4"}
            />
          </View>
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <FontAwesome5
                name="temperature-high"
                size={16}
                color={colors.text}
                style={{ marginRight: 8 }}
              />
              <Text style={[styles.settingLabel, { color: colors.text }]}>
                Turn ON at
              </Text>
            </View>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <TextInput
                style={[
                  styles.tempInput,
                  {
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    color: colors.text,
                    width: 50,
                  },
                ]}
                value={String(settings.temp_on)}
                onChangeText={updateTempOn}
                keyboardType="numeric"
                editable={true}
              />
              <Text style={[styles.settingValue, { color: colors.primary }]}>
                °C
              </Text>
            </View>
          </View>
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <FontAwesome5
                name="temperature-low"
                size={16}
                color={colors.text}
                style={{ marginRight: 8 }}
              />
              <Text style={[styles.settingLabel, { color: colors.text }]}>
                Turn OFF at
              </Text>
            </View>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <TextInput
                style={[
                  styles.tempInput,
                  {
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    color: colors.text,
                    width: 50,
                  },
                ]}
                value={String(settings.temp_off)}
                onChangeText={updateTempOff}
                keyboardType="numeric"
                editable={true}
              />
              <Text style={[styles.settingValue, { color: colors.primary }]}>
                °C
              </Text>
            </View>
          </View>
        </View>
      </View>

      {/* Current Conditions */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="stats-chart-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Current Conditions
          </Text>
        </View>
        <View
          style={[
            styles.conditionCard,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={styles.conditionItem}>
            <FontAwesome5
              name="thermometer-half"
              size={28}
              color={colors.orange}
            />
            <Text style={[styles.conditionValue, { color: colors.text }]}>
              {temperature.toFixed(1)}°C
            </Text>
            <Text style={[styles.conditionLabel, { color: colors.textMuted }]}>
              Temperature
            </Text>
          </View>
          <View
            style={[
              styles.conditionDivider,
              { backgroundColor: colors.border },
            ]}
          />
          <View style={styles.conditionItem}>
            <FontAwesome5 name="tint" size={28} color={colors.info} />
            <Text style={[styles.conditionValue, { color: colors.text }]}>
              {humidity.toFixed(0)}%
            </Text>
            <Text style={[styles.conditionLabel, { color: colors.textMuted }]}>
              Humidity
            </Text>
          </View>
        </View>
      </View>

      {/* Recent Activity */}
      <View style={[styles.section, styles.lastSection]}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="time-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Recent Activity
          </Text>
        </View>
        <View
          style={[
            styles.logCard,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={[styles.logItem, { borderColor: colors.border }]}>
            <Text style={[styles.logTime, { color: colors.textMuted }]}>
              {new Date().toLocaleTimeString()}
            </Text>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <Ionicons
                name="sync-outline"
                size={14}
                color={fanStatus === "ON" ? colors.primary : colors.danger}
                style={{ marginRight: 6 }}
              />
              <Text style={[styles.logAction, { color: colors.text }]}>
                {fanAutoMode
                  ? "Auto Mode"
                  : fanStatus === "ON"
                    ? "Fan ON"
                    : "Fan OFF"}
              </Text>
            </View>
            <Text style={[styles.logTemp, { color: colors.textMuted }]}>
              {temperature.toFixed(1)}°C
            </Text>
          </View>
        </View>
      </View>
    </ScrollView>
  );
}

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
  fanIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  fanStatusLabel: {
    fontSize: 14,
  },
  fanStatusText: {
    fontSize: 28,
    fontWeight: "800",
    marginVertical: 4,
  },
  statusOn: {},
  statusOff: {},
  toggleBtn: {
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 30,
    marginTop: 12,
  },
  toggleOn: {},
  toggleOff: {},
  toggleBtnText: {
    fontSize: 16,
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
  section: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
  },
  settingCard: {
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  settingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 8,
    borderBottomWidth: 1,
  },
  settingLabel: {
    fontSize: 14,
  },
  settingValue: {
    fontSize: 14,
    fontWeight: "700",
  },
  tempInput: {
    paddingHorizontal: 4,
    paddingVertical: 4,
    borderWidth: 1,
    borderRadius: 6,
    textAlign: "center",
    fontSize: 14,
  },
  conditionCard: {
    flexDirection: "row",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  conditionItem: {
    flex: 1,
    alignItems: "center",
  },
  conditionValue: {
    fontSize: 20,
    fontWeight: "700",
    marginTop: 4,
  },
  conditionLabel: {
    fontSize: 12,
    marginTop: 2,
  },
  conditionDivider: {
    width: 1,
  },
  lastSection: {
    paddingBottom: 20,
  },
  logCard: {
    borderRadius: 12,
    padding: 4,
    borderWidth: 1,
  },
  logItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: 12,
    borderBottomWidth: 1,
  },
  logTime: {
    fontSize: 12,
  },
  logAction: {
    fontSize: 13,
    fontWeight: "500",
  },
  logTemp: {
    fontSize: 12,
  },
});

export default FanControlScreen;
