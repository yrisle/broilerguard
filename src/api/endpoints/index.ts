// src/api/endpoints/index.ts
import api from "../client";

// ============================================
// AUTH ENDPOINTS
// ============================================
export const auth = {
  login: (username: string, password: string) =>
    api.post("/auth/login", { username, password }),

  logout: () => api.post("/auth/logout"),

  validate: () => api.get("/auth/validate"),
};

// ============================================
// DASHBOARD ENDPOINTS
// ============================================
export const dashboard = {
  getStats: () => api.get("/dashboard/stats"),

  getChart: (period: string = "week") =>
    api.get(`/dashboard/chart?period=${period}`),

  getRecentActivity: (params?: {
    limit?: number;
    filter?: string;
    search?: string;
  }) => api.get("/dashboard/activity", { params }),
};

// ============================================
// SENSORS ENDPOINTS
// ============================================
export const sensors = {
  getCurrent: () => api.get("/sensors/current"),

  getTemperature: (period: string = "24h") =>
    api.get(`/sensors/temperature?period=${period}`),

  getHumidity: (period: string = "24h") =>
    api.get(`/sensors/humidity?period=${period}`),

  getFeed: () => api.get("/sensors/feed"),

  getWater: () => api.get("/sensors/water"),

  getChickenStatus: () => api.get("/sensors/chicken"),
};

// ============================================
// AUTOMATION ENDPOINTS
// ============================================
export const automation = {
  // Fan Control
  fan: {
    getStatus: () => api.get("/automation/fan"),

    toggle: (status: "ON" | "OFF") =>
      api.post("/automation/fan", { action: "toggle", status }),

    updateSettings: (settings: {
      auto_mode: boolean;
      temp_on: number;
      temp_off: number;
    }) => api.post("/automation/fan", { action: "settings", ...settings }),

    resetOverride: () =>
      api.post("/automation/fan", { action: "reset_override" }),
  },

  // Feed Dispenser
  feeder: {
    getStatus: () => api.get("/automation/feeder"),

    dispense: (amount: number) =>
      api.post("/automation/feeder", { action: "dispense", amount }),

    refill: (amount: number) =>
      api.post("/automation/feeder", { action: "refill", amount }),

    updateSchedules: (schedules: any[]) =>
      api.post("/automation/feeder", { action: "schedules", schedules }),

    toggleAuto: (enabled: boolean) =>
      api.post("/automation/feeder", { action: "toggle_auto", enabled }),
  },

  // Water Pump
  pump: {
    getStatus: () => api.get("/automation/pump"),

    toggle: (status: "ON" | "OFF") =>
      api.post("/automation/pump", { action: "toggle", status }),

    release: (duration: number) =>
      api.post("/automation/pump", { action: "water", duration }),

    updateSchedules: (schedules: any[]) =>
      api.post("/automation/pump", { action: "schedules", schedules }),

    toggleAuto: (enabled: boolean) =>
      api.post("/automation/pump", { action: "toggle_auto", enabled }),

    resetOverride: () =>
      api.post("/automation/pump", { action: "reset_override" }),
  },
};

// ============================================
// NOTIFICATIONS ENDPOINTS
// ============================================
export const notifications = {
  getAll: (limit: number = 50) => api.get(`/notifications?limit=${limit}`),

  markRead: (id: string) =>
    api.post("/notifications", { action: "mark_read", id }),

  markAllRead: () => api.post("/notifications", { action: "mark_all_read" }),

  delete: (id: string) => api.post("/notifications", { action: "delete", id }),

  deleteAll: () => api.post("/notifications", { action: "delete_all" }),

  getSettings: () => api.get("/notifications/settings"),

  updateSettings: (settings: any) =>
    api.post("/notifications/settings", settings),

  test: () => api.post("/notifications", { action: "test" }),
};

// ============================================
// SETTINGS ENDPOINTS
// ============================================
export const settings = {
  get: () => api.get("/settings"),

  update: (settings: any) =>
    api.post("/settings", { action: "save_settings", ...settings }),

  changePassword: (data: {
    current_password: string;
    new_password: string;
    confirm_password: string;
  }) => api.post("/settings", { action: "change_password", ...data }),

  reset: () => api.post("/settings", { action: "reset_settings" }),

  clearCache: () => api.post("/settings", { action: "clear_cache" }),

  exportData: (type: string = "all") =>
    api.post("/settings", { action: "export_data", export_type: type }),

  clearLogs: () => api.post("/settings", { action: "clear_activity_logs" }),
};
