// app/index.tsx
import { Redirect } from "expo-router";
import React from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../src/context/AuthContext";

function RootLayout() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <View style={styles.container}>
        <Text style={styles.logo}>🐔</Text>
        <Text style={styles.title}>BroilerGuard</Text>
        <ActivityIndicator size="large" color="#FFD62E" />
      </View>
    );
  }

  if (isAuthenticated) {
    return <Redirect href="/(tabs)/home" />;
  } else {
    return <Redirect href="/login" />;
  }
}

export default function App() {
  return <RootLayout />;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#FFFCF2",
    justifyContent: "center",
    alignItems: "center",
  },
  logo: {
    fontSize: 60,
  },
  title: {
    fontSize: 32,
    fontWeight: "800",
    color: "#3E2C1C",
    marginTop: 12,
  },
});
