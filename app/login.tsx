// app/login.tsx
import React, { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { useAuth } from "../src/context/AuthContext";
import { useTheme } from "../src/hooks/useTheme";

export default function LoginScreen() {
  const { colors } = useTheme();
  const { login, isLoading, isAuthenticated, clearAuth } = useAuth();
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("broilerguard2025");
  const [debugInfo, setDebugInfo] = useState("");
  const [isChecking, setIsChecking] = useState(true);

  // ✅ Clear any old tokens on mount
  useEffect(() => {
    const clearOldTokens = async () => {
      try {
        await clearAuth();
        console.log("✅ Cleared old tokens");
      } catch (error) {
        console.error("Error clearing tokens:", error);
      } finally {
        setIsChecking(false);
      }
    };
    clearOldTokens();
  }, []);

  // ✅ If still checking, show loading
  if (isChecking) {
    return (
      <View
        style={[
          styles.container,
          {
            backgroundColor: colors.background,
            justifyContent: "center",
            alignItems: "center",
          },
        ]}
      >
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={{ marginTop: 20, color: colors.text }}>Loading...</Text>
      </View>
    );
  }

  // ✅ If already authenticated, don't show login
  if (isAuthenticated) {
    return (
      <View
        style={[
          styles.container,
          {
            backgroundColor: colors.background,
            justifyContent: "center",
            alignItems: "center",
          },
        ]}
      >
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={{ marginTop: 20, color: colors.text }}>
          Already logged in...
        </Text>
      </View>
    );
  }

  const handleLogin = async () => {
    if (!username.trim() || !password.trim()) {
      Alert.alert("Error", "Please enter username and password");
      return;
    }

    setDebugInfo("🔄 Logging in...");

    try {
      await login(username.trim(), password.trim());
      setDebugInfo("✅ Login successful!");
    } catch (error: any) {
      console.error("Login error:", error);
      setDebugInfo(`❌ Error: ${error.message || "Unknown error"}`);

      // ✅ Clear any partial auth data
      await clearAuth();

      Alert.alert(
        "Login Failed",
        error.message || "Invalid credentials. Please try again.",
      );
    }
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.header}>
          <Text style={styles.logo}>🐔</Text>
          <Text style={[styles.title, { color: colors.text }]}>
            BroilerGuard
          </Text>
          <Text style={[styles.subtitle, { color: colors.textMuted }]}>
            Smart Poultry Management
          </Text>
        </View>

        <View style={styles.form}>
          <TextInput
            style={[
              styles.input,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                color: colors.text,
              },
            ]}
            placeholder="Username"
            placeholderTextColor={colors.textMuted}
            value={username}
            onChangeText={setUsername}
            autoCapitalize="none"
          />
          <TextInput
            style={[
              styles.input,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                color: colors.text,
              },
            ]}
            placeholder="Password"
            placeholderTextColor={colors.textMuted}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
          />

          <TouchableOpacity
            style={[
              styles.loginBtn,
              { backgroundColor: colors.primary },
              isLoading && styles.loginBtnDisabled,
            ]}
            onPress={handleLogin}
            disabled={isLoading}
          >
            {isLoading ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <Text style={styles.loginBtnText}>Login</Text>
            )}
          </TouchableOpacity>

          {debugInfo ? (
            <View style={[styles.debugBox, { backgroundColor: colors.card }]}>
              <Text style={[styles.debugText, { color: colors.primary }]}>
                {debugInfo}
              </Text>
            </View>
          ) : null}

          <Text style={[styles.footerText, { color: colors.textMuted }]}>
            Demo: admin / broilerguard2025
          </Text>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    padding: 24,
  },
  header: {
    alignItems: "center",
    marginBottom: 40,
  },
  logo: {
    fontSize: 56,
  },
  title: {
    fontSize: 32,
    fontWeight: "800",
    marginTop: 8,
  },
  subtitle: {
    fontSize: 14,
    marginTop: 4,
    textAlign: "center",
  },
  form: {
    width: "100%",
  },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    fontSize: 16,
    marginBottom: 12,
  },
  loginBtn: {
    borderRadius: 12,
    paddingVertical: 16,
    alignItems: "center",
    marginTop: 8,
  },
  loginBtnDisabled: {
    opacity: 0.6,
  },
  loginBtnText: {
    color: "#FFFFFF",
    fontSize: 16,
    fontWeight: "700",
  },
  debugBox: {
    marginTop: 16,
    padding: 12,
    borderRadius: 8,
  },
  debugText: {
    fontSize: 12,
    textAlign: "center",
  },
  footerText: {
    textAlign: "center",
    marginTop: 16,
    fontSize: 12,
  },
});
