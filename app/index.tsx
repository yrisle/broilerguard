// app/index.tsx
import { Redirect } from "expo-router";
import React, { useEffect, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../src/context/AuthContext";
import { useTheme } from "../src/hooks/useTheme";

function RootLayout() {
  const { colors } = useTheme();
  const { isAuthenticated, isLoading, validateToken } = useAuth();
  const [isChecking, setIsChecking] = useState(true);

  useEffect(() => {
    const checkAuth = async () => {
      try {
        await validateToken();
      } catch (error) {
        console.error("Auth check error:", error);
      } finally {
        setIsChecking(false);
      }
    };

    checkAuth();
  }, []);

  // Show loading while checking authentication
  if (isLoading || isChecking) {
    return (
      <View style={[styles.container, { backgroundColor: colors.background }]}>
        <Text style={styles.logo}>🐔</Text>
        <Text style={[styles.title, { color: colors.text }]}>BroilerGuard</Text>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Smart Poultry Management
        </Text>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={[styles.loadingText, { color: colors.textMuted }]}>
          Loading...
        </Text>
      </View>
    );
  }

  // ✅ If authenticated, go to home
  if (isAuthenticated) {
    return <Redirect href="/(tabs)/home" />;
  }

  // ✅ If not authenticated, go to login (this should always be the default)
  return <Redirect href="/login" />;
}

export default function App() {
  return <RootLayout />;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    padding: 20,
  },
  logo: {
    fontSize: 60,
    marginBottom: 8,
  },
  title: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 4,
  },
  subtitle: {
    fontSize: 14,
    marginTop: 4,
    marginBottom: 32,
  },
  loadingText: {
    fontSize: 14,
    marginTop: 16,
  },
});
