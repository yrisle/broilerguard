// src/screens/Settings/SettingsScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
import Ionicons from "@expo/vector-icons/Ionicons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as ImagePicker from "expo-image-picker";
import { useRouter } from "expo-router";
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
import api from "../../api/client";
import { useAuth, User } from "../../context/AuthContext";
import { useTheme } from "../../hooks/useTheme";

// Storage keys
const STORAGE_KEYS = {
  PROFILE: "@broilerguard_profile",
  NOTIFICATIONS: "@broilerguard_notifications",
  AUTO_REFRESH: "@broilerguard_auto_refresh",
};

function SettingsScreen() {
  const { colors } = useTheme();
  const { logout, user, token, updateUser } = useAuth();
  const router = useRouter();
  const [settings, setSettings] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  // Profile states
  const [isEditing, setIsEditing] = useState(false);
  const [profile, setProfile] = useState<User | null>(null);
  const [editedProfile, setEditedProfile] = useState<User | null>(null);
  const [tempAvatar, setTempAvatar] = useState<string | null>(null);

  // Settings states
  const [notifications, setNotifications] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(true);

  // ✅ Update profile when user changes (login/logout)
  useEffect(() => {
    if (user) {
      // Set profile from user data
      const userProfile: User = {
        id: user.id || 0,
        username: user.username || "guest",
        name: user.full_name || user.name || user.username || "Guest User",
        full_name: user.full_name || user.name || "",
        role: user.role || "viewer",
        email: user.email || "",
        phone: user.phone || "",
        farm_name: user.farm_name || "",
        avatar: user.avatar || null,
        status: user.status || "active",
        source: user.source || "unknown",
      };
      setProfile(userProfile);
      setEditedProfile(userProfile);
      setLoading(false); // ✅ Set loading to false when user is available
    } else {
      // If no user, still set loading to false after a short delay
      setTimeout(() => {
        setLoading(false);
      }, 1000);
    }
  }, [user]);

  // Load saved data on mount and when user changes
  useEffect(() => {
    if (user) {
      loadSavedData();
      fetchSettings();
      fetchUserProfile();
    } else {
      // If no user, stop loading after a moment
      setTimeout(() => {
        setLoading(false);
      }, 1000);
    }
  }, [user]);

  const loadSavedData = async () => {
    try {
      // Load profile from storage
      const savedProfile = await AsyncStorage.getItem(STORAGE_KEYS.PROFILE);
      if (savedProfile) {
        const parsedProfile = JSON.parse(savedProfile);
        // Only use saved profile if it matches the current user
        if (parsedProfile.id === user?.id) {
          setProfile(parsedProfile);
          setEditedProfile(parsedProfile);
          return;
        }
      }

      // If no saved profile or different user, use user from AuthContext
      if (user) {
        const userProfile: User = {
          id: user.id || 0,
          username: user.username || "guest",
          name: user.full_name || user.name || user.username || "Guest User",
          full_name: user.full_name || user.name || "",
          role: user.role || "viewer",
          email: user.email || "",
          phone: user.phone || "",
          farm_name: user.farm_name || "",
          avatar: user.avatar || null,
          status: user.status || "active",
          source: user.source || "unknown",
        };
        setProfile(userProfile);
        setEditedProfile(userProfile);
        await AsyncStorage.setItem(
          STORAGE_KEYS.PROFILE,
          JSON.stringify(userProfile),
        );
      }

      // Load settings
      const savedNotifications = await AsyncStorage.getItem(
        STORAGE_KEYS.NOTIFICATIONS,
      );
      if (savedNotifications !== null) {
        setNotifications(JSON.parse(savedNotifications));
      }

      const savedAutoRefresh = await AsyncStorage.getItem(
        STORAGE_KEYS.AUTO_REFRESH,
      );
      if (savedAutoRefresh !== null) {
        setAutoRefresh(JSON.parse(savedAutoRefresh));
      }
    } catch (error) {
      console.error("Error loading saved data:", error);
    } finally {
      // ✅ Always set loading to false after loading data
      setLoading(false);
    }
  };

  const fetchUserProfile = async () => {
    try {
      if (!token || !user) return;

      const response = await api.get("/user/profile");
      if (response.data && response.data.success) {
        const userData = response.data.data || response.data.user;
        if (userData && userData.id === user.id) {
          const updatedProfile: User = {
            id: userData.id || user.id,
            username: userData.username || user.username,
            name:
              userData.full_name ||
              userData.name ||
              userData.username ||
              user.name,
            full_name: userData.full_name || userData.name || "",
            role: userData.role || user.role,
            email: userData.email || user.email,
            phone: userData.phone || user.phone,
            farm_name: userData.farm_name || user.farm_name,
            avatar: userData.avatar || null,
            status: userData.status || user.status,
            source: userData.source || user.source,
          };
          setProfile(updatedProfile);
          setEditedProfile(updatedProfile);
          await AsyncStorage.setItem(
            STORAGE_KEYS.PROFILE,
            JSON.stringify(updatedProfile),
          );

          // Also update AuthContext user
          await updateUser({
            full_name: updatedProfile.name,
            name: updatedProfile.name,
            email: updatedProfile.email,
            phone: updatedProfile.phone,
            farm_name: updatedProfile.farm_name,
            avatar: updatedProfile.avatar,
            role: updatedProfile.role,
          });
        }
      }
    } catch (error) {
      console.error("Error fetching user profile:", error);
    }
  };

  const fetchSettings = async () => {
    try {
      const response = await api.get("/settings");
      if (response.data && response.data.success) {
        setSettings(response.data.data);
      }
    } catch (error) {
      console.error("Error fetching settings:", error);
    } finally {
      // ✅ Also set loading to false here
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    setLoading(true); // ✅ Set loading to true when refreshing
    Promise.all([fetchSettings(), fetchUserProfile(), loadSavedData()]).finally(
      () => {
        setLoading(false);
        setRefreshing(false);
      },
    );
  };

  const handleEditProfile = () => {
    if (profile) {
      setEditedProfile({ ...profile });
      setTempAvatar(profile.avatar || null);
      setIsEditing(true);
    }
  };

  const handlePickImage = async () => {
    try {
      const { status } =
        await ImagePicker.requestMediaLibraryPermissionsAsync();

      if (status !== "granted") {
        Alert.alert(
          "Permission Needed",
          "Please grant permission to access your photos.",
        );
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
        if (editedProfile) {
          setEditedProfile({ ...editedProfile, avatar: imageUri });
        }
      }
    } catch (error) {
      console.error("Error picking image:", error);
      Alert.alert("Error", "Failed to select image. Please try again.");
    }
  };

  const handleSaveProfile = async () => {
    if (!editedProfile) return;

    if (!editedProfile.name?.trim()) {
      Alert.alert("Error", "Name is required");
      return;
    }
    if (!editedProfile.email?.trim()) {
      Alert.alert("Error", "Email is required");
      return;
    }

    try {
      setLoading(true);

      const profileData = {
        id: editedProfile.id,
        username: editedProfile.username,
        full_name: editedProfile.name,
        name: editedProfile.name,
        email: editedProfile.email,
        phone: editedProfile.phone,
        role: editedProfile.role,
        farm_name: editedProfile.farm_name,
        avatar: editedProfile.avatar,
      };

      try {
        const response = await api.put("/user/profile", profileData);
        if (response.data && response.data.success) {
          setProfile(editedProfile);
          await AsyncStorage.setItem(
            STORAGE_KEYS.PROFILE,
            JSON.stringify(editedProfile),
          );

          await updateUser({
            full_name: editedProfile.name,
            name: editedProfile.name,
            email: editedProfile.email,
            phone: editedProfile.phone,
            farm_name: editedProfile.farm_name,
            avatar: editedProfile.avatar,
          });

          setIsEditing(false);
          setTempAvatar(null);
          Alert.alert("Success", "Profile updated successfully!");
          return;
        }
      } catch (apiError: any) {
        console.log("API error:", apiError.response?.data || apiError.message);
        await AsyncStorage.setItem(
          STORAGE_KEYS.PROFILE,
          JSON.stringify(editedProfile),
        );
        setProfile(editedProfile);

        await updateUser({
          full_name: editedProfile.name,
          name: editedProfile.name,
          email: editedProfile.email,
          phone: editedProfile.phone,
          farm_name: editedProfile.farm_name,
          avatar: editedProfile.avatar,
        });

        setIsEditing(false);
        setTempAvatar(null);
        Alert.alert("Success", "Profile saved locally! Will sync when online.");
        return;
      }
    } catch (error) {
      console.error("Error updating profile:", error);
      Alert.alert("Error", "Failed to update profile. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleCancelEdit = () => {
    if (profile) {
      setEditedProfile({ ...profile });
    }
    setTempAvatar(null);
    setIsEditing(false);
  };

  const handleSaveSettings = async () => {
    try {
      await AsyncStorage.setItem(
        STORAGE_KEYS.NOTIFICATIONS,
        JSON.stringify(notifications),
      );
      await AsyncStorage.setItem(
        STORAGE_KEYS.AUTO_REFRESH,
        JSON.stringify(autoRefresh),
      );
      Alert.alert("Success", "Settings saved successfully!");
    } catch (error) {
      Alert.alert("Error", "Failed to save settings");
    }
  };

  const handleLogout = () => {
    Alert.alert("Logout", "Are you sure you want to logout?", [
      {
        text: "Cancel",
        style: "cancel",
      },
      {
        text: "Logout",
        style: "destructive",
        onPress: async () => {
          try {
            setIsLoggingOut(true);
            await logout();
          } catch (error) {
            console.error("Logout error:", error);
            try {
              await AsyncStorage.multiRemove(["auth_token", "user"]);
              router.replace("/login");
            } catch (e) {
              console.error("Force logout failed:", e);
            }
          } finally {
            setIsLoggingOut(false);
          }
        },
      },
    ]);
  };

  const getRoleDisplay = (role: string) => {
    const roleMap: { [key: string]: string } = {
      admin: "Administrator",
      staff: "Staff",
      viewer: "Viewer",
      super_admin: "Super Admin",
      farm_manager: "Farm Manager",
    };
    return roleMap[role?.toLowerCase()] || role || "User";
  };

  // ✅ Show loading only if loading is true AND no profile
  if (loading) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={[styles.loadingText, { color: colors.textMuted }]}>
          Loading profile...
        </Text>
      </View>
    );
  }

  // ✅ If no profile but not loading, show fallback
  if (!profile || !user) {
    return (
      <View style={[styles.centered, { backgroundColor: colors.background }]}>
        <Text style={[styles.errorText, { color: colors.text }]}>
          No user profile found
        </Text>
        <TouchableOpacity
          style={[styles.retryButton, { backgroundColor: colors.primary }]}
          onPress={onRefresh}
        >
          <Text style={styles.retryButtonText}>Retry</Text>
        </TouchableOpacity>
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
          <View
            style={[
              styles.profileCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
            ]}
          >
            <TouchableOpacity
              onPress={handleEditProfile}
              style={styles.avatarContainer}
            >
              {profile.avatar ? (
                <Image
                  source={{ uri: profile.avatar }}
                  style={styles.avatarImage}
                />
              ) : (
                <View
                  style={[styles.avatar, { backgroundColor: colors.primary }]}
                >
                  <Text style={[styles.avatarText, { color: "#FFFFFF" }]}>
                    {(profile.name || profile.username || "U")
                      .charAt(0)
                      .toUpperCase()}
                  </Text>
                </View>
              )}
              <View
                style={[
                  styles.avatarBadge,
                  { backgroundColor: colors.primary },
                ]}
              >
                <Ionicons name="camera" size={12} color="#FFFFFF" />
              </View>
            </TouchableOpacity>
            <View style={styles.profileInfo}>
              <Text style={[styles.profileName, { color: colors.text }]}>
                {profile.name || profile.username}
              </Text>
              <Text style={[styles.profileRole, { color: colors.textMuted }]}>
                {getRoleDisplay(profile.role)}
              </Text>
              {profile.farm_name && (
                <View
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    marginTop: 2,
                  }}
                >
                  <Ionicons
                    name="business-outline"
                    size={14}
                    color={colors.textMuted}
                    style={{ marginRight: 4 }}
                  />
                  <Text
                    style={[styles.profileFarm, { color: colors.textMuted }]}
                  >
                    {profile.farm_name}
                  </Text>
                </View>
              )}
              {profile.email && (
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
              )}
              {profile.phone && (
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
              )}
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  marginTop: 4,
                }}
              >
                <View
                  style={[
                    styles.statusBadge,
                    {
                      backgroundColor:
                        profile.status === "active"
                          ? "#d4edda"
                          : profile.status === "pending"
                            ? "#fff3cd"
                            : "#f8d7da",
                    },
                  ]}
                >
                  <Text
                    style={[
                      styles.statusText,
                      {
                        color:
                          profile.status === "active"
                            ? "#155724"
                            : profile.status === "pending"
                              ? "#856404"
                              : "#721c24",
                      },
                    ]}
                  >
                    {profile.status?.toUpperCase() || "ACTIVE"}
                  </Text>
                </View>
                <Text
                  style={[
                    styles.profileSource,
                    { color: colors.textMuted, marginLeft: 8 },
                  ]}
                >
                  {profile.source === "user_accounts"
                    ? "📱 App User"
                    : profile.source === "admins"
                      ? "👑 Admin"
                      : profile.source === "users"
                        ? "💻 System User"
                        : "👤 User"}
                </Text>
              </View>
            </View>
          </View>
        ) : (
          editedProfile && (
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
              <TouchableOpacity
                onPress={handlePickImage}
                style={styles.avatarContainer}
              >
                {tempAvatar || editedProfile.avatar ? (
                  <Image
                    source={{
                      uri: tempAvatar || editedProfile.avatar || undefined,
                    }}
                    style={styles.avatarImage}
                  />
                ) : (
                  <View
                    style={[styles.avatar, { backgroundColor: colors.primary }]}
                  >
                    <Text style={[styles.avatarText, { color: "#FFFFFF" }]}>
                      {(editedProfile.name || editedProfile.username || "U")
                        .charAt(0)
                        .toUpperCase()}
                    </Text>
                  </View>
                )}
                <View
                  style={[
                    styles.avatarBadge,
                    { backgroundColor: colors.primary },
                  ]}
                >
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
                  placeholder="Farm Name"
                  placeholderTextColor={colors.textMuted}
                  value={editedProfile.farm_name}
                  onChangeText={(text) =>
                    setEditedProfile({ ...editedProfile, farm_name: text })
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
                  placeholder="Role"
                  placeholderTextColor={colors.textMuted}
                  value={editedProfile.role}
                  onChangeText={(text) =>
                    setEditedProfile({ ...editedProfile, role: text })
                  }
                />
                <View style={styles.editActions}>
                  <TouchableOpacity
                    style={[
                      styles.cancelButton,
                      { borderColor: colors.border },
                    ]}
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
          )
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

      {/* Logout Section */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <Ionicons
              name="log-out-outline"
              size={18}
              color={colors.danger || "#FF4444"}
              style={{ marginRight: 8 }}
            />
            <Text
              style={[
                styles.sectionTitle,
                { color: colors.danger || "#FF4444" },
              ]}
            >
              Account
            </Text>
          </View>
        </View>

        <TouchableOpacity
          style={[
            styles.logoutCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
            },
          ]}
          onPress={handleLogout}
          activeOpacity={0.7}
          disabled={isLoggingOut}
        >
          <View style={styles.logoutContent}>
            <View style={styles.logoutIconContainer}>
              <Ionicons
                name="log-out-outline"
                size={24}
                color={colors.danger || "#FF4444"}
              />
            </View>
            <View style={styles.logoutTextContainer}>
              <Text
                style={[
                  styles.logoutTitle,
                  { color: colors.danger || "#FF4444" },
                ]}
              >
                {isLoggingOut ? "Logging out..." : "Logout"}
              </Text>
              <Text style={[styles.logoutDesc, { color: colors.textMuted }]}>
                {isLoggingOut
                  ? "Please wait..."
                  : `Sign out from ${profile.username || "your"} account`}
              </Text>
            </View>
            {!isLoggingOut && (
              <Ionicons
                name="chevron-forward-outline"
                size={20}
                color={colors.textMuted}
              />
            )}
            {isLoggingOut && (
              <ActivityIndicator
                size="small"
                color={colors.danger || "#FF4444"}
              />
            )}
          </View>
        </TouchableOpacity>
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
    padding: 20,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
  },
  errorText: {
    fontSize: 16,
    marginBottom: 16,
  },
  retryButton: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  retryButtonText: {
    color: "#FFFFFF",
    fontSize: 16,
    fontWeight: "600",
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
  profileFarm: {
    fontSize: 13,
    marginLeft: 2,
  },
  profileEmail: {
    fontSize: 13,
    marginLeft: 2,
  },
  profilePhone: {
    fontSize: 13,
    marginLeft: 2,
  },
  profileSource: {
    fontSize: 11,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  statusText: {
    fontSize: 10,
    fontWeight: "700",
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
  logoutCard: {
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  logoutContent: {
    flexDirection: "row",
    alignItems: "center",
  },
  logoutIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: "rgba(255, 68, 68, 0.1)",
    justifyContent: "center",
    alignItems: "center",
    marginRight: 12,
  },
  logoutTextContainer: {
    flex: 1,
  },
  logoutTitle: {
    fontSize: 16,
    fontWeight: "700",
  },
  logoutDesc: {
    fontSize: 13,
    marginTop: 1,
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
