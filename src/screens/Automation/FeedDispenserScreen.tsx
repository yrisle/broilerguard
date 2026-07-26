// src/screens/Automation/FeedDispenserScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
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
import { useTheme } from "../../hooks/useTheme";

const FeedDispenserScreen = () => {
  const { colors } = useTheme();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [autoMode, setAutoMode] = useState(true);
  const [customAmount, setCustomAmount] = useState("0.5");

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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  const inventory = data?.inventory || { current_level: 0, capacity: 50 };
  const percentage = (inventory.current_level / inventory.capacity) * 100;
  const isLow = percentage < 20;

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
            name="utensils"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Feed Dispenser
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Automated feeding system
        </Text>
      </View>

      <View style={[styles.levelCard, { backgroundColor: colors.card }]}>
        <View style={styles.levelHeader}>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <FontAwesome5
              name="box"
              size={20}
              color={colors.text}
              style={{ marginRight: 8 }}
            />
            <Text style={[styles.levelTitle, { color: colors.text }]}>
              Feed Level
            </Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            {isLow ? (
              <Ionicons name="warning" size={18} color={colors.danger} />
            ) : (
              <Ionicons
                name="checkmark-circle"
                size={18}
                color={colors.success}
              />
            )}
            <Text
              style={[
                styles.levelStatus,
                isLow
                  ? [styles.levelStatusLow, { color: colors.danger }]
                  : { color: colors.success },
              ]}
            >
              {isLow ? " LOW" : " OK"}
            </Text>
          </View>
        </View>
        <View style={styles.progressBar}>
          <View
            style={[
              styles.progressFill,
              {
                width: `${Math.min(percentage, 100)}%`,
                backgroundColor: isLow ? colors.danger : colors.primary,
              },
            ]}
          />
        </View>
        <View style={styles.levelDetails}>
          <Text style={[styles.levelText, { color: colors.textMuted }]}>
            {inventory.current_level.toFixed(1)} kg / {inventory.capacity} kg
          </Text>
          <Text style={[styles.levelPercent, { color: colors.text }]}>
            {percentage.toFixed(0)}%
          </Text>
        </View>
        <TouchableOpacity
          style={[styles.refillBtn, { backgroundColor: colors.success }]}
          onPress={() => setRefillModalVisible(true)}
        >
          <Ionicons name="add-outline" size={20} color="#FFFFFF" />
          <Text style={styles.refillBtnText}> Refill Feed</Text>
        </TouchableOpacity>
      </View>

      <View
        style={[
          styles.autoCard,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <View style={styles.autoRow}>
          <View>
            <View style={{ flexDirection: "row", alignItems: "center" }}>
              <Ionicons
                name="rocket-outline"
                size={20}
                color={colors.text}
                style={{ marginRight: 8 }}
              />
              <Text style={[styles.autoLabel, { color: colors.text }]}>
                Auto Mode
              </Text>
            </View>
            <Text style={[styles.autoDesc, { color: colors.textMuted }]}>
              {autoMode ? "Feed dispensed automatically" : "Manual mode only"}
            </Text>
          </View>
          <Switch
            value={autoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: colors.primary }}
          />
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
            name="hand-left-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Manual Dispense
          </Text>
        </View>
        <View style={styles.dispenseButtons}>
          {[0.3, 0.5, 1.0].map((amount) => (
            <TouchableOpacity
              key={amount}
              style={[styles.dispenseBtn, { backgroundColor: colors.primary }]}
              onPress={() => handleDispense(amount)}
            >
              <Text style={[styles.dispenseBtnText, { color: "#FFFFFF" }]}>
                {amount} kg
              </Text>
            </TouchableOpacity>
          ))}
        </View>
        <View style={styles.customDispense}>
          <TextInput
            style={[
              styles.customInput,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                color: colors.text,
              },
            ]}
            value={customAmount}
            onChangeText={setCustomAmount}
            keyboardType="numeric"
            placeholder="0.5"
            placeholderTextColor={colors.textMuted}
          />
          <TouchableOpacity
            style={[styles.customBtn, { backgroundColor: colors.primary }]}
            onPress={() => handleDispense(parseFloat(customAmount) || 0.5)}
          >
            <Text style={[styles.customBtnText, { color: "#FFFFFF" }]}>
              Dispense
            </Text>
          </TouchableOpacity>
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
            name="calendar"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Feeding Schedule
          </Text>
        </View>
        {data?.schedules?.map((schedule: any, index: number) => (
          <View
            key={index}
            style={[
              styles.scheduleItem,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <View style={styles.scheduleLeft}>
              <Text style={[styles.scheduleTime, { color: colors.text }]}>
                {new Date(`2000-01-01T${schedule.time}:00`).toLocaleTimeString(
                  "en-US",
                  {
                    hour: "numeric",
                    minute: "2-digit",
                  },
                )}
              </Text>
              <Text
                style={[styles.scheduleAmount, { color: colors.textSecondary }]}
              >
                {schedule.amount} kg
              </Text>
            </View>
            <View style={styles.scheduleRight}>
              <Text style={[styles.scheduleLabel, { color: colors.textMuted }]}>
                {schedule.label}
              </Text>
              <View
                style={[
                  styles.scheduleStatus,
                  schedule.enabled
                    ? [
                        styles.statusActive,
                        { backgroundColor: colors.successLight },
                      ]
                    : [
                        styles.statusInactive,
                        { backgroundColor: colors.dangerLight },
                      ],
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

      <View style={[styles.section, styles.lastSection]}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="time-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Recent Activity
          </Text>
        </View>
        {data?.logs?.slice(0, 5).map((log: any, index: number) => (
          <View
            key={index}
            style={[
              styles.logItem,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <Text style={[styles.logTime, { color: colors.textMuted }]}>
              {new Date(log.timestamp).toLocaleTimeString()}
            </Text>
            <View
              style={{ flexDirection: "row", alignItems: "center", flex: 1 }}
            >
              {log.source === "refill" ? (
                <Ionicons
                  name="add-circle-outline"
                  size={14}
                  color={colors.success}
                  style={{ marginRight: 6 }}
                />
              ) : (
                <FontAwesome5
                  name="utensils"
                  size={12}
                  color={colors.primary}
                  style={{ marginRight: 6 }}
                />
              )}
              <Text style={[styles.logAction, { color: colors.text }]}>
                {log.source === "refill" ? "Refilled" : "Dispensed"}{" "}
                {log.amount} kg
              </Text>
            </View>
            <Text style={[styles.logRemaining, { color: colors.textMuted }]}>
              {log.remaining} kg left
            </Text>
          </View>
        ))}
      </View>

      <Modal
        visible={refillModalVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setRefillModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: colors.card }]}>
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                marginBottom: 8,
              }}
            >
              <FontAwesome5
                name="box"
                size={24}
                color={colors.text}
                style={{ marginRight: 12 }}
              />
              <Text style={[styles.modalTitle, { color: colors.text }]}>
                Refill Feed
              </Text>
            </View>
            <Text style={[styles.modalSubtitle, { color: colors.textMuted }]}>
              Enter amount to add (kg):
            </Text>
            <TextInput
              style={[
                styles.modalInput,
                {
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  color: colors.text,
                },
              ]}
              value={refillAmount}
              onChangeText={setRefillAmount}
              keyboardType="numeric"
              placeholder="Enter amount"
              placeholderTextColor={colors.textMuted}
              autoFocus
            />
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[
                  styles.modalBtn,
                  styles.modalCancelBtn,
                  { backgroundColor: colors.background },
                ]}
                onPress={() => {
                  setRefillModalVisible(false);
                  setRefillAmount("");
                }}
              >
                <Text
                  style={[styles.modalCancelText, { color: colors.textMuted }]}
                >
                  Cancel
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[
                  styles.modalBtn,
                  styles.modalConfirmBtn,
                  { backgroundColor: colors.primary },
                ]}
                onPress={handleRefill}
              >
                <Text style={[styles.modalConfirmText, { color: "#FFFFFF" }]}>
                  Refill
                </Text>
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
  levelCard: {
    borderRadius: 16,
    padding: 20,
    margin: 16,
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
  },
  levelStatus: {
    fontSize: 14,
    fontWeight: "700",
  },
  levelStatusLow: {},
  progressBar: {
    height: 12,
    borderRadius: 6,
    overflow: "hidden",
    backgroundColor: "#F0E8D8",
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
  },
  levelPercent: {
    fontSize: 14,
    fontWeight: "700",
  },
  refillBtn: {
    flexDirection: "row",
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 12,
  },
  refillBtnText: {
    fontSize: 14,
    fontWeight: "600",
    color: "#FFFFFF",
  },
  autoCard: {
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 16,
    borderWidth: 1,
  },
  autoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  autoLabel: {
    fontSize: 16,
    fontWeight: "600",
  },
  autoDesc: {
    fontSize: 12,
    marginTop: 2,
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
  dispenseButtons: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 10,
  },
  dispenseBtn: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginHorizontal: 4,
  },
  dispenseBtnText: {
    fontSize: 16,
    fontWeight: "700",
  },
  customDispense: {
    flexDirection: "row",
    gap: 10,
  },
  customInput: {
    flex: 1,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
    fontSize: 16,
  },
  customBtn: {
    borderRadius: 12,
    paddingHorizontal: 20,
    justifyContent: "center",
  },
  customBtnText: {
    fontSize: 14,
    fontWeight: "700",
  },
  scheduleItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
  },
  scheduleLeft: {
    flexDirection: "row",
    alignItems: "center",
    gap: 16,
  },
  scheduleTime: {
    fontSize: 16,
    fontWeight: "700",
    width: 70,
  },
  scheduleAmount: {
    fontSize: 14,
  },
  scheduleRight: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  scheduleLabel: {
    fontSize: 12,
  },
  scheduleStatus: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusActive: {},
  statusInactive: {},
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
    borderRadius: 10,
    padding: 12,
    marginBottom: 6,
    borderWidth: 1,
  },
  logTime: {
    fontSize: 12,
    width: 70,
  },
  logAction: {
    flex: 1,
    fontSize: 13,
  },
  logRemaining: {
    fontSize: 12,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "center",
    alignItems: "center",
  },
  modalContent: {
    borderRadius: 20,
    padding: 24,
    width: "85%",
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: "700",
    marginBottom: 0,
  },
  modalSubtitle: {
    fontSize: 14,
    marginBottom: 16,
    marginLeft: 36,
  },
  modalInput: {
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderWidth: 1,
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
  modalCancelBtn: {},
  modalCancelText: {
    fontSize: 16,
    fontWeight: "600",
  },
  modalConfirmBtn: {},
  modalConfirmText: {
    fontSize: 16,
    fontWeight: "600",
  },
});

export default FeedDispenserScreen;
