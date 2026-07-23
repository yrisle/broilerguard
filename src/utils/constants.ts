// src/utils/constants.ts

export const API_CONFIG = {
  // CHANGE THIS TO YOUR SERVER URL
  BASE_URL: "http://192.168.1.100/broilerguard/api",
  TIMEOUT: 15000,
};

export const STORAGE_KEYS = {
  AUTH_TOKEN: "auth_token",
  USER_DATA: "user_data",
  THEME: "theme",
  NOTIFICATIONS: "notifications",
  SETTINGS: "settings",
};

export const COLORS = {
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

  text: "#3E2C1C",
  textSecondary: "#5C4A1E",
  textMuted: "#8B7355",

  background: "#FFFCF2",
  backgroundSecondary: "#FFF8E0",
  card: "#FFFFFF",

  border: "rgba(255, 214, 46, 0.2)",
  shadow: "rgba(139, 115, 30, 0.06)",
};

export const FONTS = {
  regular: "Inter_400Regular",
  medium: "Inter_500Medium",
  semiBold: "Inter_600SemiBold",
  bold: "Inter_700Bold",
  extraBold: "Inter_800ExtraBold",
};

export const SPACING = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
};

export const SIZES = {
  headerHeight: 70,
  sidebarWidth: 280,
  borderRadius: 16,
  borderRadiusSmall: 12,
  borderRadiusLarge: 24,
};

export const CHART_COLORS = {
  temperature: "#E67E22",
  humidity: "#2980B9",
  feed: "#E6B800",
  water: "#27AE60",
  healthy: "#27AE60",
  weak: "#F39C12",
  unhealthy: "#E74C3C",
};

export const STATUS = {
  HEALTHY: "healthy",
  WEAK: "weak",
  UNHEALTHY: "unhealthy",
  ON: "ON",
  OFF: "OFF",
  NORMAL: "normal",
  WARNING: "warning",
  DANGER: "danger",
};

export const TIME_PERIODS = {
  "24H": "24h",
  "7D": "7d",
  "30D": "30d",
  CUSTOM: "custom",
} as const;

export const DEFAULT_SETTINGS = {
  timezone: "Asia/Manila",
  dateFormat: "F d, Y",
  temperatureUnit: "celsius",
  language: "en",
  refreshInterval: 30,
  enableSound: true,
  enableBrowserNotifications: true,
  alertDuration: 5,
  sessionTimeout: 30,
  twoFactorAuth: false,
  loginAttempts: 5,
  autoBackup: true,
  backupFrequency: "daily",
  dataRetentionDays: 7,
  theme: "light",
  compactView: false,
  showCharts: true,
} as const;
