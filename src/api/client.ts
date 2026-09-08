// src/api/client.ts
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";
import { Platform } from "react-native";

// ============================================
// 🔧 PALITAN ITO - ILAGAY ANG MGA IP ADDRESS
// ============================================
const ESP32_IP = "192.168.1.15"; // ESP32 IP
const SERVER_IP = "192.168.8.154"; // ← PALITAN! IP ng PC/Raspberry Pi na may database

const getBaseUrl = () => {
  if (Platform.OS === "android") {
    return `http://${SERVER_IP}/broilerguard/api`;
  }
  if (Platform.OS === "ios") {
    return `http://${SERVER_IP}/broilerguard/api`;
  }
  if (Platform.OS === "web") {
    return `http://localhost/broilerguard/api`;
  }
  return `http://${SERVER_IP}/broilerguard/api`;
};

export const API_BASE_URL = getBaseUrl();
export const ESP32_BASE_URL = `http://${ESP32_IP}`;

console.log("========================================");
console.log("🔗 Database API URL:", API_BASE_URL);
console.log("🔗 ESP32 API URL:", ESP32_BASE_URL);
console.log("========================================");

// CREATE AXIOS INSTANCE for Database
const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// REQUEST INTERCEPTOR - Add auth token
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
    console.log("📤 DB Request:", config.method?.toUpperCase(), config.url);
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
    console.log("📥 DB Response:", response.status, response.config.url);
    return response;
  },
  async (error) => {
    console.log("❌ DB Error:", error.message);
    console.log("❌ URL:", error.config?.url);

    if (error.response?.status === 401) {
      try {
        await AsyncStorage.removeItem("auth_token");
        // Navigate to login
      } catch {}
    }
    return Promise.reject(error);
  },
);

// CREATE AXIOS INSTANCE for ESP32
const esp32Api = axios.create({
  baseURL: ESP32_BASE_URL,
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

esp32Api.interceptors.request.use(
  async (config) => {
    console.log("📤 ESP32 Request:", config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.log("📤 ESP32 Request Error:", error);
    return Promise.reject(error);
  },
);

esp32Api.interceptors.response.use(
  (response) => {
    console.log("📥 ESP32 Response:", response.status, response.config.url);
    console.log("📥 Data:", response.data);
    return response;
  },
  async (error) => {
    console.log("❌ ESP32 Error:", error.message);
    console.log("❌ URL:", error.config?.url);
    return Promise.reject(error);
  },
);

// Export both
export default api;
export { esp32Api };

