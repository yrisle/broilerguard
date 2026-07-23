// src/api/endpoints/settings.ts
import api from "../client";

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
