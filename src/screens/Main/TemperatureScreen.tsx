// src/screens/Main/TemperatureScreen.tsx
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

const { width } = Dimensions.get("window");

const TemperatureScreen = () => {
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
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
        <Text style={styles.loadingText}>Loading sensor data...</Text>
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
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      {/* Current Readings */}
      <View style={styles.currentSection}>
        <View style={[styles.currentCard, { borderColor: "#E67E22" }]}>
          <Text style={styles.currentIcon}>🌡️</Text>
          <Text style={[styles.currentValue, { color: "#E67E22" }]}>
            {currentTemp}°C
          </Text>
          <Text style={styles.currentLabel}>Current Temperature</Text>
        </View>
        <View style={[styles.currentCard, { borderColor: "#2980B9" }]}>
          <Text style={styles.currentIcon}>💧</Text>
          <Text style={[styles.currentValue, { color: "#2980B9" }]}>
            {currentHumidity}%
          </Text>
          <Text style={styles.currentLabel}>Current Humidity</Text>
        </View>
      </View>

      {/* Chart */}
      <View style={styles.chartCard}>
        <Text style={styles.chartTitle}>Temperature & Humidity Trend</Text>
        <LineChart
          data={chartData}
          width={width - 32}
          height={220}
          chartConfig={{
            backgroundColor: "#FFFFFF",
            backgroundGradientFrom: "#FFFFFF",
            backgroundGradientTo: "#FFFFFF",
            decimalPlaces: 1,
            color: (opacity = 1) => `rgba(62, 44, 28, ${opacity})`,
            labelColor: (opacity = 1) => `rgba(139, 115, 85, ${opacity})`,
            style: {
              borderRadius: 16,
            },
          }}
          bezier
          style={styles.chart}
        />
      </View>

      {/* Statistics */}
      <View style={styles.statsContainer}>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{Math.max(...tempData)}°C</Text>
          <Text style={styles.statLabel}>Max</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{Math.min(...tempData)}°C</Text>
          <Text style={styles.statLabel}>Min</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>
            {(
              tempData.reduce((a: any, b: any) => a + b, 0) / tempData.length
            ).toFixed(1)}
            °C
          </Text>
          <Text style={styles.statLabel}>Average</Text>
        </View>
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
  loadingText: {
    marginTop: 12,
    color: "#8B7355",
    fontSize: 14,
  },
  currentSection: {
    flexDirection: "row",
    padding: 16,
    gap: 12,
  },
  currentCard: {
    flex: 1,
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 20,
    alignItems: "center",
    borderWidth: 1,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  currentIcon: {
    fontSize: 28,
  },
  currentValue: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 8,
  },
  currentLabel: {
    fontSize: 14,
    color: "#8B7355",
    marginTop: 4,
  },
  chartCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 16,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  chartTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#3E2C1C",
    marginBottom: 12,
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
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 12,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  statValue: {
    fontSize: 18,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  statLabel: {
    fontSize: 11,
    color: "#8B7355",
    marginTop: 4,
  },
});

export default TemperatureScreen;
