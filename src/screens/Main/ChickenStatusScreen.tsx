// src/screens/Main/ChickenStatusScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
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
import { useTheme } from "../../hooks/useTheme";

const ChickenStatusScreen = () => {
  const { colors } = useTheme();
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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const healthy = data?.healthyChicks || 0;
  const weak = data?.weakChicks || 0;
  const total = data?.totalChicks || 5;
  const unhealthy = total - healthy - weak;

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
            name="drumstick-bite"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Chicken Health Status
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          AI-powered health monitoring
        </Text>
      </View>

      <View style={styles.statusCards}>
        <View
          style={[
            styles.statusCard,
            styles.healthyCard,
            { backgroundColor: colors.card },
          ]}
        >
          <Ionicons name="checkmark-circle" size={32} color={colors.success} />
          <Text style={[styles.statusValue, { color: colors.success }]}>
            {healthy}
          </Text>
          <Text style={[styles.statusLabel, { color: colors.textMuted }]}>
            Healthy
          </Text>
        </View>
        <View
          style={[
            styles.statusCard,
            styles.weakCard,
            { backgroundColor: colors.card },
          ]}
        >
          <Ionicons name="warning" size={32} color={colors.warning} />
          <Text style={[styles.statusValue, { color: colors.warning }]}>
            {weak}
          </Text>
          <Text style={[styles.statusLabel, { color: colors.textMuted }]}>
            Weak
          </Text>
        </View>
        <View
          style={[
            styles.statusCard,
            styles.unhealthyCard,
            { backgroundColor: colors.card },
          ]}
        >
          <Ionicons name="close-circle" size={32} color={colors.danger} />
          <Text style={[styles.statusValue, { color: colors.danger }]}>
            {unhealthy}
          </Text>
          <Text style={[styles.statusLabel, { color: colors.textMuted }]}>
            Unhealthy
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
            name="people-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Individual Chick Status
          </Text>
        </View>
        {data?.chickDetails?.map((chick: any, index: number) => {
          const statusColors: Record<string, string> = {
            healthy: colors.success,
            weak: colors.warning,
            unhealthy: colors.danger,
          };
          return (
            <View
              key={index}
              style={[
                styles.chickCard,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                },
              ]}
            >
              <View style={styles.chickHeader}>
                <Text style={[styles.chickId, { color: colors.text }]}>
                  {chick.id}
                </Text>
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
                <View style={styles.chickDetailItem}>
                  <Ionicons
                    name="scale-outline"
                    size={14}
                    color={colors.textMuted}
                  />
                  <Text
                    style={[styles.chickDetail, { color: colors.textMuted }]}
                  >
                    Weight: {chick.weight}
                  </Text>
                </View>
                <View style={styles.chickDetailItem}>
                  <Ionicons
                    name="calendar-outline"
                    size={14}
                    color={colors.textMuted}
                  />
                  <Text
                    style={[styles.chickDetail, { color: colors.textMuted }]}
                  >
                    Age: {chick.age}
                  </Text>
                </View>
                <View style={styles.chickDetailItem}>
                  <Ionicons
                    name="time-outline"
                    size={14}
                    color={colors.textMuted}
                  />
                  <Text
                    style={[styles.chickDetail, { color: colors.textMuted }]}
                  >
                    Last: {chick.last_detection}
                  </Text>
                </View>
              </View>
            </View>
          );
        })}
      </View>

      <TouchableOpacity
        style={[styles.viewAllBtn, { backgroundColor: colors.primary }]}
      >
        <Text style={[styles.viewAllText, { color: "#FFFFFF" }]}>
          View Full Detection History →
        </Text>
      </TouchableOpacity>
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
  statusCards: {
    flexDirection: "row",
    paddingHorizontal: 16,
    gap: 12,
  },
  statusCard: {
    flex: 1,
    borderRadius: 16,
    padding: 16,
    alignItems: "center",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  healthyCard: {
    borderTopWidth: 4,
    borderTopColor: "#4D724D",
  },
  weakCard: {
    borderTopWidth: 4,
    borderTopColor: "#C8A24A",
  },
  unhealthyCard: {
    borderTopWidth: 4,
    borderTopColor: "#A44A3F",
  },
  statusValue: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 4,
  },
  statusLabel: {
    fontSize: 14,
    marginTop: 2,
  },
  section: {
    paddingHorizontal: 16,
    marginTop: 20,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
  },
  chickCard: {
    borderRadius: 12,
    padding: 16,
    marginBottom: 10,
    borderWidth: 1,
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
    flexWrap: "wrap",
  },
  chickDetailItem: {
    flexDirection: "row",
    alignItems: "center",
    marginRight: 16,
    marginTop: 4,
  },
  chickDetail: {
    fontSize: 13,
    marginLeft: 4,
  },
  viewAllBtn: {
    borderRadius: 12,
    padding: 16,
    margin: 16,
    alignItems: "center",
  },
  viewAllText: {
    fontSize: 14,
    fontWeight: "600",
  },
});

export default ChickenStatusScreen;
