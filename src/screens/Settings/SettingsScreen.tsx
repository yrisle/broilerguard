// src/screens/Settings/SettingsScreen.tsx
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Switch,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import api from "../../api/client";
import { useAuth } from "../../context/AuthContext";

function SettingsScreen() {
  const { logout } = useAuth();
  const [settings, setSettings] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchSettings = async () => {
    try {
      const response = await api.get("/settings");
      if (response.data.success) {
        setSettings(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching settings:", error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchSettings();
  };

  const handleLogout = () => {
    Alert.alert("Logout", "Are you sure you want to logout?", [
      { text: "Cancel", style: "cancel" },
      { text: "Logout", style: "destructive", onPress: logout },
    ]);
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <Text style={styles.title}>⚙️ Settings</Text>
      <Text style={styles.subtitle}>Configure your app preferences</Text>

      {/* Profile */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>👤 Profile</Text>
        <View style={styles.profileCard}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>A</Text>
          </View>
          <View style={styles.profileInfo}>
            <Text style={styles.profileName}>Admin User</Text>
            <Text style={styles.profileRole}>Farm Administrator</Text>
          </View>
        </View>
      </View>

      {/* Preferences */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🎨 Preferences</Text>
        <View style={styles.settingCard}>
          <View style={styles.settingRow}>
            <View>
              <Text style={styles.settingLabel}>🌙 Dark Mode</Text>
              <Text style={styles.settingDesc}>Switch to dark theme</Text>
            </View>
            <Switch
              value={false}
              trackColor={{ false: "#E0D5C0", true: "#FFD62E" }}
            />
          </View>
          <View style={styles.settingRow}>
            <View>
              <Text style={styles.settingLabel}>🔔 Notifications</Text>
              <Text style={styles.settingDesc}>Enable push notifications</Text>
            </View>
            <Switch
              value={true}
              trackColor={{ false: "#E0D5C0", true: "#FFD62E" }}
            />
          </View>
          <View style={styles.settingRow}>
            <View>
              <Text style={styles.settingLabel}>📶 Auto Refresh</Text>
              <Text style={styles.settingDesc}>
                Auto refresh dashboard data
              </Text>
            </View>
            <Switch
              value={true}
              trackColor={{ false: "#E0D5C0", true: "#FFD62E" }}
            />
          </View>
        </View>
      </View>

      {/* About */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>ℹ️ About</Text>
        <View style={styles.aboutCard}>
          <Text style={styles.aboutTitle}>BroilerGuard</Text>
          <Text style={styles.aboutVersion}>Version 1.0.0</Text>
          <Text style={styles.aboutDesc}>Smart Poultry Management System</Text>
          <Text style={styles.aboutDesc}>
            IoT-based monitoring and automation
          </Text>
        </View>
      </View>

      {/* Logout */}
      <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
        <Text style={styles.logoutBtnText}>🚪 Logout</Text>
      </TouchableOpacity>

      <View style={styles.footer}>
        <Text style={styles.footerText}>
          © 2025 BroilerGuard. All rights reserved.
        </Text>
      </View>
    </ScrollView>
  );
}

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
  profileCard: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: "#FFD62E",
    justifyContent: "center",
    alignItems: "center",
    marginRight: 12,
  },
  avatarText: {
    fontSize: 24,
    fontWeight: "800",
    color: "#3E2C1C",
  },
  profileInfo: {
    flex: 1,
  },
  profileName: {
    fontSize: 18,
    fontWeight: "700",
    color: "#3E2C1C",
  },
  profileRole: {
    fontSize: 13,
    color: "#8B7355",
    marginTop: 2,
  },
  settingCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 4,
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  settingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255, 214, 46, 0.05)",
  },
  settingLabel: {
    fontSize: 14,
    fontWeight: "600",
    color: "#3E2C1C",
  },
  settingDesc: {
    fontSize: 12,
    color: "#8B7355",
    marginTop: 2,
  },
  aboutCard: {
    backgroundColor: "#FFFFFF",
    borderRadius: 12,
    padding: 16,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(255, 214, 46, 0.1)",
  },
  aboutTitle: {
    fontSize: 20,
    fontWeight: "800",
    color: "#3E2C1C",
  },
  aboutVersion: {
    fontSize: 14,
    color: "#8B7355",
    marginTop: 4,
  },
  aboutDesc: {
    fontSize: 13,
    color: "#8B7355",
    marginTop: 2,
  },
  logoutBtn: {
    backgroundColor: "#FDEDEC",
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginTop: 8,
    alignItems: "center",
    borderWidth: 1,
    borderColor: "#E74C3C",
  },
  logoutBtnText: {
    fontSize: 16,
    fontWeight: "700",
    color: "#E74C3C",
  },
  footer: {
    padding: 20,
    alignItems: "center",
  },
  footerText: {
    fontSize: 12,
    color: "#8B7355",
  },
});

export default SettingsScreen;
