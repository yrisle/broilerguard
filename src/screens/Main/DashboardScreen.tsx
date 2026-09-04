// src/screens/Main/DashboardScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
import Icon from "@expo/vector-icons/Ionicons";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useState } from "react";
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { notifications, sensors } from "../../api/endpoints";
import Card from "../../components/common/Card";
import { useAutomation } from "../../context/AutomationContext";
import { useTheme } from "../../hooks/useTheme";

const DashboardScreen = () => {
  const { colors } = useTheme();
  const router = useRouter();
  const { fanAutoMode, gateAutoMode, pumpAutoMode, lightAutoMode } =
    useAutomation();
  const [stats, setStats] = useState({
    // Environmental Conditions
    temperature: 0,
    humidity: 0,
    feedLevel: 50,
    waterLevel: 60,
    fanPhysicalStatus: "OFF",
    waterPumpPhysicalStatus: "OFF",
    lightStatus: "OFF",
    gateStatus: "CLOSED",

    // Automation Status
    fanStatus: "OFF",
    waterPump: "OFF",
    lightStatusDisplay: "OFF",

    // Chicken Health
    healthyChicks: 0,
    weakChicks: 0,
    unhealthyChicks: 0,
    totalChicks: 0,

    // Consumption
    feedConsumed: 0,
    waterConsumed: 0,
  });
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [notificationCount, setNotificationCount] = useState(0);

  const handleNavigate = (routeName: string) => {
    try {
      router.push(routeName as never);
    } catch {
      setErrorMessage("Navigation is unavailable right now. Please try again.");
    }
  };

  // Fetch notification count
  const fetchNotificationCount = async () => {
    try {
      const response = await notifications.getAll(1);
      if (response.data.success) {
        const unread = response.data.data.unread || 0;
        setNotificationCount(unread);
        console.log("📥 Notification count:", unread);
      }
    } catch (error) {
      console.log("Error fetching notification count:", error);
    }
  };

  const fetchDashboard = async () => {
    try {
      console.log("📱 Fetching dashboard data from ESP32...");

      // Get data from ESP32
      const sensorRes = await sensors.getCurrent();
      const sensorData = sensorRes.data || {};

      console.log("📥 ESP32 Data:", sensorData);

      // Get device status
      const fanStatus = sensorData.fan === 1 ? "ON" : "OFF";
      const pumpStatus = sensorData.pump === 1 ? "ON" : "OFF";
      const lightStatus = sensorData.light === 1 ? "ON" : "OFF";
      const gateStatus = sensorData.gate === 1 ? "OPEN" : "CLOSED";

      setStats({
        // Environmental Conditions - from ESP32
        temperature: sensorData.temperature || 0,
        humidity: sensorData.humidity || 0,
        feedLevel: 50,
        waterLevel: 60,
        fanPhysicalStatus: fanStatus,
        waterPumpPhysicalStatus: pumpStatus,
        lightStatus: lightStatus,
        gateStatus: gateStatus,

        // Automation Status - same as physical
        fanStatus: fanStatus,
        waterPump: pumpStatus,
        lightStatusDisplay: lightStatus,

        // Chicken Health (kung wala, zero)
        healthyChicks: 0,
        weakChicks: 0,
        unhealthyChicks: 0,
        totalChicks: 0,

        // Consumption
        feedConsumed: 0,
        waterConsumed: 0,
      });
      setErrorMessage(null);

      // Fetch notification count after dashboard data
      await fetchNotificationCount();
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "Unable to reach ESP32.";

      console.warn("ESP32 data unavailable:", message);
      setErrorMessage(
        "Unable to connect to ESP32. Please check your connection.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      console.log("📱 Dashboard focused - refreshing data...");
      fetchDashboard();
    }, []),
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchDashboard();
  };

  // Helper function para sa device status
  const getDeviceStatus = (physicalStatus: string, autoMode: boolean) => {
    if (autoMode) return "AUTO";
    return physicalStatus;
  };

  const getStatusColor = (status: string, colors: any) => {
    if (status === "AUTO") return colors.info;
    if (status === "OPEN") return colors.success;
    return status === "ON" ? colors.success : colors.danger;
  };

  const getStatusBgColor = (status: string, colors: any) => {
    if (status === "AUTO") return colors.infoLight;
    if (status === "OPEN") return colors.successLight;
    return status === "ON" ? colors.successLight : colors.dangerLight;
  };

  if (loading) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <Text style={{ color: colors.text }}>Connecting to ESP32...</Text>
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
        <View>
          <Text style={[styles.greeting, { color: colors.text }]}>
            Good Morning, Admin!
          </Text>
          <Text style={[styles.date, { color: colors.textMuted }]}>
            {new Date().toLocaleDateString("en-US", {
              weekday: "long",
              year: "numeric",
              month: "long",
              day: "numeric",
            })}
          </Text>
        </View>
        <TouchableOpacity
          style={styles.notificationBtn}
          onPress={() => handleNavigate("/notifications")}
        >
          <Icon name="notifications-outline" size={24} color={colors.text} />
          {notificationCount > 0 && (
            <View
              style={[
                styles.notificationBadge,
                { backgroundColor: colors.danger },
              ]}
            >
              <Text style={styles.badgeText}>
                {notificationCount > 99 ? "99+" : notificationCount}
              </Text>
            </View>
          )}
        </TouchableOpacity>
      </View>

      {errorMessage ? (
        <View
          style={[
            styles.errorBanner,
            {
              backgroundColor: colors.warningLight,
              borderColor: colors.warning,
            },
          ]}
        >
          <Text style={[styles.errorText, { color: colors.warning }]}>
            {errorMessage}
          </Text>
        </View>
      ) : null}

      {/* ============================================
      ENVIRONMENTAL CONDITIONS - From ESP32
      ============================================ */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <FontAwesome5
            name="thermometer-half"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Environmental Conditions
          </Text>
        </View>
        <View style={styles.row}>
          <Card style={styles.halfCard}>
            <View style={styles.tempContainer}>
              <Text style={[styles.tempValue, { color: colors.orange }]}>
                {stats.temperature}°C
              </Text>
              <Text style={[styles.tempLabel, { color: colors.textMuted }]}>
                Temperature
              </Text>
              <Text style={[styles.tempRange, { color: colors.textMuted }]}>
                Ideal: 30°C - 35°C
              </Text>
            </View>
          </Card>
          <Card style={styles.halfCard}>
            <View style={styles.tempContainer}>
              <Text style={[styles.tempValue, { color: colors.info }]}>
                {stats.humidity}%
              </Text>
              <Text style={[styles.tempLabel, { color: colors.textMuted }]}>
                Humidity
              </Text>
              <Text style={[styles.tempRange, { color: colors.textMuted }]}>
                Ideal: 55% - 80%
              </Text>
            </View>
          </Card>
        </View>
      </View>

      {/* ============================================
      DEVICE STATUS - ESP32 Devices with Auto Mode Indicator
      ============================================ */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Icon
            name="settings-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Device Status
          </Text>
        </View>

        <View style={styles.deviceRow}>
          {/* FAN */}
          <TouchableOpacity
            style={styles.deviceCardWrapper}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/fan-control")}
          >
            <Card style={styles.deviceCard}>
              <View style={styles.deviceItem}>
                <FontAwesome5 name="fan" size={24} color={colors.primary} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[styles.deviceLabel, { color: colors.primaryDark }]}
                  >
                    Fan
                  </Text>
                  <View
                    style={[
                      styles.statusIndicator,
                      {
                        backgroundColor: getStatusBgColor(
                          getDeviceStatus(stats.fanStatus, fanAutoMode),
                          colors,
                        ),
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.statusText,
                        {
                          color: getStatusColor(
                            getDeviceStatus(stats.fanStatus, fanAutoMode),
                            colors,
                          ),
                        },
                      ]}
                    >
                      {getDeviceStatus(stats.fanStatus, fanAutoMode)}
                    </Text>
                  </View>
                </View>
              </View>
            </Card>
          </TouchableOpacity>

          {/* WATER PUMP */}
          <TouchableOpacity
            style={styles.deviceCardWrapper}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/water-pump")}
          >
            <Card style={styles.deviceCard}>
              <View style={styles.deviceItem}>
                <FontAwesome5 name="water" size={24} color={colors.info} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[styles.deviceLabel, { color: colors.primaryDark }]}
                  >
                    Water
                  </Text>
                  <View
                    style={[
                      styles.statusIndicator,
                      {
                        backgroundColor: getStatusBgColor(
                          getDeviceStatus(stats.waterPump, pumpAutoMode),
                          colors,
                        ),
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.statusText,
                        {
                          color: getStatusColor(
                            getDeviceStatus(stats.waterPump, pumpAutoMode),
                            colors,
                          ),
                        },
                      ]}
                    >
                      {getDeviceStatus(stats.waterPump, pumpAutoMode)}
                    </Text>
                  </View>
                </View>
              </View>
            </Card>
          </TouchableOpacity>

          {/* LIGHT */}
          <TouchableOpacity
            style={styles.deviceCardWrapper}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/light-control")}
          >
            <Card style={styles.deviceCard}>
              <View style={styles.deviceItem}>
                <Icon name="bulb-outline" size={24} color={colors.warning} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[styles.deviceLabel, { color: colors.primaryDark }]}
                  >
                    Light
                  </Text>
                  <View
                    style={[
                      styles.statusIndicator,
                      {
                        backgroundColor: getStatusBgColor(
                          getDeviceStatus(stats.lightStatus, lightAutoMode),
                          colors,
                        ),
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.statusText,
                        {
                          color: getStatusColor(
                            getDeviceStatus(stats.lightStatus, lightAutoMode),
                            colors,
                          ),
                        },
                      ]}
                    >
                      {getDeviceStatus(stats.lightStatus, lightAutoMode)}
                    </Text>
                  </View>
                </View>
              </View>
            </Card>
          </TouchableOpacity>
        </View>

        {/* GATE - Separate row */}
        <View style={styles.deviceRow}>
          <TouchableOpacity
            style={[styles.deviceCardWrapper, { maxWidth: "33.33%" }]}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/gate-control")}
          >
            <Card style={styles.deviceCard}>
              <View style={styles.deviceItem}>
                <FontAwesome5
                  name="door-open"
                  size={24}
                  color={colors.success}
                />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[styles.deviceLabel, { color: colors.primaryDark }]}
                  >
                    Gate
                  </Text>
                  <View
                    style={[
                      styles.statusIndicator,
                      {
                        backgroundColor: getStatusBgColor(
                          getDeviceStatus(stats.gateStatus, gateAutoMode),
                          colors,
                        ),
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.statusText,
                        {
                          color: getStatusColor(
                            getDeviceStatus(stats.gateStatus, gateAutoMode),
                            colors,
                          ),
                        },
                      ]}
                    >
                      {getDeviceStatus(stats.gateStatus, gateAutoMode)}
                    </Text>
                  </View>
                </View>
              </View>
            </Card>
          </TouchableOpacity>
        </View>
      </View>

      {/* ============================================
      QUICK ACTIONS
      ============================================ */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Icon
            name="flash-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Quick Actions
          </Text>
        </View>
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/fan-control")}
          >
            <FontAwesome5 name="fan" size={28} color={colors.orange} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Fan
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/gate-control")}
          >
            <FontAwesome5 name="door-open" size={24} color={colors.primary} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Gate
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/water-pump")}
          >
            <FontAwesome5 name="water" size={24} color={colors.info} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Water
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/light-control")}
          >
            <Icon name="bulb-outline" size={28} color={colors.warning} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Light
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/(tabs)/camera")}
          >
            <Icon name="camera" size={28} color={colors.purple} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Camera
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.footer} />
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1 },
  centered: { flex: 1, justifyContent: "center", alignItems: "center" },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 8,
  },
  greeting: { fontSize: 20, fontWeight: "700" },
  date: { fontSize: 13, marginTop: 2 },
  notificationBtn: { position: "relative", padding: 8 },
  notificationBadge: {
    position: "absolute",
    top: 0,
    right: 0,
    borderRadius: 10,
    minWidth: 18,
    height: 18,
    justifyContent: "center",
    alignItems: "center",
    paddingHorizontal: 4,
  },
  badgeText: { color: "#FFFFFF", fontSize: 10, fontWeight: "700" },
  section: { paddingHorizontal: 16, marginTop: 16 },
  errorBanner: {
    marginHorizontal: 16,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  errorText: { fontSize: 12, fontWeight: "600" },
  sectionTitle: { fontSize: 16, fontWeight: "600", marginBottom: 0 },
  row: { flexDirection: "row", justifyContent: "space-between" },
  halfCard: { flex: 1, marginHorizontal: 4 },
  tempContainer: {
    alignItems: "center",
    paddingVertical: 8,
  },
  tempValue: { fontSize: 36, fontWeight: "800" },
  tempLabel: { fontSize: 14, marginTop: 4 },
  tempRange: { fontSize: 11, marginTop: 4 },

  deviceRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 6,
    marginBottom: 8,
  },
  deviceCardWrapper: {
    flex: 1,
    maxWidth: "33.33%",
  },
  deviceCard: {
    paddingVertical: 8,
    paddingHorizontal: 4,
    minHeight: 100,
    justifyContent: "center",
  },
  deviceItem: {
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 2,
  },
  deviceInfo: {
    alignItems: "center",
    marginTop: 4,
  },
  deviceLabel: {
    fontSize: 12,
    fontWeight: "600",
    marginBottom: 2,
  },
  statusIndicator: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 2,
    minWidth: 50,
    alignItems: "center",
  },
  statusText: {
    fontSize: 12,
    fontWeight: "700",
  },

  quickActions: {
    flexDirection: "row",
    justifyContent: "space-between",
    flexWrap: "wrap",
  },
  quickAction: {
    flex: 1,
    minWidth: "18%",
    alignItems: "center",
    padding: 12,
    borderRadius: 12,
    marginHorizontal: 3,
    marginVertical: 4,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  quickActionText: { fontSize: 11, fontWeight: "600", marginTop: 4 },
  footer: { height: 40 },
});

export default DashboardScreen;
