/**
 * Below are the colors that are used in the app. The colors are defined in the light and dark mode.
 * There are many other ways to style your app. For example, [Nativewind](https://www.nativewind.dev/), [Tamagui](https://tamagui.dev/), [unistyles](https://reactnativeunistyles.vercel.app), etc.
 */

import { Platform } from "react-native";

// Green/Earthy Color Palette
const tintColorLight = "#4D724D"; // Primary Dark Green
const tintColorDark = "#8DB48E"; // Primary Light Green

export const Colors = {
  light: {
    // Core Colors
    text: "#2C3E2C",
    background: "#F5F5F5",
    tint: tintColorLight,
    icon: "#6B8A6B",
    tabIconDefault: "#6B8A6B",
    tabIconSelected: tintColorLight,

    // Additional Colors
    primary: "#8DB48E",
    primaryDark: "#4D724D",
    primaryLight: "#D4E8D4",
    secondary: "#E8F0E8",
    card: "#FFFFFF",
    textSecondary: "#4D724D",
    textMuted: "#6B8A6B",

    // Status Colors
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
  },
  dark: {
    // Core Colors
    text: "#F5F5F5",
    background: "#1A1A1A",
    tint: tintColorDark,
    icon: "#6B8A6B",
    tabIconDefault: "#6B8A6B",
    tabIconSelected: tintColorDark,

    // Additional Colors
    primary: "#8DB48E",
    primaryDark: "#4D724D",
    primaryLight: "#2C3E2C",
    secondary: "#2C3E2C",
    card: "#2C3E2C",
    textSecondary: "#A8C8A8",
    textMuted: "#6B8A6B",

    // Status Colors
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
  },
};

export const Fonts = Platform.select({
  ios: {
    /** iOS `UIFontDescriptorSystemDesignDefault` */
    sans: "system-ui",
    /** iOS `UIFontDescriptorSystemDesignSerif` */
    serif: "ui-serif",
    /** iOS `UIFontDescriptorSystemDesignRounded` */
    rounded: "ui-rounded",
    /** iOS `UIFontDescriptorSystemDesignMonospaced` */
    mono: "ui-monospace",
  },
  default: {
    sans: "normal",
    serif: "serif",
    rounded: "normal",
    mono: "monospace",
  },
  web: {
    sans: "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    rounded:
      "'SF Pro Rounded', 'Hiragino Maru Gothic ProN', Meiryo, 'MS PGothic', sans-serif",
    mono: "SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace",
  },
});
