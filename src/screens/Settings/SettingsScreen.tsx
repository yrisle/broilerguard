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
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

interface UserProfile {
  id: number;
  username: string;
  name: string;
  role: string;
  email?: string;
  phone?: string;
}

function SettingsScreen() {
  const { colors } = useTheme();
  const [settings, setSettings] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // Profile states
  const [isEditing, setIsEditing] = useState(false);
  const [profile, setProfile] = useState<UserProfile>({
    id: 1,
    username: "admin",
    name: "Admin User",
    role: "Farm Administrator",
    email: "admin@broilerguard.com",
    phone: "+63 912 345 6789",
  });
  const [editedProfile, setEditedProfile] = useState<UserProfile>(profile);

  // Settings states
  const [darkMode, setDarkMode] = useState(false);
  const [notifications, setNotifications] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(true);

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
    loadPreferences();
  }, []);

  const loadPreferences = () => {
    // Load from AsyncStorage or default values
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchSettings();
  };

  const handleEditProfile = () => {
    setEditedProfile(profile);
    setIsEditing(true);
  };

  const handleSaveProfile = async () => {
    // Check if any field is empty
    if (!editedProfile.name.trim()) {
      Alert.alert("Error", "Name is required");
      return;
    }
    if (!editedProfile.email?.trim()) {
      Alert.alert("Error", "Email is required");
      return;
    }

    try {
      setLoading(true);

      // Try to save to API
      try {
        const response = await api.put("/user/profile", editedProfile);
        if (response.data.success) {
          setProfile(editedProfile);
          setIsEditing(false);
          Alert.alert("Success", "Profile updated successfully!");
          return;
        }
      } catch (apiError) {
        console.log("API not available, saving locally");
      }

      // If API fails, save locally
      setProfile(editedProfile);
      setIsEditing(false);
      Alert.alert("Success", "Profile updated locally!");
    } catch (error) {
      console.error("Error updating profile:", error);
      Alert.alert("Error", "Failed to update profile. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleCancelEdit = () => {
    setEditedProfile(profile);
    setIsEditing(false);
  };

  const handleSaveSettings = async () => {
    try {
      // Save preferences locally
      Alert.alert("Success", "Settings saved successfully!");
    } catch (error) {
      Alert.alert("Error", "Failed to save settings");
    }
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

      {/* Profile Section */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            👤 Profile
          </Text>
          {!isEditing && (
            <TouchableOpacity onPress={handleEditProfile}>
              <Text style={[styles.editButton, { color: colors.primary }]}>
                Edit
              </Text>
            </TouchableOpacity>
          )}
        </View>

        {!isEditing ? (
          // View Mode
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
              <Text style={[styles.avatarText, { color: colors.text }]}>
                {profile.name.charAt(0).toUpperCase()}
              </Text>
            </View>
            <View style={styles.profileInfo}>
              <Text style={[styles.profileName, { color: colors.text }]}>
                {profile.name}
              </Text>
              <Text style={[styles.profileRole, { color: colors.textMuted }]}>
                {profile.role}
              </Text>
              <Text style={[styles.profileEmail, { color: colors.textMuted }]}>
                {profile.email}
              </Text>
              <Text style={[styles.profilePhone, { color: colors.textMuted }]}>
                {profile.phone}
              </Text>
            </View>
          </View>
        ) : (
          // Edit Mode
          <View
            style={[
              styles.profileCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <View style={styles.editForm}>
              <TextInput
                style={[
                  styles.input,
                  {
                    backgroundColor: colors.background,
                    color: colors.text,
                    borderColor: colors.border,
                  },
                ]}
                placeholder="Full Name"
                placeholderTextColor={colors.textMuted}
                value={editedProfile.name}
                onChangeText={(text) =>
                  setEditedProfile({ ...editedProfile, name: text })
                }
              />
              <TextInput
                style={[
                  styles.input,
                  {
                    backgroundColor: colors.background,
                    color: colors.text,
                    borderColor: colors.border,
                  },
                ]}
                placeholder="Email"
                placeholderTextColor={colors.textMuted}
                value={editedProfile.email}
                onChangeText={(text) =>
                  setEditedProfile({ ...editedProfile, email: text })
                }
                keyboardType="email-address"
              />
              <TextInput
                style={[
                  styles.input,
                  {
                    backgroundColor: colors.background,
                    color: colors.text,
                    borderColor: colors.border,
                  },
                ]}
                placeholder="Phone Number"
                placeholderTextColor={colors.textMuted}
                value={editedProfile.phone}
                onChangeText={(text) =>
                  setEditedProfile({ ...editedProfile, phone: text })
                }
                keyboardType="phone-pad"
              />
              <TextInput
                style={[
                  styles.input,
                  {
                    backgroundColor: colors.background,
                    color: colors.text,
                    borderColor: colors.border,
                  },
                ]}
                placeholder="Role"
                placeholderTextColor={colors.textMuted}
                value={editedProfile.role}
                onChangeText={(text) =>
                  setEditedProfile({ ...editedProfile, role: text })
                }
              />
              <View style={styles.editActions}>
                <TouchableOpacity
                  style={[styles.cancelButton, { borderColor: colors.border }]}
                  onPress={handleCancelEdit}
                >
                  <Text
                    style={[styles.cancelButtonText, { color: colors.text }]}
                  >
                    Cancel
                  </Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[
                    styles.saveButton,
                    { backgroundColor: colors.primary },
                  ]}
                  onPress={handleSaveProfile}
                >
                  <Text style={styles.saveButtonText}>Save</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        )}
      </View>

      {/* Preferences Section */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            🎨 Preferences
          </Text>
          <TouchableOpacity onPress={handleSaveSettings}>
            <Text
              style={[styles.saveSettingsButton, { color: colors.primary }]}
            >
              Save
            </Text>
          </TouchableOpacity>
        </View>

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
              value={darkMode}
              onValueChange={setDarkMode}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={darkMode ? "#fff" : "#f4f3f4"}
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
              value={notifications}
              onValueChange={setNotifications}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={notifications ? "#fff" : "#f4f3f4"}
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
              value={autoRefresh}
              onValueChange={setAutoRefresh}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={autoRefresh ? "#fff" : "#f4f3f4"}
            />
          </View>
        </View>
      </View>

      {/* About Section */}
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
  sectionHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
  },
  editButton: {
    fontSize: 14,
    fontWeight: "600",
  },
  saveSettingsButton: {
    fontSize: 14,
    fontWeight: "600",
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
  profileEmail: {
    fontSize: 13,
    marginTop: 2,
  },
  profilePhone: {
    fontSize: 13,
    marginTop: 2,
  },
  editForm: {
    flex: 1,
    width: "100%",
    gap: 10,
  },
  input: {
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    fontSize: 14,
    width: "100%",
  },
  editActions: {
    flexDirection: "row",
    justifyContent: "flex-end",
    gap: 10,
    marginTop: 10,
  },
  cancelButton: {
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    marginRight: 10,
  },
  cancelButtonText: {
    fontSize: 14,
    fontWeight: "600",
  },
  saveButton: {
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 8,
  },
  saveButtonText: {
    color: "#FFFFFF",
    fontSize: 14,
    fontWeight: "600",
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
  footer: {
    padding: 20,
    alignItems: "center",
  },
  footerText: {
    fontSize: 12,
  },
});

export default SettingsScreen;
