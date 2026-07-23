// src/screens/Main/FeedScreen.tsx
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

const FeedScreen = () => {
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
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  const level = data?.inventory?.current_level || 0;
  const capacity = data?.inventory?.capacity || 100;
  const percentage = (level / capacity) * 100;
  const isLow = percentage < 20;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      {/* Current Level */}
      <View style={styles.levelCard}>
        <View style={styles.levelHeader}>
          <Text style={styles.levelTitle}>🍗 Feed Level</Text>
          <Text style={[styles.levelStatus, isLow && styles.levelStatusLow]}>
            {isLow ? "⚠️ LOW" : "✅ OK"}
          </Text>
        </View>
        <View style={styles.levelGauge}>
          <View style={styles.progressBar}>
            <View
              style={[
                styles.progressFill,
                {
                  width: `${Math.min(percentage, 100)}%`,
                  backgroundColor: isLow ? "#E74C3C" : "#FFD62E",
                },
              ]}
            />
          </View>
          <Text style={styles.levelText}>
            {level.toFixed(1)} kg / {capacity} kg ({percentage.toFixed(0)}%)
          </Text>
        </View>
      </View>

      {/* Quick Dispense */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>⚡ Quick Dispense</Text>
        <View style={styles.dispenseButtons}>
          {[0.5, 1.0, 2.0].map((amount) => (
            <TouchableOpacity
              key={amount}
              style={styles.dispenseBtn}
              onPress={() => handleDispense(amount)}
            >
              <Text style={styles.dispenseBtnText}>{amount} kg</Text>
            </TouchableOpacity>
          ))}
        </View>
        <TouchableOpacity style={styles.customDispenseBtn}>
          <Text style={styles.customDispenseText}>Custom Amount →</Text>
        </TouchableOpacity>
      </View>

      {/* Feed Schedules */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📅 Feeding Schedule</Text>
        {data?.schedules?.map((schedule: any, index: number) => (
          <View key={index} style={styles.scheduleItem}>
            <Text style={styles.scheduleTime}>
              {new Date(`2000-01-01T${schedule.time}:00`).toLocaleTimeString(
                "en-US",
                { hour: "numeric", minute: "2-digit" },
              )}
            </Text>
            <Text style={styles.scheduleAmount}>{schedule.amount} kg</Text>
            <View
              style={[
                styles.scheduleStatus,
                schedule.enabled ? styles.statusActive : styles.statusInactive,
              ]}
            >
              <Text style={styles.scheduleStatusText}>
                {schedule.enabled ? "Active" : "Inactive"}
              </Text>
            </View>
          </View>
        ))}
      </View>

      {/* Recent Activity */}
      <View style={[styles.section, styles.lastSection]}>
        <Text style={styles.sectionTitle}>📋 Recent Activity</Text>
        {data?.logs?.slice(0, 5).map((log: any, index: number) => (
          <View key={index} style={styles.logItem}>
            <Text style={styles.logTime}>
              {new Date(log.timestamp).toLocaleTimeString()}
            </Text>
            <Text style={styles.logAction}>
              {log.source === "refill" ? "➕ Refilled" : "🍽️ Dispensed"}{" "}
              {log.amount} kg
            </Text>
            <Text style={styles.logRemaining}>{log.remaining} kg left</Text>
          </View>
        ))}
      </View>
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
  levelCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 20,
    margin: 16,
    shadowColor: "#000",
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
    color: "#3E2C1C",
  },
  levelStatus: {
    fontSize: 14,
    fontWeight: "700",
    color: "#27AE60",
  },
  levelStatusLow: {
    color: "#E74C3C",
  },
  levelGauge: {
    marginTop: 4,
  },
  progressBar: {
    height: 12,
    backgroundColor: "#F0E8D8",
    borderRadius: 6,
    overflow: "hidden",
  },
  progressFill: {
    height: "100%",
    borderRadius: 6,
  },
  levelText: {
    marginTop: 8,
    fontSize: 14,
    color: "#8B7355",
    textAlign: "center",
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
  dispenseButtons: {
    flexDirection: "row",
    gap: 10,
  },
  dispenseBtn: {
    flex: 1,
    backgroundColor: "#FFD62E",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginHorizontal: 4,
  },
  dispenseBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  customDispenseBtn: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 10,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.3)",
  },
  customDispenseText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#8B7355",
  },
  scheduleItem: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  scheduleTime: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
    width: 70,
  },
  scheduleAmount: {
    flex: 1,
    fontSize: 14,
    color: "#5C4A1E",
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
  logRemaining: {
    fontSize: 12,
    color: "#8B7355",
  },
});

export default FeedScreen;
