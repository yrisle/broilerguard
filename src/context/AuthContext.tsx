// src/context/AuthContext.tsx
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
  clearAuth: () => Promise<void>;
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
    // ✅ Only validate if not logging out
    if (!isLoggingOut) {
      validateToken();
    }
  }, [isLoggingOut]);

  // ✅ Navigation effect
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

  // ✅ Clear auth function
  const clearAuth = async () => {
    try {
      await AsyncStorage.multiRemove(["auth_token", "user"]);
      setIsAuthenticated(false);
      setUser(null);
    } catch (error) {
      console.error("Clear auth error:", error);
    }
  };

  const validateToken = async (): Promise<boolean> => {
    // ✅ Skip validation if logging out
    if (isLoggingOut) {
      console.log("⏭️ Skipping validation during logout");
      setIsLoading(false);
      return false;
    }

    try {
      const token = await AsyncStorage.getItem("auth_token");
      console.log("🔍 Token found:", token ? "Yes" : "No");

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
          await clearAuth();
          setIsLoading(false);
          return false;
        }
      } catch (error) {
        console.log("Token validation failed:", error);
        await clearAuth();
        setIsLoading(false);
        return false;
      }
    } catch (error) {
      console.error("Validation error:", error);
      await clearAuth();
      setIsLoading(false);
      return false;
    }
  };

  const login = async (username: string, password: string) => {
    // ✅ Reset logout flag
    setIsLoggingOut(false);
    setIsLoading(true);

    try {
      console.log("🔐 LOGIN ATTEMPT");
      console.log("📍 URL:", `${API_BASE_URL}/auth/login.php`);

      // ✅ Clear any old tokens first
      await clearAuth();

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

        // ✅ Save new token
        await AsyncStorage.setItem("auth_token", token);
        if (user) {
          await AsyncStorage.setItem("user", JSON.stringify(user));
          setUser(user);
        }

        setIsAuthenticated(true);
        console.log("✅ Login successful!");

        // ✅ Navigate to home
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
      await clearAuth();
      throw new Error(error.message || "Login failed");
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      console.log("🔄 Logging out...");

      // ✅ Set logging out flag FIRST
      setIsLoggingOut(true);

      // ✅ Clear everything
      await clearAuth();

      console.log("✅ Logout successful!");

      // ✅ Navigate to login
      if (router) {
        try {
          router.replace("/login");
        } catch (navError) {
          console.log("Navigation error during logout:", navError);
        }
      }
    } catch (error) {
      console.error("❌ Logout error:", error);
      await clearAuth();
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
        clearAuth,
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
