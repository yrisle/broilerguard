// src/screens/Automation/FanControlScreen.tsx
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
import api from "../../api/client";

function FanControlScreen() {
  const [fanStatus, setFanStatus] = useState("OFF");
  const [settings, setSettings] = useState({
    auto_mode: true,
    temp_on: 32,
    temp_off: 28,
  });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const response = await api.get("/automation/fan");
      if (response.data.success) {
        setFanStatus(response.data.data.status);
        setSettings(response.data.data.settings);
      }
    } catch (error) {
      console.error("Error fetching fan data:", error);
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

  const toggleFan = async () => {
    const newStatus = fanStatus === "ON" ? "OFF" : "ON";
    try {
      const response = await api.post("/automation/fan", {
        action: "toggle",
        status: newStatus,
      });
      if (response.data.success) {
        setFanStatus(newStatus);
        Alert.alert("Success", `Fan turned ${newStatus}`);
      }
    } catch (error) {
      Alert.alert("Error", "Failed to toggle fan");
    }
  };

  const toggleAutoMode = async () => {
    const newMode = !settings.auto_mode;
    try {
      const response = await api.post("/automation/fan", {
        action: "settings",
        auto_mode: newMode,
        temp_on: settings.temp_on,
        temp_off: settings.temp_off,
      });
      if (response.data.success) {
        setSettings({ ...settings, auto_mode: newMode });
        Alert.alert("Success", `Auto mode ${newMode ? "enabled" : "disabled"}`);
      }
    } catch (error) {
      Alert.alert("Error", "Failed to update settings");
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <Text style={styles.title}>🌀 Fan Control</Text>
      <Text style={styles.subtitle}>
        Temperature-based automatic ventilation
      </Text>

      {/* Fan Status */}
      <View style={styles.statusCard}>
        <View style={styles.fanIconContainer}>
          <Text
            style={[styles.fanIcon, fanStatus === "ON" && styles.fanIconOn]}
          >
            🌀
          </Text>
        </View>
        <Text style={styles.fanStatusLabel}>Fan is</Text>
        <Text
          style={[
            styles.fanStatusText,
            fanStatus === "ON" ? styles.statusOn : styles.statusOff,
          ]}
        >
          {fanStatus === "ON" ? "RUNNING" : "OFF"}
        </Text>
        <TouchableOpacity
          style={[
            styles.toggleBtn,
            fanStatus === "ON" ? styles.toggleOn : styles.toggleOff,
          ]}
          onPress={toggleFan}
        >
          <Text style={styles.toggleBtnText}>
            {fanStatus === "ON" ? "Turn OFF" : "Turn ON"}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Settings */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>⚙️ Automation Settings</Text>
        <View style={styles.settingCard}>
          <View style={styles.settingRow}>
            <Text style={styles.settingLabel}>🤖 Auto Mode</Text>
            <Switch
              value={settings.auto_mode}
              onValueChange={toggleAutoMode}
              trackColor={{ false: "#E0D5C0", true: "#FFD62E" }}
            />
          </View>
          <View style={styles.settingRow}>
            <Text style={styles.settingLabel}>🌡️ Turn ON at</Text>
            <Text style={styles.settingValue}>{settings.temp_on}°C</Text>
          </View>
          <View style={styles.settingRow}>
            <Text style={styles.settingLabel}>🌡️ Turn OFF at</Text>
            <Text style={styles.settingValue}>{settings.temp_off}°C</Text>
          </View>
        </View>
      </View>

      {/* Current Conditions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📊 Current Conditions</Text>
        <View style={styles.conditionCard}>
          <View style={styles.conditionItem}>
            <Text style={styles.conditionIcon}>🌡️</Text>
            <Text style={styles.conditionValue}>32.5°C</Text>
            <Text style={styles.conditionLabel}>Temperature</Text>
          </View>
          <View style={styles.conditionDivider} />
          <View style={styles.conditionItem}>
            <Text style={styles.conditionIcon}>💧</Text>
            <Text style={styles.conditionValue}>65%</Text>
            <Text style={styles.conditionLabel}>Humidity</Text>
          </View>
        </View>
      </View>

      {/* Activity Log */}
      <View style={[styles.section, styles.lastSection]}>
        <Text style={styles.sectionTitle}>📋 Recent Activity</Text>
        <View style={styles.logCard}>
          <View style={styles.logItem}>
            <Text style={styles.logTime}>10:30 AM</Text>
            <Text style={styles.logAction}>🔄 Auto ON</Text>
            <Text style={styles.logTemp}>32.5°C</Text>
          </View>
          <View style={styles.logItem}>
            <Text style={styles.logTime}>08:15 AM</Text>
            <Text style={styles.logAction}>🔄 Auto OFF</Text>
            <Text style={styles.logTemp}>28.0°C</Text>
          </View>
          <View style={styles.logItem}>
            <Text style={styles.logTime}>06:00 AM</Text>
            <Text style={styles.logAction}>👤 Manual ON</Text>
            <Text style={styles.logTemp}>31.0°C</Text>
          </View>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#FFFCF2",
  },
  centered: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  title: {
    fontSize: 24,
    fontWeight: "800",
    color: "#3E2C1C",
    paddingHorizontal: 20,
    paddingTop: 20,
  },
  subtitle: {
    fontSize: 14,
    color: "#8B7355",
    paddingHorizontal: 20,
    paddingBottom: 16,
  },
  statusCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 24,
    margin: 16,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  fanIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: "#F0E8D8",
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  fanIcon: {
    fontSize: 40,
    opacity: 0.5,
  },
  fanIconOn: {
    opacity: 1,
  },
  fanStatusLabel: {
    fontSize: 14,
    color: "#8B7355",
  },
  fanStatusText: {
    fontSize: 28,
    fontWeight: "800",
    marginVertical: 4,
  },
  statusOn: {
    color: "#27AE60",
  },
  statusOff: {
    color: "#E74C3C",
  },
  toggleBtn: {
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 30,
    marginTop: 12,
  },
  toggleOn: {
    backgroundColor: "#E74C3C",
  },
  toggleOff: {
    backgroundColor: "#27AE60",
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
    color: "#5C4A1E",
    marginBottom: 12,
  },
  settingCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  settingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255, 214, 46, 0.05)",
  },
  settingLabel: {
    fontSize: 14,
    color: "#3E2C1C",
  },
  settingValue: {
    fontSize: 14,
    fontWeight: "700",
    color: "#FFD62E",
  },
  conditionCard: {
    flexDirection: "row",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  conditionItem: {
    flex: 1,
    alignItems: "center",
  },
  conditionIcon: {
    fontSize: 28,
  },
  conditionValue: {
    fontSize: 20,
    fontWeight: "700",
    color: "#3E2C1C",
    marginTop: 4,
  },
  conditionLabel: {
    fontSize: 12,
    color: "#8B7355",
    marginTop: 2,
  },
  conditionDivider: {
    width: 1,
    backgroundColor: "rgba(255, 214, 46, 0.2)",
  },
  lastSection: {
    paddingBottom: 20,
  },
  logCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 4,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  logItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255, 214, 46, 0.05)",
  },
  logTime: {
    fontSize: 12,
    color: "#8B7355",
  },
  logAction: {
    fontSize: 13,
    fontWeight: "500",
    color: "#3E2C1C",
  },
  logTemp: {
    fontSize: 12,
    color: "#8B7355",
  },
});

export default FanControlScreen;
