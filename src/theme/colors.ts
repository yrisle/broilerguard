// src/theme/colors.ts

export const Colors = {
  light: {
    primary: "#FFD62E",
    primaryDark: "#E6B800",
    primaryLight: "#FFF3CC",

    success: "#27AE60",
    successLight: "#E8F5E9",

    warning: "#F39C12",
    warningLight: "#FFF8E1",

    danger: "#E74C3C",
    dangerLight: "#FDEDEC",

    info: "#2980B9",
    infoLight: "#EBF5FB",

    orange: "#E67E22",
    orangeLight: "#FDF2E9",

    background: "#FFFCF2",
    backgroundSecondary: "#FFF8E0",
    card: "#FFFFFF",

    text: "#3E2C1C",
    textSecondary: "#5C4A1E",
    textMuted: "#8B7355",

    border: "rgba(255, 214, 46, 0.2)",
    shadow: "rgba(139, 115, 30, 0.06)",
    shadowDark: "rgba(139, 115, 30, 0.1)",

    sidebar: {
      background: "#5C3D2E",
      text: "#E8D5C4",
      muted: "#B8977A",
      hover: "rgba(255, 214, 46, 0.12)",
    },
  },

  dark: {
    primary: "#FFD62E",
    primaryDark: "#E6B800",
    primaryLight: "#2D2D1A",

    success: "#2ECC71",
    successLight: "#1A2D1A",

    warning: "#F1C40F",
    warningLight: "#2D2D1A",

    danger: "#E74C3C",
    dangerLight: "#2D1A1A",

    info: "#3498DB",
    infoLight: "#1A2D3D",

    orange: "#E67E22",
    orangeLight: "#2D1A0D",

    background: "#1A1A1A",
    backgroundSecondary: "#2D2D2D",
    card: "#2D2D2D",

    text: "#F5F0E0",
    textSecondary: "#C4B8A0",
    textMuted: "#8B7A5A",

    border: "rgba(255, 214, 46, 0.1)",
    shadow: "rgba(0, 0, 0, 0.3)",
    shadowDark: "rgba(0, 0, 0, 0.5)",

    sidebar: {
      background: "#2D1A0D",
      text: "#E8D5C4",
      muted: "#8B7A5A",
      hover: "rgba(255, 214, 46, 0.08)",
    },
  },
};

export type ColorScheme = "light" | "dark";
