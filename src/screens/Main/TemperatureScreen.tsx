// src/screens/Main/TemperatureScreen.tsx
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
  View,
} from "react-native";
import { LineChart } from "react-native-chart-kit";
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

const { width } = Dimensions.get("window");

const TemperatureScreen = () => {
  const { colors } = useTheme();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = async () => {
    try {
      const response = await api.get("/sensors/temperature?period=24h");
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching temperature data:", error);
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
        <Text style={[styles.loadingText, { color: colors.textMuted }]}>
          Loading sensor data...
        </Text>
      </View>
    );
  }

  const chartLabels = data?.labels?.slice(0, 10) || [
    "Mon",
    "Tue",
    "Wed",
    "Thu",
    "Fri",
    "Sat",
    "Sun",
  ];
  const tempData = data?.temperature || [30, 31, 32, 33, 32, 31, 30];
  const humidityData = data?.humidity || [65, 68, 70, 72, 71, 69, 67];

  const chartData = {
    labels: chartLabels,
    datasets: [
      {
        data: tempData,
        color: (opacity = 1) => `rgba(230, 126, 34, ${opacity})`,
        strokeWidth: 2,
      },
      {
        data: humidityData,
        color: (opacity = 1) => `rgba(41, 128, 185, ${opacity})`,
        strokeWidth: 2,
      },
    ],
    legend: ["Temperature (°C)", "Humidity (%)"],
  };

  const currentTemp = tempData[tempData.length - 1] || 0;
  const currentHumidity = humidityData[humidityData.length - 1] || 0;

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
            name="thermometer-half"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Temperature & Humidity
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Real-time environmental monitoring
        </Text>
      </View>

      <View style={styles.currentSection}>
        <View style={[styles.currentCard, { backgroundColor: colors.card }]}>
          <FontAwesome5
            name="thermometer-half"
            size={28}
            color={colors.orange}
          />
          <Text style={[styles.currentValue, { color: colors.orange }]}>
            {currentTemp}°C
          </Text>
          <Text style={[styles.currentLabel, { color: colors.textMuted }]}>
            Current Temperature
          </Text>
        </View>
        <View style={[styles.currentCard, { backgroundColor: colors.card }]}>
          <FontAwesome5 name="tint" size={28} color={colors.info} />
          <Text style={[styles.currentValue, { color: colors.info }]}>
            {currentHumidity}%
          </Text>
          <Text style={[styles.currentLabel, { color: colors.textMuted }]}>
            Current Humidity
          </Text>
        </View>
      </View>

      <View style={[styles.chartCard, { backgroundColor: colors.card }]}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="stats-chart-outline"
            size={20}
            color={colors.text}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.chartTitle, { color: colors.text }]}>
            Temperature & Humidity Trend
          </Text>
        </View>
        <LineChart
          data={chartData}
          width={width - 32}
          height={220}
          chartConfig={{
            backgroundColor: colors.card,
            backgroundGradientFrom: colors.card,
            backgroundGradientTo: colors.card,
            decimalPlaces: 1,
            color: (opacity = 1) =>
              `rgba(${colors.text === "#2C3E2C" ? "44, 62, 44" : "245, 245, 245"}, ${opacity})`,
            labelColor: (opacity = 1) => `rgba(139, 115, 85, ${opacity})`,
            style: {
              borderRadius: 16,
            },
          }}
          bezier
          style={styles.chart}
        />
      </View>

      <View style={styles.statsContainer}>
        <View
          style={[
            styles.statCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <FontAwesome5 name="arrow-up" size={16} color={colors.danger} />
          <Text style={[styles.statValue, { color: colors.text }]}>
            {Math.max(...tempData)}°C
          </Text>
          <Text style={[styles.statLabel, { color: colors.textMuted }]}>
            Max
          </Text>
        </View>
        <View
          style={[
            styles.statCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <FontAwesome5 name="arrow-down" size={16} color={colors.info} />
          <Text style={[styles.statValue, { color: colors.text }]}>
            {Math.min(...tempData)}°C
          </Text>
          <Text style={[styles.statLabel, { color: colors.textMuted }]}>
            Min
          </Text>
        </View>
        <View
          style={[
            styles.statCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Ionicons
            name="calculator-outline"
            size={16}
            color={colors.primary}
          />
          <Text style={[styles.statValue, { color: colors.text }]}>
            {(
              tempData.reduce((a: any, b: any) => a + b, 0) / tempData.length
            ).toFixed(1)}
            °C
          </Text>
          <Text style={[styles.statLabel, { color: colors.textMuted }]}>
            Average
          </Text>
        </View>
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
  loadingText: {
    marginTop: 12,
    fontSize: 14,
  },
  currentSection: {
    flexDirection: "row",
    padding: 16,
    gap: 12,
  },
  currentCard: {
    flex: 1,
    borderRadius: 16,
    padding: 20,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(77, 114, 77, 0.1)",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  currentValue: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 8,
  },
  currentLabel: {
    fontSize: 14,
    marginTop: 4,
  },
  chartCard: {
    borderRadius: 16,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 16,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  chartTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
  },
  chart: {
    marginLeft: -20,
    borderRadius: 16,
  },
  statsContainer: {
    flexDirection: "row",
    paddingHorizontal: 16,
    marginBottom: 20,
    gap: 12,
  },
  statCard: {
    flex: 1,
    borderRadius: 12,
    padding: 12,
    alignItems: "center",
    borderWidth: 1,
  },
  statValue: {
    fontSize: 18,
    fontWeight: "700",
    marginTop: 2,
  },
  statLabel: {
    fontSize: 11,
    marginTop: 2,
  },
});

export default TemperatureScreen;
