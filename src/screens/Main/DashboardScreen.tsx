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
import { useTheme } from "../../hooks/useTheme";

const DashboardScreen = () => {
  const { colors } = useTheme();
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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <Text style={{ color: colors.text }}>Loading...</Text>
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
            Good Morning, Admin 👋
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
          <View
            style={[
              styles.notificationBadge,
              { backgroundColor: colors.danger },
            ]}
          >
            <Text style={styles.badgeText}>3</Text>
          </View>
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

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          🌡️ Environmental Conditions
        </Text>
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

      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 8,
          }}
        >
          <Image
            source={require("./assets/images/pic.avif")}
            style={{ width: 24, height: 24, marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Resource Levels
          </Text>
        </View>
        <Card>
          <LevelIndicator
            label="Feed Level"
            value={stats.feedLevel}
            maxValue={100}
            color={colors.primary}
            consumed={stats.feedConsumed}
            unit="kg"
          />
          <LevelIndicator
            label="Water Level"
            value={stats.waterLevel}
            maxValue={100}
            color={colors.info}
            consumed={stats.waterConsumed}
            unit="L"
          />
        </Card>
      </View>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          ⚙️ Automation Status
        </Text>
        <View style={styles.row}>
          <Card style={styles.halfCard}>
            <View style={styles.automationItem}>
              <Icon name="options-outline" size={32} color={colors.primary} />
              <View>
                <Text
                  style={[
                    styles.automationLabel,
                    { color: colors.primaryDark },
                  ]}
                >
                  Fan
                </Text>
                <View
                  style={[
                    styles.statusIndicator,
                    stats.fanStatus === "ON"
                      ? [
                          styles.statusOn,
                          { backgroundColor: colors.successLight },
                        ]
                      : [
                          styles.statusOff,
                          { backgroundColor: colors.dangerLight },
                        ],
                  ]}
                >
                  <Text
                    style={[
                      styles.statusText,
                      {
                        color:
                          stats.fanStatus === "ON"
                            ? colors.success
                            : colors.danger,
                      },
                    ]}
                  >
                    {stats.fanStatus}
                  </Text>
                </View>
              </View>
            </View>
          </Card>
          <Card style={styles.halfCard}>
            <View style={styles.automationItem}>
              <Icon name="water" size={32} color={colors.info} />
              <View>
                <Text
                  style={[
                    styles.automationLabel,
                    { color: colors.primaryDark },
                  ]}
                >
                  Water Pump
                </Text>
                <View
                  style={[
                    styles.statusIndicator,
                    stats.waterPump === "ON"
                      ? [
                          styles.statusOn,
                          { backgroundColor: colors.successLight },
                        ]
                      : [
                          styles.statusOff,
                          { backgroundColor: colors.dangerLight },
                        ],
                  ]}
                >
                  <Text
                    style={[
                      styles.statusText,
                      {
                        color:
                          stats.waterPump === "ON"
                            ? colors.success
                            : colors.danger,
                      },
                    ]}
                  >
                    {stats.waterPump}
                  </Text>
                </View>
              </View>
            </View>
          </Card>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          🐔 Chicken Health
        </Text>
        <Card>
          <View style={styles.chickenStats}>
            <View style={styles.chickenStat}>
              <Text style={[styles.chickenValue, { color: colors.success }]}>
                {stats.healthyChicks}
              </Text>
              <Text style={[styles.chickenLabel, { color: colors.textMuted }]}>
                Healthy
              </Text>
            </View>
            <View style={styles.chickenStat}>
              <Text style={[styles.chickenValue, { color: colors.warning }]}>
                {stats.weakChicks}
              </Text>
              <Text style={[styles.chickenLabel, { color: colors.textMuted }]}>
                Weak
              </Text>
            </View>
            <View style={styles.chickenStat}>
              <Text style={[styles.chickenValue, { color: colors.danger }]}>
                {stats.totalChicks - stats.healthyChicks - stats.weakChicks}
              </Text>
              <Text style={[styles.chickenLabel, { color: colors.textMuted }]}>
                Unhealthy
              </Text>
            </View>
          </View>
        </Card>
      </View>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          ⚡ Quick Actions
        </Text>
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/fan-control")}
          >
            <Icon name="options-outline" size={28} color={colors.orange} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Fan
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/feed-dispenser")}
          >
            <Icon name="fast-food" size={28} color={colors.primary} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Feed
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.quickAction, { backgroundColor: colors.card }]}
            onPress={() => handleNavigate("/water-pump")}
          >
            <Icon name="water" size={28} color={colors.info} />
            <Text style={[styles.quickActionText, { color: colors.text }]}>
              Water
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
  container: {
    flex: 1,
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
  },
  date: {
    fontSize: 13,
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
    borderRadius: 10,
    width: 18,
    height: 18,
    justifyContent: "center",
    alignItems: "center",
  },
  badgeText: {
    color: "#282121",
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
    borderWidth: 1,
  },
  errorText: {
    fontSize: 12,
    fontWeight: "600",
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
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
  },
  tempLabel: {
    fontSize: 14,
    marginTop: 4,
  },
  tempRange: {
    fontSize: 11,
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
  },
  statusIndicator: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 4,
  },
  statusOn: {},
  statusOff: {},
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
    marginTop: 4,
  },
  quickActions: {
    flexDirection: "row",
    justifyContent: "space-between",
  },
  quickAction: {
    flex: 1,
    alignItems: "center",
    padding: 16,
    borderRadius: 12,
    marginHorizontal: 4,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  quickActionText: {
    fontSize: 12,
    fontWeight: "600",
    marginTop: 4,
  },
  footer: {
    height: 40,
  },
});

export default DashboardScreen;
