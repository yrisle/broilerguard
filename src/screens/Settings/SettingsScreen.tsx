// src/screens/Settings/SettingsScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import * as ImagePicker from 'expo-image-picker';
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Image,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

interface UserProfile {
  id: number;
  username: string;
  name: string;
  role: string;
  email?: string;
  phone?: string;
  avatar?: string | null;
}

// Storage keys
const STORAGE_KEYS = {
  PROFILE: '@broilerguard_profile',
  NOTIFICATIONS: '@broilerguard_notifications',
  AUTO_REFRESH: '@broilerguard_auto_refresh',
};

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
    avatar: null,
  });
  const [editedProfile, setEditedProfile] = useState<UserProfile>(profile);
  const [tempAvatar, setTempAvatar] = useState<string | null>(null);

  // Settings states
  const [notifications, setNotifications] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(true);

  // Load saved data on mount
  useEffect(() => {
    loadSavedData();
    fetchSettings();
  }, []);

  const loadSavedData = async () => {
    try {
      // Load profile from AsyncStorage
      const savedProfile = await AsyncStorage.getItem(STORAGE_KEYS.PROFILE);
      if (savedProfile) {
        const parsedProfile = JSON.parse(savedProfile);
        setProfile(parsedProfile);
        setEditedProfile(parsedProfile);
      }

      // Load preferences
      const savedNotifications = await AsyncStorage.getItem(STORAGE_KEYS.NOTIFICATIONS);
      if (savedNotifications !== null) {
        setNotifications(JSON.parse(savedNotifications));
      }

      const savedAutoRefresh = await AsyncStorage.getItem(STORAGE_KEYS.AUTO_REFRESH);
      if (savedAutoRefresh !== null) {
        setAutoRefresh(JSON.parse(savedAutoRefresh));
      }
    } catch (error) {
      console.error("Error loading saved data:", error);
    }
  };

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

  const onRefresh = () => {
    setRefreshing(true);
    fetchSettings();
    loadSavedData();
  };

  const handleEditProfile = () => {
    setEditedProfile(profile);
    setTempAvatar(profile.avatar || null);
    setIsEditing(true);
  };

  const handlePickImage = async () => {
    try {
      // Request permission
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      
      if (status !== 'granted') {
        Alert.alert('Permission Needed', 'Please grant permission to access your photos.');
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.8,
        base64: true,
      });

      if (!result.canceled && result.assets[0]) {
        const imageUri = result.assets[0].uri;
        setTempAvatar(imageUri);
        setEditedProfile({ ...editedProfile, avatar: imageUri });
      }
    } catch (error) {
      console.error("Error picking image:", error);
      Alert.alert('Error', 'Failed to select image. Please try again.');
    }
  };

  const handleSaveProfile = async () => {
    // Validate fields
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

      // Save to AsyncStorage (persistent)
      await AsyncStorage.setItem(STORAGE_KEYS.PROFILE, JSON.stringify(editedProfile));

      // Update state
      setProfile(editedProfile);
      setIsEditing(false);
      setTempAvatar(null);

      // Try to save to API if available
      try {
        const response = await api.put("/user/profile", editedProfile);
        if (response.data.success) {
          Alert.alert("Success", "Profile updated successfully!");
          return;
        }
      } catch (apiError) {
        console.log("API not available, saved locally");
      }

      Alert.alert("Success", "Profile saved successfully!");
    } catch (error) {
      console.error("Error updating profile:", error);
      Alert.alert("Error", "Failed to update profile. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleCancelEdit = () => {
    setEditedProfile(profile);
    setTempAvatar(null);
    setIsEditing(false);
  };

  const handleSaveSettings = async () => {
    try {
      // Save preferences to AsyncStorage
      await AsyncStorage.setItem(STORAGE_KEYS.NOTIFICATIONS, JSON.stringify(notifications));
      await AsyncStorage.setItem(STORAGE_KEYS.AUTO_REFRESH, JSON.stringify(autoRefresh));
      
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
      <View style={styles.header}>
        <View style={{ flexDirection: "row", alignItems: "center" }}>
          <Ionicons
            name="settings-outline"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>Settings</Text>
        </View>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Configure your app preferences
        </Text>
      </View>

      {/* Profile Section */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <FontAwesome5
              name="user-circle"
              size={18}
              color={colors.textSecondary}
              style={{ marginRight: 8 }}
            />
            <Text
              style={[styles.sectionTitle, { color: colors.textSecondary }]}
            >
              Profile
            </Text>
          </View>
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
            <TouchableOpacity onPress={handleEditProfile} style={styles.avatarContainer}>
              {profile.avatar ? (
                <Image source={{ uri: profile.avatar }} style={styles.avatarImage} />
              ) : (
                <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
                  <Text style={[styles.avatarText, { color: "#FFFFFF" }]}>
                    {profile.name.charAt(0).toUpperCase()}
                  </Text>
                </View>
              )}
              <View style={[styles.avatarBadge, { backgroundColor: colors.primary }]}>
                <Ionicons name="camera" size={12} color="#FFFFFF" />
              </View>
            </TouchableOpacity>
            <View style={styles.profileInfo}>
              <Text style={[styles.profileName, { color: colors.text }]}>
                {profile.name}
              </Text>
              <Text style={[styles.profileRole, { color: colors.textMuted }]}>
                {profile.role}
              </Text>
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  marginTop: 2,
                }}
              >
                <Ionicons
                  name="mail-outline"
                  size={14}
                  color={colors.textMuted}
                  style={{ marginRight: 4 }}
                />
                <Text
                  style={[styles.profileEmail, { color: colors.textMuted }]}
                >
                  {profile.email}
                </Text>
              </View>
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  marginTop: 2,
                }}
              >
                <Ionicons
                  name="call-outline"
                  size={14}
                  color={colors.textMuted}
                  style={{ marginRight: 4 }}
                />
                <Text
                  style={[styles.profilePhone, { color: colors.textMuted }]}
                >
                  {profile.phone}
                </Text>
              </View>
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
                flexDirection: "column",
                alignItems: "center",
              },
            ]}
          >
            <TouchableOpacity onPress={handlePickImage} style={styles.avatarContainer}>
              {tempAvatar || editedProfile.avatar ? (
                <Image 
                  source={{ uri: tempAvatar || editedProfile.avatar || undefined }} 
                  style={styles.avatarImage} 
                />
              ) : (
                <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
                  <Text style={[styles.avatarText, { color: "#FFFFFF" }]}>
                    {editedProfile.name.charAt(0).toUpperCase()}
                  </Text>
                </View>
              )}
              <View style={[styles.avatarBadge, { backgroundColor: colors.primary }]}>
                <Ionicons name="camera" size={12} color="#FFFFFF" />
              </View>
            </TouchableOpacity>
            <Text style={[styles.changePhotoText, { color: colors.primary }]}>
              Tap to change photo
            </Text>
            
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
                    style={[
                      styles.cancelButtonText,
                      { color: colors.textMuted },
                    ]}
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
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <Ionicons
              name="color-palette-outline"
              size={18}
              color={colors.textSecondary}
              style={{ marginRight: 8 }}
            />
            <Text
              style={[styles.sectionTitle, { color: colors.textSecondary }]}
            >
              Preferences
            </Text>
          </View>
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
              <View style={{ flexDirection: "row", alignItems: "center" }}>
                <Ionicons
                  name="notifications-outline"
                  size={18}
                  color={colors.text}
                  style={{ marginRight: 8 }}
                />
                <Text style={[styles.settingLabel, { color: colors.text }]}>
                  Notifications
                </Text>
              </View>
              <Text style={[styles.settingDesc, { color: colors.textMuted }]}>
                Enable push notifications
              </Text>
            </View>
            <Switch
              value={notifications}
              onValueChange={setNotifications}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={notifications ? "#FFFFFF" : "#f4f3f4"}
            />
          </View>
          <View style={[styles.settingRow, { borderColor: colors.border }]}>
            <View>
              <View style={{ flexDirection: "row", alignItems: "center" }}>
                <Ionicons
                  name="refresh-outline"
                  size={18}
                  color={colors.text}
                  style={{ marginRight: 8 }}
                />
                <Text style={[styles.settingLabel, { color: colors.text }]}>
                  Auto Refresh
                </Text>
              </View>
              <Text style={[styles.settingDesc, { color: colors.textMuted }]}>
                Auto refresh dashboard data
              </Text>
            </View>
            <Switch
              value={autoRefresh}
              onValueChange={setAutoRefresh}
              trackColor={{ false: "#E0D5C0", true: colors.primary }}
              thumbColor={autoRefresh ? "#FFFFFF" : "#f4f3f4"}
            />
          </View>
        </View>
      </View>

      {/* About Section */}
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
            size={18}
            color={colors.textSecondary}
            style={{ marginRight: 8 }}
          />
          <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>
            About
          </Text>
        </View>
        <View
          style={[
            styles.aboutCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
        >
          <FontAwesome5
            name="drumstick-bite"
            size={40}
            color={colors.primary}
          />
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
  avatarContainer: {
    position: "relative",
    marginRight: 12,
  },
  avatar: {
    width: 70,
    height: 70,
    borderRadius: 35,
    justifyContent: "center",
    alignItems: "center",
  },
  avatarImage: {
    width: 70,
    height: 70,
    borderRadius: 35,
  },
  avatarText: {
    fontSize: 28,
    fontWeight: "800",
  },
  avatarBadge: {
    position: "absolute",
    bottom: 0,
    right: 0,
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: "center",
    alignItems: "center",
    borderWidth: 2,
    borderColor: "#FFFFFF",
  },
  changePhotoText: {
    fontSize: 12,
    marginBottom: 12,
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
    marginLeft: 2,
  },
  profilePhone: {
    fontSize: 13,
    marginLeft: 2,
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
    marginTop: 8,
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