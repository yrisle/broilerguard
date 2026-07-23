// src/api/endpoints/dashboard.ts
import api from "../client";

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
