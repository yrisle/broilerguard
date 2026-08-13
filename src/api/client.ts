// src/api/client.ts
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";
import { Platform } from "react-native";

// ============================================
// 🔧 PALITAN ITO - ILAGAY ANG NGROK URL MO
// ============================================
const NGROK_URL = "https://deltoidal-nonregeneratively-florance.ngrok-free.dev";

const getBaseUrl = () => {
  if (Platform.OS === "android") {
    return `${NGROK_URL}/broilerguard/api`;
  }
  if (Platform.OS === "ios") {
    return `${NGROK_URL}/broilerguard/api`;
  }
  if (Platform.OS === "web") {
    return `http://localhost/broilerguard/api`;
  }
  return `${NGROK_URL}/broilerguard/api`;
};

export const API_BASE_URL = getBaseUrl();

console.log("========================================");
console.log("🔗 API Base URL:", API_BASE_URL);
console.log("========================================");

// ✅ CREATE AXIOS INSTANCE
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

// ✅ SIMPLE INTERCEPTOR - just add token
api.interceptors.request.use(
  async (config) => {
    try {
      const token = await AsyncStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {
      console.log("Token error:", error);
    }

    // ✅ SIMPLE FIX: Add .php to auth endpoints only
    if (config.url && !config.url.includes(".php")) {
      // Only add .php to auth and automation endpoints
      const needsPhp = ["/auth/", "/automation/", "/test"];
      let shouldAdd = false;

      for (const prefix of needsPhp) {
        if (config.url.startsWith(prefix)) {
          shouldAdd = true;
          break;
        }
      }

      // Also add for simple endpoints without slashes
      if (!config.url.includes("/") && !config.url.includes(".")) {
        shouldAdd = true;
      }

      if (shouldAdd) {
        config.url = config.url + ".php";
        console.log("🔄 Added .php:", config.url);
      }
    }

    console.log("📤 Request:", config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.log("📤 Request Error:", error);
    return Promise.reject(error);
  },
);

// ✅ RESPONSE INTERCEPTOR
api.interceptors.response.use(
  (response) => {
    console.log("📥 Response:", response.status, response.config.url);
    return response;
  },
  async (error) => {
    console.log("❌ Response Error:", error.message);
    console.log("❌ URL:", error.config?.url);
    console.log("❌ Full URL:", error.config?.baseURL + error.config?.url);

    if (error.response?.status === 401) {
      try {
        await AsyncStorage.removeItem("auth_token");
      } catch {}
    }

    return Promise.reject(error);
  },
);

// ✅ EXPORT DEFAULT
export default api;
