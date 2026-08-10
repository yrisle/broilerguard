// src/api/client.ts
import axios from "axios";
import { Platform } from "react-native";
import AsyncStorage from '@react-native-async-storage/async-storage';

// ✅ USE YOUR PC'S IP ADDRESS (from ipconfig)
const SERVER_IP = "192.168.0.154"; // ← CHANGE THIS

const getBaseUrl = () => {
  // For Android Emulator - use PC's IP instead of 10.0.2.2
  if (Platform.OS === "android") {
    return `http://${SERVER_IP}/broilerguard/api`; // ← USE IP
  }
  
  // For iOS Simulator
  if (Platform.OS === "ios") {
    return `http://localhost/broilerguard/api`;
  }
  
  // For Web
  if (Platform.OS === "web") {
    return `http://localhost/broilerguard/api`;
  }
  
  // For physical device
  return `http://${SERVER_IP}/broilerguard/api`;
};

export const API_BASE_URL = getBaseUrl();

console.log("🔗 API Base URL:", API_BASE_URL);

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use(
  async (config) => {
    try {
      const token = await AsyncStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {}
    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    console.log("❌ API Error:", error.message);
    return Promise.reject(error);
  }
);

export default api;