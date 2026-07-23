// src/screens/AI/DetectionHistoryScreen.tsx
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import api from "../../api/client";

const DetectionHistoryScreen = () => {
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");

  const fetchHistory = async () => {
    try {
      const response = await api.get("/detection/history");
      if (response.data.success) {
        setHistory(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching history:", error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchHistory();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchHistory();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case "healthy":
        return "#27AE60";
      case "weak":
        return "#F39C12";
      case "unhealthy":
        return "#E74C3C";
      default:
        return "#8B7355";
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "healthy":
        return "✅";
      case "weak":
        return "⚠️";
      case "unhealthy":
        return "❌";
      default:
        return "❓";
    }
  };

  const filteredHistory = history.filter((item: any) => {
    if (filter !== "all" && item.status !== filter) return false;
    if (search && !item.chick_id.toLowerCase().includes(search.toLowerCase()))
      return false;
    return true;
  });

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
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
      <Text style={styles.title}>📋 Detection History</Text>
      <Text style={styles.subtitle}>AI health detection records</Text>

      {/* Search */}
      <View style={styles.searchContainer}>
        <TextInput
          style={styles.searchInput}
          placeholder="🔍 Search by chick ID..."
          value={search}
          onChangeText={setSearch}
        />
      </View>

      {/* Filters */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.filtersContainer}
      >
        {["all", "healthy", "weak", "unhealthy"].map((f) => (
          <TouchableOpacity
            key={f}
            style={[styles.filterBtn, filter === f && styles.filterBtnActive]}
            onPress={() => setFilter(f)}
          >
            <Text
              style={[
                styles.filterText,
                filter === f && styles.filterTextActive,
              ]}
            >
              {f.charAt(0).toUpperCase() + f.slice(1)}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {/* History List */}
      {filteredHistory.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyIcon}>🔍</Text>
          <Text style={styles.emptyTitle}>No records found</Text>
          <Text style={styles.emptyDesc}>Try adjusting your filters</Text>
        </View>
      ) : (
        filteredHistory.map((item: any, index: number) => (
          <View key={index} style={styles.historyCard}>
            <View style={styles.historyHeader}>
              <Text style={styles.historyId}>{item.chick_id}</Text>
              <View
                style={[
                  styles.historyStatus,
                  { backgroundColor: getStatusColor(item.status) + "20" },
                ]}
              >
                <Text
                  style={[
                    styles.historyStatusText,
                    { color: getStatusColor(item.status) },
                  ]}
                >
                  {getStatusIcon(item.status)} {item.status.toUpperCase()}
                </Text>
              </View>
            </View>
            <View style={styles.historyDetails}>
              <Text style={styles.historyDetail}>🕐 {item.time}</Text>
              <Text style={styles.historyDetail}>
                🎯 Confidence: {item.confidence}%
              </Text>
              <Text style={styles.historyDetail}>⚖️ Weight: {item.weight}</Text>
              <Text style={styles.historyDetail}>
                🏃 Activity: {item.activity}
              </Text>
            </View>
          </View>
        ))
      )}

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
  searchContainer: {
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  searchInput: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.3)",
    fontSize: 14,
  },
  filtersContainer: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  filterBtn: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    marginRight: 8,
    backgroundColor: "#FFFFFF",
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.3)",
  },
  filterBtnActive: {
    backgroundColor: "#FFD62E",
    borderColor: "#FFD62E",
  },
  filterText: {
    fontSize: 12,
    fontWeight: "600",
    color: "#8B7355",
  },
  filterTextActive: {
    color: "#3E2C1C",
  },
  historyCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.02,
    shadowRadius: 2,
    elevation: 1,
  },
  historyHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 8,
  },
  historyId: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  historyStatus: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  historyStatusText: {
    fontSize: 11,
    fontWeight: "700",
  },
  historyDetails: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8,
  },
  historyDetail: {
    fontSize: 13,
    color: "#8B7355",
    marginRight: 8,
  },
  emptyState: {
    alignItems: "center",
    paddingVertical: 60,
  },
  emptyIcon: {
    fontSize: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: "600",
    color: "#3E2C1C",
    marginTop: 12,
  },
  emptyDesc: {
    fontSize: 14,
    color: "#8B7355",
    marginTop: 4,
  },
  footer: {
    height: 20,
  },
});

export default DetectionHistoryScreen;
