// src/api/endpoints/sensors.ts
import api from "../client";

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
