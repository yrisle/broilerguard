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

  // Light Control Endpoints
  getLightStatus: () => api.get("/sensors/light"),

  controlLight: (data: { status: string }) =>
    api.post("/sensors/control-light", data),

  setLightBrightness: (data: { brightness: number }) =>
    api.post("/sensors/set-brightness", data),

  getLightSchedule: () => api.get("/sensors/light-schedule"),

  updateLightSchedule: (data: { 
    onTime: string; 
    offTime: string; 
    enabled: boolean 
  }) => api.post("/sensors/update-light-schedule", data),
};