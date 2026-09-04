// src/screens/Automation/GateControlScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
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
import { automation } from "../../api/endpoints";
import { useTheme } from "../../hooks/useTheme";

const GateControlScreen = () => {
  const { colors } = useTheme();
  const [gateStatus, setGateStatus] = useState(false); // false = closed, true = open
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [isToggling, setIsToggling] = useState(false);

  const fetchData = async () => {
    try {
      console.log("📥 Fetching gate data from ESP32...");
      const response = await automation.gate.getStatus();

      // ESP32 returns: { gate: 0 or 1 }
      const data = response.data;
      const isOpen = data.gate === 1;

      setGateStatus(isOpen);
      console.log("📥 Gate status:", isOpen ? "OPEN" : "CLOSED");
    } catch (error: any) {
      console.error("❌ Error fetching gate data:", error);
      Alert.alert(
        "Error",
        "Failed to load gate data. Please check connection to ESP32.",
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

  const handleOpenGate = async () => {
    if (isToggling) return;
    setIsToggling(true);

    try {
      console.log("🔄 Opening gate...");
      await automation.gate.open();
      setGateStatus(true);
      await fetchData();
    } catch (error: any) {
      console.error("❌ Open gate error:", error);
      Alert.alert("Error", "Failed to open gate. Please try again.");
    } finally {
      setIsToggling(false);
    }
  };

  const handleCloseGate = async () => {
    if (isToggling) return;
    setIsToggling(true);

    try {
      console.log("🔄 Closing gate...");
      await automation.gate.close();
      setGateStatus(false);
      await fetchData();
    } catch (error: any) {
      console.error("❌ Close gate error:", error);
      Alert.alert("Error", "Failed to close gate. Please try again.");
    } finally {
      setIsToggling(false);
    }
  };

  const handleToggleGate = async () => {
    if (isToggling) return;
    setIsToggling(true);

    try {
      console.log("🔄 Toggling gate...");
      await automation.gate.toggle();
      // Fetch updated status
      await fetchData();
    } catch (error: any) {
      console.error("❌ Toggle gate error:", error);
      Alert.alert("Error", "Failed to toggle gate. Please try again.");
    } finally {
      setIsToggling(false);
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
          <FontAwesome5
            name="door-open"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Gate Control
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Open and close the poultry house gate
        </Text>
      </View>

      {/* Gate Status Card */}
      <View style={[styles.statusCard, { backgroundColor: colors.card }]}>
        <View
          style={[
            styles.gateIconContainer,
            {
              backgroundColor: colors.backgroundSecondary || colors.card + "80",
            },
          ]}
        >
          <FontAwesome5
            name={gateStatus ? "door-open" : "door-closed"}
            size={50}
            color={gateStatus ? colors.success : colors.danger}
          />
        </View>

        <Text style={[styles.gateStatusLabel, { color: colors.textMuted }]}>
          Gate is
        </Text>
        <Text
          style={[
            styles.gateStatusText,
            gateStatus
              ? [styles.statusOpen, { color: colors.success }]
              : [styles.statusClosed, { color: colors.danger }],
          ]}
        >
          {gateStatus ? "OPEN" : "CLOSED"}
        </Text>

        <Text style={[styles.gatePosition, { color: colors.textMuted }]}>
          {gateStatus ? "🔓 Unlocked" : "🔒 Locked"}
        </Text>

        {/* Toggle Button */}
        <TouchableOpacity
          style={[
            styles.toggleBtn,
            gateStatus
              ? [styles.toggleClose, { backgroundColor: colors.danger }]
              : [styles.toggleOpen, { backgroundColor: colors.success }],
          ]}
          onPress={handleToggleGate}
          disabled={isToggling}
        >
          <Text style={styles.toggleBtnText}>
            {isToggling
              ? "Processing..."
              : gateStatus
                ? "Close Gate"
                : "Open Gate"}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Quick Action Buttons */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
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
              styles.actionOpen,
              {
                backgroundColor: colors.success,
                opacity: gateStatus || isToggling ? 0.5 : 1,
              },
            ]}
            onPress={handleOpenGate}
            disabled={gateStatus || isToggling}
          >
            <FontAwesome5 name="door-open" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Open</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionClose,
              {
                backgroundColor: colors.danger,
                opacity: !gateStatus || isToggling ? 0.5 : 1,
              },
            ]}
            onPress={handleCloseGate}
            disabled={!gateStatus || isToggling}
          >
            <FontAwesome5 name="door-closed" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Close</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Gate Position Indicator */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="radio-button-on"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Gate Position
          </Text>
        </View>

        <View
          style={[
            styles.positionCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <View style={styles.positionIndicator}>
            <View
              style={[
                styles.positionBar,
                {
                  width: gateStatus ? "100%" : "0%",
                  backgroundColor: gateStatus ? colors.success : colors.danger,
                },
              ]}
            />
          </View>
          <View style={styles.positionLabels}>
            <Text style={[styles.positionLabel, { color: colors.textMuted }]}>
              Closed
            </Text>
            <Text style={[styles.positionLabel, { color: colors.text }]}>
              {gateStatus ? "🟢 100% Open" : "🔴 0% Open"}
            </Text>
            <Text style={[styles.positionLabel, { color: colors.textMuted }]}>
              Open
            </Text>
          </View>
        </View>
      </View>

      {/* Info Section */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="information-circle-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Gate Information
          </Text>
        </View>

        <View
          style={[
            styles.infoCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <View style={styles.infoRow}>
            <Text style={[styles.infoLabel, { color: colors.textMuted }]}>
              Status
            </Text>
            <Text style={[styles.infoValue, { color: colors.text }]}>
              {gateStatus ? "Open" : "Closed"}
            </Text>
          </View>
          <View style={[styles.infoRow, { borderColor: colors.border }]}>
            <Text style={[styles.infoLabel, { color: colors.textMuted }]}>
              Mode
            </Text>
            <Text style={[styles.infoValue, { color: colors.text }]}>
              Manual
            </Text>
          </View>
          <View style={[styles.infoRow, { borderColor: colors.border }]}>
            <Text style={[styles.infoLabel, { color: colors.textMuted }]}>
              Control
            </Text>
            <Text style={[styles.infoValue, { color: colors.text }]}>
              Servo Motor
            </Text>
          </View>
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
  gateIconContainer: {
    width: 100,
    height: 100,
    borderRadius: 50,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  gateStatusLabel: {
    fontSize: 14,
  },
  gateStatusText: {
    fontSize: 32,
    fontWeight: "800",
    marginVertical: 4,
  },
  statusOpen: {},
  statusClosed: {},
  gatePosition: {
    fontSize: 14,
    marginBottom: 8,
  },
  toggleBtn: {
    paddingHorizontal: 40,
    paddingVertical: 14,
    borderRadius: 30,
    marginTop: 12,
    width: "80%",
    alignItems: "center",
  },
  toggleOpen: {},
  toggleClose: {},
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
  actionOpen: {},
  actionClose: {},
  actionBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  positionCard: {
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  positionIndicator: {
    height: 20,
    borderRadius: 10,
    backgroundColor: "#E0E0E0",
    overflow: "hidden",
  },
  positionBar: {
    height: "100%",
    borderRadius: 10,
  },
  positionLabels: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 8,
  },
  positionLabel: {
    fontSize: 12,
  },
  infoCard: {
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  infoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    paddingVertical: 8,
    borderBottomWidth: 1,
  },
  infoLabel: {
    fontSize: 14,
  },
  infoValue: {
    fontSize: 14,
    fontWeight: "600",
  },
  footer: {
    height: 40,
  },
});

export default GateControlScreen;
