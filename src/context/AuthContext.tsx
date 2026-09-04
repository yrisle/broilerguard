// src/context/AuthContext.tsx
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { createContext, useContext, useEffect, useState } from "react";
import api from "../api/client";

interface AuthContextType {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: any;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  validateToken: () => Promise<boolean>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [user, setUser] = useState<any>(null);
  const router = useRouter();

  useEffect(() => {
    validateToken();
  }, []);

  const validateToken = async (): Promise<boolean> => {
    try {
      const token = await AsyncStorage.getItem("auth_token");
      if (!token) {
        setIsAuthenticated(false);
        setIsLoading(false);
        return false;
      }

      try {
        const response = await api.get("/auth/validate.php");
        if (response.data.success) {
          setIsAuthenticated(true);
          setUser(response.data.user);
          setIsLoading(false);
          return true;
        } else {
          await AsyncStorage.removeItem("auth_token");
          setIsAuthenticated(false);
          setIsLoading(false);
          return false;
        }
      } catch (error) {
        await AsyncStorage.removeItem("auth_token");
        setIsAuthenticated(false);
        setIsLoading(false);
        return false;
      }
    } catch (error) {
      setIsAuthenticated(false);
      setIsLoading(false);
      return false;
    }
  };

  const login = async (username: string, password: string) => {
    setIsLoading(true);
    try {
      const response = await api.post("/auth/login.php", {
        username,
        password,
      });

      if (response.data.success) {
        const { token, user } = response.data;
        await AsyncStorage.setItem("auth_token", token);
        await AsyncStorage.setItem("user", JSON.stringify(user));
        setIsAuthenticated(true);
        setUser(user);
        router.replace("/(tabs)/home");
      } else {
        throw new Error(response.data.message || "Login failed");
      }
    } catch (error: any) {
      throw new Error(
        error.response?.data?.message || error.message || "Login failed",
      );
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      await api.post("/auth/logout.php");
      await AsyncStorage.multiRemove(["auth_token", "user"]);
      setIsAuthenticated(false);
      setUser(null);
      router.replace("/login");
    } catch (error) {
      console.error("Logout error:", error);
      await AsyncStorage.multiRemove(["auth_token", "user"]);
      setIsAuthenticated(false);
      setUser(null);
      router.replace("/login");
    }
  };

  return (
    <AuthContext.Provider
      value={{ isAuthenticated, isLoading, user, login, logout, validateToken }}
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
