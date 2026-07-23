// src/screens/Settings/NotificationsScreen.tsx
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
        return "✅";
      case "warning":
        return "⚠️";
      case "danger":
        return "❌";
      default:
        return "ℹ️";
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
        <Text style={[styles.title, { color: colors.text }]}>
          🔔 Notifications
        </Text>
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
          <Text style={styles.emptyIcon}>🔕</Text>
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
              <Text style={styles.notifIconText}>{getTypeIcon(item.type)}</Text>
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
              <Text
                style={[styles.notifDeleteText, { color: colors.textMuted }]}
              >
                ✕
              </Text>
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
  notifIconText: {
    fontSize: 20,
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
  notifDeleteText: {
    fontSize: 16,
  },
  emptyState: {
    alignItems: "center",
    paddingVertical: 60,
  },
  emptyIcon: {
    fontSize: 48,
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
