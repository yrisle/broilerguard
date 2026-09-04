// src/api/endpoints/dashboard.ts
import api from "../client";

export const dashboard = {
  // Get stats from database (historical data)
  getStats: () => api.get("/dashboard/stats.php"),

  // Get chart data from database
  getChart: (period: string = "week") =>
    api.get(`/dashboard/chart.php?period=${period}`),

  getRecentActivity: (params?: {
    limit?: number;
    filter?: string;
    search?: string;
  }) => api.get("/dashboard/activity.php", { params }),
};
