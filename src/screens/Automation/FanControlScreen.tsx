// src/screens/Automation/FanControlScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
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

function FanControlScreen() {
  const { colors } = useTheme();
  const [fanStatus, setFanStatus] = useState("OFF");
  const [settings, setSettings] = useState({
    auto_mode: false,
    temp_on: 32,
    temp_off: 28,
  });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [temperature, setTemperature] = useState(0);
  const [humidity, setHumidity] = useState(0);
  const [currentTime, setCurrentTime] = useState("");

  const intervalRef = useRef<number | null>(null);
  const timeIntervalRef = useRef<number | null>(null);

  // ============================================
  // PHILIPPINE TIME (UTC+8)
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

  // Fetch data from ESP32
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
      console.log("📥 Temperature:", temperature, "Humidity:", humidity);
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

  useEffect(() => {
    fetchData();
    updateCurrentTime();
    timeIntervalRef.current = setInterval(updateCurrentTime, 1000);
    return () => {
      if (timeIntervalRef.current) clearInterval(timeIntervalRef.current);
    };
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // Toggle Fan ON/OFF
  const toggleFan = async () => {
    if (settings.auto_mode) {
      Alert.alert("Auto Mode ON", "Manual control is disabled in Auto Mode.");
      return;
    }
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
  // AUTO MODE - Temperature-based
  // ============================================
  const toggleAutoMode = () => {
    const newMode = !settings.auto_mode;
    setSettings({ ...settings, auto_mode: newMode });

    if (newMode) {
      Alert.alert(
        "🤖 Auto Mode Enabled",
        `Fan will turn ON when temperature reaches ${settings.temp_on}°C and OFF when it drops below ${settings.temp_off}°C.\n\n🔒 Manual controls are now disabled.`,
        [{ text: "OK" }],
      );
      startTemperatureScheduler();
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

  // Temperature-based auto scheduler
  const startTemperatureScheduler = () => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }

    intervalRef.current = setInterval(async () => {
      if (!settings.auto_mode) return;

      try {
        // Get latest temperature
        const response = await automation.fan.getStatus();
        const data = response.data;
        const currentTemp = data.temperature || 0;
        setTemperature(currentTemp);

        console.log(
          `🌡️ Auto check: ${currentTemp}°C | ON at ${settings.temp_on}°C | OFF at ${settings.temp_off}°C`,
        );

        // Check if fan should turn ON
        if (currentTemp >= settings.temp_on && fanStatus === "OFF") {
          console.log(
            `🔥 Temperature ${currentTemp}°C >= ${settings.temp_on}°C - Turning FAN ON`,
          );
          await automation.fan.toggle("ON");
          setFanStatus("ON");
        }
        // Check if fan should turn OFF
        else if (currentTemp <= settings.temp_off && fanStatus === "ON") {
          console.log(
            `❄️ Temperature ${currentTemp}°C <= ${settings.temp_off}°C - Turning FAN OFF`,
          );
          await automation.fan.toggle("OFF");
          setFanStatus("OFF");
        }
      } catch (error) {
        console.error("❌ Auto scheduler error:", error);
      }
    }, 10000); // Check every 10 seconds
  };

  // Update temp_on setting
  const updateTempOn = (value: string) => {
    const numValue = parseInt(value) || 0;
    if (numValue > 0 && numValue > settings.temp_off) {
      setSettings({ ...settings, temp_on: numValue });
    }
  };

  // Update temp_off setting
  const updateTempOff = (value: string) => {
    const numValue = parseInt(value) || 0;
    if (numValue > 0 && numValue < settings.temp_on) {
      setSettings({ ...settings, temp_off: numValue });
    }
  };

  // Cleanup interval on unmount
  useEffect(() => {
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      if (timeIntervalRef.current) clearInterval(timeIntervalRef.current);
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
            backgroundColor: settings.auto_mode
              ? colors.successLight
              : colors.card,
            borderColor: settings.auto_mode ? colors.success : colors.border,
          },
        ]}
      >
        <Ionicons
          name={settings.auto_mode ? "checkmark-circle" : "time-outline"}
          size={20}
          color={settings.auto_mode ? colors.success : colors.textMuted}
        />
        <Text
          style={[
            styles.autoStatusText,
            { color: settings.auto_mode ? colors.success : colors.textMuted },
          ]}
        >
          {settings.auto_mode ? "Auto Mode is ACTIVE" : "Auto Mode is OFF"}
        </Text>
        {settings.auto_mode && (
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
            settings.auto_mode && styles.disabledBtn,
          ]}
          onPress={toggleFan}
          disabled={fanStatus === "ON" || settings.auto_mode}
        >
          <Text style={styles.toggleBtnText}>
            {settings.auto_mode
              ? "🔒 Auto Mode ON"
              : fanStatus === "ON"
                ? "Turn OFF"
                : "Turn ON"}
          </Text>
        </TouchableOpacity>
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
              value={settings.auto_mode}
              onValueChange={toggleAutoMode}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={settings.auto_mode ? "#FFFFFF" : "#f4f3f4"}
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

      {/* Current Conditions - Real-time from ESP32 */}
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
            {settings.auto_mode && (
              <Text
                style={[
                  styles.conditionStatus,
                  {
                    color:
                      temperature >= settings.temp_on
                        ? colors.danger
                        : temperature <= settings.temp_off
                          ? colors.success
                          : colors.warning,
                  },
                ]}
              >
                {temperature >= settings.temp_on
                  ? "🔥 High"
                  : temperature <= settings.temp_off
                    ? "❄️ Low"
                    : "🌡️ Normal"}
              </Text>
            )}
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
                {fanStatus === "ON" ? "Fan ON" : "Fan OFF"}
              </Text>
            </View>
            <Text style={[styles.logTemp, { color: colors.textMuted }]}>
              {temperature.toFixed(1)}°C
            </Text>
          </View>
          <View style={[styles.logItem, { borderColor: colors.border }]}>
            <Text style={[styles.logTime, { color: colors.textMuted }]}>
              {new Date(Date.now() - 3600000).toLocaleTimeString()}
            </Text>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <Ionicons
                name="person-outline"
                size={14}
                color={colors.warning}
                style={{ marginRight: 6 }}
              />
              <Text style={[styles.logAction, { color: colors.text }]}>
                Manual ON
              </Text>
            </View>
            <Text style={[styles.logTemp, { color: colors.textMuted }]}>
              31.0°C
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
  disabledBtn: {
    opacity: 0.5,
  },
  toggleBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#FFFFFF",
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
  conditionStatus: {
    fontSize: 11,
    fontWeight: "600",
    marginTop: 4,
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
