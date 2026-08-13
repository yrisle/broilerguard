// app/login.tsx
import AsyncStorage from "@react-native-async-storage/async-storage";
import React, { useState } from "react";
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
import { useTheme } from "../src/hooks/useTheme";

export default function LoginScreen({ navigation }: any) {
  const { colors } = useTheme();
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("broilerguard2025");
  const [isLoading, setIsLoading] = useState(false);
  const [debugInfo, setDebugInfo] = useState("");

  const API_URL =
    "https://deltoidal-nonregeneratively-florance.ngrok-free.dev/broilerguard/api";

  const handleLogin = async () => {
    if (!username.trim() || !password.trim()) {
      Alert.alert("Error", "Please enter username and password");
      return;
    }

    setIsLoading(true);
    setDebugInfo("🔄 Starting login process...");

    try {
      const url = `${API_URL}/auth/login.php`;
      console.log("========================================");
      console.log("🔐 LOGIN ATTEMPT");
      console.log("📍 URL:", url);
      console.log("👤 Username:", username);
      console.log("🔑 Password:", password);
      console.log("========================================");

      setDebugInfo(`📤 POST to: ${url}`);

      // ✅ TRY 1: POST with JSON
      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          username: username.trim(),
          password: password.trim(),
        }),
      });

      console.log("📥 Response status:", response.status);
      setDebugInfo(`📥 Status: ${response.status}`);

      // Get raw response
      const responseText = await response.text();
      console.log("📥 Raw response:", responseText);
      setDebugInfo(`📥 Raw: ${responseText.substring(0, 100)}...`);

      // Parse JSON
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e: any) {
        console.error("❌ JSON Parse error:", e);
        setDebugInfo(`❌ Parse error: ${e.message}`);

        // Show raw response in alert for debugging
        Alert.alert(
          "Invalid Response",
          `Server returned:\n${responseText.substring(0, 200)}`,
        );
        setIsLoading(false);
        return;
      }

      console.log("📥 Parsed data:", data);
      setDebugInfo(`📥 Data: ${JSON.stringify(data).substring(0, 100)}`);

      if (data.success) {
        // Save token
        await AsyncStorage.setItem("auth_token", data.token);
        if (data.user) {
          await AsyncStorage.setItem("user", JSON.stringify(data.user));
        }

        setDebugInfo("✅ Login successful!");
        Alert.alert("Success", "Logged in successfully!");
        navigation.replace("Main");
      } else {
        setDebugInfo(`❌ Login failed: ${data.message || "Unknown error"}`);
        Alert.alert("Login Failed", data.message || "Invalid credentials");
      }
    } catch (error: any) {
      console.error("❌ Login error:", error);
      setDebugInfo(`❌ Error: ${error.message}`);

      // ✅ TRY 2: GET method as fallback
      try {
        setDebugInfo("🔄 Trying GET method...");
        const getUrl = `${API_URL}/auth/login.php?username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`;
        console.log("📍 GET URL:", getUrl);

        const getResponse = await fetch(getUrl);
        const getText = await getResponse.text();
        console.log("📥 GET raw:", getText);
        setDebugInfo(`📥 GET: ${getText.substring(0, 100)}...`);

        const getData = JSON.parse(getText);
        console.log("📥 GET parsed:", getData);

        if (getData.success) {
          await AsyncStorage.setItem("auth_token", getData.token);
          if (getData.user) {
            await AsyncStorage.setItem("user", JSON.stringify(getData.user));
          }
          setDebugInfo("✅ GET login successful!");
          Alert.alert("Success", "Logged in successfully!");
          navigation.replace("Main");
          return;
        }
      } catch (getError: any) {
        console.error("❌ GET also failed:", getError);
        setDebugInfo(`❌ GET failed: ${getError.message}`);
      }

      Alert.alert(
        "Connection Error",
        `Failed to connect to server.\n\nError: ${error.message}\n\nPlease check:\n1. Internet connection\n2. Ngrok is running\n3. Backend is started`,
      );
    } finally {
      setIsLoading(false);
    }
  };

  const testConnection = async () => {
    try {
      setDebugInfo("🔄 Testing connection...");
      const url = `${API_URL}/test-connection.php`;
      console.log("📍 Test URL:", url);

      const response = await fetch(url);
      const text = await response.text();
      console.log("📥 Test response:", text);
      setDebugInfo(`✅ Connection OK`);

      Alert.alert(
        "Connection Test",
        `✅ Server is reachable!\n\nResponse:\n${text.substring(0, 200)}`,
      );
    } catch (error: any) {
      console.error("❌ Test failed:", error);
      setDebugInfo(`❌ Test failed: ${error.message}`);
      Alert.alert("Connection Failed", error.message);
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

          <TouchableOpacity
            style={[styles.testBtn, { borderColor: colors.border }]}
            onPress={testConnection}
            disabled={isLoading}
          >
            <Text style={[styles.testBtnText, { color: colors.textMuted }]}>
              🔗 Test Connection
            </Text>
          </TouchableOpacity>

          {/* Debug Info */}
          <View style={[styles.debugBox, { backgroundColor: colors.card }]}>
            <Text style={[styles.debugLabel, { color: colors.textSecondary }]}>
              🔗 API URL:
            </Text>
            <Text style={[styles.debugText, { color: colors.textMuted }]}>
              {API_URL}
            </Text>
            {debugInfo ? (
              <Text
                style={[
                  styles.debugText,
                  { color: colors.primary, marginTop: 4 },
                ]}
              >
                {debugInfo}
              </Text>
            ) : null}
          </View>

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
  testBtn: {
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: "center",
    marginTop: 8,
    borderWidth: 1,
  },
  testBtnText: {
    fontSize: 14,
    fontWeight: "600",
  },
  debugBox: {
    marginTop: 16,
    padding: 12,
    borderRadius: 8,
  },
  debugLabel: {
    fontSize: 12,
    fontWeight: "600",
    marginBottom: 2,
  },
  debugText: {
    fontSize: 12,
  },
  footerText: {
    textAlign: "center",
    marginTop: 16,
    fontSize: 12,
  },
});
