// src/screens/Main/ChickenStatusScreen.tsx
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import api from "../../api/client";

const ChickenStatusScreen = () => {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const response = await api.get("/dashboard/stats");
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching chicken data:", error);
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

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  const healthy = data?.healthyChicks || 0;
  const weak = data?.weakChicks || 0;
  const total = data?.totalChicks || 5;
  const unhealthy = total - healthy - weak;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <Text style={styles.title}>🐔 Chicken Health Status</Text>
      <Text style={styles.subtitle}>AI-powered health monitoring</Text>

      {/* Status Cards */}
      <View style={styles.statusCards}>
        <View style={[styles.statusCard, styles.healthyCard]}>
          <Text style={styles.statusIcon}>✅</Text>
          <Text style={[styles.statusValue, { color: "#27AE60" }]}>
            {healthy}
          </Text>
          <Text style={styles.statusLabel}>Healthy</Text>
        </View>
        <View style={[styles.statusCard, styles.weakCard]}>
          <Text style={styles.statusIcon}>⚠️</Text>
          <Text style={[styles.statusValue, { color: "#F39C12" }]}>{weak}</Text>
          <Text style={styles.statusLabel}>Weak</Text>
        </View>
        <View style={[styles.statusCard, styles.unhealthyCard]}>
          <Text style={styles.statusIcon}>❌</Text>
          <Text style={[styles.statusValue, { color: "#E74C3C" }]}>
            {unhealthy}
          </Text>
          <Text style={styles.statusLabel}>Unhealthy</Text>
        </View>
      </View>

      {/* Chick List */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>👥 Individual Chick Status</Text>
        {data?.chickDetails?.map((chick: any, index: number) => {
          const statusColors: Record<string, string> = {
            healthy: "#27AE60",
            weak: "#F39C12",
            unhealthy: "#E74C3C",
          };
          return (
            <View key={index} style={styles.chickCard}>
              <View style={styles.chickHeader}>
                <Text style={styles.chickId}>{chick.id}</Text>
                <View
                  style={[
                    styles.chickStatus,
                    { backgroundColor: statusColors[chick.status] + "20" },
                  ]}
                >
                  <Text
                    style={[
                      styles.chickStatusText,
                      { color: statusColors[chick.status] },
                    ]}
                  >
                    {chick.status.toUpperCase()}
                  </Text>
                </View>
              </View>
              <View style={styles.chickDetails}>
                <Text style={styles.chickDetail}>
                  ⚖️ Weight: {chick.weight}
                </Text>
                <Text style={styles.chickDetail}>📅 Age: {chick.age}</Text>
                <Text style={styles.chickDetail}>
                  🕐 Last: {chick.last_detection}
                </Text>
              </View>
            </View>
          );
        })}
      </View>

      <TouchableOpacity style={styles.viewAllBtn}>
        <Text style={styles.viewAllText}>View Full Detection History →</Text>
      </TouchableOpacity>
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
  statusCards: {
    flexDirection: "row",
    paddingHorizontal: 16,
    gap: 12,
  },
  statusCard: {
    flex: 1,
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 16,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  healthyCard: {
    borderTopWidth: 4,
    borderTopColor: "#27AE60",
  },
  weakCard: {
    borderTopWidth: 4,
    borderTopColor: "#F39C12",
  },
  unhealthyCard: {
    borderTopWidth: 4,
    borderTopColor: "#E74C3C",
  },
  statusIcon: {
    fontSize: 24,
  },
  statusValue: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 4,
  },
  statusLabel: {
    fontSize: 14,
    color: "#8B7355",
    marginTop: 2,
  },
  section: {
    paddingHorizontal: 16,
    marginTop: 20,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#5C4A1E",
    marginBottom: 12,
  },
  chickCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  chickHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 8,
  },
  chickId: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  chickStatus: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  chickStatusText: {
    fontSize: 11,
    fontWeight: "700",
  },
  chickDetails: {
    flexDirection: "row",
    justifyContent: "space-between",
    flexWrap: "wrap",
  },
  chickDetail: {
    fontSize: 13,
    color: "#8B7355",
  },
  viewAllBtn: {
    backgroundColor: "#FFD62E",
    borderRadius: 12,
    padding: 16,
    margin: 16,
    alignItems: "center",
  },
  viewAllText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#3E2C1C",
  },
});

export default ChickenStatusScreen;
