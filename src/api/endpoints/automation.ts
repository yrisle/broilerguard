// src/api/endpoints/automation.ts
import api from "../client";

export const automation = {
  // Fan Control
  fan: {
    getStatus: () => api.get("/automation/fan"),

    toggle: (status: "ON" | "OFF") =>
      api.post("/automation/fan", { action: "toggle", status }),

    updateSettings: (settings: {
      auto_mode: boolean;
      temp_on: number;
      temp_off: number;
    }) => api.post("/automation/fan", { action: "settings", ...settings }),

    resetOverride: () =>
      api.post("/automation/fan", { action: "reset_override" }),
  },

  // Feed Dispenser
  feeder: {
    getStatus: () => api.get("/automation/feeder"),

    dispense: (amount: number) =>
      api.post("/automation/feeder", { action: "dispense", amount }),

    refill: (amount: number) =>
      api.post("/automation/feeder", { action: "refill", amount }),

    updateSchedules: (schedules: any[]) =>
      api.post("/automation/feeder", { action: "schedules", schedules }),

    toggleAuto: (enabled: boolean) =>
      api.post("/automation/feeder", { action: "toggle_auto", enabled }),
  },

  // Water Pump
  pump: {
    getStatus: () => api.get("/automation/pump"),

    toggle: (status: "ON" | "OFF") =>
      api.post("/automation/pump", { action: "toggle", status }),

    release: (duration: number) =>
      api.post("/automation/pump", { action: "water", duration }),

    updateSchedules: (schedules: any[]) =>
      api.post("/automation/pump", { action: "schedules", schedules }),

    toggleAuto: (enabled: boolean) =>
      api.post("/automation/pump", { action: "toggle_auto", enabled }),

    resetOverride: () =>
      api.post("/automation/pump", { action: "reset_override" }),
  },
};
