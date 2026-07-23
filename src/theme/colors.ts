// src/theme/colors.ts

export const Colors = {
  light: {
    // PRIMARY COLORS - Green/Earthy Theme
    primary: "#8DB48E",
    primaryDark: "#4D724D",
    primaryLight: "#D4E8D4",

    // BACKGROUNDS
    background: "#F5F5F5",
    backgroundSecondary: "#E8F0E8",
    card: "#FFFFFF",

    // TEXT COLORS
    text: "#192119",
    textSecondary: "#4D724D",
    textMuted: "#6B8A6B",

    // STATUS COLORS
    success: "#4D724D",
    successLight: "#D4E8D4",

    warning: "#C8A24A",
    warningLight: "#F4EEDC",

    danger: "#A44A3F",
    dangerLight: "#F6E9E7",

    info: "#4F6C7A",
    infoLight: "#EAF0F3",

    orange: "#B9772A",
    orangeLight: "#F9EFE5",

    purple: "#8E44AD",

    // BORDER & SHADOW
    border: "rgba(77, 114, 77, 0.15)",
    shadow: "rgba(77, 114, 77, 0.08)",
    shadowDark: "rgba(77, 114, 77, 0.12)",

    // SIDEBAR
    sidebar: {
      background: "#3A5C3A",
      text: "#F5F5F5",
      muted: "#A8C8A8",
      hover: "rgba(141, 180, 142, 0.2)",
    },

    // COMPONENT SPECIFIC
    input: {
      background: "#FFFFFF",
      border: "#D4E8D4",
      focus: "#8DB48E",
    },
    button: {
      primary: "#4D724D",
      primaryText: "#FFFFFF",
      secondary: "#E8F0E8",
      secondaryText: "#2C3E2C",
    },
  },

  dark: {
    // PRIMARY COLORS - Dark Mode
    primary: "#8DB48E",
    primaryDark: "#4D724D",
    primaryLight: "#2C3E2C",

    // BACKGROUNDS
    background: "#1A1A1A",
    backgroundSecondary: "#2C3E2C",
    card: "#2C3E2C",

    // TEXT COLORS
    text: "#F5F5F5",
    textSecondary: "#A8C8A8",
    textMuted: "#6B8A6B",

    // STATUS COLORS
    success: "#4D724D",
    successLight: "#2C3E2C",

    warning: "#C8A24A",
    warningLight: "#3D3520",

    danger: "#A44A3F",
    dangerLight: "#3D201A",

    info: "#4F6C7A",
    infoLight: "#1A2D3D",

    orange: "#B9772A",
    orangeLight: "#3D2A1A",

    purple: "#8E44AD",
    purpleLight: "#2D1A3D",

    // BORDER & SHADOW
    border: "rgba(141, 180, 142, 0.15)",
    shadow: "rgba(0, 0, 0, 0.3)",
    shadowDark: "rgba(0, 0, 0, 0.5)",

    // SIDEBAR
    sidebar: {
      background: "#2C3E2C",
      text: "#E8F0E8",
      muted: "#6B8A6B",
      hover: "rgba(141, 180, 142, 0.15)",
    },

    // COMPONENT SPECIFIC
    input: {
      background: "#3D3D3D",
      border: "#4D724D",
      focus: "#8DB48E",
    },
    button: {
      primary: "#8DB48E",
      primaryText: "#1A1A1A",
      secondary: "#3D3D3D",
      secondaryText: "#F5F5F5",
    },
  },
};

export type ColorScheme = "light" | "dark";
