// src/api/endpoints/automation.ts
import api from "../client";

export const automation = {
  // Fan Control
  fan: {
    getStatus: () => api.get("/automation/fan.php"),

    toggle: (status: "ON" | "OFF") =>
      api.post("/automation/fan.php", { action: "toggle", status }),

    updateSettings: (settings: {
      auto_mode: boolean;
      temp_on: number;
      temp_off: number;
    }) => api.post("/automation/fan.php", { action: "settings", ...settings }),

    resetOverride: () =>
      api.post("/automation/fan.php", { action: "reset_override" }),
  },

  // Feed Dispenser
  feeder: {
    getStatus: () => api.get("/automation/feeder.php"),

    dispense: (amount: number) =>
      api.post("/automation/feeder.php", { action: "dispense", amount }),

    refill: (amount: number) =>
      api.post("/automation/feeder.php", { action: "refill", amount }),

    updateSchedules: (schedules: any[]) =>
      api.post("/automation/feeder.php", { action: "schedules", schedules }),

    toggleAuto: (enabled: boolean) =>
      api.post("/automation/feeder.php", { action: "toggle_auto", enabled }),
  },

  // Water Pump
  pump: {
    getStatus: () => api.get("/automation/pump.php"),

    toggle: (status: "ON" | "OFF") =>
      api.post("/automation/pump.php", { action: "toggle", status }),

    release: (duration: number) =>
      api.post("/automation/pump.php", { action: "water", duration }),

    updateSchedules: (schedules: any[]) =>
      api.post("/automation/pump.php  ", { action: "schedules", schedules }),

    toggleAuto: (enabled: boolean) =>
      api.post("/automation/pump.php", { action: "toggle_auto", enabled }),

    resetOverride: () =>
      api.post("/automation/pump.php", { action: "reset_override" }),
  },
};
