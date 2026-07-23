// src/hooks/useTheme.ts
import { useColorScheme } from "react-native";
import { Colors } from "../theme/colors";

export const useTheme = () => {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? "light"];
  return { colors, isDark: colorScheme === "dark" };
};
