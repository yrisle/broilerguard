// src/screens/Auth/SplashScreen.tsx
import React, { useEffect } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../../context/AuthContext";

const SplashScreen = ({ navigation }: any) => {
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
    <View style={styles.container}>
      <Text style={styles.logo}>🐔</Text>
      <Text style={styles.title}>BroilerGuard</Text>
      <ActivityIndicator size="large" color="#FFD62E" style={styles.loader} />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#FFFCF2",
    justifyContent: "center",
    alignItems: "center",
  },
  logo: {
    fontSize: 80,
  },
  title: {
    fontSize: 32,
    fontWeight: "800",
    color: "#3E2C1C",
    marginTop: 16,
  },
  loader: {
    marginTop: 40,
  },
});

export default SplashScreen;
