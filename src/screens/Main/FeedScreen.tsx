// src/screens/Main/FeedScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

const FeedScreen = () => {
  const { colors } = useTheme();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const response = await api.get("/sensors/feed");
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching feed data:", error);
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

  const handleDispense = async (amount: number) => {
    try {
      const response = await api.post("/automation/feeder", {
        action: "dispense",
        amount,
      });
      if (response.data.success) {
        Alert.alert("Success", `Dispensed ${amount} kg of feed`);
        fetchData();
      }
    } catch (error) {
      Alert.alert("Error", "Failed to dispense feed");
    }
  };

  if (loading) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const level = data?.inventory?.current_level || 0;
  const capacity = data?.inventory?.capacity || 100;
  const percentage = (level / capacity) * 100;
  const isLow = percentage < 20;

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
            name="utensils"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Feed Management
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Monitor and manage feed levels
        </Text>
      </View>

      <View style={[styles.levelCard, { backgroundColor: colors.card }]}>
        <View style={styles.levelHeader}>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <FontAwesome5
              name="box"
              size={20}
              color={colors.text}
              style={{ marginRight: 8 }}
            />
            <Text style={[styles.levelTitle, { color: colors.text }]}>
              Feed Level
            </Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            {isLow ? (
              <Ionicons name="warning" size={18} color={colors.danger} />
            ) : (
              <Ionicons
                name="checkmark-circle"
                size={18}
                color={colors.success}
              />
            )}
            <Text
              style={[
                styles.levelStatus,
                isLow
                  ? [styles.levelStatusLow, { color: colors.danger }]
                  : { color: colors.success },
              ]}
            >
              {isLow ? " LOW" : " OK"}
            </Text>
          </View>
        </View>
        <View style={styles.levelGauge}>
          <View style={styles.progressBar}>
            <View
              style={[
                styles.progressFill,
                {
                  width: `${Math.min(percentage, 100)}%`,
                  backgroundColor: isLow ? colors.danger : colors.primary,
                },
              ]}
            />
          </View>
          <Text style={[styles.levelText, { color: colors.textMuted }]}>
            {level.toFixed(1)} kg / {capacity} kg ({percentage.toFixed(0)}%)
          </Text>
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
            name="flash-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Quick Dispense
          </Text>
        </View>
        <View style={styles.dispenseButtons}>
          {[0.5, 1.0, 2.0].map((amount) => (
            <TouchableOpacity
              key={amount}
              style={[styles.dispenseBtn, { backgroundColor: colors.primary }]}
              onPress={() => handleDispense(amount)}
            >
              <Text style={[styles.dispenseBtnText, { color: "#FFFFFF" }]}>
                {amount} kg
              </Text>
            </TouchableOpacity>
          ))}
        </View>
        <TouchableOpacity
          style={[
            styles.customDispenseBtn,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <View
            style={{
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <Text
              style={[styles.customDispenseText, { color: colors.textMuted }]}
            >
              Custom Amount
            </Text>
            <Ionicons
              name="arrow-forward-outline"
              size={16}
              color={colors.textMuted}
              style={{ marginLeft: 6 }}
            />
          </View>
        </TouchableOpacity>
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
            Feeding Schedule
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
              style={[styles.scheduleAmount, { color: colors.textSecondary }]}
            >
              {schedule.amount} kg
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
                {schedule.enabled ? "Active" : "Inactive"}
              </Text>
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
              {log.source === "refill" ? (
                <Ionicons
                  name="add-circle-outline"
                  size={14}
                  color={colors.success}
                  style={{ marginRight: 6 }}
                />
              ) : (
                <FontAwesome5
                  name="utensils"
                  size={12}
                  color={colors.primary}
                  style={{ marginRight: 6 }}
                />
              )}
              <Text style={[styles.logAction, { color: colors.text }]}>
                {log.source === "refill" ? "Refilled" : "Dispensed"}{" "}
                {log.amount} kg
              </Text>
            </View>
            <Text style={[styles.logRemaining, { color: colors.textMuted }]}>
              {log.remaining} kg left
            </Text>
          </View>
        ))}
      </View>
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
  levelCard: {
    borderRadius: 16,
    padding: 20,
    margin: 16,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  levelHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  levelTitle: {
    fontSize: 18,
    fontWeight: "700",
  },
  levelStatus: {
    fontSize: 14,
    fontWeight: "700",
  },
  levelStatusLow: {},
  levelGauge: {
    marginTop: 4,
  },
  progressBar: {
    height: 12,
    borderRadius: 6,
    overflow: "hidden",
    backgroundColor: "#F0E8D8",
  },
  progressFill: {
    height: "100%",
    borderRadius: 6,
  },
  levelText: {
    marginTop: 8,
    fontSize: 14,
    textAlign: "center",
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
  dispenseButtons: {
    flexDirection: "row",
    gap: 10,
  },
  dispenseBtn: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginHorizontal: 4,
  },
  dispenseBtnText: {
    fontSize: 16,
    fontWeight: "700",
  },
  customDispenseBtn: {
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 10,
    borderWidth: 1,
  },
  customDispenseText: {
    fontSize: 14,
    fontWeight: "600",
  },
  scheduleItem: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
  },
  scheduleTime: {
    fontSize: 16,
    fontWeight: "700",
    width: 70,
  },
  scheduleAmount: {
    flex: 1,
    fontSize: 14,
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
  logRemaining: {
    fontSize: 12,
  },
});

export default FeedScreen;
