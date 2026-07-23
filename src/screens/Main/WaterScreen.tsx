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
import { useTheme } from "../../hooks/useTheme";

const WaterScreen = () => {
  const { colors } = useTheme();
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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

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
      <View style={[styles.levelCard, { backgroundColor: colors.card }]}>
        <View style={styles.levelHeader}>
          <Text style={[styles.levelTitle, { color: colors.text }]}>
            💧 Water Level
          </Text>
          <Text
            style={[
              styles.levelStatus,
              percentage < 20
                ? [styles.levelStatusLow, { color: colors.danger }]
                : { color: colors.success },
            ]}
          >
            {percentage < 20 ? "⚠️ LOW" : "✅ OK"}
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

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          ⚡ Quick Release
        </Text>
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
        <TouchableOpacity
          style={[
            styles.customReleaseBtn,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Text style={[styles.customReleaseText, { color: colors.info }]}>
            Custom Duration →
          </Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          🔄 Water Pump
        </Text>
        <View
          style={[
            styles.pumpCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Text style={styles.pumpIcon}>🔧</Text>
          <View style={styles.pumpInfo}>
            <Text style={[styles.pumpLabel, { color: colors.text }]}>
              Main Water Pump
            </Text>
            <View
              style={[
                styles.pumpStatus,
                data?.pump?.status === "ON"
                  ? [
                      styles.pumpRunning,
                      { backgroundColor: colors.successLight },
                    ]
                  : [
                      styles.pumpStopped,
                      { backgroundColor: colors.dangerLight },
                    ],
              ]}
            >
              <Text
                style={[
                  styles.pumpStatusText,
                  {
                    color:
                      data?.pump?.status === "ON"
                        ? colors.success
                        : colors.danger,
                  },
                ]}
              >
                {data?.pump?.status === "ON" ? "▶️ RUNNING" : "⏹️ STOPPED"}
              </Text>
            </View>
          </View>
        </View>
      </View>

      <View style={[styles.section, styles.lastSection]}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          📋 Recent Activity
        </Text>
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
            <Text style={[styles.logAction, { color: colors.text }]}>
              💧 Released {log.water_amount} L
            </Text>
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
  levelCard: {
    borderRadius: 16,
    padding: 20,
    margin: 16,
    alignItems: "center",
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
  },
  levelStatus: {
    fontSize: 14,
    fontWeight: "700",
  },
  levelStatusLow: {},
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
  section: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 12,
  },
  releaseButtons: {
    flexDirection: "row",
    gap: 10,
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
  customReleaseBtn: {
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 10,
    borderWidth: 1,
  },
  customReleaseText: {
    fontSize: 14,
    fontWeight: "600",
  },
  pumpCard: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
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
  },
  pumpStatus: {
    marginTop: 4,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: "flex-start",
  },
  pumpRunning: {},
  pumpStopped: {},
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

export default WaterScreen;
