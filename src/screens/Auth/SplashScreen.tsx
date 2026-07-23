// src/screens/Auth/SplashScreen.tsx
import React, { useEffect } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../../context/AuthContext";
import { useTheme } from "../../hooks/useTheme";

const SplashScreen = ({ navigation }: any) => {
  const { colors } = useTheme();
  const { isAuthenticated, isLoading } = useAuth();

  useEffect(() => {
    if (!isLoading) {
      if (isAuthenticated) {
        navigation.replace("Main");
      } else {
        navigation.replace("Login");
      }
    }
  }, [isLoading, isAuthenticated, navigation]);

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <Text style={styles.logo}>🐔</Text>
      <Text style={[styles.title, { color: colors.text }]}>BroilerGuard</Text>
      <ActivityIndicator
        size="large"
        color={colors.primary}
        style={styles.loader}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  logo: {
    fontSize: 80,
  },
  title: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 16,
  },
  loader: {
    marginTop: 40,
  },
});

export default SplashScreen;
