// src/context/AuthContext.tsx
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useContext, useEffect, useState } from 'react';
import { auth } from '../api/endpoints/auth';

interface AuthContextType {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: any;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  validateToken: () => Promise<boolean>;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [user, setUser] = useState<any>(null);

  useEffect(() => {
    validateToken();
  }, []);

  const validateToken = async (): Promise<boolean> => {
    try {
      const token = await AsyncStorage.getItem('auth_token');
      
      if (!token) {
        setIsAuthenticated(false);
        setIsLoading(false);
        return false;
      }

      // Validate token with backend
      const response = await auth.validate();
      
      if (response.data.success) {
        setIsAuthenticated(true);
        setUser(response.data.user || { username: 'admin' });
        setIsLoading(false);
        return true;
      } else {
        await AsyncStorage.removeItem('auth_token');
        setIsAuthenticated(false);
        setIsLoading(false);
        return false;
      }
    } catch (error) {
      console.error('Token validation error:', error);
      await AsyncStorage.removeItem('auth_token');
      setIsAuthenticated(false);
      setIsLoading(false);
      return false;
    }
  };

  const login = async (username: string, password: string) => {
    setIsLoading(true);
    try {
      const response = await auth.login(username, password);
      
      if (response.data.success) {
        const { token, user } = response.data;
        await AsyncStorage.setItem('auth_token', token);
        setUser(user);
        setIsAuthenticated(true);
      } else {
        throw new Error(response.data.message || 'Login failed');
      }
    } catch (error: any) {
      throw new Error(error.response?.data?.message || 'Network error');
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    try {
      await auth.logout();
    } catch (error) {
      console.error('Logout error:', error);
    }
    await AsyncStorage.removeItem('auth_token');
    setIsAuthenticated(false);
    setUser(null);
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
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};