// src/api/client.ts
import axios from "axios";
import { Platform } from "react-native";
import AsyncStorage from '@react-native-async-storage/async-storage';

// Get the server IP - CHANGE THIS TO YOUR SERVER IP
const SERVER_IP = "192.168.0.154"; // Change to your actual server IP

// Base URL for API
const getBaseUrl = () => {
  // For Android Emulator
  if (Platform.OS === "android") {
    return `http://10.0.2.2/broilerguard/api`;
  }
  
  // For iOS Simulator
  if (Platform.OS === "ios") {
    return `http://localhost/broilerguard/api`;
  }
  
  // For Web
  if (Platform.OS === "web") {
    if (typeof window !== "undefined") {
      const hostname = window.location.hostname;
      if (hostname === "localhost" || hostname === "127.0.0.1") {
        return `http://localhost/broilerguard/api`;
      }
      return `http://${hostname}/broilerguard/api`;
    }
    return `http://localhost/broilerguard/api`;
  }
  
  // For physical device - use your server IP
  return `http://${SERVER_IP}/broilerguard/api`;
};

export const API_BASE_URL = getBaseUrl();

console.log("API Base URL:", API_BASE_URL);

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Request interceptor to add token
api.interceptors.request.use(
  async (config) => {
    try {
      const token = await AsyncStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch (error) {
      // Ignore storage errors
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      try {
        await AsyncStorage.removeItem("auth_token");
        // You might want to trigger a logout event here
      } catch {
        // Ignore storage errors
      }
    }
    return Promise.reject(error);
  }
);

export default api;