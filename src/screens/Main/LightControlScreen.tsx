// src/screens/Main/LightControlScreen.tsx

import Icon from "@expo/vector-icons/Ionicons";
import { useRouter } from "expo-router";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View
} from "react-native";
import { automation } from "../../api/endpoints";
import { useTheme } from "../../hooks/useTheme";

const LightControlScreen = () => {
  const { colors } = useTheme();
  const router = useRouter();
  const [lightStatus, setLightStatus] = useState("OFF");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // Fetch light status from ESP32
  const fetchData = async () => {
    try {
      console.log("📥 Fetching light data from ESP32...");
      const response = await automation.light.getStatus();
      const data = response.data;

      const status = data.light === 1 ? "ON" : "OFF";
      setLightStatus(status);
      console.log("📥 Light status:", status);
    } catch (error: any) {
      console.error("❌ Error fetching light data:", error);
      Alert.alert(
        "Error",
        "Failed to load light data. Please check connection to ESP32.",
      );
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

  // Toggle Light ON/OFF
  const toggleLight = async () => {
    const newStatus = lightStatus === "ON" ? "OFF" : "ON";
    try {
      console.log("🔄 Toggling light to:", newStatus);
      await automation.light.toggle(newStatus);
      setLightStatus(newStatus);
      Alert.alert("Success", `Light turned ${newStatus}`);
      fetchData();
    } catch (error: any) {
      console.error("❌ Toggle error:", error);
      Alert.alert("Error", "Failed to toggle light. Please try again.");
    }
  };

  if (loading) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={[styles.loadingText, { color: colors.textMuted }]}>
          Connecting to ESP32...
        </Text>
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
      <View style={styles.header}>
        <View style={{ flexDirection: "row", alignItems: "center" }}>
          <Icon
            name="bulb-outline"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Light Control
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Control the poultry house lighting
        </Text>
      </View>

      {/* Light Status Card */}
      <View style={[styles.statusCard, { backgroundColor: colors.card }]}>
        <View
          style={[
            styles.lightIconContainer,
            {
              backgroundColor: colors.backgroundSecondary || colors.card + "80",
            },
          ]}
        >
          <Icon
            name={lightStatus === "ON" ? "bulb" : "bulb-outline"}
            size={50}
            color={lightStatus === "ON" ? colors.warning : colors.textMuted}
          />
        </View>
        <Text style={[styles.lightStatusLabel, { color: colors.textMuted }]}>
          Light is
        </Text>
        <Text
          style={[
            styles.lightStatusText,
            lightStatus === "ON"
              ? [styles.statusOn, { color: colors.success }]
              : [styles.statusOff, { color: colors.danger }],
          ]}
        >
          {lightStatus === "ON" ? "ON" : "OFF"}
        </Text>

        {/* Toggle Button */}
        <TouchableOpacity
          style={[
            styles.toggleBtn,
            lightStatus === "ON"
              ? [styles.toggleOn, { backgroundColor: colors.danger }]
              : [styles.toggleOff, { backgroundColor: colors.success }],
          ]}
          onPress={toggleLight}
        >
          <Text style={styles.toggleBtnText}>
            {lightStatus === "ON" ? "Turn OFF" : "Turn ON"}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Icon
            name="flash-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Quick Actions
          </Text>
        </View>

        <View style={styles.actionRow}>
          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionOn,
              {
                backgroundColor: colors.success,
                opacity: lightStatus === "ON" ? 0.5 : 1,
              },
            ]}
            onPress={() => {
              if (lightStatus !== "ON") toggleLight();
            }}
            disabled={lightStatus === "ON"}
          >
            <Icon name="power" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Turn ON</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionOff,
              {
                backgroundColor: colors.danger,
                opacity: lightStatus === "OFF" ? 0.5 : 1,
              },
            ]}
            onPress={() => {
              if (lightStatus !== "OFF") toggleLight();
            }}
            disabled={lightStatus === "OFF"}
          >
            <Icon name="power-outline" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Turn OFF</Text>
          </TouchableOpacity>
        </View>
      </View>

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
  loadingText: {
    marginTop: 12,
    fontSize: 14,
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
  statusCard: {
    borderRadius: 16,
    padding: 24,
    margin: 16,
    alignItems: "center",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  lightIconContainer: {
    width: 100,
    height: 100,
    borderRadius: 50,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  lightStatusLabel: {
    fontSize: 14,
  },
  lightStatusText: {
    fontSize: 32,
    fontWeight: "800",
    marginVertical: 4,
  },
  statusOn: {},
  statusOff: {},
  toggleBtn: {
    paddingHorizontal: 40,
    paddingVertical: 14,
    borderRadius: 30,
    marginTop: 12,
    width: "80%",
    alignItems: "center",
  },
  toggleOn: {},
  toggleOff: {},
  toggleBtnText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  section: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
  },
  actionRow: {
    flexDirection: "row",
    gap: 12,
  },
  actionBtn: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 20,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  actionOn: {},
  actionOff: {},
  actionBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  footer: {
    height: 40,
  },
});

export default LightControlScreen;
