// src/context/AuthContext.tsx
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter, useSegments } from "expo-router";
import React, { createContext, useContext, useEffect, useState } from "react";
import { API_BASE_URL } from "../api/client";
import { auth } from "../api/endpoints/auth";

interface AuthContextType {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: any;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  validateToken: () => Promise<boolean>;
}

export const AuthContext = createContext<AuthContextType | undefined>(
  undefined,
);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [user, setUser] = useState<any>(null);
  const router = useRouter();
  const segments = useSegments();

  useEffect(() => {
    validateToken();
  }, []);

  useEffect(() => {
    if (!isLoading) {
      const inAuthGroup = segments[0] === "login";

      if (!isAuthenticated && !inAuthGroup) {
        router.replace("/login");
      } else if (isAuthenticated && inAuthGroup) {
        router.replace("/(tabs)/home");
      }
    }
  }, [isAuthenticated, isLoading, segments]);

  const validateToken = async (): Promise<boolean> => {
    try {
      const token = await AsyncStorage.getItem("auth_token");

      if (!token) {
        setIsAuthenticated(false);
        setUser(null);
        setIsLoading(false);
        return false;
      }

      try {
        const response = await auth.validate();
        console.log("📥 Validate response:", response.data);

        if (response.data.success) {
          setIsAuthenticated(true);
          setUser(response.data.user || { username: "admin" });
          setIsLoading(false);
          return true;
        } else {
          await AsyncStorage.removeItem("auth_token");
          setIsAuthenticated(false);
          setUser(null);
          setIsLoading(false);
          return false;
        }
      } catch (error) {
        console.log("Token validation failed:", error);
        await AsyncStorage.removeItem("auth_token");
        setIsAuthenticated(false);
        setUser(null);
        setIsLoading(false);
        return false;
      }
    } catch (error) {
      console.error("Validation error:", error);
      setIsAuthenticated(false);
      setUser(null);
      setIsLoading(false);
      return false;
    }
  };

  const login = async (username: string, password: string) => {
    setIsLoading(true);
    try {
      console.log("========================================");
      console.log("🔐 LOGIN ATTEMPT");
      console.log("📍 URL:", `${API_BASE_URL}/auth/login.php`);
      console.log("👤 Username:", username);
      console.log("========================================");

      const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ username, password }),
      });

      console.log("📥 Response status:", response.status);

      const responseText = await response.text();
      console.log("📥 Raw response:", responseText);

      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        console.error("❌ JSON Parse error:", e);
        throw new Error("Invalid server response format");
      }

      console.log("📥 Parsed data:", data);

      if (data.success) {
        const { token, user } = data;
        await AsyncStorage.setItem("auth_token", token);
        if (user) {
          await AsyncStorage.setItem("user", JSON.stringify(user));
          setUser(user);
        }
        setIsAuthenticated(true);
        console.log("✅ Login successful!");

        try {
          router.replace("/(tabs)/home");
        } catch (navError) {
          console.log("Navigation error:", navError);
          // Fallback - use window.location for web
          if (typeof window !== "undefined") {
            window.location.href = "/";
          }
        }
      } else {
        throw new Error(data.message || "Login failed");
      }
    } catch (error: any) {
      console.error("❌ Login error:", error);

      let errorMessage = "Network error. Please check your connection.";

      if (error.code === "ECONNABORTED") {
        errorMessage = "Connection timeout. Server is not responding.";
      } else if (error.message?.includes("Network Error")) {
        errorMessage = `Cannot reach server at ${API_BASE_URL}`;
      } else if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      } else if (error.message) {
        errorMessage = error.message;
      }

      throw new Error(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // ✅ FIX: LOGOUT FUNCTION - with safe navigation
  const logout = async () => {
    try {
      setIsLoading(true);

      // Try to call logout API
      try {
        await auth.logout();
      } catch (error) {
        console.log("Logout API error (ignored):", error);
      }

      // Clear storage
      await AsyncStorage.removeItem("auth_token");
      await AsyncStorage.removeItem("user");

      // Update state
      setIsAuthenticated(false);
      setUser(null);

      console.log("✅ Logout successful!");

      // ✅ SAFE NAVIGATION - with try/catch
      try {
        router.replace("/login");
      } catch (navError) {
        console.log("Navigation error during logout:", navError);
        // Fallback navigation
        try {
          router.push("/login");
        } catch (e) {
          console.log("Fallback navigation also failed:", e);
        }
      }
    } catch (error) {
      console.error("❌ Logout error:", error);
      // Even if there's an error, try to clear state
      setIsAuthenticated(false);
      setUser(null);
      await AsyncStorage.removeItem("auth_token");
      await AsyncStorage.removeItem("user");

      try {
        router.replace("/login");
      } catch (navError) {
        console.log("Navigation error during logout fallback:", navError);
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        isAuthenticated,
        isLoading,
        user,
        login,
        logout,
        validateToken,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
};
