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
import { useTheme } from "../../hooks/useTheme";

const DetectionHistoryScreen = () => {
  const { colors } = useTheme();
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
        return "#4D724D";
      case "weak":
        return "#C8A24A";
      case "unhealthy":
        return "#A44A3F";
      default:
        return colors.textMuted;
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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
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
      <Text style={[styles.title, { color: colors.text }]}>
        📋 Detection History
      </Text>
      <Text style={[styles.subtitle, { color: colors.textMuted }]}>
        AI health detection records
      </Text>

      <View style={styles.searchContainer}>
        <TextInput
          style={[
            styles.searchInput,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              color: colors.text,
            },
          ]}
          placeholder="🔍 Search by chick ID..."
          placeholderTextColor={colors.textMuted}
          value={search}
          onChangeText={setSearch}
        />
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={styles.filtersContainer}
      >
        {["all", "healthy", "weak", "unhealthy"].map((f) => (
          <TouchableOpacity
            key={f}
            style={[
              styles.filterBtn,
              {
                backgroundColor: filter === f ? colors.primary : colors.card,
                borderColor: colors.border,
              },
            ]}
            onPress={() => setFilter(f)}
          >
            <Text
              style={[
                styles.filterText,
                { color: filter === f ? colors.text : colors.textMuted },
              ]}
            >
              {f.charAt(0).toUpperCase() + f.slice(1)}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {filteredHistory.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyIcon}>🔍</Text>
          <Text style={[styles.emptyTitle, { color: colors.text }]}>
            No records found
          </Text>
          <Text style={[styles.emptyDesc, { color: colors.textMuted }]}>
            Try adjusting your filters
          </Text>
        </View>
      ) : (
        filteredHistory.map((item: any, index: number) => {
          const statusColor = getStatusColor(item.status);
          return (
            <View
              key={index}
              style={[
                styles.historyCard,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  shadowColor: colors.shadow,
                },
              ]}
            >
              <View style={styles.historyHeader}>
                <Text style={[styles.historyId, { color: colors.text }]}>
                  {item.chick_id}
                </Text>
                <View
                  style={[
                    styles.historyStatus,
                    { backgroundColor: statusColor + "20" },
                  ]}
                >
                  <Text
                    style={[styles.historyStatusText, { color: statusColor }]}
                  >
                    {getStatusIcon(item.status)} {item.status.toUpperCase()}
                  </Text>
                </View>
              </View>
              <View style={styles.historyDetails}>
                <Text
                  style={[styles.historyDetail, { color: colors.textMuted }]}
                >
                  🕐 {item.time}
                </Text>
                <Text
                  style={[styles.historyDetail, { color: colors.textMuted }]}
                >
                  🎯 Confidence: {item.confidence}%
                </Text>
                <Text
                  style={[styles.historyDetail, { color: colors.textMuted }]}
                >
                  ⚖️ Weight: {item.weight}
                </Text>
                <Text
                  style={[styles.historyDetail, { color: colors.textMuted }]}
                >
                  🏃 Activity: {item.activity}
                </Text>
              </View>
            </View>
          );
        })
      )}

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
  title: {
    fontSize: 24,
    fontWeight: "800",
    paddingHorizontal: 20,
    paddingTop: 20,
  },
  subtitle: {
    fontSize: 14,
    paddingHorizontal: 20,
    paddingBottom: 16,
  },
  searchContainer: {
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  searchInput: {
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
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
    borderWidth: 1,
  },
  filterText: {
    fontSize: 12,
    fontWeight: "600",
  },
  historyCard: {
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 10,
    borderWidth: 1,
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
    marginTop: 12,
  },
  emptyDesc: {
    fontSize: 14,
    marginTop: 4,
  },
  footer: {
    height: 20,
  },
});

export default DetectionHistoryScreen;
