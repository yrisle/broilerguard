// src/api/client.ts
import axios from "axios";
import Constants from "expo-constants";
import { Platform } from "react-native";

const envBaseUrl = (process.env.EXPO_PUBLIC_API_BASE_URL || "").trim();
const shouldLogNetworkErrors =
  (process.env.EXPO_PUBLIC_API_DEBUG || "").toLowerCase() === "true";

// SIMPLIFIED: Get Expo local host
const getExpoLocalHost = (): string | undefined => {
  try {
    // Get debugger host from Constants
    const debuggerHost = Constants.expoConfig?.hostUri;
    if (debuggerHost) {
      const host = debuggerHost.split(":")[0];
      return host?.trim() || undefined;
    }
    return undefined;
  } catch (error) {
    return undefined;
  }
};

// SIMPLIFIED: Get base URL
const getFallbackBaseUrl = () => {
  // If env URL is set, use it
  if (envBaseUrl) {
    return envBaseUrl;
  }

  // CHANGE THIS TO YOUR PHP SERVER URL
  const serverUrl = "http://192.168.1.100"; // Replace with your server IP

  // For Android Emulator
  if (Platform.OS === "android") {
    // Use 10.0.2.2 for Android emulator to access host machine
    return "http://10.0.2.2/broilerguard/api";
  }

  // For iOS Simulator
  if (Platform.OS === "ios") {
    return "http://localhost/broilerguard/api";
  }

  // For Web
  if (Platform.OS === "web") {
    const isBrowser = typeof window !== "undefined";
    const hostname = isBrowser ? window.location.hostname : "localhost";
    return `http://${hostname}/broilerguard/api`;
  }

  // Fallback for physical device - use your server IP
  return `${serverUrl}/broilerguard/api`;
};

export const API_BASE_URL = getFallbackBaseUrl().replace(/\/+$/, "");

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Request interceptor to add token
api.interceptors.request.use(
  async (config) => {
    try {
      // Use AsyncStorage for React Native
      const AsyncStorage = require('@react-native-async-storage/async-storage').default;
      const token = await AsyncStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch {
      // ignore storage access issues
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      try {
        const AsyncStorage = require('@react-native-async-storage/async-storage').default;
        await AsyncStorage.removeItem("auth_token");
      } catch {
        // ignore storage access issues
      }
    }

    if (!error.response && shouldLogNetworkErrors) {
      console.warn("API network error", {
        baseURL: API_BASE_URL,
        message: error.message,
      });
    }

    return Promise.reject(error);
  },
);

export default api;