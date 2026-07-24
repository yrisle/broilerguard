// src/context/AuthContext.tsx
import React, { createContext, useContext, useEffect, useState } from "react";

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
  // ✅ Lagi nang may user (auto-login)
  const [user, setUser] = useState<any>({
    id: 1,
    username: "admin",
    name: "Admin User",
    role: "admin",
  });
  const [isLoading, setIsLoading] = useState(false);

  // ✅ Hindi na nag-che-check ng authentication
  useEffect(() => {
    // Simple loading simulation (optional)
    setIsLoading(false);
  }, []);

  // ✅ Login function (hindi na ginagamit pero kailangan para sa interface)
  const login = async (username: string, password: string) => {
    // Auto-login agad
    setUser({
      id: 1,
      username: "admin",
      name: "Admin User",
      role: "admin",
    });
  };

  // ✅ Logout function (hindi na ginagamit pero kailangan para sa interface)
  const logout = async () => {
    // Hindi na naglo-logout, pero pwede ninyong i-implement kung gusto
    console.log("Logout disabled - always logged in");
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        login,
        logout,
        isAuthenticated: true, // ✅ Lagi nang authenticated
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
