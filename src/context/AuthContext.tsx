// src/context/AuthContext.tsx - Simplified version
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
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
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  let router;
  try {
    router = useRouter();
  } catch (e) {
    console.log("Router not available");
    router = null;
  }

  useEffect(() => {
    if (!isLoggingOut) {
      validateToken();
    }
  }, [isLoggingOut]);

  // ✅ Simplified navigation - no segments
  useEffect(() => {
    if (!isLoading && router && !isLoggingOut) {
      try {
        if (!isAuthenticated) {
          router.replace("/login");
        }
      } catch (error) {
        console.log("Navigation error:", error);
      }
    }
  }, [isAuthenticated, isLoading, isLoggingOut]);

  const validateToken = async (): Promise<boolean> => {
    if (isLoggingOut) {
      setIsLoading(false);
      return false;
    }

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
    setIsLoggingOut(false);
    setIsLoading(true);

    try {
      console.log("🔐 LOGIN ATTEMPT");

      const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ username, password }),
      });

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

        if (router) {
          try {
            router.replace("/(tabs)/home");
          } catch (navError) {
            console.log("Navigation error:", navError);
          }
        }
      } else {
        throw new Error(data.message || "Login failed");
      }
    } catch (error: any) {
      console.error("❌ Login error:", error);
      throw new Error(error.message || "Login failed");
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      console.log("🔄 Logging out...");

      setIsLoggingOut(true);
      await AsyncStorage.multiRemove(["auth_token", "user"]);
      setIsAuthenticated(false);
      setUser(null);

      console.log("✅ Logout successful!");

      if (router) {
        try {
          router.replace("/login");
        } catch (navError) {
          console.log("Navigation error during logout:", navError);
        }
      }
    } catch (error) {
      console.error("❌ Logout error:", error);
      setIsAuthenticated(false);
      setUser(null);
      await AsyncStorage.removeItem("auth_token");
      await AsyncStorage.removeItem("user");
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
