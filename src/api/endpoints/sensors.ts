// src/api/endpoints/sensors.ts
import api, { esp32Api } from "../client";

export const sensors = {
  // Get current data from ESP32 (real-time)
  getCurrent: () => esp32Api.get("/sensor"),

  // Get historical data from database
  getTemperature: (period: string = "24h") =>
    api.get(`/sensors/temperature?period=${period}`),

  getHumidity: (period: string = "24h") =>
    api.get(`/sensors/humidity?period=${period}`),

  getFeed: () => api.get("/sensors/feed"),

  getWater: () => api.get("/sensors/water"),

  getChickenStatus: () => api.get("/sensors/chicken"),

  // Light Control
  getLightStatus: () => esp32Api.get("/sensor"),
  controlLight: (data: { status: string }) =>
    esp32Api.get(`/light_${data.status.toLowerCase()}`),
  setLightBrightness: (data: { brightness: number }) =>
    esp32Api.post("/light/brightness", data),
  getLightSchedule: () => esp32Api.get("/light/schedule"),
  updateLightSchedule: (data: {
    onTime: string;
    offTime: string;
    enabled: boolean;
  }) => esp32Api.post("/light/schedule", data),
};
