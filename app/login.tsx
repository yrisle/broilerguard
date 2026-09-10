// app/login.tsx
import { useRouter } from "expo-router";
import React, { useState } from "react";
import {
  ActivityIndicator,
  Alert,
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
  const { login, isLoading } = useAuth();
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");

  const handleLogin = async () => {
    if (!username.trim() || !password.trim()) {
      Alert.alert("Error", "Please enter username and password");
      return;
    }

    try {
      await login(username.trim(), password.trim());
    } catch (error: any) {
      Alert.alert("Login Failed", error.message || "Invalid credentials");
    }
  };

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <Text style={styles.logo}>🐔</Text>
        <Text style={[styles.title, { color: colors.text }]}>BroilerGuard</Text>
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

        {/* ✅ Register link */}
        <View style={styles.footerRow}>
          <Text style={[styles.footerText, { color: colors.textMuted }]}>
            Don't have an account?{" "}
          </Text>
          <TouchableOpacity onPress={() => router.push("/register" as any)}>
            <Text style={[styles.footerLink, { color: colors.primary }]}>
              Register
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
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
  footerRow: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    marginTop: 16,
  },
  footerText: {
    fontSize: 13,
  },
  footerLink: {
    fontSize: 13,
    fontWeight: "700",
  },
});
