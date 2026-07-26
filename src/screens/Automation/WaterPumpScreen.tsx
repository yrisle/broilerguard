// src/screens/Automation/WaterPumpScreen.tsx
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
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

function WaterPumpScreen() {
  const { colors } = useTheme();
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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const pumpStatus = data?.pump?.status || "OFF";
  const level = data?.inventory?.current_level || 0;
  const capacity = data?.inventory?.capacity || 2000;
  const percentage = (level / capacity) * 100;

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
            name="water"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>Water Pump</Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Automated watering system
        </Text>
      </View>

      <View style={[styles.statusCard, { backgroundColor: colors.card }]}>
        <View
          style={[
            styles.pumpIconContainer,
            {
              backgroundColor: colors.backgroundSecondary || colors.card + "80",
            },
          ]}
        >
          <FontAwesome5
            name="water"
            size={40}
            color={pumpStatus === "ON" ? colors.info : colors.textMuted}
          />
        </View>
        <Text style={[styles.pumpStatusLabel, { color: colors.textMuted }]}>
          Water Pump is
        </Text>
        <Text
          style={[
            styles.pumpStatusText,
            pumpStatus === "ON"
              ? [styles.statusOn, { color: colors.success }]
              : [styles.statusOff, { color: colors.danger }],
          ]}
        >
          {pumpStatus === "ON" ? "RUNNING" : "STOPPED"}
        </Text>
        <TouchableOpacity
          style={[
            styles.toggleBtn,
            pumpStatus === "ON"
              ? [styles.toggleOn, { backgroundColor: colors.danger }]
              : [styles.toggleOff, { backgroundColor: colors.success }],
          ]}
          onPress={togglePump}
        >
          <Text style={styles.toggleBtnText}>
            {pumpStatus === "ON" ? "Stop Pump" : "Start Pump"}
          </Text>
        </TouchableOpacity>
      </View>

      <View style={[styles.levelCard, { backgroundColor: colors.card }]}>
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
          <Text style={[styles.levelTitle, { color: colors.textSecondary }]}>
            Water Level
          </Text>
        </View>
        <View style={styles.tankContainer}>
          <View style={[styles.tank, { borderColor: colors.textMuted }]}>
            <View
              style={[
                styles.tankFill,
                {
                  height: `${Math.min(percentage, 100)}%`,
                  backgroundColor: colors.info,
                },
              ]}
            />
            <Text style={styles.tankLabel}>{percentage.toFixed(0)}%</Text>
          </View>
        </View>
        <Text style={[styles.levelText, { color: colors.textMuted }]}>
          {level.toFixed(0)} L / {capacity} L
        </Text>
      </View>

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
              {autoMode ? "Water released automatically" : "Manual mode only"}
            </Text>
          </View>
          <Switch
            value={autoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: colors.primary }}
          />
        </View>
      </View>

      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="hand-left-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Manual Release
          </Text>
        </View>
        <View style={styles.releaseButtons}>
          {[15, 30, 60].map((seconds) => {
            const amount = (seconds * 0.5).toFixed(1);
            return (
              <TouchableOpacity
                key={seconds}
                style={[styles.releaseBtn, { backgroundColor: colors.info }]}
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
            style={[
              styles.customInput,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                color: colors.text,
              },
            ]}
            value={customDuration}
            onChangeText={setCustomDuration}
            keyboardType="numeric"
            placeholder="30"
            placeholderTextColor={colors.textMuted}
          />
          <TouchableOpacity
            style={[styles.customBtn, { backgroundColor: colors.info }]}
            onPress={() => handleWaterRelease(parseInt(customDuration) || 30)}
          >
            <Text style={styles.customBtnText}>Release</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="calendar"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Watering Schedule
          </Text>
        </View>
        {data?.schedules?.map((schedule: any, index: number) => (
          <View
            key={index}
            style={[
              styles.scheduleItem,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <View style={styles.scheduleLeft}>
              <Text style={[styles.scheduleTime, { color: colors.text }]}>
                {new Date(`2000-01-01T${schedule.time}:00`).toLocaleTimeString(
                  "en-US",
                  {
                    hour: "numeric",
                    minute: "2-digit",
                  },
                )}
              </Text>
              <Text
                style={[
                  styles.scheduleDuration,
                  { color: colors.textSecondary },
                ]}
              >
                {schedule.duration}s
              </Text>
            </View>
            <View style={styles.scheduleRight}>
              <Text style={[styles.scheduleLabel, { color: colors.textMuted }]}>
                {schedule.label}
              </Text>
              <View
                style={[
                  styles.scheduleStatus,
                  schedule.enabled
                    ? [
                        styles.statusActive,
                        { backgroundColor: colors.successLight },
                      ]
                    : [
                        styles.statusInactive,
                        { backgroundColor: colors.dangerLight },
                      ],
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
        {data?.logs?.slice(0, 5).map((log: any, index: number) => (
          <View
            key={index}
            style={[
              styles.logItem,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <Text style={[styles.logTime, { color: colors.textMuted }]}>
              {new Date(log.timestamp).toLocaleTimeString()}
            </Text>
            <View
              style={{ flexDirection: "row", alignItems: "center", flex: 1 }}
            >
              <FontAwesome5
                name="water"
                size={12}
                color={colors.info}
                style={{ marginRight: 6 }}
              />
              <Text style={[styles.logAction, { color: colors.text }]}>
                Released {log.water_amount} L
              </Text>
            </View>
            <Text
              style={[
                styles.logTrigger,
                {
                  color: colors.textMuted,
                  backgroundColor: colors.backgroundSecondary,
                },
              ]}
            >
              {log.trigger}
            </Text>
          </View>
        ))}
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
  pumpIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  pumpStatusLabel: {
    fontSize: 14,
  },
  pumpStatusText: {
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
  levelCard: {
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 16,
    marginBottom: 16,
    alignItems: "center",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  levelTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
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
    borderRadius: 10,
    overflow: "hidden",
    position: "relative",
    backgroundColor: "#F0E8D8",
  },
  tankFill: {
    position: "absolute",
    bottom: 0,
    width: "100%",
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
  },
  autoLabel: {
    fontSize: 16,
    fontWeight: "600",
  },
  autoDesc: {
    fontSize: 12,
    marginTop: 2,
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
  releaseButtons: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 10,
  },
  releaseBtn: {
    flex: 1,
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
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
    fontSize: 16,
  },
  customBtn: {
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
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
  },
  scheduleLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 16,
  },
  scheduleTime: {
    fontSize: 16,
    fontWeight: "700",
    width: 70,
  },
  scheduleDuration: {
    fontSize: 14,
  },
  scheduleRight: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  scheduleLabel: {
    fontSize: 12,
  },
  scheduleStatus: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusActive: {},
  statusInactive: {},
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
    borderRadius: 10,
    padding: 12,
    marginBottom: 6,
    borderWidth: 1,
  },
  logTime: {
    fontSize: 12,
    width: 70,
  },
  logAction: {
    flex: 1,
    fontSize: 13,
  },
  logTrigger: {
    fontSize: 11,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
});

export default WaterPumpScreen;
