// src/context/AuthContext.tsx
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { createContext, useContext, useEffect, useState } from "react";

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
      if (token) {
        setIsAuthenticated(true);
        const userData = await AsyncStorage.getItem("user");
        if (userData) setUser(JSON.parse(userData));
        setIsLoading(false);
        return true;
      }
      setIsAuthenticated(false);
      setIsLoading(false);
      return false;
    } catch (error) {
      setIsAuthenticated(false);
      setIsLoading(false);
      return false;
    }
  };

  const login = async (username: string, password: string) => {
    setIsLoading(true);
    try {
      // ✅ Local login validation
      if (username === "admin" && password === "admin") {
        await AsyncStorage.setItem("auth_token", "dummy_token");
        await AsyncStorage.setItem(
          "user",
          JSON.stringify({ username: "admin" }),
        );
        setIsAuthenticated(true);
        setUser({ username: "admin" });
        router.replace("/(tabs)/home");
      } else {
        throw new Error("Invalid credentials");
      }
    } catch (error: any) {
      throw new Error(error.message || "Login failed");
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      await AsyncStorage.multiRemove(["auth_token", "user"]);
      setIsAuthenticated(false);
      setUser(null);
      router.replace("/login");
    } catch (error) {
      console.error("Logout error:", error);
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
