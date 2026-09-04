// src/screens/Automation/FanControlScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { automation } from "../../api/endpoints";
import { useTheme } from "../../hooks/useTheme";

function FanControlScreen() {
  const { colors } = useTheme();
  const [fanStatus, setFanStatus] = useState("OFF");
  const [settings, setSettings] = useState({
    auto_mode: true,
    temp_on: 32,
    temp_off: 28,
  });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [temperature, setTemperature] = useState(0);
  const [humidity, setHumidity] = useState(0);

  // Fetch data from ESP32
  const fetchData = async () => {
    try {
      console.log("📥 Fetching fan data from ESP32...");
      const response = await automation.fan.getStatus();

      // ESP32 response from /sensor: { temperature, humidity, fan, pump, light, gate }
      const data = response.data;

      console.log("📥 ESP32 Data:", data);

      // Update fan status
      const fanStatusValue = data.fan === 1 ? "ON" : "OFF";
      setFanStatus(fanStatusValue);

      // Update temperature and humidity
      setTemperature(data.temperature || 0);
      setHumidity(data.humidity || 0);

      // For settings - you can store these in ESP32 or keep local
      // For now, we'll keep them as is or you can add endpoints to save/load settings
      // setSettings({
      //   auto_mode: data.auto_mode || true,
      //   temp_on: data.temp_on || 32,
      //   temp_off: data.temp_off || 28,
      // });

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

  useEffect(() => {
    fetchData();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // Toggle Fan ON/OFF
  const toggleFan = async () => {
    const newStatus = fanStatus === "ON" ? "OFF" : "ON";
    try {
      console.log("🔄 Toggling fan to:", newStatus);
      await automation.fan.toggle(newStatus);
      setFanStatus(newStatus);
      Alert.alert("Success", `Fan turned ${newStatus}`);
      fetchData(); // Refresh to get updated status
    } catch (error: any) {
      console.error("❌ Toggle error:", error);
      Alert.alert("Error", "Failed to toggle fan. Please try again.");
    }
  };

  // Toggle Auto Mode
  const toggleAutoMode = async () => {
    const newMode = !settings.auto_mode;
    try {
      console.log("🔄 Toggling auto mode to:", newMode);
      await automation.fan.updateSettings({
        auto_mode: newMode,
        temp_on: settings.temp_on,
        temp_off: settings.temp_off,
      });
      setSettings({ ...settings, auto_mode: newMode });
      Alert.alert("Success", `Auto mode ${newMode ? "enabled" : "disabled"}`);
      fetchData();
    } catch (error: any) {
      console.error("❌ Toggle auto mode error:", error);
      Alert.alert("Error", "Failed to update settings. Please try again.");
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

        {/* Toggle Button */}
        <TouchableOpacity
          style={[
            styles.toggleBtn,
            fanStatus === "ON"
              ? [styles.toggleOn, { backgroundColor: colors.danger }]
              : [styles.toggleOff, { backgroundColor: colors.success }],
          ]}
          onPress={toggleFan}
        >
          <Text style={styles.toggleBtnText}>
            {fanStatus === "ON" ? "Turn OFF" : "Turn ON"}
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
            <Text style={[styles.settingValue, { color: colors.primary }]}>
              {settings.temp_on}°C
            </Text>
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
            <Text style={[styles.settingValue, { color: colors.primary }]}>
              {settings.temp_off}°C
            </Text>
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

      {/* Recent Activity - Sample data or from ESP32 logs */}
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
