// src/screens/Automation/GateControlScreen.tsx

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
import { useTheme } from "../../hooks/useTheme";

const GATE_AUTO_MODE_KEY = "@gate_auto_mode";
const GATE_FEED_AMOUNT_KEY = "@gate_feed_amount";
const GATE_FEED_TIMES_KEY = "@gate_feed_times";

const GateControlScreen = () => {
  const { colors } = useTheme();
  const [gateStatus, setGateStatus] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [isToggling, setIsToggling] = useState(false);

  // Feed Settings
  const [autoMode, setAutoMode] = useState(false);
  const [feedAmount, setFeedAmount] = useState("0.5");
  const [feedTimes, setFeedTimes] = useState<string[]>([
    "8:00 AM",
    "12:00 PM",
    "4:00 PM",
    "8:00 PM",
  ]);
  const [newTime, setNewTime] = useState("");

  // Tracking
  const [feedDispensed, setFeedDispensed] = useState(0);
  const [dispenseCount, setDispenseCount] = useState(0);
  const [isAutoDispensing, setIsAutoDispensing] = useState(false);
  const [nextDispenseTime, setNextDispenseTime] = useState<Date | null>(null);
  const [currentTime, setCurrentTime] = useState("");

  const intervalRef = useRef<number | null>(null);
  const timeIntervalRef = useRef<number | null>(null);

  // ============================================
  // PHILIPPINE TIME (UTC+8)
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
  // TIME CONVERSION FUNCTIONS
  // ============================================
  const convertTo24Hour = (timeStr: string): string => {
    const parts = timeStr.split(" ");
    if (parts.length !== 2) return timeStr;

    const time = parts[0];
    const period = parts[1];
    let [hours, minutes] = time.split(":").map(Number);

    if (period === "PM" && hours !== 12) {
      hours += 12;
    } else if (period === "AM" && hours === 12) {
      hours = 0;
    }

    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}`;
  };

  const formatTimeDisplay = (
    hours: number,
    minutes: number,
    period: string,
  ): string => {
    const hour12 = hours % 12 || 12;
    return `${hour12}:${minutes.toString().padStart(2, "0")} ${period}`;
  };

  const parseTimeInput = (
    input: string,
  ): { hours: number; minutes: number; period: string } | null => {
    const patterns = [
      /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i,
      /^(\d{1,2}):(\d{2})(AM|PM)$/i,
      /^(\d{1,2})\s*(AM|PM)$/i,
      /^(\d{1,2})(AM|PM)$/i,
    ];

    for (const pattern of patterns) {
      const match = input.match(pattern);
      if (match) {
        if (match.length === 4) {
          return {
            hours: parseInt(match[1]),
            minutes: parseInt(match[2]),
            period: match[3].toUpperCase(),
          };
        } else if (match.length === 3) {
          return {
            hours: parseInt(match[1]),
            minutes: 0,
            period: match[2].toUpperCase(),
          };
        }
      }
    }
    return null;
  };

  // ============================================
  // SAVE & LOAD AUTO MODE STATE
  // ============================================
  const saveAutoModeState = async () => {
    try {
      await AsyncStorage.setItem(GATE_AUTO_MODE_KEY, JSON.stringify(autoMode));
      await AsyncStorage.setItem(GATE_FEED_AMOUNT_KEY, feedAmount);
      await AsyncStorage.setItem(
        GATE_FEED_TIMES_KEY,
        JSON.stringify(feedTimes),
      );
      console.log("✅ Gate auto mode saved:", autoMode);
    } catch (error) {
      console.log("Error saving gate auto mode:", error);
    }
  };

  const loadAutoModeState = async () => {
    try {
      const autoMode = await AsyncStorage.getItem(GATE_AUTO_MODE_KEY);
      const amount = await AsyncStorage.getItem(GATE_FEED_AMOUNT_KEY);
      const times = await AsyncStorage.getItem(GATE_FEED_TIMES_KEY);

      if (autoMode !== null) {
        const isAuto = JSON.parse(autoMode);
        setAutoMode(isAuto);
        if (amount) setFeedAmount(amount);
        if (times) setFeedTimes(JSON.parse(times));
        console.log("✅ Gate auto mode loaded:", isAuto);
        return isAuto;
      }
      return false;
    } catch (error) {
      console.log("Error loading gate auto mode:", error);
      return false;
    }
  };

  const fetchData = async () => {
    try {
      console.log("📥 Fetching gate data from ESP32...");
      const response = await automation.gate.getStatus();
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

  // ============================================
  // INIT - Load auto mode state on mount
  // ============================================
  useEffect(() => {
    const init = async () => {
      const isAuto = await loadAutoModeState();
      await fetchData();
      updateCurrentTime();

      timeIntervalRef.current = setInterval(updateCurrentTime, 1000);

      if (isAuto) {
        console.log("🔄 Restarting gate auto scheduler...");
        findNextDispenseTime();
        startScheduler();
      }
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
  // MANUAL GATE CONTROL
  // ============================================
  const handleOpenGate = async () => {
    if (isToggling || autoMode) return;
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
    if (isToggling || autoMode) return;
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
    if (isToggling || autoMode) return;
    setIsToggling(true);

    try {
      console.log("🔄 Toggling gate...");
      await automation.gate.toggle();
      await fetchData();
    } catch (error: any) {
      console.error("❌ Toggle gate error:", error);
      Alert.alert("Error", "Failed to toggle gate. Please try again.");
    } finally {
      setIsToggling(false);
    }
  };

  // ============================================
  // TIME MANAGEMENT
  // ============================================
  const addTime = () => {
    if (!newTime.trim()) {
      Alert.alert("Error", "Please enter a time (e.g., 8:00 AM)");
      return;
    }

    const parsed = parseTimeInput(newTime.trim());
    if (!parsed) {
      Alert.alert(
        "Error",
        "Invalid time format. Use: 8:00 AM, 8:00am, 8 AM, or 8am",
      );
      return;
    }

    const { hours, minutes, period } = parsed;
    if (hours < 1 || hours > 12) {
      Alert.alert("Error", "Hour must be between 1 and 12");
      return;
    }
    if (minutes < 0 || minutes > 59) {
      Alert.alert("Error", "Minutes must be between 0 and 59");
      return;
    }

    const formattedTime = formatTimeDisplay(hours, minutes, period);
    if (feedTimes.includes(formattedTime)) {
      Alert.alert("Error", "Time already exists");
      return;
    }

    const allTimes = [...feedTimes, formattedTime];
    allTimes.sort((a, b) => {
      const a24 = convertTo24Hour(a);
      const b24 = convertTo24Hour(b);
      return a24.localeCompare(b24);
    });

    setFeedTimes(allTimes);
    setNewTime("");
    saveAutoModeState();
  };

  const removeTime = (time: string) => {
    if (feedTimes.length <= 1) {
      Alert.alert("Error", "You need at least one scheduled time");
      return;
    }
    const newTimes = feedTimes.filter((t) => t !== time);
    setFeedTimes(newTimes);
    saveAutoModeState();
  };

  // ============================================
  // AUTO FEED DISPENSING
  // ============================================
  const handleDispenseFeed = async () => {
    if (isAutoDispensing) return;
    setIsAutoDispensing(true);

    try {
      const amount = parseFloat(feedAmount) || 0.5;
      console.log(`🔄 Dispensing ${amount} kg of feed...`);

      await automation.gate.open();
      setGateStatus(true);
      await new Promise((resolve) => setTimeout(resolve, 3000));
      await automation.gate.close();
      setGateStatus(false);

      const newTotal = feedDispensed + amount;
      setFeedDispensed(parseFloat(newTotal.toFixed(2)));
      setDispenseCount((prev) => prev + 1);

      console.log(
        `✅ Dispensed ${amount} kg. Total today: ${newTotal.toFixed(2)} kg`,
      );

      findNextDispenseTime();

      const target = 5.0;
      if (newTotal >= target) {
        Alert.alert(
          "✅ Daily Target Reached",
          `Daily feed target of ${target} kg has been reached!`,
          [{ text: "OK" }],
        );
      } else {
        Alert.alert(
          "Success",
          `Dispensed ${amount} kg of feed. (${newTotal.toFixed(2)} kg total today)`,
          [{ text: "OK" }],
        );
      }

      await fetchData();
    } catch (error: any) {
      console.error("❌ Dispense error:", error);
      Alert.alert("Error", "Failed to dispense feed. Please try again.");
    } finally {
      setIsAutoDispensing(false);
    }
  };

  // ============================================
  // FIND NEXT DISPENSE TIME
  // ============================================
  const findNextDispenseTime = () => {
    if (feedTimes.length === 0) return;

    const now = getPhilippineTime();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();

    const timeMinutes = feedTimes.map((time) => {
      const time24 = convertTo24Hour(time);
      const [hours, minutes] = time24.split(":").map(Number);
      return { time, minutes: hours * 60 + minutes };
    });

    timeMinutes.sort((a, b) => a.minutes - b.minutes);

    let nextTime: string | null = null;
    let nextDate = new Date(now);

    for (const tm of timeMinutes) {
      if (tm.minutes > currentMinutes) {
        nextTime = tm.time;
        const [hours, minutes] = convertTo24Hour(tm.time)
          .split(":")
          .map(Number);
        nextDate.setHours(hours, minutes, 0, 0);
        break;
      }
    }

    if (!nextTime && timeMinutes.length > 0) {
      nextTime = timeMinutes[0].time;
      const [hours, minutes] = convertTo24Hour(nextTime).split(":").map(Number);
      nextDate.setDate(nextDate.getDate() + 1);
      nextDate.setHours(hours, minutes, 0, 0);
      setNextDispenseTime(nextDate);
      console.log(`⏰ Next dispense tomorrow at ${nextTime}`);
      return;
    }

    if (nextTime) {
      setNextDispenseTime(nextDate);
      console.log(
        `⏰ Next dispense at ${nextDate.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", hour12: true })}`,
      );
    }
  };

  // ============================================
  // TOGGLE AUTO MODE
  // ============================================
  const toggleAutoMode = async () => {
    const newMode = !autoMode;
    setAutoMode(newMode);
    await saveAutoModeState();

    if (newMode) {
      setFeedDispensed(0);
      setDispenseCount(0);
      findNextDispenseTime();

      Alert.alert(
        "🤖 Auto Mode Enabled",
        `Feed will be dispensed automatically at:\n\n${feedTimes.map((t) => `🕐 ${t}`).join("\n")}\n\nAmount per dispense: ${feedAmount} kg\n\n🔒 Manual gate controls are now disabled.`,
        [{ text: "OK" }],
      );

      startScheduler();
    } else {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
      setNextDispenseTime(null);
      Alert.alert("Auto Mode Disabled", "Manual control restored.", [
        { text: "OK" },
      ]);
    }
  };

  // ============================================
  // SCHEDULER - Check every 10 seconds
  // ============================================
  const startScheduler = () => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }

    intervalRef.current = setInterval(() => {
      if (!autoMode || isAutoDispensing) return;

      const now = getPhilippineTime();
      const currentMinutes = now.getHours() * 60 + now.getMinutes();
      const currentSeconds = now.getSeconds();

      for (const time of feedTimes) {
        const time24 = convertTo24Hour(time);
        const [hours, minutes] = time24.split(":").map(Number);
        const timeMinutes = hours * 60 + minutes;

        const timeDiff = Math.abs(
          currentMinutes * 60 + currentSeconds - timeMinutes * 60,
        );
        if (timeDiff <= 10) {
          console.log(
            `⏰ [PH Time: ${currentTime}] SCHEDULED DISPENSE AT ${time} - TRIGGERING NOW!`,
          );
          handleDispenseFeed();
          break;
        }
      }

      findNextDispenseTime();
    }, 10000);
  };

  // Update scheduler when feed times change
  useEffect(() => {
    if (autoMode) {
      startScheduler();
      findNextDispenseTime();
    }
  }, [feedTimes]);

  // Reset daily counter at midnight
  useEffect(() => {
    const checkMidnight = () => {
      const now = getPhilippineTime();
      if (
        now.getHours() === 0 &&
        now.getMinutes() === 0 &&
        now.getSeconds() < 10
      ) {
        setFeedDispensed(0);
        setDispenseCount(0);
        setNextDispenseTime(null);
        console.log("🔄 Daily feed counter reset (Philippine Time)");
      }
    };

    const interval = setInterval(checkMidnight, 10000);
    return () => clearInterval(interval);
  }, []);

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

  const target = 5.0;
  const progress = Math.min((feedDispensed / target) * 100, 100);

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
            Gate & Feed Control
          </Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Control gate and automated feed dispensing
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
            backgroundColor: autoMode ? colors.successLight : colors.card,
            borderColor: autoMode ? colors.success : colors.border,
          },
        ]}
      >
        <Ionicons
          name={autoMode ? "checkmark-circle" : "time-outline"}
          size={20}
          color={autoMode ? colors.success : colors.textMuted}
        />
        <Text
          style={[
            styles.autoStatusText,
            { color: autoMode ? colors.success : colors.textMuted },
          ]}
        >
          {autoMode ? "Auto Mode is ACTIVE" : "Auto Mode is OFF"}
        </Text>
        {autoMode && nextDispenseTime && (
          <Text style={[styles.autoStatusSubtext, { color: colors.textMuted }]}>
            Next dispense at:{" "}
            {nextDispenseTime.toLocaleTimeString("en-US", {
              hour: "numeric",
              minute: "2-digit",
              hour12: true,
            })}
          </Text>
        )}
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

        <TouchableOpacity
          style={[
            styles.toggleBtn,
            gateStatus
              ? [styles.toggleClose, { backgroundColor: colors.danger }]
              : [styles.toggleOpen, { backgroundColor: colors.success }],
            autoMode && styles.disabledBtn,
          ]}
          onPress={handleToggleGate}
          disabled={isToggling || autoMode}
        >
          <Text style={styles.toggleBtnText}>
            {isToggling
              ? "Processing..."
              : autoMode
                ? "🔒 Auto Mode ON"
                : gateStatus
                  ? "Close Gate"
                  : "Open Gate"}
          </Text>
        </TouchableOpacity>

        {autoMode && (
          <View
            style={[
              styles.autoIndicator,
              { backgroundColor: colors.successLight },
            ]}
          >
            <Ionicons name="rocket-outline" size={16} color={colors.success} />
            <Text style={[styles.autoIndicatorText, { color: colors.success }]}>
              Auto Mode Active - Manual controls disabled
            </Text>
          </View>
        )}
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
          {autoMode && (
            <View
              style={[
                styles.lockBadge,
                { backgroundColor: colors.warningLight, marginLeft: 8 },
              ]}
            >
              <Ionicons name="lock-closed" size={12} color={colors.warning} />
              <Text style={[styles.lockBadgeText, { color: colors.warning }]}>
                Locked
              </Text>
            </View>
          )}
        </View>

        <View style={styles.actionRow}>
          <TouchableOpacity
            style={[
              styles.actionBtn,
              styles.actionOpen,
              {
                backgroundColor: colors.success,
                opacity: gateStatus || isToggling || autoMode ? 0.5 : 1,
              },
            ]}
            onPress={handleOpenGate}
            disabled={gateStatus || isToggling || autoMode}
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
                opacity: !gateStatus || isToggling || autoMode ? 0.5 : 1,
              },
            ]}
            onPress={handleCloseGate}
            disabled={!gateStatus || isToggling || autoMode}
          >
            <FontAwesome5 name="door-closed" size={24} color="#FFFFFF" />
            <Text style={styles.actionBtnText}>Close</Text>
          </TouchableOpacity>
        </View>

        {autoMode && (
          <Text style={[styles.lockedMessage, { color: colors.warning }]}>
            🔒 Manual gate controls are locked while Auto Mode is ON
          </Text>
        )}
      </View>

      {/* Auto Feed Dispensing Section */}
      <View style={styles.section}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            marginBottom: 12,
          }}
        >
          <Ionicons
            name="rocket-outline"
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            Auto Feed Dispensing
          </Text>
        </View>

        <View
          style={[
            styles.autoCard,
            {
              backgroundColor: colors.card,
              borderColor: autoMode ? colors.success : colors.border,
              borderWidth: autoMode ? 2 : 1,
            },
          ]}
        >
          {/* Auto Mode Toggle */}
          <View style={styles.autoRow}>
            <View>
              <View style={{ flexDirection: "row", alignItems: "center" }}>
                <Ionicons
                  name={autoMode ? "checkmark-circle" : "time-outline"}
                  size={20}
                  color={autoMode ? colors.success : colors.textMuted}
                  style={{ marginRight: 8 }}
                />
                <Text style={[styles.autoLabel, { color: colors.text }]}>
                  Auto Mode
                </Text>
              </View>
              <Text style={[styles.autoDesc, { color: colors.textMuted }]}>
                {autoMode
                  ? `✅ Auto dispensing is ON (${feedTimes.length} scheduled times)`
                  : "⏸️ Auto dispensing is OFF"}
              </Text>
            </View>
            <Switch
              value={autoMode}
              onValueChange={toggleAutoMode}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={autoMode ? "#FFFFFF" : "#f4f3f4"}
            />
          </View>

          {/* Amount per Dispense */}
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <Text style={[styles.settingLabel, { color: colors.text }]}>
              Amount per Dispense
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
                value={feedAmount}
                onChangeText={(text) => {
                  setFeedAmount(text);
                  saveAutoModeState();
                }}
                keyboardType="numeric"
                editable={true}
              />
              <Text style={[styles.settingValue, { color: colors.textMuted }]}>
                kg
              </Text>
            </View>
          </View>

          {/* Schedule Times */}
          <View style={styles.scheduleContainer}>
            <Text style={[styles.scheduleLabel, { color: colors.text }]}>
              Scheduled Times (12-hour format)
            </Text>

            <View style={styles.addTimeRow}>
              <TextInput
                style={[
                  styles.timeInput,
                  {
                    backgroundColor: colors.background,
                    borderColor: colors.border,
                    color: colors.text,
                  },
                ]}
                value={newTime}
                onChangeText={setNewTime}
                placeholder="e.g., 8:00 AM"
                placeholderTextColor={colors.textMuted}
                autoCapitalize="characters"
                editable={!autoMode}
              />
              <TouchableOpacity
                style={[
                  styles.addTimeBtn,
                  {
                    backgroundColor: colors.primary,
                    opacity: autoMode ? 0.5 : 1,
                  },
                ]}
                onPress={addTime}
                disabled={autoMode}
              >
                <Text style={styles.addTimeBtnText}>Add</Text>
              </TouchableOpacity>
            </View>

            <View style={styles.timeList}>
              {feedTimes.map((time, index) => (
                <View
                  key={index}
                  style={[
                    styles.timeItem,
                    {
                      backgroundColor: colors.backgroundSecondary,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <View style={{ flexDirection: "row", alignItems: "center" }}>
                    <Ionicons
                      name="time-outline"
                      size={16}
                      color={colors.text}
                    />
                    <Text style={[styles.timeItemText, { color: colors.text }]}>
                      {time}
                    </Text>
                  </View>
                  <TouchableOpacity
                    onPress={() => removeTime(time)}
                    disabled={autoMode}
                    style={{ opacity: autoMode ? 0.5 : 1 }}
                  >
                    <Ionicons
                      name="close-circle"
                      size={20}
                      color={autoMode ? colors.textMuted : colors.danger}
                    />
                  </TouchableOpacity>
                </View>
              ))}
            </View>

            <Text style={[styles.timeHint, { color: colors.textMuted }]}>
              💡 Examples: 8:00 AM, 12:00 PM, 4:30 PM, 8am, 12pm
            </Text>

            {autoMode && (
              <Text style={[styles.scheduleNote, { color: colors.warning }]}>
                ⚠️ Schedule is locked while Auto Mode is ON
              </Text>
            )}
          </View>

          {/* Next Dispense Time */}
          {autoMode && nextDispenseTime && (
            <View style={styles.nextDispenseContainer}>
              <Ionicons name="alarm-outline" size={20} color={colors.primary} />
              <Text style={[styles.nextDispenseText, { color: colors.text }]}>
                Next dispense at:{" "}
                {nextDispenseTime.toLocaleTimeString("en-US", {
                  hour: "numeric",
                  minute: "2-digit",
                  hour12: true,
                })}
              </Text>
            </View>
          )}

          {/* Progress */}
          <View style={styles.progressContainer}>
            <View style={styles.progressHeader}>
              <Text style={[styles.progressLabel, { color: colors.textMuted }]}>
                Today's Dispensed
              </Text>
              <Text style={[styles.progressText, { color: colors.text }]}>
                {feedDispensed.toFixed(2)} kg
              </Text>
            </View>
            <View style={styles.progressBar}>
              <View
                style={[
                  styles.progressFill,
                  {
                    width: `${Math.min(progress, 100)}%`,
                    backgroundColor:
                      progress >= 100 ? colors.success : colors.primary,
                  },
                ]}
              />
            </View>
            <Text style={[styles.progressPercent, { color: colors.textMuted }]}>
              {progress.toFixed(0)}% of daily target (5.0 kg)
            </Text>
            <Text style={[styles.dispenseCount, { color: colors.textMuted }]}>
              {dispenseCount} dispenses today
            </Text>
          </View>

          {/* Manual Dispense Button */}
          <TouchableOpacity
            style={[
              styles.dispenseBtn,
              {
                backgroundColor: colors.primary,
                opacity: isAutoDispensing || autoMode ? 0.5 : 1,
              },
            ]}
            onPress={handleDispenseFeed}
            disabled={isAutoDispensing || autoMode}
          >
            <Text style={styles.dispenseBtnText}>
              {isAutoDispensing ? "Dispensing..." : "Dispense Feed Now"}
            </Text>
          </TouchableOpacity>

          {autoMode && (
            <Text style={[styles.autoNote, { color: colors.warning }]}>
              ⚠️ Auto mode is ON. Manual dispense is disabled.
            </Text>
          )}
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
  disabledBtn: {
    opacity: 0.5,
  },
  autoIndicator: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    marginTop: 8,
  },
  autoIndicatorText: {
    fontSize: 12,
    fontWeight: "600",
    marginLeft: 6,
  },
  lockBadge: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 12,
  },
  lockBadgeText: {
    fontSize: 10,
    fontWeight: "600",
    marginLeft: 4,
  },
  lockedMessage: {
    fontSize: 12,
    textAlign: "center",
    marginTop: 8,
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
  autoCard: {
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  autoRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#E0E0E0",
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
  scheduleContainer: {
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: "#E0E0E0",
  },
  scheduleLabel: {
    fontSize: 14,
    fontWeight: "600",
    marginBottom: 8,
  },
  addTimeRow: {
    flexDirection: "row",
    gap: 8,
    marginBottom: 10,
  },
  timeInput: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 14,
  },
  addTimeBtn: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    justifyContent: "center",
  },
  addTimeBtnText: {
    color: "#FFFFFF",
    fontSize: 14,
    fontWeight: "600",
  },
  timeList: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
  },
  timeItem: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 16,
    borderWidth: 1,
    gap: 6,
  },
  timeItemText: {
    fontSize: 13,
    fontWeight: "500",
  },
  timeHint: {
    fontSize: 11,
    marginTop: 6,
    fontStyle: "italic",
  },
  scheduleNote: {
    fontSize: 11,
    marginTop: 6,
    fontStyle: "italic",
  },
  nextDispenseContainer: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    marginTop: 10,
    padding: 8,
    backgroundColor: "#F0F8FF",
    borderRadius: 8,
  },
  nextDispenseText: {
    fontSize: 13,
    fontWeight: "600",
    marginLeft: 8,
  },
  progressContainer: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: "#E0E0E0",
  },
  progressHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginBottom: 6,
  },
  progressLabel: {
    fontSize: 13,
  },
  progressText: {
    fontSize: 13,
    fontWeight: "600",
  },
  progressBar: {
    height: 8,
    borderRadius: 4,
    backgroundColor: "#E0E0E0",
    overflow: "hidden",
  },
  progressFill: {
    height: "100%",
    borderRadius: 4,
  },
  progressPercent: {
    fontSize: 12,
    marginTop: 4,
    textAlign: "right",
  },
  dispenseCount: {
    fontSize: 11,
    marginTop: 2,
    textAlign: "right",
  },
  dispenseBtn: {
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
    marginTop: 12,
  },
  dispenseBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#FFFFFF",
  },
  autoNote: {
    fontSize: 12,
    marginTop: 8,
    textAlign: "center",
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
  footer: {
    height: 40,
  },
});

export default GateControlScreen;
