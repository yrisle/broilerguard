// src/api/client.ts
import axios from "axios";
import { Platform } from "react-native";

// ============================================
// 🔧 PALITAN ITO - ILAGAY ANG IP NG ESP32 MO
// ============================================
const ESP32_IP = "192.168.1.15"; // ← PALITAN! Gamitin ang IP ng ESP32 mo

const getBaseUrl = () => {
  if (Platform.OS === "android") {
    return `http://${ESP32_IP}`;
  }
  if (Platform.OS === "ios") {
    return `http://${ESP32_IP}`;
  }
  if (Platform.OS === "web") {
    return `http://localhost`;
  }
  return `http://${ESP32_IP}`;
};

export const API_BASE_URL = getBaseUrl();

console.log("========================================");
console.log("🔗 ESP32 API URL:", API_BASE_URL);
console.log("========================================");

// CREATE AXIOS INSTANCE
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000, // 10 seconds for ESP32
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// REQUEST INTERCEPTOR
api.interceptors.request.use(
  async (config) => {
    console.log("📤 ESP32 Request:", config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.log("📤 Request Error:", error);
    return Promise.reject(error);
  },
);

// RESPONSE INTERCEPTOR
api.interceptors.response.use(
  (response) => {
    console.log("📥 ESP32 Response:", response.status, response.config.url);
    console.log("📥 Data:", response.data);
    return response;
  },
  async (error) => {
    console.log("❌ ESP32 Error:", error.message);
    console.log("❌ URL:", error.config?.url);
    console.log("❌ Full URL:", error.config?.baseURL + error.config?.url);
    return Promise.reject(error);
  },
);

export default api;
