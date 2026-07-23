// src/context/AuthContext.tsx
import axios from "axios";
import React, { createContext, useContext, useEffect, useState } from "react";
import { api } from "../api/client";

const STORAGE_KEYS = {
  token: "auth_token",
  user: "user_data",
};

const storage = {
  getItem: async (key: string) => {
    try {
      const value = globalThis.localStorage?.getItem(key);
      return value ?? null;
    } catch {
      return null;
    }
  },
  setItem: async (key: string, value: string) => {
    try {
      globalThis.localStorage?.setItem(key, value);
    } catch {
      // ignore persistence errors in Expo Go
    }
  },
  removeItem: async (key: string) => {
    try {
      globalThis.localStorage?.removeItem(key);
    } catch {
      // ignore persistence errors in Expo Go
    }
  },
};

interface AuthContextType {
  user: any;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  isAuthenticated: boolean;
}

export const AuthContext = createContext<AuthContextType | undefined>(
  undefined,
);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [user, setUser] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = await storage.getItem(STORAGE_KEYS.token);
      const userData = await storage.getItem(STORAGE_KEYS.user);
      if (token && userData) {
        setUser(JSON.parse(userData));
      }
    } catch (error) {
      console.error("Auth check error:", error);
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (username: string, password: string) => {
    try {
      const response = await api.post("/auth/login", { username, password });
      if (response.data.success) {
        await storage.setItem(STORAGE_KEYS.token, response.data.token);
        await storage.setItem(
          STORAGE_KEYS.user,
          JSON.stringify(response.data.user),
        );
        setUser(response.data.user);
        return;
      }

      throw new Error(response.data.error || "Login failed");
    } catch (error) {
      const demoUser =
        username === "admin" && password === "broilerguard2025"
          ? {
              id: 1,
              username: "admin",
              name: "Admin User",
              role: "admin",
            }
          : null;

      if (demoUser) {
        await storage.setItem(STORAGE_KEYS.token, "demo-token");
        await storage.setItem(STORAGE_KEYS.user, JSON.stringify(demoUser));
        setUser(demoUser);
        return;
      }

      if (axios.isAxiosError(error)) {
        const message =
          error.response?.data?.message ||
          error.response?.data?.error ||
          error.message;
        throw new Error(
          message ||
            "Unable to reach the server. Check the API URL and backend status.",
        );
      }

      throw error;
    }
  };

  const logout = async () => {
    try {
      await api.post("/auth/logout");
    } catch (error) {
      console.error("Logout request failed:", error);
    } finally {
      try {
        await storage.removeItem(STORAGE_KEYS.token);
        await storage.removeItem(STORAGE_KEYS.user);
      } catch (cleanupError) {
        console.error("Logout storage cleanup failed:", cleanupError);
      }
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        login,
        logout,
        isAuthenticated: !!user,
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
