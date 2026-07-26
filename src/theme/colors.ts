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
    textMuted: "#1a201a",

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
    // PRIMARY COLORS - Dark Mode (Lightened)
    primary: "#A8C8A8",
    primaryDark: "#6B8A6B",
    primaryLight: "#3D5C3D",

    // BACKGROUNDS - Mas light na dark
    background: "#E8EDE8",
    backgroundSecondary: "#D4DCD4",
    card: "#F0F4F0",

    // TEXT COLORS - Mas maliwanag
    text: "#1A2A1A",
    textSecondary: "#3A5C3A",
    textMuted: "#5A7A5A",

    // STATUS COLORS - Lightened
    success: "#5A8A5A",
    successLight: "#D4E8D4",

    warning: "#D4B05A",
    warningLight: "#F4EEDC",

    danger: "#B85A4A",
    dangerLight: "#F6E9E7",

    info: "#5A7A8A",
    infoLight: "#EAF0F3",

    orange: "#C89A3A",
    orangeLight: "#F9EFE5",

    purple: "#9A5AAA",
    purpleLight: "#EDE5F0",

    // BORDER & SHADOW - Mas visible
    border: "rgba(77, 114, 77, 0.25)",
    shadow: "rgba(77, 114, 77, 0.15)",
    shadowDark: "rgba(77, 114, 77, 0.2)",

    // SIDEBAR - Mas light
    sidebar: {
      background: "#4A6A4A",
      text: "#F5F8F5",
      muted: "#B8D0B8",
      hover: "rgba(168, 200, 168, 0.25)",
    },

    // COMPONENT SPECIFIC - Lightened
    input: {
      background: "#F5F8F5",
      border: "#A8C8A8",
      focus: "#8DB48E",
    },
    button: {
      primary: "#5A8A5A",
      primaryText: "#FFFFFF",
      secondary: "#D4DCD4",
      secondaryText: "#1A2A1A",
    },
  },
};

export type ColorScheme = "light" | "dark";