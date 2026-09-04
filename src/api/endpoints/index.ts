// src/api/endpoints/index.ts
import api, { esp32Api } from "../client";

// ============================================
// AUTH ENDPOINTS - Database
// ============================================
export const auth = {
  login: (username: string, password: string) =>
    api.post("/auth/login.php", { username, password }),

  logout: () => api.post("/auth/logout.php"),

  validate: () => api.get("/auth/validate.php"),
};

// ============================================
// DASHBOARD ENDPOINTS - Database
// ============================================
export const dashboard = {
  getStats: () => api.get("/dashboard/stats.php"),

  getChart: (period: string = "week") =>
    api.get(`/dashboard/chart.php?period=${period}`),

  getRecentActivity: (params?: {
    limit?: number;
    filter?: string;
    search?: string;
  }) => api.get("/dashboard/activity.php", { params }),
};

// ============================================
// SENSORS ENDPOINTS - ESP32 for real-time, Database for history
// ============================================
export const sensors = {
  // Real-time data from ESP32
  getCurrent: () => esp32Api.get("/sensor"),

  // Historical data from database
  getTemperature: (period: string = "24h") =>
    api.get(`/sensors/temperature?period=${period}`),

  getHumidity: (period: string = "24h") =>
    api.get(`/sensors/humidity?period=${period}`),

  getFeed: () => api.get("/sensors/feed"),

  getWater: () => api.get("/sensors/water"),

  getChickenStatus: () => api.get("/sensors/chicken"),
};

// ============================================
// AUTOMATION ENDPOINTS - ESP32
// ============================================
export const automation = {
  // Fan Control
  fan: {
    getStatus: () => esp32Api.get("/sensor"),
    toggle: (status: "ON" | "OFF") =>
      esp32Api.get(`/fan_${status.toLowerCase()}`),
    updateSettings: (settings: {
      auto_mode: boolean;
      temp_on: number;
      temp_off: number;
    }) => esp32Api.post("/fan/settings", settings),
    resetOverride: () => esp32Api.post("/fan/reset"),
  },

  // Feed Dispenser
  feeder: {
    getStatus: () => esp32Api.get("/sensor"),
    dispense: (amount: number) => esp32Api.get("/dynamo_on"),
    refill: (amount: number) => esp32Api.get("/dynamo_on"),
    updateSchedules: (schedules: any[]) =>
      esp32Api.post("/feeder/schedules", schedules),
    toggleAuto: (enabled: boolean) =>
      esp32Api.post("/feeder/auto", { enabled }),
  },

  // Water Pump
  pump: {
    getStatus: () => esp32Api.get("/sensor"),
    toggle: (status: "ON" | "OFF") => {
      if (status === "ON") {
        return esp32Api.get("/pump_on");
      } else {
        return esp32Api.get("/pump_off");
      }
    },
    release: (duration: number) => esp32Api.get("/pump_on"),
    updateSchedules: (schedules: any[]) =>
      esp32Api.post("/pump/schedules", schedules),
    toggleAuto: (enabled: boolean) => esp32Api.post("/pump/auto", { enabled }),
    resetOverride: () => esp32Api.post("/pump/reset"),
  },

  // Light Control
  light: {
    getStatus: () => esp32Api.get("/sensor"),
    toggle: (status: "ON" | "OFF") =>
      esp32Api.get(`/light_${status.toLowerCase()}`),
  },

  // Gate Control
  gate: {
    getStatus: () => esp32Api.get("/sensor"),
    open: () => esp32Api.get("/gate_open"),
    close: () => esp32Api.get("/gate_close"),
    toggle: async () => {
      try {
        const response = await esp32Api.get("/sensor");
        const isOpen = response.data.gate === 1;
        if (isOpen) {
          return await esp32Api.get("/gate_close");
        } else {
          return await esp32Api.get("/gate_open");
        }
      } catch (error) {
        throw error;
      }
    },
  },
};

// ============================================
// NOTIFICATIONS ENDPOINTS - Database
// ============================================
export const notifications = {
  getAll: (limit: number = 50) => api.get(`/notifications.php?limit=${limit}`),

  markRead: (id: string) =>
    api.post("/notifications.php", { action: "mark_read", id }),

  markAllRead: () =>
    api.post("/notifications.php", { action: "mark_all_read" }),

  delete: (id: string) =>
    api.post("/notifications.php", { action: "delete", id }),

  deleteAll: () => api.post("/notifications.php", { action: "delete_all" }),

  getSettings: () => api.get("/notifications/settings.php"),

  updateSettings: (settings: any) =>
    api.post("/notifications/settings.php", settings),

  test: () => api.post("/notifications.php", { action: "test" }),
};

// ============================================
// SETTINGS ENDPOINTS - Database
// ============================================
export const settings = {
  get: () => api.get("/settings.php"),

  update: (settings: any) =>
    api.post("/settings.php", { action: "save_settings", ...settings }),

  changePassword: (data: {
    current_password: string;
    new_password: string;
    confirm_password: string;
  }) => api.post("/settings.php", { action: "change_password", ...data }),

  reset: () => api.post("/settings.php", { action: "reset_settings" }),

  clearCache: () => api.post("/settings.php", { action: "clear_cache" }),

  exportData: (type: string = "all") =>
    api.post("/settings.php", { action: "export_data", export_type: type }),

  clearLogs: () => api.post("/settings.php", { action: "clear_activity_logs" }),
};
