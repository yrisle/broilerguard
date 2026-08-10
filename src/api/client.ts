// src/api/client.ts
import axios from "axios";
import { Platform } from "react-native";
import AsyncStorage from '@react-native-async-storage/async-storage';

// ============================================
// 🔧 PALITAN ITO - ILAGAY ANG NGROK URL MO
// ============================================
const NGROK_URL = "https://deltoidal-nonregeneratively-florance.ngrok-free.dev"; // ← PALITAN ITO

const getBaseUrl = () => {
  // For Android Emulator
  if (Platform.OS === "android") {
    // Pwedeng gamitin ang 10.0.2.2 or ngrok
    return `${NGROK_URL}/broilerguard/api`;
    // return `http://10.0.2.2/broilerguard/api`;
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

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Request interceptor - add token
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
    console.log("📤 Request:", config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.log("📤 Request Error:", error);
    return Promise.reject(error);
  }
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
    
    if (error.response?.status === 401) {
      try {
        await AsyncStorage.removeItem("auth_token");
      } catch {}
    }
    
    return Promise.reject(error);
  }
);

export default api;