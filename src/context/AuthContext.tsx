// src/context/AuthContext.tsx
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useContext, useEffect, useState } from 'react';
import { auth } from '../api/endpoints/auth';
import { API_BASE_URL } from '../api/client';
import { useRouter } from 'expo-router';

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
  const router = useRouter();

  useEffect(() => {
    validateToken();
  }, []);

  const validateToken = async (): Promise<boolean> => {
    try {
      const token = await AsyncStorage.getItem('auth_token');
      console.log('🔍 Token found:', token ? 'Yes' : 'No');
      
      if (!token) {
        setIsAuthenticated(false);
        setIsLoading(false);
        return false;
      }

      // ✅ Always consider token valid if it exists (for testing)
      setIsAuthenticated(true);
      setUser({ username: 'admin' });
      setIsLoading(false);
      return true;

      // Uncomment this when backend validation is fully working
      // try {
      //   const response = await auth.validate();
      //   if (response.data.success) {
      //     setIsAuthenticated(true);
      //     setUser(response.data.user || { username: 'admin' });
      //     setIsLoading(false);
      //     return true;
      //   }
      // } catch (error) {
      //   console.log('Token validation failed');
      // }
      
      // await AsyncStorage.removeItem('auth_token');
      // setIsAuthenticated(false);
      // setIsLoading(false);
      // return false;
    } catch (error) {
      console.error('Validation error:', error);
      setIsAuthenticated(false);
      setIsLoading(false);
      return false;
    }
  };

  const login = async (username: string, password: string) => {
    setIsLoading(true);
    try {
      console.log('🔐 Attempting login...');
      
      const response = await auth.login(username, password);
      console.log('📥 Login response:', response.data);
      
      if (response.data.success) {
        const { token, user } = response.data;
        await AsyncStorage.setItem('auth_token', token);
        setUser(user);
        setIsAuthenticated(true);
        console.log('✅ Login successful!');
        router.replace('/(tabs)/home');
      } else {
        throw new Error(response.data.message || 'Login failed');
      }
    } catch (error: any) {
      console.error('❌ Login error:', error);
      throw new Error(error.message || 'Login failed');
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
    router.replace('/login');
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