// src/api/client.ts
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";
import { Platform } from "react-native";

// ============================================
// 🔧 PALITAN ITO - ILAGAY ANG NGROK URL MO
// ============================================
const NGROK_URL = "https://deltoidal-nonregeneratively-florance.ngrok-free.dev"; // ← PALITAN ITO

const getBaseUrl = () => {
  // For Android Emulator
  if (Platform.OS === "android") {
    // Pwedeng gamitin ang 10.0.2.2 or ngrok
    return `${NGROK_URL}/broilerguard/api`;
  }

  // For iOS Simulator
  if (Platform.OS === "ios") {
    return `${NGROK_URL}/broilerguard/api`;
  }

  // For Web
  if (Platform.OS === "web") {
    return `http://localhost/broilerguard/api`;
  }

  // For Physical Device
  return `${NGROK_URL}/broilerguard/api`;
};

export const API_BASE_URL = getBaseUrl();

console.log("========================================");
console.log("🔗 API Base URL:", API_BASE_URL);
console.log("========================================");

// ✅ FIX: Helper function to add .php extension to endpoints
const addPhpExtension = (url: string): string => {
  // Skip if URL already has .php or has query string with .php
  if (url.includes(".php")) {
    return url;
  }

  // Skip if URL is a custom route (has / in it, like /dashboard/stats)
  // But we want to add .php to all endpoints
  const parts = url.split("?");
  let path = parts[0];
  const query = parts[1] || "";

  // Remove trailing slash
  path = path.replace(/\/$/, "");

  // If path is empty or just '/', return as is
  if (!path || path === "/") {
    return url;
  }

  // Add .php extension
  const newPath = path + ".php";
  return query ? `${newPath}?${query}` : newPath;
};

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

// ✅ FIX: Request interceptor - modify URL to add .php
api.interceptors.request.use(
  async (config) => {
    try {
      // ✅ Add .php extension to the URL
      if (config.url) {
        const originalUrl = config.url;
        config.url = addPhpExtension(originalUrl);
        console.log(`🔄 URL transformed: ${originalUrl} → ${config.url}`);
      }

      const token = await AsyncStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {
      console.log("Token error:", error);
    }
    console.log("📤 Request:", config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.log("📤 Request Error:", error);
    return Promise.reject(error);
  },
);

// Response interceptor - handle errors
api.interceptors.response.use(
  (response) => {
    console.log("📥 Response:", response.status, response.config.url);
    return response;
  },
  async (error) => {
    console.log("❌ Response Error:", error.message);
    console.log("❌ URL:", error.config?.url);
    console.log("❌ BaseURL:", error.config?.baseURL);
    console.log("❌ Full URL:", error.config?.baseURL + error.config?.url);

    if (error.response?.status === 401) {
      try {
        await AsyncStorage.removeItem("auth_token");
      } catch {}
    }

    return Promise.reject(error);
  },
);

// ✅ FIX: Export a wrapper that adds .php extension
export const apiWithPhp = {
  get: (url: string, config?: any) => api.get(addPhpExtension(url), config),
  post: (url: string, data?: any, config?: any) =>
    api.post(addPhpExtension(url), data, config),
  put: (url: string, data?: any, config?: any) =>
    api.put(addPhpExtension(url), data, config),
  delete: (url: string, config?: any) =>
    api.delete(addPhpExtension(url), config),
  patch: (url: string, data?: any, config?: any) =>
    api.patch(addPhpExtension(url), data, config),
};

// ✅ Default export with .php support
export default apiWithPhp;
