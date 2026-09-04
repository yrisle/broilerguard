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
import { notifications } from "../../api/endpoints";
import { useTheme } from "../../hooks/useTheme";

function NotificationsScreen() {
  const { colors } = useTheme();
  const [notificationsList, setNotificationsList] = useState<any[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchNotifications = async () => {
    try {
      console.log("📥 Fetching notifications...");
      const response = await notifications.getAll(50);
      console.log("📥 Response:", response.data);

      if (response.data.success) {
        setNotificationsList(response.data.data.notifications || []);
        setUnreadCount(response.data.data.unread || 0);
      } else {
        console.log("❌ API returned success: false", response.data.message);
        // Use sample data if API fails
        setNotificationsList([]);
        setUnreadCount(0);
      }
    } catch (error: any) {
      console.error("❌ Error fetching notifications:", error.message);
      setNotificationsList([]);
      setUnreadCount(0);
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

  // ✅ Fix: Mark as read and remove from list immediately
  const markAsRead = async (id: string) => {
    try {
      console.log("📤 Marking notification as read:", id);
      await notifications.markRead(id);

      // ✅ Update local state immediately - remove the notification
      setNotificationsList((prev) => prev.filter((item) => item.id !== id));
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (error) {
      console.error("❌ Failed to mark as read:", error);
      Alert.alert("Error", "Failed to mark as read");
    }
  };

  const markAllRead = async () => {
    try {
      console.log("📤 Marking all as read...");
      await notifications.markAllRead();

      // ✅ Update local state immediately
      setNotificationsList((prev) =>
        prev.map((item) => ({ ...item, read: 1 })),
      );
      setUnreadCount(0);
    } catch (error) {
      console.error("❌ Failed to mark all as read:", error);
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
            console.log("📤 Deleting notification:", id);
            await notifications.delete(id);

            // ✅ Update local state immediately
            setNotificationsList((prev) =>
              prev.filter((item) => item.id !== id),
            );
            if (unreadCount > 0) {
              setUnreadCount((prev) => Math.max(0, prev - 1));
            }
          } catch (error) {
            console.error("❌ Failed to delete:", error);
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
          {unreadCount > 0 && (
            <View
              style={[styles.countBadge, { backgroundColor: colors.danger }]}
            >
              <Text style={styles.countBadgeText}>{unreadCount}</Text>
            </View>
          )}
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
          {unreadCount} unread notification{unreadCount > 1 ? "s" : ""}
        </Text>
      )}

      {notificationsList.length === 0 ? (
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
        notificationsList.map((item: any, index: number) => (
          <TouchableOpacity
            key={index}
            style={[
              styles.notifCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
              },
              // ✅ Highlight unread notifications with left border
              item.read === 0 && [
                styles.notifUnread,
                { borderLeftColor: colors.primary },
              ],
            ]}
            onPress={() => {
              // ✅ Mark as read when tapped
              if (item.read === 0) {
                markAsRead(item.id);
              }
            }}
            activeOpacity={0.7}
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
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  justifyContent: "space-between",
                }}
              >
                <Text style={[styles.notifTitle, { color: colors.text }]}>
                  {item.title}
                </Text>
                {item.read === 0 && (
                  <View
                    style={[
                      styles.unreadDot,
                      { backgroundColor: colors.primary },
                    ]}
                  />
                )}
              </View>
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
    paddingBottom: 8,
  },
  title: {
    fontSize: 24,
    fontWeight: "800",
  },
  countBadge: {
    borderRadius: 12,
    paddingHorizontal: 8,
    paddingVertical: 2,
    marginLeft: 8,
    minWidth: 24,
    alignItems: "center",
  },
  countBadgeText: {
    color: "#FFFFFF",
    fontSize: 12,
    fontWeight: "700",
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
    paddingTop: 4,
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
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
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
