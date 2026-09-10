// src/context/AuthContext.tsx
import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import React, { createContext, useContext, useEffect, useState } from "react";
import api from "../api/client";

// Export User interface para magamit sa ibang files
export interface User {
  id: number;
  username: string;
  email?: string;
  full_name?: string;
  name?: string;
  role: string;
  phone?: string;
  farm_name?: string;
  avatar?: string | null;
  status?: string;
  source?: string; // 'users' | 'user_accounts' | 'admins'
}

// Payload para sa register
export interface RegisterPayload {
  fullName: string;
  username: string;
  email: string;
  password: string;
}

interface AuthContextType {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: User | null;
  token: string | null;
  login: (username: string, password: string) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  validateToken: () => Promise<boolean>;
  updateUser: (userData: Partial<User>) => Promise<void>;
}

// ✅ EXPORT AuthContext para magamit ng useAuth
export const AuthContext = createContext<AuthContextType | undefined>(
  undefined,
);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const router = useRouter();

  useEffect(() => {
    validateToken();
  }, []);

  const validateToken = async (): Promise<boolean> => {
    try {
      const storedToken = await AsyncStorage.getItem("auth_token");
      if (!storedToken) {
        setIsAuthenticated(false);
        setIsLoading(false);
        return false;
      }

      setToken(storedToken);

      try {
        const response = await api.get("/auth/validate.php");
        if (response.data.success) {
          const userData = response.data.user || response.data.data;
          if (userData) {
            const formattedUser: User = {
              id: userData.id || 0,
              username: userData.username || "guest",
              email: userData.email || "",
              full_name:
                userData.full_name ||
                userData.name ||
                userData.username ||
                "Guest User",
              name:
                userData.name ||
                userData.full_name ||
                userData.username ||
                "Guest User",
              role: userData.role || "viewer",
              phone: userData.phone || "",
              farm_name: userData.farm_name || "",
              avatar: userData.avatar || null,
              status: userData.status || "active",
              source: userData.source || "unknown",
            };
            setUser(formattedUser);
            await AsyncStorage.setItem("user", JSON.stringify(formattedUser));
          }
          setIsAuthenticated(true);
          setIsLoading(false);
          return true;
        } else {
          await AsyncStorage.removeItem("auth_token");
          await AsyncStorage.removeItem("user");
          setToken(null);
          setUser(null);
          setIsAuthenticated(false);
          setIsLoading(false);
          return false;
        }
      } catch (error) {
        await AsyncStorage.removeItem("auth_token");
        await AsyncStorage.removeItem("user");
        setToken(null);
        setUser(null);
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
        const { token: authToken, user: userData } = response.data;

        // Format user data
        const formattedUser: User = {
          id: userData.id || 0,
          username: userData.username || username,
          email: userData.email || "",
          full_name:
            userData.full_name ||
            userData.name ||
            userData.username ||
            username,
          name:
            userData.name ||
            userData.full_name ||
            userData.username ||
            username,
          role: userData.role || "viewer",
          phone: userData.phone || "",
          farm_name: userData.farm_name || "",
          avatar: userData.avatar || null,
          status: userData.status || "active",
          source: userData.source || "unknown",
        };

        await AsyncStorage.setItem("auth_token", authToken);
        await AsyncStorage.setItem("user", JSON.stringify(formattedUser));

        setToken(authToken);
        setUser(formattedUser);
        setIsAuthenticated(true);
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

  // ✅ REGISTER FUNCTION
  const register = async (payload: RegisterPayload) => {
    setIsLoading(true);
    try {
      const response = await api.post("/auth/register.php", {
        full_name: payload.fullName,
        username: payload.username,
        email: payload.email,
        password: payload.password,
      });

      if (!response.data.success) {
        throw new Error(response.data.message || "Registration failed");
      }

      // Option A: Auto-login kung may token na binabalik ang backend
      const { token: authToken, user: userData } = response.data;

      if (authToken && userData) {
        const formattedUser: User = {
          id: userData.id || 0,
          username: userData.username || payload.username,
          email: userData.email || payload.email,
          full_name: userData.full_name || userData.name || payload.fullName,
          name: userData.name || userData.full_name || payload.fullName,
          role: userData.role || "viewer",
          phone: userData.phone || "",
          farm_name: userData.farm_name || "",
          avatar: userData.avatar || null,
          status: userData.status || "active",
          source: userData.source || "unknown",
        };

        await AsyncStorage.setItem("auth_token", authToken);
        await AsyncStorage.setItem("user", JSON.stringify(formattedUser));

        setToken(authToken);
        setUser(formattedUser);
        setIsAuthenticated(true);
        router.replace("/(tabs)/home");
      }
      // Option B: kung walang token, hayaang mag-navigate ang register.tsx pabalik sa login
    } catch (error: any) {
      throw new Error(
        error.response?.data?.message || error.message || "Registration failed",
      );
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      // Try to call logout API
      await api.post("/auth/logout.php");
    } catch (error) {
      console.error("Logout API error:", error);
    } finally {
      // Always clear local storage and state
      await AsyncStorage.multiRemove(["auth_token", "user"]);
      setToken(null);
      setUser(null);
      setIsAuthenticated(false);
      router.replace("/login");
    }
  };

  const updateUser = async (userData: Partial<User>) => {
    try {
      if (user) {
        const updatedUser = { ...user, ...userData };
        setUser(updatedUser);
        await AsyncStorage.setItem("user", JSON.stringify(updatedUser));
      }
    } catch (error) {
      console.error("Error updating user:", error);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        isAuthenticated,
        isLoading,
        user,
        token,
        login,
        register,
        logout,
        validateToken,
        updateUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

// Export useAuth hook directly from here
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
};
