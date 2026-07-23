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
import { useTheme } from "../../hooks/useTheme";

function SettingsScreen() {
  const { colors } = useTheme();
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
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
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
      <Text style={[styles.title, { color: colors.text }]}>⚙️ Settings</Text>
      <Text style={[styles.subtitle, { color: colors.textMuted }]}>
        Configure your app preferences
      </Text>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          👤 Profile
        </Text>
        <View
          style={[
            styles.profileCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
            <Text style={[styles.avatarText, { color: colors.text }]}>A</Text>
          </View>
          <View style={styles.profileInfo}>
            <Text style={[styles.profileName, { color: colors.text }]}>
              Admin User
            </Text>
            <Text style={[styles.profileRole, { color: colors.textMuted }]}>
              Farm Administrator
            </Text>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          🎨 Preferences
        </Text>
        <View
          style={[
            styles.settingCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View>
              <Text style={[styles.settingLabel, { color: colors.text }]}>
                🌙 Dark Mode
              </Text>
              <Text style={[styles.settingDesc, { color: colors.textMuted }]}>
                Switch to dark theme
              </Text>
            </View>
            <Switch
              value={false}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
            />
          </View>
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View>
              <Text style={[styles.settingLabel, { color: colors.text }]}>
                🔔 Notifications
              </Text>
              <Text style={[styles.settingDesc, { color: colors.textMuted }]}>
                Enable push notifications
              </Text>
            </View>
            <Switch
              value={true}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
            />
          </View>
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View>
              <Text style={[styles.settingLabel, { color: colors.text }]}>
                📶 Auto Refresh
              </Text>
              <Text style={[styles.settingDesc, { color: colors.textMuted }]}>
                Auto refresh dashboard data
              </Text>
            </View>
            <Switch
              value={true}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
            />
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
          ℹ️ About
        </Text>
        <View
          style={[
            styles.aboutCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <Text style={[styles.aboutTitle, { color: colors.text }]}>
            BroilerGuard
          </Text>
          <Text style={[styles.aboutVersion, { color: colors.textMuted }]}>
            Version 1.0.0
          </Text>
          <Text style={[styles.aboutDesc, { color: colors.textMuted }]}>
            Smart Poultry Management System
          </Text>
          <Text style={[styles.aboutDesc, { color: colors.textMuted }]}>
            IoT-based monitoring and automation
          </Text>
        </View>
      </View>

      <TouchableOpacity
        style={[
          styles.logoutBtn,
          {
            backgroundColor: colors.dangerLight,
            borderColor: colors.danger,
          },
        ]}
        onPress={handleLogout}
      >
        <Text style={[styles.logoutBtnText, { color: colors.danger }]}>
          🚪 Logout
        </Text>
      </TouchableOpacity>

      <View style={styles.footer}>
        <Text style={[styles.footerText, { color: colors.textMuted }]}>
          © 2025 BroilerGuard. All rights reserved.
        </Text>
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
  title: {
    fontSize: 24,
    fontWeight: "800",
    paddingHorizontal: 20,
    paddingTop: 20,
  },
  subtitle: {
    fontSize: 14,
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
    marginBottom: 12,
  },
  profileCard: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    justifyContent: "center",
    alignItems: "center",
    marginRight: 12,
  },
  avatarText: {
    fontSize: 24,
    fontWeight: "800",
  },
  profileInfo: {
    flex: 1,
  },
  profileName: {
    fontSize: 18,
    fontWeight: "700",
  },
  profileRole: {
    fontSize: 13,
    marginTop: 2,
  },
  settingCard: {
    borderRadius: 12,
    padding: 4,
    borderWidth: 1,
  },
  settingRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: 12,
    borderBottomWidth: 1,
  },
  settingLabel: {
    fontSize: 14,
    fontWeight: "600",
  },
  settingDesc: {
    fontSize: 12,
    marginTop: 2,
  },
  aboutCard: {
    borderRadius: 12,
    padding: 16,
    alignItems: "center",
    borderWidth: 1,
  },
  aboutTitle: {
    fontSize: 20,
    fontWeight: "800",
  },
  aboutVersion: {
    fontSize: 14,
    marginTop: 4,
  },
  aboutDesc: {
    fontSize: 13,
    marginTop: 2,
  },
  logoutBtn: {
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginTop: 8,
    alignItems: "center",
    borderWidth: 1,
  },
  logoutBtnText: {
    fontSize: 16,
    fontWeight: "700",
  },
  footer: {
    padding: 20,
    alignItems: "center",
  },
  footerText: {
    fontSize: 12,
  },
});

export default SettingsScreen;
