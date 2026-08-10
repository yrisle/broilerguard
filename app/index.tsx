// app/index.tsx
import { Redirect } from "expo-router";
import React, { useEffect } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../src/context/AuthContext";
import { useTheme } from "../src/hooks/useTheme";

function RootLayout() {
  const { colors } = useTheme();
  const { isAuthenticated, isLoading, validateToken } = useAuth();

  useEffect(() => {
    validateToken();
  }, []);

  if (isLoading) {
    return (
      <View style={[styles.container, { backgroundColor: colors.background }]}>
        <Text style={styles.logo}>🐔</Text>
        <Text style={[styles.title, { color: colors.text }]}>BroilerGuard</Text>
        <Text style={[styles.subtitle, { color: colors.textMuted }]}>
          Smart Poultry Management
        </Text>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (!isAuthenticated) {
    return <Redirect href="/login" />;
  }

  return <Redirect href="/(tabs)/home" />;
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
});