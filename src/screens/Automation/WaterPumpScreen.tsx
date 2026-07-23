// src/screens/Automation/WaterPumpScreen.tsx
import React, { useEffect, useState } from "react";
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
import api from "../../api/client";

function WaterPumpScreen() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [autoMode, setAutoMode] = useState(true);
  const [customDuration, setCustomDuration] = useState("30");

  const fetchData = async () => {
    try {
      const response = await api.get("/automation/pump");
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching pump data:", error);
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

  const handleWaterRelease = async (duration: number) => {
    try {
      const response = await api.post("/automation/pump", {
        action: "water",
        duration,
      });
      if (response.data.success) {
        const amount = response.data.data.amount;
        Alert.alert("Success", `Released ${amount} L of water`);
        fetchData();
      }
    } catch (error) {
      Alert.alert("Error", "Failed to release water");
    }
  };

  const togglePump = async () => {
    const currentStatus = data?.pump?.status || "OFF";
    const newStatus = currentStatus === "ON" ? "OFF" : "ON";
    try {
      await api.post("/automation/pump", {
        action: "toggle",
        status: newStatus,
      });
      fetchData();
    } catch (error) {
      Alert.alert("Error", "Failed to toggle pump");
    }
  };

  const toggleAutoMode = async () => {
    const newMode = !autoMode;
    setAutoMode(newMode);
    Alert.alert("Success", `Auto mode ${newMode ? "enabled" : "disabled"}`);
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  const pumpStatus = data?.pump?.status || "OFF";
  const level = data?.inventory?.current_level || 0;
  const capacity = data?.inventory?.capacity || 2000;
  const percentage = (level / capacity) * 100;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <Text style={styles.title}>💧 Water Pump</Text>
      <Text style={styles.subtitle}>Automated watering system</Text>

      {/* Pump Status */}
      <View style={styles.statusCard}>
        <View style={styles.pumpIconContainer}>
          <Text
            style={[styles.pumpIcon, pumpStatus === "ON" && styles.pumpIconOn]}
          >
            💧
          </Text>
        </View>
        <Text style={styles.pumpStatusLabel}>Water Pump is</Text>
        <Text
          style={[
            styles.pumpStatusText,
            pumpStatus === "ON" ? styles.statusOn : styles.statusOff,
          ]}
        >
          {pumpStatus === "ON" ? "RUNNING" : "STOPPED"}
        </Text>
        <TouchableOpacity
          style={[
            styles.toggleBtn,
            pumpStatus === "ON" ? styles.toggleOn : styles.toggleOff,
          ]}
          onPress={togglePump}
        >
          <Text style={styles.toggleBtnText}>
            {pumpStatus === "ON" ? "Stop Pump" : "Start Pump"}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Water Level */}
      <View style={styles.levelCard}>
        <Text style={styles.levelTitle}>📊 Water Level</Text>
        <View style={styles.tankContainer}>
          <View style={styles.tank}>
            <View
              style={[
                styles.tankFill,
                { height: `${Math.min(percentage, 100)}%` },
              ]}
            />
            <Text style={styles.tankLabel}>{percentage.toFixed(0)}%</Text>
          </View>
        </View>
        <Text style={styles.levelText}>
          {level.toFixed(0)} L / {capacity} L
        </Text>
      </View>

      {/* Auto Mode */}
      <View style={styles.autoCard}>
        <View style={styles.autoRow}>
          <View>
            <Text style={styles.autoLabel}>🤖 Auto Mode</Text>
            <Text style={styles.autoDesc}>
              {autoMode ? "Water released automatically" : "Manual mode only"}
            </Text>
          </View>
          <Switch
            value={autoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: "#FFD62E" }}
          />
        </View>
      </View>

      {/* Manual Release */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>✋ Manual Release</Text>
        <View style={styles.releaseButtons}>
          {[15, 30, 60].map((seconds) => {
            const amount = (seconds * 0.5).toFixed(1);
            return (
              <TouchableOpacity
                key={seconds}
                style={styles.releaseBtn}
                onPress={() => handleWaterRelease(seconds)}
              >
                <Text style={styles.releaseBtnText}>{seconds}s</Text>
                <Text style={styles.releaseSubtext}>{amount} L</Text>
              </TouchableOpacity>
            );
          })}
        </View>
        <View style={styles.customRelease}>
          <TextInput
            style={styles.customInput}
            value={customDuration}
            onChangeText={setCustomDuration}
            keyboardType="numeric"
            placeholder="30"
          />
          <TouchableOpacity
            style={styles.customBtn}
            onPress={() => handleWaterRelease(parseInt(customDuration) || 30)}
          >
            <Text style={styles.customBtnText}>Release</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Schedules */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📅 Watering Schedule</Text>
        {data?.schedules?.map((schedule: any, index: number) => (
          <View key={index} style={styles.scheduleItem}>
            <View style={styles.scheduleLeft}>
              <Text style={styles.scheduleTime}>
                {new Date(`2000-01-01T${schedule.time}:00`).toLocaleTimeString(
                  "en-US",
                  { hour: "numeric", minute: "2-digit" },
                )}
              </Text>
              <Text style={styles.scheduleDuration}>{schedule.duration}s</Text>
            </View>
            <View style={styles.scheduleRight}>
              <Text style={styles.scheduleLabel}>{schedule.label}</Text>
              <View
                style={[
                  styles.scheduleStatus,
                  schedule.enabled
                    ? styles.statusActive
                    : styles.statusInactive,
                ]}
              >
                <Text style={styles.scheduleStatusText}>
                  {schedule.enabled ? "Active" : "Off"}
                </Text>
              </View>
            </View>
          </View>
        ))}
      </View>

      {/* Recent Logs */}
      <View style={[styles.section, styles.lastSection]}>
        <Text style={styles.sectionTitle}>📋 Recent Activity</Text>
        {data?.logs?.slice(0, 5).map((log: any, index: number) => (
          <View key={index} style={styles.logItem}>
            <Text style={styles.logTime}>
              {new Date(log.timestamp).toLocaleTimeString()}
            </Text>
            <Text style={styles.logAction}>
              💧 Released {log.water_amount} L
            </Text>
            <Text style={styles.logTrigger}>{log.trigger}</Text>
          </View>
        ))}
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
  pumpIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: "#F0E8D8",
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  pumpIcon: {
    fontSize: 40,
    opacity: 0.5,
  },
  pumpIconOn: {
    opacity: 1,
  },
  pumpStatusLabel: {
    fontSize: 14,
    color: "#8B7355",
  },
  pumpStatusText: {
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
  levelCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 16,
    marginBottom: 16,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  levelTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#5C4A1E",
    marginBottom: 12,
  },
  tankContainer: {
    width: 120,
    height: 160,
    justifyContent: "center",
    alignItems: "center",
  },
  tank: {
    width: 100,
    height: 140,
    borderWidth: 4,
    borderColor: "#8B7355",
    borderRadius: 10,
    overflow: "hidden",
    backgroundColor: "#F0E8D8",
    position: "relative",
  },
  tankFill: {
    position: "absolute",
    bottom: 0,
    width: "100%",
    backgroundColor: "#2980B9",
  },
  tankLabel: {
    position: "absolute",
    top: "50%",
    left: "50%",
    transform: [{ translateX: -18 }, { translateY: -10 }],
    fontSize: 18,
    fontWeight: "800",
    color: "#FFFFFF",
    textShadowColor: "rgba(0,0,0,0.5)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 2,
  },
  levelText: {
    marginTop: 8,
    fontSize: 14,
    color: "#8B7355",
  },
  autoCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  autoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  autoLabel: {
    fontSize: 16,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  autoDesc: {
    fontSize: 12,
    color: "#8B7355",
    marginTop: 2,
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
  releaseButtons: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 10,
  },
  releaseBtn: {
    flex: 1,
    backgroundColor: "#2980B9",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    marginHorizontal: 4,
  },
  releaseBtnText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  releaseSubtext: {
    fontSize: 11,
    color: "rgba(255,255,255,0.8)",
    marginTop: 2,
  },
  customRelease: {
    flexDirection: "row",
    gap: 10,
  },
  customInput: {
    flex: 1,
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: "rgba(41, 128, 185, 0.3)",
    fontSize: 16,
  },
  customBtn: {
    backgroundColor: "#2980B9",
    borderRadius: 12,
    paddingHorizontal: 20,
    justifyContent: "center",
  },
  customBtnText: {
    fontSize: 14,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  scheduleItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  scheduleLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 16,
  },
  scheduleTime: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
    width: 70,
  },
  scheduleDuration: {
    fontSize: 14,
    color: "#5C4A1E",
  },
  scheduleRight: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  scheduleLabel: {
    fontSize: 12,
    color: "#8B7355",
  },
  scheduleStatus: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusActive: {
    backgroundColor: "#E8F5E9",
  },
  statusInactive: {
    backgroundColor: "#FDEDEC",
  },
  scheduleStatusText: {
    fontSize: 11,
    fontWeight: "600",
  },
  lastSection: {
    paddingBottom: 20,
  },
  logItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 10,
    padding: 12,
    marginBottom: 6,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.05)",
  },
  logTime: {
    fontSize: 12,
    color: "#8B7355",
    width: 70,
  },
  logAction: {
    flex: 1,
    fontSize: 13,
    color: "#3E2C1C",
  },
  logTrigger: {
    fontSize: 11,
    color: "#8B7355",
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
    backgroundColor: "#F0E8D8",
  },
});

export default WaterPumpScreen;
