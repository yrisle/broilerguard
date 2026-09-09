// src/screens/Main/DashboardScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
import Icon from "@expo/vector-icons/Ionicons";
import { useFocusEffect } from "@react-navigation/native";
import { useRouter } from "expo-router";
import React, { useCallback, useState } from "react";
import {
  Alert,
  Dimensions,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { LineChart, PieChart } from "react-native-chart-kit";
import { notifications, sensors } from "../../api/endpoints";
import { useAuth } from "../../context/AuthContext";
import { useAutomation } from "../../context/AutomationContext";
import { useTheme } from "../../hooks/useTheme";

const DashboardScreen = () => {
  const { colors } = useTheme();
  const router = useRouter();
  const { user, logout } = useAuth();
  const { fanAutoMode, gateAutoMode, pumpAutoMode, lightAutoMode } =
    useAutomation();
  const [stats, setStats] = useState({
    temperature: 0,
    humidity: 0,
    feedLevel: 50,
    waterLevel: 60,
    fanPhysicalStatus: "OFF",
    waterPumpPhysicalStatus: "OFF",
    lightStatus: "OFF",
    gateStatus: "CLOSED",
    fanStatus: "OFF",
    waterPump: "OFF",
    lightStatusDisplay: "OFF",
    healthyChicks: 0,
    weakChicks: 0,
    unhealthyChicks: 0,
    totalChicks: 0,
    feedConsumed: 0,
    waterConsumed: 0,
  });
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [notificationCount, setNotificationCount] = useState(0);
  const [chickHistory, setChickHistory] = useState<any[]>([]);

  const screenWidth = Dimensions.get("window").width - 32;

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return "Good Morning";
    if (hour < 17) return "Good Afternoon";
    return "Good Evening";
  };

  const getUserDisplayName = () => {
    if (user?.full_name) return user.full_name;
    if (user?.name) return user.name;
    if (user?.username) return user.username;
    return "Admin";
  };

  const handleNavigate = (routeName: string) => {
    try {
      router.push(routeName as never);
    } catch {
      setErrorMessage("Navigation is unavailable right now. Please try again.");
    }
  };

  const handleLogout = () => {
    Alert.alert("Logout", "Are you sure you want to logout?", [
      {
        text: "Cancel",
        style: "cancel",
      },
      {
        text: "Logout",
        style: "destructive",
        onPress: async () => {
          try {
            await logout();
          } catch (error) {
            console.error("Logout error:", error);
            Alert.alert("Error", "Failed to logout. Please try again.");
          }
        },
      },
    ]);
  };

  const fetchNotificationCount = async () => {
    try {
      const response = await notifications.getAll(1);
      if (response.data.success) {
        const unread = response.data.data.unread || 0;
        setNotificationCount(unread);
      }
    } catch (error) {
      console.log("Error fetching notification count:", error);
    }
  };

  const fetchDashboard = async () => {
    try {
      const sensorRes = await sensors.getCurrent();
      const sensorData = sensorRes.data || {};

      const fanStatus = sensorData.fan === 1 ? "ON" : "OFF";
      const pumpStatus = sensorData.pump === 1 ? "ON" : "OFF";
      const lightStatus = sensorData.light === 1 ? "ON" : "OFF";
      const gateStatus = sensorData.gate === 1 ? "OPEN" : "CLOSED";

      const mockHealthy = 8;
      const mockWeak = 3;
      const mockUnhealthy = 2;
      const total = mockHealthy + mockWeak + mockUnhealthy;

      setStats({
        temperature: sensorData.temperature || 0,
        humidity: sensorData.humidity || 0,
        feedLevel: 50,
        waterLevel: 60,
        fanPhysicalStatus: fanStatus,
        waterPumpPhysicalStatus: pumpStatus,
        lightStatus: lightStatus,
        gateStatus: gateStatus,
        fanStatus: fanStatus,
        waterPump: pumpStatus,
        lightStatusDisplay: lightStatus,
        healthyChicks: mockHealthy,
        weakChicks: mockWeak,
        unhealthyChicks: mockUnhealthy,
        totalChicks: total,
        feedConsumed: 45,
        waterConsumed: 32,
      });

      setChickHistory([
        { day: "Mon", healthy: 10, weak: 2, unhealthy: 1 },
        { day: "Tue", healthy: 9, weak: 3, unhealthy: 1 },
        { day: "Wed", healthy: 8, weak: 3, unhealthy: 2 },
        { day: "Thu", healthy: 9, weak: 2, unhealthy: 2 },
        { day: "Fri", healthy: 8, weak: 3, unhealthy: 2 },
        { day: "Sat", healthy: 7, weak: 4, unhealthy: 2 },
        { day: "Sun", healthy: 8, weak: 3, unhealthy: 2 },
      ]);

      setErrorMessage(null);
      await fetchNotificationCount();
    } catch (error) {
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
      fetchDashboard();
    }, []),
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchDashboard();
  };

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

  const pieData = [
    {
      name: "Healthy",
      population: stats.healthyChicks,
      color: colors.success || "#4D724D",
      legendFontColor: colors.text || "#333",
      legendFontSize: 12,
    },
    {
      name: "Weak",
      population: stats.weakChicks,
      color: colors.warning || "#C8A24A",
      legendFontColor: colors.text || "#333",
      legendFontSize: 12,
    },
    {
      name: "Unhealthy",
      population: stats.unhealthyChicks,
      color: colors.danger || "#A44A3F",
      legendFontColor: colors.text || "#333",
      legendFontSize: 12,
    },
  ];

  const chartConfig = {
    backgroundGradientFrom: "#ffffff",
    backgroundGradientTo: "#ffffff",
    decimalPlaces: 0,

    color: (opacity = 1) => `rgba(0, 122, 255, ${opacity})`,

    labelColor: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,

    strokeWidth: 2,

    propsForDots: {
      r: "4",
      strokeWidth: "2",
    },

    propsForBackgroundLines: {
      strokeDasharray: "",
    },
  };

  const chartData = {
    labels: chickHistory.map((item) => item.day),

    datasets: [
      {
        data: chickHistory.map((item) => item.healthy),
        color: (opacity = 1) => `rgba(34, 197, 94, ${opacity})`,
        strokeWidth: 2,
      },

      {
        data: chickHistory.map((item) => item.weak),
        color: (opacity = 1) => `rgba(234, 179, 8, ${opacity})`,
        strokeWidth: 2,
      },

      {
        data: chickHistory.map((item) => item.unhealthy),
        color: (opacity = 1) => `rgba(239, 68, 68, ${opacity})`,
        strokeWidth: 2,
      },
    ],

    legend: ["Healthy", "Weak", "Unhealthy"],
  };

  const cardBg = colors.card || "#FFFFFF";
  const textColor = colors.text || "#333";
  const textMuted = colors.textMuted || "#666";
  const textSecondary = colors.textSecondary || "#888";
  const primaryColor = colors.primary || "#4CAF50";
  const dangerColor = colors.danger || "#F44336";
  const warningColor = colors.warning || "#FFC107";
  const successColor = colors.success || "#4CAF50";
  const infoColor = colors.info || "#2196F3";
  const orangeColor = colors.orange || "#FF9800";
  const purpleColor = colors.purple || "#9C27B0";

  return (
    <ScrollView
      style={[
        styles.container,
        { backgroundColor: colors.background || "#F5F5F5" },
      ]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      {/* HEADER */}
      <View style={styles.header}>
        <View>
          <Text style={[styles.greeting, { color: textColor }]}>
            {getGreeting()}, {getUserDisplayName()}!
          </Text>
          <Text style={[styles.date, { color: textMuted }]}>
            {new Date().toLocaleDateString("en-US", {
              weekday: "long",
              year: "numeric",
              month: "long",
              day: "numeric",
            })}
          </Text>
          {user?.role && (
            <View style={styles.roleBadge}>
              <Text style={[styles.roleText, { color: textMuted }]}>
                {user.role.charAt(0).toUpperCase() + user.role.slice(1)}
              </Text>
            </View>
          )}
        </View>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={styles.notificationBtn}
            onPress={() => handleNavigate("/notifications")}
          >
            <Icon name="notifications-outline" size={24} color={textColor} />
            {notificationCount > 0 && (
              <View
                style={[
                  styles.notificationBadge,
                  { backgroundColor: dangerColor },
                ]}
              >
                <Text style={styles.badgeText}>
                  {notificationCount > 99 ? "99+" : notificationCount}
                </Text>
              </View>
            )}
          </TouchableOpacity>
          <TouchableOpacity
            style={[
              styles.logoutBtn,
              { borderColor: colors.border || "#E0E0E0" },
            ]}
            onPress={handleLogout}
          >
            <Icon name="log-out-outline" size={22} color={dangerColor} />
          </TouchableOpacity>
        </View>
      </View>

      {/* ERROR BANNER */}
      {errorMessage ? (
        <View
          style={[
            styles.errorBanner,
            {
              backgroundColor: warningColor + "20",
              borderColor: warningColor,
            },
          ]}
        >
          <Text style={[styles.errorText, { color: warningColor }]}>
            {errorMessage}
          </Text>
        </View>
      ) : null}

      {/* ============================================
      ENVIRONMENTAL CONDITIONS
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
            color={textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: textSecondary }]}>
            Environmental Conditions
          </Text>
        </View>
        <View style={styles.row}>
          {/* Temperature */}
          <View
            style={[
              styles.card,
              styles.halfCard,
              { marginHorizontal: 4, backgroundColor: cardBg },
            ]}
          >
            <View style={styles.tempContainer}>
              <Text style={[styles.tempValue, { color: orangeColor }]}>
                {stats.temperature}°C
              </Text>
              <Text style={[styles.tempLabel, { color: textMuted }]}>
                Temperature
              </Text>
              <Text style={[styles.tempRange, { color: textMuted }]}>
                Ideal: 30°C - 35°C
              </Text>
            </View>
          </View>
          {/* Humidity */}
          <View
            style={[
              styles.card,
              styles.halfCard,
              { marginHorizontal: 4, backgroundColor: cardBg },
            ]}
          >
            <View style={styles.tempContainer}>
              <Text style={[styles.tempValue, { color: infoColor }]}>
                {stats.humidity}%
              </Text>
              <Text style={[styles.tempLabel, { color: textMuted }]}>
                Humidity
              </Text>
              <Text style={[styles.tempRange, { color: textMuted }]}>
                Ideal: 55% - 80%
              </Text>
            </View>
          </View>
        </View>
      </View>

      {/* ============================================
      CHICKEN HEALTH STATUS
      ============================================ */}
      <View style={styles.section}>
        <TouchableOpacity
          style={{
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "space-between",
            marginBottom: 12,
          }}
          onPress={() => handleNavigate("/chicken-status")}
          activeOpacity={0.7}
        >
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <FontAwesome5
              name="drumstick-bite"
              size={20}
              color={textSecondary}
              style={{ marginRight: 8 }}
            />
            <Text style={[styles.sectionTitle, { color: textSecondary }]}>
              Chicken Health Status
            </Text>
          </View>
          <Icon name="chevron-forward" size={20} color={textMuted} />
        </TouchableOpacity>

        {/* Health Summary Cards */}
        <View style={styles.healthSummary}>
          <View
            style={[
              styles.card,
              styles.healthCard,
              {
                borderLeftColor: successColor,
                backgroundColor: cardBg,
              },
            ]}
          >
            <Text style={[styles.healthValue, { color: successColor }]}>
              {stats.healthyChicks}
            </Text>
            <Text style={[styles.healthLabel, { color: textMuted }]}>
              Healthy
            </Text>
          </View>

          <View
            style={[
              styles.card,
              styles.healthCard,
              {
                borderLeftColor: warningColor,
                backgroundColor: cardBg,
              },
            ]}
          >
            <Text style={[styles.healthValue, { color: warningColor }]}>
              {stats.weakChicks}
            </Text>
            <Text style={[styles.healthLabel, { color: textMuted }]}>Weak</Text>
          </View>

          <View
            style={[
              styles.card,
              styles.healthCard,
              {
                borderLeftColor: dangerColor,
                backgroundColor: cardBg,
              },
            ]}
          >
            <Text style={[styles.healthValue, { color: dangerColor }]}>
              {stats.unhealthyChicks}
            </Text>
            <Text style={[styles.healthLabel, { color: textMuted }]}>
              Unhealthy
            </Text>
          </View>
        </View>

        {/* Pie Chart */}
        <View
          style={[styles.card, styles.chartCard, { backgroundColor: cardBg }]}
        >
          <Text style={[styles.chartTitle, { color: textColor }]}>
            Health Distribution
          </Text>
          <PieChart
            data={pieData}
            width={screenWidth}
            height={180}
            chartConfig={{
              color: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,
            }}
            accessor={"population"}
            backgroundColor={"transparent"}
            paddingLeft={"15"}
            absolute
          />
        </View>

        {/* Line Chart */}
        <View
          style={[styles.card, styles.chartCard, { backgroundColor: cardBg }]}
        >
          <Text style={[styles.chartTitle, { color: textColor }]}>
            Health Trend (This Week)
          </Text>
          <LineChart
            data={chartData}
            width={screenWidth}
            height={200}
            chartConfig={{
              backgroundColor: "transparent",
              backgroundGradientFrom: "transparent",
              backgroundGradientTo: "transparent",
              decimalPlaces: 0,
              color: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,
              labelColor: (opacity = 1) =>
                textColor
                  ? textColor + opacity * 100
                  : `rgba(0,0,0,${opacity})`,
              style: { borderRadius: 16 },
              propsForDots: {
                r: "4",
                strokeWidth: "2",
                stroke: "#fff",
              },
            }}
            bezier
            style={{
              marginVertical: 8,
              borderRadius: 16,
            }}
          />
          <View style={styles.legendContainer}>
            <View style={styles.legendItem}>
              <View
                style={[styles.legendDot, { backgroundColor: successColor }]}
              />
              <Text style={[styles.legendText, { color: textMuted }]}>
                Healthy
              </Text>
            </View>
            <View style={styles.legendItem}>
              <View
                style={[styles.legendDot, { backgroundColor: warningColor }]}
              />
              <Text style={[styles.legendText, { color: textMuted }]}>
                Weak
              </Text>
            </View>
            <View style={styles.legendItem}>
              <View
                style={[styles.legendDot, { backgroundColor: dangerColor }]}
              />
              <Text style={[styles.legendText, { color: textMuted }]}>
                Unhealthy
              </Text>
            </View>
          </View>
        </View>

        <TouchableOpacity
          style={[styles.viewDetailsBtn, { backgroundColor: primaryColor }]}
          onPress={() => handleNavigate("/chicken-status")}
        >
          <Text style={styles.viewDetailsText}>View Full Details →</Text>
        </TouchableOpacity>
      </View>

      {/* ============================================
      DEVICE STATUS
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
            color={textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: textSecondary }]}>
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
            <View
              style={[
                styles.card,
                styles.deviceCard,
                { backgroundColor: cardBg },
              ]}
            >
              <View style={styles.deviceItem}>
                <FontAwesome5 name="fan" size={24} color={primaryColor} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[
                      styles.deviceLabel,
                      { color: colors.primaryDark || "#388E3C" },
                    ]}
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
            </View>
          </TouchableOpacity>

          {/* WATER PUMP */}
          <TouchableOpacity
            style={styles.deviceCardWrapper}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/water-pump")}
          >
            <View
              style={[
                styles.card,
                styles.deviceCard,
                { backgroundColor: cardBg },
              ]}
            >
              <View style={styles.deviceItem}>
                <FontAwesome5 name="water" size={24} color={infoColor} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[
                      styles.deviceLabel,
                      { color: colors.primaryDark || "#388E3C" },
                    ]}
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
            </View>
          </TouchableOpacity>

          {/* LIGHT */}
          <TouchableOpacity
            style={styles.deviceCardWrapper}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/light-control")}
          >
            <View
              style={[
                styles.card,
                styles.deviceCard,
                { backgroundColor: cardBg },
              ]}
            >
              <View style={styles.deviceItem}>
                <Icon name="bulb-outline" size={24} color={warningColor} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[
                      styles.deviceLabel,
                      { color: colors.primaryDark || "#388E3C" },
                    ]}
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
            </View>
          </TouchableOpacity>
        </View>

        {/* GATE */}
        <View style={styles.deviceRow}>
          <TouchableOpacity
            style={[styles.deviceCardWrapper, { maxWidth: "33.33%" }]}
            activeOpacity={0.7}
            onPress={() => handleNavigate("/gate-control")}
          >
            <View
              style={[
                styles.card,
                styles.deviceCard,
                { backgroundColor: cardBg },
              ]}
            >
              <View style={styles.deviceItem}>
                <FontAwesome5 name="door-open" size={24} color={successColor} />
                <View style={styles.deviceInfo}>
                  <Text
                    style={[
                      styles.deviceLabel,
                      { color: colors.primaryDark || "#388E3C" },
                    ]}
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
            </View>
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
            color={textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: textSecondary }]}>
            Quick Actions
          </Text>
        </View>
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: cardBg }]}
            onPress={() => handleNavigate("/fan-control")}
          >
            <FontAwesome5 name="fan" size={28} color={orangeColor} />
            <Text style={[styles.quickActionText, { color: textColor }]}>
              Fan
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: cardBg }]}
            onPress={() => handleNavigate("/gate-control")}
          >
            <FontAwesome5 name="door-open" size={24} color={primaryColor} />
            <Text style={[styles.quickActionText, { color: textColor }]}>
              Gate
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: cardBg }]}
            onPress={() => handleNavigate("/water-pump")}
          >
            <FontAwesome5 name="water" size={24} color={infoColor} />
            <Text style={[styles.quickActionText, { color: textColor }]}>
              Water
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: cardBg }]}
            onPress={() => handleNavigate("/light-control")}
          >
            <Icon name="bulb-outline" size={28} color={warningColor} />
            <Text style={[styles.quickActionText, { color: textColor }]}>
              Light
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: cardBg }]}
            onPress={() => handleNavigate("/(tabs)/camera")}
          >
            <Icon name="camera" size={28} color={purpleColor} />
            <Text style={[styles.quickActionText, { color: textColor }]}>
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

  // Header
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 8,
  },
  greeting: { fontSize: 20, fontWeight: "700" },
  date: { fontSize: 13, marginTop: 2 },
  roleBadge: {
    marginTop: 4,
    paddingHorizontal: 10,
    paddingVertical: 2,
    borderRadius: 12,
    backgroundColor: "rgba(0,0,0,0.05)",
    alignSelf: "flex-start",
  },
  roleText: { fontSize: 11, fontWeight: "600" },
  headerActions: { flexDirection: "row", alignItems: "center", gap: 8 },
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
  logoutBtn: { padding: 8, borderRadius: 8, borderWidth: 1, marginLeft: 4 },

  // Common
  section: { paddingHorizontal: 16, marginTop: 16 },
  sectionTitle: { fontSize: 16, fontWeight: "600", marginBottom: 0 },
  row: { flexDirection: "row", justifyContent: "space-between" },
  footer: { height: 40 },

  // Card
  card: {
    borderRadius: 12,
    padding: 16,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },

  // Error
  errorBanner: {
    marginHorizontal: 16,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  errorText: { fontSize: 12, fontWeight: "600" },

  // Environmental
  halfCard: { flex: 1 },
  tempContainer: { alignItems: "center", paddingVertical: 8 },
  tempValue: { fontSize: 36, fontWeight: "800" },
  tempLabel: { fontSize: 14, marginTop: 4 },
  tempRange: { fontSize: 11, marginTop: 4 },

  // Chicken Health
  healthSummary: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 8,
    marginBottom: 12,
  },
  healthCard: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 8,
    alignItems: "center",
    borderLeftWidth: 4,
  },
  healthValue: { fontSize: 28, fontWeight: "800" },
  healthLabel: { fontSize: 12, marginTop: 2 },
  chartCard: {
    padding: 12,
    marginBottom: 12,
    alignItems: "center",
  },
  chartTitle: {
    fontSize: 14,
    fontWeight: "600",
    marginBottom: 8,
    alignSelf: "flex-start",
  },
  legendContainer: {
    flexDirection: "row",
    justifyContent: "center",
    flexWrap: "wrap",
    marginTop: 8,
    gap: 16,
  },
  legendItem: { flexDirection: "row", alignItems: "center" },
  legendDot: { width: 10, height: 10, borderRadius: 5, marginRight: 6 },
  legendText: { fontSize: 12 },
  viewDetailsBtn: {
    borderRadius: 12,
    padding: 14,
    alignItems: "center",
    marginTop: 4,
    marginBottom: 8,
  },
  viewDetailsText: { color: "#FFFFFF", fontSize: 14, fontWeight: "600" },

  // Device Status
  deviceRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    gap: 6,
    marginBottom: 8,
  },
  deviceCardWrapper: { flex: 1, maxWidth: "33.33%" },
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
  deviceInfo: { alignItems: "center", marginTop: 4 },
  deviceLabel: { fontSize: 12, fontWeight: "600", marginBottom: 2 },
  statusIndicator: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 2,
    minWidth: 50,
    alignItems: "center",
  },
  statusText: { fontSize: 12, fontWeight: "700" },

  // Quick Actions
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
  },
  quickActionText: { fontSize: 11, fontWeight: "600", marginTop: 4 },
});

export default DashboardScreen;
