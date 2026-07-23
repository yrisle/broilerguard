// src/theme/ThemeContext.tsx
import React, { createContext, useContext, useState } from "react";
import { useColorScheme } from "react-native";

type ThemeType = "light" | "dark";

interface ThemeContextType {
  theme: ThemeType;
  toggleTheme: () => void;
  colors: {
    background: string;
    card: string;
    text: string;
    textSecondary: string;
    accent: string;
    border: string;
  };
}

const lightColors = {
  background: "#FFFCF2",
  card: "#FFFFFF",
  text: "#3E2C1C",
  textSecondary: "#8B7355",
  accent: "#FFD62E",
  border: "rgba(255, 214, 46, 0.2)",
};

const darkColors = {
  background: "#1A1A1A",
  card: "#2D2D2D",
  text: "#F5F0E0",
  textSecondary: "#B8A88A",
  accent: "#FFD62E",
  border: "rgba(255, 214, 46, 0.1)",
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const systemTheme = useColorScheme();
  const [theme, setTheme] = useState<ThemeType>(systemTheme || "light");

  const toggleTheme = () => {
    setTheme(theme === "light" ? "dark" : "light");
  };

  const colors = theme === "light" ? lightColors : darkColors;

  return (
    <ThemeContext.Provider value={{ theme, toggleTheme, colors }}>
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (context === undefined) {
    throw new Error("useTheme must be used within a ThemeProvider");
  }
  return context;
};
