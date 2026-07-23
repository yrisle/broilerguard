// src/theme/typography.ts

import { Platform } from "react-native";

export const Typography = {
  fonts: {
    regular: Platform.OS === "ios" ? "Inter-Regular" : "Inter_400Regular",
    medium: Platform.OS === "ios" ? "Inter-Medium" : "Inter_500Medium",
    semiBold: Platform.OS === "ios" ? "Inter-SemiBold" : "Inter_600SemiBold",
    bold: Platform.OS === "ios" ? "Inter-Bold" : "Inter_700Bold",
    extraBold: Platform.OS === "ios" ? "Inter-ExtraBold" : "Inter_800ExtraBold",
  },

  sizes: {
    xs: 10,
    sm: 12,
    md: 14,
    base: 16,
    lg: 18,
    xl: 20,
    xxl: 24,
    xxxl: 28,
    huge: 32,
    giant: 40,
  },

  lineHeights: {
    tight: 1.2,
    normal: 1.5,
    relaxed: 1.8,
  },

  weights: {
    light: "300" as const,
    regular: "400" as const,
    medium: "500" as const,
    semiBold: "600" as const,
    bold: "700" as const,
    extraBold: "800" as const,
  },
};

export type TypographyVariant =
  | "h1"
  | "h2"
  | "h3"
  | "h4"
  | "h5"
  | "body1"
  | "body2"
  | "caption"
  | "button"
  | "overline";

export const getTypographyStyles = (variant: TypographyVariant) => {
  const styles = {
    h1: {
      fontSize: Typography.sizes.huge,
      fontWeight: Typography.weights.extraBold,
      lineHeight: Typography.sizes.huge * Typography.lineHeights.tight,
    },
    h2: {
      fontSize: Typography.sizes.xxxl,
      fontWeight: Typography.weights.bold,
      lineHeight: Typography.sizes.xxxl * Typography.lineHeights.tight,
    },
    h3: {
      fontSize: Typography.sizes.xxl,
      fontWeight: Typography.weights.bold,
      lineHeight: Typography.sizes.xxl * Typography.lineHeights.tight,
    },
    h4: {
      fontSize: Typography.sizes.xl,
      fontWeight: Typography.weights.semiBold,
      lineHeight: Typography.sizes.xl * Typography.lineHeights.normal,
    },
    h5: {
      fontSize: Typography.sizes.lg,
      fontWeight: Typography.weights.semiBold,
      lineHeight: Typography.sizes.lg * Typography.lineHeights.normal,
    },
    body1: {
      fontSize: Typography.sizes.base,
      fontWeight: Typography.weights.regular,
      lineHeight: Typography.sizes.base * Typography.lineHeights.normal,
    },
    body2: {
      fontSize: Typography.sizes.md,
      fontWeight: Typography.weights.regular,
      lineHeight: Typography.sizes.md * Typography.lineHeights.normal,
    },
    caption: {
      fontSize: Typography.sizes.sm,
      fontWeight: Typography.weights.regular,
      lineHeight: Typography.sizes.sm * Typography.lineHeights.normal,
    },
    button: {
      fontSize: Typography.sizes.md,
      fontWeight: Typography.weights.semiBold,
      lineHeight: Typography.sizes.md * Typography.lineHeights.tight,
    },
    overline: {
      fontSize: Typography.sizes.xs,
      fontWeight: Typography.weights.medium,
      lineHeight: Typography.sizes.xs * Typography.lineHeights.normal,
      textTransform: "uppercase" as const,
      letterSpacing: 1.5,
    },
  };

  return styles[variant];
};
