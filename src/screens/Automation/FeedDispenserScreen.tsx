// src/screens/Automation/FeedDispenserScreen.tsx
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    Modal,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Switch,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { api } from "../../api/client";

const FeedDispenserScreen = () => {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [autoMode, setAutoMode] = useState(true);
  const [customAmount, setCustomAmount] = useState("0.5");

  // Refill Modal
  const [refillModalVisible, setRefillModalVisible] = useState(false);
  const [refillAmount, setRefillAmount] = useState("");

  const fetchData = async () => {
    try {
      const response = await api.get("/automation/feeder");
      if (response.data.success) {
        setData(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching feeder data:", error);
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

  const handleDispense = async (amount: number) => {
    try {
      const response = await api.post("/automation/feeder", {
        action: "dispense",
        amount,
      });
      if (response.data.success) {
        Alert.alert("Success", `Dispensed ${amount} kg of feed`);
        fetchData();
      }
    } catch (error) {
      Alert.alert("Error", "Failed to dispense feed");
    }
  };

  const handleRefill = async () => {
    const amount = parseFloat(refillAmount);
    if (!amount || amount <= 0) {
      Alert.alert("Error", "Please enter a valid amount");
      return;
    }

    try {
      const response = await api.post("/automation/feeder", {
        action: "refill",
        amount,
      });
      if (response.data.success) {
        Alert.alert("Success", `Added ${amount} kg of feed`);
        setRefillModalVisible(false);
        setRefillAmount("");
        fetchData();
      }
    } catch (error) {
      Alert.alert("Error", "Failed to refill feed");
    }
  };

  const toggleAutoMode = async () => {
    const newMode = !autoMode;
    try {
      await api.post("/automation/feeder", {
        action: "settings",
        auto_mode: newMode,
      });
      setAutoMode(newMode);
      Alert.alert("Success", `Auto mode ${newMode ? "enabled" : "disabled"}`);
    } catch (error) {
      Alert.alert("Error", "Failed to update settings");
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  const inventory = data?.inventory || { current_level: 0, capacity: 50 };
  const percentage = (inventory.current_level / inventory.capacity) * 100;
  const isLow = percentage < 20;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <Text style={styles.title}>🍗 Feed Dispenser</Text>
      <Text style={styles.subtitle}>Automated feeding system</Text>

      {/* Feed Level */}
      <View style={styles.levelCard}>
        <View style={styles.levelHeader}>
          <Text style={styles.levelTitle}>📦 Feed Level</Text>
          <Text style={[styles.levelStatus, isLow && styles.levelStatusLow]}>
            {isLow ? "⚠️ LOW" : "✅ OK"}
          </Text>
        </View>
        <View style={styles.progressBar}>
          <View
            style={[
              styles.progressFill,
              {
                width: `${Math.min(percentage, 100)}%`,
                backgroundColor: isLow ? "#E74C3C" : "#FFD62E",
              },
            ]}
          />
        </View>
        <View style={styles.levelDetails}>
          <Text style={styles.levelText}>
            {inventory.current_level.toFixed(1)} kg / {inventory.capacity} kg
          </Text>
          <Text style={styles.levelPercent}>{percentage.toFixed(0)}%</Text>
        </View>
        <TouchableOpacity
          style={styles.refillBtn}
          onPress={() => setRefillModalVisible(true)}
        >
          <Text style={styles.refillBtnText}>➕ Refill Feed</Text>
        </TouchableOpacity>
      </View>

      {/* Automation Status */}
      <View style={styles.autoCard}>
        <View style={styles.autoRow}>
          <View>
            <Text style={styles.autoLabel}>🤖 Auto Mode</Text>
            <Text style={styles.autoDesc}>
              {autoMode ? "Feed dispensed automatically" : "Manual mode only"}
            </Text>
          </View>
          <Switch
            value={autoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: "#FFD62E" }}
          />
        </View>
      </View>

      {/* Manual Dispense */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>✋ Manual Dispense</Text>
        <View style={styles.dispenseButtons}>
          {[0.3, 0.5, 1.0].map((amount) => (
            <TouchableOpacity
              key={amount}
              style={styles.dispenseBtn}
              onPress={() => handleDispense(amount)}
            >
              <Text style={styles.dispenseBtnText}>{amount} kg</Text>
            </TouchableOpacity>
          ))}
        </View>
        <View style={styles.customDispense}>
          <TextInput
            style={styles.customInput}
            value={customAmount}
            onChangeText={setCustomAmount}
            keyboardType="numeric"
            placeholder="0.5"
          />
          <TouchableOpacity
            style={styles.customBtn}
            onPress={() => handleDispense(parseFloat(customAmount) || 0.5)}
          >
            <Text style={styles.customBtnText}>Dispense</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Schedules */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📅 Feeding Schedule</Text>
        {data?.schedules?.map((schedule: any, index: number) => (
          <View key={index} style={styles.scheduleItem}>
            <View style={styles.scheduleLeft}>
              <Text style={styles.scheduleTime}>
                {new Date(`2000-01-01T${schedule.time}:00`).toLocaleTimeString(
                  "en-US",
                  { hour: "numeric", minute: "2-digit" },
                )}
              </Text>
              <Text style={styles.scheduleAmount}>{schedule.amount} kg</Text>
            </View>
            <View style={styles.scheduleRight}>
              <Text style={styles.scheduleLabel}>{schedule.label}</Text>
              <View
                style={[
                  styles.scheduleStatus,
                  schedule.enabled
                    ? styles.statusActive
                    : styles.statusInactive,
                ]}
              >
                <Text style={styles.scheduleStatusText}>
                  {schedule.enabled ? "Active" : "Off"}
                </Text>
              </View>
            </View>
          </View>
        ))}
      </View>

      {/* Recent Logs */}
      <View style={[styles.section, styles.lastSection]}>
        <Text style={styles.sectionTitle}>📋 Recent Activity</Text>
        {data?.logs?.slice(0, 5).map((log: any, index: number) => (
          <View key={index} style={styles.logItem}>
            <Text style={styles.logTime}>
              {new Date(log.timestamp).toLocaleTimeString()}
            </Text>
            <Text style={styles.logAction}>
              {log.source === "refill" ? "➕ Refilled" : "🍽️ Dispensed"}{" "}
              {log.amount} kg
            </Text>
            <Text style={styles.logRemaining}>{log.remaining} kg left</Text>
          </View>
        ))}
      </View>

      {/* Refill Modal */}
      <Modal
        visible={refillModalVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setRefillModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Refill Feed</Text>
            <Text style={styles.modalSubtitle}>Enter amount to add (kg):</Text>
            <TextInput
              style={styles.modalInput}
              value={refillAmount}
              onChangeText={setRefillAmount}
              keyboardType="numeric"
              placeholder="Enter amount"
              autoFocus
            />
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalBtn, styles.modalCancelBtn]}
                onPress={() => {
                  setRefillModalVisible(false);
                  setRefillAmount("");
                }}
              >
                <Text style={styles.modalCancelText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalBtn, styles.modalConfirmBtn]}
                onPress={handleRefill}
              >
                <Text style={styles.modalConfirmText}>Refill</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
  levelCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 16,
    padding: 20,
    margin: 16,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  levelHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  levelTitle: {
    fontSize: 18,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  levelStatus: {
    fontSize: 14,
    fontWeight: "700",
    color: "#27AE60",
  },
  levelStatusLow: {
    color: "#E74C3C",
  },
  progressBar: {
    height: 12,
    backgroundColor: "#F0E8D8",
    borderRadius: 6,
    overflow: "hidden",
  },
  progressFill: {
    height: "100%",
    borderRadius: 6,
  },
  levelDetails: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 8,
  },
  levelText: {
    fontSize: 14,
    color: "#8B7355",
  },
  levelPercent: {
    fontSize: 14,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  refillBtn: {
    backgroundColor: "#27AE60",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    marginTop: 12,
  },
  refillBtnText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#FFFFFF",
  },
  autoCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  autoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  autoLabel: {
    fontSize: 16,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  autoDesc: {
    fontSize: 12,
    color: "#8B7355",
    marginTop: 2,
  },
  section: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#5C4A1E",
    marginBottom: 12,
  },
  dispenseButtons: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 10,
  },
  dispenseBtn: {
    flex: 1,
    backgroundColor: "#FFD62E",
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginHorizontal: 4,
  },
  dispenseBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  customDispense: {
    flexDirection: "row",
    gap: 10,
  },
  customInput: {
    flex: 1,
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.3)",
    fontSize: 16,
  },
  customBtn: {
    backgroundColor: "#FFD62E",
    borderRadius: 12,
    paddingHorizontal: 20,
    justifyContent: "center",
  },
  customBtnText: {
    fontSize: 14,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  scheduleItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  scheduleLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 16,
  },
  scheduleTime: {
    fontSize: 16,
    fontWeight: "700",
    color: "#3E2C1C",
    width: 70,
  },
  scheduleAmount: {
    fontSize: 14,
    color: "#5C4A1E",
  },
  scheduleRight: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  scheduleLabel: {
    fontSize: 12,
    color: "#8B7355",
  },
  scheduleStatus: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusActive: {
    backgroundColor: "#E8F5E9",
  },
  statusInactive: {
    backgroundColor: "#FDEDEC",
  },
  scheduleStatusText: {
    fontSize: 11,
    fontWeight: "600",
  },
  lastSection: {
    paddingBottom: 20,
  },
  logItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 10,
    padding: 12,
    marginBottom: 6,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.05)",
  },
  logTime: {
    fontSize: 12,
    color: "#8B7355",
    width: 70,
  },
  logAction: {
    flex: 1,
    fontSize: 13,
    color: "#3E2C1C",
  },
  logRemaining: {
    fontSize: 12,
    color: "#8B7355",
  },
  // Modal Styles
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "center",
    alignItems: "center",
  },
  modalContent: {
    backgroundColor: "#FFFFFF",
    borderRadius: 20,
    padding: 24,
    width: "85%",
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: "700",
    color: "#3E2C1C",
    marginBottom: 4,
  },
  modalSubtitle: {
    fontSize: 14,
    color: "#8B7355",
    marginBottom: 16,
  },
  modalInput: {
    backgroundColor: "#FFFCF2",
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.3)",
    fontSize: 16,
    marginBottom: 16,
  },
  modalButtons: {
    flexDirection: "row",
    gap: 12,
  },
  modalBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: "center",
  },
  modalCancelBtn: {
    backgroundColor: "#F0E8D8",
  },
  modalCancelText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#8B7355",
  },
  modalConfirmBtn: {
    backgroundColor: "#FFD62E",
  },
  modalConfirmText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#3E2C1C",
  },
});

export default FeedDispenserScreen;
