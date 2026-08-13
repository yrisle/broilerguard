// src/api/client.ts
import AsyncStorage from "@react-native-async-storage/async-storage";
import axios from "axios";

const NGROK_URL = "https://deltoidal-nonregeneratively-florance.ngrok-free.dev";

const getBaseUrl = () => {
  return `${NGROK_URL}/broilerguard/api`;
};

console.log("🔗 API URL:", getBaseUrl());

// ✅ CREATE AND EXPORT THE API INSTANCE
const api = axios.create({
  baseURL: getBaseUrl(),
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Add token interceptor
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
    return config;
  },
  (error) => Promise.reject(error),
);

// ✅ EXPORT AS DEFAULT
export default api;

// ✅ ALSO EXPORT NAMED (for compatibility)
export { api };

