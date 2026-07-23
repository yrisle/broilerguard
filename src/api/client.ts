// src/api/client.ts
import axios from "axios";
import Constants from "expo-constants";
import { Platform } from "react-native";

const envBaseUrl = (process.env.EXPO_PUBLIC_API_BASE_URL || "").trim();
const shouldLogNetworkErrors =
  (process.env.EXPO_PUBLIC_API_DEBUG || "").toLowerCase() === "true";

const getExpoLocalHost = () => {
  const debuggerHost =
    typeof Constants?.manifest === "object" && Constants.manifest?.debuggerHost
      ? Constants.manifest.debuggerHost
      : typeof Constants?.expoConfig === "object" &&
          typeof Constants.expoConfig?.hostUri === "string"
        ? Constants.expoConfig.hostUri
        : undefined;

  if (!debuggerHost || typeof debuggerHost !== "string") {
    return undefined;
  }

  const host = debuggerHost.split(",")[0].split(":")[0];
  return host?.trim() || undefined;
};

const getFallbackBaseUrl = () => {
  if (envBaseUrl) {
    return envBaseUrl;
  }

  const expoHost = getExpoLocalHost();

  if (Platform.OS === "android") {
    return expoHost ? `http://${expoHost}:8000` : "http://10.0.2.2:8000";
  }

  if (Platform.OS === "ios") {
    return expoHost ? `http://${expoHost}:8000` : "http://localhost:8000";
  }

  if (Platform.OS === "web") {
    const hostname = window.location.hostname || "localhost";
    return `http://${hostname}:8000`;
  }

  return expoHost ? `http://${expoHost}:8000` : "http://192.168.1.100:8000";
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
      const token = globalThis.localStorage?.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch {
      // ignore storage access issues in Expo Go
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
        globalThis.localStorage?.removeItem("auth_token");
      } catch {
        // ignore storage access issues in Expo Go
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
