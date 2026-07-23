// src/screens/Main/WaterScreen.tsx
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

const WaterScreen = () => {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const response = await api.get("/sensors/water");
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching water data:", error);
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

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

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
      {/* Water Level */}
      <View style={styles.levelCard}>
        <View style={styles.levelHeader}>
          <Text style={styles.levelTitle}>💧 Water Level</Text>
          <Text
            style={[
              styles.levelStatus,
              percentage < 20 && styles.levelStatusLow,
            ]}
          >
            {percentage < 20 ? "⚠️ LOW" : "✅ OK"}
          </Text>
        </View>
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

      {/* Quick Release */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>⚡ Quick Release</Text>
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
        <TouchableOpacity style={styles.customReleaseBtn}>
          <Text style={styles.customReleaseText}>Custom Duration →</Text>
        </TouchableOpacity>
      </View>

      {/* Pump Status */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🔄 Water Pump</Text>
        <View style={styles.pumpCard}>
          <Text style={styles.pumpIcon}>🔧</Text>
          <View style={styles.pumpInfo}>
            <Text style={styles.pumpLabel}>Main Water Pump</Text>
            <View
              style={[
                styles.pumpStatus,
                data?.pump?.status === "ON"
                  ? styles.pumpRunning
                  : styles.pumpStopped,
              ]}
            >
              <Text style={styles.pumpStatusText}>
                {data?.pump?.status === "ON" ? "▶️ RUNNING" : "⏹️ STOPPED"}
              </Text>
            </View>
          </View>
        </View>
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
              💧 Released {log.water_amount} L
            </Text>
            <Text style={styles.logTrigger}>{log.trigger}</Text>
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
    alignItems: "center",
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
    width: "100%",
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
  customReleaseBtn: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 10,
    borderWidth: 1,
    borderColor: "rgba(41, 128, 185, 0.3)",
  },
  customReleaseText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#2980B9",
  },
  pumpCard: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  pumpIcon: {
    fontSize: 32,
    marginRight: 16,
  },
  pumpInfo: {
    flex: 1,
  },
  pumpLabel: {
    fontSize: 14,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  pumpStatus: {
    marginTop: 4,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: "flex-start",
  },
  pumpRunning: {
    backgroundColor: "#E8F5E9",
  },
  pumpStopped: {
    backgroundColor: "#FDEDEC",
  },
  pumpStatusText: {
    fontSize: 12,
    fontWeight: "700",
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

export default WaterScreen;
