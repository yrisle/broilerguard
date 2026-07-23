// src/screens/Main/DashboardScreen.tsx
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
import { dashboard, sensors } from "../../api/endpoints";
import Card from "../../components/common/Card";
import LevelIndicator from "../../components/sensors/LevelIndicator";

const DashboardScreen = () => {
  const router = useRouter();
  const [stats, setStats] = useState({
    temperature: 0,
    humidity: 0,
    feedLevel: 0,
    waterLevel: 0,
    fanStatus: "OFF",
    waterPump: "OFF",
    healthyChicks: 0,
    weakChicks: 0,
    totalChicks: 0,
    feedConsumed: 0,
    waterConsumed: 0,
  });
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleNavigate = (routeName: string) => {
    try {
      router.push(routeName as never);
    } catch {
      setErrorMessage("Navigation is unavailable right now. Please try again.");
    }
  };

  const fetchDashboard = async () => {
    try {
      const [sensorRes, statsRes] = await Promise.all([
        sensors.getCurrent(),
        dashboard.getStats(),
      ]);

      const sensorData = sensorRes.data.data || {};
      const statsData = statsRes.data.data || {};

      setStats({
        temperature: sensorData.temperature || 0,
        humidity: sensorData.humidity || 0,
        feedLevel: sensorData.feed_level || 0,
        waterLevel: sensorData.water_level || 0,
        fanStatus: sensorData.fan_status || "OFF",
        waterPump: sensorData.water_pump || "OFF",
        healthyChicks: statsData.healthy_chicks || 0,
        weakChicks: statsData.weak_chicks || 0,
        totalChicks: statsData.total_chicks || 0,
        feedConsumed: statsData.feed_consumed_today || 0,
        waterConsumed: statsData.water_consumed_today || 0,
      });
      setErrorMessage(null);
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "Unable to reach the server.";

      console.warn("Dashboard data unavailable:", message);
      setErrorMessage(
        "Unable to reach the server. Showing the latest available values.",
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

  if (loading) {
    return (
      <View style={styles.centered}>
        <Text>Loading...</Text>
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
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Good Morning, Admin 👋</Text>
          <Text style={styles.date}>
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
          <Icon name="notifications-outline" size={24} color="#3E2C1C" />
          <View style={styles.notificationBadge}>
            <Text style={styles.badgeText}>3</Text>
          </View>
        </TouchableOpacity>
      </View>

      {errorMessage ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText}>{errorMessage}</Text>
        </View>
      ) : null}

      {/* Environmental Cards */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🌡️ Environmental Conditions</Text>
        <View style={styles.row}>
          <Card style={styles.halfCard}>
            <View style={styles.tempContainer}>
              <Text style={styles.tempValue}>{stats.temperature}°C</Text>
              <Text style={styles.tempLabel}>Temperature</Text>
              <Text style={styles.tempRange}>Ideal: 30°C - 35°C</Text>
            </View>
          </Card>
          <Card style={styles.halfCard}>
            <View style={styles.tempContainer}>
              <Text style={[styles.tempValue, { color: "#2980B9" }]}>
                {stats.humidity}%
              </Text>
              <Text style={styles.tempLabel}>Humidity</Text>
              <Text style={styles.tempRange}>Ideal: 55% - 80%</Text>
            </View>
          </Card>
        </View>
      </View>

      {/* Resource Levels */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📦 Resource Levels</Text>
        <Card>
          <LevelIndicator
            label="Feed Level"
            value={stats.feedLevel}
            maxValue={100}
            color="#FFD62E"
            consumed={stats.feedConsumed}
            unit="kg"
          />
          <LevelIndicator
            label="Water Level"
            value={stats.waterLevel}
            maxValue={100}
            color="#2980B9"
            consumed={stats.waterConsumed}
            unit="L"
          />
        </Card>
      </View>

      {/* Automation Status - LAHAT NG "name" ay lowercase na */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>⚙️ Automation Status</Text>
        <View style={styles.row}>
          <Card style={styles.halfCard}>
            <View style={styles.automationItem}>
              <Icon name="options-outline" size={32} color="#FFD62E" />
              <View>
                <Text style={styles.automationLabel}>Fan</Text>
                <View
                  style={[
                    styles.statusIndicator,
                    stats.fanStatus === "ON"
                      ? styles.statusOn
                      : styles.statusOff,
                  ]}
                >
                  <Text style={styles.statusText}>{stats.fanStatus}</Text>
                </View>
              </View>
            </View>
          </Card>
          <Card style={styles.halfCard}>
            <View style={styles.automationItem}>
              <Icon name="water" size={32} color="#2980B9" />
              <View>
                <Text style={styles.automationLabel}>Water Pump</Text>
                <View
                  style={[
                    styles.statusIndicator,
                    stats.waterPump === "ON"
                      ? styles.statusOn
                      : styles.statusOff,
                  ]}
                >
                  <Text style={styles.statusText}>{stats.waterPump}</Text>
                </View>
              </View>
            </View>
          </Card>
        </View>
      </View>

      {/* Chicken Status */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🐔 Chicken Health</Text>
        <Card>
          <View style={styles.chickenStats}>
            <View style={styles.chickenStat}>
              <Text style={[styles.chickenValue, { color: "#27AE60" }]}>
                {stats.healthyChicks}
              </Text>
              <Text style={styles.chickenLabel}>Healthy</Text>
            </View>
            <View style={styles.chickenStat}>
              <Text style={[styles.chickenValue, { color: "#F39C12" }]}>
                {stats.weakChicks}
              </Text>
              <Text style={styles.chickenLabel}>Weak</Text>
            </View>
            <View style={styles.chickenStat}>
              <Text style={[styles.chickenValue, { color: "#E74C3C" }]}>
                {stats.totalChicks - stats.healthyChicks - stats.weakChicks}
              </Text>
              <Text style={styles.chickenLabel}>Unhealthy</Text>
            </View>
          </View>
        </Card>
      </View>

      {/* Quick Actions - LAHAT NG "name" ay lowercase na */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>⚡ Quick Actions</Text>
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => handleNavigate("/fan-control")}
          >
            <Icon name="options-outline" size={28} color="#E67E22" />
            <Text style={styles.quickActionText}>Fan</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => handleNavigate("/feed-dispenser")}
          >
            <Icon name="fast-food" size={28} color="#E6B800" />
            <Text style={styles.quickActionText}>Feed</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => handleNavigate("/water-pump")}
          >
            <Icon name="water" size={28} color="#2980B9" />
            <Text style={styles.quickActionText}>Water</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => handleNavigate("/(tabs)/camera")}
          >
            <Icon name="camera" size={28} color="#8E44AD" />
            <Text style={styles.quickActionText}>Camera</Text>
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
    backgroundColor: "#FFFCF2",
  },
  centered: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 8,
  },
  greeting: {
    fontSize: 20,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  date: {
    fontSize: 13,
    color: "#8B7355",
    marginTop: 2,
  },
  notificationBtn: {
    position: "relative",
    padding: 8,
  },
  notificationBadge: {
    position: "absolute",
    top: 4,
    right: 4,
    backgroundColor: "#E74C3C",
    borderRadius: 10,
    width: 18,
    height: 18,
    justifyContent: "center",
    alignItems: "center",
  },
  badgeText: {
    color: "#FFF",
    fontSize: 10,
    fontWeight: "700",
  },
  section: {
    paddingHorizontal: 16,
    marginTop: 16,
  },
  errorBanner: {
    marginHorizontal: 16,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: "#FFF4E5",
    borderWidth: 1,
    borderColor: "#F5C27A",
  },
  errorText: {
    color: "#8A4B00",
    fontSize: 12,
    fontWeight: "600",
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#5C4A1E",
    marginBottom: 12,
  },
  row: {
    flexDirection: "row",
    justifyContent: "space-between",
  },
  halfCard: {
    flex: 1,
    marginHorizontal: 4,
  },
  tempContainer: {
    alignItems: "center",
    paddingVertical: 8,
  },
  tempValue: {
    fontSize: 36,
    fontWeight: "800",
    color: "#E67E22",
  },
  tempLabel: {
    fontSize: 14,
    color: "#8B7355",
    marginTop: 4,
  },
  tempRange: {
    fontSize: 11,
    color: "#8B7355",
    marginTop: 4,
  },
  automationItem: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-around",
    paddingVertical: 12,
  },
  automationLabel: {
    fontSize: 14,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  statusIndicator: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 4,
  },
  statusOn: {
    backgroundColor: "#E8F5E9",
  },
  statusOff: {
    backgroundColor: "#FDEDEC",
  },
  statusText: {
    fontSize: 12,
    fontWeight: "700",
  },
  chickenStats: {
    flexDirection: "row",
    justifyContent: "space-around",
    paddingVertical: 12,
  },
  chickenStat: {
    alignItems: "center",
  },
  chickenValue: {
    fontSize: 28,
    fontWeight: "800",
  },
  chickenLabel: {
    fontSize: 12,
    color: "#8B7355",
    marginTop: 4,
  },
  quickActions: {
    flexDirection: "row",
    justifyContent: "space-between",
  },
  quickAction: {
    flex: 1,
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    padding: 16,
    borderRadius: 12,
    marginHorizontal: 4,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  quickActionText: {
    fontSize: 12,
    fontWeight: "600",
    color: "#3E2C1C",
    marginTop: 4,
  },
  footer: {
    height: 40,
  },
});

export default DashboardScreen;
