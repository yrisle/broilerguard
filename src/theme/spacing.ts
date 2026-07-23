// src/theme/spacing.ts

export const Spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
  xxxxl: 40,
  huge: 48,
  giant: 64,
};

export const Margins = {
  xs: { margin: Spacing.xs },
  sm: { margin: Spacing.sm },
  md: { margin: Spacing.md },
  lg: { margin: Spacing.lg },
  xl: { margin: Spacing.xl },
  xxl: { margin: Spacing.xxl },
  xxxl: { margin: Spacing.xxxl },

  horizontal: {
    xs: { marginHorizontal: Spacing.xs },
    sm: { marginHorizontal: Spacing.sm },
    md: { marginHorizontal: Spacing.md },
    lg: { marginHorizontal: Spacing.lg },
    xl: { marginHorizontal: Spacing.xl },
  },

  vertical: {
    xs: { marginVertical: Spacing.xs },
    sm: { marginVertical: Spacing.sm },
    md: { marginVertical: Spacing.md },
    lg: { marginVertical: Spacing.lg },
    xl: { marginVertical: Spacing.xl },
  },
};

export const Paddings = {
  xs: { padding: Spacing.xs },
  sm: { padding: Spacing.sm },
  md: { padding: Spacing.md },
  lg: { padding: Spacing.lg },
  xl: { padding: Spacing.xl },
  xxl: { padding: Spacing.xxl },
  xxxl: { padding: Spacing.xxxl },

  horizontal: {
    xs: { paddingHorizontal: Spacing.xs },
    sm: { paddingHorizontal: Spacing.sm },
    md: { paddingHorizontal: Spacing.md },
    lg: { paddingHorizontal: Spacing.lg },
    xl: { paddingHorizontal: Spacing.xl },
  },

  vertical: {
    xs: { paddingVertical: Spacing.xs },
    sm: { paddingVertical: Spacing.sm },
    md: { paddingVertical: Spacing.md },
    lg: { paddingVertical: Spacing.lg },
    xl: { paddingVertical: Spacing.xl },
  },
};

export const BorderRadius = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
  round: 9999,
};

export const Shadows = {
  sm: {
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  md: {
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 2,
  },
  lg: {
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 4,
  },
  xl: {
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 8,
  },
};
