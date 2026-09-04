// src/api/endpoints/sensors.ts
import api from "../client";

export const sensors = {
  // Get all sensor data from ESP32
  getCurrent: () => api.get("/sensor"),

  getTemperature: () => api.get("/sensor"),
  getHumidity: () => api.get("/sensor"),
  getFeed: () => api.get("/sensor"),
  getWater: () => api.get("/sensor"),
  getChickenStatus: () => api.get("/sensor"),

  // Light Control
  getLightStatus: () => api.get("/sensor"),
  controlLight: (data: { status: string }) =>
    api.get(`/light_${data.status.toLowerCase()}`),
  setLightBrightness: (data: { brightness: number }) =>
    api.post("/light/brightness", data),
  getLightSchedule: () => api.get("/light/schedule"),
  updateLightSchedule: (data: {
    onTime: string;
    offTime: string;
    enabled: boolean;
  }) => api.post("/light/schedule", data),
};
