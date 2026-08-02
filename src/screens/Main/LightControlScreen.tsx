// src/screens/Main/LightControlScreen.tsx
import Icon from "@expo/vector-icons/Ionicons";
import { useRouter } from "expo-router";
import React, { useState } from "react";
import {
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
  TextInput,
  Modal,
} from "react-native";
import Card from "../../components/common/Card";
import { useTheme } from "../../hooks/useTheme";

const LightControlScreen = () => {
  const { colors } = useTheme();
  const router = useRouter();
  
  // Local state only - no API calls
  const [lightStatus, setLightStatus] = useState("OFF");
  const [brightness, setBrightness] = useState(50);
  const [schedule, setSchedule] = useState({
    onTime: "06:00",
    offTime: "18:00",
    enabled: true,
  });
  const [showTimePicker, setShowTimePicker] = useState(false);
  const [editingTime, setEditingTime] = useState<"on" | "off" | null>(null);

  // Toggle light locally
  const toggleLight = () => {
    setLightStatus(lightStatus === "ON" ? "OFF" : "ON");
  };

  // Update brightness locally
  const updateBrightness = (value: number) => {
    setBrightness(value);
  };

  // Manual control locally
  const handleManualControl = (status: string) => {
    if (status === lightStatus) return;
    setLightStatus(status);
  };

  // Toggle schedule locally
  const toggleSchedule = () => {
    setSchedule({ ...schedule, enabled: !schedule.enabled });
  };

  // Update schedule time locally
  const updateScheduleTime = (type: "on" | "off", time: string) => {
    const newSchedule = { 
      ...schedule,
      onTime: type === "on" ? time : schedule.onTime,
      offTime: type === "off" ? time : schedule.offTime,
    };
    setSchedule(newSchedule);
    setShowTimePicker(false);
  };

  // Fallback colors
  const themeColors = {
    background: colors?.background || "#F9FAFB",
    text: colors?.text || "#111827",
    textMuted: colors?.textMuted || "#6B7280",
    textSecondary: colors?.textSecondary || "#4B5563",
    card: colors?.card || "#FFFFFF",
    border: colors?.border || "#D1D5DB",
    primary: colors?.primary || "#3B82F6",
    success: colors?.success || "#10B981",
    danger: colors?.danger || "#EF4444",
    warning: colors?.warning || "#F59E0B",
    warningLight: colors?.warningLight || "#FEF3C7",
  };

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: themeColors.background }]}
    >
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          onPress={() => router.back()}
          style={styles.backButton}
        >
          <Icon name="arrow-back" size={24} color={themeColors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: themeColors.text }]}>
          Light Control
        </Text>
        <View style={{ width: 40 }} />
      </View>

      {/* Demo Mode Banner */}
      <View
        style={[
          styles.demoBanner,
          {
            backgroundColor: themeColors.warningLight,
            borderColor: themeColors.warning,
          },
        ]}
      >
        <Icon name="information-circle" size={20} color={themeColors.warning} />
        <Text style={[styles.demoText, { color: themeColors.warning }]}>
          Demo Mode - Local Control Only
        </Text>
      </View>

      {/* Light Status Card */}
      <Card style={styles.statusCard}>
        <View style={styles.statusContainer}>
          <View style={styles.statusIconContainer}>
            <Icon
              name={lightStatus === "ON" ? "bulb" : "bulb-outline"}
              size={60}
              color={lightStatus === "ON" ? themeColors.warning : themeColors.textMuted}
            />
          </View>
          <View style={styles.statusInfo}>
            <Text style={[styles.statusLabel, { color: themeColors.textMuted }]}>
              Light Status
            </Text>
            <Text
              style={[
                styles.statusValue,
                {
                  color: lightStatus === "ON" ? themeColors.success : themeColors.danger,
                },
              ]}
            >
              {lightStatus}
            </Text>
            <View style={styles.switchContainer}>
              <Switch
                trackColor={{ 
                  false: themeColors.border, 
                  true: themeColors.success 
                }}
                thumbColor={lightStatus === "ON" ? "#fff" : "#f4f3f4"}
                ios_backgroundColor={themeColors.border}
                onValueChange={toggleLight}
                value={lightStatus === "ON"}
              />
              <Text style={[styles.switchLabel, { color: themeColors.textMuted }]}>
                Toggle Light
              </Text>
            </View>
          </View>
        </View>
      </Card>

      {/* Brightness Control */}
      <Card style={styles.brightnessCard}>
        <Text style={[styles.brightnessTitle, { color: themeColors.text }]}>
          Brightness Control
        </Text>
        <View style={styles.brightnessContainer}>
          <Icon name="sunny-outline" size={24} color={themeColors.textMuted} />
          <View style={styles.brightnessSliderContainer}>
            <View style={styles.brightnessTrack}>
              <View
                style={[
                  styles.brightnessFill,
                  {
                    width: `${brightness}%`,
                    backgroundColor: themeColors.warning,
                  },
                ]}
              />
            </View>
            <View style={styles.brightnessControls}>
              {[0, 25, 50, 75, 100].map((value) => (
                <TouchableOpacity
                  key={value}
                  style={[
                    styles.brightnessDot,
                    {
                      backgroundColor:
                        brightness === value ? themeColors.warning : themeColors.border,
                    },
                  ]}
                  onPress={() => updateBrightness(value)}
                />
              ))}
            </View>
          </View>
          <Text style={[styles.brightnessValue, { color: themeColors.text }]}>
            {brightness}%
          </Text>
        </View>
        <View style={styles.brightnessButtons}>
          {["Low", "Medium", "High"].map((level, index) => {
            const values = [25, 50, 75];
            return (
              <TouchableOpacity
                key={level}
                style={[
                  styles.brightnessButton,
                  {
                    backgroundColor:
                      brightness === values[index]
                        ? themeColors.warning
                        : themeColors.card,
                    borderColor: themeColors.border,
                  },
                ]}
                onPress={() => updateBrightness(values[index])}
              >
                <Text
                  style={[
                    styles.brightnessButtonText,
                    {
                      color:
                        brightness === values[index]
                          ? "#fff"
                          : themeColors.textSecondary,
                    },
                  ]}
                >
                  {level}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </Card>

      {/* Manual Control */}
      <Card style={styles.manualControlCard}>
        <Text style={[styles.manualTitle, { color: themeColors.text }]}>
          Manual Control
        </Text>
        <View style={styles.manualButtons}>
          <TouchableOpacity
            style={[
              styles.manualButton,
              {
                backgroundColor:
                  lightStatus === "ON" ? themeColors.success : themeColors.card,
                borderColor: themeColors.border,
              },
            ]}
            onPress={() => handleManualControl("ON")}
            disabled={lightStatus === "ON"}
          >
            <Icon
              name="power"
              size={24}
              color={lightStatus === "ON" ? "#fff" : themeColors.textSecondary}
            />
            <Text
              style={[
                styles.manualButtonText,
                {
                  color: lightStatus === "ON" ? "#fff" : themeColors.textSecondary,
                },
              ]}
            >
              Turn ON
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[
              styles.manualButton,
              {
                backgroundColor:
                  lightStatus === "OFF" ? themeColors.danger : themeColors.card,
                borderColor: themeColors.border,
              },
            ]}
            onPress={() => handleManualControl("OFF")}
            disabled={lightStatus === "OFF"}
          >
            <Icon
              name="power-outline"
              size={24}
              color={lightStatus === "OFF" ? "#fff" : themeColors.textSecondary}
            />
            <Text
              style={[
                styles.manualButtonText,
                {
                  color: lightStatus === "OFF" ? "#fff" : themeColors.textSecondary,
                },
              ]}
            >
              Turn OFF
            </Text>
          </TouchableOpacity>
        </View>
      </Card>

      {/* Schedule/Automation */}
      <Card style={styles.scheduleCard}>
        <Text style={[styles.scheduleTitle, { color: themeColors.text }]}>
          Automation Schedule
        </Text>
        
        {/* ON Time */}
        <TouchableOpacity
          style={styles.scheduleItem}
          onPress={() => {
            setEditingTime("on");
            setShowTimePicker(true);
          }}
        >
          <View style={styles.scheduleInfo}>
            <Icon name="time-outline" size={20} color={themeColors.textMuted} />
            <Text style={[styles.scheduleText, { color: themeColors.textMuted }]}>
              Light ON at {schedule.onTime}
            </Text>
          </View>
          <Icon name="chevron-forward" size={20} color={themeColors.textMuted} />
        </TouchableOpacity>
        
        {/* OFF Time */}
        <TouchableOpacity
          style={styles.scheduleItem}
          onPress={() => {
            setEditingTime("off");
            setShowTimePicker(true);
          }}
        >
          <View style={styles.scheduleInfo}>
            <Icon name="time-outline" size={20} color={themeColors.textMuted} />
            <Text style={[styles.scheduleText, { color: themeColors.textMuted }]}>
              Light OFF at {schedule.offTime}
            </Text>
          </View>
          <Icon name="chevron-forward" size={20} color={themeColors.textMuted} />
        </TouchableOpacity>
        
        {/* Enable Automation */}
        <View style={[styles.scheduleItem, styles.scheduleToggle]}>
          <View style={styles.scheduleInfo}>
            <Icon name="calendar-outline" size={20} color={themeColors.textMuted} />
            <Text style={[styles.scheduleText, { color: themeColors.textMuted }]}>
              Enable Automation
            </Text>
          </View>
          <Switch
            trackColor={{ false: themeColors.border, true: themeColors.success }}
            thumbColor="#fff"
            ios_backgroundColor={themeColors.border}
            onValueChange={toggleSchedule}
            value={schedule.enabled}
          />
        </View>
      </Card>

      {/* Time Picker Modal */}
      <Modal
        visible={showTimePicker}
        transparent={true}
        animationType="slide"
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: themeColors.card }]}>
            <Text style={[styles.modalTitle, { color: themeColors.text }]}>
              Select Time
            </Text>
            
            <TextInput
              style={[styles.timeInput, { 
                borderColor: themeColors.border,
                color: themeColors.text,
              }]}
              value={editingTime === "on" ? schedule.onTime : schedule.offTime}
              onChangeText={(text) => {
                // Simple time validation (HH:MM)
                if (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(text) || text === "") {
                  if (editingTime === "on") {
                    setSchedule({ ...schedule, onTime: text });
                  } else {
                    setSchedule({ ...schedule, offTime: text });
                  }
                }
              }}
              placeholder="HH:MM"
              placeholderTextColor={themeColors.textMuted}
              keyboardType="numbers-and-punctuation"
              maxLength={5}
            />
            
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalButton, { backgroundColor: themeColors.border }]}
                onPress={() => {
                  setShowTimePicker(false);
                }}
              >
                <Text style={{ color: themeColors.text }}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, { backgroundColor: themeColors.primary }]}
                onPress={() => {
                  if (editingTime === "on") {
                    updateScheduleTime("on", schedule.onTime);
                  } else {
                    updateScheduleTime("off", schedule.offTime);
                  }
                }}
              >
                <Text style={{ color: "#fff" }}>Save</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <View style={styles.footer} />
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: "700",
  },
  demoBanner: {
    marginHorizontal: 16,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    flexDirection: "row",
    alignItems: "center",
  },
  demoText: {
    fontSize: 12,
    fontWeight: "600",
    marginLeft: 8,
  },
  statusCard: {
    marginHorizontal: 16,
    marginTop: 16,
  },
  statusContainer: {
    flexDirection: "row",
    alignItems: "center",
    padding: 16,
  },
  statusIconContainer: {
    marginRight: 20,
  },
  statusInfo: {
    flex: 1,
  },
  statusLabel: {
    fontSize: 14,
    fontWeight: "500",
  },
  statusValue: {
    fontSize: 24,
    fontWeight: "700",
    marginTop: 4,
  },
  switchContainer: {
    flexDirection: "row",
    alignItems: "center",
    marginTop: 8,
  },
  switchLabel: {
    marginLeft: 8,
    fontSize: 14,
  },
  brightnessCard: {
    marginHorizontal: 16,
    marginTop: 12,
  },
  brightnessTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 16,
  },
  brightnessContainer: {
    flexDirection: "row",
    alignItems: "center",
  },
  brightnessSliderContainer: {
    flex: 1,
    marginHorizontal: 12,
  },
  brightnessTrack: {
    height: 6,
    backgroundColor: "#E5E7EB",
    borderRadius: 3,
    overflow: "hidden",
  },
  brightnessFill: {
    height: "100%",
    borderRadius: 3,
  },
  brightnessControls: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 8,
  },
  brightnessDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: "#D1D5DB",
  },
  brightnessValue: {
    fontSize: 16,
    fontWeight: "600",
    minWidth: 50,
    textAlign: "right",
  },
  brightnessButtons: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 16,
  },
  brightnessButton: {
    flex: 1,
    paddingVertical: 10,
    marginHorizontal: 4,
    borderRadius: 8,
    alignItems: "center",
    borderWidth: 1,
  },
  brightnessButtonText: {
    fontSize: 14,
    fontWeight: "500",
  },
  manualControlCard: {
    marginHorizontal: 16,
    marginTop: 12,
  },
  manualTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 16,
  },
  manualButtons: {
    flexDirection: "row",
    justifyContent: "space-between",
  },
  manualButton: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 12,
    marginHorizontal: 4,
    borderRadius: 8,
    borderWidth: 1,
  },
  manualButtonText: {
    fontSize: 14,
    fontWeight: "600",
    marginLeft: 8,
  },
  scheduleCard: {
    marginHorizontal: 16,
    marginTop: 12,
    marginBottom: 16,
  },
  scheduleTitle: {
    fontSize: 16,
    fontWeight: "600",
    marginBottom: 16,
  },
  scheduleItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: "#F3F4F6",
  },
  scheduleToggle: {
    borderBottomWidth: 0,
    marginTop: 4,
  },
  scheduleInfo: {
    flexDirection: "row",
    alignItems: "center",
  },
  scheduleText: {
    fontSize: 14,
    marginLeft: 8,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(0, 0, 0, 0.5)",
    justifyContent: "center",
    alignItems: "center",
  },
  modalContent: {
    width: "80%",
    padding: 20,
    borderRadius: 12,
    alignItems: "center",
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: "600",
    marginBottom: 16,
  },
  timeInput: {
    width: "100%",
    borderWidth: 1,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    textAlign: "center",
    marginBottom: 16,
  },
  modalButtons: {
    flexDirection: "row",
    justifyContent: "space-between",
    width: "100%",
  },
  modalButton: {
    flex: 1,
    padding: 12,
    borderRadius: 8,
    alignItems: "center",
    marginHorizontal: 4,
  },
  footer: {
    height: 40,
  },
});

export default LightControlScreen;