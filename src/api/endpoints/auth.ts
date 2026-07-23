// src/api/endpoints/auth.ts
import { api } from "../client";

export const auth = {
  login: (username: string, password: string) =>
    api.post("/auth/login", { username, password }),

  logout: () => api.post("/auth/logout"),

  validate: () => api.get("/auth/validate"),

  changePassword: (data: {
    current_password: string;
    new_password: string;
    confirm_password: string;
  }) => api.post("/auth/change-password", data),
};
