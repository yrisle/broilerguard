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

// ✅ Helper function to add .php extension
const addPhpExtension = (url: string): string => {
  // Skip if URL already has .php
  if (url.includes(".php")) {
    return url;
  }

  // Skip if URL has query string with .php
  const parts = url.split("?");
  let path = parts[0];
  const query = parts[1] || "";

  // Remove trailing slash
  path = path.replace(/\/$/, "");

  // Skip if path is empty
  if (!path || path === "/") {
    return url;
  }

  // Add .php extension
  const newPath = path + ".php";
  return query ? `${newPath}?${query}` : newPath;
};

// ✅ CREATE AXIOS INSTANCE
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// ✅ REQUEST INTERCEPTOR
api.interceptors.request.use(
  async (config) => {
    try {
      // Add auth token
      const token = await AsyncStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {
      console.log("Token error:", error);
    }

    // ✅ ADD .php EXTENSION
    if (config.url) {
      const originalUrl = config.url;
      config.url = addPhpExtension(originalUrl);
      console.log("🔄 URL:", originalUrl, "→", config.url);
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
