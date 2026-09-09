// src/screens/Main/ChickenStatusScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Dimensions,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { LineChart, PieChart } from "react-native-chart-kit";
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

const screenWidth = Dimensions.get("window").width;

const ChickenStatusScreen = () => {
  const { colors } = useTheme();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState("All");
  const [searchQuery, setSearchQuery] = useState("");

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
  const unhealthy = data?.unhealthyChicks || 0;
  const total = healthy + weak + unhealthy || 1;

  // Filter chicks based on status
  const filteredChicks =
    data?.chickDetails?.filter((chick: any) => {
      const matchesFilter =
        filter === "All" || chick.status === filter.toLowerCase();
      const matchesSearch = chick.id
        .toLowerCase()
        .includes(searchQuery.toLowerCase());
      return matchesFilter && matchesSearch;
    }) || [];

  // Prepare data for pie chart
  const pieData = [
    {
      name: "Healthy",
      population: healthy,
      color: colors.success || "#4D724D",
      legendFontColor: colors.text || "#333",
      legendFontSize: 12,
    },
    {
      name: "Weak",
      population: weak,
      color: colors.warning || "#C8A24A",
      legendFontColor: colors.text || "#333",
      legendFontSize: 12,
    },
    {
      name: "Unhealthy",
      population: unhealthy,
      color: colors.danger || "#A44A3F",
      legendFontColor: colors.text || "#333",
      legendFontSize: 12,
    },
  ];

  // Sample health trend data (7 days)
  const trendData = {
    labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
    datasets: [
      {
        data: data?.trend?.healthy || [8, 7, 9, 6, 5, 4, 3],
        color: (opacity = 1) => `rgba(77, 114, 77, ${opacity})`,
        strokeWidth: 2,
      },
      {
        data: data?.trend?.weak || [2, 3, 1, 4, 3, 2, 1],
        color: (opacity = 1) => `rgba(200, 162, 74, ${opacity})`,
        strokeWidth: 2,
      },
      {
        data: data?.trend?.unhealthy || [0, 1, 0, 2, 4, 5, 6],
        color: (opacity = 1) => `rgba(164, 74, 63, ${opacity})`,
        strokeWidth: 2,
      },
    ],
    legend: ["Healthy", "Weak", "Unhealthy"],
  };

  // Prepare detection history
  const detectionHistory = data?.detectionHistory || [];

  // Helper function to get status color
  const getStatusColor = (status: string) => {
    const statusMap: Record<string, string> = {
      healthy: colors.success || "#4D724D",
      weak: colors.warning || "#C8A24A",
      unhealthy: colors.danger || "#A44A3F",
    };
    return statusMap[status?.toLowerCase()] || colors.textMuted || "#999";
  };

  // Helper function to get status icon - using only valid Ionicons names
  const getStatusIcon = (status: string): any => {
    const iconMap: Record<string, any> = {
      healthy: "checkmark-circle",
      weak: "warning-outline",
      unhealthy: "close-circle",
    };
    const defaultIcon = "help-circle-outline";
    return iconMap[status?.toLowerCase()] || defaultIcon;
  };

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: colors.background }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      {/* Header */}
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
          AI-powered detection with confidence scoring
        </Text>
      </View>

      {/* Alert Banner */}
      {unhealthy > 0 && (
        <View
          style={[
            styles.alertBanner,
            { backgroundColor: colors.danger + "15" },
          ]}
        >
          <Ionicons name="alert-circle" size={20} color={colors.danger} />
          <Text style={[styles.alertText, { color: colors.danger }]}>
            {unhealthy} unhealthy chick(s) detected. Immediate attention
            required!
          </Text>
          <Text style={[styles.alertTime, { color: colors.textMuted }]}>
            Just now
          </Text>
        </View>
      )}

      {/* Status Cards */}
      <View style={styles.statusCards}>
        <View
          style={[
            styles.statusCard,
            styles.healthyCard,
            { backgroundColor: colors.card },
          ]}
        >
          <Ionicons name="checkmark-circle" size={28} color={colors.success} />
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
          <Ionicons name="alert-circle" size={28} color={colors.warning} />
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
          <Ionicons name="close-circle" size={28} color={colors.danger} />
          <Text style={[styles.statusValue, { color: colors.danger }]}>
            {unhealthy}
          </Text>
          <Text style={[styles.statusLabel, { color: colors.textMuted }]}>
            Unhealthy
          </Text>
        </View>
      </View>

      {/* Pie Chart - Current Distribution */}
      <View style={[styles.chartContainer, { backgroundColor: colors.card }]}>
        <View style={styles.chartHeader}>
          <Text style={[styles.chartTitle, { color: colors.text }]}>
            Current Distribution
          </Text>
          <Text style={[styles.chartSubtitle, { color: colors.textMuted }]}>
            AI Analyzed • {total} total chicks
          </Text>
        </View>
        <PieChart
          data={pieData}
          width={screenWidth - 40}
          height={200}
          chartConfig={{
            color: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,
            labelColor: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,
          }}
          accessor="population"
          backgroundColor="transparent"
          paddingLeft="15"
          absolute
        />
      </View>

      {/* Health Trend Chart */}
      <View style={[styles.chartContainer, { backgroundColor: colors.card }]}>
        <View style={styles.chartHeader}>
          <Text style={[styles.chartTitle, { color: colors.text }]}>
            Health Trend (7 Days)
          </Text>
          <Text style={[styles.chartSubtitle, { color: colors.textMuted }]}>
            Daily health status distribution
          </Text>
        </View>
        <LineChart
          data={trendData}
          width={screenWidth - 40}
          height={220}
          chartConfig={{
            backgroundColor: colors.card || "#fff",
            backgroundGradientFrom: colors.card || "#fff",
            backgroundGradientTo: colors.card || "#fff",
            decimalPlaces: 0,
            color: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,
            labelColor: (opacity = 1) => `rgba(0, 0, 0, ${opacity})`,
            style: {
              borderRadius: 16,
            },
            propsForDots: {
              r: "6",
              strokeWidth: "2",
              stroke: "#fff",
            },
          }}
          bezier
          style={styles.chart}
          formatYLabel={(value) => value.toString()}
        />
        <View style={styles.legendContainer}>
          <View style={styles.legendItem}>
            <View
              style={[styles.legendDot, { backgroundColor: colors.success }]}
            />
            <Text style={[styles.legendText, { color: colors.textMuted }]}>
              Healthy
            </Text>
          </View>
          <View style={styles.legendItem}>
            <View
              style={[styles.legendDot, { backgroundColor: colors.warning }]}
            />
            <Text style={[styles.legendText, { color: colors.textMuted }]}>
              Weak
            </Text>
          </View>
          <View style={styles.legendItem}>
            <View
              style={[styles.legendDot, { backgroundColor: colors.danger }]}
            />
            <Text style={[styles.legendText, { color: colors.textMuted }]}>
              Unhealthy
            </Text>
          </View>
        </View>
      </View>

      {/* Filter and Search */}
      <View style={styles.filterContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {["All", "Healthy", "Weak", "Unhealthy"].map((status) => (
            <TouchableOpacity
              key={status}
              style={[
                styles.filterBtn,
                filter === status && { backgroundColor: colors.primary },
                { borderColor: colors.border },
              ]}
              onPress={() => setFilter(status)}
            >
              <Text
                style={[
                  styles.filterText,
                  { color: filter === status ? "#fff" : colors.textMuted },
                ]}
              >
                {status}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
        <View
          style={[
            styles.searchContainer,
            { backgroundColor: colors.border + "30" },
          ]}
        >
          <Ionicons name="search-outline" size={20} color={colors.textMuted} />
          <TextInput
            style={[styles.searchInput, { color: colors.text }]}
            placeholder="Search chick ID..."
            placeholderTextColor={colors.textMuted}
            value={searchQuery}
            onChangeText={setSearchQuery}
          />
        </View>
      </View>

      {/* Individual Chick Status */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <Ionicons
              name="people-outline"
              size={20}
              color={colors.textSecondary}
              style={{ marginRight: 8 }}
            />
            <Text
              style={[styles.sectionTitle, { color: colors.textSecondary }]}
            >
              Individual Chick Status
            </Text>
          </View>
          <Text style={[styles.sectionCount, { color: colors.textMuted }]}>
            Total: {filteredChicks.length} chicks
          </Text>
        </View>

        {filteredChicks.map((chick: any, index: number) => {
          const statusColor = getStatusColor(chick.status);
          const statusIcon = getStatusIcon(chick.status);

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
                    { backgroundColor: statusColor + "20" },
                  ]}
                >
                  <Ionicons
                    name={statusIcon}
                    size={12}
                    color={statusColor}
                    style={{ marginRight: 4 }}
                  />
                  <Text
                    style={[styles.chickStatusText, { color: statusColor }]}
                  >
                    {chick.status?.toUpperCase() || "UNKNOWN"}
                  </Text>
                </View>
              </View>

              <View style={styles.chickDetails}>
                <View style={styles.chickDetailItem}>
                  <Ionicons
                    name="stats-chart-outline"
                    size={14}
                    color={colors.textMuted}
                  />
                  <Text
                    style={[styles.chickDetail, { color: colors.textMuted }]}
                  >
                    Confidence: {chick.confidence || "97.4%"}
                  </Text>
                </View>
                <View style={styles.chickDetailItem}>
                  <Ionicons
                    name="flash-outline"
                    size={14}
                    color={colors.textMuted}
                  />
                  <Text
                    style={[styles.chickDetail, { color: colors.textMuted }]}
                  >
                    {chick.activity || "Active"}
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
                    Last: {chick.last_detection || "01:51 AM"}
                  </Text>
                </View>
              </View>
            </View>
          );
        })}
      </View>

      {/* Detection History Preview */}
      {detectionHistory.length > 0 && (
        <View
          style={[styles.historyContainer, { backgroundColor: colors.card }]}
        >
          <View style={styles.historyHeader}>
            <Text style={[styles.historyTitle, { color: colors.text }]}>
              Detection History
            </Text>
            <TouchableOpacity>
              <Text style={[styles.historyViewAll, { color: colors.primary }]}>
                View All →
              </Text>
            </TouchableOpacity>
          </View>
          {detectionHistory.slice(0, 5).map((item: any, index: number) => {
            const statusColor = getStatusColor(item.status);
            return (
              <View
                key={index}
                style={[styles.historyItem, { borderColor: colors.border }]}
              >
                <View style={styles.historyLeft}>
                  <Text
                    style={[styles.historyTime, { color: colors.textMuted }]}
                  >
                    {item.time}
                  </Text>
                  <Text style={[styles.historyChickId, { color: colors.text }]}>
                    {item.chickId}
                  </Text>
                </View>
                <View style={styles.historyCenter}>
                  <Text style={[styles.historyStatus, { color: statusColor }]}>
                    {item.status || "Unknown"}
                  </Text>
                  <Text
                    style={[
                      styles.historyConfidence,
                      { color: colors.textMuted },
                    ]}
                  >
                    {item.confidence || "--"}
                  </Text>
                </View>
                <Text
                  style={[styles.historyActivity, { color: colors.textMuted }]}
                >
                  {item.activity || "--"}
                </Text>
              </View>
            );
          })}
        </View>
      )}

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
    fontSize: 13,
    marginTop: 4,
    marginLeft: 36,
  },
  alertBanner: {
    flexDirection: "row",
    alignItems: "center",
    marginHorizontal: 16,
    marginTop: 12,
    padding: 12,
    borderRadius: 12,
    flexWrap: "wrap",
  },
  alertText: {
    fontSize: 14,
    fontWeight: "600",
    marginLeft: 8,
    flex: 1,
  },
  alertTime: {
    fontSize: 12,
    marginLeft: 8,
  },
  statusCards: {
    flexDirection: "row",
    paddingHorizontal: 16,
    marginTop: 16,
    gap: 12,
  },
  statusCard: {
    flex: 1,
    borderRadius: 16,
    padding: 14,
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
    fontSize: 28,
    fontWeight: "800",
    marginTop: 2,
  },
  statusLabel: {
    fontSize: 13,
    marginTop: 2,
  },
  chartContainer: {
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 16,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  chartHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  chartTitle: {
    fontSize: 16,
    fontWeight: "700",
  },
  chartSubtitle: {
    fontSize: 12,
  },
  chart: {
    marginVertical: 8,
    borderRadius: 16,
  },
  legendContainer: {
    flexDirection: "row",
    justifyContent: "center",
    gap: 16,
    marginTop: 8,
  },
  legendItem: {
    flexDirection: "row",
    alignItems: "center",
  },
  legendDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    marginRight: 6,
  },
  legendText: {
    fontSize: 12,
  },
  filterContainer: {
    paddingHorizontal: 16,
    marginTop: 16,
  },
  filterBtn: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    marginRight: 8,
  },
  filterText: {
    fontSize: 13,
    fontWeight: "500",
  },
  searchContainer: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 12,
    paddingHorizontal: 12,
    marginTop: 12,
    height: 44,
  },
  searchInput: {
    flex: 1,
    marginLeft: 8,
    fontSize: 14,
    paddingVertical: 8,
  },
  section: {
    paddingHorizontal: 16,
    marginTop: 16,
  },
  sectionHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
  },
  sectionCount: {
    fontSize: 13,
  },
  chickCard: {
    borderRadius: 12,
    padding: 14,
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
    fontSize: 15,
    fontWeight: "700",
  },
  chickStatus: {
    flexDirection: "row",
    alignItems: "center",
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
  historyContainer: {
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 16,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  historyHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  historyTitle: {
    fontSize: 16,
    fontWeight: "700",
  },
  historyViewAll: {
    fontSize: 14,
    fontWeight: "600",
  },
  historyItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 10,
    borderBottomWidth: 1,
  },
  historyLeft: {
    flex: 1,
  },
  historyTime: {
    fontSize: 11,
  },
  historyChickId: {
    fontSize: 13,
    fontWeight: "600",
    marginTop: 2,
  },
  historyCenter: {
    alignItems: "center",
    flex: 1,
  },
  historyStatus: {
    fontSize: 13,
    fontWeight: "600",
  },
  historyConfidence: {
    fontSize: 11,
    marginTop: 2,
  },
  historyActivity: {
    fontSize: 12,
    flex: 1,
    textAlign: "right",
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
