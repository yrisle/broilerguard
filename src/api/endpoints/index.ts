// src/api/endpoints/index.ts
import api from "../client";

// ============================================
// AUTH ENDPOINTS
// ============================================
export const auth = {
  login: (username: string, password: string) =>
    api.post("/auth/login.php", { username, password }),

  logout: () => api.post("/auth/logout.php"),

  validate: () => api.get("/auth/validate.php"),
};

// ============================================
// DASHBOARD ENDPOINTS
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
// SENSORS ENDPOINTS
// ============================================
export const sensors = {
  getCurrent: () => api.get("/sensor"), // ESP32 endpoint

  getTemperature: () => api.get("/sensor"),
  getHumidity: () => api.get("/sensor"),
  getFeed: () => api.get("/sensor"),
  getWater: () => api.get("/sensor"),
  getChickenStatus: () => api.get("/sensor"),
};

// ============================================
// AUTOMATION ENDPOINTS
// ============================================
export const automation = {
  // Fan Control
  fan: {
    getStatus: () => api.get("/sensor"),
    toggle: (status: "ON" | "OFF") => api.get(`/fan_${status.toLowerCase()}`),
    updateSettings: (settings: {
      auto_mode: boolean;
      temp_on: number;
      temp_off: number;
    }) => api.post("/fan/settings", settings),
    resetOverride: () => api.post("/fan/reset"),
  },

  // Feed Dispenser
  feeder: {
    getStatus: () => api.get("/sensor"),
    dispense: (amount: number) => api.get("/dynamo_on"),
    refill: (amount: number) => api.get("/dynamo_on"),
    updateSchedules: (schedules: any[]) =>
      api.post("/feeder/schedules", schedules),
    toggleAuto: (enabled: boolean) => api.post("/feeder/auto", { enabled }),
  },

  // Water Pump - FIXED
  pump: {
    getStatus: () => api.get("/sensor"),
    toggle: (status: "ON" | "OFF") => {
      if (status === "ON") {
        return api.get("/pump_on");
      } else {
        return api.get("/pump_off");
      }
    },
    release: (duration: number) => api.get("/pump_on"),
    updateSchedules: (schedules: any[]) =>
      api.post("/pump/schedules", schedules),
    toggleAuto: (enabled: boolean) => api.post("/pump/auto", { enabled }),
    resetOverride: () => api.post("/pump/reset"),
  },

  // Light Control
  light: {
    getStatus: () => api.get("/sensor"),
    toggle: (status: "ON" | "OFF") => api.get(`/light_${status.toLowerCase()}`),
  },

  // 🚪 GATE CONTROL
  gate: {
    getStatus: () => api.get("/sensor"),
    open: () => api.get("/gate_open"),
    close: () => api.get("/gate_close"),
    toggle: async () => {
      try {
        const response = await api.get("/sensor");
        const isOpen = response.data.gate === 1;
        if (isOpen) {
          return await api.get("/gate_close");
        } else {
          return await api.get("/gate_open");
        }
      } catch (error) {
        throw error;
      }
    },
  },
};

// ============================================
// NOTIFICATIONS ENDPOINTS
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
// SETTINGS ENDPOINTS
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
