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

  // Check if dark mode by checking background color or text color
  // Since pinalight natin ang dark mode, we can check if background is light or dark
  const isLightMode = colors.background === "#F5F5F5" || colors.background === "#E8EDE8";

  const chartData = {
    labels: chartLabels,
    datasets: [
      {
        data: tempData,
        color: (opacity = 1) => {
          return isLightMode 
            ? `rgba(185, 119, 42, ${opacity})` // Orange for light mode
            : `rgba(200, 154, 58, ${opacity})`; // Lighter orange for dark mode
        },
        strokeWidth: 2,
      },
      {
        data: humidityData,
        color: (opacity = 1) => {
          return isLightMode
            ? `rgba(79, 108, 122, ${opacity})` // Info color for light mode
            : `rgba(90, 122, 138, ${opacity})`; // Lighter info for dark mode
        },
        strokeWidth: 2,
      },
    ],
    legend: ["Temperature (°C)", "Humidity (%)"],
  };

  const currentTemp = tempData[tempData.length - 1] || 0;
  const currentHumidity = humidityData[humidityData.length - 1] || 0;

  const getChartTextColor = () => {
    if (isLightMode) {
      return "44, 62, 44"; // Dark green for light mode
    } else {
      return "26, 42, 26"; // Darker for dark mode
    }
  };

  const getLabelColor = () => {
    if (isLightMode) {
      return "77, 114, 77"; // Primary dark for light mode
    } else {
      return "58, 92, 58"; // Medium green for dark mode
    }
  };

  const chartWidth = width - 48;

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: colors.background }]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
      contentContainerStyle={styles.scrollContent}
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
        <View
          style={[
            styles.currentCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <FontAwesome5
            name="thermometer-half"
            size={28}
            color={colors.orange || "#B9772A"}
          />
          <Text
            style={[
              styles.currentValue,
              { color: colors.orange || "#B9772A" },
            ]}
          >
            {currentTemp}°C
          </Text>
          <Text style={[styles.currentLabel, { color: colors.textMuted }]}>
            Current Temperature
          </Text>
        </View>
        <View
          style={[
            styles.currentCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <FontAwesome5
            name="tint"
            size={28}
            color={colors.info || "#4F6C7A"}
          />
          <Text
            style={[styles.currentValue, { color: colors.info || "#4F6C7A" }]}
          >
            {currentHumidity}%
          </Text>
          <Text style={[styles.currentLabel, { color: colors.textMuted }]}>
            Current Humidity
          </Text>
        </View>
      </View>

      <View
        style={[
          styles.chartCard,
          {
            backgroundColor: colors.card,
            shadowColor: colors.shadowDark,
          },
        ]}
      >
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
          width={chartWidth}
          height={200}
          chartConfig={{
            backgroundColor: colors.card,
            backgroundGradientFrom: colors.card,
            backgroundGradientTo: colors.card,
            decimalPlaces: 1,
            color: (opacity = 1) =>
              `rgba(${getChartTextColor()}, ${opacity})`,
            labelColor: (opacity = 1) =>
              `rgba(${getLabelColor()}, ${opacity})`,
            style: {
              borderRadius: 16,
            },
            propsForDots: {
              r: "4",
              strokeWidth: "2",
              stroke: isLightMode ? "#4D724D" : "#5A8A5A",
            },
            propsForBackgroundLines: {
              strokeDasharray: "5, 5",
              stroke: isLightMode ? "rgba(77, 114, 77, 0.15)" : "rgba(90, 138, 90, 0.15)",
            },
          }}
          bezier
          style={styles.chart}
          withDots={true}
          withInnerLines={true}
          withOuterLines={true}
          withVerticalLines={false}
          withHorizontalLines={true}
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
  scrollContent: {
    paddingBottom: 20,
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
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 12,
  },
  currentCard: {
    flex: 1,
    borderRadius: 16,
    padding: 16,
    alignItems: "center",
    borderWidth: 1,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  currentValue: {
    fontSize: 28,
    fontWeight: "800",
    marginTop: 6,
  },
  currentLabel: {
    fontSize: 12,
    marginTop: 2,
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
    fontSize: 15,
    fontWeight: "600",
    marginBottom: 0,
  },
  chart: {
    marginLeft: -20,
    marginRight: -8,
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
    fontSize: 17,
    fontWeight: "700",
    marginTop: 2,
  },
  statLabel: {
    fontSize: 11,
    marginTop: 2,
  },
});

export default TemperatureScreen;