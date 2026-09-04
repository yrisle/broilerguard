// src/api/endpoints/automation.ts
import api from "../client";

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

  // Water Pump
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
    open: () => api.get("/gate_open"), // ESP32 endpoint
    close: () => api.get("/gate_close"), // ESP32 endpoint
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
