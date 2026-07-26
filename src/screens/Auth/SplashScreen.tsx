// src/screens/Auth/SplashScreen.tsx
import { FontAwesome5 } from "@expo/vector-icons";
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
      <View style={styles.logoContainer}>
        <FontAwesome5 name="drumstick-bite" size={80} color={colors.primary} />
      </View>
      <Text style={[styles.title, { color: colors.text }]}>BroilerGuard</Text>
      <Text style={[styles.subtitle, { color: colors.textMuted }]}>
        Smart Poultry Management
      </Text>
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
    padding: 20,
  },
  logoContainer: {
    marginBottom: 8,
  },
  title: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 16,
  },
  subtitle: {
    fontSize: 14,
    marginTop: 4,
    fontWeight: "500",
  },
  loader: {
    marginTop: 40,
  },
});

export default SplashScreen;
