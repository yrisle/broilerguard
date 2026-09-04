// src/api/endpoints/automation.ts
import { esp32Api } from "../client";

export const automation = {
  // Fan Control - ESP32
  fan: {
    getStatus: () => esp32Api.get("/sensor"),
    toggle: (status: "ON" | "OFF") =>
      esp32Api.get(`/fan_${status.toLowerCase()}`),
    updateSettings: (settings: {
      auto_mode: boolean;
      temp_on: number;
      temp_off: number;
    }) => esp32Api.post("/fan/settings", settings),
    resetOverride: () => esp32Api.post("/fan/reset"),
  },

  // Water Pump - ESP32
  pump: {
    getStatus: () => esp32Api.get("/sensor"),
    toggle: (status: "ON" | "OFF") => {
      if (status === "ON") {
        return esp32Api.get("/pump_on");
      } else {
        return esp32Api.get("/pump_off");
      }
    },
    release: (duration: number) => esp32Api.get("/pump_on"),
    updateSchedules: (schedules: any[]) =>
      esp32Api.post("/pump/schedules", schedules),
    toggleAuto: (enabled: boolean) => esp32Api.post("/pump/auto", { enabled }),
    resetOverride: () => esp32Api.post("/pump/reset"),
  },

  // Light Control - ESP32
  light: {
    getStatus: () => esp32Api.get("/sensor"),
    toggle: (status: "ON" | "OFF") =>
      esp32Api.get(`/light_${status.toLowerCase()}`),
  },

  // Gate Control - ESP32
  gate: {
    getStatus: () => esp32Api.get("/sensor"),
    open: () => esp32Api.get("/gate_open"),
    close: () => esp32Api.get("/gate_close"),
    toggle: async () => {
      try {
        const response = await esp32Api.get("/sensor");
        const isOpen = response.data.gate === 1;
        if (isOpen) {
          return await esp32Api.get("/gate_close");
        } else {
          return await esp32Api.get("/gate_open");
        }
      } catch (error) {
        throw error;
      }
    },
  },
};
