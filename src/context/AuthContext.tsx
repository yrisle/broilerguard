// src/context/AuthContext.tsx
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { createContext, useContext, useEffect, useState } from 'react';
import { auth } from '../api/endpoints/auth';
import { API_BASE_URL } from '../api/client';
import { useRouter } from 'expo-router'; // ← ADD THIS

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
  const router = useRouter(); // ← ADD THIS

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

      try {
        const response = await auth.validate();
        if (response.data.success) {
          setIsAuthenticated(true);
          setUser(response.data.user || { username: 'admin' });
          setIsLoading(false);
          // ✅ Redirect to home if already authenticated
          router.replace('/(tabs)/home');
          return true;
        }
      } catch (error) {
        console.log('Token validation failed, clearing token');
      }
      
      await AsyncStorage.removeItem('auth_token');
      setIsAuthenticated(false);
      setIsLoading(false);
      return false;
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
      const url = `${API_BASE_URL}/auth/login`;
      console.log('========================================');
      console.log('🔐 LOGIN ATTEMPT');
      console.log('📍 URL:', url);
      console.log('👤 Username:', username);
      console.log('========================================');
      
      const response = await auth.login(username, password);
      
      console.log('📥 Response:', response.data);
      
      if (response.data.success) {
        const { token, user } = response.data;
        await AsyncStorage.setItem('auth_token', token);
        setUser(user);
        setIsAuthenticated(true);
        console.log('✅ Login successful!');
        
        // ✅ REDIRECT TO HOME AFTER LOGIN
        router.replace('/(tabs)/home');
      } else {
        throw new Error(response.data.message || 'Login failed');
      }
    } catch (error: any) {
      console.error('❌ Login error:', error);
      
      let errorMessage = 'Network error. Please check your connection.';
      
      if (error.code === 'ECONNABORTED') {
        errorMessage = 'Connection timeout. Server is not responding.';
      } else if (error.message?.includes('Network Error')) {
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

  const logout = async () => {
    try {
      await auth.logout();
    } catch (error) {
      console.error('Logout error:', error);
    }
    await AsyncStorage.removeItem('auth_token');
    setIsAuthenticated(false);
    setUser(null);
    // ✅ Redirect to login after logout
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