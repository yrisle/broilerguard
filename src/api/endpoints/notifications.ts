// src/api/endpoints/notifications.ts
import api from "../client";

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
