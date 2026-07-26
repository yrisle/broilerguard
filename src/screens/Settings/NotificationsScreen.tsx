// src/screens/Settings/NotificationsScreen.tsx
import Ionicons from "@expo/vector-icons/Ionicons";
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import api from "../../api/client";
import { useTheme } from "../../hooks/useTheme";

function NotificationsScreen() {
  const { colors } = useTheme();
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchNotifications = async () => {
    try {
      const response = await api.get("/notifications?limit=50");
      if (response.data.success) {
        setNotifications(response.data.data.notifications);
        setUnreadCount(response.data.data.unread);
      }
    } catch (error) {
      console.error("Error fetching notifications:", error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchNotifications();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchNotifications();
  };

  const markAsRead = async (id: string) => {
    try {
      await api.post("/notifications", { action: "mark_read", id });
      fetchNotifications();
    } catch (error) {
      Alert.alert("Error", "Failed to mark as read");
    }
  };

  const markAllRead = async () => {
    try {
      await api.post("/notifications", { action: "mark_all_read" });
      fetchNotifications();
    } catch (error) {
      Alert.alert("Error", "Failed to mark all as read");
    }
  };

  const deleteNotification = async (id: string) => {
    Alert.alert("Delete", "Delete this notification?", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: async () => {
          try {
            await api.post("/notifications", { action: "delete", id });
            fetchNotifications();
          } catch (error) {
            Alert.alert("Error", "Failed to delete");
          }
        },
      },
    ]);
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case "success":
        return <Ionicons name="checkmark-circle" size={20} color="#4D724D" />;
      case "warning":
        return <Ionicons name="warning" size={20} color="#C8A24A" />;
      case "danger":
        return <Ionicons name="close-circle" size={20} color="#A44A3F" />;
      default:
        return (
          <Ionicons
            name="information-circle"
            size={20}
            color={colors.textMuted}
          />
        );
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
            name="notifications"
            size={24}
            color={colors.text}
            style={{ marginRight: 12 }}
          />
          <Text style={[styles.title, { color: colors.text }]}>
            Notifications
          </Text>
        </View>
        {unreadCount > 0 && (
          <TouchableOpacity
            style={[
              styles.markAllBtn,
              { backgroundColor: colors.primaryLight },
            ]}
            onPress={markAllRead}
          >
            <Text style={[styles.markAllText, { color: colors.primaryDark }]}>
              Mark all read
            </Text>
          </TouchableOpacity>
        )}
      </View>

      {unreadCount > 0 && (
        <Text style={[styles.unreadText, { color: colors.textMuted }]}>
          {unreadCount} unread notifications
        </Text>
      )}

      {notifications.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons
            name="notifications-off-outline"
            size={64}
            color={colors.textMuted}
          />
          <Text style={[styles.emptyTitle, { color: colors.text }]}>
            No notifications
          </Text>
          <Text style={[styles.emptyDesc, { color: colors.textMuted }]}>
            You're all caught up!
          </Text>
        </View>
      ) : (
        notifications.map((item: any, index: number) => (
          <TouchableOpacity
            key={index}
            style={[
              styles.notifCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
              !item.read && [
                styles.notifUnread,
                { borderLeftColor: colors.primary },
              ],
            ]}
            onPress={() => markAsRead(item.id)}
          >
            <View
              style={[
                styles.notifIcon,
                { backgroundColor: colors.backgroundSecondary },
              ]}
            >
              {getTypeIcon(item.type)}
            </View>
            <View style={styles.notifContent}>
              <Text style={[styles.notifTitle, { color: colors.text }]}>
                {item.title}
              </Text>
              <Text style={[styles.notifMessage, { color: colors.textMuted }]}>
                {item.message}
              </Text>
              <Text style={[styles.notifTime, { color: colors.textMuted }]}>
                {new Date(item.timestamp).toLocaleString()}
              </Text>
            </View>
            <TouchableOpacity
              style={styles.notifDelete}
              onPress={() => deleteNotification(item.id)}
            >
              <Ionicons
                name="close-outline"
                size={20}
                color={colors.textMuted}
              />
            </TouchableOpacity>
          </TouchableOpacity>
        ))
      )}
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
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingTop: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: "800",
  },
  markAllBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  markAllText: {
    fontSize: 12,
    fontWeight: "600",
  },
  unreadText: {
    fontSize: 14,
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 12,
  },
  notifCard: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 8,
    borderWidth: 1,
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.02,
    shadowRadius: 2,
    elevation: 1,
  },
  notifUnread: {
    borderLeftWidth: 4,
  },
  notifIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: "center",
    alignItems: "center",
    marginRight: 12,
  },
  notifContent: {
    flex: 1,
  },
  notifTitle: {
    fontSize: 14,
    fontWeight: "600",
  },
  notifMessage: {
    fontSize: 13,
    marginTop: 2,
  },
  notifTime: {
    fontSize: 11,
    marginTop: 4,
  },
  notifDelete: {
    padding: 8,
    marginLeft: 4,
  },
  emptyState: {
    alignItems: "center",
    paddingVertical: 60,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: "600",
    marginTop: 12,
  },
  emptyDesc: {
    fontSize: 14,
    marginTop: 4,
  },
});

export default NotificationsScreen;
