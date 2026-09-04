// src/screens/Automation/WaterPumpScreen.tsx

import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { automation } from "../../api/endpoints";
import { useAutomation } from "../../context/AutomationContext";
import { useTheme } from "../../hooks/useTheme";
import { automationScheduler } from "../../services/AutomationScheduler";

const PUMP_DISPENSE_AMOUNT_KEY = "@pump_dispense_amount";

function WaterPumpScreen() {
  const { colors } = useTheme();
  const { pumpAutoMode, setPumpAutoMode } = useAutomation();
  const [pumpStatus, setPumpStatus] = useState("OFF");
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [customDuration, setCustomDuration] = useState("30");
  const [waterLevel, setWaterLevel] = useState(60);
  const [capacity, setCapacity] = useState(2000);
  const [dispenseAmount, setDispenseAmount] = useState("10");
  const [isAutoDispensing, setIsAutoDispensing] = useState(false);
  const [currentTime, setCurrentTime] = useState("");

  const timeIntervalRef = useRef<number | null>(null);

  // ============================================
  // PHILIPPINE TIME
  // ============================================
  const getPhilippineTime = (): Date => {
    const now = new Date();
    const phTime = new Date(now.getTime() + 8 * 60 * 60 * 1000);
    return phTime;
  };

  const updateCurrentTime = () => {
    const now = getPhilippineTime();
    setCurrentTime(
      now.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: true,
      }),
    );
  };

  // ============================================
  // SAVE & LOAD DISPENSE AMOUNT
  // ============================================
  const saveDispenseAmount = async () => {
    try {
      await AsyncStorage.setItem(PUMP_DISPENSE_AMOUNT_KEY, dispenseAmount);
      console.log("✅ Pump dispense amount saved:", dispenseAmount);
    } catch (error) {
      console.log("Error saving pump dispense amount:", error);
    }
  };

  const loadDispenseAmount = async () => {
    try {
      const amount = await AsyncStorage.getItem(PUMP_DISPENSE_AMOUNT_KEY);
      if (amount) {
        setDispenseAmount(amount);
        console.log("✅ Pump dispense amount loaded:", amount);
      }
    } catch (error) {
      console.log("Error loading pump dispense amount:", error);
    }
  };

  // ============================================
  // FETCH DATA
  // ============================================
  const fetchData = async () => {
    try {
      console.log("📥 Fetching pump data from ESP32...");
      const response = await automation.pump.getStatus();
      const data = response.data;

      console.log("📥 ESP32 Data:", data);

      const pumpStatusValue = data.pump === 1 ? "ON" : "OFF";
      setPumpStatus(pumpStatusValue);
      setWaterLevel(data.water_level || 60);

      console.log("📥 Pump status:", pumpStatusValue);
    } catch (error: any) {
      console.error("❌ Error fetching pump data:", error);
      Alert.alert(
        "Error",
        "Failed to load pump data. Please check connection to ESP32.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  // ============================================
  // INIT - Load settings on mount
  // ============================================
  useEffect(() => {
    const init = async () => {
      await loadDispenseAmount();
      await fetchData();
      updateCurrentTime();

      timeIntervalRef.current = setInterval(updateCurrentTime, 1000);
    };

    init();

    return () => {
      if (timeIntervalRef.current) clearInterval(timeIntervalRef.current);
    };
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // ============================================
  // TOGGLE PUMP
  // ============================================
  const togglePump = async () => {
    const newStatus = pumpStatus === "ON" ? "OFF" : "ON";
    try {
      console.log("🔄 Toggling pump to:", newStatus);
      await automation.pump.toggle(newStatus);
      setPumpStatus(newStatus);
      fetchData();
    } catch (error: any) {
      console.error("❌ Toggle error:", error);
      Alert.alert("Error", "Failed to toggle pump. Please try again.");
    }
  };

  // ============================================
  // MANUAL WATER RELEASE
  // ============================================
  const handleWaterRelease = async (duration: number) => {
    try {
      console.log("🔄 Releasing water for:", duration, "seconds");
      await automation.pump.release(duration);
      const amount = (duration * 0.5).toFixed(1);
      Alert.alert("Success", `Released ${amount} L of water`);
      fetchData();
    } catch (error: any) {
      console.error("❌ Water release error:", error);
      Alert.alert("Error", "Failed to release water. Please try again.");
    }
  };

  // ============================================
  // TOGGLE AUTO MODE
  // ============================================
  const toggleAutoMode = async () => {
    const newMode = !pumpAutoMode;
    await setPumpAutoMode(newMode);

    if (newMode) {
      Alert.alert(
        "🤖 Auto Mode Enabled",
        `Water will be dispensed automatically.\n\nAmount per dispense: ${dispenseAmount} L every 30 minutes`,
        [{ text: "OK" }],
      );
      await automationScheduler.startPumpScheduler();
    } else {
      automationScheduler.stopPumpScheduler();
      Alert.alert("Auto Mode Disabled", "Manual control restored.", [
        { text: "OK" },
      ]);
    }
  };

  const percentage = (waterLevel / capacity) * 100;

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
            name="water"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>Water Pump</Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Automated watering system
        </Text>
      </View>

      {/* Current Time Display */}
      <View style={[styles.timeDisplay, { backgroundColor: colors.card }]}>
        <Ionicons name="time-outline" size={20} color={colors.primary} />
        <Text style={[styles.timeDisplayText, { color: colors.text }]}>
          Philippine Time: {currentTime}
        </Text>
      </View>

      {/* Auto Mode Status */}
      <View
        style={[
          styles.autoStatusCard,
          {
            backgroundColor: pumpAutoMode ? colors.successLight : colors.card,
            borderColor: pumpAutoMode ? colors.success : colors.border,
          },
        ]}
      >
        <Ionicons
          name={pumpAutoMode ? "checkmark-circle" : "time-outline"}
          size={20}
          color={pumpAutoMode ? colors.success : colors.textMuted}
        />
        <Text
          style={[
            styles.autoStatusText,
            { color: pumpAutoMode ? colors.success : colors.textMuted },
          ]}
        >
          {pumpAutoMode ? "Auto Mode is ACTIVE" : "Auto Mode is OFF"}
        </Text>
        {pumpAutoMode && (
          <Text style={[styles.autoStatusSubtext, { color: colors.textMuted }]}>
            Dispensing {dispenseAmount} L every 30 minutes
          </Text>
        )}
      </View>

      {/* Pump Status Card */}
      <View style={[styles.statusCard, { backgroundColor: colors.card }]}>
        <View
          style={[
            styles.pumpIconContainer,
            {
              backgroundColor: colors.backgroundSecondary || colors.card + "80",
            },
          ]}
        >
          <FontAwesome5
            name="water"
            size={40}
            color={pumpStatus === "ON" ? colors.info : colors.textMuted}
          />
        </View>
        <Text style={[styles.pumpStatusLabel, { color: colors.textMuted }]}>
          Water Pump is
        </Text>
        <Text
          style={[
            styles.pumpStatusText,
            pumpStatus === "ON"
              ? [styles.statusOn, { color: colors.success }]
              : [styles.statusOff, { color: colors.danger }],
          ]}
        >
          {pumpStatus === "ON" ? "RUNNING" : "STOPPED"}
        </Text>

        <TouchableOpacity
          style={[
            styles.toggleBtn,
            pumpStatus === "ON"
              ? [styles.toggleOn, { backgroundColor: colors.danger }]
              : [styles.toggleOff, { backgroundColor: colors.success }],
          ]}
          onPress={togglePump}
        >
          <Text style={styles.toggleBtnText}>
            {pumpStatus === "ON" ? "Stop Pump" : "Start Pump"}
          </Text>
        </TouchableOpacity>
      </View>

      {/* Water Level */}
      <View style={[styles.levelCard, { backgroundColor: colors.card }]}>
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
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.levelTitle, { color: colors.textSecondary }]}>
            Water Level
          </Text>
        </View>
        <View style={styles.tankContainer}>
          <View style={[styles.tank, { borderColor: colors.textMuted }]}>
            <View
              style={[
                styles.tankFill,
                {
                  height: `${Math.min(percentage, 100)}%`,
                  backgroundColor: colors.info,
                },
              ]}
            />
            <Text style={styles.tankLabel}>{percentage.toFixed(0)}%</Text>
          </View>
        </View>
        <Text style={[styles.levelText, { color: colors.textMuted }]}>
          {waterLevel.toFixed(0)} L / {capacity} L
        </Text>
      </View>

      {/* Auto Mode Section */}
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
              {pumpAutoMode
                ? "✅ Auto dispensing is ON"
                : "⏸️ Auto dispensing is OFF"}
            </Text>
          </View>
          <Switch
            value={pumpAutoMode}
            onValueChange={toggleAutoMode}
            trackColor={{ false: "#E0D5C0", true: colors.primary }}
            thumbColor={pumpAutoMode ? "#FFFFFF" : "#f4f3f4"}
          />
        </View>

        <View style={[styles.settingRow, { borderColor: colors.border }]}>
          <Text style={[styles.settingLabel, { color: colors.text }]}>
            Dispense Amount
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <TextInput
              style={[
                styles.amountInput,
                {
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  color: colors.text,
                  width: 70,
                },
              ]}
              value={dispenseAmount}
              onChangeText={(text) => {
                setDispenseAmount(text);
                saveDispenseAmount();
              }}
              keyboardType="numeric"
              editable={true}
            />
            <Text style={[styles.settingValue, { color: colors.textMuted }]}>
              L
            </Text>
          </View>
        </View>

        <Text style={[styles.autoNote, { color: colors.textMuted }]}>
          ⏰ Auto dispense every 30 minutes
        </Text>
      </View>

      {/* Manual Release */}
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
            Manual Release
          </Text>
        </View>
        <View style={styles.releaseButtons}>
          {[15, 30, 60].map((seconds) => {
            const amount = (seconds * 0.5).toFixed(1);
            return (
              <TouchableOpacity
                key={seconds}
                style={[
                  styles.releaseBtn,
                  {
                    backgroundColor: colors.info,
                  },
                ]}
                onPress={() => handleWaterRelease(seconds)}
              >
                <Text style={styles.releaseBtnText}>{seconds}s</Text>
                <Text style={styles.releaseSubtext}>{amount} L</Text>
              </TouchableOpacity>
            );
          })}
        </View>
        <View style={styles.customRelease}>
          <TextInput
            style={[
              styles.customInput,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                color: colors.text,
              },
            ]}
            value={customDuration}
            onChangeText={setCustomDuration}
            keyboardType="numeric"
            placeholder="30"
            placeholderTextColor={colors.textMuted}
          />
          <TouchableOpacity
            style={[
              styles.customBtn,
              {
                backgroundColor: colors.info,
              },
            ]}
            onPress={() => handleWaterRelease(parseInt(customDuration) || 30)}
          >
            <Text style={styles.customBtnText}>Release</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Recent Activity */}
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

        <View
          style={[
            styles.logItem,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Text style={[styles.logTime, { color: colors.textMuted }]}>
            {new Date().toLocaleTimeString()}
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center", flex: 1 }}>
            <FontAwesome5
              name="water"
              size={12}
              color={pumpStatus === "ON" ? colors.info : colors.danger}
              style={{ marginRight: 6 }}
            />
            <Text style={[styles.logAction, { color: colors.text }]}>
              {pumpAutoMode
                ? "Auto Mode"
                : pumpStatus === "ON"
                  ? "Pump ON"
                  : "Pump OFF"}
            </Text>
          </View>
          <Text
            style={[
              styles.logTrigger,
              {
                color: colors.textMuted,
                backgroundColor: colors.backgroundSecondary,
              },
            ]}
          >
            {pumpAutoMode ? "Auto" : "Manual"}
          </Text>
        </View>
      </View>
    </ScrollView>
  );
}

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
  timeDisplay: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    marginHorizontal: 16,
    marginTop: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 8,
    gap: 8,
  },
  timeDisplayText: {
    fontSize: 14,
    fontWeight: "600",
  },
  autoStatusCard: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    flexWrap: "wrap",
    marginHorizontal: 16,
    marginTop: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    borderWidth: 1,
    gap: 8,
  },
  autoStatusText: {
    fontSize: 14,
    fontWeight: "700",
  },
  autoStatusSubtext: {
    fontSize: 12,
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
  pumpIconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 12,
  },
  pumpStatusLabel: {
    fontSize: 14,
  },
  pumpStatusText: {
    fontSize: 28,
    fontWeight: "800",
    marginVertical: 4,
  },
  statusOn: {},
  statusOff: {},
  toggleBtn: {
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 30,
    marginTop: 12,
  },
  toggleOn: {},
  toggleOff: {},
  toggleBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  levelCard: {
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 16,
    marginBottom: 16,
    alignItems: "center",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  levelTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 0,
  },
  tankContainer: {
    width: 120,
    height: 160,
    justifyContent: "center",
    alignItems: "center",
  },
  tank: {
    width: 100,
    height: 140,
    borderWidth: 4,
    borderRadius: 10,
    overflow: "hidden",
    position: "relative",
    backgroundColor: "#F0E8D8",
  },
  tankFill: {
    position: "absolute",
    bottom: 0,
    width: "100%",
  },
  tankLabel: {
    position: "absolute",
    top: "50%",
    left: "50%",
    transform: [{ translateX: -18 }, { translateY: -10 }],
    fontSize: 18,
    fontWeight: "800",
    color: "#FFFFFF",
    textShadowColor: "rgba(0,0,0,0.5)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 2,
  },
  levelText: {
    marginTop: 8,
    fontSize: 14,
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
  settingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 10,
    borderBottomWidth: 1,
  },
  settingLabel: {
    fontSize: 14,
  },
  settingValue: {
    fontSize: 14,
    marginLeft: 6,
  },
  amountInput: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderWidth: 1,
    borderRadius: 6,
    textAlign: "center",
    fontSize: 14,
  },
  autoNote: {
    fontSize: 12,
    marginTop: 8,
    textAlign: "center",
    fontStyle: "italic",
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
  releaseButtons: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 10,
  },
  releaseBtn: {
    flex: 1,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    marginHorizontal: 4,
  },
  releaseBtnText: {
    fontSize: 18,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  releaseSubtext: {
    fontSize: 11,
    color: "rgba(255,255,255,0.8)",
    marginTop: 2,
  },
  customRelease: {
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
    color: "#FFFFFF",
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
  logTrigger: {
    fontSize: 11,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
});

export default WaterPumpScreen;
